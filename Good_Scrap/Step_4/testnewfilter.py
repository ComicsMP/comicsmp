import asyncio
import aiohttp
import aiomysql
import random
import time
from bs4 import BeautifulSoup
from tqdm.asyncio import tqdm

# ✅ Database Configuration (using localhost)
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',  # Adjust if needed
    'db': 'comics_db'
}

# ✅ User-Agent rotation to avoid being blocked
USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/110.0.0.0 Safari/537.36",
]

# ✅ Filtering by "Years": Only process comics that either:
#     - Contain "Currently", OR
#     - Have a starting year (left side) >= 2000, OR
#     - Have an ending year (right side) >= 2000.
years_filter = """
  AND (
        `Years` LIKE '%Currently%' OR 
        CAST(SUBSTRING_INDEX(`Years`, '-', 1) AS UNSIGNED) >= 2000 OR 
        CAST(SUBSTRING_INDEX(`Years`, '-', -1) AS UNSIGNED) >= 2000
      )
"""

# ✅ Function to count the total number of comics matching the filters (missing UPC)
async def count_test_comics():
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor() as cursor:
        await cursor.execute(f"""
            SELECT COUNT(*) AS total
            FROM comics 
            WHERE (UPC IS NULL OR UPC = '')
              AND `Date` NOT LIKE '%Gold Age%'
              AND `Date` NOT LIKE '%Bronze Age%'
              {years_filter}
        """)
        result = await cursor.fetchone()
    connection.close()
    return result[0] if result else 0

# ✅ Fetch comics that are missing a UPC only, applying our filters.
async def fetch_test_comics(batch_size=1000):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute(f"""
            SELECT Issue_URL, Unique_ID, Comic_Title, Issue_Number, `Date`, `Years`
            FROM comics 
            WHERE (UPC IS NULL OR UPC = '')
              AND `Date` NOT LIKE '%Gold Age%'
              AND `Date` NOT LIKE '%Bronze Age%'
              {years_filter}
            LIMIT {batch_size}
        """)
        comics_data = await cursor.fetchall()
    connection.close()
    return comics_data

# ✅ Function to fetch UPC from a single comic.
async def fetch_upc(session, comic):
    url = comic['Issue_URL']
    unique_id = comic['Unique_ID']
    title = comic['Comic_Title']
    issue_number = comic['Issue_Number']
    
    headers = {'User-Agent': random.choice(USER_AGENTS)}
    try:
        async with session.get(url, headers=headers) as response:
            if response.status != 200:
                return {
                    'Unique_ID': unique_id,
                    'Comic_Title': title,
                    'Issue_Number': issue_number,
                    'Issue_URL': url,
                    'UPC': None,
                    'Status': f"HTTP Error {response.status}"
                }
            page_source = await response.text()
            soup = BeautifulSoup(page_source, 'html.parser')
            upc_code = None
            upc_label = soup.find('div', class_='m-0 f-12', string='UPC')
            if upc_label:
                upc_value = upc_label.find_next('span', class_='f-11')
                if upc_value:
                    upc_code = upc_value.get_text(strip=True)
            await asyncio.sleep(random.uniform(1, 3))
            status = "UPC Found" if upc_code else "UPC Not Found"
            if upc_code:
                print(f"[{unique_id}] UPC Found for '{title}' (Issue #{issue_number}): {upc_code}")
            return {
                'Unique_ID': unique_id,
                'Comic_Title': title,
                'Issue_Number': issue_number,
                'Issue_URL': url,
                'UPC': upc_code,
                'Status': status
            }
    except Exception as e:
        return {
            'Unique_ID': unique_id,
            'Comic_Title': title,
            'Issue_Number': issue_number,
            'Issue_URL': url,
            'UPC': None,
            'Status': f"Error: {str(e)}"
        }

# ✅ Function to update the database with the results from a batch.
async def update_database(results):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor() as cursor:
        for res in results:
            if res['UPC']:  # If a UPC was found, update it.
                await cursor.execute(
                    "UPDATE comics SET UPC=%s, last_checked=NOW() WHERE Issue_URL=%s",
                    (res['UPC'], res['Issue_URL'])
                )
            else:
                # Even if no UPC was found, update last_checked to avoid reprocessing immediately.
                await cursor.execute(
                    "UPDATE comics SET last_checked=NOW() WHERE Issue_URL=%s",
                    (res['Issue_URL'],)
                )
        await connection.commit()
    connection.close()

# ✅ Main function: Processes batches until no comics remain matching the filters.
async def main():
    batch_size = 1000
    print("\n=== TEST MODE: Scanning comics (only missing UPCs) based on filtering conditions ===\n")
    
    total_count = await count_test_comics()
    print(f"📊 Total comics to search (missing UPC, filtered by Date and Years): {total_count}\n")
    
    overall_scanned = 0
    overall_found = 0
    
    while True:
        comics_data = await fetch_test_comics(batch_size)
        batch_count = len(comics_data)
        if batch_count == 0:
            break
        print(f"📢 Processing a batch of {batch_count} comics...")
        results = []
        async with aiohttp.ClientSession() as session:
            tasks = [fetch_upc(session, comic) for comic in comics_data]
            for future in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc="UPC Extraction"):
                result = await future
                results.append(result)
        found_in_batch = sum(1 for res in results if res['UPC'])
        overall_scanned += batch_count
        overall_found += found_in_batch
        
        # Update the database after processing the batch.
        await update_database(results)
        
        print("\n--- Batch Summary ---")
        print(f"Comics processed in this batch: {batch_count}")
        print(f"Comics with new UPC found in batch: {found_in_batch}")
        print(f"Comics with no UPC found or errors in batch: {batch_count - found_in_batch}\n")
    
    overall_not_found = overall_scanned - overall_found
    print("\n=== FINAL SUMMARY ===")
    print(f"Total comics processed: {overall_scanned}")
    print(f"Total comics with new UPC found: {overall_found}")
    print(f"Total comics with no UPC found or errors: {overall_not_found}")
    print("\n=== PROCESS COMPLETE ===\n")

# ✅ Run the script
if __name__ == '__main__':
    asyncio.run(main())

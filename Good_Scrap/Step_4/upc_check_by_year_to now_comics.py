import asyncio
import aiohttp
import aiomysql
import random
import time
from bs4 import BeautifulSoup
from tqdm.asyncio import tqdm

# ✅ Database Configuration
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'db': 'comics_db'
}

# ✅ User-Agent rotation to avoid being blocked
USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/110.0.0.0 Safari/537.36",
]

# ✅ Count comics that need UPC checking based on the date year
async def count_comics_to_check():
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        # Count how many comics have UPC as NULL and are from year 2000 and beyond
        await cursor.execute("""
            SELECT COUNT(*) AS total_needed 
            FROM comics 
            WHERE UPC IS NULL 
            AND CAST(SUBSTRING_INDEX(date, ' ', -1) AS UNSIGNED) BETWEEN 2000 AND YEAR(NOW())
        """)
        total_needed = (await cursor.fetchone())['total_needed']

    connection.close()

    print("\n📊 **INITIAL STATUS REPORT**")
    print(f"✅ Total comics missing UPC (from 2000 and beyond): {total_needed}")
    return total_needed

# ✅ Fetch comics that need UPC checking (filtered by year)
async def fetch_comics(batch_size=1000):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute(f"""
            SELECT Issue_URL, Unique_ID, Comic_Title, Issue_Number, date 
            FROM comics 
            WHERE UPC IS NULL 
            AND CAST(SUBSTRING_INDEX(date, ' ', -1) AS UNSIGNED) BETWEEN 2000 AND YEAR(NOW())
            LIMIT {batch_size}
        """)
        comics_data = await cursor.fetchall()
    connection.close()
    return comics_data

# ✅ Function to fetch UPC from a single comic
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

            # ✅ Extract the UPC
            upc_code = None
            upc_label = soup.find('div', class_='m-0 f-12', string='UPC')
            if upc_label:
                upc_value = upc_label.find_next('span', class_='f-11')
                if upc_value:
                    upc_code = upc_value.get_text(strip=True)

            # ✅ Random delay between 1-3 seconds to avoid detection
            await asyncio.sleep(random.uniform(1, 3))

            return {
                'Unique_ID': unique_id,
                'Comic_Title': title,
                'Issue_Number': issue_number,
                'Issue_URL': url,
                'UPC': upc_code,
                'Status': "UPC Found" if upc_code else "UPC Not Found"
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

# ✅ Update database with found UPCs & mark them as checked
async def update_database(upc_results, processed_comics):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor() as cursor:
        for res in upc_results:
            if res['UPC']:  
                await cursor.execute(
                    "UPDATE comics SET UPC=%s, last_checked=NOW() WHERE Unique_ID=%s",
                    (res['UPC'], res['Unique_ID'])
                )

        # ✅ Ensure last_checked is updated for all comics processed
        unique_ids = [comic['Unique_ID'] for comic in processed_comics]
        if unique_ids:
            await cursor.executemany(
                "UPDATE comics SET last_checked=NOW() WHERE Unique_ID=%s", 
                [(uid,) for uid in unique_ids]
            )

        await connection.commit()
        print(f"\n📝 Updated last_checked for {len(unique_ids)} comics.\n")

    connection.close()

# ✅ Main function that runs in chunks & exits when finished
async def main():
    batch_size = 1000  # ✅ Process 1,000 rows at a time
    total_to_check = await count_comics_to_check()  # Get initial count

    if total_to_check == 0:
        print("\n✅ No comics need to be checked. Exiting script.")
        return  # Exit immediately if nothing needs to be processed

    total_processed = 0

    while True:
        comics_data = await fetch_comics(batch_size)

        if not comics_data:
            print("\n✅ No more comics left to scan. Exiting script.")
            break  # ✅ Exit when no more comics meet the condition

        print(f"\n📢 Processing {len(comics_data)} comics in this batch...\n")

        results = []
        async with aiohttp.ClientSession() as session:
            tasks = [fetch_upc(session, comic) for comic in comics_data]
            for future in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc="Scraping UPC Codes"):
                result = await future
                results.append(result)

        found_upcs = [r for r in results if r['UPC']]
        print("\n📊 SUMMARY for This Batch:")
        print(f"✅ Checked: {len(results)}")
        print(f"🟢 Found UPCs: {len(found_upcs)}")
        print(f"🔴 Missing UPCs: {len(results) - len(found_upcs)}")

        await update_database(found_upcs, comics_data)
        total_processed += len(comics_data)

        print(f"\n✅ Batch complete. Total comics processed so far: {total_processed}")

    print("\n🚀 Script finished. All eligible comics are scanned!")

# ✅ Run the script
if __name__ == '__main__':
    asyncio.run(main())

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

# SQL snippet for filtering based on the "Years" field:
years_filter = """
  AND (
        `Years` LIKE '%Current%' OR 
        (
          `Years` REGEXP '^[0-9]{4}$' AND CAST(`Years` AS UNSIGNED) >= 1990
        ) OR 
        (
          `Years` REGEXP '^[0-9]{4}-[0-9]{4}$' AND (
            CAST(SUBSTRING_INDEX(`Years`, '-', 1) AS UNSIGNED) >= 1990 OR 
            CAST(SUBSTRING_INDEX(`Years`, '-', -1) AS UNSIGNED) >= 1990
          )
        )
      )
"""

# ✅ Count new comics (never checked) excluding Gold and Bronze Age comics (Silver Age allowed)
async def count_new_comics():
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute(f"""
            SELECT COUNT(*) AS new_entries 
            FROM comics 
            WHERE last_checked IS NULL
              AND `Date` NOT LIKE '%Gold Age%'
              AND `Date` NOT LIKE '%Bronze Age%'
              {years_filter}
        """)
        new_entries = (await cursor.fetchone())['new_entries']
    connection.close()
    return new_entries

# ✅ Count outdated comics (30+ days old and missing UPC) excluding Gold, Silver, and Bronze Age comics
async def count_outdated_comics():
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute(f"""
            SELECT COUNT(*) AS outdated_entries 
            FROM comics 
            WHERE last_checked < DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND (UPC IS NULL OR UPC = '')
              AND `Date` NOT LIKE '%Gold Age%'
              AND `Date` NOT LIKE '%Silver Age%'
              AND `Date` NOT LIKE '%Bronze Age%'
              {years_filter}
        """)
        outdated_entries = (await cursor.fetchone())['outdated_entries']
    connection.close()
    return outdated_entries

# ✅ Fetch new comics in chunks excluding Gold and Bronze Age comics (Silver Age allowed)
async def fetch_new_comics(batch_size=1000):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute(f"""
            SELECT Issue_URL, Unique_ID, Comic_Title, Issue_Number 
            FROM comics 
            WHERE (last_checked IS NULL OR last_checked = '')
              AND `Date` NOT LIKE '%Gold Age%'
              AND `Date` NOT LIKE '%Bronze Age%'
              {years_filter}
            LIMIT {batch_size}
        """)
        comics_data = await cursor.fetchall()
    connection.close()
    return comics_data

# ✅ Fetch outdated comics (missing UPC) in chunks excluding Gold, Silver, and Bronze Age comics
async def fetch_outdated_comics(batch_size=1000):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute(f"""
            SELECT Issue_URL, Unique_ID, Comic_Title, Issue_Number 
            FROM comics 
            WHERE last_checked < DATE_SUB(NOW(), INTERVAL 30 DAY)
              AND (UPC IS NULL OR UPC = '')
              AND `Date` NOT LIKE '%Gold Age%'
              AND `Date` NOT LIKE '%Silver Age%'
              AND `Date` NOT LIKE '%Bronze Age%'
              {years_filter}
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

# ✅ Update database: update UPC and last_checked timestamp
async def update_database(results, processed_comics):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor() as cursor:
        # Update UPCs where found and update last_checked regardless
        for res in results:
            print("Updating row with Issue_URL:", res['Issue_URL'])
            await cursor.execute(
                "UPDATE comics SET UPC=%s, last_checked=NOW() WHERE Issue_URL=%s",
                (res['UPC'] if res['UPC'] else None, res['Issue_URL'])
            )

        # Ensure last_checked updates for all processed comics (for logging purposes)
        processed_comics_urls = [comic['Issue_URL'] for comic in processed_comics]
        if processed_comics_urls:
            print(f"\n🛠 Updating last_checked for {len(processed_comics_urls)} comics...")
            affected_rows = await cursor.executemany(
                "UPDATE comics SET last_checked=NOW() + INTERVAL 0 SECOND WHERE Issue_URL=%s", 
                [(url,) for url in processed_comics_urls]
            )
            if affected_rows == 0:
                print(f"\n✅ All {len(processed_comics_urls)} rows were already up-to-date.")
            else:
                print(f"\n✅ Expected to update {len(processed_comics_urls)} rows, MySQL actually updated {affected_rows} rows.")
        await connection.commit()
    connection.close()

# ✅ Main function that runs in two phases: first new comics, then outdated comics
async def main():
    batch_size = 1000  # Process 1,000 rows at a time

    # Phase 1: Process new comics
    new_entries = await count_new_comics()
    print("\n📊 **NEW COMICS PHASE**")
    print(f"✅ New entries with no last_checked (excluding Gold, Bronze Age; including Silver Age): {new_entries}")

    total_processed_new = 0
    while total_processed_new < new_entries:
        comics_data = await fetch_new_comics(batch_size)
        if not comics_data:
            print("\n✅ No more new comics left to scan.")
            break

        print(f"\n📢 Processing {len(comics_data)} new comics in this batch...\n")

        results = []
        async with aiohttp.ClientSession() as session:
            tasks = [fetch_upc(session, comic) for comic in comics_data]
            for future in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc="Scraping UPC Codes for New Comics"):
                result = await future
                results.append(result)

        found_upcs = [r for r in results if r['UPC']]
        print("\n📊 SUMMARY for New Comics Batch:")
        print(f"✅ Checked: {len(results)}")
        print(f"🟢 Found UPCs: {len(found_upcs)}")
        print(f"🔴 Missing UPCs: {len(results) - len(found_upcs)}")

        await update_database(results, comics_data)
        total_processed_new += len(comics_data)
        print(f"\n✅ New Comics Phase: Total comics processed so far: {total_processed_new}")

    # Phase 2: Process outdated comics (30+ days old and missing UPC)
    outdated_entries = await count_outdated_comics()
    print("\n📊 **OUTDATED COMICS PHASE**")
    print(f"✅ Outdated comics (30+ days old and missing UPC, excluding Gold, Silver, Bronze Age): {outdated_entries}")

    total_processed_outdated = 0
    while total_processed_outdated < outdated_entries:
        comics_data = await fetch_outdated_comics(batch_size)
        if not comics_data:
            print("\n✅ No more outdated comics left to scan.")
            break

        print(f"\n📢 Processing {len(comics_data)} outdated comics in this batch...\n")

        results = []
        async with aiohttp.ClientSession() as session:
            tasks = [fetch_upc(session, comic) for comic in comics_data]
            for future in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc="Scraping UPC Codes for Outdated Comics"):
                result = await future
                results.append(result)

        found_upcs = [r for r in results if r['UPC']]
        print("\n📊 SUMMARY for Outdated Comics Batch:")
        print(f"✅ Checked: {len(results)}")
        print(f"🟢 Found UPCs: {len(found_upcs)}")
        print(f"🔴 Missing UPCs: {len(results) - len(found_upcs)}")

        await update_database(results, comics_data)
        total_processed_outdated += len(comics_data)
        print(f"\n✅ Outdated Comics Phase: Total comics processed so far: {total_processed_outdated}")

    print("\n🚀 Script finished. All eligible comics are scanned!")

# ✅ Run the script
if __name__ == '__main__':
    asyncio.run(main())

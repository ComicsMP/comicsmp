import asyncio
import aiohttp
import aiomysql
import random
import time
from bs4 import BeautifulSoup
from tqdm.asyncio import tqdm

# ----- Database Configuration -----
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'db': 'comics_db'
}

# ----- Year Filters (Excluding Anything Before 1985) -----
TARGET_YEAR_FILTERS = ["2023-2023"]

# ----- User-Agent Rotation -----
USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/110.0.0.0 Safari/537.36",
]

# ----- Count and Fetch Functions -----
async def count_missing_upcs(year_filter):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute("""
            SELECT COUNT(*) AS missing_count 
            FROM comics 
            WHERE UPC IS NULL 
              AND Years = %s
        """, (year_filter,))
        row = await cursor.fetchone()
        missing_count = row['missing_count'] if row else 0
    await connection.ensure_closed()
    return missing_count

async def fetch_missing_upcs(year_filter, batch_size=1000):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute("""
            SELECT Issue_URL, Unique_ID, Comic_Title, Issue_Number 
            FROM comics 
            WHERE UPC IS NULL 
              AND Years = %s
            LIMIT %s
        """, (year_filter, batch_size))
        comics_data = await cursor.fetchall()
    await connection.ensure_closed()
    return comics_data

# ----- UPC Extraction via Asynchronous HTTP -----
async def fetch_upc(session, comic):
    """Scrape the UPC for a single comic entry."""
    url = comic['Issue_URL']
    headers = {'User-Agent': random.choice(USER_AGENTS)}
    
    try:
        async with session.get(url, headers=headers) as response:
            if response.status != 200:
                return {
                    'Issue_URL': url,
                    'UPC': None,
                    'Status': f"HTTP Error {response.status}"
                }

            page_source = await response.text()
            soup = BeautifulSoup(page_source, 'html.parser')
            
            # Extract the UPC code
            upc_code = None
            upc_label = soup.find('div', class_='m-0 f-12', string='UPC')
            if upc_label:
                upc_value = upc_label.find_next('span', class_='f-11')
                if upc_value:
                    upc_code = upc_value.get_text(strip=True)
            
            # Sleep 1-3 seconds to throttle requests
            await asyncio.sleep(random.uniform(1, 3))
            
            return {
                'Issue_URL': url,
                'UPC': upc_code,
                'Status': "UPC Found" if upc_code else "UPC Not Found"
            }
    except Exception as e:
        return {
            'Issue_URL': url,
            'UPC': None,
            'Status': f"Error: {str(e)}"
        }

# ----- Database Update Function -----
async def update_database(results):
    """Update the comics table with newly found UPCs, matching by Issue_URL."""
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor() as cursor:
        found_upcs = [res for res in results if res['UPC']]
        
        updated_count = 0
        for res in found_upcs:
            # Attempt update by Issue_URL
            await cursor.execute(
                """UPDATE comics 
                   SET UPC = %s,
                       last_checked = NOW()
                   WHERE Issue_URL = %s 
                     AND UPC IS NULL
                """,
                (res['UPC'], res['Issue_URL'])
            )
            # If rowcount > 0, the DB accepted the update
            if cursor.rowcount > 0:
                updated_count += 1
        
        await connection.commit()
    await connection.ensure_closed()

    print(f"\n📝 Database commit complete. Successfully updated {updated_count} comics with new UPCs.")
    if updated_count == 0 and found_upcs:
        print("⚠️ WARNING: No records were updated even though UPCs were found. "
              "Check that Issue_URL in your DB matches the script exactly!")

    # Show details of found UPCs
    for res in found_upcs:
        print(f"🔹 {res['Issue_URL']} - UPC: {res['UPC']}")

# ----- Main Async Routine -----
async def process_year(year_filter):
    """Process all comics for a particular year filter."""
    batch_size = 1000
    total_missing = await count_missing_upcs(year_filter)
    print(f"\n📊 **Processing Year: {year_filter}**")
    print(f"✅ Total comics with NULL UPC and Years = '{year_filter}': {total_missing}")
    
    total_processed = 0
    while total_processed < total_missing:
        # Grab a batch
        comics_data = await fetch_missing_upcs(year_filter, batch_size)
        if not comics_data:
            print(f"\n✅ No more comics left to scan for {year_filter}.")
            break
        
        print(f"\n📢 Processing {len(comics_data)} comics in this batch for {year_filter}...\n")
        results = []
        async with aiohttp.ClientSession() as session:
            tasks = [fetch_upc(session, comic) for comic in comics_data]
            for future in tqdm(asyncio.as_completed(tasks), 
                               total=len(tasks), 
                               desc=f"Scraping UPC Codes ({year_filter})"):
                result = await future
                results.append(result)
        
        # Update DB with any newly found UPC codes
        await update_database(results)
        
        # Increase processed count by however many comics were fetched
        total_processed += len(comics_data)
        print(f"\n✅ Total comics processed for {year_filter} so far: {total_processed}")
    
    print(f"\n🚀 Completed processing for {year_filter}!")

async def main():
    for year_filter in TARGET_YEAR_FILTERS:
        await process_year(year_filter)
    print("\n🚀 All selected years have been processed!")

# ----- Run the Script -----
if __name__ == '__main__':
    asyncio.run(main())

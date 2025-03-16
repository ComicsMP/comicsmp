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

# ----- User-Agent Rotation -----
USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/110.0.0.0 Safari/537.36",
]

# ----- Count and Fetch Functions -----
async def count_missing_upcs():
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute("""
            SELECT COUNT(*) AS missing_count 
            FROM comics 
            WHERE UPC IS NULL
        """)
        missing_count = (await cursor.fetchone())['missing_count']
    connection.close()
    return missing_count

async def fetch_missing_upcs(batch_size=1000):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute(f"""
            SELECT Issue_URL, Unique_ID, Comic_Title, Issue_Number 
            FROM comics 
            WHERE UPC IS NULL
            LIMIT {batch_size}
        """)
        comics_data = await cursor.fetchall()
    connection.close()
    return comics_data

# ----- UPC Extraction via Asynchronous HTTP -----
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
            
            # Extract the UPC code: look for a div with class "m-0 f-12" containing the text "UPC"
            upc_code = None
            upc_label = soup.find('div', class_='m-0 f-12', string='UPC')
            if upc_label:
                upc_value = upc_label.find_next('span', class_='f-11')
                if upc_value:
                    upc_code = upc_value.get_text(strip=True)
            
            # Delay to avoid detection
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

# ----- Database Update Function -----
async def update_database(results):
    """
    Updates the comics table by setting the UPC (if found) and updating the last_checked timestamp.
    """
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor() as cursor:
        for res in results:
            # Update last_checked regardless; if a UPC was found, update that field as well.
            await cursor.execute(
                "UPDATE comics SET UPC=%s, last_checked=NOW() WHERE Issue_URL=%s",
                (res['UPC'] if res['UPC'] else None, res['Issue_URL'])
            )
        await connection.commit()
    connection.close()

# ----- Main Async Routine -----
async def main():
    batch_size = 1000  # Process 1,000 rows at a time
    total_missing = await count_missing_upcs()
    print("\n📊 **MISSING UPCs PHASE**")
    print(f"✅ Total comics with NULL UPC: {total_missing}")
    
    total_processed = 0
    all_results = []  # Store results for all processed comics
    
    while total_processed < total_missing:
        comics_data = await fetch_missing_upcs(batch_size)
        if not comics_data:
            print("\n✅ No more comics left to scan.")
            break
        print(f"\n📢 Processing {len(comics_data)} comics in this batch...\n")
        results = []
        async with aiohttp.ClientSession() as session:
            tasks = [fetch_upc(session, comic) for comic in comics_data]
            for future in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc="Scraping UPC Codes"):
                result = await future
                results.append(result)
        
        # Print batch summary and detailed list for the batch
        found_upcs = [r for r in results if r['UPC']]
        print("\n📊 BATCH SUMMARY:")
        print(f"✅ Checked: {len(results)}")
        print(f"🟢 Found UPCs: {len(found_upcs)}")
        print(f"🔴 Missing UPCs: {len(results) - len(found_upcs)}")
        print("\n📝 Details (URL - UPC):")
        for rec in found_upcs:
            print(f"{rec['Issue_URL']} - {rec['UPC']}")
        
        # Update the database for this batch so they won't be processed again.
        await update_database(results)
        
        all_results.extend(results)
        total_processed += len(comics_data)
        print(f"\n✅ Total comics processed so far: {total_processed}")
    
    print("\n🚀 ALL BATCHES COMPLETED. SUMMARY OF FOUND UPCs:")
    overall_found = [r for r in all_results if r['UPC']]
    if overall_found:
        for rec in overall_found:
            print(f"{rec['Issue_URL']} - {rec['UPC']}")
    else:
        print("No UPC codes were found.")

    # Prompt user for confirmation to update the database.
    choice = input("\nDo you want to finalize these updates? (yes/no): ").strip().lower()
    if choice in ('yes', 'y'):
        print("\n✅ Database updates have been finalized.")
    else:
        print("\n⚠️ You chose not to finalize the updates. You may need to revert changes manually if needed.")

# ----- Run the Script -----
if __name__ == '__main__':
    asyncio.run(main())

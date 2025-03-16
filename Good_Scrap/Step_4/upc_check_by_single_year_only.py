import asyncio
import aiohttp
import aiomysql
import random
import time
from bs4 import BeautifulSoup
from tqdm.asyncio import tqdm

# 1) Database configuration
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'db': 'comics_db'
}

# 2) Choose the date/name filter (change ONLY this line to something else later)
TARGET_DATE_FILTER = "Modern Age"

# 3) User-Agent rotation
USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/110.0.0.0 Safari/537.36",
]


# ──────────────────────────────────────────
# Count how many comics need UPC checking
# ──────────────────────────────────────────
async def count_comics_to_check():
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        sql = """
            SELECT COUNT(*) AS total_needed
            FROM comics
            WHERE UPC IS NULL
              AND date LIKE CONCAT('%%', %s, '%%')
        """
        await cursor.execute(sql, (TARGET_DATE_FILTER,))
        row = await cursor.fetchone()
        total_needed = row['total_needed'] if row else 0

    connection.close()

    print("\n📊 **INITIAL STATUS REPORT**")
    print(f"✅ Total comics missing UPC with date LIKE '%{TARGET_DATE_FILTER}%': {total_needed}")
    return total_needed


# ──────────────────────────────────────────
# Fetch comics to check in batches
# ──────────────────────────────────────────
async def fetch_comics(batch_size=1000):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        sql = """
            SELECT Issue_URL, Unique_ID, Comic_Title, Issue_Number, date
            FROM comics
            WHERE UPC IS NULL
              AND date LIKE CONCAT('%%', %s, '%%')
            LIMIT %s
        """
        await cursor.execute(sql, (TARGET_DATE_FILTER, batch_size))
        comics_data = await cursor.fetchall()
    connection.close()
    return comics_data


# ──────────────────────────────────────────
# Fetch the UPC from a single comic page
# ──────────────────────────────────────────
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

            # Extract the UPC
            upc_code = None
            upc_label = soup.find('div', class_='m-0 f-12', string='UPC')
            if upc_label:
                upc_value = upc_label.find_next('span', class_='f-11')
                if upc_value:
                    upc_code = upc_value.get_text(strip=True)

            # Random delay (1-3 seconds) to be polite
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


# ──────────────────────────────────────────
# Update database with the found UPC codes
# ──────────────────────────────────────────
async def update_database(upc_results, processed_comics):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor() as cursor:
        # Update comics where we found a UPC
        for res in upc_results:
            if res['UPC']:
                await cursor.execute(
                    "UPDATE comics SET UPC=%s, last_checked=NOW() WHERE Unique_ID=%s",
                    (res['UPC'], res['Unique_ID'])
                )

        # Update last_checked for all processed comics
        unique_ids = [comic['Unique_ID'] for comic in processed_comics]
        if unique_ids:
            await cursor.executemany(
                "UPDATE comics SET last_checked=NOW() WHERE Unique_ID=%s",
                [(uid,) for uid in unique_ids]
            )

        await connection.commit()
        print(f"\n📝 Updated last_checked for {len(unique_ids)} comics.\n")

    connection.close()


# ──────────────────────────────────────────
# Main driver: processes comics in chunks
# ──────────────────────────────────────────
async def main():
    batch_size = 1000
    total_to_check = await count_comics_to_check()

    if total_to_check == 0:
        print(f"\n✅ No comics need to be checked for date LIKE '{TARGET_DATE_FILTER}'. Exiting.")
        return

    total_processed = 0

    while True:
        comics_data = await fetch_comics(batch_size)
        if not comics_data:
            print(f"\n✅ No more comics left matching '{TARGET_DATE_FILTER}'. Exiting script.")
            break

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

        # Update DB
        await update_database(found_upcs, comics_data)
        total_processed += len(comics_data)

        print(f"\n✅ Batch complete. Total comics processed so far: {total_processed}")

        # ───────────── ADDITIONAL STOP CHECK ─────────────
        # If we've processed enough based on the initial count, break out
        if total_processed >= total_to_check:
            print(f"\n✅ Processed {total_processed} comics, which meets or exceeds the count of {total_to_check}. Stopping.")
            break

    print("\n🚀 Script finished. All eligible comics matching your filter are scanned!")


# ──────────────────────────────────────────
# Run the script
# ──────────────────────────────────────────
if __name__ == '__main__':
    asyncio.run(main())

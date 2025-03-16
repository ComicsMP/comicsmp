import asyncio
import aiohttp
import aiomysql
import random
import os
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

# ✅ File to store URLs that already have UPCs
CHECKED_URLS_FILE = "upc_checked_urls.txt"

# ✅ User-Agent rotation to avoid being blocked
USER_AGENTS = [
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/110.0.0.0 Safari/537.36",
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Edge/110.0.0.0 Safari/537.36",
]

# ✅ Load previously checked URLs
def load_checked_urls():
    if os.path.exists(CHECKED_URLS_FILE):
        with open(CHECKED_URLS_FILE, "r", encoding="utf-8") as f:
            return set(line.strip() for line in f)
    return set()

# ✅ Save checked URLs to the file
def save_checked_urls(urls):
    with open(CHECKED_URLS_FILE, "a", encoding="utf-8") as f:
        for url in urls:
            f.write(url + "\n")

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

# ✅ Fetch **ALL comics** that need UPCs from the database
async def fetch_comics():
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor(aiomysql.DictCursor) as cursor:
        await cursor.execute(
            "SELECT Issue_URL, Unique_ID, Comic_Title, Issue_Number FROM comics WHERE UPC IS NULL"
        )
        comics_data = await cursor.fetchall()
    connection.close()
    return comics_data

# ✅ Update database with found UPCs
async def update_database(upc_results):
    connection = await aiomysql.connect(**db_config)
    async with connection.cursor() as cursor:
        for res in upc_results:
            if res['UPC']:  # ✅ Only update if UPC was found
                await cursor.execute(
                    "UPDATE comics SET UPC=%s WHERE Unique_ID=%s",
                    (res['UPC'], res['Unique_ID'])
                )
        await connection.commit()
    connection.close()

# ✅ Main function that loops until the database is fully scraped
async def main():
    while True:
        checked_urls = load_checked_urls()
        comics_data = await fetch_comics()

        if not comics_data:
            print("\n✅ All comics have UPCs. Scraping completed!")
            break

        print(f"\n📢 Fetched {len(comics_data)} comics for scraping.\n")

        # ✅ Remove already checked URLs
        comics_to_check = [comic for comic in comics_data if comic['Issue_URL'] not in checked_urls]

        print(f"✅ Skipping {len(comics_data) - len(comics_to_check)} comics that already have UPCs.")
        print(f"⏳ Checking {len(comics_to_check)} new comics.\n")

        results = []
        new_checked_urls = set()
        count = 0
        batch_size = 1000
        stop_limit = 8000  # Stop after 8,000 comics

        async with aiohttp.ClientSession() as session:
            tasks = [fetch_upc(session, comic) for comic in comics_to_check]

            for future in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc="Scraping UPC Codes"):
                result = await future
                results.append(result)
                new_checked_urls.add(result['Issue_URL'])
                count += 1

                if count % batch_size == 0:
                    found_upcs = [r for r in results if r['UPC']]
                    missing_upcs = [r for r in results if r['UPC'] is None]

                    # ✅ Save backup every 1,000 entries
                    with open("found_upcs_log.txt", "a", encoding="utf-8") as found_log:
                        for res in found_upcs[-batch_size:]:
                            found_log.write(f"Title: {res['Comic_Title']}, Issue: {res['Issue_Number']}, UPC: {res['UPC']}, URL: {res['Issue_URL']}\n")

                    with open("missing_upcs_log.txt", "a", encoding="utf-8") as missing_log:
                        for res in missing_upcs[-batch_size:]:
                            missing_log.write(f"Title: {res['Comic_Title']}, Issue: {res['Issue_Number']}, URL: {res['Issue_URL']}\n")

                    print(f"📂 Backup saved: {len(found_upcs[-batch_size:])} found, {len(missing_upcs[-batch_size:])} missing.")

                if count >= stop_limit:
                    break  # Stop after 8,000 comics

        save_checked_urls(new_checked_urls)

        # ✅ Summary Printout
        found_upcs = [r for r in results if r['UPC']]
        print("\n📊 FINAL SUMMARY:")
        print(f"✅ Total Comics Checked: {len(results)}")
        print(f"🟢 Total Found UPCs: {len(found_upcs)}")
        print(f"🔴 Total Missing UPCs: {len(results) - len(found_upcs)}")

        # ✅ Auto-update database
        await update_database(found_upcs)
        print("\n✅ Database updated successfully!")

        # ✅ Restart for the next batch
        print("\n🔄 Restarting script for next batch...\n")
        time.sleep(5)

# ✅ Run the script
if __name__ == '__main__':
    asyncio.run(main())

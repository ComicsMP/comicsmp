import time
import pandas as pd
import logging
import pymysql
import requests
import concurrent.futures
from bs4 import BeautifulSoup
from datetime import datetime

# ------------------- Logging Setup -------------------
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s %(levelname)s: %(message)s',
    handlers=[
        logging.FileHandler("fast_check.log", encoding="utf-8", errors="replace"),
        logging.StreamHandler()
    ]
)

# ------------------- Database Configuration -------------------
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'comics_db'
}

PLACEHOLDER_IMG = "https://www.comicspriceguide.com/img/missing_lrg.jpg"

# ------------------- Database Query -------------------
def fetch_candidate_urls():
    """
    Query the comics table for Issue_URLs that have the placeholder image.
    """
    connection = None
    candidates = []
    try:
        connection = pymysql.connect(**db_config)
        cursor = connection.cursor()
        query = "SELECT Issue_URL FROM comics WHERE Image_Path = '/images/default.jpg'"
        cursor.execute(query)
        rows = cursor.fetchall()
        candidates = [row[0] for row in rows]
        logging.info(f"Found {len(candidates)} candidate URLs with placeholder images.")
    except pymysql.MySQLError as e:
        logging.error(f"Database error: {e}")
    finally:
        if connection:
            connection.close()
    return candidates

# ------------------- Fast Check Function -------------------
def check_url_for_new_image(issue_url):
    """
    Uses a fast HTTP GET to fetch the page and checks if the cover image has updated.
    Returns a dict with the Issue_URL and new image URL if found;
    otherwise returns None.
    """
    try:
        response = requests.get(issue_url, timeout=15)
        if response.status_code != 200:
            logging.warning(f"Non-200 response for {issue_url}: {response.status_code}")
            return None
        soup = BeautifulSoup(response.text, 'html.parser')
        # Look for the image element; adjust the selector if needed
        img_elem = soup.select_one("img.img-responsive.img-thumbnail")
        if not img_elem or not img_elem.has_attr('src'):
            return None
        raw_img = img_elem['src']
        # If the src starts with a slash, prepend the domain
        if raw_img.startswith("/"):
            image_url = "https://www.comicspriceguide.com" + raw_img
        else:
            image_url = raw_img
        # Check if the image URL still indicates a placeholder
        if "missing_lrg.jpg" in image_url:
            return None
        else:
            # New image found; return a dict record for later full scraping
            return {"Issue_URL": issue_url, "New_Image_URL": image_url}
    except Exception as e:
        logging.error(f"Error processing {issue_url}: {e}")
        return None

# ------------------- Main Processing -------------------
def main():
    start_time = datetime.now()
    candidate_urls = fetch_candidate_urls()
    total = len(candidate_urls)
    processed = 0
    new_image_count = 0
    unchanged_count = 0
    results = []

    # Use 50 workers to avoid hammering the server
    max_workers = 50
    logging.info("Starting fast check with concurrency (50 workers)...")
    with concurrent.futures.ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_url = {executor.submit(check_url_for_new_image, url): url for url in candidate_urls}
        for future in concurrent.futures.as_completed(future_to_url):
            processed += 1
            url = future_to_url[future]
            result = future.result()
            if result:
                new_image_count += 1
                results.append(result)
                print(f"NEW IMAGE FOUND: {url}")
            else:
                unchanged_count += 1

            # Print status every 1000 URLs
            if processed % 1000 == 0:
                logging.info(f"Processed {processed}/{total} URLs: {new_image_count} new images, {unchanged_count} unchanged.")
    end_time = datetime.now()
    elapsed = (end_time - start_time).total_seconds()
    logging.info(f"Finished processing {total} URLs in {elapsed:.2f} seconds.")
    logging.info(f"Total new images: {new_image_count}.")
    
    # Save only the valid new image records to Excel
    if results:
        df = pd.DataFrame(results)
        output_file = "updated_issue_urls.xlsx"
        df.to_excel(output_file, index=False)
        logging.info(f"Saved {len(df)} records with new images to {output_file}.")
    else:
        logging.info("No new images found; no Excel file was saved.")

if __name__ == '__main__':
    main()

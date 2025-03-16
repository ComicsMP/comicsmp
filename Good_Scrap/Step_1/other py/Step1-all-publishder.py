import os
import time
import pandas as pd
import logging
import shutil
from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.chrome.options import Options
from concurrent.futures import ThreadPoolExecutor

# ====== CONFIGURATION ======
chrome_driver_path = r"C:\WebDrivers\chromedriver.exe"  # REPLACE WITH YOUR PATH
DONE_PUBLISHERS_FILE = "scraped_publishers.txt"
DONE_SERIES_FILE = "scraped_series.txt"
ALL_PUBLISHERS_FILE = "all_publishers.txt"
ALL_SERIES_LINKS_FILE = "all_main_page_links.txt"
MAX_ROWS_PER_CHUNK = 100_000
MAX_SERIES_PER_CHUNK = 1_000
THREADS = 5  # Number of concurrent threads

BASE_URL = "https://www.comicspriceguide.com/publishers"

# ====== LOGGING SETUP ======
logging.basicConfig(
    filename="scraper.log", level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s"
)

# ====== CHROME DRIVER SETUP ======
service = Service(chrome_driver_path)
options = Options()
options.add_argument("--headless=new")  # Use headless mode
options.add_argument("--disable-gpu")  # Improve headless stability
profile_directory = os.path.join(os.getcwd(), "chrome_profile")

# Remove previous profile data to avoid caching issues
if os.path.exists(profile_directory):
    shutil.rmtree(profile_directory)
options.add_argument(f"--user-data-dir={profile_directory}")

# Start WebDriver
driver = webdriver.Chrome(service=service, options=options)


# ====== HELPER FUNCTIONS ======
def save_unique_excel(df, base_filename):
    """Saves DataFrame to a unique Excel file by appending a number if the file exists."""
    base_name, ext = os.path.splitext(base_filename)
    filename = base_filename
    counter = 1
    while os.path.exists(filename):
        filename = f"{base_name}_{counter}{ext}"
        counter += 1
    df.to_excel(filename, index=False)
    logging.info(f"Saved {len(df)} rows -> '{filename}'")


def lazy_scroll():
    """Scrolls down dynamically, stopping when no new content loads."""
    last_height = driver.execute_script("return document.body.scrollHeight")
    while True:
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        try:
            WebDriverWait(driver, 2).until(
                lambda d: d.execute_script("return document.body.scrollHeight") > last_height
            )
        except:
            break  # No more content loaded, exit loop
        new_height = driver.execute_script("return document.body.scrollHeight")
        if new_height == last_height:
            break
        last_height = new_height


def collect_all_publishers():
    """Scrapes all publisher links from the top-level page."""
    publishers = []
    page_num = 1

    while True:
        logging.info(f"Collecting publishers from page {page_num}...")
        lazy_scroll()
        soup = BeautifulSoup(driver.page_source, "html.parser")
        links = soup.select("a.grid_publisher_title")

        if not links:
            logging.info("No more publisher links found. Stopping.")
            break

        for link in links:
            pub_url = "https://www.comicspriceguide.com" + link["href"]
            pub_name = link.text.strip()
            if not any(pub[0] == pub_url for pub in publishers):
                publishers.append((pub_url, pub_name))

        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button")
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_btn)
            WebDriverWait(driver, 5).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button"))).click()
            page_num += 1
        except Exception as e:
            logging.error(f"Error navigating publisher pages: {e}")
            break

    with open(ALL_PUBLISHERS_FILE, "w", encoding="utf-8") as f:
        f.writelines(f"{url}|{name}\n" for url, name in publishers)

    logging.info(f"Total publishers collected: {len(publishers)}")
    return publishers


def collect_series_links_for_publisher(publisher_url, publisher_name):
    """Scrapes all series links for a given publisher."""
    all_series = []
    page_num = 1

    driver.get(publisher_url)
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "a.fkTitleLnk.grid_title")))

    while True:
        logging.info(f"Collecting series from publisher {publisher_name}, page {page_num}...")
        lazy_scroll()
        soup = BeautifulSoup(driver.page_source, "html.parser")
        links = soup.select("a.fkTitleLnk.grid_title")

        if not links:
            break

        for link in links:
            series_url = "https://www.comicspriceguide.com" + link["href"]
            if not any(s[0] == series_url for s in all_series):
                all_series.append((series_url, publisher_name))

        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button")
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_btn)
            WebDriverWait(driver, 5).until(EC.element_to_be_clickable((By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button"))).click()
            page_num += 1
        except Exception as e:
            logging.error(f"Error navigating series pages for {publisher_name}: {e}")
            break

    with open(ALL_SERIES_LINKS_FILE, "a", encoding="utf-8") as f:
        f.writelines(f"{url}|{publisher_name}\n" for url, publisher_name in all_series)

    logging.info(f"Total series collected for {publisher_name}: {len(all_series)}")
    return all_series


def scrape_series(series_url):
    """Scrapes data from a single series page."""
    logging.info(f"Scraping series: {series_url}")

    driver.get(series_url)
    WebDriverWait(driver, 10).until(EC.presence_of_element_located((By.CSS_SELECTOR, "div.h--title.pb-0 > b")))

    soup = BeautifulSoup(driver.page_source, "html.parser")
    title_el = soup.select_one("div.h--title.pb-0 > b")
    title = title_el.text.strip() if title_el else "N/A"

    all_data = []

    for page_num in range(1, 6):  # Max of 5 pages per tab
        lazy_scroll()

        soup = BeautifulSoup(driver.page_source, "html.parser")
        issue_links = soup.find_all("a", class_="grid_issue")

        for issue in issue_links:
            issue_url = "https://www.comicspriceguide.com" + issue["href"]
            img_el = issue.find_previous("img", class_="fkiimg")
            issue_image = "https://www.comicspriceguide.com" + img_el["src"] if img_el else "N/A"
            issue_number = issue.get_text(strip=True)

            row = {
                "Title": title,
                "Issue Number": issue_number,
                "Issue URL": issue_url,
                "Image URL": issue_image,
            }
            all_data.append(row)

    df = pd.DataFrame(all_data)
    if not df.empty:
        save_unique_excel(df, f"{title.replace(' ', '_')}.xlsx")


# ====== MAIN EXECUTION ======
try:
    logging.info("Starting script...")
    publishers = collect_all_publishers()

    with ThreadPoolExecutor(max_workers=THREADS) as executor:
        executor.map(scrape_series, [pub[0] for pub in publishers])

    logging.info("All scraping completed successfully.")
except KeyboardInterrupt:
    logging.warning("Script interrupted by user.")
finally:
    driver.quit()
    logging.info("WebDriver closed.")

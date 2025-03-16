import time
import random
import pandas as pd
import logging
from urllib.parse import urlparse
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service  # For Selenium 4
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import (
    TimeoutException,
    NoSuchElementException,
    StaleElementReferenceException,
    ElementClickInterceptedException,
)
from bs4 import BeautifulSoup
from webdriver_manager.chrome import ChromeDriverManager  # For auto driver management
import concurrent.futures
from datetime import datetime, timedelta
import os  # ADDED for folder manipulation
import shutil  # ADDED for moving the final file

# Set up logging to both file and console.
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s %(levelname)s: %(message)s',
    handlers=[
        logging.FileHandler("scraped_comics_v2.log", encoding="utf-8", errors="replace"),  # Changed to v2
        logging.StreamHandler()
    ]
)

# Global option: Set to 1 to enable skipping URLs that were scraped within the past 7 days.
SKIP_ALREADY_SCRAPED = 1

# Predefined list of known countries.
KNOWN_COUNTRIES = {
    "USA",
    "United Kingdom",
    "Canada",
    "Australia",
    "Germany",
    "France",
    "Mexico",
    "India",
    "Japan",
    "Italy",
    "Spain",
    "Argentina",
    "Belgium",
    "Brazil",
    "Bulgaria",
    "Chile",
    "China",
    "Colombia",
    "Congo (Zaire)",
    "Croatia",
    "Czech Republic",
    "Denmark",
    "Egypt",
    "Finland",
    "Greece",
    "Hong Kong",
    "Hungary",
    "Iceland",
    "Ireland",
    "Israel",
    "Kenya",
    "Latvia",
    "Luxembourg",
    "Netherlands",
    "New Zealand",
    "Norway",
    "Philippines",
    "Poland",
    "Portugal",
    "Puerto Rico",
    "Romania",
    "Russia",
    "Singapore",
    "Slovenia",
    "South Africa",
    "South Korea",
    "Sweden",
    "Switzerland",
    "Taiwan",
    "Thailand",
    "Lebanon",
    "Bermuda",
    "Austria",
    "British Virgin Islands",
    "Iraq",
    "Malaysia",
    "Serbia and Montenegro (Yugoslavia)",
    "Ukraine",
    "United Arab Emirates",
}

def setup_driver():
    """Set up Selenium WebDriver with Chrome options."""
    options = webdriver.ChromeOptions()
    options.add_argument("--headless")  # Run in headless mode.
    options.add_argument("--disable-gpu")
    options.add_argument("--no-sandbox")
    options.add_argument("--enable-unsafe-swiftshader")
    options.add_argument("--ignore-certificate-errors")
    options.add_argument(
        "user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36"
    )
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--log-level=3")
    options.add_experimental_option('excludeSwitches', ['enable-logging'])
    # Updated to specify the exact ChromeDriver version for Chrome 133.0.6943.142
    service = Service(ChromeDriverManager(driver_version="133.0.6943.142").install())
    driver = webdriver.Chrome(service=service, options=options)
    return driver

def extract_country(text):
    """Extract the country from text based on a predefined list."""
    for country in KNOWN_COUNTRIES:
        if country in text:
            return country
    return "N/A"

def extract_volume(text):
    """Extract the volume number from text if present."""
    if "Volume" in text:
        parts = text.split("Volume")
        if len(parts) > 1:
            volume_part = parts[1].split()[0]
            return volume_part.strip()
    return "N/A"

def get_relative_url(full_url):
    """Return the path portion of a full URL."""
    parsed = urlparse(full_url)
    return parsed.path

def fix_repeated_text(s):
    """
    If the string is exactly doubled (e.g. '19551955'), return just half ('1955').
    Otherwise return the original string.
    """
    s = s.strip()
    if not s:
        return s
    length = len(s)
    if length % 2 == 0:
        half = length // 2
        if s[:half] == s[half:]:
            return s[:half]
    return s

def click_tab_button(driver, tab_elem):
    """Safely scroll the tab element into view and click it."""
    WebDriverWait(driver, 10).until(EC.visibility_of(tab_elem))
    driver.execute_script("arguments[0].scrollIntoView(true);", tab_elem)
    time.sleep(1)
    driver.execute_script("window.scrollBy(0, -150);")
    time.sleep(1)
    try:
        tab_elem.click()
    except (ElementClickInterceptedException, StaleElementReferenceException) as e:
        logging.warning(f"Normal click failed, trying JS click. Error: {e}")
        driver.execute_script("arguments[0].click();", tab_elem)
    time.sleep(2)

def lazy_scroll(driver):
    """Scrolls to the bottom of the page until no more content is loaded."""
    while True:
        old_height = driver.execute_script("return document.body.scrollHeight")
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(2)
        new_height = driver.execute_script("return document.body.scrollHeight")
        if new_height == old_height:
            break

# --- Timestamp Functions using a dedicated file (scraped_urls.txt) ---
def load_scraped_urls_with_timestamps(file_path="scraped_urls.txt"):
    """
    Load scraped URLs with timestamps from a dedicated file.
    Each line should be in the format: URL|YYYY-MM-DD HH:MM:SS
    Returns a dictionary mapping URL -> timestamp (datetime object).
    """
    scraped = {}
    try:
        with open(file_path, "r", encoding="utf-8", errors="replace") as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                if "|" in line:
                    parts = line.split("|")
                    if len(parts) == 2:
                        url, ts_str = parts
                        try:
                            ts = datetime.strptime(ts_str, "%Y-%m-%d %H:%M:%S")
                        except Exception:
                            ts = None
                        scraped[url] = ts
    except FileNotFoundError:
        scraped = {}
    return scraped

def save_scraped_url_with_timestamp(url, file_path="scraped_urls.txt"):
    """Append the URL and current timestamp to the dedicated scraped URLs file."""
    ts = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    with open(file_path, "a", encoding="utf-8") as f:
        f.write(f"{url}|{ts}\n")

# We'll define a helper that merges both old and new logs:
def load_combined_scraped_logs():
    combined = {}
    # Read from old (scraped_urls.txt)
    try:
        old_log = load_scraped_urls_with_timestamps("scraped_urls.txt")
        combined.update(old_log)
    except Exception as e:
        logging.warning("Could not read from scraped_urls.txt: " + str(e))
    # Read from new (scraped_urls_v2.txt)
    try:
        new_log = load_scraped_urls_with_timestamps("scraped_urls_v2.txt")
        combined.update(new_log)
    except Exception as e:
        logging.warning("Could not read from scraped_urls_v2.txt: " + str(e))
    return combined

# --- Scraping Functions for Detail Pages ---
def scrape_single_url(driver, url):
    """
    Scrape a single detail page and return a dictionary with detail fields:
      - Comic_Title
      - Issue_Number
      - Publisher_Name
      - Date (from detail page, from <span id="lblYears">)
      - Country
      - Volume
      - Image_URL
      - Issue_URL
      - Years (from the series page, <span id="spYears">)
      - Issues_Note
      - (Variant and Edition will be added later)
    
    The "Date" field comes solely from the detail page's <span id="lblYears"> (e.g. "May 2025"),
    and the "Years" field will be updated with the series page's <span id="spYears"> value.
    """
    logging.info(f"Scraping URL: {url}")
    driver.get(url)
    try:
        WebDriverWait(driver, 30).until(
            lambda d: len(d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0")) > 0 or 
                      len(d.find_elements(By.CSS_SELECTOR, "span#lblYears")) > 0
        )
    except TimeoutException:
        logging.error(f"Timeout waiting for key elements for URL: {url}")
        return None
    try:
        WebDriverWait(driver, 10).until(
            lambda d: d.execute_script("return document.readyState") == "complete"
        )
    except TimeoutException:
        logging.warning(f"Document not fully ready for URL: {url}")

    soup = BeautifulSoup(driver.page_source, 'html.parser')

    # Extract local "Date" from the detail page using <span id="lblYears">
    date_element = soup.select_one("span#lblYears")
    date = date_element.text.strip() if date_element else "N/A"

    issue_props = soup.select_one("div.mt-0.mb-0.issue-prop.text-muted")
    if issue_props:
        country = extract_country(issue_props.text)
        volume = extract_volume(issue_props.text)
    else:
        country, volume = "N/A", "N/A"

    image_element = soup.select_one("img.img-responsive.img-thumbnail")
    if image_element and image_element.has_attr('src'):
        image_url = "https://www.comicspriceguide.com" + image_element['src']
    else:
        image_url = "N/A"

    title_element = soup.select_one("div.h--title.pb-0")
    if title_element:
        title_text = title_element.text.strip()
        if "#" in title_text:
            title, issue_number = title_text.split("#", 1)
        else:
            title, issue_number = title_text, "N/A"
    else:
        title, issue_number = "N/A", "N/A"

    breadcrumb_publisher = soup.select_one("ol.breadcrumb li:nth-child(2)")
    publisher = breadcrumb_publisher.text.strip() if breadcrumb_publisher else "N/A"

    # Build initial result; note "Years" starts as "N/A"
    result = {
        "Comic_Title": title.strip(),
        "Issue_Number": issue_number.strip(),
        "Publisher_Name": publisher,
        "Date": date,  # Date comes solely from <span id="lblYears">
        "Country": country,
        "Volume": volume,
        "Image_URL": image_url,
        "Issue_URL": url,
        "Years": "N/A",
        "Issues_Note": "N/A"
    }
    logging.info(f"Scraped detail data: {result}")

    # Attempt to load the series page from breadcrumb li:nth-child(3) -> spYears
    breadcrumb_series = soup.select_one("ol.breadcrumb li:nth-child(3) a")
    if breadcrumb_series and breadcrumb_series.has_attr('href'):
        series_url = breadcrumb_series['href']
        if not series_url.startswith("http"):
            series_url = "https://www.comicspriceguide.com" + series_url
        try:
            driver.get(series_url)
            WebDriverWait(driver, 10).until(
                lambda d: d.execute_script("return document.readyState") == "complete"
            )
            series_soup = BeautifulSoup(driver.page_source, 'html.parser')
            sp_years_elem = series_soup.select_one("span#spYears")
            if sp_years_elem:
                result["Years"] = sp_years_elem.get_text(strip=True)
                logging.info(f"Overwrote 'Years' with series info (breadcrumb 3): {result['Years']}")
            else:
                logging.info("No <span id='spYears'> found on the series page (breadcrumb 3).")
        except TimeoutException:
            logging.warning(f"Timeout loading series page at {series_url} (breadcrumb 3)")
        except Exception as e:
            logging.error(f"Error scraping series page {series_url} (breadcrumb 3): {e}")
    else:
        logging.info("No breadcrumb child #3 link found, leaving 'Years' as N/A.")

    # If still no Years found, try the 4th breadcrumb as fallback.
    if result["Years"] == "N/A":
        breadcrumb_series4 = soup.select_one("ol.breadcrumb li:nth-child(4) a")
        if breadcrumb_series4 and breadcrumb_series4.has_attr('href'):
            series_url4 = breadcrumb_series4['href']
            if not series_url4.startswith("http"):
                series_url4 = "https://www.comicspriceguide.com" + series_url4
            try:
                driver.get(series_url4)
                WebDriverWait(driver, 10).until(
                    lambda d: d.execute_script("return document.readyState") == "complete"
                )
                series_soup4 = BeautifulSoup(driver.page_source, 'html.parser')
                sp_years_elem4 = series_soup4.select_one("span#spYears")
                if sp_years_elem4:
                    result["Years"] = sp_years_elem4.get_text(strip=True)
                    logging.info(f"Overwrote 'Years' with series info (breadcrumb 4): {result['Years']}")
                else:
                    logging.info("No <span id='spYears'> found on the series page (breadcrumb 4).")
            except TimeoutException:
                logging.warning(f"Timeout loading series page at {series_url4} (breadcrumb 4)")
            except Exception as e:
                logging.error(f"Error scraping series page {series_url4} (breadcrumb 4): {e}")
        else:
            logging.info("No breadcrumb child #4 link found, leaving 'Years' as N/A.")

    return result

def scrape_detail_with_tabs(driver, url):
    """
    Loads a detail page and checks for a tabs container.
    If tabs exist, iterate over them and scrape detail data; if not, scrape once and tag with "Default".
    Returns a list of result dictionaries.
    """
    results = []
    driver.get(url)
    try:
        WebDriverWait(driver, 30).until(
            lambda d: len(d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0")) > 0 or 
                      len(d.find_elements(By.CSS_SELECTOR, "span#lblYears")) > 0
        )
    except TimeoutException:
        logging.error(f"Timeout waiting for key elements for URL: {url}")
        return results

    tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
    if not tabs:
        data = scrape_single_url(driver, url)
        if data:
            data["Tab"] = "Default"
            results.append(data)
        return results

    logging.info("Found %s tabs on detail page: %s", len(tabs), url)
    for idx in range(len(tabs)):
        current_tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        if idx >= len(current_tabs):
            break
        tab_button = current_tabs[idx]
        tab_label = tab_button.text.strip() or f"Tab#{idx+1}"
        aria_pressed = tab_button.get_attribute("aria-pressed")
        class_attr = tab_button.get_attribute("class")
        is_selected = (aria_pressed == "true") or ("dx-state-selected" in class_attr)
        logging.info("Processing detail-page Tab %s/%s: '%s' (selected=%s)",
                     idx+1, len(current_tabs), tab_label, is_selected)
        if not is_selected:
            try:
                click_tab_button(driver, tab_button)
                logging.info("Clicked on detail-page tab '%s'.", tab_label)
                time.sleep(3)
            except Exception as e:
                logging.error("Error clicking detail-page tab '%s': %s", tab_label, str(e))
                continue
        else:
            logging.info("Detail-page tab '%s' is already selected.", tab_label)
        data = scrape_single_url(driver, url)
        if data:
            data["Tab"] = tab_label
            results.append(data)
    return results

def scrape_one_tab(driver, tab_button):
    """
    For a single main-page tab, click it (if not selected), paginate,
    and return grid data and a list of issue URLs.
    """
    tab_label = tab_button.text.strip() or "UnknownTab"
    aria_pressed = tab_button.get_attribute("aria-pressed")
    class_attr = tab_button.get_attribute("class")
    is_selected = (aria_pressed == "true") or ("dx-state-selected" in class_attr)
    if not is_selected:
        try:
            logging.info(f"Clicking main-page tab: '{tab_label}'")
            click_tab_button(driver, tab_button)
            time.sleep(2)
        except Exception as e:
            logging.error(f"Error clicking main-page tab '{tab_label}': {e}")
            return {}, []
    else:
        logging.info(f"Main-page tab '{tab_label}' is already selected.")
    aggregated_grid_data = {}
    aggregated_issue_urls = []
    unique_urls = set()
    page_counter = 1
    while True:
        logging.info(f"  [Tab '{tab_label}'] Scraping page {page_counter}...")
        lazy_scroll(driver)
        try:
            WebDriverWait(driver, 15).until(
                EC.presence_of_all_elements_located((By.CSS_SELECTOR, "a.grid_issue"))
            )
        except TimeoutException:
            logging.error(f"Timeout waiting for grid issues on page {page_counter}, tab '{tab_label}'.")
            break
        soup = BeautifulSoup(driver.page_source, 'html.parser')
        grid_issues = soup.find_all("a", class_="grid_issue")
        if not grid_issues:
            logging.info(f"  [Tab '{tab_label}'] No grid issues found. Stopping pagination.")
            break
        new_count = 0
        for issue in grid_issues:
            href = issue.get("href")
            if not href:
                continue
            parent_td = issue.find_parent("td")
            if not parent_td:
                continue
            variant_elem = parent_td.find("span", class_="d-none d-sm-inline f-11")
            variant_raw = variant_elem.get_text(strip=True) if variant_elem else "N/A"
            variant = fix_repeated_text(variant_raw)
            edition_elem = parent_td.find("span", class_="d-block mt-1 text-black f-10 fw-bold")
            edition_raw = edition_elem.get_text(strip=True) if edition_elem else "N/A"
            edition = fix_repeated_text(edition_raw)
            info_div = parent_td.find("div", class_="grid_issue_info")
            years = "N/A"
            if info_div:
                span_years = info_div.find("span", class_="d-none d-sm-inline")
                if span_years:
                    years_raw = span_years.get_text(strip=True)
                    years = fix_repeated_text(years_raw)
            rel_href = href
            if not href.startswith("http"):
                href_full = "https://www.comicspriceguide.com" + href
            else:
                href_full = href
            aggregated_grid_data[rel_href] = {
                "Years": years,
                "Variant": variant,
                "Edition": edition,
                "MainTab": tab_label,
            }
            if href_full not in unique_urls:
                unique_urls.add(href_full)
                aggregated_issue_urls.append(href_full)
                new_count += 1
                logging.info(f"  Saving from Tab '{tab_label}': {href_full} (Variant='{variant}', Edition='{edition}', Years='{years}')")
        logging.info(f"  [Tab '{tab_label}'] Page {page_counter}: Found {new_count} new issues (Total unique so far: {len(unique_urls)}).")
        if new_count == 0:
            logging.info(f"  [Tab '{tab_label}'] No new issues found on page {page_counter}. Stopping pagination.")
            break
        try:
            next_button = driver.find_element(By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button")
            if "dx-state-disabled" in next_button.get_attribute("class"):
                logging.info(f"  [Tab '{tab_label}'] Next page button disabled. Done with this tab.")
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_button)
            time.sleep(1)
            next_button.click()
            logging.info(f"  [Tab '{tab_label}'] Clicked next page button.")
            time.sleep(random.uniform(2, 4))
            page_counter += 1
        except Exception as e:
            logging.error(f"  [Tab '{tab_label}'] Error clicking next page button: {e}")
            break
    return aggregated_grid_data, aggregated_issue_urls

def scrape_all_grid_pages(driver):
    """
    1) Load the /new-comics page.
    2) Click 'Show Variants' if available.
    3) Find all top-level tabs.
    4) For each tab, gather grid data and issue URLs.
    5) Return a tuple of (aggregated grid data, list of unique issue URLs).
    """
    grid_url = "https://www.comicspriceguide.com/new-comics"
    logging.info(f"Loading main page: {grid_url}")
    driver.get(grid_url)
    try:
        variant_checkbox = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.XPATH, "//span[contains(text(), 'Show Variants')]"))
        )
        variant_checkbox.click()
        logging.info("Clicked on 'Show Variants' checkbox.")
    except TimeoutException:
        logging.warning("Timeout or not found: 'Show Variants' checkbox (might already be checked).")
    time.sleep(2)
    tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
    if not tabs:
        logging.warning("No tabs found on the main page. Possibly everything is under a single default tab.")
        return {}, []
    logging.info(f"Found {len(tabs)} tabs on main page: {grid_url}")
    all_aggregated_grid_data = {}
    all_aggregated_urls = []
    for i in range(len(tabs)):
        current_tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        if i >= len(current_tabs):
            break
        tab_elem = current_tabs[i]
        tab_label = tab_elem.text.strip() or f"Tab#{i+1}"
        logging.info(f"\n==> Processing main-page tab {i+1}/{len(current_tabs)}: '{tab_label}' <==")
        tab_grid_data, tab_urls = scrape_one_tab(driver, tab_elem)
        all_aggregated_grid_data.update(tab_grid_data)
        all_aggregated_urls.extend(tab_urls)
    unique_full_urls = list(dict.fromkeys(all_aggregated_urls))
    logging.info(f"Aggregated total: {len(unique_full_urls)} unique issue URLs across all tabs.")
    return all_aggregated_grid_data, unique_full_urls

def load_scraped_urls(file_path="scraped_urls.txt"):
    try:
        with open(file_path, "r", encoding="utf-8") as f:
            return {line.strip() for line in f if line.strip()}
    except FileNotFoundError:
        return set()

def save_scraped_url(url, file_path="scraped_urls.txt"):
    with open(file_path, "a", encoding="utf-8") as f:
        f.write(url + "\n")

def process_detail_page(url, scraped_log, grid_data):
    """
    Process a single detail page:
      - If the URL is in the scraped log with a timestamp within 7 days, skip it.
      - Otherwise, scrape detail data (using tabs if available), merge with grid data,
        log the URL with timestamp, and return a list of result dictionaries.
    """
    logging.info(f"Processing detail page: {url}")
    if SKIP_ALREADY_SCRAPED and (url in scraped_log):
        ts = scraped_log[url]
        if ts and (datetime.now() - ts) < timedelta(days=7):
            logging.info("Skipping URL (recently scraped): %s", url)
            return []  # Skip if scraped within 7 days
    detail_driver = setup_driver()
    try:
        data_list = scrape_detail_with_tabs(detail_driver, url)
        if not data_list:
            logging.info(f"Retrying URL: {url}")
            time.sleep(3)
            data_list = scrape_detail_with_tabs(detail_driver, url)
        results_local = []
        if data_list:
            for data in data_list:
                rel_url = get_relative_url(url)
                main_tab_label = grid_data.get(rel_url, {}).get("MainTab", "Default")
                data["Tab"] = main_tab_label

                # Removed fallback to grid's "Years" to ensure "Years" comes only from the series page (spYears)
                data["Variant"] = grid_data.get(rel_url, {}).get("Variant", "N/A")
                data["Edition"] = grid_data.get(rel_url, {}).get("Edition", "N/A")

                results_local.append(data)
        # Now save to scraped_urls_v2.txt instead of the original
        save_scraped_url_with_timestamp(url, file_path="scraped_urls_v2.txt")
        time.sleep(random.uniform(2, 5))
        return results_local
    except Exception as e:
        logging.error("Error processing detail page %s: %s", url, e)
        return []
    finally:
        detail_driver.quit()

def main():
    # EXACT original logic, except we switch to reading from combined logs & saving to v2
    scraped_log = {}
    if SKIP_ALREADY_SCRAPED:
        try:
            scraped_log = load_combined_scraped_logs()
        except Exception as e:
            logging.warning("Could not read from old/new scraped_urls files: " + str(e))

    grid_driver = setup_driver()
    grid_data, issue_urls = scrape_all_grid_pages(grid_driver)
    grid_driver.quit()

    total_issues = len(issue_urls)
    logging.info(f"Found {total_issues} total issue URLs (from all tabs).")
    detail_count = 0
    live_update_threshold = 20
    live_update_counter = 0
    results = []
    max_workers = 15

    with concurrent.futures.ThreadPoolExecutor(max_workers=max_workers) as executor:
        future_to_url = {executor.submit(process_detail_page, url, scraped_log, grid_data): url for url in issue_urls}
        for future in concurrent.futures.as_completed(future_to_url):
            url = future_to_url[future]
            try:
                data_list = future.result()
                detail_count += 1
                remaining = total_issues - detail_count
                logging.info(f"Completed detail page {detail_count}/{total_issues} (remaining: {remaining}).")
                if data_list:
                    results.extend(data_list)
                    live_update_counter += len(data_list)
                if live_update_counter >= live_update_threshold:
                    df_live = pd.DataFrame(results)
                    # Data is already using correct column names so no renaming is needed here.
                    desired_order = [
                        "Tab",
                        "Comic_Title",
                        "Years",
                        "Volume",
                        "Country",
                        "Issues_Note",
                        "Issue_Number",
                        "Issue_URL",
                        "Image_URL",
                        "Date",
                        "Variant",
                        "Edition",
                        "Publisher_Name"
                    ]
                    existing_cols = [c for c in desired_order if c in df_live.columns]
                    df_live = df_live.reindex(columns=existing_cols)
                    df_live.to_excel("live_scraped_comics.xlsx", index=False)
                    logging.info(f"Live output updated with {len(results)} records.")
                    live_update_counter = 0
            except Exception as e:
                logging.error("Error in processing URL %s: %s", url, e)

    if results:
        df = pd.DataFrame(results)
        # Data is already correctly named.
        desired_order = [
            "Tab",
            "Comic_Title",
            "Years",
            "Volume",
            "Country",
            "Issues_Note",
            "Issue_Number",
            "Issue_URL",
            "Image_URL",
            "Date",
            "Variant",
            "Edition",
            "Publisher_Name"
        ]
        existing_cols = [c for c in desired_order if c in df.columns]
        df = df.reindex(columns=existing_cols)
        df.to_excel("scraped_comics.xlsx", index=False)
        logging.info("Data saved to scraped_comics.xlsx")

        #  Create or use the sibling Step_2 folder: go up one level, then Step_2
        step2_folder = "../Step_2"
        os.makedirs(step2_folder, exist_ok=True)

        final_dest = os.path.join(step2_folder, "scraped_comics.xlsx")
        shutil.move("scraped_comics.xlsx", final_dest)
        logging.info(f"Final output moved to {final_dest}")
    else:
        logging.info("No data scraped or no new data to save.")

if __name__ == '__main__':
    main()

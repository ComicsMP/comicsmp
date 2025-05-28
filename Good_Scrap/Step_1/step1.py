import time
import random
import pandas as pd
import logging
import re
import subprocess
import platform
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
        logging.FileHandler("scraped_comics.log", encoding="utf-8", errors="replace"),
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

def extract_country(text):
    text_lower = text.lower()

    aliases = {
        "uk": "United Kingdom",
        "u.k.": "United Kingdom",
        "england": "United Kingdom",
        "scotland": "United Kingdom",
        "wales": "United Kingdom",
        "northern ireland": "United Kingdom",

        "usa": "USA",
        "u.s.a.": "USA",
        "u.s.": "USA",
        "united states": "USA",
        "united states of america": "USA",

        "s.korea": "South Korea",
        "republic of korea": "South Korea",

        "prc": "China",
        "people's republic of china": "China",
        "hongkong": "Hong Kong",

        "uae": "United Arab Emirates",
        "u.a.e.": "United Arab Emirates",

        "brasil": "Brazil",
        "russia federation": "Russia",
    }

    for alias, normalized in aliases.items():
        if alias in text_lower:
            return normalized

    for country in KNOWN_COUNTRIES:
        if country.lower() in text_lower:
            return country

    return "N/A"


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
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.7049.42 Safari/537.36"
    )
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--log-level=3")
    options.add_experimental_option('excludeSwitches', ['enable-logging'])
    # Updated to specify the exact ChromeDriver version for Chrome 133.0.6943.142
    service = Service(ChromeDriverManager(driver_version="135.0.7049.42").install())
    driver = webdriver.Chrome(service=service, options=options)
    return driver


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

# NEW FUNCTION for glued repeats:
def fix_glued_variant(s):
    """
    If the first 20 characters reappear beyond index 20, 
    trim the string at the start of that repeated chunk.
    """
    s = s.strip()
    if len(s) < 40:
        return s
    chunk = s[:20]
    repeat_pos = s.find(chunk, 20)
    if repeat_pos != -1:
        return s[:repeat_pos]
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

# --- Scraping Functions for Detail Pages ---
def scrape_single_url(driver, url):
    """
    Scrape a single detail page and return a dictionary with detail fields:
    Comic_Title, Issue Number, Publisher, Date, Country, Volume, Image URL, Issue URL.
    """
    logging.info(f"Scraping URL: {url}")
    driver.get(url)
    try:
        WebDriverWait(driver, 30).until(
            lambda d: len(d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0")) > 0
                      or len(d.find_elements(By.CSS_SELECTOR, "span#lblYears")) > 0
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

    result = {
        "Comic_Title": title.strip(),
        "Issue Number": issue_number.strip(),
        "Publisher": publisher,
        "Date": date,
        "Country": country,
        "Volume": volume,
        "Image URL": image_url,
        "Issue URL": url
    }
    logging.info(f"Scraped detail data: {result}")
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
            lambda d: len(d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0")) > 0
                      or len(d.find_elements(By.CSS_SELECTOR, "span#lblYears")) > 0
        )
    except TimeoutException:
        logging.error(f"Timeout waiting for key elements for URL: {url}")
        return results

    tabs = driver.find_elements(By.CSS_SELECTOR, "div.dx-buttongroup-wrapper > div.dx-widget > div.dx-button-content > div.h--basic")

    if not tabs:
        data = scrape_single_url(driver, url)
        if data:
            data["Tab"] = "Default"
            results.append(data)
        return results

    logging.info("Found %s tabs on detail page: %s", len(tabs), url)
    for idx in range(len(tabs)):
        current_tabs = driver.find_elements(By.CSS_SELECTOR, "div.dx-buttongroup-wrapper > div.dx-widget > div.dx-button-content > div.h--basic")

        if idx >= len(current_tabs):
            break
        tab_button = current_tabs[idx]
        tab_label = tab_button.get_attribute("innerText").strip() or f"Tab#{idx+1}"

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

def scrape_one_tab(driver, tab_button, tab_label):
    """
    For a single main-page tab, click it (if not selected), paginate,
    and return grid data and a list of issue URLs.
    """
    tab_label = tab_button.text.strip() or "UnknownTab"
    # Select the tab if needed
    aria = tab_button.get_attribute("aria-pressed")
    cls  = tab_button.get_attribute("class")
    if aria != "true" and "dx-state-selected" not in cls:
        logging.info(f"Clicking main-page tab: '{tab_label}'")
        click_tab_button(driver, tab_button)
        time.sleep(2)
    else:
        logging.info(f"Main-page tab '{tab_label}' is already selected.")

    aggregated_grid_data = {}
    aggregated_issue_urls = []
    unique_urls = set()
    page_counter = 1

    while True:
        logging.info(f"  [Tab '{tab_label}'] Scraping page {page_counter}...")
        # 1) Wait for at least one grid issue to appear
        try:
            WebDriverWait(driver, 15).until(
                EC.presence_of_all_elements_located((By.CSS_SELECTOR, "a.grid_issue"))
            )
        except TimeoutException:
            logging.error(f"Timeout waiting for grid issues on page {page_counter}, tab '{tab_label}'.")
            break

        # 2) Scroll & parse
        lazy_scroll(driver)
        soup = BeautifulSoup(driver.page_source, 'html.parser')
        grid_issues = soup.find_all("a", class_="grid_issue")
        if not grid_issues:
            logging.info(f"  [Tab '{tab_label}'] No grid issues found. Stopping pagination.")
            break

        # 3) Extract issue links & metadata
        new_count = 0
        for issue in grid_issues:
            href = issue.get("href")
            if not href:
                continue
            full_url = href if href.startswith("http") else "https://www.comicspriceguide.com" + href
            rel_path = get_relative_url(full_url)

            if full_url not in unique_urls:
                unique_urls.add(full_url)
                aggregated_issue_urls.append(full_url)
                new_count += 1

                parent_td = issue.find_parent("td")
                variant_elem = parent_td.find("span", class_="d-none d-sm-inline f-11")
                variant = fix_glued_variant(variant_elem.get_text(strip=True)) if variant_elem else "N/A"

                edition_elem = parent_td.find("span", class_="d-block mt-1 text-black f-10 fw-bold")
                edition = fix_repeated_text(edition_elem.get_text(strip=True)) if edition_elem else "N/A"

                info_div = parent_td.find("div", class_="grid_issue_info")
                years = "N/A"
                if info_div:
                    span_years = info_div.find("span", class_="d-none d-sm-inline")
                    if span_years:
                        years = fix_repeated_text(span_years.get_text(strip=True))

                aggregated_grid_data[rel_path] = {
                    "MainTab": tab_label,
                    "Years": years,
                    "Variant": variant,
                    "Edition": edition
                }

                logging.debug(f"Saved issue: {full_url} (Years={years}, Variant={variant}, Edition={edition})")


        logging.info(f"  [Tab '{tab_label}'] Page {page_counter}: found {new_count} new issues.")
        if new_count == 0:
            break

        # 4) Extract current page number
        try:
            pag_txt   = driver.find_element(
                            By.XPATH,
                            "//div[contains(text(),'Page') and contains(text(),'items')]"
                        ).text
            prev_page = int(re.search(r'Page (\d+)', pag_txt).group(1))
        except Exception as e:
            logging.warning(f"  [Tab '{tab_label}'] Could not extract current page: {e}")
            break

        # 5) Attempt to click “Next” up to 5 times until page number increases
        attempts = 0
        success  = False
        while attempts < 5 and not success:
            next_btns = driver.find_elements(By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button")
            if not next_btns or "dx-state-disabled" in next_btns[0].get_attribute("class"):
                break
            click_tab_button(driver, next_btns[0])
            try:
                WebDriverWait(driver, 10).until(
                    lambda d: int(re.search(
                        r'Page (\d+)',
                        d.find_element(By.XPATH, "//div[contains(text(),'Page') and contains(text(),'items')]")
                         .text
                    ).group(1)) > prev_page
                )
                success = True
            except TimeoutException:
                attempts += 1
                time.sleep(1.5)

        if not success:
            logging.info(f"  [Tab '{tab_label}'] No more pages or failed to advance after retries.")
            break

        page_counter += 1
        logging.info(f"  [Tab '{tab_label}'] Advanced to page {page_counter}.")

    return aggregated_grid_data, aggregated_issue_urls


# --- Updated scrape_all_grid_pages with V2 pagination logic ---
def scrape_all_grid_pages(driver):
    grid_url = "https://www.comicspriceguide.com/new-scans"
    logging.info(f"Loading main page: {grid_url}")
    driver.get(grid_url)

    # Handle cookie consent if shown
    try:
        consent = WebDriverWait(driver, 5).until(
            EC.element_to_be_clickable(
                (By.XPATH, "//button[contains(text(), 'Consent') or contains(text(), 'Agree')]")
            )
        )
        consent.click()
        time.sleep(2)
    except TimeoutException:
        pass

    # Click "Show Variants" if present
    try:
        variants = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable(
                (By.XPATH, "//span[contains(text(), 'Show Variants')]")
            )
        )
        driver.execute_script("arguments[0].click();", variants)
        time.sleep(2)
    except TimeoutException:
        pass

    # Find main‐page tabs
    tab_buttons = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
    num_tabs = len(tab_buttons)
    logging.info(f"Found {num_tabs} tabs on main page.")

    all_grid_data = {}
    all_issue_urls = []

    # Delegate each tab’s pagination to scrape_one_tab()
    for i in range(num_tabs):
        tab_buttons = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        if i >= len(tab_buttons):
            logging.warning(f"Tab index {i} out of range; skipping.")
            continue

        tab = tab_buttons[i]
        label = tab.text.strip() or f"Tab#{i+1}"
        logging.info(f"Processing tab {i+1}/{num_tabs}: '{label}'")

        try:
            tab_grid, tab_urls = scrape_one_tab(driver, tab, label)
            all_grid_data.update(tab_grid)
            all_issue_urls.extend(tab_urls)
        except Exception as e:
            logging.error(f"Error in tab '{label}': {e}")

    # Dedupe
    unique_urls = list(dict.fromkeys(all_issue_urls))
    logging.info(f"Aggregated total: {len(unique_urls)} unique URLs.")
    return all_grid_data, unique_urls

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
                data["Years"] = grid_data.get(rel_url, {}).get("Years", "N/A")
                data["Variant"] = grid_data.get(rel_url, {}).get("Variant", "N/A")
                data["Edition"] = grid_data.get(rel_url, {}).get("Edition", "N/A")
                results_local.append(data)
        save_scraped_url_with_timestamp(url)  # Use dedicated scraped_urls.txt file
        time.sleep(random.uniform(2, 5))
        return results_local
    except Exception as e:
        logging.error("Error processing detail page %s: %s", url, e)
        return []
    finally:
        detail_driver.quit()

def main():
    # EXACT original logic, except we add a final move to Step_2
    scraped_log = {}
    if SKIP_ALREADY_SCRAPED:
        try:
            scraped_log = load_scraped_urls_with_timestamps("scraped_urls.txt")
        except Exception as e:
            logging.warning("Could not read scraped_urls.txt: " + str(e))

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

    with concurrent.futures.ThreadPoolExecutor(max_workers=15) as executor:
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
                    df_live.rename(columns={"Publisher": "Publisher Name"}, inplace=True)
                    if "Issues Note" not in df_live.columns:
                        df_live["Issues Note"] = "N/A"
                    desired_order = [
                        "Tab",
                        "Comic_Title",
                        "Years",
                        "Volume",
                        "Country",
                        "Issues Note",
                        "Issue Number",
                        "Issue URL",
                        "Image URL",
                        "Date",
                        "Variant",
                        "Edition",
                        "Publisher Name"
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
        df.rename(columns={"Publisher": "Publisher Name"}, inplace=True)
        if "Issues Note" not in df.columns:
            df["Issues Note"] = "N/A"
        desired_order = [
            "Tab", "Comic_Title", "Years", "Volume", "Country", "Issues Note",
            "Issue Number", "Issue URL", "Image URL", "Date", "Variant", "Edition", "Publisher Name"
        ]
        existing_cols = [c for c in desired_order if c in df.columns]
        df = df.reindex(columns=existing_cols)
        df.to_excel("scraped_comics.xlsx", index=False)
        logging.info("Data saved to scraped_comics.xlsx")

        step2_folder = "../Step_2"
        os.makedirs(step2_folder, exist_ok=True)
        final_dest = os.path.join(step2_folder, "scraped_comics.xlsx")
        shutil.move("scraped_comics.xlsx", final_dest)
        logging.info(f"Final output moved to {final_dest}")

        # ✅ Save sample_check.xlsx with last 10 rows
        try:
            df_sample = pd.DataFrame(results[-10:])
            df_sample = df_sample.reindex(columns=[c for c in desired_order if c in df_sample.columns])
            df_sample.to_excel("sample_check.xlsx", index=False)
            logging.info("Saved sample_check.xlsx with the last 10 rows.")
        except Exception as e:
            logging.warning(f"Could not save sample_check.xlsx: {e}")

        # ✅ Auto-open sample_check.xlsx
        try:
            filepath = os.path.abspath("sample_check.xlsx")
            if platform.system() == "Windows":
                os.startfile(filepath)
            elif platform.system() == "Darwin":
                subprocess.call(["open", filepath])
            else:
                subprocess.call(["xdg-open", filepath])
            logging.info("Opened sample_check.xlsx for manual review.")
        except Exception as e:
            logging.warning(f"Could not auto-open sample_check.xlsx: {e}")
    else:
        logging.info("No data scraped or no new data to save.")


if __name__ == '__main__':
    main()

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
    StaleElementReferenceException,
    ElementClickInterceptedException,
)
from datetime import datetime, timedelta
# For auto driver management (without special version checks)
from webdriver_manager.chrome import ChromeDriverManager

# ========================= EDIT THESE OPTIONS BEFORE RUNNING =========================
# TARGET_URL: 
#   - This is the base URL for the section you want to scrape.
#   - It can be a main series page (when using 3-level mode) or a grid page (when using 2-level mode).
#
# ENABLE_VARIANTS_CHECK:
#   - Set to 1 if you want the script to click the "Show Variants" checkbox (if present) on grid pages.
#   - Set to 0 if you want to skip this step.
#
# SKIP_ALREADY_SCRAPED:
#   - Set to 1 if you want to enable logic that skips issues already scraped in previous runs.
#   - Set to 0 if you want the script to re-scan all issues every time it runs.
#
# SCRAPE_DEPTH:
#   - This option controls the number of levels the script will scrape:
#       * Set to 2: The script will work in grid → detail mode (directly from grid page to issue detail pages).
#       * Set to 3: The script will work in main series → grid → detail mode. It will first gather series links from a main page,
#         then scrape each grid page, and finally process detail pages.
#
# TARGET_URLS:
#   - This is a list of URLs that the script will process.
#   - You can include one or more URLs here. If you need to scrape multiple sections of the website,
#     add each URL to this list.
#
TARGET_URL = "https://www.comicspriceguide.com/genres/mature/ph"
ENABLE_VARIANTS_CHECK = 0
SKIP_ALREADY_SCRAPED = 0
SCRAPE_DEPTH = 3
TARGET_URLS = [
    TARGET_URL,
    # Add additional URLs as needed.
]
# ============================================================================================

KNOWN_COUNTRIES = {
    "USA", "United Kingdom", "Canada", "Australia", "Germany", "France",
    "Mexico", "India", "Japan", "Italy", "Spain", "Argentina", "Belgium",
    "Brazil", "Bulgaria", "Chile", "China", "Colombia", "Congo (Zaire)",
    "Croatia", "Czech Republic", "Denmark", "Egypt", "Finland", "Greece",
    "Hong Kong", "Hungary", "Iceland", "Ireland", "Israel", "Kenya", "Latvia",
    "Luxembourg", "Netherlands", "New Zealand", "Norway", "Philippines",
    "Poland", "Portugal", "Puerto Rico", "Romania", "Russia", "Singapore",
    "Slovenia", "South Africa", "South Korea", "Sweden", "Switzerland", "Taiwan",
    "Thailand", "Lebanon", "Bermuda", "Austria", "British Virgin Islands",
    "Iraq", "Malaysia", "Serbia and Montenegro (Yugoslavia)", "Ukraine",
    "United Arab Emirates",
}

logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s: %(message)s",
    handlers=[
        logging.FileHandler("scraped_comics.log", encoding="utf-8", errors="replace"),
        logging.StreamHandler(),
    ],
)

def login_to_site(driver, username, password):
    driver.get("https://www.comicspriceguide.com/")
    time.sleep(2)
    try:
        login_button = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.XPATH, "//span[contains(text(),'Login')]"))
        )
        login_button.click()
        logging.info("Clicked Login button")
        time.sleep(2)
        username_field = WebDriverWait(driver, 10).until(
            EC.visibility_of_element_located((By.XPATH, "//div[@id='dvUser']//input"))
        )
        username_field.clear()
        username_field.send_keys(username)
        logging.info("Entered Username")
        password_field = WebDriverWait(driver, 10).until(
            EC.visibility_of_element_located((By.XPATH, "//div[@id='dvPassword']//input"))
        )
        password_field.clear()
        password_field.send_keys(password)
        logging.info("Entered Password")
        submit_button = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.XPATH, "//div[@id='btnLgn']"))
        )
        submit_button.click()
        logging.info("Clicked Login Submit button")
        time.sleep(5)
        WebDriverWait(driver, 20).until(
            EC.invisibility_of_element_located((By.ID, "btnLgn"))
        )
        logging.info("Login successful! (Login popup disappeared)")
        return True
    except Exception as e:
        logging.error(f"Error during login process: {e}")
        return False

def setup_driver():
    options = webdriver.ChromeOptions()
    options.add_argument("--headless")
    options.add_argument("--disable-gpu")
    options.add_argument("--no-sandbox")
    options.add_argument("--enable-unsafe-swiftshader")
    options.add_argument("--ignore-certificate-errors")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--log-level=3")
    options.add_experimental_option("excludeSwitches", ["enable-logging"])
    options.add_argument("user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
                         "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36")
    service = Service()
    driver = webdriver.Chrome(service=service, options=options)
    return driver

def extract_country(text):
    for country in KNOWN_COUNTRIES:
        if country in text:
            return country
    return "N/A"

def extract_volume(text):
    if "Volume" in text:
        parts = text.split("Volume")
        if len(parts) > 1:
            volume_part = parts[1].split()[0]
            return volume_part.strip()
    return "N/A"

def get_relative_url(full_url):
    parsed = urlparse(full_url)
    return parsed.path

def fix_repeated_text(s):
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
    while True:
        old_height = driver.execute_script("return document.body.scrollHeight")
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(2)
        new_height = driver.execute_script("return document.body.scrollHeight")
        if new_height == old_height:
            break

def scrape_single_url(driver, url):
    logging.info(f"Scraping URL: {url}")
    driver.get(url)
    try:
        WebDriverWait(driver, 30).until(
            lambda d: d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0")
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
    # For detail pages we set Years to "N/A" (we get Years from grid pages)
    detail_years = "N/A"
    try:
        issue_props = driver.find_element(By.CSS_SELECTOR, "div.mt-0.mb-0.issue-prop.text-muted")
        props_text = issue_props.text
        country = extract_country(props_text)
        volume = extract_volume(props_text)
    except Exception:
        country, volume = "N/A", "N/A"
    try:
        image_element = driver.find_element(By.CSS_SELECTOR, "img.img-responsive.img-thumbnail")
        src = image_element.get_attribute("src")
        if src.startswith("https://www.comicspriceguide.com"):
            image_url = src
        else:
            image_url = "https://www.comicspriceguide.com" + src
    except Exception:
        image_url = "N/A"
    try:
        title_element = driver.find_element(By.CSS_SELECTOR, "div.h--title.pb-0")
        title_text = title_element.text.strip()
        if "#" in title_text:
            title, issue_number = title_text.split("#", 1)
        else:
            title, issue_number = title_text, "N/A"
    except Exception:
        title, issue_number = "N/A", "N/A"
    try:
        breadcrumb_publisher = driver.find_element(By.CSS_SELECTOR, "ol.breadcrumb li:nth-child(2)")
        publisher = breadcrumb_publisher.text.strip()
    except Exception:
        publisher = "N/A"
    result = {
        "Comic_Title": title.strip(),
        "Issue Number": issue_number.strip(),
        "Publisher": publisher,
        "Years": detail_years,
        "Country": country,
        "Volume": volume,
        "Image URL": image_url,
        "Issue URL": url,
    }
    logging.info(f"Scraped detail data: {result}")
    return result

def scrape_detail_with_tabs(driver, url):
    results = []
    driver.get(url)
    try:
        WebDriverWait(driver, 30).until(
            lambda d: d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0")
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
    logging.info(f"Found {len(tabs)} tabs on detail page: {url}")
    for idx in range(len(tabs)):
        current_tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        if idx >= len(current_tabs):
            break
        tab_button = current_tabs[idx]
        try:
            tab_label = tab_button.text.strip() or "UnknownTab"
            aria_pressed = tab_button.get_attribute("aria-pressed")
            class_attr = tab_button.get_attribute("class")
        except StaleElementReferenceException:
            tab_label = "UnknownTab"
            aria_pressed = ""
            class_attr = ""
        is_selected = (aria_pressed == "true") or ("dx-state-selected" in class_attr)
        logging.info("Processing detail-page Tab %s/%s: '%s' (selected=%s)",
                     idx + 1, len(current_tabs), tab_label, is_selected)
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
    try:
        tab_label = tab_button.text.strip() or "UnknownTab"
        aria_pressed = tab_button.get_attribute("aria-pressed")
        class_attr = tab_button.get_attribute("class")
    except StaleElementReferenceException:
        tab_label = "UnknownTab"
        aria_pressed = ""
        class_attr = ""
    is_selected = (aria_pressed == "true") or ("dx-state-selected" in class_attr)
    if not is_selected:
        try:
            logging.info(f"Clicking main-page tab: '{tab_label}'")
            click_tab_button(driver, tab_button)
            time.sleep(2)
        except Exception as e:
            logging.error(f"Error clicking main-page tab '{tab_label}': {e}")
            return {}, []
    aggregated_grid_data = {}
    aggregated_issue_urls = []
    unique_urls = set()
    page_counter = 1
    while True:
        logging.info(f"  [Tab '{tab_label}'] Scraping page {page_counter}...")
        lazy_scroll(driver)
        try:
            grid_issue_elements = WebDriverWait(driver, 15).until(
                EC.presence_of_all_elements_located((By.CSS_SELECTOR, "a.grid_issue"))
            )
        except TimeoutException:
            logging.error(f"Timeout waiting for grid issues on page {page_counter}, tab '{tab_label}'.")
            break
        new_count = 0
        for issue_elem in grid_issue_elements:
            try:
                href = issue_elem.get_attribute("href")
            except Exception:
                continue
            if not href:
                continue
            try:
                parent_td = issue_elem.find_element(By.XPATH, "./ancestor::td")
            except Exception:
                continue
            try:
                variant_elem = parent_td.find_element(By.CSS_SELECTOR, "span.d-none.d-sm-inline.f-11")
                variant_text = variant_elem.text.strip()
            except Exception:
                variant_text = "N/A"
            variant = fix_repeated_text(variant_text)
            try:
                edition_elem = parent_td.find_element(By.CSS_SELECTOR, "span.d-block.mt-1.text-black.f-10.fw-bold")
                edition_text = edition_elem.text.strip()
            except Exception:
                edition_text = "N/A"
            edition = fix_repeated_text(edition_text)
            date_field = "N/A"
            try:
                info_div = parent_td.find_element(By.CSS_SELECTOR, "div.grid_issue_info")
                date_span = info_div.find_element(By.CSS_SELECTOR, "span.d-none.d-sm-inline")
                date_field = fix_repeated_text(date_span.text.strip())
            except Exception:
                info_div = None
            years_field = "N/A"
            try:
                if info_div:
                    years_elem = info_div.find_element(By.ID, "spYears")
                    years_field = years_elem.text.strip()
                else:
                    years_elem = parent_td.find_element(By.ID, "spYears")
                    years_field = years_elem.text.strip()
            except Exception:
                try:
                    years_elem = driver.find_element(By.ID, "spYears")
                    years_field = years_elem.text.strip()
                except Exception:
                    years_field = "N/A"
            if href.startswith("/"):
                href_full = "https://www.comicspriceguide.com" + href
            else:
                href_full = href
            aggregated_grid_data[get_relative_url(href_full)] = {
                "Years": years_field,
                "Date": date_field,
                "Variant": variant,
                "Edition": edition,
                "MainTab": tab_label,
            }
            if href_full not in unique_urls:
                unique_urls.add(href_full)
                aggregated_issue_urls.append(href_full)
                new_count += 1
                logging.info(f"  Saving from Tab '{tab_label}': {href_full} (Variant='{variant}', Edition='{edition}', Date='{date_field}', Years='{years_field}')")
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
    all_aggregated_grid_data = {}
    all_aggregated_urls = []
    urls_to_process = []
    if SCRAPE_DEPTH == 3:
        logging.info("SCRAPE_DEPTH==3: Scraping main pages for series links first.")
        for url in TARGET_URLS:
            if not url.strip():
                continue
            series_data = scrape_main_page_series(driver, url)
            logging.info(f"Found {len(series_data)} series links on main page: {url}")
            for item in series_data:
                urls_to_process.append(item)
    else:
        urls_to_process = [{"url": u, "years": "N/A"} for u in TARGET_URLS if u.strip()]
    for item in urls_to_process:
        url = item["url"]
        logging.info(f"Loading grid page: {url}")
        driver.get(url)
        if ENABLE_VARIANTS_CHECK:
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
            logging.warning("No tabs found on the grid page. Possibly everything is under a single default tab.")
            continue
        logging.info(f"Found {len(tabs)} tabs on grid page: {url}")
        for i in range(len(tabs)):
            current_tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
            if i >= len(current_tabs):
                break
            tab_elem = current_tabs[i]
            try:
                tab_label = tab_elem.text.strip() or f"Tab#{i+1}"
            except StaleElementReferenceException:
                tab_label = f"Tab#{i+1}"
            logging.info(f"\n==> Processing grid page tab {i+1}/{len(current_tabs)}: '{tab_label}' <==")
            tab_grid_data, tab_urls = scrape_one_tab(driver, tab_elem)
            all_aggregated_grid_data.update(tab_grid_data)
            all_aggregated_urls.extend(tab_urls)
    unique_full_urls = list(dict.fromkeys(all_aggregated_urls))
    logging.info(f"Aggregated total: {len(unique_full_urls)} unique issue URLs across all grid pages.")
    return all_aggregated_grid_data, unique_full_urls

def process_detail_page(url, grid_data):
    logging.info(f"Processing detail page: {url}")
    results_local = []
    try:
        data_list = scrape_detail_with_tabs(driver, url)
        if not data_list:
            logging.info(f"Retrying URL: {url}")
            time.sleep(3)
            data_list = scrape_detail_with_tabs(driver, url)
        if data_list:
            for data in data_list:
                rel_url = get_relative_url(url)
                partial_info = grid_data.get(rel_url, {})
                data["Tab"] = partial_info.get("MainTab", data.get("Tab", "Default"))
                data["Date"] = partial_info.get("Date", "N/A")
                data["Years"] = partial_info.get("Years", "N/A")
                data["Variant"] = partial_info.get("Variant", "N/A")
                data["Edition"] = partial_info.get("Edition", "N/A")
                results_local.append(data)
        time.sleep(random.uniform(2, 5))
        return results_local
    except Exception as e:
        logging.error(f"Error processing detail page {url}: {e}")
        return []

def scrape_main_page_series(driver, url):
    driver.get(url)
    series_data = {}
    page_counter = 1
    while True:
        logging.info(f"Scraping main page: {url} - Page {page_counter}")
        time.sleep(3)
        lazy_scroll(driver)
        try:
            WebDriverWait(driver, 20).until(
                EC.presence_of_all_elements_located((By.CSS_SELECTOR, "a.fkTitleLnk.grid_title"))
            )
        except TimeoutException:
            logging.info("Timeout waiting for series links. Possibly no more links.")
        from bs4 import BeautifulSoup
        soup = BeautifulSoup(driver.page_source, "html.parser")
        links = soup.find_all("a", class_="fkTitleLnk grid_title")
        page_found = 0
        for link in links:
            href = link.get("href")
            if href:
                if href.startswith("/"):
                    full_url = "https://www.comicspriceguide.com" + href
                else:
                    full_url = href
                years = "N/A"
                if full_url not in series_data:
                    series_data[full_url] = years
                    page_found += 1
                    logging.info(f"Found series link: {full_url} with Years: {years}")
        logging.info(f"Page {page_counter} found {page_found} new series links; running total: {len(series_data)}")
        if page_found == 0:
            logging.info("No new series links found on this page. Ending pagination.")
            break
        try:
            next_button = driver.find_element(By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button")
            if "dx-state-disabled" in next_button.get_attribute("class"):
                logging.info("Main page next button disabled. No more pages.")
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_button)
            time.sleep(1)
            next_button.click()
            logging.info("Clicked main page next button.")
            page_counter += 1
            time.sleep(random.uniform(2, 4))
        except Exception as e:
            logging.info(f"Error or no next button on main page: {e}")
            break
    return [{"url": url, "years": years} for url, years in series_data.items()]

def main():
    print("Script started...")
    logging.info("Script started...")
    global driver
    driver = setup_driver()
    username = "2xd"
    password = "19731973"
    if not login_to_site(driver, username, password):
        logging.error("Login failed. Exiting script.")
        driver.quit()
        print("Login failed. Exiting script.")
        return
    print("Login successful. Starting scraping...")
    grid_data, issue_urls = scrape_all_grid_pages(driver)
    total_issues = len(issue_urls)
    logging.info(f"Found {total_issues} total issue URLs (from all tabs).")
    print(f"Found {total_issues} total issue URLs.")
    if total_issues == 0:
        print("No issues found to process. Exiting.")
        driver.quit()
        return
    results = []
    processed_count = 0
    for url in issue_urls:
        data_list = process_detail_page(url, grid_data)
        if data_list:
            results.extend(data_list)
        processed_count += 1
        logging.info(f"Completed detail page: {url}")
        if processed_count % 5 == 0:
            live_df = pd.DataFrame(results)
            live_df.to_excel("scraped_comics_live.xlsx", index=False)
            logging.info(f"Live status update: Processed {processed_count} series.")
    if results:
        df = pd.DataFrame(results)
        df.rename(columns={"Publisher": "Publisher Name"}, inplace=True)
        if "Issues Note" not in df.columns:
            df["Issues Note"] = "N/A"
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
            "Publisher Name",
        ]
        existing_cols = [c for c in desired_order if c in df.columns]
        df = df.reindex(columns=existing_cols)
        df.to_excel("scraped_comics.xlsx", index=False)
        logging.info("Data saved to scraped_comics.xlsx")
        print("Data saved to scraped_comics.xlsx")
    else:
        logging.info("No data scraped or no new data to save.")
        print("No data scraped or no new data to save.")
    print("Script completed successfully.")
    logging.info("Script completed successfully.")
    driver.quit()

if __name__ == "__main__":
    main()

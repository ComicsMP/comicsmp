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
from bs4 import BeautifulSoup
import concurrent.futures
from datetime import datetime, timedelta

# For auto driver management (without special version checks)
from webdriver_manager.chrome import ChromeDriverManager

# ========================= EDIT THESE OPTIONS BEFORE RUNNING =========================
# 1) Which page/series do you want to scrape?
TARGET_URL = "https://www.comicspriceguide.com/titles/belladonna/shwrj"

# 2) Set to 1 to click 'Show Variants' checkbox (if present). 0 to skip.
ENABLE_VARIANTS_CHECK = 0

# 3) Skip-logic disabled: 0 means ALWAYS re-scan all issues
SKIP_ALREADY_SCRAPED = 0

# List of target URLs to scrape. The first URL is the one above (TARGET_URL) and you can add more.
TARGET_URLS = [
    TARGET_URL,
    "https://www.comicspriceguide.com/titles/belladonna/ubtvk",
    "https://www.comicspriceguide.com/titles/belladonna-2004-convention-special/skyro", 
    "https://www.comicspriceguide.com/titles/belladonna-preview/skqto"
    # Add additional URLs as needed.
]
# ====================================================================================

# Predefined list of known countries (from original script)
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
    """
    Logs into the site by clicking the login button to open the popup,
    entering credentials, clicking the submit button, and waiting for
    the login popup to disappear.
    Returns True if login is successful, False otherwise.
    """
    driver.get("https://www.comicspriceguide.com/")
    time.sleep(2)

    try:
        # Click the login button to open the popup
        login_button = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.XPATH, "//span[contains(text(),'Login')]"))
        )
        login_button.click()
        logging.info("Clicked Login button")
        time.sleep(2)

        # Wait for the username field to appear
        username_field = WebDriverWait(driver, 10).until(
            EC.visibility_of_element_located((By.XPATH, "//div[@id='dvUser']//input"))
        )
        username_field.clear()
        username_field.send_keys(username)
        logging.info("Entered Username")

        # Wait for the password field to appear
        password_field = WebDriverWait(driver, 10).until(
            EC.visibility_of_element_located((By.XPATH, "//div[@id='dvPassword']//input"))
        )
        password_field.clear()
        password_field.send_keys(password)
        logging.info("Entered Password")

        # Click the login button inside the popup
        submit_button = WebDriverWait(driver, 10).until(
            EC.element_to_be_clickable((By.XPATH, "//div[@id='btnLgn']"))
        )
        submit_button.click()
        logging.info("Clicked Login Submit button")
        time.sleep(5)  # Allow time for processing

        # Wait until the login popup disappears (i.e. the submit button is gone)
        WebDriverWait(driver, 20).until(
            EC.invisibility_of_element_located((By.ID, "btnLgn"))
        )
        logging.info("Login successful! (Login popup disappeared)")
        return True

    except Exception as e:
        logging.error(f"Error during login process: {e}")
        return False

def setup_driver():
    """
    Sets up the Chrome WebDriver using the system's default driver.
    Uses a fixed user-agent (Chrome/133) with no version checks.
    """
    options = webdriver.ChromeOptions()
    # For debugging you can remove "--headless" to see the browser window
    options.add_argument("--headless")
    options.add_argument("--disable-gpu")
    options.add_argument("--no-sandbox")
    options.add_argument("--enable-unsafe-swiftshader")
    options.add_argument("--ignore-certificate-errors")
    options.add_argument("--disable-dev-shm-usage")
    options.add_argument("--log-level=3")
    options.add_experimental_option("excludeSwitches", ["enable-logging"])
    options.add_argument(
        "user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/133.0.0.0 Safari/537.36"
    )
    service = Service()  # Use the system default ChromeDriver
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
            or d.find_elements(By.CSS_SELECTOR, "span#lblYears")
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

    soup = BeautifulSoup(driver.page_source, "html.parser")
    # Modified: assign span#lblYears to 'Years' from detail page
    years_element = soup.select_one("span#lblYears")
    detail_years = years_element.text.strip() if years_element else "N/A"

    issue_props = soup.select_one("div.mt-0.mb-0.issue-prop.text-muted")
    if issue_props:
        country = extract_country(issue_props.text)
        volume = extract_volume(issue_props.text)
    else:
        country, volume = "N/A", "N/A"

    image_element = soup.select_one("img.img-responsive.img-thumbnail")
    if image_element and image_element.has_attr("src"):
        image_url = "https://www.comicspriceguide.com" + image_element["src"]
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
        # Now 'Years' comes from detail page's span#lblYears
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
            or d.find_elements(By.CSS_SELECTOR, "span#lblYears")
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
        tab_label = tab_button.text.strip() or f"Tab#{idx+1}"
        aria_pressed = tab_button.get_attribute("aria-pressed")
        class_attr = tab_button.get_attribute("class")
        is_selected = (aria_pressed == "true") or ("dx-state-selected" in class_attr)
        logging.info(
            "Processing detail-page Tab %s/%s: '%s' (selected=%s)",
            idx + 1, len(current_tabs), tab_label, is_selected
        )
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
        soup = BeautifulSoup(driver.page_source, "html.parser")
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
            if href.startswith("/"):
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
                logging.info(
                    f"  Saving from Tab '{tab_label}': {href_full} (Variant='{variant}', Edition='{edition}', Years='{years}')"
                )
        logging.info(
            f"  [Tab '{tab_label}'] Page {page_counter}: Found {new_count} new issues (Total unique so far: {len(unique_urls)})."
        )
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
    # Modified: iterate over all target URLs in TARGET_URLS
    for url in TARGET_URLS:
        logging.info(f"Loading main page: {url}")
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
            logging.warning("No tabs found on the main page. Possibly everything is under a single default tab.")
            continue
        logging.info(f"Found {len(tabs)} tabs on main page: {url}")
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

def process_detail_page(url, grid_data):
    logging.info(f"Processing detail page: {url}")
    # Use the same driver (which is still logged in) to scrape detail pages sequentially.
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
                # Preserve detail page 'Years' from scrape_single_url and set 'Date' from grid data's 'Years'
                data["Date"] = partial_info.get("Years", "N/A")
                data["Variant"] = partial_info.get("Variant", "N/A")
                data["Edition"] = partial_info.get("Edition", "N/A")
                results_local.append(data)
        time.sleep(random.uniform(2, 5))
        return results_local
    except Exception as e:
        logging.error(f"Error processing detail page {url}: {e}")
        return []
    
# Main function: uses a single driver session to log in and scrape all pages sequentially.
def main():
    print("Script started...")
    logging.info("Script started...")
    
    global driver
    driver = setup_driver()
    
    # Login
    username = "2xd"
    password = "19731973"
    if not login_to_site(driver, username, password):
        logging.error("Login failed. Exiting script.")
        driver.quit()
        print("Login failed. Exiting script.")
        return
    print("Login successful. Starting scraping...")
    
    # Scrape grid pages from all target URLs
    grid_data, issue_urls = scrape_all_grid_pages(driver)
    total_issues = len(issue_urls)
    logging.info(f"Found {total_issues} total issue URLs (from all tabs).")
    print(f"Found {total_issues} total issue URLs.")
    
    if total_issues == 0:
        print("No issues found to process. Exiting.")
        driver.quit()
        return
    
    results = []
    # Process each detail page sequentially using the same driver
    for url in issue_urls:
        data_list = process_detail_page(url, grid_data)
        if data_list:
            results.extend(data_list)
        logging.info(f"Completed detail page: {url}")
    
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

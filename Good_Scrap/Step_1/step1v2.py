# ─────────────────────────── ComicPriceGuide Scraper v2.1 ───────────────────────────
# • fixes “Years” (now from <span id="spYears"> on the series page)
# • fixes “Tab” column (uses grid-tab label)
# • writes incremental progress to  live_scraped_comics.xlsx  every 20 issues
# • skips re-scraping URLs scraped within SKIP_DAYS (14)
# • prunes log entries older than CUTOFF_DAYS (20)
# ─────────────────────────────────────────────────────────────────────────────────────

import time, random, os, shutil, logging, concurrent.futures
from datetime import datetime, timedelta
from urllib.parse import urlparse

import pandas as pd
import re
import subprocess
import platform
from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.chrome.service import Service
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.common.exceptions import (
    TimeoutException, StaleElementReferenceException,
    ElementClickInterceptedException
)
from webdriver_manager.chrome import ChromeDriverManager


# ──────────────── Kill any leftover Chrome or Chromedriver processes ────────────────
def kill_old_chrome_processes():
    chrome_names = ["chromedriver.exe", "chrome.exe"]
    for name in chrome_names:
        try:
            subprocess.call(f"taskkill /f /im {name} >nul 2>&1", shell=True)
        except Exception as e:
            print(f"Error killing {name}: {e}")

kill_old_chrome_processes()  # run this once before scraping


# ─────────────────────────────── LOGGING ───────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s %(levelname)s: %(message)s",
    handlers=[
        logging.FileHandler("scraped_comics_v2.log", encoding="utf-8", errors="replace"),
        logging.StreamHandler()
    ]
)

# ───────────────────────── GLOBAL CONSTANTS ────────────────────────────
SKIP_ALREADY_SCRAPED = 1
SKIP_DAYS            = 14   # ignore URLs scraped in the last 14 days
CUTOFF_DAYS          = 20   # remove log entries older than 20 days
LIVE_UPDATE_EVERY    = 20   # write live_scraped_comics.xlsx after this many new rows

KNOWN_COUNTRIES = {
    "USA","United Kingdom","Canada","Australia","Germany","France","Mexico",
    "India","Japan","Italy","Spain","Argentina","Belgium","Brazil","Bulgaria",
    "Chile","China","Colombia","Congo (Zaire)","Croatia","Czech Republic",
    "Denmark","Egypt","Finland","Greece","Hong Kong","Hungary","Iceland",
    "Ireland","Israel","Kenya","Latvia","Luxembourg","Netherlands",
    "New Zealand","Norway","Philippines","Poland","Portugal","Puerto Rico",
    "Romania","Russia","Singapore","Slovenia","South Africa","South Korea",
    "Sweden","Switzerland","Taiwan","Thailand","Lebanon","Bermuda","Austria",
    "British Virgin Islands","Iraq","Malaysia","Serbia and Montenegro (Yugoslavia)",
    "Ukraine","United Arab Emirates"
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


# ───────────────────────────── UTILITIES ───────────────────────────────
def setup_driver() -> webdriver.Chrome:
    opts = webdriver.ChromeOptions()
    opts.add_argument("--headless"); opts.add_argument("--disable-gpu")
    opts.add_argument("--no-sandbox"); opts.add_argument("--disable-dev-shm-usage")
    opts.add_argument("--log-level=3")
    opts.add_experimental_option("excludeSwitches", ["enable-logging"])
    opts.add_argument(
        "user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.7049.42 Safari/537.36"
    )
    service = Service(ChromeDriverManager().install())
    return webdriver.Chrome(service=service, options=opts)

def click_tab_button(driver: webdriver.Chrome, elem):
    """scroll → click; JS-fallback if intercepted; 2-sec settle time"""
    WebDriverWait(driver, 10).until(EC.visibility_of(elem))
    driver.execute_script("arguments[0].scrollIntoView(true);", elem)
    driver.execute_script("window.scrollBy(0,-150);")
    try:
        elem.click()
    except (ElementClickInterceptedException, StaleElementReferenceException):
        driver.execute_script("arguments[0].click();", elem)
    time.sleep(2)

def lazy_scroll(driver):
    while True:
        old_h = driver.execute_script("return document.body.scrollHeight")
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(2)
        if driver.execute_script("return document.body.scrollHeight") == old_h:
            break

def fix_repeated_text(s: str) -> str:
    s = s.strip()
    if not s:
        return s
    return s[:len(s)//2] if len(s)%2==0 and s[:len(s)//2]==s[len(s)//2:] else s

def fix_glued_variant(s: str) -> str:
    s = s.strip()
    if len(s) < 40:
        return s
    first20, pos = s[:20], s.find(s[:20], 20)
    return s[:pos] if pos != -1 else s

def extract_volume(t):
    return t.split("Volume")[1].split()[0].strip() if "Volume" in t else "N/A"

def rel(u):
    return urlparse(u).path

# ────────────────────────── LOG CLEANUP UTIL ───────────────────────────
def clean_scraped_file(filename, keep_dict):
    """Overwrite *filename* with only the entries in *keep_dict*."""
    with open(filename, "w", encoding="utf-8") as f:
        for url, ts_obj in keep_dict.items():
            f.write(f"{url}|{ts_obj:%Y-%m-%d %H:%M:%S}\n")

# ───────────────────── SCRAPE: DETAIL-PAGE HELPERS ─────────────────────
def _fill_series_years(driver, soup, result):
    """visit breadcrumb 3 or 4 → pull <span id='spYears'>"""
    for pos in (3, 4):
        bc = soup.select_one(f"ol.breadcrumb li:nth-child({pos}) a")
        if not bc or not bc.has_attr("href"):
            continue
        url = bc["href"]
        url = url if url.startswith("http") else "https://www.comicspriceguide.com" + url
        try:
            driver.get(url)
            WebDriverWait(driver,10).until(lambda d: d.execute_script("return document.readyState") == "complete")
            yrs = BeautifulSoup(driver.page_source,"html.parser").select_one("span#spYears")
            if yrs:
                result["Years"] = yrs.get_text(strip=True)
                logging.info(f"Series years found ({pos}): {result['Years']}")
                return
        except TimeoutException:
            logging.warning(f"Timeout visiting series breadcrumb {pos}: {url}")
    logging.info("Series years not found; leaving as N/A")

def scrape_single_url(driver, url):
    logging.info(f"Scraping URL: {url}")
    driver.get(url)
    WebDriverWait(driver, 30).until(
        lambda d: d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0")
              or d.find_elements(By.CSS_SELECTOR, "span#lblYears")
    )
    WebDriverWait(driver,10).until(lambda d: d.execute_script("return document.readyState") == "complete")

    soup = BeautifulSoup(driver.page_source, "html.parser")

    date_el   = soup.select_one("span#lblYears")
    props_el  = soup.select_one("div.mt-0.mb-0.issue-prop.text-muted")
    img_el    = soup.select_one("img.img-responsive.img-thumbnail")
    title_el  = soup.select_one("div.h--title.pb-0")
    pub_el    = soup.select_one("ol.breadcrumb li:nth-child(2)")

    if title_el:
        t = title_el.text.strip()
        parts = t.split("#",1)
        title, issue_no = parts[0], parts[1] if len(parts)==2 else "N/A"
    else:
        title = issue_no = "N/A"

    result = {
        "Comic_Title"   : title.strip(),
        "Issue_Number"  : issue_no.strip(),
        "Publisher_Name": pub_el.text.strip() if pub_el else "N/A",
        "Date"          : date_el.text.strip() if date_el else "N/A",
        "Country"       : extract_country(props_el.text) if props_el else "N/A",
        "Volume"        : extract_volume(props_el.text) if props_el else "N/A",
        "Image_URL"     : ("https://www.comicspriceguide.com"+img_el["src"]) if img_el else "N/A",
        "Issue_URL"     : url,
        "Years"         : "N/A",
        "Issues_Note"   : "N/A"
    }

    _fill_series_years(driver, soup, result)
    return result

def scrape_detail_with_tabs(driver, url):
    results = []
    driver.get(url)
    WebDriverWait(driver,30).until(
        lambda d: d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0")
              or d.find_elements(By.CSS_SELECTOR, "span#lblYears")
    )

    tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
    if not tabs:
        d = scrape_single_url(driver, url)
        d and results.append({**d, "Tab":"Default"})
        return results

    for i in range(len(tabs)):
        tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        btn  = tabs[i]
        label = btn.text.strip() or f"Tab#{i+1}"
        if btn.get_attribute("aria-pressed") != "true":
            click_tab_button(driver, btn)
        d = scrape_single_url(driver, url)
        d and results.append({**d, "Tab":label})
    return results

def scrape_one_tab(driver, tab_elem, tab_label):
    if tab_elem.get_attribute("aria-pressed") != "true":
        click_tab_button(driver, tab_elem)

    WebDriverWait(driver,10).until(
        EC.presence_of_all_elements_located((By.CSS_SELECTOR,"a.grid_issue"))
    )

    grid_data, urls, seen = {}, [], set()
    page = 1

    while True:
        logging.info(f"[Tab '{tab_label}'] Scraping page {page} …")
        lazy_scroll(driver)
        soup  = BeautifulSoup(driver.page_source,"html.parser")
        links = soup.find_all("a", class_="grid_issue")
        new = 0

        for a in links:
            href = a.get("href")
            full = href if href.startswith("http") else "https://www.comicspriceguide.com" + href
            if full in seen:
                continue
            seen.add(full)
            urls.append(full)
            new += 1

            td       = a.find_parent("td")
            years_el = td.select_one("div.grid_issue_info span.d-none.d-sm-inline")
            var_el   = td.select_one("span.d-none.d-sm-inline.f-11")
            edt_el   = td.select_one("span.f-10")
            grid_data[rel(full)] = {
                "MainTab": tab_label,
                "Years"  : fix_repeated_text(years_el.get_text(strip=True)) if years_el else "N/A",
                "Variant": fix_glued_variant(var_el.get_text(strip=True)) if var_el else "N/A",
                "Edition": fix_repeated_text(edt_el.get_text(strip=True)) if edt_el else "N/A"
            }

        logging.info(f"[Tab '{tab_label}'] page {page}: {new} new issues.")
        if new == 0:
            break

        # ─── Extract pagination footer and compute next page ───
        pag_txt = driver.find_element(
            By.XPATH,
            "//div[contains(text(),'Page') and contains(text(),'items')]"
        ).text
        m = re.search(r'Page \d+ of (\d+)', pag_txt)
        prev_page = int(re.search(r'Page (\d+)', pag_txt).group(1))
        
        # ─── Try to click Next until page number increases ───
        attempts, success = 0, False
        while attempts < 5 and not success:
            next_btns = driver.find_elements(By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button")
            if not next_btns or "dx-state-disabled" in next_btns[0].get_attribute("class"):
                break
            click_tab_button(driver, next_btns[0])
            try:
                WebDriverWait(driver, 10).until(
                    lambda d: int(
                        re.search(
                            r'Page (\d+)',
                            d.find_element(
                                By.XPATH,
                                "//div[contains(text(),'Page') and contains(text(),'items')]"
                            ).text
                        ).group(1)
                    ) > prev_page
                )
                success = True
            except TimeoutException:
                attempts += 1
                time.sleep(1.5)

        if not success:
            break

        page += 1

    return grid_data, urls

def scrape_all_grid_pages(driver):
    grid_url = "https://www.comicspriceguide.com/new-comics"
    logging.info(f"Loading main page: {grid_url}")
    driver.get(grid_url)

    # cookie consent
    try:
        consent = WebDriverWait(driver,5).until(
            EC.element_to_be_clickable((By.XPATH,
                "//button[contains(text(),'Consent') or contains(text(),'Agree')]"))
        )
        consent.click()
        logging.info("Cookie consent clicked.")
        time.sleep(2)
    except TimeoutException:
        logging.info("No consent banner.")

    # show variants
    try:
        variants = WebDriverWait(driver,10).until(
            EC.element_to_be_clickable((By.XPATH,
                "//span[contains(text(),'Show Variants')]"))
        )
        driver.execute_script("arguments[0].click();", variants)
        logging.info("'Show Variants' checked.")
        time.sleep(2)
    except TimeoutException:
        logging.info("'Show Variants' control not found or already active.")

    all_grid, all_urls = {}, []
    num_tabs = len(driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']"))
    logging.info(f"Beginning scrape across {num_tabs} tabs")

    for i in range(num_tabs):
        tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        tab_elem = tabs[i]
        label = tab_elem.text.strip() or f"Tab#{i+1}"
        logging.info(f"\n==> Processing main-page tab {i+1}/{num_tabs}: '{label}' <==")
        tab_grid, tab_urls = scrape_one_tab(driver, tab_elem, label)
        all_grid.update(tab_grid)
        all_urls.extend(tab_urls)

    unique_urls = list(dict.fromkeys(all_urls))
    logging.info(f"Aggregated {len(unique_urls)} unique issue URLs across all tabs.")
    return all_grid, unique_urls

def process_detail_page(url, scraped_log, grid_data):
    # skip if we scraped this URL in the last SKIP_DAYS
    if SKIP_ALREADY_SCRAPED and url in scraped_log:
        ts = scraped_log[url]
        if ts and (datetime.now() - ts) < timedelta(days=SKIP_DAYS):
            logging.info(f"Skipping (recent) {url}")
            return []

    drv = setup_driver()
    try:
        data = scrape_detail_with_tabs(drv, url)  # ① detail scrape (tabs aware)

        for d in data:
            rel_path = rel(url)
            # ② merge everything from the grid EXCEPT the Years field
            d.update({k: v for k, v in grid_data.get(rel_path, {}).items() if k != "Years"})
            # ③ if Years still N/A, fall back to the grid’s value
            if d.get("Years") == "N/A":
                d["Years"] = grid_data.get(rel_path, {}).get("Years", "N/A")
            # ④ promote MainTab → Tab
            if "MainTab" in d:
                d["Tab"] = d.pop("MainTab")

        # log URL + timestamp so we skip it next run
        with open("scraped_urls_v2.txt", "a", encoding="utf-8") as f:
            f.write(f"{url}|{datetime.now():%Y-%m-%d %H:%M:%S}\n")

        return data

    except Exception as e:
        logging.error(f"Detail scrape error {url}: {e}")
        return []

    finally:
        drv.quit()

# ──────────────────────────── MAIN WORKFLOW ────────────────────────────
def main():
    # 1) Load scrape logs with CUTOFF filtering
    scraped_log = {}
    if SKIP_ALREADY_SCRAPED:
        for fn in ("scraped_urls.txt", "scraped_urls_v2.txt"):
            try:
                with open(fn, "r", encoding="utf-8") as f:
                    for line in f:
                        url, ts = (line.strip().split("|") + [None])[:2]
                        if ts:
                            ts_obj = datetime.strptime(ts, "%Y-%m-%d %H:%M:%S")
                            if (datetime.now() - ts_obj) < timedelta(days=CUTOFF_DAYS):
                                scraped_log[url] = ts_obj
            except FileNotFoundError:
                pass
        # 2) Clean up old entries from the log file
        clean_scraped_file("scraped_urls_v2.txt", scraped_log)

    # 3) Gather grid
    grid_driver = setup_driver()
    grid_data, issue_urls = scrape_all_grid_pages(grid_driver)
    grid_driver.quit()

    logging.info(f"{len(issue_urls)} detail URLs collected; starting detail scrape …")
    results, live_counter = [], 0
    with concurrent.futures.ThreadPoolExecutor(max_workers=4) as ex:
        fut_to_url = {ex.submit(process_detail_page, u, scraped_log, grid_data): u for u in issue_urls}
        for fut in concurrent.futures.as_completed(fut_to_url):
            res = fut.result()
            results.extend(res)
            live_counter += len(res)

            # live XLSX every N new rows
            if live_counter >= LIVE_UPDATE_EVERY:
                df_live = pd.DataFrame(results)
                order = ["Tab","Comic_Title","Years","Volume","Country","Issues_Note",
                         "Issue_Number","Issue_URL","Image_URL","Date","Variant","Edition",
                         "Publisher_Name"]
                df_live = df_live.reindex(columns=[c for c in order if c in df_live.columns])
                df_live.to_excel("live_scraped_comics.xlsx", index=False)
                logging.info(f"live_scraped_comics.xlsx updated ({len(results)} rows).")
                live_counter = 0

        # 4) Final save
    if results:
        df = pd.DataFrame(results)
        order = ["Tab","Comic_Title","Years","Volume","Country","Issues_Note",
                 "Issue_Number","Issue_URL","Image_URL","Date","Variant","Edition",
                 "Publisher_Name"]
        df = df.reindex(columns=[c for c in order if c in df.columns])
        df.to_excel("scraped_comics.xlsx", index=False)
        logging.info("Data saved to scraped_comics.xlsx")
        os.makedirs("../Step_2", exist_ok=True)
        shutil.move("scraped_comics.xlsx", "../Step_2/scraped_comics.xlsx")

        # Save sample_check.xlsx with the last 10 results
        try:
            df_sample = pd.DataFrame(results[-10:])
            df_sample = df_sample.reindex(columns=[c for c in order if c in df_sample.columns])
            df_sample.to_excel("sample_check.xlsx", index=False)
            logging.info("Saved sample_check.xlsx with the last 10 rows.")
        except Exception as e:
            logging.warning(f"Could not save sample_check.xlsx: {e}")

        # Auto-open sample_check.xlsx
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
        logging.info("No new data scraped.")


if __name__ == "__main__":
    main()

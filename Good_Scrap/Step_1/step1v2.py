# ─────────────────────────── ComicPriceGuide Scraper v2.1 ───────────────────────────
# • fixes “Years” (now from <span id="spYears"> on the series page)
# • fixes “Tab” column (uses grid-tab label)
# • writes incremental progress to  live_scraped_comics.xlsx  every 20 issues
# ─────────────────────────────────────────────────────────────────────────────────────
import time, random, os, shutil, logging, concurrent.futures
from datetime import datetime, timedelta
from urllib.parse import urlparse

import pandas as pd
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
LIVE_UPDATE_EVERY     = 20          # write live_scraped_comics.xlsx after this many new rows
KNOWN_COUNTRIES = {"USA","United Kingdom","Canada","Australia","Germany","France","Mexico",
                   "India","Japan","Italy","Spain","Argentina","Belgium","Brazil","Bulgaria",
                   "Chile","China","Colombia","Congo (Zaire)","Croatia","Czech Republic",
                   "Denmark","Egypt","Finland","Greece","Hong Kong","Hungary","Iceland",
                   "Ireland","Israel","Kenya","Latvia","Luxembourg","Netherlands",
                   "New Zealand","Norway","Philippines","Poland","Portugal","Puerto Rico",
                   "Romania","Russia","Singapore","Slovenia","South Africa","South Korea",
                   "Sweden","Switzerland","Taiwan","Thailand","Lebanon","Bermuda","Austria",
                   "British Virgin Islands","Iraq","Malaysia","Serbia and Montenegro (Yugoslavia)",
                   "Ukraine","United Arab Emirates"}

# ───────────────────────────── UTILITIES ───────────────────────────────
def setup_driver() -> webdriver.Chrome:
    opts = webdriver.ChromeOptions()
    opts.add_argument("--headless"); opts.add_argument("--disable-gpu")
    opts.add_argument("--no-sandbox"); opts.add_argument("--disable-dev-shm-usage")
    opts.add_argument("--log-level=3"); opts.add_experimental_option(
        "excludeSwitches", ["enable-logging"])
    opts.add_argument(
        "user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) "
        "AppleWebKit/537.36 (KHTML, like Gecko) Chrome/135.0.7049.42 Safari/537.36"
    )
    service = Service(ChromeDriverManager(driver_version="135.0.7049.42").install())
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
    if not s: return s
    return s[:len(s)//2] if len(s)%2==0 and s[:len(s)//2]==s[len(s)//2:] else s


def fix_glued_variant(s: str) -> str:
    s = s.strip()
    if len(s) < 40: return s
    first20, pos = s[:20], s.find(s[:20], 20)
    return s[:pos] if pos != -1 else s


def extract_country(t):   return next((c for c in KNOWN_COUNTRIES if c in t), "N/A")
def extract_volume(t):
    return t.split("Volume")[1].split()[0].strip() if "Volume" in t else "N/A"
def rel(u): return urlparse(u).path


# ───────────────────── SCRAPE: DETAIL-PAGE HELPERS ─────────────────────
def _fill_series_years(driver, soup, result):
    """visit breadcrumb 3 or 4 → pull <span id='spYears'>"""
    for pos in (3, 4):
        bc = soup.select_one(f"ol.breadcrumb li:nth-child({pos}) a")
        if not bc or not bc.has_attr("href"): continue
        url = bc["href"]; url = url if url.startswith("http") else "https://www.comicspriceguide.com"+url
        try:
            driver.get(url)
            WebDriverWait(driver,10).until(
                lambda d: d.execute_script("return document.readyState") == "complete")
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
        lambda d: d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0") or
                  d.find_elements(By.CSS_SELECTOR, "span#lblYears"))
    WebDriverWait(driver,10).until(
        lambda d: d.execute_script("return document.readyState") == "complete")

    soup = BeautifulSoup(driver.page_source, "html.parser")

    date_el   = soup.select_one("span#lblYears")
    props_el  = soup.select_one("div.mt-0.mb-0.issue-prop.text-muted")
    img_el    = soup.select_one("img.img-responsive.img-thumbnail")
    title_el  = soup.select_one("div.h--title.pb-0")
    pub_el    = soup.select_one("ol.breadcrumb li:nth-child(2)")

    if title_el:
        t = title_el.text.strip(); parts = t.split("#",1)
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
    WebDriverWait(driver, 30).until(
        lambda d: d.find_elements(By.CSS_SELECTOR, "div.h--title.pb-0") or
                  d.find_elements(By.CSS_SELECTOR, "span#lblYears"))

    tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
    if not tabs:
        d = scrape_single_url(driver, url); d and results.append({**d,"Tab":"Default"}); return results

    for i in range(len(tabs)):
        tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        btn  = tabs[i]; label = btn.text.strip() or f"Tab#{i+1}"
        if btn.get_attribute("aria-pressed") != "true":
            click_tab_button(driver, btn)
        d = scrape_single_url(driver, url); d and results.append({**d,"Tab":label})
    return results


# ─────────────────── SCRAPE: GRID / TAB-LEVEL HELPERS ───────────────────
def scrape_one_tab(driver, tab_elem, tab_label):
    if tab_elem.get_attribute("aria-pressed") != "true":
        click_tab_button(driver, tab_elem)

    WebDriverWait(driver,10).until(
        EC.presence_of_all_elements_located((By.CSS_SELECTOR,"a.grid_issue"))
    )

    grid_data, urls, seen = {}, [], set(); page = 1
    while True:
        logging.info(f"[Tab '{tab_label}'] Scraping page {page} …")
        lazy_scroll(driver)
        soup  = BeautifulSoup(driver.page_source,"html.parser")
        links = soup.find_all("a", class_="grid_issue"); new = 0

        for a in links:
            href = a.get("href"); full = href if href.startswith("http") else "https://www.comicspriceguide.com"+href
            if full in seen: continue
            seen.add(full); urls.append(full); new += 1

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
        if new == 0: break

        try:
            nxt = driver.find_elements(By.CSS_SELECTOR,"div.dx-navigate-button.dx-next-button")
            if not nxt or "dx-state-disabled" in nxt[0].get_attribute("class"):
                break
            click_tab_button(driver, nxt[0])
            WebDriverWait(driver,10).until(
                EC.presence_of_all_elements_located((By.CSS_SELECTOR,"a.grid_issue"))
            )
            page += 1
        except Exception as e:
            logging.error(f"[Tab '{tab_label}'] pagination error: {e}")
            break
    return grid_data, urls


# ────────────────────── SCRAPE: TABS (TOP LEVEL) ────────────────────────
def scrape_all_grid_pages(driver):
    grid_url = "https://www.comicspriceguide.com/new-comics"
    logging.info(f"Loading main page: {grid_url}")
    driver.get(grid_url)

    # cookie consent
    try:
        consent = WebDriverWait(driver,5).until(
            EC.element_to_be_clickable((By.XPATH,"//button[contains(text(),'Consent') or contains(text(),'Agree')]"))
        )
        consent.click(); logging.info("Cookie consent clicked."); time.sleep(2)
    except TimeoutException:
        logging.info("No consent banner.")

    # show variants
    try:
        variants = WebDriverWait(driver,10).until(
            EC.element_to_be_clickable((By.XPATH,"//span[contains(text(),'Show Variants')]"))
        )
        driver.execute_script("arguments[0].click();", variants)
        logging.info("'Show Variants' checked."); time.sleep(2)
    except TimeoutException:
        logging.info("'Show Variants' control not found or already active.")

    all_grid, all_urls = {}, []
    num_tabs = len(driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']"))
    logging.info(f"Beginning scrape across {num_tabs} tabs")

    for i in range(num_tabs):
        tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        tab_elem = tabs[i]; label = tab_elem.text.strip() or f"Tab#{i+1}"
        logging.info(f"\n==> Processing main-page tab {i+1}/{num_tabs}: '{label}' <==")
        tab_grid, tab_urls = scrape_one_tab(driver, tab_elem, label)
        all_grid.update(tab_grid); all_urls.extend(tab_urls)

    unique_urls = list(dict.fromkeys(all_urls))
    logging.info(f"Aggregated {len(unique_urls)} unique issue URLs across all tabs.")
    return all_grid, unique_urls


# ───────────────────── SCRAPE: DETAIL-PAGE WRAPPER ─────────────────────
def process_detail_page(url, scraped_log, grid_data):
    # skip if we scraped this URL in the last 7 days --------------------
    if SKIP_ALREADY_SCRAPED and url in scraped_log:
        ts = scraped_log[url]
        if ts and (datetime.now() - ts) < timedelta(days=7):
            logging.info(f"Skipping (recent) {url}")
            return []

    drv = setup_driver()
    try:
        data = scrape_detail_with_tabs(drv, url)          # ① scrape detail page (tabs aware)

        for d in data:
            rel_path = rel(url)

            # ② merge everything from the grid EXCEPT the Years field
            d.update({k: v for k, v in grid_data.get(rel_path, {}).items()
                      if k != "Years"})

            # ③ if the detail scrape never found a Years value, fall back to the grid’s
            if d.get("Years") == "N/A":
                d["Years"] = grid_data.get(rel_path, {}).get("Years", "N/A")

            # ④ promote MainTab → Tab (for final XLSX column)
            if "MainTab" in d:
                d["Tab"] = d.pop("MainTab")

        # log URL + timestamp so we can skip it next run ----------------
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
    # load scrape logs
    scraped_log = {}
    if SKIP_ALREADY_SCRAPED:
        for fn in ("scraped_urls.txt","scraped_urls_v2.txt"):
            try:
                with open(fn,"r",encoding="utf-8") as f:
                    for line in f:
                        url,ts = (line.strip().split("|")+[None])[:2]
                        scraped_log[url] = datetime.strptime(ts,"%Y-%m-%d %H:%M:%S") if ts else None
            except FileNotFoundError:
                pass

    # gather grid
    grid_driver = setup_driver()
    grid_data, issue_urls = scrape_all_grid_pages(grid_driver)
    grid_driver.quit()

    logging.info(f"{len(issue_urls)} detail URLs collected; starting detail scrape …")
    results, max_workers, live_counter = [], 5, 0
    with concurrent.futures.ThreadPoolExecutor(max_workers=max_workers) as ex:
        fut_to_url = {ex.submit(process_detail_page,u,scraped_log,grid_data):u for u in issue_urls}
        for fut in concurrent.futures.as_completed(fut_to_url):
            res = fut.result(); results.extend(res); live_counter += len(res)

            # ----- live XLSX every N new rows -----
            if live_counter >= LIVE_UPDATE_EVERY:
                df_live = pd.DataFrame(results)
                order = ["Tab","Comic_Title","Years","Volume","Country","Issues_Note",
                         "Issue_Number","Issue_URL","Image_URL","Date","Variant","Edition",
                         "Publisher_Name"]
                df_live = df_live.reindex(columns=[c for c in order if c in df_live.columns])
                df_live.to_excel("live_scraped_comics.xlsx", index=False)
                logging.info(f"live_scraped_comics.xlsx updated ({len(results)} rows).")
                live_counter = 0
            # --------------------------------------

    if results:
        df = pd.DataFrame(results)
        order = ["Tab","Comic_Title","Years","Volume","Country","Issues_Note",
                 "Issue_Number","Issue_URL","Image_URL","Date","Variant","Edition",
                 "Publisher_Name"]
        df = df.reindex(columns=[c for c in order if c in df.columns])
        df.to_excel("scraped_comics.xlsx", index=False)
        logging.info("Data saved to scraped_comics.xlsx")
        os.makedirs("../Step_2", exist_ok=True)
        shutil.move("scraped_comics.xlsx","../Step_2/scraped_comics.xlsx")
    else:
        logging.info("No new data scraped.")

if __name__ == "__main__":
    main()

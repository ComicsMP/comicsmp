import os
import time
import pandas as pd
from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

DONE_FILE = "scraped_series.txt"
ALL_LINKS_FILE = "all_main_page_links.txt"
MAX_ROWS_PER_CHUNK = 100_000
MAX_SERIES_PER_CHUNK = 1_000

chunk_count = 1
series_count = 0

# Set up Chrome options for headless mode
chrome_options = webdriver.ChromeOptions()
chrome_options.add_argument("--headless")
chrome_options.add_argument("--disable-gpu")
chrome_options.add_argument("--no-sandbox")
# You can also add other options if needed:
# chrome_options.add_argument("--window-size=1920,1080")

driver = webdriver.Chrome(options=chrome_options)
base_url = "https://www.comicspriceguide.com/new-scans"
driver.get(base_url)
time.sleep(5)

def save_unique_excel(df, base_filename):
    base_name, ext = os.path.splitext(base_filename)
    filename = base_filename
    counter = 1
    while os.path.exists(filename):
        filename = f"{base_name}_{counter}{ext}"
        counter += 1

    df.to_excel(filename, index=False)
    print(f"Saved {len(df)} rows -> '{filename}'")

def lazy_scroll():
    while True:
        old_height = driver.execute_script("return document.body.scrollHeight")
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(2)
        new_height = driver.execute_script("return document.body.scrollHeight")
        if new_height == old_height:
            break

def collect_all_main_page_links():
    """
    Gathers all series links from all main pages, stops if:
      - Next is disabled, or
      - no new links found on the new page.
    Writes links to 'all_main_page_links.txt' and returns them.
    """
    all_links = []
    page_num = 1

    while True:
        print(f"\nCollecting main page {page_num} links...")
        lazy_scroll()

        soup = BeautifulSoup(driver.page_source, "html.parser")
        links = soup.select('a.fkTitleLnk.grid_title')
        if not links:
            print("No links found on this page -> likely last main page.")
            break

        old_count = len(all_links)
        for link in links:
            full_url = "https://www.comicspriceguide.com" + link["href"]
            if full_url not in all_links:
                all_links.append(full_url)

        newly_found = len(all_links) - old_count
        print(f"Page {page_num}: newly found {newly_found} links, total = {len(all_links)}.")

        if newly_found == 0:
            print("No new links on this page. Stopping collection.")
            break

        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, 'div.dx-navigate-button.dx-next-button')
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                print("Next button disabled. Last main page.")
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_btn)
            time.sleep(1)
            next_btn.click()
            time.sleep(3)
            page_num += 1
        except Exception as e:
            print(f"No more next or error: {e}")
            break

    with open(ALL_LINKS_FILE, "w", encoding="utf-8") as f:
        for url in all_links:
            f.write(url + "\n")

    print(f"\nDone collecting main-page links. Found {len(all_links)} total.")
    print(f"Wrote them to '{ALL_LINKS_FILE}'.")
    return all_links

def scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label="DefaultTab"):
    page_num = 1
    seen_issue_urls = set()

    while True:
        print(f"      [Tab '{tab_label}'] Scraping page {page_num}...")
        lazy_scroll()

        soup = BeautifulSoup(driver.page_source, "html.parser")
        issue_links = soup.find_all("a", class_="grid_issue")
        print(f"        Found {len(issue_links)} issues on this page.")

        new_issues = 0
        for issue in issue_links:
            issue_url = "https://www.comicspriceguide.com" + issue["href"]
            if issue_url in seen_issue_urls:
                continue
            seen_issue_urls.add(issue_url)
            new_issues += 1

            img_el = issue.find_previous("img", class_="fkiimg")
            issue_image = (
                "https://www.comicspriceguide.com" + img_el["src"]
                if img_el and "src" in img_el.attrs else "N/A"
            )
            issue_number = issue.get_text(strip=True)

            date_el = issue.find_next("span", class_="d-none d-sm-inline")
            issue_date = date_el.text.strip() if date_el else "N/A"

            variant_el = issue.find_next("span", class_="d-none d-sm-inline f-11")
            issue_variant = variant_el.text.strip() if variant_el else "N/A"

            edition_el = issue.find_next("span", class_="d-block mt-1 text-black f-10 fw-bold")
            issue_edition = edition_el.text.strip() if edition_el else "N/A"

            row = {
                "Tab": tab_label,
                "Title": title,
                "Years": years,
                "Volume": volume,
                "Country": country,
                "Issues Note": issues_note,
                "Issue Number": issue_number,
                "Issue URL": issue_url,
                "Image URL": issue_image,
                "Date": issue_date,
                "Variant": issue_variant,
                "Edition": issue_edition,
            }
            all_data.append(row)

        print(f"        Added {new_issues} new issues on this page.")

        df = pd.DataFrame(all_data)
        df.to_excel("comics_details_in_progress.xlsx", index=False)
        print(f"        Partial save -> {len(all_data)} rows in 'comics_details_in_progress.xlsx'.")

        if new_issues == 0:
            print(f"        No new issues. Last page for tab '{tab_label}'.")
            break

        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, 'div.dx-navigate-button.dx-next-button')
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                print(f"        Next disabled. Done with tab '{tab_label}'.")
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_btn)
            time.sleep(1)
            next_btn.click()
            time.sleep(3)
            page_num += 1
        except:
            print(f"        No Next button. Done with tab '{tab_label}'.")
            break

def extract_series_details_with_tabs(series_url, all_data):
    print(f"\nLoading series: {series_url}")
    driver.get(series_url)
    time.sleep(3)

    soup = BeautifulSoup(driver.page_source, "html.parser")
    title_el = soup.select_one("div.h--title.pb-0 > b")
    title = title_el.text.strip() if title_el else "N/A"

    years_el = soup.select_one("#spYears")
    if years_el:
        start_year = years_el.get("data-startyear", "")
        end_year = years_el.get("data-endyear", "")
        years = f"{start_year}-{end_year}"
    else:
        years = "N/A"

    vol_el = soup.select_one("#spVolume")
    volume = vol_el.get("data-volume", "") if vol_el else "N/A"

    country_el = soup.select_one("#spCountry")
    country = country_el.text.strip() if country_el else "N/A"

    issues_note_el = soup.select_one("div.f-12.mt-2 > span")
    issues_note = issues_note_el.text.strip() if issues_note_el else "N/A"

    try:
        WebDriverWait(driver, 10).until(
            EC.presence_of_all_elements_located((By.CSS_SELECTOR, "#dvComicTypes div[role='button']"))
        )
    except:
        print(f"No tabs found for '{title}'. Scraping default page only.")
        scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label="Default(NoTabs)")
        return

    tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
    if not tabs:
        print(f"No tabs found for '{title}'. Using default only.")
        scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label="DefaultOnly")
        return

    print(f"Found {len(tabs)} tabs for series '{title}'.")

    for idx in range(len(tabs)):
        tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        if idx >= len(tabs):
            print("Tab count changed. Stopping.")
            break

        tab_button = tabs[idx]
        tab_label = tab_button.text.strip() or f"Tab#{idx+1}"

        aria_pressed = tab_button.get_attribute("aria-pressed")
        class_attr = tab_button.get_attribute("class")
        is_tab_selected = (aria_pressed == "true") or ("dx-state-selected" in class_attr)

        print(f"\n  ==> Tab {idx+1}/{len(tabs)}: '{tab_label}' (selected={is_tab_selected}) <==")

        if not is_tab_selected:
            clicked_ok = False
            try:
                driver.execute_script("arguments[0].scrollIntoView(true);", tab_button)
                time.sleep(1)
                tab_button.click()
                time.sleep(3)
                clicked_ok = True
            except Exception as e:
                print(f"Normal click failed on '{tab_label}'. Trying JS. Error: {e}")

            if not clicked_ok:
                try:
                    driver.execute_script("arguments[0].click();", tab_button)
                    time.sleep(3)
                    clicked_ok = True
                except Exception as e:
                    print(f"JS click also failed for '{tab_label}'. Skipping. Error: {e}")
                    continue

        scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label=tab_label)

    print(f"Finished all tabs for series '{title}'.")

def second_pass_scrape_subpages(all_links, all_data, done_series):
    global chunk_count, series_count

    for i, link in enumerate(all_links, start=1):
        if link in done_series:
            print(f"Series already done: {link}, skipping.")
            continue

        print(f"\n=== Scraping series {i}/{len(all_links)}: {link} ===")
        extract_series_details_with_tabs(link, all_data)

        done_series.add(link)
        with open(DONE_FILE, "a", encoding="utf-8") as f:
            f.write(link + "\n")

        series_count += 1
        if series_count >= MAX_SERIES_PER_CHUNK or len(all_data) >= MAX_ROWS_PER_CHUNK:
            chunk_filename = f"comics_chunk_{chunk_count}.xlsx"
            df_chunk = pd.DataFrame(all_data)
            save_unique_excel(df_chunk, chunk_filename)
            all_data.clear()
            chunk_count += 1
            series_count = 0

try:
    print("Starting script...")

    # If all_main_page_links.txt exists, skip the main-page collection
    if os.path.exists(ALL_LINKS_FILE):
        print(f"'{ALL_LINKS_FILE}' found. Skipping main-page link collection.")
        # load them from the file
        with open(ALL_LINKS_FILE, "r", encoding="utf-8") as f:
            all_main_links = [line.strip() for line in f if line.strip()]
        print(f"Loaded {len(all_main_links)} series links from '{ALL_LINKS_FILE}'.")
    else:
        # collect them fresh
        all_main_links = collect_all_main_page_links()

    # Setup or load resume data
    done_series = set()
    if os.path.exists(DONE_FILE):
        with open(DONE_FILE, "r", encoding="utf-8") as f:
            for line in f:
                done_series.add(line.strip())
        print(f"Loaded {len(done_series)} series from '{DONE_FILE}'.")

    all_data = []

    # Second pass: Scrape each link
    second_pass_scrape_subpages(all_main_links, all_data, done_series)

    # leftover
    if all_data:
        final_name = f"comics_chunk_{chunk_count}_final.xlsx"
        df_final = pd.DataFrame(all_data)
        save_unique_excel(df_final, final_name)
    else:
        print("No leftover data to save.")

    print("\nAll done with both passes.")

except KeyboardInterrupt:
    print("\nInterrupted by user. Saving partial data.")
    if 'all_data' in globals() and all_data:
        df_int = pd.DataFrame(all_data)
        save_unique_excel(df_int, "comics_details_interrupted.xlsx")
    else:
        print("No data to save.")
finally:
    driver.quit()
    print("Script stopped.")

import os
import time
import pandas as pd
from bs4 import BeautifulSoup

from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

# --------------------------------------------------------------------
# 1) FILES & CONSTANTS
# --------------------------------------------------------------------
DONE_PUBLISHERS_FILE   = "scraped_publishers.txt"   # Mark entire publisher as done
DONE_SERIES_FILE       = "scraped_series.txt"       # Mark series as done
ALL_PUBLISHERS_FILE    = "all_publishers.txt"       # Top-level list of publishers
ALL_SERIES_LINKS_FILE  = "all_main_page_links.txt"  # One file for all series from all publishers
MAX_ROWS_PER_CHUNK     = 100_000
MAX_SERIES_PER_CHUNK   = 1_000

chunk_count  = 1
series_count = 0

# The top-level page listing all publishers
BASE_URL = "https://www.comicspriceguide.com/publishers"

# --------------------------------------------------------------------
# 2) SETUP SELENIUM
# --------------------------------------------------------------------
driver = webdriver.Chrome()
driver.get(BASE_URL)
time.sleep(5)  # optional wait for popups/cookies

# --------------------------------------------------------------------
# 3) SAVE EXCEL WITHOUT OVERWRITING
# --------------------------------------------------------------------
def save_unique_excel(df, base_filename):
    base_name, ext = os.path.splitext(base_filename)
    filename = base_filename
    counter = 1
    while os.path.exists(filename):
        filename = f"{base_name}_{counter}{ext}"
        counter += 1

    df.to_excel(filename, index=False)
    print(f"Saved {len(df)} rows -> '{filename}'")

# --------------------------------------------------------------------
# 4) LAZY-SCROLL
# --------------------------------------------------------------------
def lazy_scroll():
    """Scroll multiple times so the entire page & 'Next' button are visible."""
    while True:
        old_height = driver.execute_script("return document.body.scrollHeight")
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(2)
        new_height = driver.execute_script("return document.body.scrollHeight")
        if new_height == old_height:
            break

# --------------------------------------------------------------------
# 5) PRE-MAIN: COLLECT ALL PUBLISHERS
# --------------------------------------------------------------------
def collect_all_publishers():
    """
    Scrape the top-level publisher listing from /publishers, across multiple pages.
    e.g. <a href="/publishers/marvel" class="grid_publisher_title">Marvel</a>
    Stop if no new links or 'Next' is disabled.
    Writes the results to 'all_publishers.txt'.
    Returns a list of (publisher_url, publisher_display_name) pairs in memory.
    """
    publishers = []
    page_num   = 1

    while True:
        print(f"\nCollecting publisher links on page {page_num} ...")
        lazy_scroll()

        soup = BeautifulSoup(driver.page_source, "html.parser")
        # For example: <a href="/publishers/marvel" class="grid_publisher_title">Marvel</a>
        links = soup.select("a.grid_publisher_title")
        if not links:
            print("No publisher links found -> possibly last page of publishers.")
            break

        newly_found_count = 0
        for link in links:
            pub_url  = "https://www.comicspriceguide.com" + link["href"]
            pub_name = link.text.strip()  # e.g. "Blood Guns" exactly as displayed

            # We'll store (pub_url, pub_name) together
            if not any(pub[0] == pub_url for pub in publishers):
                publishers.append((pub_url, pub_name))
                newly_found_count += 1

        print(f"Page {page_num}: found {newly_found_count} new. total so far: {len(publishers)}.")
        if newly_found_count == 0:
            print("No new publishers on this page -> stopping.")
            break

        # Attempt Next
        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button")
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                print("Publishers Next disabled -> last page.")
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_btn)
            time.sleep(1)
            next_btn.click()
            time.sleep(3)
            page_num += 1
        except Exception as e:
            print(f"No more next or error for publishers: {e}")
            break

    # Write them out to all_publishers.txt
    with open(ALL_PUBLISHERS_FILE, "w", encoding="utf-8") as f:
        for (url, name) in publishers:
            # We'll store them line by line as 'url|name'
            f.write(f"{url}|{name}\n")

    print(f"\nDone collecting publishers. Found {len(publishers)} total.")
    return publishers

# --------------------------------------------------------------------
# 6) PASS A FOR ONE PUBLISHER: COLLECT SERIES IN MEMORY
# --------------------------------------------------------------------
def collect_series_links_for_publisher(publisher_url, publisher_name):
    """
    Gathers all series for this one publisher.
    Returns a list of (series_url, publisher_display_name) pairs.
    Also appends them to 'all_main_page_links.txt' (a single file) for reference.
    """
    all_series = []
    page_num   = 1

    driver.get(publisher_url)
    time.sleep(3)

    while True:
        print(f"[{publisher_name}] Collecting series on page {page_num} ...")
        lazy_scroll()

        soup = BeautifulSoup(driver.page_source, "html.parser")
        # typical: <a href="/titles/..." class="fkTitleLnk grid_title">
        links = soup.select("a.fkTitleLnk.grid_title")
        if not links:
            print(f"[{publisher_name}] No series links on this page -> last page maybe.")
            break

        newly_found_count = 0
        for link in links:
            series_url = "https://www.comicspriceguide.com" + link["href"]
            if not any(s[0] == series_url for s in all_series):
                # store (series_url, publisher_name)
                all_series.append((series_url, publisher_name))
                newly_found_count += 1

        print(f"[{publisher_name}] Page {page_num}: {newly_found_count} new, total so far: {len(all_series)}.")
        if newly_found_count == 0:
            print(f"[{publisher_name}] No new links found -> stopping.")
            break

        # Next
        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, "div.dx-navigate-button.dx-next-button")
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                print(f"[{publisher_name}] Next disabled. Last page of series.")
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_btn)
            time.sleep(1)
            next_btn.click()
            time.sleep(3)
            page_num += 1
        except Exception:
            print(f"[{publisher_name}] No more next or error. Breaking series collection.")
            break

    # We'll append them to a single 'all_main_page_links.txt' for your reference
    # so you have one file containing all series from all publishers.
    with open("all_main_page_links.txt", "a", encoding="utf-8") as f:
        for (s_url, s_pub_name) in all_series:
            # store them line by line as 'series_url|publisher_name'
            f.write(f"{s_url}|{s_pub_name}\n")

    print(f"[{publisher_name}] Done collecting series. Found {len(all_series)} total in memory.")
    return all_series

# --------------------------------------------------------------------
# 7) SCRAPE ONE TAB
# --------------------------------------------------------------------
def scrape_one_tab(all_data, title, years, volume, country, issues_note, publisher_name, tab_label="DefaultTab"):
    page_num = 1
    seen_issue_urls = set()

    while True:
        print(f"      [Tab '{tab_label}' / Publisher '{publisher_name}'] page {page_num} ...")
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

            # Here we add the "Publisher" column
            row = {
                "Publisher": publisher_name,  # <--- PUBLISHER EXACT NAME
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

        # Next in sub
        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, 'div.dx-navigate-button.dx-next-button')
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                print(f"        Next button disabled. Done with tab '{tab_label}'.")
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_btn)
            time.sleep(1)
            next_btn.click()
            time.sleep(3)
            page_num += 1
        except:
            print(f"        No Next button. Done with tab '{tab_label}'.")
            break

# --------------------------------------------------------------------
# 8) SCRAPE A SINGLE SERIES
# --------------------------------------------------------------------
def extract_series_details_with_tabs(series_url, publisher_name, all_data):
    """
    We also pass in 'publisher_name', so we can store
    it in the 'Publisher' column for each row.
    """
    print(f"\nLoading series: {series_url}")
    driver.get(series_url)
    time.sleep(3)

    soup = BeautifulSoup(driver.page_source, "html.parser")
    title_el = soup.select_one("div.h--title.pb-0 > b")
    title = title_el.text.strip() if title_el else "N/A"

    years_el = soup.select_one("#spYears")
    if years_el:
        start_year = years_el.get("data-startyear", "")
        end_year   = years_el.get("data-endyear", "")
        years      = f"{start_year}-{end_year}"
    else:
        years = "N/A"

    vol_el = soup.select_one("#spVolume")
    volume = vol_el.get("data-volume", "") if vol_el else "N/A"

    country_el = soup.select_one("#spCountry")
    country    = country_el.text.strip() if country_el else "N/A"

    issues_note_el = soup.select_one("div.f-12.mt-2 > span")
    issues_note    = issues_note_el.text.strip() if issues_note_el else "N/A"

    # Wait for tabs
    try:
        WebDriverWait(driver, 10).until(
            EC.presence_of_all_elements_located((By.CSS_SELECTOR, "#dvComicTypes div[role='button']"))
        )
    except:
        print(f"No tabs found for '{title}'. Using default page only.")
        scrape_one_tab(all_data, title, years, volume, country, issues_note, publisher_name, tab_label="Default(NoTabs)")
        return

    tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
    if not tabs:
        print(f"No tabs found for '{title}'. Using default-only.")
        scrape_one_tab(all_data, title, years, volume, country, issues_note, publisher_name, tab_label="DefaultOnly")
        return

    print(f"Found {len(tabs)} tabs for series '{title}'.")

    for idx in range(len(tabs)):
        tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        if idx >= len(tabs):
            print("Tab count changed, stopping.")
            break

        tab_button = tabs[idx]
        tab_label  = tab_button.text.strip() or f"Tab#{idx+1}"

        aria_pressed = tab_button.get_attribute("aria-pressed")
        class_attr   = tab_button.get_attribute("class")
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

        # we pass publisher_name so we can store it in each row
        scrape_one_tab(all_data, title, years, volume, country, issues_note, publisher_name, tab_label=tab_label)

    print(f"Finished all tabs for series '{title}'.")

# --------------------------------------------------------------------
# 9) PASS B: SCRAPE SERIES
# --------------------------------------------------------------------
def second_pass_scrape_subpages(all_series, all_data, done_series):
    """
    'all_series' is a list of (series_url, publisher_display_name).
    We skip series in done_series. Then scrape each sub-level with
    the known publisher_name so we can store it in the row.
    """
    global chunk_count, series_count

    for i, (series_url, publisher_name) in enumerate(all_series, start=1):
        if series_url in done_series:
            print(f"Series already done: {series_url}, skipping.")
            continue

        print(f"[{publisher_name}] Scraping series {i}/{len(all_series)}: {series_url}")
        extract_series_details_with_tabs(series_url, publisher_name, all_data)

        # Mark series done
        done_series.add(series_url)
        with open(DONE_SERIES_FILE, "a", encoding="utf-8") as f:
            f.write(series_url + "\n")

        series_count += 1
        # chunk logic
        if series_count >= MAX_SERIES_PER_CHUNK or len(all_data) >= MAX_ROWS_PER_CHUNK:
            chunk_filename = f"comics_chunk_{chunk_count}.xlsx"
            df_chunk = pd.DataFrame(all_data)
            save_unique_excel(df_chunk, chunk_filename)
            all_data.clear()
            chunk_count += 1
            series_count = 0

# --------------------------------------------------------------------
# 10) MAIN
# --------------------------------------------------------------------
try:
    print("Starting script...")

    # 1) If 'all_publishers.txt' is found, skip collecting
    if os.path.exists(ALL_PUBLISHERS_FILE):
        print(f"'{ALL_PUBLISHERS_FILE}' found, skipping publisher collection.")
        all_publisher_entries = []
        with open(ALL_PUBLISHERS_FILE, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                if not line:
                    continue
                # each line is "url|name"
                parts = line.split("|", 1)
                if len(parts) == 2:
                    pub_url, pub_name = parts
                    all_publisher_entries.append((pub_url.strip(), pub_name.strip()))
        print(f"Loaded {len(all_publisher_entries)} publishers from '{ALL_PUBLISHERS_FILE}'.")
    else:
        # gather from top-level
        raw_pubs = collect_all_publishers()
        # 'raw_pubs' is a list of (url, display_name) from code above
        all_publisher_entries = raw_pubs

    # 2) Load 'scraped_publishers.txt' to skip entire publishers
    done_publishers = set()
    if os.path.exists(DONE_PUBLISHERS_FILE):
        with open(DONE_PUBLISHERS_FILE, "r", encoding="utf-8") as f:
            for line in f:
                done_publishers.add(line.strip())
        print(f"Loaded {len(done_publishers)} done publishers from '{DONE_PUBLISHERS_FILE}'.")

    # 3) Load 'scraped_series.txt' to skip series
    done_series = set()
    if os.path.exists(DONE_SERIES_FILE):
        with open(DONE_SERIES_FILE, "r", encoding="utf-8") as f:
            for line in f:
                done_series.add(line.strip())
        print(f"Loaded {len(done_series)} done series from '{DONE_SERIES_FILE}'.")

    all_data = []

    # 4) For each publisher
    for (pub_url, pub_name) in all_publisher_entries:
        if pub_url in done_publishers:
            print(f"Publisher done: {pub_url}, skipping.")
            continue

        print(f"\n=== PUBLISHER: {pub_url} (name='{pub_name}') ===")

        # PASS A: Collect all series in memory
        # e.g. returns a list of (series_url, publisher_display_name)
        all_series_for_this_publisher = collect_series_links_for_publisher(pub_url, pub_name)

        # PASS B: scrape each series
        second_pass_scrape_subpages(all_series_for_this_publisher, all_data, done_series)

        # Mark this publisher as done
        done_publishers.add(pub_url)
        with open(DONE_PUBLISHERS_FILE, "a", encoding="utf-8") as f:
            f.write(pub_url + "\n")

    # leftover final chunk
    if all_data:
        final_name = f"comics_chunk_{chunk_count}_final.xlsx"
        df_final = pd.DataFrame(all_data)
        save_unique_excel(df_final, final_name)
    else:
        print("No leftover data to save for final chunk.")

    print("\nAll done with Pre-Main -> Main -> Sub flow (single 'all_main_page_links.txt').")

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

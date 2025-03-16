import os
import time
import pandas as pd
from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC

# ------------------------------------------------------------------
# SETTINGS
# ------------------------------------------------------------------
DONE_FILE = "scraped_series.txt"
MAX_ROWS_PER_CHUNK = 100_000
MAX_SERIES_PER_CHUNK = 1_000

chunk_count = 1
series_count = 0

# ------------------------------------------------------------------
# 1) SETUP WEBDRIVER
# ------------------------------------------------------------------
driver = webdriver.Chrome()
base_url = "https://www.comicspriceguide.com/publishers/marvel"
driver.get(base_url)
time.sleep(5)  # optional wait for slow load/cookie popups

# ------------------------------------------------------------------
# 2) LAZY-SCROLL
# ------------------------------------------------------------------
def lazy_scroll():
    """Scroll down multiple times to reveal all content (and the Next button)."""
    while True:
        old_height = driver.execute_script("return document.body.scrollHeight")
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(2)
        new_height = driver.execute_script("return document.body.scrollHeight")
        if new_height == old_height:
            break

# ------------------------------------------------------------------
# 3) SCRAPE ONE TAB (MULTI-PAGE)
# ------------------------------------------------------------------
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
            if img_el and "src" in img_el.attrs:
                issue_image = "https://www.comicspriceguide.com" + img_el["src"]
            else:
                issue_image = "N/A"

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

        # Partial save for crash safety
        df = pd.DataFrame(all_data)
        df.to_excel("comics_details_in_progress.xlsx", index=False)
        print(f"        Partial save complete -> {len(all_data)} total rows.")

        if new_issues == 0:
            print(f"        No new issues. Last page for tab '{tab_label}'.")
            break

        # Next on tab
        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, 'div.dx-navigate-button.dx-next-button')
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                print(f"        Next button disabled. Done with tab '{tab_label}'.")
                break
            next_btn.click()
            time.sleep(3)
            page_num += 1
        except:
            print(f"        No more 'Next' button. Done with tab '{tab_label}'.")
            break

# ------------------------------------------------------------------
# 4) SCRAPE TABS FOR ONE SERIES
# ------------------------------------------------------------------
def extract_series_details_with_tabs(series_url, all_data):
    """Load a single series; find & click each tab; scrape all pages of that tab."""
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

    # Wait for tab container if it exists
    try:
        WebDriverWait(driver, 10).until(
            EC.presence_of_all_elements_located((By.CSS_SELECTOR, "#dvComicTypes div[role='button']"))
        )
    except:
        print(f"No visible tabs for '{title}'. Scraping default page only...")
        scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label="Default(NoTabs)")
        return

    # find the tabs
    tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
    if not tabs:
        print(f"No tabs found for '{title}'. Scraping default page only.")
        scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label="DefaultOnly")
        return

    print(f"Found {len(tabs)} tabs for series '{title}'.")

    for idx in range(len(tabs)):
        tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        if idx >= len(tabs):
            print("Tab count changed unexpectedly. Stopping tab iteration.")
            break

        tab_button = tabs[idx]
        tab_label = tab_button.text.strip() or f"Tab#{idx+1}"

        # see if tab is already pressed
        aria_pressed = tab_button.get_attribute("aria-pressed")
        class_attr = tab_button.get_attribute("class")
        is_tab_selected = (aria_pressed == "true") or ("dx-state-selected" in class_attr)

        print(f"\n  ==> Tab {idx+1}/{len(tabs)}: '{tab_label}' (selected={is_tab_selected}) <==")

        if not is_tab_selected:
            clicked_ok = False
            try:
                tab_button.click()
                time.sleep(3)
                clicked_ok = True
            except Exception as e:
                print(f"Normal click failed on tab '{tab_label}'. Trying JS click. Error: {e}")

            if not clicked_ok:
                try:
                    driver.execute_script("arguments[0].click();", tab_button)
                    time.sleep(3)
                    clicked_ok = True
                except Exception as e:
                    print(f"JavaScript click failed for tab '{tab_label}'. Skipping. Error: {e}")
                    continue

        scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label=tab_label)

    print(f"Finished all tabs for series '{title}'.")

# ------------------------------------------------------------------
# 5) MAIN ADVANCED-FLOW: SCRAPE SERIES IMMEDIATELY, THEN NEXT PAGE
# ------------------------------------------------------------------
def scrape_main_pages_and_series(all_data, done_series):
    """
    Instead of collecting all main-page links first,
    we handle each page as we go:
      1. Scroll the main page,
      2. Gather the series links (for this page),
      3. For each link, if not in done_series, scrape sub-pages.
      4. Then click Next (if not disabled) to move to the next main page.
      5. Repeat until no more main pages.
    """

    page_index = 1

    while True:
        # (A) lazy-scroll so we can see all series + Next button
        lazy_scroll()

        # (B) parse the page for series links
        soup = BeautifulSoup(driver.page_source, "html.parser")
        links = soup.select('a.fkTitleLnk.grid_title')
        print(f"\nMain Page {page_index}: Found {len(links)} series links on this page.")

        if not links:
            print("No series links found. Possibly the last page. Stopping main-page loop.")
            break

        # (C) For each link on THIS page, scrape if not done
        for link in links:
            series_url = "https://www.comicspriceguide.com" + link["href"]
            if series_url in done_series:
                print(f"Series already done: {series_url}. Skipping.")
                continue

            print(f"\n--- Scraping new series on page {page_index}: {series_url} ---")
            extract_series_details_with_tabs(series_url, all_data)

            # Mark done
            done_series.add(series_url)
            with open(DONE_FILE, "a", encoding="utf-8") as f:
                f.write(series_url + "\n")

            # chunk logic
            global series_count, chunk_count
            series_count += 1
            if series_count >= MAX_SERIES_PER_CHUNK or len(all_data) >= MAX_ROWS_PER_CHUNK:
                chunk_filename = f"comics_chunk_{chunk_count}.xlsx"
                pd.DataFrame(all_data).to_excel(chunk_filename, index=False)
                print(f"=== CHUNK SAVE === Saved {len(all_data)} rows to '{chunk_filename}'.")
                all_data.clear()
                chunk_count += 1
                series_count = 0

        # (D) Attempt to find & click "Next"
        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, 'div.dx-navigate-button.dx-next-button')
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                print("Main-page Next is disabled. End of main pages.")
                break
            driver.execute_script("arguments[0].scrollIntoView(true);", next_btn)
            time.sleep(1)
            next_btn.click()
            time.sleep(3)  # let next page load
            page_index += 1
        except Exception as e:
            print(f"No more next pages or error: {e}")
            break

    print(f"\nDone scraping all main pages visited. Total pages: {page_index}.")

# ------------------------------------------------------------------
# RUN SCRIPT
# ------------------------------------------------------------------
try:
    print("Starting script...")

    # load done_series for resume
    done_series = set()
    if os.path.exists(DONE_FILE):
        with open(DONE_FILE, "r", encoding="utf-8") as f:
            for line in f:
                done_series.add(line.strip())
        print(f"Loaded {len(done_series)} series from {DONE_FILE}.")

    all_data = []

    # (1) We start on base_url. We'll do the advanced flow
    #     scraping each main page's series, then Next.
    scrape_main_pages_and_series(all_data, done_series)

    # (2) Final leftover chunk
    if all_data:
        final_name = f"comics_chunk_{chunk_count}_final.xlsx"
        pd.DataFrame(all_data).to_excel(final_name, index=False)
        print(f"Final leftover chunk with {len(all_data)} rows -> '{final_name}'.")
    else:
        print("No leftover data to save.")

    print("\nAll done.")

except KeyboardInterrupt:
    print("\nInterrupted by user. Saving partial data...")
    if all_data:
        df_int = pd.DataFrame(all_data)
        df_int.to_excel("comics_details_interrupted.xlsx", index=False)
        print(f"Saved {len(all_data)} rows -> 'comics_details_interrupted.xlsx'.")
    else:
        print("No data to save.")
finally:
    driver.quit()
    print("Script stopped.")

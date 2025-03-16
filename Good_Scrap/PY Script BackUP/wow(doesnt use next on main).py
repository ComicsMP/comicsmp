from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time
import pandas as pd

# --------------------------------------------------------
# 1) LAUNCH WEBDRIVER
# --------------------------------------------------------
driver = webdriver.Chrome()
base_url = "https://www.comicspriceguide.com/publishers/marvel"
driver.get(base_url)
time.sleep(5)  # optional wait for cookies/popups

# --------------------------------------------------------
# 2) LAZY-SCROLL
# --------------------------------------------------------
def lazy_scroll():
    while True:
        old_height = driver.execute_script("return document.body.scrollHeight")
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(2)
        new_height = driver.execute_script("return document.body.scrollHeight")
        if new_height == old_height:
            break

# --------------------------------------------------------
# 3) EXTRACT MAIN PAGE LINKS
# --------------------------------------------------------
def extract_main_page_links():
    series_links = []
    print("Extracting series links from the main Marvel publisher page...")

    while True:
        try:
            WebDriverWait(driver, 10).until(
                EC.presence_of_all_elements_located((By.CSS_SELECTOR, 'a.fkTitleLnk.grid_title'))
            )
        except:
            print("Could not locate series links on main page (timed out).")
            break

        soup = BeautifulSoup(driver.page_source, "html.parser")
        links = soup.select('a.fkTitleLnk.grid_title')
        if not links:
            print("No more links on this main page.")
            break

        for link in links:
            url = "https://www.comicspriceguide.com" + link["href"]
            if url not in series_links:
                series_links.append(url)

        print(f"Collected {len(series_links)} series links so far...")

        # Attempt Next
        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, 'div.dx-navigate-button.dx-next-button')
            if "dx-state-disabled" in next_btn.get_attribute("class"):
                print("Main-page Next button is disabled. Done collecting links.")
                break
            next_btn.click()
            time.sleep(2)
        except:
            print("No more next pages on the main publisher page.")
            break

    print(f"Total series links extracted: {len(series_links)}")
    return series_links

# --------------------------------------------------------
# 4) SCRAPE ONE TAB (multi-page)
# --------------------------------------------------------
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
                continue  # skip duplicates
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

        # Save partial data
        df = pd.DataFrame(all_data)
        df.to_excel("comics_details_in_progress.xlsx", index=False)
        print(f"        Partial save complete -> {len(all_data)} total rows.")

        if new_issues == 0:
            print(f"        No new issues. Last page for tab '{tab_label}'.")
            break

        # Click "Next"
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

# --------------------------------------------------------
# 5) EXTRACT SERIES + TABS
# --------------------------------------------------------
def extract_series_details_with_tabs(series_url, all_data):
    """
    1) Load the series page
    2) Extract title, year, etc.
    3) Find tab buttons (#dvComicTypes div[role='button'])
    4) For each tab: if not selected, click it (with fallback JS).
       If selected, just scrape. Then run multi-page logic.
    """
    print(f"\nLoading series: {series_url}")
    driver.get(series_url)
    time.sleep(3)  # let page load

    # --- Series metadata ---
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

    # --- Wait for the tab container (#dvComicTypes) if it exists
    try:
        WebDriverWait(driver, 10).until(
            EC.presence_of_all_elements_located((By.CSS_SELECTOR, "#dvComicTypes div[role='button']"))
        )
    except:
        print(f"No visible tabs found for '{title}'. Scraping default content only...")
        scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label="Default(NoTabs)")
        return

    # Identify the tabs
    tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
    if not tabs:
        print(f"No tabs found after wait for '{title}'. Scraping default only.")
        scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label="DefaultOnly")
        return

    print(f"Found {len(tabs)} tabs for series '{title}'.")

    # For each tab
    for idx in range(len(tabs)):
        # Re-locate them each iteration
        tabs = driver.find_elements(By.CSS_SELECTOR, "#dvComicTypes div[role='button']")
        if idx >= len(tabs):
            print("Tab count changed unexpectedly. Stopping tab iteration.")
            break

        tab_button = tabs[idx]
        tab_label = tab_button.text.strip() or f"Tab#{idx+1}"

        # Check if this tab is already pressed (selected)
        # If so, we do NOT click, we just scrape. 
        aria_pressed = tab_button.get_attribute("aria-pressed")
        class_attr = tab_button.get_attribute("class")
        is_tab_selected = (aria_pressed == "true") or ("dx-state-selected" in class_attr)

        print(f"\n  ==> Tab {idx+1}/{len(tabs)}: '{tab_label}' (selected={is_tab_selected}) <==")

        if not is_tab_selected:
            # Attempt normal click
            clicked_ok = False
            try:
                tab_button.click()
                time.sleep(3)  # wait for tab content to load
                clicked_ok = True
            except Exception as e:
                print(f"Normal click failed on tab '{tab_label}'. Trying JS click. Error: {e}")

            if not clicked_ok:
                # Fallback: JS click
                try:
                    driver.execute_script("arguments[0].click();", tab_button)
                    time.sleep(3)
                    clicked_ok = True
                except Exception as e:
                    print(f"JavaScript click also failed for tab '{tab_label}'. Skipping. Error: {e}")
                    continue

        # Now do multi-page scrape for this tab
        scrape_one_tab(all_data, title, years, volume, country, issues_note, tab_label=tab_label)

    print(f"Finished all tabs for series '{title}'.")

# --------------------------------------------------------
# 6) MAIN SCRIPT
# --------------------------------------------------------
try:
    print("Starting script...")
    # A) Get all series links
    all_series_links = extract_main_page_links()
    print(f"Found {len(all_series_links)} series links total.")

    # B) For each series, do multi-tab scraping
    all_data = []
    for i, link in enumerate(all_series_links, start=1):
        print(f"\n=== Scraping series {i}/{len(all_series_links)}: {link} ===")
        extract_series_details_with_tabs(link, all_data)

    # C) Final save
    df_final = pd.DataFrame(all_data)
    df_final.to_excel("comics_details_final.xlsx", index=False)
    print(f"\nAll done. Final data with {len(all_data)} rows saved.")

except KeyboardInterrupt:
    print("\nInterrupted by user (Ctrl+C). Saving partial data...")
    if all_data:
        df_int = pd.DataFrame(all_data)
        df_int.to_excel("comics_details_interrupted.xlsx", index=False)
        print(f"Saved {len(all_data)} rows in 'comics_details_interrupted.xlsx'.")
    else:
        print("No data to save.")
finally:
    driver.quit()
    print("Script stopped.")

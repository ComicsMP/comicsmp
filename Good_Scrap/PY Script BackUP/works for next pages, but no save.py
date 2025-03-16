from bs4 import BeautifulSoup
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
import time
import pandas as pd

# ------------------------------------------------------------
# 1) SETUP WEBDRIVER
# ------------------------------------------------------------
driver = webdriver.Chrome()
base_url = "https://www.comicspriceguide.com/publishers/marvel"
driver.get(base_url)

# Optional: time to see if a cookie popup or something appears
time.sleep(5)

def lazy_scroll():
    """
    Scroll down the page multiple times to force any lazy-loaded
    content (images, new issues, etc.) to appear.
    """
    while True:
        old_height = driver.execute_script("return document.body.scrollHeight")
        driver.execute_script("window.scrollTo(0, document.body.scrollHeight);")
        time.sleep(2)
        new_height = driver.execute_script("return document.body.scrollHeight")
        if new_height == old_height:
            break

# ------------------------------------------------------------
# 2) EXTRACT ALL SERIES LINKS FROM PUBLISHER MAIN PAGE
# ------------------------------------------------------------
def extract_main_page_links():
    series_links = []
    print("Extracting series links from the main Marvel publisher page...")

    while True:
        try:
            WebDriverWait(driver, 15).until(
                EC.presence_of_all_elements_located((By.CSS_SELECTOR, 'a.fkTitleLnk.grid_title'))
            )
        except Exception as e:
            print(f"Error waiting for main-page links: {e}")
            break

        soup = BeautifulSoup(driver.page_source, "html.parser")
        links = soup.select('a.fkTitleLnk.grid_title')
        if not links:
            print("No series links found on this page. Breaking.")
            break

        for link in links:
            full_url = "https://www.comicspriceguide.com" + link["href"]
            if full_url not in series_links:
                series_links.append(full_url)

        print(f"Collected so far: {len(series_links)} series links.")

        # Attempt to click the main-page "Next"
        try:
            next_btn = driver.find_element(By.CSS_SELECTOR, 'div.dx-navigate-button.dx-next-button')
            WebDriverWait(driver, 10).until(
                EC.element_to_be_clickable((By.CSS_SELECTOR, 'div.dx-navigate-button.dx-next-button'))
            )
            next_btn.click()
            time.sleep(2)
        except:
            print("No more next pages on the main publisher page.")
            break

    print(f"Total series links extracted: {len(series_links)}")
    return series_links

# ------------------------------------------------------------
# 3) EXTRACT ISSUES (MULTIPLE PAGES) FOR A SINGLE SERIES
# ------------------------------------------------------------
def extract_series_details(series_url, all_data):
    """Scrape multiple pages of issues for one series, appending to all_data.
       Export partial data after each page, so we don't lose progress.
    """
    print(f"\nLoading series: {series_url}")
    driver.get(series_url)
    time.sleep(3)  # let the first page load

    # --- Parse series metadata ---
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

    page_count = 1

    while True:
        print(f"Scraping page {page_count} of series '{title}'...")
        # (A) Scroll to load all issues
        lazy_scroll()

        # (B) Parse the (fully scrolled) HTML
        soup = BeautifulSoup(driver.page_source, "html.parser")
        issue_links = soup.find_all("a", class_="grid_issue")
        print(f"Found {len(issue_links)} issues on page {page_count}.")

        old_data_count = len(all_data)

        # (C) Extract each issue on the page
        for issue in issue_links:
            img_el = issue.find_previous("img", class_="fkiimg")
            if img_el and "src" in img_el.attrs:
                issue_image = "https://www.comicspriceguide.com" + img_el["src"]
            else:
                issue_image = "N/A"

            issue_number = issue.get_text(strip=True)
            issue_url = "https://www.comicspriceguide.com" + issue["href"]

            date_el = issue.find_next("span", class_="d-none d-sm-inline")
            issue_date = date_el.text.strip() if date_el else "N/A"

            variant_el = issue.find_next("span", class_="d-none d-sm-inline f-11")
            issue_variant = variant_el.text.strip() if variant_el else "N/A"

            edition_el = issue.find_next("span", class_="d-block mt-1 text-black f-10 fw-bold")
            issue_edition = edition_el.text.strip() if edition_el else "N/A"

            row = {
                "Image URL": issue_image,
                "Title": title,
                "Years": years,
                "Volume": volume,
                "Country": country,
                "Issues Note": issues_note,
                "Issue Number": issue_number,
                "Issue URL": issue_url,
                "Date": issue_date,
                "Variant": issue_variant,
                "Edition": issue_edition,
            }
            all_data.append(row)

        new_count = len(all_data) - old_data_count
        print(f"Added {new_count} new issues from page {page_count} of '{title}'.")

        # (D) Save partial data RIGHT AFTER finishing this page
        df = pd.DataFrame(all_data)
        df.to_excel("comics_details_in_progress.xlsx", index=False)
        print(f"Partial save complete. {len(all_data)} rows in total so far.")

        # (E) Attempt to find & click the "Next" button in the series page
        try:
            next_btn = WebDriverWait(driver, 5).until(
                EC.element_to_be_clickable((By.CSS_SELECTOR, 'div.dx-navigate-button.dx-next-button'))
            )
            next_btn.click()
            time.sleep(3)  # let page 2 (or next) load
        except:
            print(f"No more next pages for '{title}'. Done scraping this series.")
            break

        # (F) After clicking Next, do a quick check:
        # If we come right back to the same set of issues (no new issues found),
        # or if the site is not actually loading new content, then we break.
        # We'll see on the next loop iteration if "issue_links" changes. 
        # But if it's always the same, the new_count next time will be 0, 
        # so we break automatically on the next iteration.
        page_count += 1

    print(f"Finished scraping all pages for series '{title}'.\n")

# ------------------------------------------------------------
# 4) MAIN EXECUTION
# ------------------------------------------------------------
try:
    print("Starting script...")
    # A) Collect all series links from the main page
    all_series_links = extract_main_page_links()
    print(f"Found {len(all_series_links)} series links total.")

    # B) For each series link, scrape sub-pages
    all_data = []
    for idx, series_link in enumerate(all_series_links, start=1):
        print(f"==== Scraping series {idx}/{len(all_series_links)} ====")
        extract_series_details(series_link, all_data)

    # C) Final save once done
    df_final = pd.DataFrame(all_data)
    df_final.to_excel("comics_details_final.xlsx", index=False)
    print(f"\nAll series completed. Final data saved with {len(all_data)} total rows.")

except KeyboardInterrupt:
    print("\nScript interrupted by user. Saving partial data...")
    if all_data:
        df_interrupt = pd.DataFrame(all_data)
        df_interrupt.to_excel("comics_details_interrupted.xlsx", index=False)
        print(f"Saved {len(all_data)} rows to 'comics_details_interrupted.xlsx'.")
    else:
        print("No data to save.")
finally:
    driver.quit()
    print("Script stopped.")

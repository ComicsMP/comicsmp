import requests
import re
import pymysql
from bs4 import BeautifulSoup
from urllib.parse import quote_plus

# Database configuration
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'comics_db'
}

def get_missing_upc_entries(limit=10):
    connection = pymysql.connect(**DB_CONFIG)
    cursor = connection.cursor()
    query = "SELECT Comic_Title, Issue_Number, Years, Date FROM comics WHERE UPC IS NULL LIMIT %s"
    cursor.execute(query, (limit,))
    entries = cursor.fetchall()
    cursor.close()
    connection.close()
    return entries

def fetch_upc_from_web(title, issue, year):
    search_term = f"{title} {year} #{issue}"
    search_url = f"https://www.comics.org/searchNew/?q={quote_plus(search_term)}&search_object=issue"
    response = requests.get(search_url, timeout=10)
    soup = BeautifulSoup(response.text, 'html.parser')
    # Look for UPC using regex on the text of the page
    text_content = soup.get_text()
    match = re.search(r'(UPC|Barcode)(?:/EAN)?:?\s*(\d{12,14})', text_content, re.IGNORECASE)
    if match:
        return match.group(2)
    return None

def update_upc_in_db(title, issue, upc):
    connection = pymysql.connect(**DB_CONFIG)
    cursor = connection.cursor()
    query = "UPDATE comics SET UPC = %s WHERE Comic_Title = %s AND Issue_Number = %s"
    cursor.execute(query, (upc, title, issue))
    connection.commit()
    cursor.close()
    connection.close()

def main():
    entries = get_missing_upc_entries()
    for title, issue, years, _ in entries:
        # Use only first year in range
        year = years.split('-')[0] if years and '-' in years else years
        print(f"Processing {title} #{issue} ({year})")
        upc = fetch_upc_from_web(title, issue, year)
        if upc:
            print(f"Found UPC: {upc}")
            update_upc_in_db(title, issue, upc)
        else:
            print("UPC not found.")
    
    print("Done updating UPCs.")

if __name__ == "__main__":
    main()

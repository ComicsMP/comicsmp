import os
import time
import pandas as pd
import pymysql
from datetime import datetime
import re

def unify(value):
    """ Ensure NaN is treated as None and strings are stripped of whitespace. """
    if pd.isna(value) or value is None:
        return None
    value = str(value).strip()
    if value == "" or value.upper() == "N/A":
        return None
    return value

def normalize_volume(volume_value):
    """ Ensure volume is stored as a clean number (e.g., '1', '2'). """
    if isinstance(volume_value, str):
        volume_value = volume_value.lower().replace("vol ", "").replace("v", "").strip()
        if re.match(r'^\d+(\.\d+)?$', volume_value):  # Matches '1', '1.0', '2'
            return str(int(float(volume_value)))  # Converts '1.0' → '1'
    return volume_value

def normalize_issue_number(issue_number):
    """ Ensure issue number has a '#' prefix. """
    if isinstance(issue_number, str):
        issue_number = issue_number.strip()
        if not issue_number.startswith("#"):
            return f"#{issue_number}"
    return issue_number

def bulk_insert_dedup(folder_path, db_config):
    print("Starting bulk upsert operation with Issue_URL and Unique_ID check...")

    # 1. Connect to the database
    try:
        conn = pymysql.connect(
            host=db_config['host'],
            user=db_config['user'],
            password=db_config['password'],
            database=db_config['database'],
            charset='utf8mb4',
            cursorclass=pymysql.cursors.DictCursor
        )
        print("✅ Database connected successfully.")
    except pymysql.MySQLError as e:
        print(f"❌ Database connection failed: {e}")
        return

    cursor = conn.cursor()

    # 2. Fetch existing data (Issue_URL, Unique_ID) from the comics table
    existing_data = {}
    try:
        cursor.execute("SELECT TRIM(LOWER(Issue_URL)) AS Issue_URL, Unique_ID FROM comics")
        for row in cursor.fetchall():
            existing_data[row["Issue_URL"].strip().lower()] = row["Unique_ID"]
    except Exception as e:
        print(f"⚠️ Could not fetch existing data: {e}")

    print(f"📌 Found {len(existing_data)} existing records in the DB.")

    # 3. Make the "processed" folder if it doesn’t exist
    processed_folder = os.path.join(folder_path, "processed")
    os.makedirs(processed_folder, exist_ok=True)

    # 4. Prepare the upsert and suggestion SQL statements
    upsert_sql = """
    INSERT INTO comics (
        Tab, Comic_Title, Years, Volume, Country, Issues_Note, Issue_Number, 
        Issue_URL, Image_URL, Date, Variant, Edition, Publisher_Name, Unique_ID, 
        Image_Path, Timestamp
    )
    VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    ON DUPLICATE KEY UPDATE
    Tab = VALUES(Tab), Comic_Title = VALUES(Comic_Title), Years = VALUES(Years), 
    Volume = VALUES(Volume), Country = VALUES(Country), Issues_Note = VALUES(Issues_Note), 
    Issue_Number = VALUES(Issue_Number), Image_URL = VALUES(Image_URL), Date = VALUES(Date), 
    Variant = VALUES(Variant), Edition = VALUES(Edition), Publisher_Name = VALUES(Publisher_Name), 
    Image_Path = VALUES(Image_Path), Timestamp = VALUES(Timestamp),
    UPC = UPC, last_checked = last_checked
    """

    insert_suggestions_sql = "INSERT IGNORE INTO comic_title_suggestions (Comic_Title) VALUES (%s)"

    # 5. Gather all files in the folder and filter for Excel files
    all_files = os.listdir(folder_path)
    print(f"📂 All files in folder: {all_files}")

    excel_files = [f for f in all_files if f.lower().endswith('.xlsx')]
    print(f"📂 Detected Excel files: {excel_files}")

    total_files = len(excel_files)
    total_rows_processed = 0
    total_inserted = 0
    total_updated = 0
    total_skipped = 0
    total_suggestions = 0

    DEFAULT_IMG_FILENAME = "default.jpg"

    # 6. Iterate through each Excel file
    for file_name in excel_files:
        file_path = os.path.join(folder_path, file_name)
        try:
            # Read the Excel file
            data = pd.read_excel(file_path)
            data.columns = data.columns.str.strip()

            # Rename columns to match our DB fields
            rename_dict = {
                'Comic Title': 'Comic_Title',
                'Issues Note': 'Issues_Note',
                'Issue Number': 'Issue_Number',
                'Issue URL': 'Issue_URL',
                'Image URL': 'Image_URL',
                'Image Path': 'Image_Path',
                'Publisher Name': 'Publisher_Name',
                'Publisher': 'Publisher_Name',
                'Image Hash': 'Unique_ID'
            }
            data.rename(columns=rename_dict, inplace=True)

            if 'Image_URL' not in data.columns:
                print(f"⚠️ Error: 'Image_URL' column missing in {file_name}. Available columns: {data.columns.tolist()}")
                continue

            # Count total rows in the current file
            num_rows = len(data)
            print(f"\n📊 Processing file: {file_name}, total rows: {num_rows}")
            data = data.where(pd.notnull(data), None)

            # Normalize Volume and Issue_Number
            if 'Volume' in data.columns:
                data['Volume'] = data['Volume'].apply(normalize_volume)
            if 'Issue_Number' in data.columns:
                data['Issue_Number'] = data['Issue_Number'].apply(normalize_issue_number)

            bulk_data = []
            file_inserted = 0
            file_updated = 0
            file_skipped = 0
            file_suggestions = set()

            for _, row in data.iterrows():
                issue_url = unify(row.get('Issue_URL'))
                if issue_url:
                    issue_url = issue_url.lower()
                unique_id = unify(row.get('Unique_ID'))
                image_url = unify(row.get('Image_URL'))

                uses_default_image = image_url and image_url.lower().endswith(DEFAULT_IMG_FILENAME.lower())

                # If this Issue_URL already exists in DB
                if issue_url in existing_data:
                    # Skip if the existing record matches or if it's using default.jpg in a certain way
                    if (not uses_default_image and existing_data[issue_url] == unique_id) or \
                       (uses_default_image and existing_data[issue_url] is None):
                        file_skipped += 1
                        continue

                # Build the data tuple
                new_row = (
                    unify(row.get('Tab')),
                    unify(row.get('Comic_Title')),
                    unify(row.get('Years')),
                    unify(row.get('Volume')),
                    unify(row.get('Country')),
                    unify(row.get('Issues_Note')),
                    unify(row.get('Issue_Number')),
                    issue_url,
                    image_url,
                    unify(row.get('Date')),
                    unify(row.get('Variant')),
                    unify(row.get('Edition')),
                    unify(row.get('Publisher_Name')),
                    unique_id,
                    unify(row.get('Image_Path')),
                    unify(row.get('Timestamp'))
                )
                bulk_data.append(new_row)

                if issue_url in existing_data:
                    file_updated += 1
                else:
                    file_inserted += 1

                # Track comic title suggestions
                if row.get('Comic_Title'):
                    file_suggestions.add(row.get('Comic_Title'))

            # Update counters
            total_rows_processed += num_rows
            total_inserted += file_inserted
            total_updated += file_updated
            total_skipped += file_skipped
            total_suggestions += len(file_suggestions)

            # 7. Perform the bulk upsert
            if bulk_data:
                cursor.executemany(upsert_sql, bulk_data)
                conn.commit()
                print(f"✅ {file_name}: Processed {num_rows} rows -> {len(bulk_data)} rows affected (Inserted: {file_inserted}, Updated: {file_updated}, Skipped: {file_skipped}).")

            # 8. Insert comic title suggestions
            if file_suggestions:
                cursor.executemany(insert_suggestions_sql, [(title,) for title in file_suggestions])
                conn.commit()
                print(f"✅ {file_name}: Added {len(file_suggestions)} new comic title suggestions.")

            # 9. Move the processed file to the "processed" folder
            processed_file_path = os.path.join(processed_folder, file_name)
            if os.path.exists(processed_file_path):
                base, ext = os.path.splitext(file_name)
                processed_file_path = os.path.join(processed_folder, f"{base}_{int(time.time())}{ext}")

            os.rename(file_path, processed_file_path)
            print(f"📁 Moved {file_name} to processed folder.")

        except Exception as e:
            print(f"❌ Error processing {file_name}: {e}")

    # 10. Clean up
    cursor.close()
    conn.close()

    print("\n✅ Bulk upsert completed.")
    print("===========================================")
    print(f"Total files processed: {total_files}")
    print(f"Total rows processed: {total_rows_processed}")
    print(f"Total rows affected (Inserted): {total_inserted}")
    print(f"Total rows affected (Updated): {total_updated}")
    print(f"Total rows skipped: {total_skipped}")
    print(f"Total new comic title suggestions added: {total_suggestions}")
    print("===========================================")

if __name__ == "__main__":
    # Adjust this as needed for your actual MySQL credentials and database name
    db_config = {
        'host': 'localhost',
        'user': 'root',
        'password': '',
        'database': 'comics_db'
    }

    folder_path = r"C:\xampp6\htdocs\comicsmp\Good_Scrap\Step_3"
    bulk_insert_dedup(folder_path, db_config)

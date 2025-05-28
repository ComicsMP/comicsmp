import os
import time
import math
import pandas as pd
import pymysql
import re
import traceback
from datetime import datetime

def unify(value):
    """Ensure NaN is treated as None and strings are stripped of whitespace."""
    if pd.isna(value) or value is None:
        return None
    value = str(value).strip()
    return None if value == "" or value.upper() == "N/A" else value

def normalize_volume(volume_value):
    """Ensure volume is stored as a clean number (e.g., '1', '2')."""
    if isinstance(volume_value, str):
        volume_value = volume_value.lower().replace("vol ", "").replace("v", "").strip()
        if re.match(r'^\d+(\.\d+)?$', volume_value):
            return str(int(float(volume_value)))
    return volume_value

def normalize_issue_number(issue_number):
    """Ensure issue number has a '#' prefix."""
    if isinstance(issue_number, str):
        issue_number = issue_number.strip()
        if not issue_number.startswith("#"):
            return f"#{issue_number}"
    return issue_number

def bulk_insert_dedup(folder_path, db_config):
    print("Starting bulk upsert operation with Issue_URL and Unique_ID check...")

    # Totals for SearchIndex and Suggestions
    total_searchindex_added = 0
    total_searchindex_skipped = 0
    total_suggestions_added = 0
    total_suggestions_skipped = 0

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

    # 2. Fetch existing data (Issue_URL, Unique_ID) from comics
    existing_data = {}
    try:
        cursor.execute("SELECT TRIM(LOWER(Issue_URL)) AS Issue_URL, Unique_ID FROM comics")
        for row in cursor.fetchall():
            existing_data[row['Issue_URL']] = row['Unique_ID']
    except Exception as e:
        print(f"⚠️ Could not fetch existing data: {e}")

    print(f"📌 Found {len(existing_data)} existing records in the DB.")

    # 3. Make the 'processed' folder
    processed_folder = os.path.join(folder_path, "processed")
    os.makedirs(processed_folder, exist_ok=True)

    # 4. Prepare SQL statements
    upsert_sql = """
    INSERT INTO comics (
        Tab, Comic_Title, Years, Volume, Country, Issues_Note, Issue_Number,
        Issue_URL, Image_URL, Date, Variant, Edition, Publisher_Name, Unique_ID,
        Image_Path, Timestamp
    ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    ON DUPLICATE KEY UPDATE
      Tab=VALUES(Tab), Comic_Title=VALUES(Comic_Title), Years=VALUES(Years),
      Volume=VALUES(Volume), Country=VALUES(Country), Issues_Note=VALUES(Issues_Note),
      Issue_Number=VALUES(Issue_Number), Image_URL=VALUES(Image_URL), Date=VALUES(Date),
      Variant=VALUES(Variant), Edition=VALUES(Edition), Publisher_Name=VALUES(Publisher_Name),
      Image_Path=VALUES(Image_Path), Timestamp=VALUES(Timestamp),
      UPC=UPC, last_checked=last_checked
    """
    insert_suggestions_sql = "INSERT IGNORE INTO comic_title_suggestions (Comic_Title) VALUES (%s)"
    insert_searchindex_sql = "INSERT IGNORE INTO Comic_SearchIndex (original_title, normalized_title) VALUES (%s, %s)"

    # 5. Gather Excel files (skip Excel lock files that start with "~$")
    excel_files = [
        f for f in os.listdir(folder_path)
        if f.lower().endswith('.xlsx') and not f.startswith('~$')
    ]
    print(f"📂 Detected Excel files: {excel_files}")

    # Totals for comics upsert
    total_files = len(excel_files)
    total_rows_processed = 0
    total_inserted = 0
    total_updated = 0
    total_skipped = 0

    DEFAULT_IMG_FILENAME = "default.jpg"

    # 6. Iterate through each Excel file
    for file_name in excel_files:
        file_path = os.path.join(folder_path, file_name)
        file_searchindex_added = file_searchindex_skipped = 0
        file_suggestions_added = file_suggestions_skipped = 0
        file_inserted = file_updated = file_skipped = 0
        file_suggestions_set = set()

        try:
            data = pd.read_excel(file_path)
            data.columns = data.columns.str.strip()

            # Rename columns
            rename_dict = {
                'Comic Title':'Comic_Title','Issues Note':'Issues_Note',
                'Issue Number':'Issue_Number','Issue URL':'Issue_URL',
                'Image URL':'Image_URL','Image Path':'Image_Path',
                'Publisher Name':'Publisher_Name','Publisher':'Publisher_Name',
                'Image Hash':'Unique_ID'
            }
            data.rename(columns=rename_dict, inplace=True)
            if 'Image_URL' not in data.columns:
                print(f"⚠️ {file_name}: missing 'Image_URL' column")
                continue

            num_rows = len(data)
            print(f"\n📊 Processing file: {file_name}, rows: {num_rows}")
            data = data.where(pd.notnull(data), None)

            # Normalize Volume and Issue_Number
            if 'Volume' in data.columns:
                data['Volume'] = data['Volume'].apply(normalize_volume)
            if 'Issue_Number' in data.columns:
                data['Issue_Number'] = data['Issue_Number'].apply(normalize_issue_number)

            bulk_rows = []

            for idx, row in data.iterrows():
                issue_url = unify(row.get('Issue_URL'))
                if issue_url:
                    issue_url = issue_url.lower()
                unique_id = unify(row.get('Unique_ID'))
                image_url = unify(row.get('Image_URL'))
                uses_default = image_url and image_url.lower().endswith(DEFAULT_IMG_FILENAME.lower())

                # --- SKIP LOGIC to avoid duplicate-update collision ----------
                if issue_url in existing_data:
                    # already in DB with *different* Unique_ID → skip to avoid secondary duplicate
                    if existing_data[issue_url] != unique_id:
                        file_skipped += 1
                        continue
                    # same Unique_ID and not default image → treat as unchanged
                    if not uses_default and existing_data[issue_url] == unique_id:
                        file_skipped += 1
                        continue

                # Prepare upsert tuple
                new_row = (
                    unify(row.get('Tab')), unify(row.get('Comic_Title')),
                    unify(row.get('Years')), unify(row.get('Volume')),
                    unify(row.get('Country')), unify(row.get('Issues_Note')),
                    unify(row.get('Issue_Number')), issue_url, image_url,
                    unify(row.get('Date')), unify(row.get('Variant')),
                    unify(row.get('Edition')), unify(row.get('Publisher_Name')),
                    unique_id, unify(row.get('Image_Path')), unify(row.get('Timestamp'))
                )
                new_row = tuple(None if isinstance(v, float) and math.isnan(v) else v for v in new_row)
                bulk_rows.append(new_row)

                # Update SearchIndex
                comic_title = unify(row.get('Comic_Title'))
                if comic_title:
                    normalized = re.sub(r'[\s\-:()]+',' ', comic_title).lower().strip()
                    cursor.execute(insert_searchindex_sql, (comic_title, normalized))
                    if cursor.rowcount == 1:
                        file_searchindex_added += 1
                        total_searchindex_added += 1
                    else:
                        file_searchindex_skipped += 1
                        total_searchindex_skipped += 1
                    file_suggestions_set.add(comic_title)

                # Track insert/update counts
                if issue_url in existing_data:
                    file_updated += 1
                else:
                    file_inserted += 1

            # Counters for file
            total_rows_processed += num_rows
            total_inserted += file_inserted
            total_updated += file_updated
            total_skipped += file_skipped

            # Batch upsert (unchanged)
            batch_size = 50
            for i in range(0, len(bulk_rows), batch_size):
                batch = bulk_rows[i:i+batch_size]
                try:
                    cursor.executemany(upsert_sql, batch)
                    conn.commit()
                except Exception as e:
                    print(f"⚠️ Error in batch {i}-{i+len(batch)}: {e}")
                    try:
                        conn.rollback()
                    except:
                        pass

            # Insert suggestions
            if file_suggestions_set:
                sugg_list = [(t.strip().upper(),) for t in file_suggestions_set]
                cursor.executemany(insert_suggestions_sql, sugg_list)
                conn.commit()
                added = cursor.rowcount
                skipped = len(sugg_list) - added
                total_suggestions_added += added
                total_suggestions_skipped += skipped
                print(f"💡 comic_title_suggestions {file_name}: Added {added}, Skipped {skipped}")

            # Move processed file
            dest = os.path.join(processed_folder, file_name)
            if os.path.exists(dest):
                base, ext = os.path.splitext(file_name)
                dest = os.path.join(processed_folder, f"{base}_{int(time.time())}{ext}")
            os.rename(file_path, dest)
            print(f"📁 Moved {file_name} to processed folder.")

            # File-level summaries
            print(f"🔍 Comic_SearchIndex {file_name}: Added {file_searchindex_added}, Skipped {file_searchindex_skipped}")

        except Exception as e:
            print(f"❌ Error processing {file_name}: {e}")
            traceback.print_exc()

    # 7. Clean up
    cursor.close()
    conn.close()

    # Final summary
    print("\n✅ Bulk upsert completed.")
    print("===========================================")
    print(f"Files processed: {total_files}, Rows: {total_rows_processed}")
    print(f"Comics inserted: {total_inserted}, updated: {total_updated}, skipped: {total_skipped}")
    print(f"SearchIndex added: {total_searchindex_added}, skipped: {total_searchindex_skipped}")
    print(f"Suggestions added: {total_suggestions_added}, skipped: {total_suggestions_skipped}")
    print("===========================================")

if __name__ == "__main__":
    db_config = {
        'host': 'localhost',
        'user': 'root',
        'password': '',
        'database': 'comics_db'
    }
    folder_path = r"C:\xampp6\htdocs\comicsmp\Good_Scrap\Step_3"
    bulk_insert_dedup(folder_path, db_config)

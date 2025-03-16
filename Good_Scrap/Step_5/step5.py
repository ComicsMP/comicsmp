import pymysql

# ✅ Database Configuration
db_config = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'comics_db'
}

# ✅ Connect to the database
try:
    conn = pymysql.connect(**db_config)
    cursor = conn.cursor()
    print("✅ Connected to the database.")

    # ✅ Find all volumes with decimal values ending in .0 using Issue_URL as identifier
    select_query = """
        SELECT Issue_URL, Volume 
        FROM comics 
        WHERE Volume REGEXP '^[0-9]+\\.0$'
    """
    cursor.execute(select_query)
    rows = cursor.fetchall()

    # ✅ SQL command to verify how many rows have been found
    cursor.execute("SELECT COUNT(*) FROM comics WHERE Volume REGEXP '^[0-9]+\\.0$'")
    total_rows = cursor.fetchone()[0]
    print(f"\n📌 Found {total_rows} rows with volumes ending in .0.")

    if not rows:
        print("✅ No volumes need updating.")
    else:
        print(f"\n📌 The following {len(rows)} rows will be updated:")
        print("-" * 50)
        for issue_url, volume in rows:
            corrected_volume = int(float(volume))
            print(f"🔗 Issue_URL: {issue_url} | 📖 Volume: {volume} ➝ {corrected_volume}")
        print("-" * 50)

        # ✅ Automatically update volumes to remove .0 using Issue_URL as reference
        for issue_url, volume in rows:
            solid_number = str(int(float(volume)))  # Convert e.g., 1.0 → 1
            update_query = """
                UPDATE comics 
                SET Volume = %s 
                WHERE Issue_URL = %s
            """
            cursor.execute(update_query, (solid_number, issue_url))
        
        conn.commit()
        print(f"\n✅ Successfully updated {len(rows)} volumes.")

        # ✅ Verify that no rows remain with a volume ending in .0
        cursor.execute("SELECT COUNT(*) FROM comics WHERE Volume REGEXP '^[0-9]+\\.0$'")
        remaining = cursor.fetchone()[0]
        if remaining == 0:
            print("✅ All matching rows have been updated successfully.")
        else:
            print(f"⚠️ There are still {remaining} rows with volumes ending in .0.")

except pymysql.MySQLError as e:
    print(f"\n❌ Database error: {e}")

finally:
    if conn:
        conn.close()
        print("\n✅ Database connection closed.")

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
    cursor.execute("""
        SELECT Issue_URL, Volume 
        FROM comics 
        WHERE Volume REGEXP '^[0-9]+\\.0$'
    """)
    
    rows = cursor.fetchall()

    if not rows:
        print("✅ No volumes need updating.")
    else:
        print(f"\n📌 Found {len(rows)} volumes that need updating:")
        print("-" * 50)
        for issue_url, volume in rows:
            corrected_volume = int(float(volume))
            print(f"🔗 Issue_URL: {issue_url} | 📖 Volume: {volume} ➝ {corrected_volume}")
        print("-" * 50)

        # ✅ Ask for confirmation
        confirm = input("\n❗ Do you want to update these volumes? (yes/no): ").strip().lower()
        
        if confirm == "yes":
            # ✅ Update volumes to remove .0 using Issue_URL as reference
            for issue_url, volume in rows:
                solid_number = str(int(float(volume)))  # Convert 1.0 → 1
                cursor.execute("""
                    UPDATE comics 
                    SET Volume = %s 
                    WHERE Issue_URL = %s
                """, (solid_number, issue_url))
            
            conn.commit()
            print(f"\n✅ Successfully updated {len(rows)} volumes.")

        else:
            print("\n⚠️ Update canceled. No changes were made.")

except pymysql.MySQLError as e:
    print(f"\n❌ Database error: {e}")

finally:
    if conn:
        conn.close()
        print("\n✅ Database connection closed.")

import mysql.connector

def main():
    # 1) Connect to your database
    connection = mysql.connector.connect(
        host='localhost',
        user='root',
        password='',
        database='comics_db'
    )
    cursor = connection.cursor(buffered=True)

    print("🔍 Previewing rows missing '#' in Issue_Number...")

    # 2) Preview — safe chunked scan
    preview_rows = []
    chunk_size = 100
    last_id = 0

    while True:
        cursor.execute(f"""
            SELECT id, Issue_Number
            FROM comics
            WHERE id > {last_id}
              AND Issue_Number IS NOT NULL
              AND Issue_Number != ''
              AND Issue_Number NOT LIKE '#%'
            ORDER BY id ASC
            LIMIT {chunk_size};
        """)
        rows = cursor.fetchall()
        if not rows:
            break
        preview_rows.extend(rows)
        last_id = rows[-1][0]

    # 3) Print preview
    print("=== PREVIEW: Rows that will be updated ===")
    for row in preview_rows:
        row_id = row[0]
        old_value = row[1]
        new_value = "#" + old_value.lstrip('#')
        print(f"ID: {row_id}, Old: {old_value}, New: {new_value}")

    print(f"\nTotal rows found: {len(preview_rows)}")

    # 4) Apply updates if any
    if preview_rows:
        confirm = input("\nProceed with update? (y/n): ").strip().lower()
        if confirm == 'y':
            updated_count = 0
            for row in preview_rows:
                row_id = row[0]
                old_value = row[1]
                new_value = "#" + old_value.lstrip('#')
                try:
                    cursor.execute("UPDATE comics SET Issue_Number = %s WHERE id = %s", (new_value, row_id))
                    connection.commit()
                    updated_count += 1
                except Exception as e:
                    print(f"❌ Failed to update ID {row_id}: {e}")
            print(f"\n✅ Update complete. Rows updated: {updated_count}")
        else:
            print("❌ Update canceled.")
    else:
        print("ℹ️ No rows to update.")

    # 5) Close up
    cursor.close()
    connection.close()

if __name__ == '__main__':
    main()

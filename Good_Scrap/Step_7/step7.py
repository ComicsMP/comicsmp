import mysql.connector

def main():
    # 1) Connect to the database
    connection = mysql.connector.connect(
        host='localhost',
        user='root',
        password='',
        database='comics_db'
    )

    cursor = connection.cursor()

    # 2) Preview rows with duplicated words/phrases (safe version)
    preview_query = """
        SELECT id, Variant,
            REGEXP_REPLACE(
                Variant,
                '\\\\b([A-Za-z]+(?: [A-Za-z0-9]+){0,2})\\\\b[[:space:]]+\\\\1\\\\b',
                '\\\\1'
            ) AS cleaned_variant
        FROM comics
        WHERE Variant REGEXP '\\\\b([A-Za-z]+(?: [A-Za-z0-9]+){0,2})\\\\b[[:space:]]+\\\\1\\\\b';
    """
    cursor.execute(preview_query)
    rows = cursor.fetchall()

    print("=== PREVIEW: Rows that will be updated ===")
    for row in rows:
        row_id = row[0]
        original = row[1]
        cleaned = row[2]
        print(f"ID: {row_id}\nOriginal: {original}\nCleaned:  {cleaned}\n")

    print(f"\nTotal rows to be updated: {len(rows)}")

    # 3) Apply update if rows found
    if rows:
        update_query = """
            UPDATE comics 
            SET Variant = REGEXP_REPLACE(
                Variant,
                '\\\\b([A-Za-z]+(?: [A-Za-z0-9]+){0,2})\\\\b[[:space:]]+\\\\1\\\\b',
                '\\\\1'
            )
            WHERE Variant REGEXP '\\\\b([A-Za-z]+(?: [A-Za-z0-9]+){0,2})\\\\b[[:space:]]+\\\\1\\\\b';
        """
        cursor.execute(update_query)
        connection.commit()
        print(f"UPDATE complete. Rows updated: {cursor.rowcount}")
    else:
        print("No duplicated entries found. Nothing to update.")

    # Close up
    cursor.close()
    connection.close()

if __name__ == '__main__':
    main()

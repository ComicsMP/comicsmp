import mysql.connector

def main():
    # 1) Connect to your database
    connection = mysql.connector.connect(
        host='localhost',
        user='root',
        password='',
        database='comics_db'
    )
    
    cursor = connection.cursor()

    # 2) Preview rows missing the '#' prefix
    preview_query = """
        SELECT id, Issue_Number
        FROM comics
        WHERE Issue_Number NOT LIKE '#%'
          AND Issue_Number IS NOT NULL
          AND Issue_Number != '';
    """
    cursor.execute(preview_query)
    rows = cursor.fetchall()

    print("=== PREVIEW: Rows that will be updated ===")
    for row in rows:
        row_id = row[0]
        old_value = row[1]
        new_value = "#" + old_value  # what it will become
        print(f"ID: {row_id}, Old: {old_value}, New: {new_value}")

    print(f"\nTotal rows found: {len(rows)}")

    # 3) Automatically apply the update if rows are found
    if rows:
        update_query = """
            UPDATE comics
            SET Issue_Number = CONCAT('#', Issue_Number)
            WHERE Issue_Number NOT LIKE '#%'
              AND Issue_Number IS NOT NULL
              AND Issue_Number != '';
        """
        cursor.execute(update_query)
        connection.commit()
        print(f"UPDATE complete. Rows updated: {cursor.rowcount}")
    else:
        print("No rows to update.")

    # Close up
    cursor.close()
    connection.close()

if __name__ == '__main__':
    main()

import pandas as pd
import mysql.connector
import logging

# Configure logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

# 🔹 MySQL Database Connection Details (Update These)
DB_CONFIG = {
    "host": "localhost",  # Change to your database host
    "user": "root",       # Change to your MySQL username
    "password": "",       # Change to your MySQL password
    "database": "comics_db"  # Change to your database name
}

# 🔹 Step 1: Read the Excel file with Issue URLs
def get_issue_urls_from_excel(file_path):
    logging.info(f"Reading Issue URLs from {file_path}...")
    df = pd.read_excel(file_path)
    
    # Ensure the 'Issue_URL' column exists
    if "Issue_URL" not in df.columns:
        logging.error("❌ 'Issue_URL' column not found in the provided Excel file.")
        return []
    
    # Extract unique Issue URLs
    issue_urls = df["Issue_URL"].dropna().unique().tolist()
    logging.info(f"✅ Found {len(issue_urls)} unique Issue URLs.")
    return issue_urls

# 🔹 Step 2: Fetch Matching Rows from MySQL
def fetch_matching_rows(issue_urls):
    if not issue_urls:
        logging.warning("⚠ No Issue URLs provided, skipping database query.")
        return pd.DataFrame()

    try:
        # Connect to MySQL
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)

        # Generate SQL query
        sql_query = f"""
        SELECT * FROM comics WHERE Issue_URL IN ({','.join(['%s'] * len(issue_urls))})
        """
        
        # Execute query
        cursor.execute(sql_query, issue_urls)
        rows = cursor.fetchall()

        # Close connection
        cursor.close()
        conn.close()

        if not rows:
            logging.warning("⚠ No matching records found in the database.")
            return pd.DataFrame()

        # Convert query results to a DataFrame
        df = pd.DataFrame(rows)
        logging.info(f"✅ Retrieved {len(df)} matching records from the database.")
        return df

    except mysql.connector.Error as err:
        logging.error(f"❌ Database error: {err}")
        return pd.DataFrame()

# 🔹 Step 3: Save Results to Excel
def save_results_to_excel(df, output_file="new_images_data.xlsx"):
    if df.empty:
        logging.warning("⚠ No data to save.")
        return

    df.to_excel(output_file, index=False)
    logging.info(f"✅ Data saved to {output_file}")

# 🔹 Main Execution
def main():
    input_file = "updated_issue_urls.xlsx"  # Excel file containing new Issue URLs
    output_file = "new_images_data.xlsx"  # Output file with full records

    # Step 1: Get list of Issue URLs
    issue_urls = get_issue_urls_from_excel(input_file)

    # Step 2: Fetch matching records from the database
    matching_data = fetch_matching_rows(issue_urls)

    # Step 3: Save results to an Excel file
    save_results_to_excel(matching_data, output_file)

if __name__ == "__main__":
    main()

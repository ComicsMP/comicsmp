import pandas as pd
import logging
import os
import shutil

# Setup logging
logging.basicConfig(level=logging.INFO, format="%(asctime)s - %(levelname)s - %(message)s")

# File Paths (current working directory)
NEW_IMAGES_FILE = "new_images_data.xlsx"  # The database export
UPDATED_ISSUE_FILE = "updated_issue_urls.xlsx"  # Contains new Image URLs under column New_Image_URL

# Output folder and file name
OUTPUT_FOLDER = r"C:\xampp6\htdocs\comicsmp\Good_Scrap\Step_2"
OUTPUT_FILE = os.path.join(OUTPUT_FOLDER, "new_images_data_updated.xlsx")

def load_excel(file_path):
    """Loads an Excel file and returns a DataFrame."""
    try:
        logging.info(f"📖 Loading file: {file_path}")
        df = pd.read_excel(file_path)
        logging.info(f"✅ Loaded {len(df)} records from {file_path}")
        return df
    except Exception as e:
        logging.error(f"❌ Error loading {file_path}: {e}")
        return pd.DataFrame()

def update_image_urls_and_clean(df_new_images, df_updated_issues):
    """
    Updates the 'Image_URL' column in df_new_images with values from df_updated_issues
    and cleans up the 'Image_Path', 'last_check', and 'Last_Checked' columns.
    Also drops the 'ID' column if it exists.
    """
    if df_new_images.empty or df_updated_issues.empty:
        logging.warning("⚠ One or both input files are empty. No updates will be made.")
        return df_new_images

    # Drop "ID" column if it exists
    for col in ["ID", "id"]:
        if col in df_new_images.columns:
            df_new_images = df_new_images.drop(columns=[col])
            logging.info(f"✅ Dropped column '{col}' from new images data.")

    # Ensure the necessary columns exist in updated issues
    required_cols = ["Issue_URL", "New_Image_URL"]
    for col in required_cols:
        if col not in df_updated_issues.columns:
            logging.error(f"❌ Column '{col}' is missing from {UPDATED_ISSUE_FILE}. Cannot proceed.")
            return df_new_images

    # Create a mapping of Issue_URL → New_Image_URL
    issue_url_to_image_url = df_updated_issues.set_index("Issue_URL")["New_Image_URL"].to_dict()

    # Update Image_URL in df_new_images where Issue_URL matches
    df_new_images["Image_URL"] = df_new_images["Issue_URL"].map(issue_url_to_image_url).fillna(df_new_images["Image_URL"])

    # Clean up Image_Path column if it exists
    if "Image_Path" in df_new_images.columns:
        df_new_images["Image_Path"] = None

    # Clean up last_check or Last_Checked column if it exists
    for col in ["last_check", "Last_Checked"]:
        if col in df_new_images.columns:
            df_new_images[col] = None

    logging.info(f"✅ Updated Image_URL for {len(issue_url_to_image_url)} records.")
    logging.info("✅ Cleaned 'Image_Path' and 'last_check' (or 'Last_Checked') columns.")

    return df_new_images

def save_updated_excel(df, output_path):
    """Saves the updated DataFrame to an Excel file."""
    try:
        # Ensure output folder exists
        os.makedirs(os.path.dirname(output_path), exist_ok=True)
        df.to_excel(output_path, index=False)
        logging.info(f"✅ Updated file saved as {output_path}")
    except Exception as e:
        logging.error(f"❌ Error saving file {output_path}: {e}")

def delete_file(file_path):
    """Deletes a file if it exists."""
    try:
        if os.path.exists(file_path):
            os.remove(file_path)
            logging.info(f"✅ Deleted file: {file_path}")
    except Exception as e:
        logging.error(f"❌ Error deleting file {file_path}: {e}")

def main():
    # Load both Excel files
    df_new_images = load_excel(NEW_IMAGES_FILE)
    df_updated_issues = load_excel(UPDATED_ISSUE_FILE)

    # Update Image_URL and clean Image_Path, last_check/Last_Checked; drop the ID column.
    df_updated = update_image_urls_and_clean(df_new_images, df_updated_issues)

    # Save the updated file to the specified output folder
    save_updated_excel(df_updated, OUTPUT_FILE)

    # Delete the original input files after processing
    delete_file(NEW_IMAGES_FILE)
    delete_file(UPDATED_ISSUE_FILE)

if __name__ == "__main__":
    main()

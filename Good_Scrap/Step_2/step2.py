import asyncio
import aiohttp
import pandas as pd
import hashlib
from pathlib import Path
from tqdm.asyncio import tqdm
import logging
from datetime import datetime
import os
import shutil

# Setup basic logging
logging.basicConfig(level=logging.INFO, format='%(asctime)s - %(levelname)s - %(message)s')

# Define image directory (keep as your specified location)
image_directory = Path(r"C:\xampp6\htdocs\comicsmp\images")
image_directory.mkdir(exist_ok=True)

# Define placeholder image setup
GENERIC_PLACEHOLDER_FILENAME = "default.jpg"
GENERIC_PLACEHOLDER_PATH = f"/images/{GENERIC_PLACEHOLDER_FILENAME}"

MAX_RETRIES = 3  # Retry failed downloads
CONCURRENT_LIMIT = 50  # Limit concurrent downloads

def normalize_field(value):
    """
    Normalize a field by converting it to a string, stripping whitespace,
    and converting to lowercase.
    """
    if pd.isna(value) or value is None:
        return ""
    return str(value).strip().lower()

def unify(value):
    """
    Convert pandas NaN to None, strip strings, and return None if empty
    or if the string (uppercased) equals 'N/A'.
    """
    if pd.isna(value) or value is None:
        return None
    if isinstance(value, str):
        value = value.strip()
        if value == '' or value.upper() == "N/A":
            return None
    return value

def compute_unique_id(comic_title, issue_number, issue_url):
    """
    Compute Unique_ID using SHA256 hash of normalized (Comic_Title, Issue_Number, Issue_URL).
    """
    norm_title = normalize_field(comic_title)
    norm_issue = normalize_field(issue_number)
    norm_url = normalize_field(issue_url)
    identifier = f"{norm_title}-{norm_issue}-{norm_url}"
    return hashlib.sha256(identifier.encode('utf-8')).hexdigest()

async def download_image(session, url, unique_id, semaphore, attempt=1):
    """
    Download an image from the URL and save it as '<unique_id>.jpg' in the designated directory.
    Returns a tuple: (url, relative_path, unique_id, timestamp).
    If the image is a placeholder or download fails, returns GENERIC_PLACEHOLDER_PATH.
    """
    async with semaphore:
        try:
            # Remove trailing '/thm' if present and update the URL accordingly.
            if isinstance(url, str) and url.endswith('/thm'):
                url = url[:-4]

            # Check for placeholder keywords in the URL.
            if any(keyword in url.lower() for keyword in ["missing_thm.jpg", "missing_lrg.jpg", "no_cover", "placeholder"]):
                return url, GENERIC_PLACEHOLDER_PATH, unique_id, None

            async with session.get(url) as response:
                if response.status == 200:
                    image_data = await response.read()
                    file_name = f"{unique_id}.jpg"
                    file_path = image_directory / file_name
                    relative_path = f"/images/{file_name}"

                    with open(file_path, 'wb') as f:
                        f.write(image_data)

                    timestamp = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                    return url, relative_path, unique_id, timestamp
                else:
                    logging.error(f"Failed to download {url}: Status {response.status}, Attempt {attempt}")
                    if attempt < MAX_RETRIES:
                        return await download_image(session, url, unique_id, semaphore, attempt + 1)
                    return url, None, None, None
        except Exception as e:
            logging.error(f"Error downloading {url}: {e}, Attempt {attempt}")
            if attempt < MAX_RETRIES:
                return await download_image(session, url, unique_id, semaphore, attempt + 1)
            return url, None, None, None

async def process_file(file_path):
    """
    Process an Excel file:
      - Reads the file.
      - Renames columns to match the required DB fields.
      - Verifies that required columns exist.
      - Computes Unique_ID for each row.
      - Downloads images asynchronously.
      - Updates 'Image_Path', 'Unique_ID', 'Timestamp', and 'Image_URL' in the dataframe.
      - Saves the updated file.
    """
    try:
        df = pd.read_excel(file_path)
    except Exception as e:
        logging.error(f"Error reading {file_path}: {e}")
        return 0, 0

    # Define mapping for column renaming (if a field has a different name, rename it)
    rename_map = {
        'comic title': 'Comic_Title',
        'comic_title': 'Comic_Title',
        'issue number': 'Issue_Number',
        'issue_number': 'Issue_Number',
        'issue url': 'Issue_URL',
        'issue_url': 'Issue_URL',
        'image url': 'Image_URL',
        'image_url': 'Image_URL',
        'unique id': 'Unique_ID',
        'unique_id': 'Unique_ID',
        'image path': 'Image_Path',
        'image_path': 'Image_Path',
        'publisher name': 'Publisher_Name',
        'publisher_name': 'Publisher_Name',
        'issues note': 'Issues_Note',
        'issues_note': 'Issues_Note'
    }

    new_columns = {}
    for col in df.columns:
        col_lower = col.strip().lower()
        if col_lower in rename_map:
            new_columns[col] = rename_map[col_lower]
    df.rename(columns=new_columns, inplace=True)

    # List of expected DB fields
    expected_columns = [
        'Tab', 'Comic_Title', 'Years', 'Volume', 'Country', 'Issues_Note',
        'Issue_Number', 'Issue_URL', 'Image_URL', 'Date', 'Variant', 'Edition',
        'Publisher_Name', 'Unique_ID', 'Image_Path', 'Timestamp'
    ]

    # Add any missing expected columns to the dataframe
    for col in expected_columns:
        if col not in df.columns:
            df[col] = None

    # Verify that the required columns exist.
    required_cols = ['Comic_Title', 'Issue_Number', 'Issue_URL', 'Image_URL']
    for col in required_cols:
        if col not in df.columns or df[col].isna().all():
            logging.error(f"Required column '{col}' not found or empty in {file_path.name}. Skipping file.")
            return 0, 0
    # Normalization for Comic_Title
    df['Comic_Title'] = df['Comic_Title'].apply(lambda x: str(x).strip().upper() if pd.notna(x) else x)
    # Compute Unique_ID for each row using standardized column names.
    df['Unique_ID'] = df.apply(lambda row: compute_unique_id(row['Comic_Title'], row['Issue_Number'], row['Issue_URL']), axis=1)
    # Set default values for Image_Path and Timestamp
    df['Image_Path'] = None
    df['Timestamp'] = None

    semaphore = asyncio.Semaphore(CONCURRENT_LIMIT)
    async with aiohttp.ClientSession() as session:
        tasks = []
        for _, row in df.iterrows():
            image_url = row['Image_URL']
            if pd.notna(image_url):
                unique_id = row['Unique_ID']
                tasks.append(download_image(session, image_url, unique_id, semaphore))

        # Process asynchronous download tasks with progress reporting.
        for future in tqdm(asyncio.as_completed(tasks), total=len(tasks), desc=f"Downloading Images for {file_path.name}"):
            try:
                result = await future
                url, path, unique_id, timestamp = result
                if path:
                    # If the downloaded image is the generic placeholder, bypass the Unique_ID update by setting it to None.
                    new_unique_id = None if path == GENERIC_PLACEHOLDER_PATH else unique_id
                    # Update the dataframe: if the original Image_URL ended with '/thm', update it with the cleaned URL.
                    df.loc[(df['Image_URL'] == url) | (df['Image_URL'] == url + '/thm'),
                           ['Image_URL', 'Image_Path', 'Unique_ID', 'Timestamp']] = [url, path, new_unique_id, timestamp]
            except Exception as e:
                logging.error(f"Error processing a download task: {e}")

    new_file_path = file_path.parent / f"processed_{file_path.stem}_updated.xlsx"
    try:
        df.to_excel(new_file_path, index=False)
        logging.info(f"Processed file saved as {new_file_path}")
    except Exception as e:
        logging.error(f"Error saving file {new_file_path}: {e}")
    return df.shape[0], len(os.listdir(image_directory))

async def main():
    """
    Process all Excel files in the current directory (excluding those starting with 'processed_'),
    then move the final processed file(s) to the sibling folder Step_3 and delete the original file(s).
    """

    # If we want to store final outputs in Step_3 (sibling to Step_2), we use ../Step_3
    step3_folder = "../Step_3"  # Sibling folder at the same level as Step_2
    os.makedirs(step3_folder, exist_ok=True)

    total_rows = 0
    total_images = 0
    for file_path in Path('.').glob('*.xlsx'):
        if not file_path.name.startswith('processed_'):
            logging.info(f"Processing file: {file_path.name}")
            rows, images = await process_file(file_path)
            total_rows += rows
            total_images = images  # Total images in the directory

            # Determine the processed file's path (as saved in process_file)
            new_file_path = file_path.parent / f"processed_{file_path.stem}_updated.xlsx"
            if new_file_path.exists():
                destination = Path(step3_folder) / new_file_path.name
                try:
                    shutil.move(str(new_file_path), str(destination))
                    logging.info(f"Moved processed file to {destination}")
                except Exception as e:
                    logging.error(f"Error moving file {new_file_path} to Step_3: {e}")
            # Delete the original file from Step_2
            try:
                os.remove(file_path)
                logging.info(f"Deleted original file {file_path}")
            except Exception as e:
                logging.error(f"Error deleting original file {file_path}: {e}")
    logging.info(f"Total rows processed: {total_rows}")
    logging.info(f"Total image files downloaded: {total_images}")

if __name__ == '__main__':
    asyncio.run(main())

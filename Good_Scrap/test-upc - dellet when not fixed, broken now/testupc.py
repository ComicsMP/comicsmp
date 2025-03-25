import os
os.environ["KMP_DUPLICATE_LIB_OK"] = "TRUE"  # Allow duplicate OpenMP runtime for testing

import re
import time
import pymysql
import cloudscraper
from bs4 import BeautifulSoup
from urllib.parse import quote_plus
from PIL import Image
from io import BytesIO
import numpy as np
import cv2
import faiss
import torch
import torchvision.models as models
import torchvision.transforms as transforms

# -------------------------------
# Configuration and Setup
# -------------------------------
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '',
    'database': 'comics_db'
}
SEARCH_LIMIT = 1  # Testing with a single entry

# Path to the FAISS index file (update as needed)
FAISS_INDEX_PATH = r"C:\xampp6\htdocs\comicsmp\FAISS_Mobile_Matching\faiss_index_L2.bin"
# Directory to temporarily save downloaded images
UPLOADS_DIR = r"C:\xampp6\htdocs\comicsmp\FAISS_Mobile_Matching\uploads"
os.makedirs(UPLOADS_DIR, exist_ok=True)

# Create a cloudscraper session (bypasses Cloudflare protection)
scraper = cloudscraper.create_scraper()

# -------------------------------
# EfficientNet-based Feature Extraction Setup
# -------------------------------
def load_model():
    # Load EfficientNet-B7 with pretrained weights and remove the classifier
    model = models.efficientnet_b7(weights=models.EfficientNet_B7_Weights.DEFAULT)
    model.classifier = torch.nn.Identity()  # Use features only
    model.eval()
    return model

model = load_model()

# Use the same transform as in your FAISS server
transform = transforms.Compose([
    transforms.Resize((600, 600)),  # Resize to 600x600 as in your index server
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406],
                         std=[0.229, 0.224, 0.225]),
])

def extract_features(image):
    """
    Extract a normalized 2560-dimensional feature vector using EfficientNet-B7.
    """
    try:
        image = image.convert("RGB")
        tensor_img = transform(image).unsqueeze(0)  # Shape: (1, C, H, W)
        with torch.no_grad():
            features = model(tensor_img)
        features = features.numpy().flatten()
        # Verify the dimension; should be 2560.
        if features.shape[0] != 2560:
            raise ValueError(f"Feature dimension mismatch! Expected 2560, got {features.shape[0]}")
        return features / np.linalg.norm(features)
    except Exception as e:
        raise RuntimeError(f"Error in feature extraction: {e}")

# -------------------------------
# Load FAISS Index (once)
# -------------------------------
print("[FAISS] Loading FAISS index...")
try:
    index = faiss.read_index(FAISS_INDEX_PATH)
    print("[FAISS] FAISS index loaded.")
except Exception as e:
    print(f"[FAISS] Error loading FAISS index: {e}")
    exit(1)

# -------------------------------
# STEP 1: Database Connection
# -------------------------------
print("[1] Connecting to database and fetching missing UPC entries...")
connection = pymysql.connect(**DB_CONFIG)
cursor = connection.cursor()
query = (
    "SELECT Comic_Title, Issue_Number, Years, Date "
    "FROM comics "
    "WHERE UPC IS NULL "
    "LIMIT %s"
)
cursor.execute(query, (SEARCH_LIMIT,))
results = cursor.fetchall()
cursor.close()
connection.close()
print(f"[1] Retrieved {len(results)} entries to test.")

# -------------------------------
# STEP 2: Process Each Issue and Match Cover Image via FAISS
# -------------------------------
for i, (title, issue, year, date) in enumerate(results):
    # Use only the first year if year is a range (e.g., "2019-2022" -> "2019")
    search_year = year.split('-')[0] if year and '-' in year else year

    # Clean the issue number: remove leading '#' and extract first digits.
    issue_str = str(issue).lstrip('#')
    issue_match = re.search(r'\d+', issue_str)
    issue_num = issue_match.group(0) if issue_match else ''
    
    print(f"\n[2.{i+1}] Processing: {title} #{issue_num} ({search_year})")
    search_term = f"{title} {search_year} #{issue_num}"
    search_url = f"https://www.comics.org/searchNew/?q={quote_plus(search_term)}&search_object=issue"
    print(f"[2.{i+1}] Search URL: {search_url}")

    try:
        res = scraper.get(search_url, timeout=10)
        soup = BeautifulSoup(res.text, 'html.parser')
        
        # Grab all <a> tags whose href contains '/issue/'
        all_issue_links = [
            a.get("href")
            for a in soup.find_all("a", href=True)
            if "/issue/" in a.get("href")
        ]
        
        # Filter out any links that include "modal" and enforce pattern like "/issue/123456/"
        issue_links = []
        for link in all_issue_links:
            if "modal" in link:
                continue
            if re.match(r'^/issue/\d+/?$', link):
                issue_links.append(f"https://www.comics.org{link}")
        # Remove duplicates
        issue_links = list(dict.fromkeys(issue_links))
        
        print(f"[2.{i+1}] Found {len(issue_links)} issue link(s) to check.")
        if not issue_links:
            print("[DEBUG] No issue links found. Here is a snippet of the page source:")
            print(soup.prettify()[:1000])
            continue

        # Process only the first issue link for testing
        for j, issue_link in enumerate(issue_links):
            print(f"[3.{i+1}.{j+1}] Visiting issue page: {issue_link}")
            issue_res = scraper.get(issue_link, timeout=10)
            issue_soup = BeautifulSoup(issue_res.text, 'html.parser')

            # Barcode/UPC Extraction
            text_blocks = issue_soup.get_text()
            barcode_match = re.search(r'(UPC|Barcode)(?:/EAN)?:?\s*(\d{12,14})', text_blocks, re.IGNORECASE)
            if barcode_match:
                code = barcode_match.group(2)
                print(f"[3.{i+1}.{j+1}] Found Barcode/UPC: {code}")
            else:
                print(f"[3.{i+1}.{j+1}] Barcode/UPC not found.")

            # Cover Image Extraction: look for <img> with class "cover_img"
            img_tag = issue_soup.find('img', {'class': 'cover_img'})
            if img_tag:
                img_url = img_tag.get("src")
                print(f"[3.{i+1}.{j+1}] Found cover image: {img_url}")

                try:
                    img_res = scraper.get(img_url, stream=True, timeout=10)
                    if img_res.status_code != 200:
                        print(f"[3.{i+1}.{j+1}] Failed to download image, status code: {img_res.status_code}")
                        continue

                    # Save image to the uploads folder
                    filename = os.path.join(UPLOADS_DIR, os.path.basename(img_url.split('?')[0]))
                    with open(filename, 'wb') as f:
                        f.write(img_res.content)
                    print(f"[3.{i+1}.{j+1}] Image saved to: {filename}")
                    
                    # Open the image from disk
                    try:
                        img = Image.open(filename).convert('RGB')
                        print(f"[3.{i+1}.{j+1}] Image opened successfully.")
                    except Exception as open_e:
                        print(f"[3.{i+1}.{j+1}] Error opening saved image: {open_e}")
                        continue
                    
                    # -------------------------------
                    # FAISS Matching Step
                    # -------------------------------
                    feature = extract_features(img)
                    feature = np.expand_dims(feature, axis=0)  # Shape (1, d)
                    k = 1  # number of nearest neighbors to return
                    distances, indices = index.search(feature, k)
                    print(f"[3.{i+1}.{j+1}] Top FAISS match index: {indices[0][0]}, distance: {distances[0][0]}")
                    
                except Exception as img_e:
                    print(f"[3.{i+1}.{j+1}] Error downloading or processing image: {img_e}")
            else:
                print(f"[3.{i+1}.{j+1}] Cover image not found.")

            # For testing, process only the first issue link per entry.
            break

    except Exception as e:
        print(f"[2.{i+1}] Error during search or parsing: {e}")
    time.sleep(1)

print("\n✅ Test script completed. No data written to DB. All results shown above.")

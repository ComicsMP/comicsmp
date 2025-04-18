import os
import torch
import torchvision.models as models
import torchvision.transforms as transforms
import numpy as np
import faiss
import pickle
from flask import Flask, request, jsonify
from flask_cors import CORS
from PIL import Image, ImageFilter, ExifTags, ImageOps
from werkzeug.utils import secure_filename
import mysql.connector

# ✅ Fix OpenMP issue (Prevents FAISS from crashing)
os.environ["KMP_DUPLICATE_LIB_OK"] = "TRUE"

# -------------------- Flask Setup --------------------
app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})

UPLOAD_FOLDER = "uploads"
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER

# -------------------- Dummy Login Route --------------------
@app.route("/login", methods=["POST"])
def login():
    data = request.get_json()
    username = data.get("username")
    password = data.get("password")
    if username == "test" and password == "password":
        return jsonify({"user_id": 1})
    else:
        return jsonify({"error": "Invalid credentials"}), 401

# -------------------- Database Connection --------------------
def get_db_connection():
    conn = mysql.connector.connect(
        host="localhost",
        user="root",
        password="",
        database="comics_db"
    )
    return conn

# -------------------- Load EfficientNet Model --------------------
def load_model():
    model = models.efficientnet_b7(weights=models.EfficientNet_B7_Weights.DEFAULT)
    model.classifier = torch.nn.Identity()
    model.eval()
    return model

model = load_model()

# -------------------- Image Processing --------------------
transform = transforms.Compose([
    transforms.Lambda(lambda img: img.filter(ImageFilter.SHARPEN)),
    transforms.Resize((600, 600)),
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225]),
])

def process_mobile_image(image_path):
    try:
        with Image.open(image_path) as img:
            for orientation in ExifTags.TAGS.keys():
                if ExifTags.TAGS[orientation] == "Orientation":
                    break
            exif = img._getexif()
            if exif and orientation in exif:
                if exif[orientation] == 3:
                    img = img.rotate(180, expand=True)
                elif exif[orientation] == 6:
                    img = img.rotate(270, expand=True)
                elif exif[orientation] == 8:
                    img = img.rotate(90, expand=True)
            img = img.convert("RGB")
            # Apply histogram equalization to reduce discoloration effects.
            img = ImageOps.equalize(img)
            if img.format != "JPEG":
                jpeg_path = image_path.rsplit(".", 1)[0] + ".jpg"
                img.save(jpeg_path, "JPEG", quality=95)
                return jpeg_path
            return image_path
    except Exception as e:
        print(f"⚠️ Error processing mobile image: {e}")
        return None

# -------------------- Load FAISS Index and Metadata --------------------
INDEX_FILE = os.path.join("..", "faiss_index_L2.bin")
METADATA_FILE = os.path.join("..", "image_metadata.pkl")

if not os.path.exists(INDEX_FILE) or not os.path.exists(METADATA_FILE):
    raise FileNotFoundError("FAISS index or metadata file is missing!")

index = faiss.read_index(INDEX_FILE)
with open(METADATA_FILE, "rb") as f:
    metadata_list = pickle.load(f)

print(f"✅ FAISS index loaded successfully with {index.ntotal} images.")

metadata_dict = {}
for item in metadata_list:
    if isinstance(item, tuple) and len(item) > 0:
        key = str(item[0]).lower().replace(" ", "_")
        metadata_dict[key] = item
    elif isinstance(item, dict):
        key = item.get("Image_Path")
        if key and isinstance(key, str):
            key = key.lower().replace(" ", "_")
            metadata_dict[key] = item

# -------------------- Extract Features --------------------
def extract_features(image_path):
    try:
        image_path = process_mobile_image(image_path)
        if not image_path:
            return None
        with Image.open(image_path) as img:
            img = img.convert("RGB")
            tensor_img = transform(img).unsqueeze(0)
            with torch.no_grad():
                features = model(tensor_img)
        features = features.numpy().flatten()
        if features.shape[0] != 2560:
            raise ValueError(f"Feature dimension mismatch! Expected 2560, got {features.shape[0]}")
        return features / np.linalg.norm(features)
    except Exception as e:
        print(f"⚠️ Error processing image '{image_path}': {e}")
        return None

# -------------------- FAISS Search --------------------
def search_faiss(query_features, k=5):
    if query_features.shape[0] != index.d:
        raise ValueError(f"Feature dimension mismatch in search! Query: {query_features.shape[0]}, FAISS expects: {index.d}")
    D, I = index.search(np.array([query_features], dtype="float32"), k)
    results = []
    for i in range(len(I[0])):
        idx = I[0][i]
        if idx < 0 or idx >= len(metadata_list):
            continue
        dist_val = float(D[0][i])
        filename = metadata_list[idx][0]
        if not filename:
            continue
        filename = str(filename).lower().replace(" ", "_")
        if dist_val > 1400:
            continue
        results.append((filename, dist_val))
    print("🔍 FAISS Raw Distances:", D)
    print("🔍 FAISS Raw Indices:", I)
    print("🔍 FAISS Matched Files (first 5):", results[:5])
    return results

# -------------------- Search Route (POST) --------------------
@app.route("/search", methods=["POST"])
def search():
    if 'file' not in request.files:
        return jsonify([])
    file = request.files['file']
    if file.filename == '':
        return jsonify([])
    filename = secure_filename(file.filename)
    file_path = os.path.join(app.config['UPLOAD_FOLDER'], filename)
    file.save(file_path)
    features = extract_features(file_path)
    if features is None:
        return jsonify([])
    results = search_faiss(features)
    if not results:
        return jsonify([])

    # Extra filtering: select near-identical candidates based on distance and matching Comic_Title.
    THRESHOLD = 50  # Adjust as needed.
    selected = []
    if results:
        top_distance = results[0][1]
        top_filename = results[0][0]
        top_unique = os.path.splitext(top_filename)[0]
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute("SELECT Comic_Title FROM comics WHERE Unique_ID = %s LIMIT 1", (top_unique,))
            row = cursor.fetchone()
            cursor.close()
            conn.close()
            top_title = row[0].strip() if row and row[0] else ""
        except Exception as e:
            print(f"⚠️ Error fetching title for {top_unique}: {e}")
            top_title = ""
        for candidate in results:
            candidate_filename, candidate_distance = candidate
            if candidate_distance - top_distance < THRESHOLD:
                candidate_unique = os.path.splitext(candidate_filename)[0]
                try:
                    conn = get_db_connection()
                    cursor = conn.cursor()
                    cursor.execute("SELECT Comic_Title FROM comics WHERE Unique_ID = %s LIMIT 1", (candidate_unique,))
                    row = cursor.fetchone()
                    cursor.close()
                    conn.close()
                    candidate_title = row[0].strip() if row and row[0] else ""
                except Exception as e:
                    print(f"⚠️ Error fetching title for {candidate_unique}: {e}")
                    candidate_title = ""
                if candidate_title == top_title:
                    selected.append(candidate)
        if not selected:
            selected = [results[0]]
    else:
        selected = [results[0]]
    selected = selected[:5]
    
    response_data = []
    for file_name, distance in selected:
        comic_info = metadata_dict.get(file_name)
        if comic_info:
            if isinstance(comic_info, dict):
                comic_info["distance"] = distance
                if "Image_Path" not in comic_info:
                    comic_info["Image_Path"] = f"images/{file_name}"
                if not (comic_info.get("Unique_ID") or comic_info.get("unique_id")):
                    comic_info["Unique_ID"] = file_name
                response_entry = comic_info.copy()
            else:
                response_entry = {
                    "Image_Path": f"images/{file_name}",
                    "Comic_Title": "",
                    "Issue_Number": "",
                    "Variant": "",
                    "distance": distance,
                    "Unique_ID": file_name
                }
            # Fetch Country and Issue_Number from comics table for completeness.
            candidate_unique = os.path.splitext(file_name)[0]
            try:
                conn = get_db_connection()
                cursor = conn.cursor()
                cursor.execute("SELECT Country, Issue_Number FROM comics WHERE Unique_ID = %s LIMIT 1", (candidate_unique,))
                row = cursor.fetchone()
                cursor.close()
                conn.close()
                response_entry["Country"] = row[0].strip() if row and row[0] else ""
                response_entry["Issue_Number"] = row[1].strip() if row and row[1] else "N/A"
            except Exception as e:
                print(f"⚠️ Error fetching country/issue for {candidate_unique}: {e}")
                response_entry["Country"] = ""
                response_entry["Issue_Number"] = "N/A"
            response_data.append(response_entry)
    print("🔍 FAISS Response Data:", response_data)
    return jsonify(response_data)

# -------------------- Create Listing Route (POST) --------------------
@app.route("/create_listing", methods=["POST"])
def create_listing():
    data = request.get_json()
    if not data:
        return jsonify({"error": "No data provided"}), 400

    user_id = data.get("user_id")
    price = data.get("price")
    comic_condition = data.get("comic_condition")
    graded = data.get("graded")
    seller_currency = data.get("seller_currency", "")
    comic_unique_id = data.get("comic_unique_id")

    if not user_id or price is None or comic_condition is None or graded is None or not comic_unique_id:
        return jsonify({"error": "Missing required fields"}), 400

    unique_id_clean, _ = os.path.splitext(comic_unique_id)

    conn = get_db_connection()
    cursor = conn.cursor(dictionary=True)
    select_query = """
        SELECT Comic_Title, Years, Issue_Number, Issue_URL, Image_Path
        FROM comics
        WHERE Unique_ID = %s
        LIMIT 1
    """
    cursor.execute(select_query, (unique_id_clean,))
    comic_row = cursor.fetchone()
    cursor.close()

    if not comic_row:
        comic_row = {
            "Comic_Title": "",
            "Years": "",
            "Issue_Number": "",
            "Issue_URL": "",
            "Image_Path": ""
        }

    cursor = conn.cursor()
    insert_query = """
        INSERT INTO comics_for_sale 
        (user_id, comic_title, years, issue_number, price, seller_currency, comic_condition, image_path, unique_id, Issue_URL, graded)
        VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
    """
    values = (
        user_id,
        comic_row.get("Comic_Title", ""),
        comic_row.get("Years", ""),
        comic_row.get("Issue_Number", ""),
        price,
        seller_currency,
        comic_condition,
        comic_row.get("Image_Path", ""),
        unique_id_clean,
        comic_row.get("Issue_URL", ""),
        graded
    )
    try:
        cursor.execute(insert_query, values)
        conn.commit()
        insert_id = cursor.lastrowid
    except Exception as e:
        conn.rollback()
        return jsonify({"error": str(e)}), 500
    finally:
        cursor.close()
        conn.close()

    return jsonify({"message": "Listing created successfully", "insert_id": insert_id})

# -------------------- Run Flask Server --------------------
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)

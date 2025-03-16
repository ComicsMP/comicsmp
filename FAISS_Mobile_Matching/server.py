import os
import torch
import torchvision.models as models
import torchvision.transforms as transforms
import numpy as np
import faiss
import pickle
import mysql.connector
from flask import Flask, request, jsonify
from flask_cors import CORS
from PIL import Image, ImageFilter, ExifTags
from werkzeug.utils import secure_filename

# ✅ Fix OpenMP issue (Prevents FAISS from crashing)
os.environ["KMP_DUPLICATE_LIB_OK"] = "TRUE"

# -------------------- Flask Setup --------------------
app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})  # Allow cross-origin requests

UPLOAD_FOLDER = "uploads"
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER

# -------------------- Database Config --------------------
DB_CONFIG = {
    "host": "localhost",
    "user": "root",
    "password": "",
    "database": "comics_db"
}

# -------------------- Load EfficientNet Model --------------------
def load_model():
    model = models.efficientnet_b7(weights=models.EfficientNet_B7_Weights.DEFAULT)
    model.classifier = torch.nn.Identity()  # Extract only features
    model.eval()
    return model

model = load_model()

# -------------------- Image Processing (for Mobile) --------------------
transform = transforms.Compose([
    transforms.Lambda(lambda img: img.filter(ImageFilter.SHARPEN)),  # 🔪 Sharpening to enhance details
    transforms.Resize((600, 600)),  # ✅ Matches FAISS indexed images
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225]),
])

def process_mobile_image(image_path):
    """Handles common mobile image issues: rotation, resizing, format conversion."""
    try:
        with Image.open(image_path) as img:
            # 🔄 Auto-rotate based on EXIF data
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

            # 🔄 Convert to RGB (some phone images are CMYK)
            img = img.convert("RGB")

            # 🔄 Convert non-JPEG images to JPEG for consistent processing
            if img.format != "JPEG":
                jpeg_path = image_path.replace(".png", ".jpg")
                img.save(jpeg_path, "JPEG", quality=95)
                return jpeg_path  # Return the new file path

            return image_path  # Return original if already JPEG
    except Exception as e:
        print(f"⚠️ Error processing mobile image: {e}")
        return None

# -------------------- Load FAISS Index + Metadata --------------------
INDEX_FILE = "faiss_index_L2.bin"
METADATA_FILE = "image_metadata.pkl"

if not os.path.exists(INDEX_FILE) or not os.path.exists(METADATA_FILE):
    raise FileNotFoundError("FAISS index or metadata file is missing!")

index = faiss.read_index(INDEX_FILE)
with open(METADATA_FILE, "rb") as f:
    metadata = pickle.load(f)

print(f"✅ FAISS index loaded successfully with {index.ntotal} images.")

# -------------------- Extract Features --------------------
def extract_features(image_path):
    """Extract a normalized 2560-d feature vector from an image."""
    try:
        image_path = process_mobile_image(image_path)  # ✅ Preprocess mobile images
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
        return features / np.linalg.norm(features)  # Normalize
    except Exception as e:
        print(f"⚠️ Error processing image '{image_path}': {e}")
        return None

# -------------------- FAISS Search --------------------
def search_faiss(query_features, k=5):
    """Search FAISS index for the most similar images."""
    if query_features.shape[0] != index.d:
        raise ValueError(f"Feature dimension mismatch in search! Query: {query_features.shape[0]}, FAISS expects: {index.d}")

    D, I = index.search(np.array([query_features], dtype="float32"), k)
    results = []

    for i in range(len(I[0])):
        idx = I[0][i]
        if idx < 0 or idx >= len(metadata):
            continue  # Skip invalid indices

        dist_val = float(D[0][i])
        filename = metadata[idx][0].lower().replace(" ", "_")  # Normalize filename

        # ✅ Filter out bad matches
        if dist_val > 1400:  # Adjust distance threshold for better accuracy
            continue

        results.append((filename, dist_val))

    # Debug outputs
    print("🔍 FAISS Raw Distances:", D)
    print("🔍 FAISS Raw Indices:", I)
    print("🔍 FAISS Matched Files (first 5):", results[:5])
    return results

# -------------------- Query DB for Comic Details --------------------
def get_comic_details(filename):
    """Fetch comic details from comics_db."""
    try:
        conn = mysql.connector.connect(**DB_CONFIG)
        cursor = conn.cursor(dictionary=True)
        db_filename = f"images/{filename}"
        cursor.execute("SELECT * FROM comics WHERE Image_Path = %s", (db_filename,))
        result = cursor.fetchone()
        cursor.close()
        conn.close()
        return result
    except Exception as e:
        print(f"⚠️ Database error: {e}")
        return None

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

    # Extract features from the uploaded image
    features = extract_features(file_path)
    if features is None:
        return jsonify([])

    # Perform FAISS search
    results = search_faiss(features)

    if not results:
        return jsonify([])

    # Process best match and near-identical results
    best_result = results[0]
    near_identical_threshold = 0.12  # Loosened slightly for mobile
    additional_delta = 0.05         # Additional tolerance for phone images

    if best_result[1] < near_identical_threshold:
        filtered_results = [res for res in results if abs(res[1] - best_result[1]) <= additional_delta]
        results = filtered_results if filtered_results else [best_result]
    else:
        results = [best_result]

    # Fetch details from DB
    response_data = []
    for file_name, distance in results:
        comic_info = get_comic_details(file_name)
        if comic_info:
            comic_info["distance"] = distance
            response_data.append(comic_info)

    print("🔍 FAISS Response Data:", response_data)
    return jsonify(response_data)

# -------------------- Run Flask Server --------------------
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)

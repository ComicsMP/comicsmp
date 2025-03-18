import os
import torch
import torchvision.models as models
import torchvision.transforms as transforms
import numpy as np
import faiss
import pickle
from flask import Flask, request, jsonify
from flask_cors import CORS
from PIL import Image, ImageFilter, ExifTags
from werkzeug.utils import secure_filename

# ✅ Fix OpenMP issue (Prevents FAISS from crashing)
os.environ["KMP_DUPLICATE_LIB_OK"] = "TRUE"

# -------------------- Flask Setup --------------------
app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})

UPLOAD_FOLDER = "uploads"
os.makedirs(UPLOAD_FOLDER, exist_ok=True)
app.config['UPLOAD_FOLDER'] = UPLOAD_FOLDER

# -------------------- Load EfficientNet Model --------------------
def load_model():
    model = models.efficientnet_b7(weights=models.EfficientNet_B7_Weights.DEFAULT)
    model.classifier = torch.nn.Identity()  # Extract features only
    model.eval()
    return model

model = load_model()

# -------------------- Image Processing (for Mobile) --------------------
transform = transforms.Compose([
    transforms.Lambda(lambda img: img.filter(ImageFilter.SHARPEN)),  # Sharpen to enhance details
    transforms.Resize((600, 600)),  # Resize to match FAISS indexed images
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225]),
])

def process_mobile_image(image_path):
    """Handles common mobile image issues: rotation, resizing, format conversion."""
    try:
        with Image.open(image_path) as img:
            # Auto-rotate based on EXIF Orientation tag
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

            # Convert to RGB (some images may be in CMYK)
            img = img.convert("RGB")

            # Convert non-JPEG images to JPEG for consistent processing
            if img.format != "JPEG":
                jpeg_path = image_path.replace(".png", ".jpg")
                img.save(jpeg_path, "JPEG", quality=95)
                return jpeg_path

            return image_path
    except Exception as e:
        print(f"⚠️ Error processing mobile image: {e}")
        return None

# -------------------- Load FAISS Index and Metadata --------------------
INDEX_FILE = "faiss_index_L2.bin"
METADATA_FILE = "image_metadata.pkl"

if not os.path.exists(INDEX_FILE) or not os.path.exists(METADATA_FILE):
    raise FileNotFoundError("FAISS index or metadata file is missing!")

index = faiss.read_index(INDEX_FILE)
with open(METADATA_FILE, "rb") as f:
    metadata_list = pickle.load(f)

print(f"✅ FAISS index loaded successfully with {index.ntotal} images.")

# Build an in-memory dictionary for metadata lookups.
# We support both dictionary and tuple/list formats.
metadata_dict = {}
for item in metadata_list:
    key = None
    if isinstance(item, dict):
        key = item.get("Image_Path")
    elif isinstance(item, (list, tuple)):
        if len(item) > 0:
            key = item[0]
    if key and isinstance(key, str):
        key = key.lower().replace(" ", "_")
        metadata_dict[key] = item

# -------------------- Extract Features --------------------
def extract_features(image_path):
    """Extract a normalized 2560-d feature vector from an image."""
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
    """Search FAISS index for the most similar images."""
    if query_features.shape[0] != index.d:
        raise ValueError(f"Feature dimension mismatch in search! Query: {query_features.shape[0]}, FAISS expects: {index.d}")

    D, I = index.search(np.array([query_features], dtype="float32"), k)
    results = []

    for i in range(len(I[0])):
        idx = I[0][i]
        if idx < 0 or idx >= len(metadata_list):
            continue

        dist_val = float(D[0][i])
        # Get the filename from metadata_list; ensure it's a string.
        filename = metadata_list[idx][0]
        if not filename:
            continue
        filename = str(filename).lower().replace(" ", "_")
        if dist_val > 1400:  # Skip bad matches
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

    # Always return only the top (first) match.
    results = [results[0]]
    response_data = []
    for file_name, distance in results:
        comic_info = metadata_dict.get(file_name)
        if comic_info:
            # If comic_info is a dict, we assume it already contains the keys the client expects.
            if isinstance(comic_info, dict):
                comic_info["distance"] = distance
                # Ensure the key "Image_Path" exists.
                if "Image_Path" not in comic_info:
                    comic_info["Image_Path"] = f"images/{file_name}"
                response_data.append(comic_info)
            else:
                # If comic_info is not a dict (e.g., a tuple), convert it to one with the needed fields.
                response_data.append({
                    "Image_Path": f"images/{file_name}",
                    "Comic_Title": "",      # Fill with available data if any
                    "Issue_Number": "",
                    "Variant": "",
                    "distance": distance
                })

    print("🔍 FAISS Response Data:", response_data)
    return jsonify(response_data)

# -------------------- Run Flask Server --------------------
if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)

import os
import torch
import torchvision.models as models
import torchvision.transforms as transforms
import numpy as np
import faiss
import pickle
import cv2
from flask import Flask, request, jsonify
from flask_cors import CORS
from PIL import Image, ExifTags
from werkzeug.utils import secure_filename
import mysql.connector

# Prevent FAISS/OpenMP crashes
os.environ["KMP_DUPLICATE_LIB_OK"] = "TRUE"

# ——— Paths —————————————————————————————————————————————————————————
BASE_DIR       = r"C:\xampp6\htdocs\comicsmp\FAISS_Mobile_Matching"
UPLOAD_FOLDER  = os.path.join(BASE_DIR, "in progress", "uploads")
INDEX_FILE     = os.path.join(BASE_DIR, "faiss_index_L2.bin")
METADATA_FILE  = os.path.join(BASE_DIR, "image_metadata.pkl")
IMAGES_DIR     = os.path.join(BASE_DIR, "images")

os.makedirs(UPLOAD_FOLDER, exist_ok=True)

# ——— Flask setup ——————————————————————————————————————————————————————
app = Flask(__name__)
CORS(app, resources={r"/*": {"origins": "*"}})
app.config["UPLOAD_FOLDER"] = UPLOAD_FOLDER

# ——— Database connection ——————————————————————————————————————————————
def get_db_connection():
    return mysql.connector.connect(
        host="localhost", user="root", password="", database="comics_db"
    )

# ——— Load EfficientNet and FAISS index ————————————————————————————————————
def load_model():
    m = models.efficientnet_b7(weights=models.EfficientNet_B7_Weights.DEFAULT)
    m.classifier = torch.nn.Identity()
    m.eval()
    return m

model = load_model()

transform = transforms.Compose([
    transforms.Resize((600,600)),
    transforms.ToTensor(),
    transforms.Normalize([0.485,0.456,0.406],[0.229,0.224,0.225]),
])

index = faiss.read_index(INDEX_FILE)
with open(METADATA_FILE, "rb") as f:
    metadata_list = pickle.load(f)

# build metadata lookup dict
metadata_dict = {}
for item in metadata_list:
    if isinstance(item, tuple):
        key = str(item[0]).lower().replace(" ","_")
        metadata_dict[key] = item
    elif isinstance(item, dict) and isinstance(item.get("Image_Path"), str):
        key = item["Image_Path"].lower().replace(" ","_")
        metadata_dict[key] = item

# ——— Image utilities ————————————————————————————————————————————————

def correct_orientation(img: Image.Image):
    try:
        for tag, val in ExifTags.TAGS.items():
            if val == "Orientation":
                orientation_tag = tag
                break
        exif = img._getexif()
        if exif and orientation_tag in exif:
            orient = exif[orientation_tag]
            if orient == 3: img = img.rotate(180, expand=True)
            elif orient == 6: img = img.rotate(270, expand=True)
            elif orient == 8: img = img.rotate(90, expand=True)
    except:
        pass
    return img.convert("RGB")

def load_and_preprocess(path):
    img = Image.open(path)
    return correct_orientation(img)

def multi_crops(img: Image.Image):
    w, h = img.size
    crop_size = int(min(w, h) * 0.9)
    coords = [
        ((w - crop_size)//2, (h - crop_size)//2),
        (0, 0),
        (w - crop_size, 0),
        (0, h - crop_size),
        (w - crop_size, h - crop_size)
    ]
    crops = []
    for x, y in coords:
        c = img.crop((x, y, x+crop_size, y+crop_size)).resize((600,600))
        crops.append(c)
        crops.append(c.transpose(Image.FLIP_LEFT_RIGHT))
    return crops

def extract_features(path):
    img   = load_and_preprocess(path)
    crops = multi_crops(img)
    feats = []
    with torch.no_grad():
        for c in crops:
            t = transform(c).unsqueeze(0)
            f = model(t).cpu().numpy().flatten()
            feats.append(f / np.linalg.norm(f))
    avg = np.mean(feats, axis=0)
    return avg / np.linalg.norm(avg)

# ——— Hybrid search & re-ranking ——————————————————————————————————————

def compute_hist_score(query_path, candidate_path):
    q_img = cv2.imread(query_path)
    c_img = cv2.imread(candidate_path)
    if q_img is None or c_img is None:
        return 0.0
    q = cv2.cvtColor(q_img, cv2.COLOR_BGR2HSV)
    c = cv2.cvtColor(c_img, cv2.COLOR_BGR2HSV)
    hist_q = cv2.calcHist([q],[0,1],None,[50,60],[0,180,0,256])
    hist_c = cv2.calcHist([c],[0,1],None,[50,60],[0,180,0,256])
    cv2.normalize(hist_q,hist_q)
    cv2.normalize(hist_c,hist_c)
    return max(0.0, cv2.compareHist(hist_q, hist_c, cv2.HISTCMP_CORREL))

def search_faiss_hybrid(query_vec, query_file, k=10):
    D, I = index.search(np.array([query_vec],dtype='float32'), k)
    candidates = []
    for dist, idx in zip(D[0], I[0]):
        if idx<0 or idx>=len(metadata_list): continue
        cos_sim = 1 - dist/2
        fn = str(metadata_list[idx][0]).lower().replace(" ", "_")
        candidates.append((fn, cos_sim))

    hybrid = []
    for fn, cos_sim in candidates:
        db_img = os.path.join(IMAGES_DIR, fn)
        hist  = compute_hist_score(query_file, db_img)
        score = 0.8 * cos_sim + 0.2 * hist
        hybrid.append((fn, score))

    hybrid.sort(key=lambda x: x[1], reverse=True)
    return hybrid[:5]

# ——— /search endpoint ——————————————————————————————————————————————

@app.route("/search", methods=["POST"])
def search():
    if 'file' not in request.files:
        return jsonify([])
    f = request.files['file']
    if f.filename == "":
        return jsonify([])

    fn   = secure_filename(f.filename)
    path = os.path.join(app.config["UPLOAD_FOLDER"], fn)
    f.save(path)

    try:
        qv = extract_features(path)
    except Exception as e:
        print("✖ Feature extraction error:", e)
        return jsonify([])

    ranked = search_faiss_hybrid(qv, path, k=10)
    THRESH = 0.65
    filtered = [r for r in ranked if r[1] >= THRESH]
    if not filtered and ranked:
        filtered = [ranked[0]]

    resp = []
    for fn_key, score in filtered:
        meta   = metadata_dict.get(fn_key)
        unique = os.path.splitext(fn_key)[0]

        # fetch DB row safely
        try:
            conn = get_db_connection()
            cur  = conn.cursor()
            cur.execute(
                "SELECT Comic_Title, Country, Issue_Number FROM comics WHERE Unique_ID=%s LIMIT 1",
                (unique,)
            )
            row = cur.fetchone()
            cur.close(); conn.close()
        except:
            row = None

        # if fetchone returned None, replace with empty tuple
        if not row:
            row = (None, None, None)

        # handle tuple vs dict metadata
        if isinstance(meta, dict):
            img_path = meta.get("Image_Path", f"images/{fn_key}")
            title    = row[0] or meta.get("Comic_Title","")
            country  = row[1] or meta.get("Country","")
            issue    = row[2] or meta.get("Issue_Number","N/A")
        else:
            img_path = f"images/{fn_key}"
            title    = row[0] or ""
            country  = row[1] or ""
            issue    = row[2] or "N/A"

        entry = {
            "Image_Path":   img_path,
            "Comic_Title":  title,
            "Country":      country,
            "Issue_Number": issue,
            "distance":     round(float(score),4),
            "Unique_ID":    unique
        }
        resp.append(entry)

    return jsonify(resp)

# ——— /create_listing endpoint ——————————————————————————————————————————

@app.route("/create_listing", methods=["POST"])
def create_listing():
    data = request.get_json() or {}
    required = ["user_id", "price", "comic_condition", "graded", "comic_unique_id"]
    if any(field not in data for field in required):
        return jsonify({"error": "Missing fields"}), 400

    uid = data["comic_unique_id"].split(".")[0]
    conn = get_db_connection()
    cur  = conn.cursor(dictionary=True)
    cur.execute("""
      SELECT Comic_Title, Years, Issue_Number, Issue_URL, Image_Path
      FROM comics WHERE Unique_ID=%s LIMIT 1
    """, (uid,))
    comic = cur.fetchone() or {}
    cur.close()

    cur = conn.cursor()
    cur.execute("""
      INSERT INTO comics_for_sale
      (user_id, comic_title, years, issue_number, price, seller_currency,
       comic_condition, image_path, unique_id, Issue_URL, graded)
      VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)
    """, (
        data["user_id"],
        comic.get("Comic_Title",""),
        comic.get("Years",""),
        comic.get("Issue_Number",""),
        data["price"],
        data.get("seller_currency",""),
        data["comic_condition"],
        comic.get("Image_Path",""),
        uid,
        comic.get("Issue_URL",""),
        data["graded"]
    ))
    conn.commit()
    last = cur.lastrowid
    cur.close(); conn.close()

    return jsonify({"message":"Listing created","insert_id":last})

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)

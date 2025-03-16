from flask import Flask, request, jsonify
from flask_cors import CORS  # Enable CORS for cross-origin requests
import cv2
import numpy as np
import pyzbar.pyzbar as pyzbar
from pyzbar.wrapper import ZBarSymbol
import mysql.connector

# Database connection configuration
DB_CONFIG = {
    "host": "localhost",
    "user": "root",  # Change this if your MySQL user is different
    "password": "",  # Change if you have a MySQL password
    "database": "comics_db"
}

app = Flask(__name__)
CORS(app)  # Allow CORS for all routes

def get_comic_details(upc_code):
    """Queries the database to find comic details by UPC."""
    try:
        connection = mysql.connector.connect(**DB_CONFIG)
        cursor = connection.cursor(dictionary=True)

        # Updated query to include Image_Path
        query = "SELECT Comic_Title, Issue_Number, Image_Path FROM comics WHERE UPC = %s"
        cursor.execute(query, (upc_code,))
        result = cursor.fetchone()

        cursor.close()
        connection.close()

        return result if result else None
    except mysql.connector.Error as err:
        print(f"❌ Database Error: {err}")
        return None

def rotate_bound(image, angle):
    (h, w) = image.shape[:2]
    (cX, cY) = (w // 2, h // 2)
    M = cv2.getRotationMatrix2D((cX, cY), -angle, 1.0)
    cos = np.abs(M[0, 0])
    sin = np.abs(M[0, 1])
    nW = int((h * sin) + (w * cos))
    nH = int((h * cos) + (w * sin))
    M[0, 2] += (nW / 2) - cX
    M[1, 2] += (nH / 2) - cY
    return cv2.warpAffine(image, M, (nW, nH))

ean2_symbol = getattr(ZBarSymbol, 'EAN2', None)
symbols_list = [ZBarSymbol.EAN13, ZBarSymbol.EAN5]
if ean2_symbol is not None:
    symbols_list.append(ean2_symbol)

@app.route('/scan', methods=['POST'])
def scan_barcode():
    if 'image' not in request.files:
        return jsonify({"error": "No image uploaded"}), 400

    file = request.files['image']
    file_bytes = file.read()

    npimg = np.frombuffer(file_bytes, np.uint8)
    img = cv2.imdecode(npimg, cv2.IMREAD_COLOR)
    if img is None:
        return jsonify({"error": "Invalid image format"}), 400

    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

    methods = [
        ('Original', img),
        ('Grayscale', gray),
        ('Adaptive Threshold', cv2.adaptiveThreshold(gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C,
                                                       cv2.THRESH_BINARY, 11, 2)),
        ('Enhanced & Blurred', cv2.GaussianBlur(cv2.convertScaleAbs(gray, alpha=1.5, beta=10), (3, 3), 0))
    ]

    barcodes = []
    for _, method_img in methods:
        barcodes = pyzbar.decode(method_img, symbols=symbols_list)
        if barcodes:
            break

    if not barcodes:
        for angle in [90, 180, 270]:
            rotated = rotate_bound(gray, angle)
            barcodes = pyzbar.decode(rotated, symbols=symbols_list)
            if barcodes:
                break

    upc = None
    supplemental = None
    for barcode in barcodes:
        barcode_data = barcode.data.decode("utf-8").strip()
        barcode_type = barcode.type
        if barcode_type == "EAN13":
            upc = barcode_data
        elif barcode_type in ["EAN5", "EAN-5", "EAN2", "EAN-2"]:
            supplemental = barcode_data

    if not supplemental:
        extra_barcodes = pyzbar.decode(gray)
        for barcode in extra_barcodes:
            data = barcode.data.decode("utf-8").strip()
            if data.isdigit() and (len(data) in [2, 3, 5]) and (upc is None or data != upc):
                supplemental = data
                break

    # Remove one leading zero from UPC if it exists
    if upc and upc.startswith("0"):
        upc = upc[1:]
    
    full_code = f"{upc}-{supplemental if supplemental is not None else 'N/A'}" if upc else "N/A"

    # Query database for comic details
    comic_info = get_comic_details(full_code)

    # Process the Image_Path to ensure it points to the correct location.
    # Prepend the main folder name "comicsmp" if it's not already included.
    if comic_info and "Image_Path" in comic_info:
        image_path = comic_info["Image_Path"]
        if not image_path.startswith("/comicsmp/"):
            image_path = "/comicsmp/" + image_path.lstrip("/")
    else:
        image_path = "Not Found"

    # Return JSON response including comic details and image path
    result = {
        "upc": upc,
        "ean5": supplemental,
        "full_code": full_code,
        "comic_title": comic_info["Comic_Title"] if comic_info else "Not Found",
        "issue_number": comic_info["Issue_Number"] if comic_info else "Not Found",
        "Image_Path": image_path
    }

    return jsonify(result)

if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=True)

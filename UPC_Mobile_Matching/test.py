import cv2
import numpy as np
import pyzbar.pyzbar as pyzbar
from pyzbar.wrapper import ZBarSymbol

def scan_barcode(image_path):
    img = cv2.imread(image_path)

    if img is None:
        print(f"❌ Error: Unable to open image '{image_path}'. Check the file path.")
        return

    # Convert to grayscale
    gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)

    # Increase contrast (helps detect EAN-5)
    gray = cv2.convertScaleAbs(gray, alpha=2.0, beta=20)

    # Force detection of both EAN-13 and EAN-5
    barcodes = pyzbar.decode(gray, symbols=[ZBarSymbol.EAN13, ZBarSymbol.EAN5])

    upc = None
    ean5 = None

    for barcode in barcodes:
        barcode_data = barcode.data.decode("utf-8")
        barcode_type = barcode.type

        if barcode_type == "EAN13":
            upc = barcode_data
        elif barcode_type == "EAN5":
            ean5 = barcode_data

    if upc and ean5:
        print(f"✅ Full Comic Code: {upc} + {ean5}")
    elif upc:
        print(f"✅ UPC Code: {upc} (Waiting for EAN-5...)")
    else:
        print("❌ No barcode detected")

# Run the script with your image
image_path = r"C:\xampp6\htdocs\comicsmp\upc\try.jpg"
scan_barcode(image_path)

import os
import torch
import torchvision.models as models
import torchvision.transforms as transforms
from PIL import Image, ImageFilter
import numpy as np
import faiss
import pickle
from tqdm import tqdm  # ✅ Import tqdm for progress bar
import time

# -------------------- Model Setup --------------------
def load_model():
    # 🔥 Using EfficientNet-B7 for more discriminative features
    model = models.efficientnet_b7(weights=models.EfficientNet_B7_Weights.DEFAULT)
    model.classifier = torch.nn.Identity()  # Extracts 2560-d features (for B7)
    model.eval()
    return model

# Load model once (to avoid reloading inside loops)
model = load_model()

# -------------------- Image Processing --------------------
transform = transforms.Compose([
    transforms.Lambda(lambda img: img.filter(ImageFilter.SHARPEN)),  # 🔪 Sharpen image to emphasize details
    transforms.Resize((600, 600)),  # ⬆️ Increased resolution for better accuracy
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225]),
])

IMAGE_FOLDER = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "images"))
INDEX_FILE = os.path.abspath(os.path.join(os.path.dirname(__file__), "faiss_index_L2.bin"))
METADATA_FILE = os.path.abspath(os.path.join(os.path.dirname(__file__), "image_metadata.pkl"))

if not os.path.exists(IMAGE_FOLDER):
    print(f"❌ Image folder not found: {IMAGE_FOLDER}")
    exit(1)

# -------------------- Load Existing Index & Metadata --------------------
if os.path.exists(METADATA_FILE) and os.path.exists(INDEX_FILE):
    with open(METADATA_FILE, "rb") as f:
        processed_metadata = pickle.load(f)
    index = faiss.read_index(INDEX_FILE)
    # Extract set of processed filenames from metadata (each item is (filename, flag))
    processed_filenames = set([filename for filename, flag in processed_metadata])
    print(f"✅ Loaded existing index and metadata. {len(processed_filenames)} images already processed.")
else:
    print("❌ Existing metadata and/or FAISS index not found. Please run Script A first.")
    exit(1)

# -------------------- Feature Extraction --------------------
def extract_features(image_filename):
    """Extract a normalized 2560-d feature vector from an image using EfficientNet-B7."""
    image_path = os.path.join(IMAGE_FOLDER, image_filename)
    try:
        with Image.open(image_path) as img:  # ✅ Open safely
            img = img.convert("RGB")
            image = transform(img).unsqueeze(0)

        with torch.no_grad():
            features = model(image)

        features = features.numpy().flatten().copy()  # ✅ Avoid memory referencing issues
        norm = np.linalg.norm(features)
        if norm != 0:
            features /= norm
        
        return image_filename, features  # Return both filename and feature vector
    except (IOError, OSError, RuntimeError) as e:  # ✅ Handle corrupted images
        print(f"⚠️ Error processing {image_filename}: {e}")
        return image_filename, None

# -------------------- Determine New Images --------------------
all_image_files = [f for f in os.listdir(IMAGE_FOLDER) if f.lower().endswith(('.jpg', '.jpeg', '.png'))]
# Only process images that have not been processed (i.e. not in processed_filenames)
new_image_files = [f for f in all_image_files if f not in processed_filenames]

if not new_image_files:
    print("✅ No new images to process.")
    exit(0)

print(f"🔄 Processing {len(new_image_files)} new images...")

start_time = time.time()
results = []
for image_filename in tqdm(new_image_files, desc="📸 Extracting Features", unit="img"):
    results.append(extract_features(image_filename))

elapsed_time = time.time() - start_time
print(f"✅ Feature extraction for new images completed in {elapsed_time:.2f} seconds.")

# Remove failed extractions
new_filenames, features_list = zip(*[res for res in results if res[1] is not None])
new_features_matrix = np.array(features_list, dtype="float32")
print(f"✅ Extracted features for {len(new_features_matrix)} new images.")

# -------------------- FAISS Indexing --------------------
print("🔄 Adding new features to existing FAISS index...")
start_indexing_time = time.time()
index.add(new_features_matrix)
elapsed_indexing_time = time.time() - start_indexing_time
print(f"✅ FAISS index updated. Total images: {index.ntotal} (added {len(new_features_matrix)} new images in {elapsed_indexing_time:.2f} seconds).")

# -------------------- Update Metadata --------------------
# Append new images with flag 1 to the metadata
new_metadata = [(filename, 1) for filename in new_filenames]
processed_metadata.extend(new_metadata)

faiss.write_index(index, INDEX_FILE)
with open(METADATA_FILE, "wb") as f:
    pickle.dump(processed_metadata, f)

print(f"📂 FAISS index saved: {INDEX_FILE}")
print(f"📂 Updated metadata saved: {METADATA_FILE}")
print(f"🎉 Update complete! {len(new_filenames)} new images added.")


total_time = time.time() - start_time
print(f"🚀 Total processing time for new images: {total_time:.2f} seconds ({total_time / 60:.2f} minutes)")

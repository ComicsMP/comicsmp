import os
import torch
import torchvision.models as models
import torchvision.transforms as transforms
from PIL import Image
import numpy as np
import faiss
import pickle
from tqdm import tqdm  # ✅ Import tqdm for progress bar
import time

# -------------------- Model Setup --------------------
def load_model():
    model = models.efficientnet_b0(weights=models.EfficientNet_B0_Weights.DEFAULT)  # 🔥 EfficientNet-B0
    model.classifier = torch.nn.Identity()  # Extracts 1280-d features
    model.eval()
    return model

# Load model once (to avoid reloading inside loops)
model = load_model()

# -------------------- Image Processing --------------------
transform = transforms.Compose([
    transforms.Resize((300, 300)),  # ⬆️ Increased resolution for better accuracy
    transforms.ToTensor(),
    transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225]),
])

IMAGE_FOLDER = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "images"))
INDEX_FILE = os.path.abspath(os.path.join(os.path.dirname(__file__), "faiss_index_L2.bin"))
METADATA_FILE = os.path.abspath(os.path.join(os.path.dirname(__file__), "image_metadata.pkl"))

if not os.path.exists(IMAGE_FOLDER):
    print(f"❌ Image folder not found: {IMAGE_FOLDER}")
    exit(1)

# -------------------- Feature Extraction --------------------
def extract_features(image_filename):
    """Extract a normalized 1280-d feature vector from an image using EfficientNet."""
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

# -------------------- Sequential Execution --------------------
if __name__ == '__main__':
    image_files = [f for f in os.listdir(IMAGE_FOLDER) if f.lower().endswith(('.jpg', '.jpeg', '.png'))]
    if not image_files:
        print(f"⚠️ No images found in {IMAGE_FOLDER}")
        exit(1)

    print(f"🔄 Processing {len(image_files)} images (Single-Core Mode)...")

    start_time = time.time()
    
    results = []
    for image_filename in tqdm(image_files, desc="📸 Extracting Features", unit="img"):
        results.append(extract_features(image_filename))

    elapsed_time = time.time() - start_time
    print(f"✅ Feature extraction completed in {elapsed_time:.2f} seconds.")

    # Remove failed extractions
    metadata, features_list = zip(*[res for res in results if res[1] is not None])
    features_matrix = np.array(features_list, dtype="float32")
    print(f"✅ Extracted features for {len(features_matrix)} images.")

    # -------------------- FAISS Indexing --------------------
    d = 1280  # EfficientNet outputs 1280-d vectors

    index = faiss.IndexFlatL2(d)  # ✅ Uses L2 distance for best accuracy

    print("🔄 Adding features to FAISS index...")
    start_indexing_time = time.time()
    index.add(features_matrix)  
    elapsed_indexing_time = time.time() - start_indexing_time
    print(f"✅ FAISS index built with {index.ntotal} images in {elapsed_indexing_time:.2f} seconds.")

    # -------------------- Save FAISS Index & Metadata --------------------
    faiss.write_index(index, INDEX_FILE)
    with open(METADATA_FILE, "wb") as f:
        pickle.dump(metadata, f)

    print(f"📂 FAISS index saved: {INDEX_FILE}")
    print(f"📂 Metadata saved: {METADATA_FILE}")

    # -------------------- Final Stats --------------------
    total_time = time.time() - start_time
    print(f"🚀 Total processing time: {total_time:.2f} seconds ({total_time / 60:.2f} minutes)")

    # -------------------- Re-Rank Matches for Higher Precision --------------------
    def search_faiss(query_features, k=5):
        """Find the top-k most similar images."""
        D, I = index.search(np.array([query_features], dtype="float32"), k)
        return I[0], D[0]  # Return indices & distances

    print("🔍 Testing FAISS with first image...")
    test_image = features_matrix[0]
    indices, distances = search_faiss(test_image, k=5)

    print("\n🎯 Top Matches:")
    for rank, (idx, dist) in enumerate(zip(indices, distances)):
        print(f"🏆 Rank {rank+1}: {metadata[idx]} (L2 Distance: {dist:.4f})")

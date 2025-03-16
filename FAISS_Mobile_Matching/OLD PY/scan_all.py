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
    processed_filenames, features_list = zip(*[res for res in results if res[1] is not None])
    # -------------------- Flag Recording --------------------
    # Create a flag system where each processed image is recorded with a flag of 1
    processed_metadata = [(filename, 1) for filename in processed_filenames]
    
    features_matrix = np.array(features_list, dtype="float32")
    print(f"✅ Extracted features for {len(features_matrix)} images.")

    # -------------------- FAISS Indexing --------------------
    d = 2560  # EfficientNet-B7 outputs 2560-d vectors

    index = faiss.IndexFlatL2(d)  # ✅ Uses L2 distance for best accuracy

    print("🔄 Adding features to FAISS index...")
    start_indexing_time = time.time()
    index.add(features_matrix)  
    elapsed_indexing_time = time.time() - start_indexing_time
    print(f"✅ FAISS index built with {index.ntotal} images in {elapsed_indexing_time:.2f} seconds.")

    # -------------------- Save FAISS Index & Metadata --------------------
    faiss.write_index(index, INDEX_FILE)
    with open(METADATA_FILE, "wb") as f:
        pickle.dump(processed_metadata, f)

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
        # processed_metadata is a tuple (filename, flag)
        print(f"🏆 Rank {rank+1}: {processed_metadata[idx][0]} (L2 Distance: {dist:.4f})")

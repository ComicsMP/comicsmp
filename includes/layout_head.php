<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - ComicsMP</title>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">

  <style>
    /*–– style the range input so its “fill” follows the thumb exactly ––*/
  #distanceSlider {
    --range-fill: 0%;
    background: transparent;
  }
  /* WebKit track: light gray, with a blue gradient up to the thumb */
  #distanceSlider::-webkit-slider-runnable-track {
    height: 6px;
    background: #dee2e6;
    border-radius: 3px;
    background-image:
      linear-gradient(
        to right,
        #0d6efd var(--range-fill),
        transparent var(--range-fill)
      );
    background-repeat: no-repeat;
  }
  /* WebKit thumb: a blue circle centered on the track */
  #distanceSlider::-webkit-slider-thumb {
    -webkit-appearance: none;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #0d6efd;
    margin-top: -5px;
  }

  /* Firefox track and progress */
  #distanceSlider::-moz-range-track {
    height: 6px;
    background: #dee2e6;
    border-radius: 3px;
  }
  #distanceSlider::-moz-range-progress {
    background: #0d6efd;
    height: 6px;
    border-radius: 3px;
  }
  .position-relative { position: relative; }
  .position-absolute { position: absolute; }
  #loading {
  display: none;
  font-size: 20px;
  font-weight: bold;
  color: white;
  background-color: rgba(0, 0, 0, 0.85);
  padding: 40px;
  text-align: center;
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  z-index: 9998;
}
#loading span {
  display: inline-block;
  font-size: 24px;
  animation: pulse 1.5s infinite;
}
#loading span:nth-child(2) { animation-delay: 0.2s; }
#loading span:nth-child(3) { animation-delay: 0.4s; }

@keyframes pulse {
  0%, 100% { opacity: 1; transform: scale(1); }
  50% { opacity: 0.3; transform: scale(1.2); }
}

#distanceSlider::-moz-range-thumb {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #0d6efd;
    border: none;
  }
    body { font-family: 'Roboto', sans-serif; background: #f0f2f5; color: #333; }
    a { text-decoration: none; color: inherit; }

    .header {
      background: #1a1a1a; color: #fff; padding: 1rem 2rem;
      display: flex; justify-content: space-between; align-items: center;
    }
    .header h1 { font-size: 2.5rem; margin: 0; }
    .header-icons a { text-decoration: none; }
    .dropdown .avatar {
      width: 40px; height: 40px; border-radius: 50%; object-fit: cover; margin-right: 10px;
      border: 2px solid #fff;
    }
    .dropdown-toggle {
      color: #fff !important; background: none; border: none;
      display: flex; align-items: center;
    }
    .dropdown-menu {
      right: 0; left: auto;
    }

    .filter-row {
  display: flex;
  justify-content: space-between;
  flex-wrap: wrap;
  align-items: center;
}

.custom-lightbox-close {
  position: absolute;
  top: 10px;
  right: 10px;
  z-index: 1055;
  width: 40px;
  height: 40px;
  background-color: white;
  color: red;
  font-size: 24px;
  font-weight: bold;
  border: none;
  border-radius: 50%;
  text-align: center;
  line-height: 40px;
  cursor: pointer;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.3);
}

.stat-text {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-size: 0.85rem;
  display: inline-block;
  max-width: 100%;
}

.card-body .stat-text {
  font-size: clamp(0.7rem, 0.9vw, 0.85rem); /* scales down slightly if needed */
}

.extra-favorite {
  display: none;
}

#favoritesList {
  list-style: none;
  padding-left: 0;
}

#suggestionsPanel .suggestion-item {
  cursor: pointer;
  padding: .5rem;
  border-bottom: 1px solid #eee;
}
#suggestionsPanel .suggestion-item:hover {
  background-color: #f8f9fa;
}


.filter-group {
  display: flex;
  flex-wrap: wrap;
  gap: 1rem;
  align-items: center;
}

.distance-slider {
  width: 300px; /* wider than default but still neat */
}

input[type=range]{
  -webkit-appearance:none;
  height:8px;
  background:linear-gradient(to right,#007BFF 0%,#007BFF var(--value,50%),#ddd var(--value,50%),#ddd 100%);
  border-radius:5px;
  outline:none;
}
input[type=range]::-webkit-slider-thumb{
  -webkit-appearance:none;
  width:20px;
  height:20px;
  background:#007BFF;
  border-radius:50%;
  cursor:pointer;
  border:2px solid #fff;
}

#upcResultModal .modal-dialog {
  max-width: 400px;
}


@media (max-width: 768px) {
  .distance-slider {
    width: 100%; /* full width on mobile for responsiveness */
  }
}

/* Ensure range input track is clearly visible */
input[type="range"].form-range::-webkit-slider-runnable-track {
  height: 2px;
  background: #eee;
  border-radius: 0px;
}

input[type="range"].form-range::-moz-range-track {
  height: 2px;
  background: #eee;
  border-radius: 0px;
}

input[type="range"].form-range::-ms-track {
  height: 2px;
  background: #eee;
  border-radius: 0px;
  border-color: transparent;
  color: transparent;
}

.favorite-title-btn.favorited .favorite-icon {
  color: #e60073;
  text-shadow: 0 0 4px #ff80bf;
}


.favorite-title-btn.favorited {
  background-color: #ffc0cb;
  border-color: #ff9aa2;
  color: #a10000;
  opacity: 0.85;
}
.favorite-title-btn.favorited:hover {
  opacity: 1;
}



.main-container { display: flex; margin-top: 1rem; }
    .sidebar {
      width: 220px; background-color: #333; color: #fff; min-height: 100vh; padding: 20px;
    }
    .sidebar h2 { font-size: 1.4rem; margin-bottom: 1.5rem; text-align: center; }
    .sidebar .nav-link {
      color: #fff; margin-bottom: 0.5rem; padding: 0.5rem 0.8rem; border-radius: 4px; cursor: pointer;
    }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #575757; }
    .main-content { flex: 1; padding: 20px; background-color: #f8f9fa; min-height: 100vh; }
    .offcanvas-header { background: #1a1a1a; color: #fff; }
    .offcanvas-body { padding: 1rem; }
    .advanced-search .modern-input {
      border: 2px solid #ccc; border-radius: 5px; padding: 0.75rem 1rem; font-size: 1.1rem;
      width: 100%; outline: none; transition: border-color 0.3s ease; margin-bottom: 1rem;
    }
    .advanced-search .modern-input:focus { border-color: #007bff; }
    .advanced-search .search-mode-group { margin-bottom: 1rem; display: flex; gap: 5px; justify-content: center; }
    .advanced-search .search-mode-group .btn { flex: 1; font-size: 0.9rem; padding: 0.5rem; }
    .advanced-search .filter-group { margin-bottom: 1rem; }
    .advanced-search .filter-group label { font-weight: 500; }
    .advanced-search .filter-group select {
      border-radius: 5px; border: 1px solid #ccc; padding: 0.5rem; width: 100%; margin-top: 0.5rem;
    }
    .search-input-container { position: relative; }
    #suggestions {
      position: absolute; top: 100%; left: 0; right: 0; background: #fff;
      border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;
      max-height: 250px; overflow-y: auto; z-index: 100;
    }
    #suggestions .suggestion-item { padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s ease; }
    #suggestions .suggestion-item:hover { background: #f7f7f7; }

    :root { --covers-per-row: 8; --gap: 15px; }
    .gallery { display: flex; flex-wrap: wrap; gap: var(--gap); margin-top: 1.5rem; }
    .gallery-item {
      width: calc((100% - (var(--covers-per-row) - 1) * var(--gap)) / var(--covers-per-row));
      min-height: 350px; background: #fafafa; border: 1px solid #ddd;
      border-radius: 8px; padding: 0.5rem; text-align: center; position: relative;
      transition: transform 0.3s ease, box-shadow 0.3s ease; cursor: pointer;
    }
    .gallery-item:hover { transform: translateY(-3px); box-shadow: 0 3px 10px rgba(0,0,0,0.12); }
    .gallery-item img { width: 100%; height: 250px; object-fit: contain; border-radius: 5px; background: #fff; }
    .button-wrapper { display: flex; justify-content: center; gap: 10px; margin-top: 0.5rem; }
    .button-wrapper button { padding: 0.4rem 0.8rem; font-size: 0.9rem; }
    .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(0,0,0,.05); }
    .expand-row { background-color: #f1f1f1; }
    .cover-container { display: flex; flex-wrap: wrap; justify-content: flex-start; }
    .nested-table thead { background-color: #eee; }
    @media (max-width: 992px) { .gallery-item { width: calc((100% - (var(--covers-per-row) - 1) * var(--gap)) / var(--covers-per-row)); } }
    @media (max-width: 768px) {
      .main-container { flex-direction: column; }
      .sidebar { margin-right: 0; margin-bottom: 1rem; }
      .gallery-item { width: calc(33.33% - var(--gap)); }
      .main-content { padding: 10px; }
    }
    @media (max-width: 576px) { .gallery-item { width: calc(50% - var(--gap)); } }
    @media (max-width: 400px) { .gallery-item { width: 100%; } }
    .popup-modal-body { display: flex; gap: 20px; flex-wrap: wrap; }
    .popup-image-container { flex: 0 0 40%; display: flex; align-items: center; justify-content: center; }
    .popup-image-container img { max-width: 100%; max-height: 350px; object-fit: contain; cursor: pointer; border-radius: 5px; }
    .popup-details-container { flex: 1; }
    .popup-details-container table { font-size: 1rem; }
    .similar-issues { margin-top: 20px; }
    .similar-issue-thumb { width: 80px; height: 120px; margin: 5px; object-fit: cover; cursor: pointer; }
    #showAllSimilarIssues { text-align: right; width: 100%; cursor: pointer; color: blue; margin-top: 5px; font-size: 0.9rem; }
  </style>
</head>
<body>

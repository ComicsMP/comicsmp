<meta charset="UTF-8">
<title>Dashboard - ComicsMP</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<style>
  /* Base Styles */
  body { font-family: 'Roboto', sans-serif; background: #f0f2f5; color: #333; }
  a { text-decoration: none; color: inherit; }
  /* Header */
  .header { background: #1a1a1a; color: #fff; padding: 1rem 0; text-align: center; }
  .header h1 { font-size: 2.5rem; margin: 0; }
  /* Layout */
  .main-container { display: flex; margin-top: 1rem; }
  /* Sidebar Navigation */
  .sidebar {
    width: 220px; background-color: #333; color: #fff; min-height: 100vh; padding: 20px;
  }
  .sidebar h2 { font-size: 1.4rem; margin-bottom: 1.5rem; text-align: center; }
  .sidebar .nav-link {
    color: #fff; margin-bottom: 0.5rem; padding: 0.5rem 0.8rem; border-radius: 4px; cursor: pointer;
  }
  .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #575757; }
  /* Main Content */
  .main-content { flex: 1; padding: 20px; background-color: #f8f9fa; min-height: 100vh; }
  /* Offcanvas for Search Filters */
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
  /* Auto-Suggest */
  .search-input-container { position: relative; }
  #suggestions {
    position: absolute; top: 100%; left: 0; right: 0; background: #fff;
    border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;
    max-height: 250px; overflow-y: auto; z-index: 100;
  }
  #suggestions .suggestion-item { padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s ease; }
  #suggestions .suggestion-item:hover { background: #f7f7f7; }
  /* Gallery: 8 covers per row (adjustable) */
  :root {
    --covers-per-row: 8;
    --gap: 15px;
  }
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
  /* Table styles for Wanted, Sale, Matches */
  .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(0,0,0,.05); }
  .expand-row { background-color: #f1f1f1; }
  .cover-container { display: flex; flex-wrap: wrap; justify-content: center; }
  .nested-table thead { background-color: #eee; }
  /* Responsive */
  @media (max-width: 992px) { .gallery-item { width: calc((100% - (var(--covers-per-row) - 1) * var(--gap)) / var(--covers-per-row)); } }
  @media (max-width: 768px) {
    .main-container { flex-direction: column; }
    .sidebar { margin-right: 0; margin-bottom: 1rem; }
    .gallery-item { width: calc(33.33% - var(--gap)); }
    .main-content { padding: 10px; }
  }
  @media (max-width: 576px) { .gallery-item { width: calc(50% - var(--gap)); } }
  @media (max-width: 400px) { .gallery-item { width: 100%; } }
  /* Modal Styles */
  .popup-modal-body { display: flex; gap: 20px; flex-wrap: wrap; }
  .popup-image-container { flex: 0 0 40%; display: flex; align-items: center; justify-content: center; }
  .popup-image-container img { max-width: 100%; max-height: 350px; object-fit: contain; cursor: pointer; border-radius: 5px; }
  .popup-details-container { flex: 1; }
  .popup-details-container table { font-size: 1rem; }
  .similar-issues { margin-top: 20px; }
  .similar-issue-thumb { width: 80px; height: 120px; margin: 5px; object-fit: cover; cursor: pointer; }
  #showAllSimilarIssues { text-align: right; width: 100%; cursor: pointer; color: blue; margin-top: 5px; font-size: 0.9rem; }
</style>

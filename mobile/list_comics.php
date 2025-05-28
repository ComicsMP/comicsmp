<?php
session_start();
require_once 'db_connection.php';
if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  echo "Forbidden";
  exit;
}
$userId = $_SESSION['user_id'];
// Count total comics in DB
$totalComics = 0;
$result = $conn->query("SELECT COUNT(ID) AS total FROM comics");
if ($result && $row = $result->fetch_assoc()) {
  $totalComics = $row['total'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport"
        content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>List Comics</title>
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    * { box-sizing: border-box; margin:0; padding:0 }
    body { font-family:'Roboto',sans-serif; background:#f4f4f4; color:#333; padding-bottom:60px }
    a { text-decoration:none; color:inherit }

    /* Header */
    .header-row { display: flex; justify-content: space-between; align-items: center; background: #007BFF; padding: 12px 16px; color: #fff; }
    .header-row .back-icon { font-size:24px; color:#fff; margin-right:10px; cursor:pointer }
    .header-row h2 { color:#fff; font-size:18px; margin:0 }

    /* Top Tabs */
    .tabs { display:flex; background:#fff }
    .tabs .tab-btn {
  flex: 1;
  padding: 12px 10px;
  border: none;
  background: #eee;
  font-size: 15px;
  font-weight: 500;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tabs .tab-btn.active {
  background: #007BFF;
  color: #fff;
}
#issueControls select {
  font-size: 1rem;
  min-height: 40px;
}

#issueControls .form-check-label {
  font-size: 0.95rem;
}

#loading {
  display: none;
  text-align: center;
  margin-top: 1.5rem;
  font-size: 1.25rem;
  color: #555;
}

.loader-dots {
  display: inline-flex;
  gap: 4px;
}

.loader-dots span {
  display: inline-block;
  width: 10px;
  height: 10px;
  background-color: #007BFF;
  border-radius: 50%;
  animation: bounce 1.2s infinite ease-in-out;
}

.loader-dots span:nth-child(2) {
  animation-delay: 0.2s;
}
.loader-dots span:nth-child(3) {
  animation-delay: 0.4s;
}

@keyframes bounce {
  0%, 80%, 100% {
    transform: scale(0.9);
    opacity: 0.6;
  }
  40% {
    transform: scale(1.4);
    opacity: 1;
  }
}


  .match-image-box {
  background: none !important;
  margin: 0 !important;
  padding: 0 !important;
  border: none !important;
  height: auto !important;
  display: block !important;
}

#listingSection .match-image-box {
  display: flex !important;
  justify-content: center;
  align-items: center;
}


#listingSection .match-image-box img {
  margin-top: 0 !important;
  margin-bottom: 0 !important;
  max-height: 240px;
  height: auto;
}



  .match-standard {
    width: 160px;
    height: auto;
    max-height: 240px;
    object-fit: contain;
    border: 1px solid #ccc;
    border-radius: 4px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
  }


.tabs .tab-btn i {
  font-size: 18px;
  margin-right: 6px;
}

/* Uniform sizing for cover matches */
#cover .match-image-box {
  width: 100%;
  height: 260px;
  display: flex;
  align-items: center;
  justify-content: center;
  overflow: hidden;
  background: #f8f8f8;
}

#cover match-image-box img {
  width: 100% !important;
  height: auto !important;
  display: block !important;
  object-fit: contain !important;
  margin: 0 !important;
  padding: 0 !important;
  background: none !important;
}





    /* Content Panes */
    .tab-content { display:none; padding:15px; background:#fff }
#favorites.tab-content {
  padding-left: 0 !important;
  padding-right: 0 !important;
}


    .tab-content.active { display:block }

    /* Manual form */
    .card.manual-card { border-radius:12px; box-shadow:0 2px 6px rgba(0,0,0,0.1) }
    #suggestions .suggestion-item {
      padding:0.5rem 0.75rem; border:1px solid #ccc;
      background:#fff; cursor:pointer;
    }
    #suggestions .suggestion-item:hover { background:#e9ecef }

    #tabsContainer .nav-item {
      margin: 2px;
    }


    /* Series Header (center title + favorites) */
    #seriesHeader {
  text-align: center;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  margin-bottom: 0.10rem; /* reduced from 0.75rem */
  padding: 0.3rem 0;      /* slightly reduced padding if needed */
}



#controls {
  display: none;
  margin-top: 0.3rem;  /* reduce default spacing above controls */
  margin-bottom: 1rem;
}

    #controls .nav-tabs { flex-wrap:wrap; }
    #controls .nav-link.active { background:#007BFF; color:#fff; }
    /* center dropdown + toggle */
    #controls .d-flex.align-items-center {
      justify-content:center;
    }

    #issueControls {
       display: none;
    }


    /* Gallery */
    #coversContainer {
      display:grid; gap:0.5rem;
      grid-template-columns:repeat(auto-fill,minmax(120px,1fr));
    }
    .covers-title {
  grid-column: 1 / -1;
  text-align: center;
  font-size: 1.3rem;
  font-weight: 600;
  margin: 1rem 0 0.75rem 0; /* Adds more space above, slight space below */
  color: #333;
}

    .gallery-item {
      background:#fff; border-radius:8px; padding:0.5rem;
      text-align:center; box-shadow:0 1px 4px rgba(0,0,0,0.1);
    }
    .gallery-item img { width:100%; height:180px; object-fit:contain; border-radius:4px; display:block; }

    .gallery-item .btn {
      font-size:0.8rem; padding:0.35rem; margin-top:0.3rem; width:100%;
    }
    .btn-wanted { background:#28a745; color:#fff; border:none }
    .btn-sell   { background:#007bff; color:#fff; border:none }

    /* Footer */
.bottom-nav {
  position: fixed;
  bottom: 0;
  left: 0;
  right: 0;
  height: 56px;
  background: #000;
  display: flex;
  justify-content: space-around;
  align-items: center;
  z-index: 100;
}

/* Apply spacing to Step 2 and Step 3 sections only */
  #uploadSection,
  #listingSection {
    margin-top: 2.50rem;
  }

  /* Keep Step 1 (bulk options) flush with progress bar */
  #bulkOptionsSection {
    margin-top: 0;
  }

.bottom-nav a {
  flex: 1;
  color: #fff;
  text-decoration: none;
  font-size: 12px;
  text-align: center;
  line-height: 1.2;
}
.bottom-nav a i {
  display: block;
  font-size: 18px;
  line-height: 1;
  margin-bottom: 1px;
}

.series-header-box {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  text-align: center !important;
  width: 100%;
  margin-bottom: 0rem !important;
  padding-bottom: 0rem !important;
}

.series-header-box h3,
.series-header-box p {
  margin: 0 !important;
  padding: 0 !important;
}

#controls {
  margin-top: 0.25rem !important;
}

/* === Cover-tab styles (scoped under #cover) === */
  #cover .container {
    margin: 20px auto;
    width: 90%;
    max-width: 90vw; /* 90% of the viewport width */
    background: white;
    padding: 15px;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
  }
  #cover .section { display: none; }
  #cover .section.active { display: block; }

  /* Progress Indicator */
  #cover #progressContainer {
    margin-bottom: 15px;
  }
  #cover #progressText {
    font-size: 16px;
    margin-bottom: 5px;
  }
  #cover .progress-bar {
    width: 0%;
    height: 10px;
    background-color: #007BFF;
    border-radius: 5px;
    transition: width 0.3s ease;
  }

  /* Buttons & Inputs */
  #cover button,
  #cover input[type=file],
  #cover input[type=text],
  #cover input[type=password],
  #cover select {
    width: 100%;
    padding: 12px;
    margin: 10px 0;
    font-size: 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    display: block;
  }
  #cover .btn-group {
    display: flex;
    justify-content: center;
    gap: 10px;
  }
  #cover .btn-group button { flex: 1; }
  #cover button { background-color: #007BFF; color: white; }
  #cover button:active { background-color: #0056b3; }

  /* Specific Colors */
  #cover .green-button { background-color: green; }
  #cover .red-button   { background-color: red; }
  #cover .black-button { background-color: black; }
  #cover .blue-button  { background-color: blue; }

  /* Hide raw file‐inputs */
  #cover #cameraInput,
  #cover #fileInput {
    display: none;
  }

  /* Loading indicator */
  #cover #loading {
    display: none;
    font-size: 18px;
    font-weight: bold;
    color: #007BFF;
  }
  #cover #loading span {
    display: inline-block;
    animation: dots 1.5s infinite;
  }
  @keyframes dots {
    0%   { opacity: 1; }
    50%  { opacity: 0.3; }
    100% { opacity: 1; }
  }

  /* Utility for full-width images */
  #cover .full-width-image {
    width: 100% !important;
    height: auto  !important;
    display: block;
  }

/* --- FAISS thumbnail sizing for the Cover tab --- */
#cover .container .comic-result img,
#cover .container .candidate img,
#cover .container .listing-cover img {
  width: 100%;
  object-fit: contain;
}

#cover .container .single-candidate img {
  height: 250px;
  object-fit: contain;
}

.candidate-container {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
  justify-items: center;
}

.candidate {
  max-width: 150px;
  width: 100%;
  background: none;
  padding: 0;
  margin: 0;
  box-shadow: none;
}

@media (min-width: 768px) {
  .candidate {
    max-width: 160px;
  }
}



@media (max-width: 400px) {
  .candidate-container {
    grid-template-columns: repeat(2, 1fr);
  }
}


.candidate img {
  width: 100%;
  height: auto;
  display: block;
  object-fit: contain;
  margin: 0;
  padding: 0;
}

/* make sure your “full-width” class still works here */
#cover .container .full-width-image {
  width: 100% !important;
  height: auto  !important;
  display: block !important;
}

/* ————————————————————————————————
   Step 4 Listing Form: force label + input side-by-side always
   ———————————————————————————————— */
#cover .details-table {
  display: flex;
  flex-direction: column;
  gap: 1.5rem;
  width: 100%;
  max-width: 480px;
  margin: 2.5rem auto 1.5rem;  /* ✅ This sets top, sides, and bottom margins */
  padding: 0;
}

/* make every row a flex row, not a column */
#cover .details-table tr {
  display: flex;
  flex-direction: row;        /* force side-by-side */
  align-items: center;        /* vertical centering */
  gap: 1rem;                  /* horizontal space between label & field */
}

/* extra space above the pricing row only */
#cover .details-table tr:last-child {
  margin-top: 0.5rem;  /* or 1rem for more space */
}

/* label cell (left) */
#cover .details-table .label {
  flex: 0 0 30%;              /* fixed 30% width */
  text-align: left;
  padding-right: 0;
  font-weight: 500;
  font-size: 0.95rem;
}

/* input cell (right) */
#cover .details-table .input {
  flex: 1;                    /* take remaining 70% */
}

/* all selects & inputs span 100% of their cell */
#cover .details-table select,
#cover .details-table input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ccc;
  border-radius: 6px;
  font-size: 1rem;
  box-sizing: border-box;
  height: 3rem;           /* same fixed height for dropdowns & inputs */
  line-height: 1;         /* ensure text is vertically centered */
}

/* price group stays the same */
#cover .price-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  width: 100%;
}
#cover .price-group span {
  font-size: 1rem;
}
#cover .price-group input {
  flex: 1;
}

/* no more “@media” stacking needed – rows always stay side-by-side */



  </style>
</head>
<body>
 <div class="header-row d-flex justify-content-between align-items-center">
  <div class="text-white" style="font-size: 16px; padding-left: 8px;">
    <i class="bi bi-book-fill me-1"></i><?= number_format($totalComics) ?> issues
  </div>
  <div class="text-white me-2" style="font-size: 16px; cursor: pointer;" onclick="loadFavorites()">
    <i class="bi bi-star-fill me-1"></i>Favorites
  </div>
</div>




  <div class="tabs">
    <button class="tab-btn active" data-target="manual">
      <i class="bi bi-keyboard-fill"></i>Manual
    </button>
    <button class="tab-btn" data-target="cover">
      <i class="bi bi-card-image"></i>Cover
    </button>
    <button class="tab-btn" data-target="barcode">
      <i class="bi bi-upc-scan"></i>UPC
    </button>
  </div>

  <!-- Manual Tab -->
  <div id="manual" class="tab-content active">
    <div class="border rounded bg-white shadow-sm p-3 mb-3" style="border-left: 4px solid #007BFF;">
      <form id="manualAddForm" autocomplete="off">
        <h5 class="mb-3" style="font-weight: 600; color: #007BFF;">Search by Title</h5>
        
        <div class="mb-3">
          <label class="form-label" for="comicSearch">Comic Title</label>
          <input type="text" id="comicSearch" class="form-control" placeholder="Start typing…">
          <div id="suggestions" class="mt-1"></div>
        </div>
    
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label" for="countrySelect">Country</label>
            <select id="countrySelect" class="form-select">
              
            </select>
          </div>
          <div class="col-6">
            <label class="form-label" for="yearSelect">Year</label>
            <select id="yearSelect" class="form-select">
              <!-- Options will be loaded via JavaScript -->
            </select>
          </div>
        </div>
    
        <button type="button" id="searchCoversBtn" class="btn btn-primary w-100">
          <i class="bi bi-search"></i> Search Covers
        </button>
      </form>
    </div>
  
    <!-- SERIES HEADER -->
    <div id="seriesHeader"></div>
  
    <!-- Sub-tabs & controls -->
    <div id="controls">
      <ul id="tabsContainer" class="nav nav-tabs flex-row flex-wrap justify-content-start mb-2"></ul>
      <div id="issueControls" class="w-100 px-2 mt-2">
        <div class="row g-2 align-items-center">
          <div class="col">
            <select id="issueFilterSelect" class="form-select form-select-md">
              <option value="">All Issues</option>
            </select>
          </div>
          <div class="col-auto d-flex align-items-center">
            <div class="form-check form-switch mb-0">
              <input class="form-check-input" type="checkbox" id="includeVariantsCheckbox" style="transform: scale(1.1);">
              <label class="form-check-label ms-1 small" for="includeVariantsCheckbox">Variants</label>
            </div>
          </div>
        </div>
      </div>
    </div>
  
    <!-- Covers Grid -->
    <div id="coversContainer"></div>
  </div>
  <!-- /Manual Tab -->
  
  <!-- Cover Tab -->
  <div id="cover" class="tab-content">
    <?php include __DIR__ . '/covers_partial.php'; ?>
  </div>
  <!-- /Cover Tab -->
  
  <!-- Barcode Tab -->
  <div id="barcode" class="tab-content"></div>
  <!-- /Barcode Tab -->
<!-- Favorites Pseudo-Tab -->
<div id="favorites" class="tab-content" style="display: none;">
  <div id="favoritesContainer" class="p-3"></div>
</div>



  <!-- Bottom Navigation -->
  <div class="bottom-nav">
    <a href="dashboard.php"><i class="bi bi-house-fill"></i>Home</a>
    <a href="wanted.php"><i class="bi bi-heart-fill"></i>Wanted</a>
    <a href="selling.php"><i class="bi bi-tag-fill"></i>Selling</a>
    <a href="matches.php"><i class="bi bi-people-fill"></i>Matches</a>
  </div>

  <!-- jQuery + Bootstrap JS -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
    // Top-level tabs
    document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    inFavoritesMode = false; // ✅ Exiting favorites mode

    // Hide all tab content
    document.querySelectorAll('.tab-content').forEach(p => {
      p.classList.remove('active');
      p.style.display = 'none';
    });

    // Activate clicked tab and show it
    const target = document.getElementById(btn.dataset.target);
    target.classList.add('active');
    target.style.display = 'block';

    // Highlight tab button
    document.querySelectorAll('.tab-btn').forEach(x => x.classList.remove('active'));
    btn.classList.add('active');
  });
});



    $(function(){
    let inFavoritesMode = false;

  let currentTab = 'All',
      includeVariants = localStorage.getItem('includeVariants') === 'true',
      currentIssueFilter = '',
      availableTabs = [],
      limit = 50, offset = 0,
      loading = false, moreToLoad = true;

  // Favorite shortcut click: apply favorite title/year and trigger full search
// Exit favorites mode and clean up its section
$(document).on('click', '.apply-fav-search', function () {
  inFavoritesMode = false;
  $('#favorites').removeClass('active').hide();
  $('#favoritesContainer').empty();

  const title = $(this).data('title');
  const year = $(this).data('year');
  const country = $(this).data('country') || 'USA';

  $('#comicSearch').val(title);
  $('#yearSelect').val(year);
  $('#countrySelect').val(country);

  $('#suggestions').slideUp().empty();
  $('#manualAddForm').closest('.border').show();
  $('#seriesHeader,#tabsContainer,#coversContainer').empty();

  currentTab = 'All';
  currentIssueFilter = '';
  availableTabs = [];
  offset = 0;
  moreToLoad = true;

  $.get('/comicsmp/getCountries.php', { comic_title: title }, function(options) {
    $('#countrySelect').html(options);
    $('#countrySelect').val(country);

    $.get('/comicsmp/getYears.php', {
      comic_title: title,
      country: country,
      include_variants: includeVariants ? 1 : 0
    }, function(yearOptions) {
      $('#yearSelect').html('<option value="" selected disabled>Select Year</option>' + yearOptions);
      $('#yearSelect').val(year);

      $.get('/comicsmp/getIssues.php', {
        comic_title: title,
        year: year,
        include_variants: includeVariants ? 1 : 0
      }, function (options) {
        $('#issueFilterSelect').html('<option value="">All Issues</option>' + options);

        // ✅ Show Manual tab and run search
        $('.tab-btn').removeClass('active');
        $('.tab-btn[data-target="manual"]').addClass('active');
        $('.tab-content').removeClass('active').hide();
        $('#manual').addClass('active').show();

        loadCovers();
      });
    });
  });
});



  // ✅ This now ensures includeVariants is declared before .apply-fav-search



      // Variant toggle persistence
      $('#includeVariantsCheckbox').prop('checked', includeVariants);
      $('#includeVariantsCheckbox').on('change', function(){
  includeVariants = this.checked;
  localStorage.setItem('includeVariants', includeVariants);

  const selectedIssue = $('#issueFilterSelect').val(); // Store current selection

  // Reload dropdown and reapply issue filter if still available
  $.get('/comicsmp/getIssues.php', {
    comic_title: $('#comicSearch').val(),
    year: $('#yearSelect').val(),
    include_variants: includeVariants ? 1 : 0
  }, function (options) {
    $('#issueFilterSelect').html('<option value="">All Issues</option>' + options);

    // Re-select issue if it's still present
    if (selectedIssue) {
      $('#issueFilterSelect').val(selectedIssue);
      currentIssueFilter = selectedIssue;
    } else {
      currentIssueFilter = '';
    }

    offset = 0;
    moreToLoad = true;
    $('#coversContainer').empty();
    loadCovers();
  });
});


      // Suggestions
      $('#comicSearch').on('input', function(){
        const q = this.value;
        if (q.length < 2) return $('#suggestions').empty();
        $.get('/comicsmp/suggest.php',{q},html=>{
          $('#suggestions').html(html).slideDown();
        });
      });
      $(document).on('click','.suggestion-item',function(){
  const selectedTitle = this.textContent;
  $('#comicSearch').val(selectedTitle);
  $('#suggestions').slideUp().empty();

  // Load dynamic countries for this title
  $.get('/comicsmp/getCountries.php', { comic_title: selectedTitle }, function(options){
  $('#countrySelect').html(options);

  // Check if USA exists in the results
  const hasUSA = options.includes("value='USA'") || options.includes('value="USA"');

  if (hasUSA) {
    $('#countrySelect').val('USA');
  } else {
    $('#countrySelect').prepend('<option value="" selected disabled>Select Country</option>');
    $('#countrySelect').val('');
  }

  // Trigger year loading after country is selected (or left blank)
  loadYears();
});


  // Then load years based on default country (after user selects)
  loadYears();
});


      function loadYears(){
        $.get('/comicsmp/getYears.php',{
          comic_title: $('#comicSearch').val(),
          country: $('#countrySelect').val(),
          include_variants: includeVariants ? 1 : 0
        }, options=>{
          $('#yearSelect').html('<option value="" selected disabled>Select Year</option>' + options);
        });
      }
      $('#countrySelect').on('change', loadYears);

      // Search covers
$('#searchCoversBtn').on('click', function(){
  const title = $('#comicSearch').val(), yr = $('#yearSelect').val();
  if (!title || !yr) return alert('Please select both title and year.');

  // ✅ Fully exit Favorites mode and hide that section
  inFavoritesMode = false;
  $('#favorites').removeClass('active').hide();
  $('#favoritesContainer').empty();

  // ✅ Switch to Manual tab
  $('.tab-btn').removeClass('active');
  $('.tab-btn[data-target="manual"]').addClass('active');

  $('.tab-content').removeClass('active').hide();
  $('#manual').addClass('active').show();

  // ✅ Now reset and run manual search
  currentTab = 'All';
  $('#issueControls').hide();
  currentIssueFilter = '';
  availableTabs = [];
  offset = 0;
  moreToLoad = true;
  $('#seriesHeader,#tabsContainer,#coversContainer').empty();
  loadCovers();
});




      // Always include “All” plus other tabs
     // Always include “All” plus other tabs
function renderTabs() {
  let nav = '<li class="nav-item"><a class="nav-link' + (currentTab === 'All' ? ' active' : '') + '" data-tab="All">All</a></li>';
  availableTabs.forEach(t => {
    nav += '<li class="nav-item"><a class="nav-link' + (t === currentTab ? ' active' : '') + '" data-tab="' + t + '">' + t + '</a></li>';
  });
  $('#tabsContainer').html(nav);

  $('#controls').show();
  if (currentTab.toLowerCase() === 'issues') {
    $('#issueControls').show();
  } else {
    $('#issueControls').hide();
  }
}





      // Load covers
      function loadCovers() {
  if (loading || !moreToLoad) return;
  loading = true;

  // Only show controls if “Issues” tab
  if (currentTab.toLowerCase() === 'issues') {
    $('#issueControls').show();
  } else {
    $('#issueControls').hide();
  }

  $('#controls').fadeIn();
  if (offset === 0) {
    $('#coversContainer').html('Loading covers…');
  } else {
    $('#coversContainer').append('<div id="loadingMore">Loading more…</div>');
  }

  $.ajax({
    url: '/comicsmp/searchResults.php',
    data: {
      comic_title: $('#comicSearch').val(),
      year: $('#yearSelect').val(),
      include_variants: includeVariants ? 1 : 0,
      tab: currentTab,
      issue_number: currentIssueFilter,
      limit, offset
    },
    dataType: 'html'
  })
  .done(html => {
    $('#loadingMore').remove();
    const $tmp = $('<div>').append(html),
          items = $tmp.find('.gallery-item');

    if (offset === 0) {
      $('#seriesHeader').html($tmp.find('.series-header-box'));
      $('#coversContainer').html('<div class="covers-title">Results</div>').append(items);

      let tabsSet = new Set(items.map((i, el) => $(el).data('tab')).get());
      availableTabs = Array.from(tabsSet);
      renderTabs();
    } else {
      $('#coversContainer').append(items);
    }

    let cnt = items.length;
    offset += cnt;
    if (cnt < limit) moreToLoad = false;
    loading = false;
  })
  .fail(() => {
    $('#loadingMore').remove();
    $('#coversContainer').append('<p class="text-danger">Error loading covers.</p>');
    loading = false;
  });
}


      // Infinite scroll
      $(window).on('scroll', function(){
  if (inFavoritesMode || !moreToLoad || loading) return;

        if ($(window).scrollTop() + $(window).height() + 100 >= $(document).height()) {
          loadCovers();
        }
      });

      // Sub-tab click
      $('#tabsContainer').on('click', '.nav-link', function () {
  currentTab = $(this).data('tab');
  $('#issueControls').hide(); // default hidden
  if ($(this).data('tab').toLowerCase() === 'issues') {
    $('#issueControls').show();
  }

  $('#tabsContainer .nav-link').removeClass('active');
  $(this).addClass('active');
  offset = 0;
  moreToLoad = true;
  currentIssueFilter = '';
  $('#coversContainer').empty();

  // ✅ Refresh the issue dropdown based on the new tab
  $.get('/comicsmp/getIssues.php', {
    comic_title: $('#comicSearch').val(),
    year: $('#yearSelect').val(),
    include_variants: includeVariants ? 1 : 0
  }, function (options) {
    $('#issueFilterSelect').html('<option value="">All Issues</option>' + options);
    loadCovers(); // Load covers only after dropdown is updated
  });
});

$(document).on('change', '#issueFilterSelect', function () {
  currentIssueFilter = this.value || '';
  offset = 0;
  moreToLoad = true;
  $('#coversContainer').empty();
  loadCovers(); // This respects tab, variant toggle, and now issue #
});



      // Add to Wanted
      $(document).on('click','.add-to-wanted',function(){
  const $btn = $(this), $it = $btn.closest('.gallery-item'),
        p = {
          comic_title:  $it.data('comicTitle')  || $it.data('comic-title'),
          issue_number: $it.data('issueNumber') || $it.data('issue-number'),
          years:        $it.data('year')        || $it.data('years')
        };
  if (!p.comic_title || !p.issue_number || !p.years) {
    return alert('Error: missing fields on the item');
  }

        $btn.prop('disabled',true).text('Processing…');
        $.post('/comicsmp/addToWanted.php', p, res=>{
          if (res.status==='success') $btn.removeClass('btn-secondary').addClass('btn-success').text('Added');
          else { alert(res.message||'Error'); $btn.prop('disabled',false).text('Wanted'); }
        }, 'json').fail(()=>{
          alert('Network error');
          $btn.prop('disabled',false).text('Wanted');
        });
      });

      // Sell button & modal logic
      let _pendingSell = null;
      $(document).on('click','.sell-button',function(){
  _pendingSell = $(this).closest('.gallery-item');
  $('#editSaleForm')[0].reset();
  $('#editSaleForm').find('input[name="comic_title"],input[name="issue_number"],input[name="years"],input[name="issue_url"]').remove();

  const title = _pendingSell.data('comicTitle') || _pendingSell.data('comic-title');
  const number = _pendingSell.data('issueNumber') || _pendingSell.data('issue-number');
  const year = _pendingSell.data('year') || _pendingSell.data('years');
  const url = _pendingSell.data('issueUrl') || _pendingSell.data('issue-url');

 $('#editSaleForm').append(`
  <input type="hidden" name="comic_title"  value="${title}">
  <input type="hidden" name="issue_number" value="${number}">
  <input type="hidden" name="years"        value="${year}">
  <input type="hidden" name="issue_url"    value="${url}">
`);

  new bootstrap.Modal(document.getElementById('editSaleModal')).show();
});


      $(document).on('submit','#editSaleForm',function(e){
        e.preventDefault();
        $.post('/comicsmp/addListing.php', $(this).serialize(), resp=>{
          if (resp.status==='success') {
            _pendingSell.find('.sell-button')
              .removeClass('btn-secondary').addClass('btn-success')
              .text('Listed').prop('disabled',true);
            bootstrap.Modal.getInstance(document.getElementById('editSaleModal')).hide();
          } else {
            alert(resp.message||'Error listing');
          }
        }, 'json').fail(()=>{
          alert('Server error while adding listing');
        });
      });
    });

     // Lazy load cover/barcode panes
  function loadPane(target){
    $(`#${target}`).load(`/comicsmp/mobile/${target}_comic.php`);
  }

  // Click any comic image to open full-size preview
  $(document).on('click', '.gallery-item img, .match-image-box img, .candidate img', function() {
    const src = $(this).attr('src');
    $('#modalCoverImage').attr('src', src);
    const modal = new bootstrap.Modal(document.getElementById('coverModal'));
    modal.show();
  });

    function loadFavorites() {
    inFavoritesMode = true;

    // Hide all real tabs and tab contents
    $('.tab-btn').removeClass('active');
    $('.tab-content').removeClass('active').hide();

    // Show only the Favorites section
    $('#favorites').addClass('active').show();
    $('#favoritesContainer').html('<p class="text-center py-4">Loading favorites…</p>');

    // Load Favorites HTML
    $.get('/comicsmp/getFavorites.php', function(html) {
      $('#favoritesContainer').html(html);
    });
  }

  // ✅ Lazy-load Barcode tab content
  document.querySelector('.tab-btn[data-target="barcode"]').addEventListener('click', () => {
  const barcodeTab = document.getElementById('barcode');
  if (!barcodeTab.classList.contains('loaded')) {
  $('#barcode').load('/comicsmp/mobile/barcode_comic.php', function () {
    initBarcodeScanner();
    barcodeTab.classList.add('loaded'); // ✅ mark as loaded
  });
}

});








  




  </script>



  <!-- include shared modals -->
  <?php include __DIR__ . '/../includes/Modals.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Cover Preview Modal -->
<div class="modal fade" id="coverModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content position-relative">
      <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
      <div class="modal-body text-center p-2">
        <img id="modalCoverImage" src="" class="img-fluid rounded" style="max-height:80vh;" alt="Comic Cover">
      </div>
    </div>
  </div>
</div>

<script>
// Favorite button logic (duplicated from searchResults.php)
$(document).on('click', '.favorite-title-btn', function () {
  const $btn        = $(this);
  const title       = $btn.data('comic-title');
  const years       = $btn.data('year');
  const country     = $('#countrySelect').val() || 'USA'; // Fallback to USA
  const isFavorited = $btn.hasClass('favorited');

  $.post('/comicsmp/toggleFavoriteTitle.php', {
    comic_title: title,
    years:       years,
    country:     country,
    action:      isFavorited ? 'remove' : 'add'
  }, function(response) {
    if (response.status === 'success') {
      $btn.toggleClass('favorited');
      $btn.find('.favorite-icon')
          .text(isFavorited ? '❤️' : '💖');
    } else {
      alert(response.message || 'Failed to update favorites.');
    }
  }, 'json');
});
</script>

<script src="https://unpkg.com/@zxing/library@latest"></script>
<script>
function initBarcodeScanner() {
  const codeReader = new ZXing.BrowserMultiFormatReader();
  const videoElement = document.getElementById('video');
  const resultElement = document.getElementById('result');
  const restartBtn = document.getElementById('restartScan');

  let selectedDeviceId;
  let scanQueue = [];
  let processing = false;
  let stableUPC = "", upcCount = 0;
  const MIN_CONSECUTIVE_DETECTIONS = 3;

  codeReader.listVideoInputDevices()
    .then((devices) => {
      if (devices.length > 0) {
        selectedDeviceId = devices[devices.length - 1].deviceId;
        startScanner();
      } else {
        resultElement.innerHTML = "No camera devices found.";
      }
    })
    .catch((err) => {
      console.error(err);
      resultElement.innerHTML = "Error listing video devices: " + err;
    });

  function startScanner() {
    const hints = new Map();
    hints.set(ZXing.DecodeHintType.POSSIBLE_FORMATS, [
      ZXing.BarcodeFormat.UPC_A,
      ZXing.BarcodeFormat.EAN_5
    ]);

    codeReader.decodeFromVideoDevice(selectedDeviceId, 'video', (result, err) => {
      if (result) {
        scanQueue.push(result);
        processQueue();
      } else if (err && !(err instanceof ZXing.NotFoundException)) {
        console.error(err);
        resultElement.innerHTML = "Error: " + err;
      }
    }, hints);
  }

  function processQueue() {
    if (processing || scanQueue.length === 0) return;
    processing = true;

    let upc = null, supplemental = null, processedIndices = [];
    for (let i = 0; i < scanQueue.length; i++) {
      const result = scanQueue[i];
      if (result.format === ZXing.BarcodeFormat.UPC_A && !upc) {
        upc = result.text;
        processedIndices.push(i);
      } else if (result.format === ZXing.BarcodeFormat.EAN_5 && !supplemental) {
        supplemental = result.text;
        processedIndices.push(i);
      }
    }

    processedIndices.sort((a, b) => b - a).forEach(i => scanQueue.splice(i, 1));

    if (upc) {
      if (stableUPC === upc) {
        upcCount++;
      } else {
        stableUPC = upc;
        upcCount = 1;
      }

      if (upcCount >= MIN_CONSECUTIVE_DETECTIONS) {
        resultElement.innerHTML = `<strong>Barcode Found:</strong> ${upc}${supplemental ? ' - ' + supplemental : ''}`;
        setTimeout(() => {
          captureSnapshotAndSend();
          codeReader.reset();
          scanQueue = [];
          restartBtn.style.display = "block";
          stableUPC = "";
          upcCount = 0;
        }, 500);
      }
    }

    processing = false;
    if (scanQueue.length > 0) processQueue();
  }

  function captureSnapshotAndSend() {
    const canvas = document.createElement("canvas");
    canvas.width = videoElement.videoWidth;
    canvas.height = videoElement.videoHeight;
    const ctx = canvas.getContext("2d");
    ctx.drawImage(videoElement, 0, 0, canvas.width, canvas.height);

    canvas.toBlob(function (blob) {
      const formData = new FormData();
      formData.append('image', blob, 'barcode.jpg');
      fetch('http://192.168.86.68:5000/scan', {
        method: 'POST',
        body: formData
      })
        .then(res => res.json())
        .then(data => {
          console.log("Server Response:", data);
          resultElement.innerHTML = `
            <strong>Barcode:</strong> ${data.full_code}<br>
            <strong>Comic:</strong> ${data.comic_title}<br>
            <strong>Issue:</strong> ${data.issue_number}
          `;
        })
        .catch(err => {
          console.error("Error sending image:", err);
          resultElement.innerHTML += "<br>❌ Server Error. Try again!";
        });
    }, 'image/jpeg');
  }

  restartBtn.addEventListener('click', () => {
    resultElement.innerHTML = "Waiting for barcode scan…";
    codeReader.reset();
    scanQueue = [];
    restartBtn.style.display = "none";
    stableUPC = "";
    upcCount = 0;
    startScanner();
  });
}
</script>

</body>
</html>
<?php
session_start();
require_once 'db_connection.php';

// Query distinct countries from the comics table
$query = "SELECT DISTINCT Country FROM comics ORDER BY Country ASC";
$result = mysqli_query($conn, $query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Comic Search</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    /* Global styles for a modern, readable look */
    .form-control, .form-select, .btn {
      font-size: 1.1rem;
      padding: 0.5rem 0.75rem;
    }
    /* Card layout for search controls */
    .search-card {
      border: 1px solid #ddd;
      border-radius: 5px;
      margin-bottom: 1rem;
      background-color: #fff;
      box-shadow: 0 0 5px rgba(0,0,0,0.1);
    }
    .search-card .card-header {
      font-weight: bold;
      text-align: center;
      background-color: #f8f9fa;
    }
    .search-card .card-body {
      padding: 1rem;
    }
    /* Search input container with absolute suggestions */
    .search-input-container {
      position: relative;
    }
    #suggestions {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      z-index: 1000;
      background: #fff;
      border: 1px solid #ddd;
      border-top: none;
      border-radius: 0 0 5px 5px;
      box-shadow: 0px 4px 8px rgba(0,0,0,0.1);
      max-height: 300px;
      overflow-y: auto;
    }
    #suggestions .suggestion-item {
      padding: 0.75rem;
      cursor: pointer;
      transition: background-color 0.2s ease;
      border-bottom: 1px solid #eee;
    }
    #suggestions .suggestion-item:last-child {
      border-bottom: none;
    }
    #suggestions .suggestion-item:hover {
      background-color: #f1f1f1;
    }
    /* Filter controls row inside the card */
    .filter-controls {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      align-items: flex-end;
      margin-top: 1rem;
    }
    .filter-controls > div {
      flex: 1;
      min-width: 120px;
    }
    /* Gallery for results */
    .gallery {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin-top: 20px;
    }
    .gallery-item {
      width: calc(16.66% - 15px);
      min-height: 400px;
      border: 1px solid #ddd;
      background-color: #f9f9f9;
      padding: 10px;
      border-radius: 5px;
      text-align: center;
      position: relative;
    }
    .gallery-item img {
      width: 100%;
      height: 270px;
      object-fit: cover;
      border-radius: 5px;
      margin-bottom: 5px;
      cursor: pointer;
    }
    .info-row {
      text-align: center;
      margin-top: 5px;
    }
    .button-wrapper {
      display: flex;
      justify-content: center;
      gap: 10px;
      flex-wrap: nowrap;
    }
    /* Responsive adjustments */
    @media (max-width: 768px) {
      .gallery-item { width: calc(50% - 15px); }
    }
    @media (max-width: 480px) {
      .gallery-item { width: 100%; }
    }
    /* New Popup Modal Styles */
    .popup-modal-body {
      display: flex;
      gap: 20px;
    }
    .popup-image-container {
      flex: 0 0 40%;
      display: flex;
      align-items: flex-start;
      justify-content: center;
    }
    .popup-image-container img {
      max-width: 100%;
      max-height: 350px;
      object-fit: contain;
      cursor: pointer;
    }
    .popup-details-container {
      flex: 1;
    }
    .popup-details-container table {
      font-size: 1rem;
    }
    .similar-issues {
      margin-top: 20px;
    }
    .similar-issue-thumb {
      width: 80px;
      height: 120px;
      margin: 5px;
      object-fit: cover;
      cursor: pointer;
    }
    /* Style for the clickable "Show All Similar Issues" link */
    #showAllSimilarIssues {
      text-align: right;
      width: 100%;
      cursor: pointer;
      color: blue;
      margin-top: 5px;
      font-size: 0.9rem;
    }
    /* Optional: Blue border for graded comics */
    .graded-cover .cover-img {
      border: 3px solid blue;
    }
  </style>
</head>
<body class="bg-light">
<div class="container my-4">
  <h1 class="text-center mb-4">Search for Comics</h1>
  
  <!-- Search Card -->
  <div class="search-card shadow-sm">
    <div class="card-header text-center">Search Comics</div>
    <div class="card-body">
      <!-- Top Row: Country Dropdown, Comic Title Input & Filter Mode Buttons -->
      <div class="row align-items-end">
        <div class="col-md-3">
          <label for="countrySelect" class="form-label">Country</label>
          <select id="countrySelect" class="form-select">
            <?php
              if ($result) {
                while ($row = mysqli_fetch_assoc($result)) {
                  $country = $row['Country'];
                  $selected = ($country == "USA") ? "selected" : "";
                  echo "<option value=\"$country\" $selected>$country</option>";
                }
              }
            ?>
          </select>
        </div>
        <div class="col-md-5 search-input-container">
          <label for="comicTitle" class="form-label">Comic Title</label>
          <input type="text" id="comicTitle" class="form-control" placeholder="Start typing..." autocomplete="off">
          <div id="suggestions"></div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Filter</label>
          <div class="btn-group w-100" role="group" id="searchModeGroup">
            <button type="button" class="btn btn-outline-primary search-mode" data-mode="allWords">All Words</button>
            <button type="button" class="btn btn-outline-primary search-mode" data-mode="anyWords">Any Words</button>
            <button type="button" class="btn btn-outline-primary search-mode active" data-mode="startsWith">Starts With</button>
          </div>
        </div>
      </div>
      
      <!-- Bottom Row: Additional Filters -->
      <div class="filter-controls mt-3" id="filterRow" style="display: none;">
        <div>
          <label for="yearSelect" class="form-label">Year</label>
          <select id="yearSelect" class="form-select">
            <option value="">Select a year</option>
          </select>
        </div>
        <div>
          <label for="tabSelect" class="form-label">Tab</label>
          <select id="tabSelect" class="form-select">
            <option value="All" selected>All</option>
            <option value="Issues">Issues</option>
            <!-- other tab options will be loaded dynamically based on the selected year -->
          </select>
        </div>
        <!-- Wrap the Number field in its own container (only for Issues) -->
        <div id="issueCol" style="display: none;">
          <label for="issueSelect" class="form-label">Number</label>
          <select id="issueSelect" class="form-select">
            <option value="All">All</option>
          </select>
        </div>
        <!-- Variants toggle (only visible when Tab is not All) -->
        <div id="variantDiv">
          <label class="form-label">Variants</label>
          <div>
            <button type="button" class="btn btn-outline-primary" id="variantToggle" data-enabled="0">Include Variants</button>
          </div>
        </div>
      </div>
      <!-- (Search button removed – live updates occur automatically) -->
    </div>
  </div>
  <!-- End Search Card -->

  <!-- Results Section -->
  <h2 class="mt-4">Results</h2>
  <div id="resultsGallery" class="gallery"></div>
</div>

<!-- Existing Image Modal (unchanged) -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="imageModalLabel">Comic Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <img id="modalFullImage" src="" class="img-fluid mb-3" alt="Comic Full Image">
        <div id="modalComicDetails">
          <p><strong>Comic Title:</strong> <span id="modalComicTitle"></span></p>
          <p><strong>Years:</strong> <span id="modalYears"></span></p>
          <p><strong>Issue Number:</strong> <span id="modalIssueNumber"></span></p>
          <p><strong>Tab:</strong> <span id="modalTab"></span></p>
          <p><strong>Variant:</strong> <span id="modalVariant"></span></p>
          <p><strong>Date:</strong> <span id="modalDate"></span></p>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- New Popup Modal for Cover Image with Details & Similar Issues -->
<div class="modal fade" id="coverPopupModal" tabindex="-1" aria-labelledby="coverPopupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="coverPopupModalLabel">Comic Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body popup-modal-body">
        <!-- Left Column: Large Cover Image -->
        <div class="popup-image-container">
          <img id="popupMainImage" src="" alt="Comic Cover">
        </div>
        <!-- Right Column: Details & Similar Issues -->
        <div class="popup-details-container">
          <table class="table table-sm">
            <tr>
              <th>Comic Title:</th>
              <td id="popupComicTitle"></td>
            </tr>
            <tr>
              <th>Years:</th>
              <td id="popupYears"></td>
            </tr>
            <tr>
              <th>Issue Number:</th>
              <td id="popupIssueNumber"></td>
            </tr>
            <tr>
              <th>Tab:</th>
              <td id="popupTab"></td>
            </tr>
            <tr>
              <th>Variant:</th>
              <td id="popupVariant"></td>
            </tr>
            <tr>
              <th>Date:</th>
              <td id="popupDate"></td>
            </tr>
            <tr>
              <th>UPC:</th>
              <td id="popupUPC"></td>
            </tr>
          </table>
          <div class="similar-issues">
            <h6>Similar Issues</h6>
            <div id="similarIssues" class="d-flex flex-wrap"></div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  let searchMode = "startsWith";
  let autoSuggestRequest = null; // Holds current auto-suggest AJAX request

  // Function to perform live search
  function performSearch() {
    const comicTitle = $("#comicTitle").val();
    const year = $("#yearSelect").val();
    const params = {
      comic_title: comicTitle,
      tab: $("#tabSelect").val(),
      issue_number: $("#issueSelect").val(),
      include_variants: $("#variantToggle").attr("data-enabled") === "1" ? 1 : 0,
      mode: searchMode,
      year: year,
      country: $("#countrySelect").val()
    };
    $.ajax({
      url: "searchResults.php",
      method: "GET",
      data: params,
      success: function(html) {
        $("#resultsGallery").html(html);
        $(".gallery-item").each(function() {
          const $item = $(this);
          $item.find(".button-wrapper").remove();
          $item.find(".issue-info").remove();
          const comicTitle = $item.data("comic-title");
          const issueNumber = $item.data("issue-number");
          const seriesYear = $item.data("years");
          const issueUrl = $item.data("issue_url");
          const date = $item.attr("data-date") || "N/A";
          $item.attr("data-date", date);
          
          let wantedBtn = $(`<button class="btn btn-primary add-to-wanted me-2 mb-2" style="margin-right:5px;">Wanted</button>`)
            .attr("data-series-name", comicTitle)
            .attr("data-issue-number", issueNumber)
            .attr("data-series-year", seriesYear)
            .attr("data-issue-url", issueUrl);
          if ($item.data("wanted") == 1) {
            wantedBtn = $(`<button class="btn btn-success add-to-wanted me-2 mb-2" style="margin-right:5px;" disabled>Added</button>`);
          }
          let sellBtn = $(`<button class="btn btn-secondary sell-button me-2 mb-2" style="margin-right:5px;">Sell</button>`);
          const buttonWrapper = $('<div class="button-wrapper"></div>');
          buttonWrapper.append(wantedBtn).append(sellBtn);
          $item.append(buttonWrapper);
          $item.find(".volume-info").remove();
          const sellFormHtml = `
            <div class="sell-form" style="display: none;">
              <form class="sell-comic-form">
                <div class="mb-2">
                  <label>Condition:</label>
                  <select name="condition" class="form-select" required>
                    <option value="">Select Condition</option>
                    <option value="10">10</option>
                    <option value="9.9">9.9</option>
                    <option value="9.8">9.8</option>
                    <option value="9.6">9.6</option>
                    <option value="9.4">9.4</option>
                    <option value="9.2">9.2</option>
                    <option value="9.0">9.0</option>
                    <option value="8.5">8.5</option>
                    <option value="8.0">8.0</option>
                    <option value="7.5">7.5</option>
                    <option value="7.0">7.0</option>
                    <option value="6.5">6.5</option>
                    <option value="6.0">6.0</option>
                    <option value="5.5">5.5</option>
                    <option value="5.0">5.0</option>
                    <option value="4.5">4.5</option>
                    <option value="4.0">4.0</option>
                    <option value="3.5">3.5</option>
                    <option value="3.0">3.0</option>
                    <option value="2.5">2.5</option>
                    <option value="2.0">2.0</option>
                    <option value="1.8">1.8</option>
                    <option value="1.5">1.5</option>
                    <option value="1.0">1.0</option>
                    <option value="0.5">0.5</option>
                  </select>
                </div>
                <div class="mb-2">
                  <label>Graded:</label>
                  <select name="graded" class="form-select" required>
                    <option value="0" selected>Not Graded</option>
                    <option value="1">Graded</option>
                  </select>
                </div>
                <div class="mb-2">
                  <label>Price:</label>
                  <input type="number" name="price" class="form-control" required>
                </div>
                <input type="hidden" name="comic_title" value="${comicTitle}">
                <input type="hidden" name="issue_number" value="${issueNumber}">
                <input type="hidden" name="years" value="${seriesYear}">
                <input type="hidden" name="issue_url" value="${issueUrl}">
                <button type="submit" class="btn btn-success">Submit Listing</button>
              </form>
            </div>
          `;
          $item.append(sellFormHtml);
        });
      },
      error: function() {
        $("#resultsGallery").html("<p class='text-danger'>Error loading results.</p>");
      }
    });
  }
  
  // SEARCH MODE BUTTONS
  $(".search-mode").on("click", function() {
    if (autoSuggestRequest) { autoSuggestRequest.abort(); }
    $(".search-mode").removeClass("active");
    $(this).addClass("active");
    searchMode = $(this).data("mode");
    $("#suggestions").html("");
    performSearch();
  });

  // AUTO-SUGGEST (including country in the request)
  $("#comicTitle").on("input", function() {
    const val = $(this).val();
    if (val.length > 2) {
      if (autoSuggestRequest) { autoSuggestRequest.abort(); }
      autoSuggestRequest = $.ajax({
        url: "suggest.php",
        method: "GET",
        data: { q: val, mode: searchMode, country: $("#countrySelect").val() },
        success: function(data) { $("#suggestions").html(data); },
        error: function() { $("#suggestions").html("<p class='text-danger'>No suggestions available.</p>"); },
        complete: function() { autoSuggestRequest = null; }
      });
      performSearch();
    } else {
      $("#suggestions").html("");
      $("#resultsGallery").html("");
    }
  });

  $(document).on("click", ".suggestion-item", function() {
    const title = $(this).text();
    $("#comicTitle").val(title);
    $("#suggestions").html("");
    $.get("getYears.php", { comic_title: title, country: $("#countrySelect").val() }, function(data) {
      $("#yearSelect").html('<option value="">Select a year</option>' + data);
      performSearch();
    });
    // Load tabs based on comic title, country (and later selected year)
    $.get("getTabs.php", { comic_title: title, country: $("#countrySelect").val() }, function(data) {
      $("#tabSelect").html(data);
      $("#tabSelect").val("All");
      performSearch();
    });
    $("#filterRow").show();
  });

  // When year selection changes, update the tabs based on comic title, year, and country.
  $("#yearSelect").on("change", function(){
    performSearch();
    const selectedYear = $(this).val();
    const comicTitle = $("#comicTitle").val();
    if(comicTitle && selectedYear){
      $.get("getTabs.php", { comic_title: comicTitle, year: selectedYear, country: $("#countrySelect").val() }, function(data){
        $("#tabSelect").html(data);
        if($("#tabSelect option[value='All']").length > 0){
          $("#tabSelect").val("All");
        } else {
          $("#tabSelect").prop("selectedIndex", 0);
        }
        performSearch();
      });
    }
  });

  // FILTERING for Tab and Country changes
  $("#tabSelect, #countrySelect").on("change", function(){
    if($("#tabSelect").val() === "Issues"){
      $("#issueCol").show();
      $("#variantDiv").show();
      loadMainIssues();
    } else {
      $("#issueCol").hide();
      $("#issueSelect").html("");
      if($("#tabSelect").val() === "All"){
        $("#variantDiv").hide();
      } else {
        $("#variantDiv").show();
      }
      performSearch();
    }
  });

  $("#issueSelect").on("change", function(){ performSearch(); });

  function loadMainIssues() {
    const comicTitle = $("#comicTitle").val();
    const year = $("#yearSelect").val();
    const includeVariants = $("#variantToggle").attr("data-enabled") === "1" ? 1 : 0;
    const params = { comic_title: comicTitle, only_main: 1, include_variants: includeVariants, year: year, country: $("#countrySelect").val() };
    $.get("getIssues.php", params, function(data) {
      $("#issueSelect").html(data);
      performSearch();
    });
  }

  // Toggle button for Include Variants
  $("#variantToggle").on("click", function() {
    let enabled = $(this).attr("data-enabled") === "1" ? 0 : 1;
    $(this).attr("data-enabled", enabled);
    if(enabled == 1){
      $(this).removeClass("btn-outline-primary").addClass("btn-primary");
    } else {
      $(this).removeClass("btn-primary").addClass("btn-outline-primary");
    }
    if($("#tabSelect").val() === "Issues"){
      loadMainIssues();
    } else {
      performSearch();
    }
  });

  // ADD TO WANTED HANDLER
  $(document).on("click", ".add-to-wanted", function(e) {
    e.preventDefault();
    const btn = $(this);
    if (btn.is(":disabled")) return;
    const comicTitle = btn.data("series-name");
    const issueNumber = btn.data("issue-number");
    const seriesYear = btn.data("series-year");
    const tab = btn.data("tab") || "";
    const variant = btn.data("variant") || "";
    const issueUrl = btn.data("issue-url") || "";
    $.ajax({
      url: "addToWanted.php",
      method: "POST",
      data: { comic_title: comicTitle, issue_number: issueNumber, years: seriesYear, tab: tab, variant: variant, issue_url: issueUrl },
      success: function(response) {
        btn.replaceWith('<button class="btn btn-success add-to-wanted me-2 mb-2" disabled>Added</button>');
      },
      error: function() { alert("Error adding comic to wanted list"); }
    });
  });

  // SELL FORM HANDLERS
  $(document).on("click", ".sell-button", function(e) {
    e.preventDefault();
    $(this).closest(".gallery-item").find(".sell-form").slideToggle();
  });

  $(document).on("submit", ".sell-comic-form", function(e) {
    e.preventDefault();
    const form = $(this);
    $.ajax({
      url: "addListing.php",
      method: "POST",
      data: form.serialize(),
      success: function(response) {
        form.closest(".sell-form").html('<div class="alert alert-success">Listed for Sale</div>');
      },
      error: function() { alert("Error listing comic for sale"); }
    });
  });

  // Function to load similar issues via AJAX
  function loadSimilarIssues(comicTitle, years, issueNumber, loadAll) {
    let requestData = { comic_title: comicTitle, years: years, issue_number: issueNumber };
    if (loadAll) { requestData.limit = "all"; }
    $.ajax({
      url: "getSimilarIssues.php",
      method: "GET",
      data: requestData,
      success: function(similarHtml) {
        if (!loadAll) { similarHtml += "<div id='showAllSimilarIssues'>Show All Similar Issues</div>"; }
        $("#similarIssues").html(similarHtml);
      },
      error: function() { $("#similarIssues").html("<p class='text-danger'>Could not load similar issues.</p>"); }
    });
  }

  // Popup Modal for Cover Image with Details & Similar Issues
  $(document).on("click", ".gallery-item img", function(e) {
    if ($(e.target).closest("button").length) return;
    const parent = $(this).closest(".gallery-item");
    const fullImageUrl = $(this).attr("src");
    const comicTitle = parent.data("comic-title") || "N/A";
    const years = parent.data("years") || "N/A";
    const issueNumber = parent.data("issue-number") || "N/A";
    const tab = parent.data("tab") || "N/A";
    const variant = parent.data("variant") || "N/A";
    const date = parent.attr("data-date") || "N/A";
    
    $("#popupMainImage").attr("src", fullImageUrl);
    $("#popupComicTitle").text(comicTitle);
    $("#popupYears").text(years);
    $("#popupIssueNumber").text(issueNumber);
    $("#popupTab").text(tab);
    $("#popupVariant").text(variant);
    $("#popupDate").text(date);
    const upc = parent.data("upc") || "N/A"; 
    $("#popupUPC").text(upc);
    
    loadSimilarIssues(comicTitle, years, issueNumber, false);
    const modal = new bootstrap.Modal(document.getElementById("coverPopupModal"));
    modal.show();
  });

  // Handler for "Show All Similar Issues"
  $(document).on("click", "#showAllSimilarIssues", function() {
    const comicTitle = $("#popupComicTitle").text() || "";
    const years = $("#popupYears").text() || "";
    const issueNumber = $("#popupIssueNumber").text() || "";
    loadSimilarIssues(comicTitle, years, issueNumber, true);
  });

  // When a similar issue thumbnail is clicked, update the popup details
  $(document).on("click", ".similar-issue-thumb", function() {
    const thumb = $(this);
    const comicTitle = thumb.data("comic-title") || "N/A";
    const years = thumb.data("years") || "N/A";
    const issueNumber = thumb.data("issue-number") || "N/A";
    const tab = thumb.data("tab") || "N/A";
    const variant = thumb.data("variant") || "N/A";
    const date = thumb.data("date") || "N/A";
    const upc = thumb.data("upc") || "N/A";
    
    $("#popupMainImage").attr("src", thumb.attr("src"));
    $("#popupComicTitle").text(comicTitle);
    $("#popupYears").text(years);
    $("#popupIssueNumber").text(issueNumber);
    $("#popupTab").text(tab);
    $("#popupVariant").text(variant);
    $("#popupDate").text(date);
    $("#popupUPC").text(upc);
  });

  // Allow clicking the main popup image to open full-size in a new window
  $(document).on("click", "#popupMainImage", function() {
    const src = $(this).attr("src");
    if(src) { window.open(src, '_blank'); }
  });
});
</script>
</body>
</html>

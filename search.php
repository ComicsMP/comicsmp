<?php
session_start();
require_once 'db_connection.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Comic Search</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    .filter-row { margin-bottom: 20px; }
    .gallery { display: flex; flex-wrap: wrap; gap: 15px; margin-top: 20px; }
    .gallery-item {
      width: calc(16.66% - 15px);
      min-height: 400px;
      border: 1px solid #ddd;
      background-color: #f9f9f9;
      position: relative;
      padding: 10px;
      border-radius: 5px;
      text-align: center;
    }
    .gallery-item img {
      width: 100%;
      height: 270px;
      object-fit: cover;
      border-radius: 5px;
      margin-bottom: 5px;
      cursor: pointer;
    }
    .gallery-item button {
      border-radius: 5px;
    }
    #suggestions .suggestion-item {
      border: 1px solid #ddd;
      padding: 8px;
      border-radius: 5px;
      background-color: #fff;
      cursor: pointer;
      margin-bottom: 5px;
    }
    #suggestions .suggestion-item:hover {
      background-color: #f1f1f1;
    }
    /* Each info element on its own line and centered */
    .info-row {
      text-align: center;
      margin-top: 5px;
    }
    .info-row div {
      margin: 2px 0;
      white-space: nowrap;
    }
    .tab-info { font-weight: normal; }
    /* Adjust the button to be slightly larger */
    .info-row button {
      font-size: 1rem;
      padding: 6px 12px;
      white-space: nowrap;
    }
    /* Modal image styling */
    #modalFullImage {
      max-width: 100%;
      height: auto;
    }
  </style>
</head>
<body class="bg-light">
<div class="container my-4">
  <h1 class="text-center">Search for Comics</h1>

  <!-- Search Input + Auto-Suggest -->
  <div class="mb-3">
    <label for="comicTitle" class="form-label">Comic Title</label>
    <input type="text" id="comicTitle" class="form-control" placeholder="Start typing..." autocomplete="off">
    <div id="suggestions" class="mt-2"></div>
  </div>

  <!-- Filter Row -->
  <div class="row filter-row" id="filterRow" style="display: none;">
    <div class="col-md-3">
      <label for="yearSelect" class="form-label">Year</label>
      <select id="yearSelect" class="form-select"></select>
    </div>
    <div class="col-md-3">
      <label for="tabSelect" class="form-label">Tab</label>
      <select id="tabSelect" class="form-select">
        <option value="All">All</option>
        <option value="Issues">Issues</option>
      </select>
    </div>
    <div class="col-md-3" id="issueCol" style="display: none;">
      <label for="issueSelect" class="form-label">Issue</label>
      <select id="issueSelect" class="form-select"></select>
    </div>
    <div class="col-md-3 d-flex align-items-center" style="margin-top:32px;">
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="variantCheckbox" value="1">
        <label class="form-check-label" for="variantCheckbox">Include Variants</label>
      </div>
    </div>
  </div>

  <!-- Search Button -->
  <div class="mb-3" id="searchButtonContainer" style="display: none;">
    <button id="searchButton" class="btn btn-primary">Search</button>
  </div>

  <!-- Results -->
  <h2 class="mt-4">Results</h2>
  <div id="resultsGallery" class="gallery"></div>
</div>

<!-- Image Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered"><!-- default size -->
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

  // Auto-suggest from comic_title_suggestions
  $("#comicTitle").on("input", function() {
    const val = $(this).val();
    if (val.length > 2) {
      $.ajax({
        url: "suggest.php",
        method: "GET",
        data: { q: val },
        success: function(data) {
          $("#suggestions").html(data);
        },
        error: function() {
          $("#suggestions").html("<p class='text-danger'>No suggestions available.</p>");
        }
      });
    } else {
      $("#suggestions").html("");
    }
  });

  // On suggestion click
  $(document).on("click", ".suggestion-item", function() {
    const title = $(this).text();
    $("#comicTitle").val(title);
    $("#suggestions").html("");

    $.get("getYears.php", { comic_title: title }, function(data) {
      $("#yearSelect").html(data);
      $("#filterRow").show();
      $("#searchButtonContainer").show();
    });

    $.get("getTabs.php", { comic_title: title }, function(data) {
      $("#tabSelect").html(data);
    });
  });

  // On tab change
  $("#tabSelect").on("change", function() {
    if ($(this).val() === "Issues") {
      $("#issueCol").show();
      loadMainIssues();
    } else {
      $("#issueCol").hide();
      $("#issueSelect").html("");
    }
  });

  // Load only main issues initially
  function loadMainIssues() {
    const comicTitle = $("#comicTitle").val();
    const year = $("#yearSelect").val();
    $.get("getIssues.php", { comic_title: comicTitle, year: year, only_main: 1 }, function(data) {
      $("#issueSelect").html(data);
    });
  }

  // Load variants if checkbox is enabled
  $("#variantCheckbox").on("change", function() {
    const comicTitle = $("#comicTitle").val();
    const year = $("#yearSelect").val();
    const includeVariants = $(this).is(":checked") ? 1 : 0;
    $.get("getIssues.php", { comic_title: comicTitle, year: year, include_variants: includeVariants }, function(data) {
      $("#issueSelect").html(data);
    });
  });

  // On search button click, load results and adjust display
  $("#searchButton").on("click", function() {
    $.ajax({
      url: "searchResults.php",
      method: "GET",
      data: {
        comic_title: $("#comicTitle").val(),
        year: $("#yearSelect").val(),
        tab: $("#tabSelect").val(),
        issue_number: $("#issueSelect").val(),
        include_variants: $("#variantCheckbox").is(":checked") ? 1 : 0
      },
      success: function(html) {
        $("#resultsGallery").html(html);
        // For each gallery item, rebuild the info row with separate lines.
        $(".gallery-item").each(function() {
          var tab = $(this).data("tab");
          var issueHtml = $(this).find("p.series-issue").html(); // Get the issue info.
          var button = $(this).find("button").detach(); // Remove the button.
          $(this).find("p.series-issue").remove(); // Remove original issue element.
          // Create a new info row (each element on its own line, centered).
          var infoRow = $('<div class="info-row"></div>');
          infoRow.append('<div class="issue-info">' + issueHtml + '</div>');
          infoRow.append('<div class="tab-info">Type: ' + tab + '</div>');
          // If already added, button remains disabled.
          if(button.hasClass("add-to-wanted")){
            button.text("+ Wanted List");
          }
          infoRow.append('<div class="button-wrapper">' + button.prop('outerHTML') + '</div>');
          $(this).append(infoRow);
        });
      },
      error: function() {
        $("#resultsGallery").html("<p class='text-danger'>Error loading results.</p>");
      }
    });
  });

  // Event handler for "Add to Wanted List" button click.
  $(document).on("click", ".add-to-wanted", function(e) {
    e.preventDefault();
    var btn = $(this);
    var comicTitle = btn.data("series-name");
    var issueNumber = btn.data("issue-number");
    var seriesYear = btn.data("series-year");
    // Send AJAX POST request to add the comic to the wanted list.
    $.ajax({
      url: "addToWanted.php",
      method: "POST",
      data: {
        comic_title: comicTitle,
        issue_number: issueNumber,
        years: seriesYear
      },
      success: function(response) {
        // On success, replace the button with a disabled "Already Added" button.
        btn.replaceWith('<button class="btn btn-success" disabled>Already Added</button>');
      },
      error: function() {
        alert("Error adding comic to wanted list");
      }
    });
  });

  // Make gallery item images clickable to open the modal with full-size image and details.
  $(document).on("click", ".gallery-item img", function() {
    var fullImageUrl = $(this).data("full");
    if (!fullImageUrl) {
      fullImageUrl = $(this).attr("src");
    }
    var parent = $(this).closest(".gallery-item");
    var comicTitle = parent.data("comic-title") || "";
    var years = parent.data("years") || "";
    var issueNumber = parent.data("issue-number") || "";
    var tab = parent.data("tab") || "";
    var variant = parent.data("variant") || "";

    $("#modalFullImage").attr("src", fullImageUrl);
    $("#modalComicTitle").text(comicTitle);
    $("#modalYears").text(years);
    $("#modalIssueNumber").text(issueNumber);
    $("#modalTab").text(tab);
    $("#modalVariant").text(variant);

    var myModal = new bootstrap.Modal(document.getElementById("imageModal"));
    myModal.show();
  });
});
</script>
</body>
</html>

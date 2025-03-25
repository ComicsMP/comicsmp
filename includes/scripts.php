<!-- REQUIRED JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  let searchMode = "startsWith";
  let autoSuggestRequest = null;
  var currentMatches = [];
  // Set these as needed.
  var currentUserId = 0; // set appropriate value
  var userCurrency = "USD";

  // Create offcanvas instance so we can close it on year selection.
  var searchOffcanvas = new bootstrap.Offcanvas(document.getElementById('searchFiltersOffcanvas'));

  // 🔥 DELETE FROM WANTED
  $(document).on("click", ".remove-cover", function() {
    const btn = $(this);
    const comicTitle = btn.data("comic-title");
    const issueNumber = btn.data("issue-number");
    const years = btn.data("years");
    const issueUrl = btn.data("issue_url");

    if (confirm("Are you sure you want to remove this from your Wanted list?")) {
      $.post("deletewanted.php", {
        comic_title: comicTitle,
        issue_number: issueNumber,
        years: years,
        issue_url: issueUrl
      }, function(response) {
        btn.closest(".cover-wrapper").fadeOut();
      }).fail(function() {
        alert("Failed to delete from Wanted list.");
      });
    }
  });

  // 🔥 DELETE FROM SALE
  $(document).on("click", ".remove-sale", function() {
    const btn = $(this);
    const listingId = btn.data("listing-id");

    if (confirm("Are you sure you want to remove this comic from your Sale list?")) {
      $.post("deletesale.php", { listing_id: listingId }, function(response) {
        btn.closest(".cover-wrapper").fadeOut();
      }).fail(function() {
        alert("Failed to delete from Sale list.");
      });
    }
  });

  // BULK EDIT SALE
  $(document).on("click", ".bulk-edit-btn", function (e) {
    e.preventDefault();
    const comicTitle = $(this).data("comic-title");
    const years = $(this).data("years");

    $("#bulkComicTitle").val(comicTitle);
    $("#bulkYears").val(years);
    $("#bulkPrice").val("");       // Clear previous
    $("#bulkCondition").val("");   // Clear previous

    const bulkModal = new bootstrap.Modal(document.getElementById("bulkEditModal"));
    bulkModal.show();
  });

  $("#bulkEditForm").on("submit", function (e) {
    e.preventDefault();
    const form = $(this);
    $.ajax({
      url: "editSaleBulk.php",
      type: "POST",
      data: form.serialize(),
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          alert("Bulk update successful.");
          location.reload(); // Optionally reload only the updated section
        } else {
          alert(response.message || "Update failed.");
        }
      },
      error: function () {
        alert("Server error during bulk update.");
      }
    });
  });

  // Function to update tab buttons dynamically.
  function updateTabButtons(title) {
    const selectedYear = $("#yearSelect").val();
    if (!selectedYear) {
      $("#tabButtons").html("");
      return;
    }
    $.get("getTabs.php", { comic_title: title, country: $("#countrySelect").val(), year: selectedYear, returnJson: 1 }, function(data) {
      let tabButtonsHtml = "";
      if (Array.isArray(data) && data.length > 0) {
        data.forEach(function(tabOption) {
          tabButtonsHtml += '<button type="button" class="btn btn-outline-primary tab-button" style="white-space: nowrap;">' + tabOption + '</button>';
        });
      } else {
        tabButtonsHtml = '<button type="button" class="btn btn-outline-primary tab-button" style="white-space: nowrap;">All</button>' +
                         '<button type="button" class="btn btn-outline-primary tab-button" style="white-space: nowrap;">Issues</button>';
      }
      $("#tabButtons").html(tabButtonsHtml);
      const $allButton = $("#tabButtons .tab-button").filter(function() {
        return $(this).text().trim() === "All";
      });
      if ($allButton.length) {
        $allButton.addClass("active");
      } else {
        $("#tabButtons .tab-button").first().addClass("active");
      }
      // If active tab is "issues", reposition the dropdown and toggle.
      if ($("#tabButtons .tab-button.active").text().trim().toLowerCase() === "issues") {
          loadMainIssues();
          if ($("#tabButtons").parent().attr("id") !== "tabRow") {
              $("#tabButtons").wrap('<div id="tabRow" style="display: flex; align-items: center; width: 100%;"></div>');
          }
          $("#issueSelectMain").css({
              "margin-left": "auto",
              "display": "inline-block",
              "vertical-align": "middle"
          }).appendTo("#tabRow").show();
          $("#variantToggleMain").css({
              "display": "inline-block",
              "vertical-align": "middle",
              "margin-left": "10px"
          }).appendTo("#tabRow").show();
      } else {
          $("#issueSelectMain").hide();
          $("#variantToggleMain").hide();
      }
      performSearch();
    }, "json");
  }

  function performSearch() {
  // Reset lazy scroll variables on new search
  currentOffset = 20;
  noMoreResults = false;

  const comicTitle = $("#comicTitle").val();
  let tab = "All";
  if ($("#tabButtons .tab-button.active").length) {
    tab = $("#tabButtons .tab-button.active").text().trim();
  } else if ($("#tabSelect").length) {
    tab = $("#tabSelect").val();
  }
  let issueNumber = $("#issueSelectMain").length ? $("#issueSelectMain").val() : $("#issueSelect").val();
  const includeVariants = $("#variantToggleMain").attr("data-enabled") === "1" ? 1 : 0;
  if(issueNumber === "All" && includeVariants === 1) {
    issueNumber = "";
  }
  const year = $("#yearSelect").val();
  const params = {
    comic_title: comicTitle,
    tab: tab,
    issue_number: issueNumber,
    include_variants: includeVariants,
    mode: searchMode,
    year: year,
    country: $("#countrySelect").val()
  };
  if (issueNumber !== "All" && issueNumber !== "" && includeVariants === 1) {
    let baseIssue = issueNumber;
    if (baseIssue.charAt(0) === "#") {
      baseIssue = baseIssue.substring(1);
    }
    params.base_issue = baseIssue.trim();
  }
    $.ajax({
      url: "searchResults.php",
      method: "GET",
      data: params,
      success: function(html) {
        $("#resultsGallery").html(html);
        $(".gallery-item").each(function() {
          const $item = $(this);
          $item.find(".button-wrapper").remove();
          const comicTitle = $item.data("comic-title");
          const issueNumber = $item.data("issue-number");
          const seriesYear = $item.data("years");
          const issueUrl = $item.data("issue_url");
          let wantedBtn = $(`<button class="btn btn-primary add-to-wanted">Wanted</button>`)
                .attr("data-series-name", comicTitle)
                .attr("data-issue-number", issueNumber)
                .attr("data-series-year", seriesYear)
                .attr("data-issue-url", issueUrl);
          if ($item.data("wanted") == 1) {
            wantedBtn = $(`<button class="btn btn-success add-to-wanted" disabled>Added</button>`);
          }
          let sellBtn = $(`<button class="btn btn-secondary sell-button">Sell</button>`);
          const buttonWrapper = $('<div class="button-wrapper text-center"></div>');
          buttonWrapper.append(wantedBtn).append(sellBtn);
          $item.append(buttonWrapper);
          if ($item.find(".sell-form").length === 0) {
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
          }
        });
      },
      error: function() {
        $("#resultsGallery").html("<p class='text-danger'>Error loading results.</p>");
      }
    });
  }

  // Dynamic tab button click event.
  $(document).on("click", "#tabButtons .tab-button", function() {
    $("#tabButtons .tab-button").removeClass("active");
    $(this).addClass("active");
    if ($(this).text().trim().toLowerCase() === "issues") {
      loadMainIssues();
      if ($("#tabButtons").parent().attr("id") !== "tabRow") {
          $("#tabButtons").wrap('<div id="tabRow" style="display: flex; align-items: center; width: 100%;"></div>');
      }
      $("#issueSelectMain").css({"margin-left": "auto", "display": "inline-block", "vertical-align": "middle"}).appendTo("#tabRow").show();
      $("#variantToggleMain").css({"display": "inline-block", "vertical-align": "middle", "margin-left": "10px"}).appendTo("#tabRow").show();
    } else {
      $("#issueSelectMain").hide();
      $("#variantToggleMain").hide();
    }
    performSearch();
  });

  $("#issueSelectMain").on("change", function() {
    performSearch();
  });

  $("#variantToggleMain").on("click", function() {
    let enabled = $(this).attr("data-enabled") === "1" ? 0 : 1;
    $(this).attr("data-enabled", enabled);
    if (enabled == 1) {
      $(this).removeClass("btn-outline-primary").addClass("btn-primary");
    } else {
      $(this).removeClass("btn-primary").addClass("btn-outline-primary");
    }
    loadMainIssues();
    performSearch();
  });

  $(".search-mode").on("click", function() {
    if (autoSuggestRequest) { autoSuggestRequest.abort(); }
    $(".search-mode").removeClass("active");
    $(this).addClass("active");
    searchMode = $(this).data("mode");
    $("#suggestions").html("");
    performSearch();
  });

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
      $("#yearFilterGroup").show();
      performSearch();
    });
    if ($("#yearSelect").val()) {
      updateTabButtons(title);
    } else {
      $("#tabButtons").html("");
    }
  });

  $("#yearSelect").on("change", function(){
    performSearch();
    const selectedYear = $(this).val();
    const comicTitle = $("#comicTitle").val();
    if (comicTitle && selectedYear) {
      updateTabButtons(comicTitle);
      $.get("getTabs.php", { comic_title: comicTitle, year: selectedYear, country: $("#countrySelect").val() }, function(data){
        performSearch();
      });
    } else {
      $("#tabButtons").html("");
    }
    searchOffcanvas.hide();
  });

  $("#navSearch").on("click", function() {
    searchOffcanvas.show();
  });

  $("#tabSelect, #countrySelect").on("change", function(){
    if ($("#tabSelect").val() === "Issues") {
      $("#issueFilterGroup").show();
      $("#variantGroup").show();
      loadMainIssues();
    } else {
      $("#issueFilterGroup").hide();
      $("#issueSelect").html("");
      if ($("#tabSelect").val() === "All") {
        $("#variantGroup").hide();
      } else {
        $("#variantGroup").show();
      }
      performSearch();
    }
  });

  $("#issueSelect").on("change", function(){ performSearch(); });
  
  function loadMainIssues() {
    const comicTitle = $("#comicTitle").val();
    const year = $("#yearSelect").val();
    const includeVariantsEnabled = $("#variantToggleMain").attr("data-enabled") === "1" ? 1 : 0;
    const params = { 
      comic_title: comicTitle, 
      only_main: includeVariantsEnabled ? 0 : 1, 
      year: year, 
      country: $("#countrySelect").val() 
    };
    $.get("getIssues.php", params, function(data) {
      $("#issueSelect").html("<option value='All'>All</option>" + data);
      $("#issueSelectMain").html("<option value='All'>All</option>" + data);
      performSearch();
    });
  }

  $("#variantToggle").on("click", function() {
    let enabled = $(this).attr("data-enabled") === "1" ? 0 : 1;
    $(this).attr("data-enabled", enabled);
    if (enabled == 1) {
      $(this).removeClass("btn-outline-primary").addClass("btn-primary");
    } else {
      $(this).removeClass("btn-primary").addClass("btn-outline-primary");
    }
    performSearch();
  });

  // -------------------------------
  // MODAL FUNCTIONALITY (Wanted, Sale, Matches)
  // -------------------------------
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
        btn.replaceWith('<button class="btn btn-success add-to-wanted" disabled>Added</button>');
      },
      error: function() { alert("Error adding comic to wanted list"); }
    });
  });

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

  // Popup Modal for Cover Image in Gallery Items.
  $(document).on("click", ".gallery-item img", function(e) {
    if ($(e.target).closest("button").length) return;
    $(".similar-issues").show();
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
    $("#popupConditionRow, #popupGradedRow, #popupPriceRow").hide();
    loadSimilarIssues(comicTitle, years, issueNumber, false);
    var modalEl = document.getElementById("coverPopupModal");
    var modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalInstance.show();
  });

  // Popup Modal for Cover Image in Wanted/Sale Covers.
  $(document).on("click", ".cover-img", function(e) {
    e.preventDefault();
    $(".similar-issues").hide();
    var $wrapper = $(this).closest(".cover-wrapper");
    var comicTitle = $wrapper.data("comic-title") || "N/A";
    var years = $wrapper.data("years") || "N/A";
    var issueNumber = $wrapper.data("issue-number") || "N/A";
    var tab = $wrapper.data("tab") || "N/A";
    var variant = $wrapper.data("variant") || "N/A";
    var date = $wrapper.data("date") || "N/A";
    var upc = $wrapper.data("upc") || "N/A";
    $("#popupMainImage").attr("src", $(this).attr("src"));
    $("#popupComicTitle").text(comicTitle);
    $("#popupYears").text(years);
    $("#popupIssueNumber").text(issueNumber);
    $("#popupTab").text(tab);
    $("#popupVariant").text(variant);
    $("#popupDate").text(date);
    $("#popupUPC").text(upc);
    $("#popupConditionRow, #popupGradedRow, #popupPriceRow").hide();
    var modal = new bootstrap.Modal(document.getElementById("coverPopupModal"));
    modal.show();
  });

  // Popup Modal for Match Cover Images.
  $(document).on("click", ".match-cover-img", function(e) {
    e.preventDefault();
    var $img = $(this);
    var src = $img.attr("src");
    var comicTitle = $img.data("comic-title") || "N/A";
    var years = $img.data("years") || "N/A";
    var issueNumber = $img.data("issue-number") || "N/A";
    $("#popupConditionRow, #popupGradedRow, #popupPriceRow").show();
    var condition = $img.data("condition") || "N/A";
    var graded = $img.data("graded") || "N/A";
    var price = $img.data("price") || "N/A";
    $(".similar-issues").hide();
    $("#popupMainImage").attr("src", src);
    $("#popupComicTitle").text(comicTitle);
    $("#popupYears").text(years);
    $("#popupIssueNumber").text(issueNumber);
    $("#popupTab").text("Loading...");
    $("#popupVariant").text("Loading...");
    $("#popupDate").text("Loading...");
    $("#popupCondition").text(condition);
    $("#popupGraded").text(graded);
    $("#popupPrice").text(price);
    $.ajax({
      url: "getMatchComicDetails.php",
      method: "GET",
      dataType: "json",
      data: { comic_title: comicTitle, years: years, issue_number: issueNumber },
      success: function(data) {
        $("#popupTab").text(data.Tab || "N/A");
        $("#popupVariant").text(data.Variant || "N/A");
        $("#popupDate").text(data.Date || "N/A");
        $("#popupUPC").text(data.upc || "N/A");
        if(data.comic_condition) { $("#popupCondition").text(data.comic_condition); }
        if(data.graded) { $("#popupGraded").text(data.graded); }
        if(data.price) { $("#popupPrice").text(data.price); }
      },
      error: function() {
        $("#popupTab").text("N/A");
        $("#popupVariant").text("N/A");
        $("#popupDate").text("N/A");
      }
    });
    var modal = new bootstrap.Modal(document.getElementById("coverPopupModal"));
    modal.show();
  });

  $(document).on("click", "#showAllSimilarIssues", function() {
    const comicTitle = $("#popupComicTitle").text() || "";
    const years = $("#popupYears").text() || "";
    const issueNumber = $("#popupIssueNumber").text() || "";
    loadSimilarIssues(comicTitle, years, issueNumber, true);
  });

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

  $(document).on("click", "#popupMainImage", function() {
    const src = $(this).attr("src");
    if(src) { window.open(src, '_blank'); }
  });

  $(document).on("click", ".expand-btn", function(e) {
    e.stopPropagation();
    var btn = $(this);
    var index = btn.data("index");
    var comicTitle = btn.data("comic-title");
    var years = btn.data("years");
    var issueUrls = btn.data("issue-urls");
    var rowSelector = "#expand-" + index;
    if ($(rowSelector).is(":visible")) {
      $(rowSelector).slideUp();
      return;
    }
    $.ajax({
      url: "getSeriesCovers.php",
      method: "GET",
      data: { comic_title: comicTitle, years: years, issue_urls: issueUrls },
      success: function (html) {
        $("#covers-" + index).html(html);
        $(rowSelector).slideDown();
      },
      error: function () { alert("Error loading series covers."); }
    });
  });

  $(document).on("click", ".sale-expand-btn", function(e) {
    e.stopPropagation();
    var btn = $(this);
    var index = btn.data("index");
    var comicTitle = btn.data("comic-title");
    var years = btn.data("years");
    var issueUrls = btn.data("issue-urls");
    var rowSelector = "#expand-sale-" + index;
    if ($(rowSelector).is(":visible")) {
      $(rowSelector).slideUp();
      return;
    }
    $.ajax({
      url: "getSaleCovers.php",
      method: "GET",
      data: { comic_title: comicTitle, years: years, issue_urls: issueUrls },
      success: function (html) {
        $("#sale-covers-" + index).html(html);
        $(rowSelector).slideDown();
      },
      error: function () { alert("Error loading sale covers."); }
    });
  });

  $(document).on("click", ".send-message-btn", function(e) {
    var recipientId = $(this).data("other-user-id");
    var recipientName = $(this).data("other-username");
    $("#recipient_id").val(recipientId);
    $("#recipientName").text(recipientName);
    var matchesData = $(this).data("matches");
    var matchesArray = (typeof matchesData === "string") ? JSON.parse(matchesData) : matchesData;
    currentMatches = matchesArray;
    var html = "";
    if (matchesArray.length) {
      matchesArray.forEach(function(match, idx) {
        var issueNum = match.issue_number ? match.issue_number.replace(/^#+/, '') : '';
        var line = '<div class="form-check mb-2 d-flex align-items-start" style="gap: 10px;">';
        line += '<input class="form-check-input mt-1 match-checkbox" type="checkbox" value="'+ idx +'" id="match_'+idx+'">';
        line += '<label class="form-check-label d-flex align-items-center" for="match_'+idx+'" style="gap: 10px;">';
        line += '<img src="'+ getFinalImagePathJS(match.cover_image) +'" alt="Cover" style="width:50px; height:75px; object-fit:cover;">';
        line += '<span>'+ match.comic_title + " (" + match.years + ") Issue #" + issueNum;
        if(match.comic_condition) { line += " (Condition: " + match.comic_condition + ")"; }
        if(match.price) { 
          var priceVal = parseFloat(match.price);
          var priceFormatted = "$" + priceVal.toFixed(2) + " " + (match.currency ? match.currency : (userCurrency ? userCurrency : "USD"));
          line += " (Price: " + priceFormatted + ")";
        }
        line += '</span></label></div>';
        html += line;
      });
    }
    $("#matchComicSelection").html(html);
    updateMessagePreview();
    var sendModal = new bootstrap.Modal(document.getElementById("sendMessageModal"));
    sendModal.show();
  });

  function updateMessagePreview() {
    var forSaleText = "";
    var wantedText = "";
    $("#matchComicSelection input.match-checkbox:checked").each(function(){
      var idx = $(this).val();
      var match = currentMatches[idx];
      var issueNum = match.issue_number ? match.issue_number.replace(/^#+/, '') : '';
      var line = "- " + match.comic_title + " (" + match.years + ") Issue #" + issueNum;
      if(match.comic_condition) { line += " (Condition: " + match.comic_condition + ")"; }
      if(match.price) {
        var priceVal = parseFloat(match.price);
        line += " (Price: $" + priceVal.toFixed(2) + " " + (match.currency ? match.currency : (userCurrency ? userCurrency : "USD")) + ")";
      }
      line += "\n";
      if (parseInt(match.buyer_id) === parseInt(currentUserId)) { forSaleText += line; }
      else if (parseInt(match.seller_id) === parseInt(currentUserId)) { wantedText += line; }
    });
    var recipientName = $("#recipientName").text() || "there";
    var messageText = "Hi " + recipientName + ",\n\n";
    if (forSaleText) { messageText += "I'm interested in buying the following comics:\n" + forSaleText + "\n"; }
    if (wantedText) { messageText += "I'm interested in selling the following comics:\n" + wantedText + "\n"; }
    messageText += "Please let me know if you're interested.";
    $("#messagePreview").val(messageText);
  }

  $(document).on("change", "#matchComicSelection input.match-checkbox", function() {
    updateMessagePreview();
  });

  $("#sendMessageForm").on("submit", function(e) {
    e.preventDefault();
    var formData = $(this).serialize();
    $.ajax({
      url: "sendMessage.php",
      method: "POST",
      data: formData,
      dataType: "json",
      success: function(response) {
        if (response.status === 'success') {
          alert("Message sent successfully.");
          $("#sendMessageModal").modal("hide");
        } else {
          alert(response.message);
        }
      },
      error: function() { alert("Failed to send message."); }
    });
  });

  $("#navSearch").on("click", function() {
    searchOffcanvas.show();
  });

  // 🛠️ EDIT SALE BUTTON HANDLER
  $(document).on("click", ".edit-sale", function (e) {
    e.preventDefault();
    const wrapper = $(this).closest(".cover-wrapper");
    const listingId = $(this).data("listing-id");
    const priceRaw = wrapper.data("price") || "";
    const price = priceRaw.toString().replace(/[^0-9.]/g, '');
    const condition = wrapper.data("condition") || "";

    $("#editListingId").val(listingId);
    $("#editPrice").val(price);
    $("#editCondition").val(condition);

    const modal = new bootstrap.Modal(document.getElementById("editSaleModal"));
    modal.show();
  });

  // 🛠️ FORM SUBMIT HANDLER for editSaleForm
  $("#editSaleForm").on("submit", function (e) {
    e.preventDefault();
    const form = $(this);
    $.ajax({
      url: "editSale.php",
      type: "POST",
      data: form.serialize(),
      dataType: "json",
      success: function (response) {
        if (response.status === "success") {
          alert("Updated successfully");
          location.reload(); // Optional: Replace with AJAX refresh logic
        } else {
          alert(response.message || "Update failed.");
        }
      },
      error: function () {
        alert("Server error during update.");
      }
    });
  });

  // -------------------------------
  // MANUAL TRIGGER ON PAGE LOAD FOR MATCHES TAB (REAL UPDATE)
  // -------------------------------
  $('#navMatches').on('shown.bs.tab', function () {
    // With throttling enabled:
    if (shouldUpdate()) {
      fetch('/comicsmp/update_matches.php?nocache=' + new Date().getTime())
        .then(response => response.text())
        .then(data => console.log('Update matches result:', data))
        .catch(error => console.error('Update error:', error));
    } else {
      console.log('Update skipped due to throttling.');
    }
  });
  // -------------------------------
  // END OF MANUAL TRIGGER REAL UPDATE SECTION

  // -------------------------------
  // LAZY SCROLL / PAGINATION FOR SEARCH RESULTS
  // -------------------------------
  // Global variables for lazy loading
  var currentOffset = 20; // assuming initial load is 20 items
  var loading = false;
  var noMoreResults = false;

  function loadMoreResults() {
    if (loading || noMoreResults) return;
    loading = true;
    // Gather search parameters (adjust selectors as needed)
    var params = {
      comic_title: $("#comicTitle").val(),
      year: $("#yearSelect").val(),
      volume: '', // adjust if needed
      tab: ($("#tabButtons .tab-button.active").length ? $("#tabButtons .tab-button.active").text().trim() : 'All'),
      issue_number: ($("#issueSelectMain").length ? $("#issueSelectMain").val() : ''),
      include_variants: $("#variantToggleMain").attr("data-enabled") === "1" ? 1 : 0,
      limit: 20,
      offset: currentOffset
    };

    $.ajax({
      url: "/comicsmp/searchResults.php",
      method: "GET",
      data: params,
      success: function(html) {
        if ($.trim(html) === "") {
          noMoreResults = true;
        } else {
          $("#resultsGallery").append(html);
          currentOffset += 20;
        }
        loading = false;
      },
      error: function() {
        console.error("Error loading more results.");
        loading = false;
      }
    });
  }

  // Bind a scroll event to the results container (#resultsGallery should be scrollable)
  $(window).on("scroll", function() {
  if ($(window).scrollTop() + $(window).height() >= $(document).height() - 100) {
    loadMoreResults();
  }
});

  // -------------------------------
  // END OF LAZY SCROLL SECTION
});

// Throttling function: only update if 5 minutes have passed since last update
function shouldUpdate() {
  const lastUpdate = sessionStorage.getItem('lastUpdate');
  const updateInterval = 300000; // 5 minutes in milliseconds
  if (!lastUpdate || (Date.now() - lastUpdate) > updateInterval) {
    sessionStorage.setItem('lastUpdate', Date.now());
    return true;
  }
  return false;
}

$('#navMatches').on('shown.bs.tab', function () {
  // With throttling enabled:
  if (shouldUpdate()) {
    fetch('/comicsmp/update_matches.php?nocache=' + new Date().getTime())
      .then(response => response.text())
      .then(data => console.log('Update matches result:', data))
      .catch(error => console.error('Update error:', error));
  } else {
    console.log('Update skipped due to throttling.');
  }
});
</script>

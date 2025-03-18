<!-- REQUIRED JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", () => {
  let searchMode = "startsWith";
  let autoSuggestRequest = null;
  
  // Create offcanvas instance so we can close it on year selection.
  var searchOffcanvas = new bootstrap.Offcanvas(document.getElementById('searchFiltersOffcanvas'));

  // Function to update tab buttons dynamically.
  // Only runs if a series year is selected.
  function updateTabButtons(title) {
    const selectedYear = $("#yearSelect").val();
    if (!selectedYear) {
      $("#tabButtons").html("");
      return;
    }
    $.get("getTabs.php", 
      { comic_title: title, country: $("#countrySelect").val(), year: selectedYear, returnJson: 1 }, 
      function(data) {
        let tabButtonsHtml = "";
        if (Array.isArray(data) && data.length > 0) {
          data.forEach(function(tabOption) {
            tabButtonsHtml += '<button type="button" class="btn btn-outline-primary tab-button" style="white-space: nowrap;">' + tabOption + '</button>';
          });
        } else {
          // Fallback in case no data is returned.
          tabButtonsHtml = '<button type="button" class="btn btn-outline-primary tab-button" style="white-space: nowrap;">All</button>' +
                           '<button type="button" class="btn btn-outline-primary tab-button" style="white-space: nowrap;">Issues</button>';
        }
        $("#tabButtons").html(tabButtonsHtml);
        // Mark "All" as active if it exists; otherwise, use the first button.
        const $allButton = $("#tabButtons .tab-button").filter(function() {
          return $(this).text().trim() === "All";
        });
        if ($allButton.length) {
          $allButton.addClass("active");
        } else {
          $("#tabButtons .tab-button").first().addClass("active");
        }
        // If the active tab is "Issues", load the issue numbers.
        if ($("#tabButtons .tab-button.active").text().trim().toLowerCase() === "issues") {
          loadMainIssues();
          $("#issueSelectMain").show();
          $("#variantToggleMain").show();
        } else {
          $("#issueSelectMain").hide();
          $("#variantToggleMain").hide();
        }
        performSearch();
      },
      "json"
    );
  }

  // Function to perform live search and update gallery results.
  function performSearch() {
    const comicTitle = $("#comicTitle").val();
    let tab = "All";
    if ($("#tabButtons .tab-button.active").length) {
      tab = $("#tabButtons .tab-button.active").text().trim();
    } else if ($("#tabSelect").length) {
      tab = $("#tabSelect").val();
    }
    // Use new main area issue dropdown if available.
    const issueNumber = $("#issueSelectMain").length ? $("#issueSelectMain").val() : $("#issueSelect").val();
    // Use new variant toggle if available.
    const includeVariants = $("#variantToggleMain").length ?
      ($("#variantToggleMain").attr("data-enabled") === "1" ? 1 : 0) :
      ($("#variantToggle").attr("data-enabled") === "1" ? 1 : 0);
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
    // If a specific issue is selected and variants are enabled, send the base_issue parameter.
    if (issueNumber !== "All" && includeVariants == 1) {
      params.base_issue = issueNumber;
    }
    $.ajax({
      url: "searchResults.php",
      method: "GET",
      data: params,
      success: function(html) {
        $("#resultsGallery").html(html);
        // Attach action buttons to each gallery item.
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

  // Event for dynamic tab buttons click.
  $(document).on("click", "#tabButtons .tab-button", function() {
    $("#tabButtons .tab-button").removeClass("active");
    $(this).addClass("active");
    // If "Issues" tab is clicked, load the issue numbers and show the controls.
    if ($(this).text().trim().toLowerCase() === "issues") {
      loadMainIssues();
      $("#issueSelectMain").show();
      $("#variantToggleMain").show();
    } else {
      $("#issueSelectMain").hide();
      $("#variantToggleMain").hide();
    }
    performSearch();
  });

  // Event for new issue dropdown in main area.
  $("#issueSelectMain").on("change", function() {
    performSearch();
  });

  // Event for new variants toggle button in main area.
  $("#variantToggleMain").on("click", function() {
    let enabled = $(this).attr("data-enabled") === "1" ? 0 : 1;
    $(this).attr("data-enabled", enabled);
    if (enabled == 1) {
      $(this).removeClass("btn-outline-primary").addClass("btn-primary");
    } else {
      $(this).removeClass("btn-primary").addClass("btn-outline-primary");
    }
    performSearch();
  });

  // Existing events for offcanvas elements.
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

  // When a suggestion is clicked, update comic title, year options, and dynamic tab buttons.
  $(document).on("click", ".suggestion-item", function() {
    const title = $(this).text();
    $("#comicTitle").val(title);
    $("#suggestions").html("");
    $.get("getYears.php", { comic_title: title, country: $("#countrySelect").val() }, function(data) {
      $("#yearSelect").html('<option value="">Select a year</option>' + data);
      $("#yearFilterGroup").show();
      performSearch();
    });
    // Only update tab buttons if a year is already selected.
    if ($("#yearSelect").val()) {
      updateTabButtons(title);
    } else {
      $("#tabButtons").html("");
    }
  });

  // When the series year changes, update the tab buttons and close the offcanvas.
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
    // Close the offcanvas automatically.
    searchOffcanvas.hide();
  });

  // Ensure that clicking the Search nav always opens the offcanvas.
  $("#navSearch").on("click", function() {
    searchOffcanvas.show();
  });

  // Legacy events for country select and tabSelect (if still needed).
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
  
  // Updated loadMainIssues function:
  function loadMainIssues() {
    const comicTitle = $("#comicTitle").val();
    const year = $("#yearSelect").val();
    const params = { comic_title: comicTitle, only_main: 1, year: year, country: $("#countrySelect").val() };
    $.get("getIssues.php", params, function(data) {
      // Prepend an "All" option to the returned data.
      $("#issueSelect").html("<option value='All'>All</option>" + data);
      $("#issueSelectMain").html("<option value='All'>All</option>" + data);
      performSearch();
    });
  }

  // Other functionality (wanted, sale, matches, modals, etc.) remains unchanged.
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
    var modalEl = document.getElementById("coverPopupModal");
    var modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
    modalInstance.show();
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
      error: function () {
        alert("Error loading series covers.");
      }
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
      error: function () {
        alert("Error loading sale covers.");
      }
    });
  });

  $(document).on("click", ".expand-match-btn", function() {
    var otherUserId = $(this).data("other-user-id");
    var rowSelector = "#expand-match-" + otherUserId;
    if ($(rowSelector).is(":visible")) {
      $(rowSelector).slideUp();
    } else {
      $(rowSelector).slideDown();
    }
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
      error: function() {
        alert("Failed to send message.");
      }
    });
  });

  // Automatically open the offcanvas when "Search" is clicked.
  $("#navSearch").on("click", function() {
    searchOffcanvas.show();
  });
});
</script>

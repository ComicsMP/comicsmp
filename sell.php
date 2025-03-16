<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sell a Comic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Gallery styling (similar to search.php) */
    .gallery {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin-top: 20px;
    }
    .gallery-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-align: center;
      border: 1px solid #ddd;
      padding: 10px;
      border-radius: 5px;
      background-color: #f9f9f9;
      position: relative;
      width: calc(16.66% - 15px);
      min-height: 400px;
      box-sizing: border-box;
    }
    .gallery-item img {
      width: 100%;
      height: 270px;
      object-fit: cover;
      margin-bottom: 5px;
      border-radius: 5px;
      cursor: pointer;
    }
    .gallery-item .series-issue,
    .gallery-item .series-tab {
      margin: 0;
      font-size: 1rem;
      margin-bottom: 3px;
      font-weight: bold;
    }
    .gallery-item button {
      width: 90%;
      padding: 8px;
      font-size: 0.9rem;
      border-radius: 5px;
      margin-top: auto;
      position: absolute;
      bottom: 10px;
    }
    .selected-cover {
      border: 2px solid green;
    }
    /* Filter row styling */
    .filter-row {
      margin-bottom: 20px;
    }
    /* Suggestion list styling */
    #suggestions {
      margin-top: 0.5rem;
    }
    .suggestion-item {
      padding: 10px;
      cursor: pointer;
      border: 1px solid #ddd;
      background-color: #fff;
      margin-bottom: 5px;
      border-radius: 5px;
    }
    .suggestion-item:hover {
      background-color: #f1f1f1;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1 class="my-4 text-center">Sell a Comic</h1>
    <form id="sellForm" method="POST" action="addListing.php" onsubmit="return forceSubmit();">

      <!-- Series Name & Suggestions -->
      <div class="mb-3">
        <label for="seriesName" class="form-label">Series Name</label>
        <input type="text" id="seriesName" name="series_name" class="form-control" placeholder="Start typing series name..." required>
        <div id="suggestions" class="mt-2"></div>
      </div>
      <!-- Filter Row: Year, Issue, Tab and Variant Checkbox -->
      <div class="row filter-row" id="filterRow" style="display: none;">
        <div class="col-md-3">
          <label for="seriesYear" class="form-label">Year</label>
          <select id="seriesYear" name="series_year" class="form-select"></select>
        </div>
        <div class="col-md-3" id="issueCol">
          <label for="issueNumber" class="form-label">Issue</label>
          <select id="issueNumber" name="issue_number" class="form-select"></select>
        </div>
        <div class="col-md-3">
          <label for="tabDropdown" class="form-label">Tab</label>
          <select id="tabDropdown" name="tab" class="form-select"></select>
        </div>
        <div class="col-md-3">
          <div class="form-check" style="margin-top:32px;">
            <input type="checkbox" id="variantCheckbox" name="include_variants" class="form-check-input" value="1">
            <label for="variantCheckbox" class="form-check-label">Include Variants</label>
          </div>
        </div>
      </div>
      <!-- Gallery & Hidden Fields -->
      <div id="coverGallery" class="gallery"></div>
      <input type="hidden" id="selectedSeriesCover" name="series_cover" required>
      <input type="hidden" id="selectedImagePath" name="image_path" required>
      <div id="additionalFields" style="display: none;">
        <div id="issueFields"></div>
        <button type="button" id="addAnotherIssue" class="btn btn-secondary my-3">+ Add Another Issue</button>
        <button type="submit" class="btn btn-primary">Submit Listings</button>
      </div>
    </form>
  </div>

  <!-- Modal for Full-Size Image & Details (shows Variant) -->
  <div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="imageModalLabel">Comic Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <img id="modalImage" src="" alt="Full-Size Image" style="max-width: 100%; height: auto;">
          <div class="details mt-3">
            <p><strong>Series Name:</strong> <span id="modalSeriesName"></span></p>
            <p><strong>Series Issue:</strong> <span id="modalSeriesIssue"></span></p>
            <p><strong>Series Year:</strong> <span id="modalSeriesYear"></span></p>
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
    $("#sellForm").on("submit", function (e) {
      e.preventDefault(); // Prevent default behavior

      let formData = new FormData(this);

      console.log("🚀 Submitting Form Data...");
      for (let [key, value] of formData.entries()) {
        console.log(`🛠️ ${key} = ${value} (Type: ${typeof value})`);
      }

      $.ajax({
        url: "addListing.php",
        type: "POST",
        data: formData,
        contentType: false,
        processData: false,
        success: function (response) {
          console.log("✅ Server Response:", response);
        },
        error: function (xhr, status, error) {
          console.log("❌ Submission Failed:", error);
        }
      });
    });

    $(document).ready(function () {
      let issueCount = 0;

      // Auto-suggest for Series Name
      $("#seriesName").on("input", function () {
        const input = $(this).val();
        if (input.length > 2) {
          $.ajax({
            url: "suggest.php",
            method: "GET",
            data: { q: input },
            success: function (data) {
              $("#suggestions").html(data);
            },
            error: function () {
              $("#suggestions").html("<p class='text-danger'>Unable to fetch suggestions.</p>");
            }
          });
        } else {
          $("#suggestions").html("");
        }
        $("#filterRow").hide();
        $("#coverGallery").html("");
      });

      // On suggestion click: set series name, then fetch Year and Tab dropdown data
      $(document).on("click", ".suggestion-item", function () {
        const seriesName = $(this).text();
        $("#seriesName").val(seriesName);
        $("#suggestions").html("");
        $.ajax({
          url: "getYears.php",
          method: "GET",
          data: { series: seriesName },
          success: function (data) {
            // Removed the hard-coded placeholder "hhh"
            $("#seriesYear").html(data);
            // After year is loaded, clear Tab dropdown (it will be loaded on year change)
            $("#tabDropdown").html("");
            $("#filterRow").show();
            // If a valid year exists, trigger the change event so issues are loaded
            if ($("#seriesYear").val()) {
              $("#seriesYear").trigger("change");
            }
          },
          error: function () {
            $("#filterRow").html("<p class='text-danger'>Unable to fetch years.</p>");
          }
        });
      });

      // When Year changes, load Issue Numbers and Tabs (pass series and year)
      $("#seriesYear").on("change", function () {
        const seriesName = $("#seriesName").val();
        const year = $(this).val();
        $.ajax({
          url: "getIssues.php",
          method: "GET",
          data: { series: seriesName, year: year },
          success: function (data) {
            $("#issueNumber").html(data);
          },
          error: function () {
            $("#issueNumber").html("<option value='' selected disabled>Error fetching issues</option>");
          }
        });
        $.ajax({
          url: "getTabs.php",
          method: "GET",
          data: { series: seriesName, year: year },
          success: function (tabData) {
            $("#tabDropdown").html(tabData);
          },
          error: function () {
            $("#tabDropdown").html("<option value='' selected disabled>Error fetching Tabs</option>");
          }
        });
      });

      // When Tab changes, if not "Issues" or "All", hide the Issue dropdown
      $("#tabDropdown").on("change", function () {
        const tabVal = $(this).val();
        if (tabVal === "Issues" || tabVal === "All") {
          $("#issueCol").show();
        } else {
          $("#issueCol").hide();
        }
        fetchGallery();
      });

      // When Issue or Variant checkbox changes, update gallery
      $("#issueNumber, #variantCheckbox").on("change", function () {
        fetchGallery();
      });

      function fetchGallery() {
        const seriesName = $("#seriesName").val();
        const year = $("#seriesYear").val();
        let issueNumber = $("#issueNumber").val();
        const tab = $("#tabDropdown").val();
        const includeVariants = $("#variantCheckbox").is(":checked") ? 1 : 0;

        // If the selected Tab is not "Issues" or "All", ignore the Issue filter.
        if (tab !== "Issues" && tab !== "All") {
          issueNumber = "";
        }

        if (seriesName && year && tab && (issueNumber !== "" || (tab !== "Issues" && tab !== "All"))) {
          $.ajax({
            url: "sellSearchResults.php",
            method: "GET",
            data: {
              series: seriesName,
              year: year,
              issue_number: issueNumber,  // <<--- Changed here
              tab: tab,
              include_variants: includeVariants
            },
            success: function (data) {
              $("#coverGallery").html(data);
              $("#coverGallery .gallery-item button").each(function () {
                $(this).text("Sell This Cover");
                $(this).removeClass("btn-primary").addClass("btn-success");
                $(this).attr("onclick", "selectCoverForSale(this)");
              });
            },
            error: function () {
              alert("An error occurred while fetching cover images.");
            }
          });
        } else {
          $("#coverGallery").html("");
        }
      }

      window.selectCoverForSale = function (button) {
        const galleryItem = $(button).closest(".gallery-item");
        // For the popup, use the Variant from the image's data-variant attribute
        const variant = galleryItem.find("img").data("variant");
        const imagePath = galleryItem.find("img").attr("src");

        $(".gallery-item").removeClass("selected-cover");
        galleryItem.addClass("selected-cover");

        $("#selectedSeriesCover").val(variant);
        $("#selectedImagePath").val(imagePath);

        $("#additionalFields").show();
        addIssueFields(variant, imagePath);
      };

      function addIssueFields(variant, imagePath) {
        const issueFieldHtml = `
          <div class="issue-group mb-3">
            <h5>Details for Issue: ${variant}</h5>
            <div class="mb-3">
              <label for="condition_${issueCount}" class="form-label">Condition</label>
              <select id="condition_${issueCount}" name="conditions[]" class="form-select" required>
                <option value="" disabled selected>Select condition</option>
                <option value="9.8">NM/M (9.8)</option>
                <option value="9.6">NM+ (9.6)</option>
                <option value="9.4">NM (9.4)</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="price_${issueCount}" class="form-label">Price</label>
              <input type="number" id="price_${issueCount}" name="prices[]" class="form-control" placeholder="Enter price" required>
            </div>
            <input type="hidden" name="image_paths[]" value="${$("#selectedImagePath").val()}">
          </div>
        `;
        $("#issueFields").append(issueFieldHtml);
        issueCount++;
      }

      $("#addAnotherIssue").on("click", function () {
        $("#issueNumber").val("");
        $("#coverGallery").html("");
      });

      // Modal popup for full-size image & details – using Variant for popup display
      $(document).on("click", ".gallery-item img", function () {
        const fullSizeImage = $(this).data("fullsize") || $(this).attr("src");
        const seriesName = $(this).data("series-name") || $("#seriesName").val();
        const issueNumber = $(this).data("issue-number") || $("#issueNumber").val();
        const seriesYear = $(this).data("series-year") || $("#seriesYear").val();
        const variant = $(this).data("variant") || "";
        $("#modalImage").attr("src", fullSizeImage);
        $("#modalSeriesName").text(seriesName);
        $("#modalSeriesIssue").text(issueNumber);
        $("#modalSeriesYear").text(seriesYear);
        $("#modalVariant").text(variant);
        $("#imageModal").modal("show");
      });
    });
  </script>
</body>
</html>

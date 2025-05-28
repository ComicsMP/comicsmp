<?php
// mainContent.php
// Start the session and ensure the user is logged in.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
if (!$userId) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// -------------------------
// Grade Mapping for Abbreviations (use string keys)
// -------------------------
$gradeMapping = [
    "10.0" => "Gem Mint",
    "9.9"  => "Mint",
    "9.8"  => "NM/M",
    "9.6"  => "NM+",
    "9.4"  => "NM",
    "9.2"  => "NM-",
    "9.0"  => "VF/NM",
    "8.5"  => "VF+",
    "8.0"  => "VF",
    "7.5"  => "VF-",
    "7.0"  => "FN/VF",
    "6.5"  => "FN+",
    "6.0"  => "FN",
    "5.5"  => "FN-",
    "5.0"  => "VG/FN",
    "4.5"  => "VG+",
    "4.0"  => "VG",
    "3.5"  => "VG-",
    "3.0"  => "G/VG",
    "2.5"  => "G",
    "2.0"  => "G",
    "1.8"  => "G-",
    "1.5"  => "Fa/G",
    "1.0"  => "Fa",
    "0.5"  => "Poor"
];

// Helper functions
function formatScore($score) {
    if (is_numeric($score)) {
        $floatVal = floatval($score);
        if ($floatVal == intval($floatVal)) {
            return intval($floatVal);
        }
    }
    return $score;
}
function getScoreKey($score) {
    if (is_numeric($score)) {
        return number_format(floatval($score), 1);
    }
    return $score;
}
if (!function_exists('getFinalImagePath')) {
    function getFinalImagePath($path) {
        return str_replace("images/images", "images", $path);
    }
}
if (!function_exists('fixImagePath')) {
    function fixImagePath($path) {
        $fixed = str_replace("images/images", "images", $path);
        $fixed = ltrim($fixed, '/');
        return "/comicsmp/" . $fixed;
    }
}
?>
<!-- MAIN CONTENT AREA -->
<div class="main-content">
  <div class="tab-content" id="profileTabContent">
    
    <!-- DASHBOARD TAB -->
    <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
      <!-- Dashboard content will be loaded via AJAX from ./includes/dashboard2.php -->
    </div>

    <!-- SEARCH TAB -->
<div class="tab-pane fade" id="search" role="tabpanel">
  <section class="content-area">
    <div class="search-controls mb-3">
      <div id="tabButtons" class="btn-group mb-2" role="group" aria-label="Tab Buttons">
        <!-- Populated by your JavaScript (updateTabButtons) -->
      </div>
      <div class="d-flex align-items-center gap-2">
        <select id="issueSelectMain" class="form-select" style="display:none; max-width: 160px;">
          <!-- Populated by loadMainIssues() -->
        </select>
        <button id="variantToggleMain" type="button" class="btn btn-outline-primary" data-enabled="0" style="display:none;">
          Include Variants
        </button>
      </div>
    </div>

    <!-- ✅ New wrapper added here -->
    <div id="search-results">
      <div id="resultsGallery" class="gallery"></div>
    </div>
  </section>
</div>

    <!-- WANTED TAB -->
    <div class="tab-pane fade" id="wanted" role="tabpanel">
      <?php if (empty($wantedSeries)): ?>
        <p>No wanted items found.</p>
      <?php else: ?>
        <table class="table table-striped" id="wantedTable">
          <thead>
            <tr>
              <th>Comic Title</th>
              <th>Years</th>
              <th>Issue Numbers</th>
              <th>Count</th>
              <th>Expand</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($wantedSeries as $index => $series): ?>
              <tr class="main-row" data-index="<?php echo $index; ?>">
                <td><?php echo htmlspecialchars($series['comic_title']); ?></td>
                <td><?php echo htmlspecialchars($series['years']); ?></td>
                <td><?php echo htmlspecialchars($series['issues']); ?></td>
                <td><?php echo htmlspecialchars($series['count']); ?></td>
                <td>
                  <button class="btn btn-info btn-sm expand-btn" 
                          data-comic-title="<?php echo htmlspecialchars($series['comic_title']); ?>" 
                          data-years="<?php echo htmlspecialchars($series['years']); ?>" 
                          data-issue-urls="<?php echo htmlspecialchars($series['issue_urls']); ?>"
                          data-index="<?php echo $index; ?>">
                    Expand
                  </button>
                </td>
              </tr>
              <tr class="expand-row" id="expand-<?php echo $index; ?>" style="display:none;">
                <td colspan="5">
                  <div class="cover-container" id="covers-<?php echo $index; ?>">
                    <!-- Wanted covers loaded via AJAX -->
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- EDIT COMIC MODAL (Unique IDs) -->
    <div class="modal fade" id="editSaleModalDashboard" tabindex="-1" aria-labelledby="editSaleModalLabelDashboard" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="editSaleFormDashboard">
            <div class="modal-header">
              <h5 class="modal-title" id="editSaleModalLabelDashboard">Edit Sale Listing</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="listing_id" id="editListingIdDashboard">
              <div class="mb-3">
                <label for="editPriceDashboard" class="form-label">Price</label>
                <input type="number" class="form-control" id="editPriceDashboard" name="price" required>
              </div>
              <div class="mb-3">
                <label for="editConditionDashboard" class="form-label">Condition</label>
                <select class="form-select" id="editConditionDashboard" name="condition" required>
                  <option value="">Select Condition</option>
                  <?php foreach ($gradeMapping as $value => $label): ?>
                    <option value="<?php echo $value; ?>"><?php echo $value . " (" . $label . ")"; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>


<!-- UPC Result Modal -->
<div class="modal fade" id="upcResultModal" tabindex="-1" aria-labelledby="upcResultModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="upcResultModalLabel">Comic Found</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center" id="upcModalBody">
        <!-- Filled dynamically -->
      </div>
    </div>
  </div>
</div>


    <!-- BULK EDIT MODAL (Unique IDs) -->
    <div class="modal fade" id="bulkEditModalDashboard" tabindex="-1" aria-labelledby="bulkEditModalLabelDashboard" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="bulkEditFormDashboard">
            <div class="modal-header">
              <h5 class="modal-title" id="bulkEditModalLabelDashboard">Bulk Edit Series</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="comic_title" id="bulkComicTitleDashboard">
              <input type="hidden" name="years" id="bulkYearsDashboard">
              <div class="mb-3">
                <label for="bulkPriceDashboard" class="form-label">New Price</label>
                <input type="number" class="form-control" id="bulkPriceDashboard" name="price" required>
              </div>
              <div class="mb-3">
                <label for="bulkConditionDashboard" class="form-label">New Condition</label>
                <select class="form-select" id="bulkConditionDashboard" name="condition" required>
                  <option value="">Select Condition</option>
                  <?php foreach ($gradeMapping as $value => $label): ?>
                    <option value="<?php echo $value; ?>"><?php echo $value . " (" . $label . ")"; ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary">Apply to All</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- COMICS FOR SALE TAB -->
    <div class="tab-pane fade" id="selling" role="tabpanel">
      <?php if (empty($saleGroups)): ?>
        <p>No comics listed for sale.</p>
      <?php else: ?>
        <table class="table table-striped" id="sellingTable">
          <thead>
            <tr>
              <th>Comic Title</th>
              <th>Years</th>
              <th>Issue Numbers</th>
              <th>Count</th>
              <th>Expand</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($saleGroups as $index => $group): ?>
              <tr class="main-row" data-index="<?php echo $index; ?>">
                <td><?php echo htmlspecialchars($group['comic_title']); ?></td>
                <td><?php echo htmlspecialchars($group['years']); ?></td>
                <td><?php echo htmlspecialchars($group['issues']); ?></td>
                <td><?php echo htmlspecialchars($group['count']); ?></td>
                <td>
                  <button class="btn btn-info btn-sm sale-expand-btn"
                          data-comic-title="<?php echo htmlspecialchars($group['comic_title']); ?>"
                          data-years="<?php echo htmlspecialchars($group['years']); ?>"
                          data-issue-urls="<?php echo htmlspecialchars($group['issue_urls']); ?>"
                          data-index="<?php echo $index; ?>">
                    Expand
                  </button>
                </td>
              </tr>
              <tr class="expand-row" id="expand-sale-<?php echo $index; ?>" style="display:none;">
                <td colspan="5">
                  <button class="btn btn-warning btn-sm bulk-edit-btn"
                          data-comic-title="<?php echo htmlspecialchars($group['comic_title']); ?>"
                          data-years="<?php echo htmlspecialchars($group['years']); ?>"
                          data-index="<?php echo $index; ?>">
                    Bulk Edit Series
                  </button>
                  <div class="cover-container" id="sale-covers-<?php echo $index; ?>">
                    <!-- Sale covers loaded via AJAX -->
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

    <!-- MATCHES TAB -->
    <div class="tab-pane fade" id="matches" role="tabpanel">
      <!-- The matches content is loaded via AJAX into this fragment -->
      <div id="matchesFragment">Loading Matches...</div>


    </div>

    <!-- PROFILE TAB -->
    <div class="tab-pane fade" id="profile" role="tabpanel">
      <?php include 'includes/profile_content_inner.php'; ?>
    </div>

    <!-- MESSAGES TAB -->
    <div class="tab-pane fade" id="messages" role="tabpanel">
      <?php include 'messages.php'; ?>
    </div>
    
  </div>
</div>

<!-- Include jQuery and Bootstrap JS if not already loaded -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


<!-- AJAX to load dashboard content -->
<script>
$(document).ready(function(){
  function loadDashboardContent() {
    $("#dashboard").load("./includes/dashboard2.php", function(response, status, xhr) {
      if (status === "error") {
        $("#dashboard").html("<p>Error loading content: " + xhr.status + " " + xhr.statusText + "</p>");
      } else {
        console.log("Dashboard content loaded successfully.");
      }
    });
  }
  
  // Initial load of dashboard content
  loadDashboardContent();
});
</script>

<!-- Load Matches Fragment via AJAX when the Matches tab is activated -->
<script>
$(document).ready(function () {
  $('a.nav-link[href="#matches"]').on('shown.bs.tab', function (e) {
    $("#matchesFragment").load("includes/matches.php #matchesContainer", function (response, status, xhr) {
      if (status === "error") {
        $("#matchesFragment").html("<p>Error loading matches: " + xhr.status + " " + xhr.statusText + "</p>");
      } else {
        // 🔧 Initialize Buy/Sell checkbox filters after AJAX content loads
        function initMatchesFiltering() {
          const buyCheckbox = document.getElementById('buyCheckbox');
          const sellCheckbox = document.getElementById('sellCheckbox');

          if (!buyCheckbox || !sellCheckbox) {
            console.warn("Buy/Sell checkboxes not found.");
            return;
          }

          function filterTestRows() {
            const showBuy = buyCheckbox.checked;
            const showSell = sellCheckbox.checked;

            document.querySelectorAll('.main-row').forEach(row => {
              const type = row.getAttribute('data-type');
              let show = false;

              if (type === 'buy' && showBuy) show = true;
              else if (type === 'sell' && showSell) show = true;
              else if (type === 'buy_sell' && (showBuy || showSell)) show = true;

              row.style.display = show ? '' : 'none';
            });
          }

          buyCheckbox.addEventListener('change', filterTestRows);
          sellCheckbox.addEventListener('change', filterTestRows);
          filterTestRows();
        }

        initMatchesFiltering(); // ✅ Run immediately after matches load
        console.log("Matches fragment loaded and filter initialized.");
      }
    });
  });
});
</script>

<!-- Auto-Populate Default Subject and Message on Tab Show and on Checkbox Change -->
<script>
$('button[data-bs-target^="#message-"]').on('shown.bs.tab', function(e) {
  var targetId = $(this).data('bsTarget');
  var $messageTab = $(targetId);
  var form = $messageTab.find('.send-message-form');
  if(form.length) {
    updateDefaultText(form);
  }
});

$(document).on("change", ".send-message-form input[name='selected_issues_buy[]'], .send-message-form input[name='selected_issues_sell[]']", function() {
  var form = $(this).closest('.send-message-form');
  updateDefaultText(form);
});

function updateDefaultText(form) {
  var intent = form.data("intent"); 
  var displayName = form.data("displayname");
  var subjectField = form.find("input[name='subject']");
  var messageField = form.find("textarea[name='message']");
  
  var selectedBuy = [];
  form.find('input[name="selected_issues_buy[]"]:checked').each(function(){
    var labelText = $(this).siblings("label").text();
    selectedBuy.push(labelText);
  });
  var selectedSell = [];
  form.find('input[name="selected_issues_sell[]"]:checked').each(function(){
    var labelText = $(this).siblings("label").text();
    selectedSell.push(labelText);
  });
  
  var overallIntent = intent;
  if(selectedBuy.length > 0 && selectedSell.length === 0) {
    overallIntent = "buy";
  } else if(selectedSell.length > 0 && selectedBuy.length === 0) {
    overallIntent = "sell";
  } else if(selectedBuy.length > 0 && selectedSell.length > 0) {
    overallIntent = "buy_sell";
  }
  
  var defaultSubject = "";
  if(overallIntent === "buy") {
    defaultSubject = "Inquiry: Interested in Purchasing Matched Comics";
  } else if(overallIntent === "sell") {
    defaultSubject = "Inquiry: Interested in Selling Matched Comics";
  } else {
    defaultSubject = "Inquiry: Buy & Sell Inquiry for Matched Comics";
  }
  subjectField.val(defaultSubject);
  
  var defaultMessage = "Hello " + displayName + ",\n\n";
  if(overallIntent === "buy") {
    defaultMessage += "I am interested in purchasing the following issues:\n\n";
    defaultMessage += selectedBuy.join("\n") + "\n\n";
  } else if(overallIntent === "sell") {
    defaultMessage += "I am interested in selling the following issues:\n\n";
    defaultMessage += selectedSell.join("\n") + "\n\n";
  } else {
    defaultMessage += "I am interested in both buying and selling the following issues:\n\n";
    if(selectedBuy.length > 0) {
      defaultMessage += "Buy:\n" + selectedBuy.join("\n") + "\n\n";
    }
    if(selectedSell.length > 0) {
      defaultMessage += "Sell:\n" + selectedSell.join("\n") + "\n\n";
    }
  }
  defaultMessage += "Please let me know if you are interested.\n\nThank you.";
  messageField.val(defaultMessage);
}
$(document).ready(function() {
  // --- UPC search handler ---
  $("#upcSearch").on("change", function() {
    const upc = $(this).val().trim();
    if (!upc) return;

    $.get("getComicByUPC.php", { upc: upc }, function(data) {
      const upcModalEl = document.getElementById('upcResultModal');
      const upcModal   = bootstrap.Modal.getOrCreateInstance(upcModalEl);

      if (data.success) {
        // Clean image path
        let rawPath = data.image_path;
        let imgSrc = (rawPath.startsWith('http') || rawPath.startsWith('/comicsmp/'))
          ? rawPath
          : '/comicsmp/' + rawPath.replace(/^\/?/, '');

        // Clean issue number
        let issueNumber = (data.issue_number || '').replace(/^#+/, '#');

        // Build Wanted/Add button based on is_wanted
        const wantedBtn = data.is_wanted
          ? `<button class="btn btn-secondary" disabled>Added</button>`
          : `<button class="btn btn-primary add-to-wanted"
                      data-comic-id="${data.ID}"
                      data-series-name="${data.comic_title}"
                      data-issue-number="${issueNumber}"
                      data-series-year="${data.years}"
                      data-issue-url="${data.issue_url}">
               Wanted
             </button>`;

        // Build full modal content
        const content = `
          <a href="${imgSrc}" target="_blank" rel="noopener noreferrer" data-bs-toggle="tooltip" title="Click to view full-size">
            <img src="${imgSrc}" class="img-fluid mb-3"
                 style="max-height:380px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.25); cursor: zoom-in;">
          </a>
          <p><strong>${data.comic_title}</strong></p>
          <p>Issue: ${issueNumber}</p>
          <p>Year: ${data.years || 'N/A'}</p>
          <div class="d-flex justify-content-center gap-2 mt-3">
            ${wantedBtn}
            <button class="btn btn-secondary sell-button"
                    data-series-name="${data.comic_title}"
                    data-issue-number="${issueNumber}"
                    data-series-year="${data.years}"
                    data-issue-url="${data.issue_url}">
              Sell
            </button>
          </div>
        `;

        $("#upcModalBody").html(content);
        upcModal.show();
        $('body').tooltip({ selector: '[data-bs-toggle="tooltip"]' });

      } else {
        $("#upcModalBody").html(`<p class="text-danger">${data.message}</p>`);
        upcModal.show();
      }
    }).fail(function() {
      $("#upcModalBody").html("<p class='text-danger'>Error contacting server.</p>");
      const upcModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('upcResultModal'));
      upcModal.show();
    });
  });

  // Enter key triggers UPC search
  $("#upcSearch").on("keypress", function(e) {
    if (e.which === 13) {
      e.preventDefault();
      $(this).trigger("change");
    }
  });

  // Cleanup backdrop on close
  $("#upcResultModal").on('hidden.bs.modal', function () {
    $("body").removeClass("modal-open");
    $(".modal-backdrop").remove();
  });
});

// --- Sell button handler ---
$(document).on("click", ".sell-button", function () {
  const upcModalEl = document.getElementById("upcResultModal");
  bootstrap.Modal.getInstance(upcModalEl).hide();

  const btn          = $(this);
  const comicTitle   = btn.data("series-name")   || "";
  const issueNumber  = btn.data("issue-number") || "";
  const years        = btn.data("series-year")  || "";
  const issueUrl     = btn.data("issue-url")    || "";

  const $form = $("#editSaleFormDashboard");
  $form[0].reset();
  $form.find("input[name='listing_id']").val("");
  $form.find("input[name='comic_title'], input[name='issue_number'], input[name='years'], input[name='issue_url'], select[name='graded']").remove();

  $form.append(`
    <input type="hidden" name="comic_title"  value="${comicTitle}">
    <input type="hidden" name="issue_number" value="${issueNumber}">
    <input type="hidden" name="years"        value="${years}">
    <input type="hidden" name="issue_url"    value="${issueUrl}">
  `);

  if (!$form.find("select[name='graded']").length) {
    $form.find(".modal-body").append(`
      <div class="mb-3" id="gradedDropdownField">
        <label for="graded" class="form-label">Graded</label>
        <select name="graded" class="form-select" required>
          <option value="">Select...</option>
          <option value="1">Yes (Graded)</option>
          <option value="0">No</option>
        </select>
      </div>
    `);
  }

  new bootstrap.Modal(document.getElementById("editSaleModalDashboard")).show();
});

// --- Add to wanted handler ---
$(document).on("click", ".add-to-wanted", function() {
  const btn = $(this);
  const payload = {
    comic_title:   btn.data("series-name"),  // ✅ fixed key
    issue_number:  btn.data("issue-number"),
    years:         btn.data("series-year"),  // ✅ fixed key
    issue_url:     btn.data("issue-url")
  };

  $.post("addToWanted.php", payload, function(resp) {
    if (resp.success || resp === "Added" || resp.status === "success") {
      btn
        .text("Added")
        .removeClass("btn-primary")
        .addClass("btn-secondary")
        .prop("disabled", true);
    } else if (resp.status === "duplicate" || resp === "Already added") {
      btn
        .text("Added")
        .removeClass("btn-primary")
        .addClass("btn-secondary")
        .prop("disabled", true);
    } else {
      alert(resp.message || "Couldn’t add to wanted list.");
    }
  }, "json").fail(function(xhr) {
    alert("Server error while adding to wanted.\n\n" + xhr.responseText);
  });
});

// --- Sale form submission handler ---
$(document).on("submit", "#editSaleFormDashboard", function (e) {
  e.preventDefault();
  const formData = $(this).serialize();

  $.post("addListing.php", formData, function (response) {
    if (response.status === "success") {
      alert("Comic listed for sale!");
      bootstrap.Modal.getInstance(document.getElementById("editSaleModalDashboard")).hide();
    } else {
      alert(response.message || "Listing failed.");
    }
  }, "json").fail(function () {
    alert("Server error while adding the listing.");
  });
});
</script>


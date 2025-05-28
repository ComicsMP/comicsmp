<?php
session_start();
require_once 'db_connection.php';

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

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}
$userId = $_SESSION['user_id'];

// Fetch selling comics data from comics_for_sale.
$sql = "SELECT Comic_Title, MIN(Years) AS Years, 
        GROUP_CONCAT(Issue_Number 
            ORDER BY SUBSTRING(Issue_Number, 2)+0 ASC, (Issue_Number LIKE '%-%') ASC, Issue_Number ASC 
            SEPARATOR ', ') AS issues, 
        GROUP_CONCAT(Issue_URL SEPARATOR ',') AS issue_urls,
        GROUP_CONCAT(id SEPARATOR ',') AS listing_ids,
        COUNT(*) AS count 
        FROM comics_for_sale 
        WHERE user_id = ? 
        GROUP BY Comic_Title
        ORDER BY Comic_Title ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$sellingSeries = [];
while ($row = $result->fetch_assoc()){
    $sellingSeries[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Selling Comics</title>
  <!-- Bootstrap Icons only -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <!-- Swiper CSS for modal slider -->
  <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">
  <style>
    /* Base Styles */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Roboto', sans-serif; background: #f4f4f4; color: #333; padding-bottom: 60px; }
    a { text-decoration: none; color: inherit; }
    
    /* Top Header */
    .top-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #000;
    padding: 12px 16px;
    color: #fff;
  }
  .top-header .logo img {
    height: 38px;
  }
  .top-header .icons a {
    color: #fff;
    font-size: 20px;
    margin-left: 12px;
  }
    
    /* Header Row */
    .header-row {
      display: flex; justify-content: flex-end; align-items: center;
      background: #007BFF; padding: 10px 15px; color: #fff;
    }
    .header-row .plus-icon { margin-right: auto; font-size: 24px; }
    .header-row .view-toggle { display: flex; gap: 15px; }
    .header-row .view-toggle a { color: #fff; font-size: 20px; opacity: 0.7; cursor: pointer; }
    .header-row .view-toggle a.active { opacity: 1; }
    
    /* Card/Grid View Styles */
    .card-container { display: flex; flex-wrap: wrap; justify-content: center; padding: 10px; }
    .card {
      background: #fff; margin: 5px; padding: 10px; border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s ease;
      cursor: pointer; width: calc(100% - 5px); max-width: 600px; position: relative;
    }
    .card:hover { transform: translateY(-3px); }
    .card h3 { font-size: 18px; margin-bottom: 5px; color: #000; }
    .card .details { font-size: 14px; margin-bottom: 3px; color: #575757; }
    .card .count { font-size: 16px; font-weight: bold; color: #0056b3; }
    
    /* Action Buttons for Group Edit/Delete */
    .card .actions { margin-top: 8px; display: flex; gap: 8px; }
    .card .actions button {
  font-size: 12px;
  padding: 5px 10px;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  box-shadow: none;
  transition: all 0.2s ease-in-out;
  font-weight: 500;
}

.card .actions .delete-btn {
  background: #f67280;
  color: #fff;
}

.card .actions .expand-btn {
  background: #355c7d;
  color: #fff;
}

.card .actions button:hover {
  filter: brightness(1.1);
}

    
    /* Expandable Content for Covers */
    .expandable-content { display: none; margin-top: 10px; border-top: 1px solid #ddd; padding-top: 10px; }
    
    /* Responsive Cover Grid Styles */
    .cover-grid {
      display: grid;
      gap: 5px;
    }
    @media (max-width:480px) {
      .cover-grid {
        grid-template-columns: repeat(3, 1fr);
      }
      .cover-grid img {
        width: 100%;
        height: auto;
      }
      .cover-modal .modal-box {
        margin: auto;
      }
    }
    @media (min-width:481px) {
      .cover-grid {
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
      }
      .cover-grid img {
        width: 100%;
        height: auto;
      }
    }
    
    /* List View Styles */
    .list-view table {
      width: 100%; border-collapse: collapse; background: #fff; margin: 0;
    }
    .list-view th, .list-view td {
      padding: 10px; border: 1px solid #ddd; text-align: left;
    }
    .list-view th { background: #575757; color: #fff; }
    
    /* Bottom Navigation Bar */
     .bottom-nav {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #000;
    display: flex;
    justify-content: space-around;
    padding: 6px 0;       /* reduced vertical padding */
    font-size: 12px;      /* smaller label text */
    color: #fff;
  }
  .bottom-nav a {
    flex: 1;
    text-align: center;
    padding: 4px 0;       /* you can tweak this too */
    color: #fff;
  }
  .bottom-nav a i {
    display: block;
    font-size: 18px;      /* smaller icon */
    margin-bottom: 0;     /* remove extra gap */
  }
    
    /* Modal Overlay for Cover Details (Popup with Swiper) */
    .cover-modal {
      position: fixed; top: 0; left: 0;
      width: 100%; height: 100%;
      background: rgba(0,0,0,0.5); display: none; z-index: 10000;
      align-items: center; justify-content: center; padding: 20px;
    }
    .cover-modal .modal-box {
      background: #fff; padding: 15px; border-radius: 8px;
      width: 90%; max-width: 400px; position: relative; text-align: center;
    }
    .cover-modal .modal-box img {
      width: 100%; height: auto; border-radius: 4px;
      display: block; margin-bottom: 10px;
    }
    /* We now display details in a paragraph below the image */
    .cover-modal .modal-box p {
      font-size: 14px;
      margin-bottom: 15px;
      text-align: center;
    }
    .cover-modal .modal-box .modal-actions {
      margin-bottom: 10px;
      position: relative;
      z-index: 2;
    }
    .cover-modal .modal-box .modal-actions button {
      font-size: 14px;
      padding: 6px 10px;
      margin: 0 5px;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .cover-modal .modal-box .btn-edit { background: #007BFF; color: #fff; }
    .cover-modal .modal-box .btn-delete { background: #D32F2F; color: #fff; }
    .cover-modal .modal-box .close-modal {
      position: absolute;
      top: 5px;
      right: 5px;
      background: #fff;
      border: 2px solid #000;
      font-size: 32px;
      line-height: 1;
      cursor: pointer;
      color: #000;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      z-index: 9999;
    }
    
    /* Swiper Pagination (modest margin) */
    .swiper-pagination {
      margin-top: 10px !important;
      position: relative;
      z-index: 1;
    }
  </style>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
  <script>
    // Updated addslashes function with safe check.
    function addslashes(str) {
      if (typeof str !== "string") {
        str = (str !== undefined && str !== null) ? str.toString() : "";
      }
      return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
    }
    
    // Create a mapping for condition abbreviations.
    var conditionMapping = {
      "10.0": "Gem Mint",
      "9.9": "Mint",
      "9.8": "NM/M",
      "9.6": "NM+",
      "9.4": "NM",
      "9.2": "NM-",
      "9.0": "VF/NM",
      "8.5": "VF+",
      "8.0": "VF",
      "7.5": "VF-",
      "7.0": "FN/VF",
      "6.5": "FN+",
      "6.0": "FN",
      "5.5": "FN-",
      "5.0": "VG/FN",
      "4.5": "VG+",
      "4.0": "VG",
      "3.5": "VG-",
      "3.0": "G/VG",
      "2.5": "G",
      "2.0": "G",
      "1.8": "G-",
      "1.5": "Fa/G",
      "1.0": "Fa",
      "0.5": "Poor"
    };
    
    $(document).ready(function(){
      // Show grid view by default; hide list view.
      $('#cardView').show();
      $('#listView').hide();
      
      // Toggle between grid and list views.
      $('.view-toggle a').click(function(){
        var view = $(this).data('view');
        $('.view-toggle a').removeClass('active');
        $(this).addClass('active');
        if(view === 'card'){
          $('#cardView').show();
          $('#listView').hide();
        } else {
          $('#listView').show();
          $('#cardView').hide();
        }
      });
      
      // Initialize each card's expanded state.
      $('.card').each(function(){
          $(this).data('expanded', false);
      });
      
      // Expand card when clicking anywhere on the card (except on a button).
      $('.card').click(function(e){
        if ($(e.target).closest('button').length > 0) return;
        var card = $(this);
        var title = card.find('h3').text();
        var contentDiv = card.find('.expandable-content');
        if (!card.data('expanded')) {
          if (!contentDiv.data('loaded')) {
            $.ajax({
              url: 'loadSellingCovers.php',
              data: { title: title },
              dataType: "json",
              success: function(data) {
                var html = '<div class="cover-grid">';
                $.each(data, function(index, cover) {
                  html += '<img src="'+ cover.image +'" alt="Cover" ';
                  html += 'data-comic-title="'+ addslashes(cover.Comic_Title) +'" ';
                  html += 'data-issue-number="'+ addslashes(cover.Issue_Number) +'" ';
                  html += 'data-years="'+ addslashes(cover.Years) +'" ';
                  html += 'data-issue-url="'+ addslashes(cover.Issue_URL) +'" ';
                  html += 'data-id="'+ cover.id +'"';
                  html += '>';
                });
                html += '</div>';
                contentDiv.html(html);
                // Attach cover data to each image.
                contentDiv.find('.cover-grid img').each(function(index){
                  $(this).data('coverData', data[index]);
                });
                contentDiv.data('loaded', true);
                contentDiv.slideDown();
                card.data('expanded', true);
              },
              error: function(xhr, status, error) {
                contentDiv.html('<p>Error loading covers.</p>');
                contentDiv.slideDown();
                card.data('expanded', true);
              }
            });
          } else {
            contentDiv.slideDown();
            card.data('expanded', true);
          }
        }
      });
      
      // Delegate click on cover image in the cover grid to open the modal popup with Swiper slider.
      $(document).on('click', '.cover-grid img', function(e){
          e.preventDefault();
          e.stopPropagation();
          var $img = $(this);
          var covers = [];
          var clickedIndex = 0;
          // Get parent card and store it in the modal's data.
          var parentCard = $img.closest('.card');
          $('.cover-modal').data('parentCard', parentCard);
          $img.closest('.cover-grid').find('img').each(function(index){
              var coverData = $(this).data('coverData');
              covers.push(coverData);
              if(this === $img[0]) {
                  clickedIndex = index;
              }
          });
          openCoverModalSlider(covers, clickedIndex);
      });
      
      // Function to open modal with Swiper slider.
      // Each slide displays the cover image, and a paragraph with details:
      // Issue, Condition (numeric value plus abbreviation), Graded, and Price.
      // Also includes Edit and Delete buttons.
      function openCoverModalSlider(covers, initialSlide = 0) {
          var modalHtml = '<div class="modal-box">';
          modalHtml += '<button class="close-modal">&times;</button>';
          modalHtml += '<div class="swiper-container" style="overflow: hidden;"><div class="swiper-wrapper">';
          covers.forEach(function(cover) {
              modalHtml += '<div class="swiper-slide" style="width: 100%;" data-id="'+ addslashes(cover.id) +'">';
              modalHtml += '<img src="' + cover.image + '" alt="Cover" style="width: 100%;">';
              modalHtml += '<p style="text-align: center;">';
              modalHtml += 'Issue: ' + cover.Issue_Number + '<br>';
              modalHtml += 'Condition: ' + cover.comic_condition + ' (' + conditionMapping[cover.comic_condition] + ')<br>';
              modalHtml += 'Graded: ' + (cover.graded == 1 ? "Yes" : "No") + '<br>';
              modalHtml += 'Price: $' + parseFloat(cover.price).toFixed(2) + ' ' + cover.preferred_currency;
              modalHtml += '</p>';
              modalHtml += '<div class="modal-actions">';
              modalHtml += '<button class="btn-edit" onclick="closeCoverModal(); showSingleEditModal(\'' + addslashes(cover.id) + '\');">Edit</button>';
              modalHtml += '<button class="btn-delete" onclick="deleteSaleListingSingle(\'' + addslashes(cover.id) + '\', this);">Delete</button>';
              modalHtml += '</div>';
              modalHtml += '</div>';
          });
          modalHtml += '</div><div class="swiper-pagination" style="margin-top: 10px;"></div></div>';
          modalHtml += '</div>';
          $('.cover-modal').html(modalHtml).fadeIn();
          new Swiper('.swiper-container', {
              slidesPerView: 1,
              spaceBetween: 0,
              centeredSlides: false,
              watchOverflow: true,
              initialSlide: initialSlide,
              pagination: { el: '.swiper-pagination', clickable: true },
              loop: false
          });
      }
      
      // Delegate event for the close (X) button.
      $(document).on('click', '.modal-box .close-modal', function(e) {
        e.stopPropagation();
        $('.cover-modal').fadeOut();
      });
      
      // Global function to close the cover modal.
      window.closeCoverModal = function() {
          $('.cover-modal').fadeOut();
      };
      
      // Delete function for a single sale listing (in the popup).
      // Sends an AJAX request and on success, closes the modal and removes the cover from the grid.
      window.deleteSaleListingSingle = function(listingId, btn) {
        $.ajax({
          url: "../deleteSingleSale.php",
          method: "POST",
          data: { listing_id: listingId },
          dataType: "json",
          success: function(response) {
            if(response.status === "success") {
              $('.cover-modal').fadeOut();
              var parentCard = $('.cover-modal').data('parentCard');
              if(parentCard) {
                parentCard.find('.cover-grid img').filter(function(){
                  return String($(this).data('id')) === String(listingId);
                }).fadeOut(300, function(){
                  $(this).remove();
                });
              }
            } else {
              alert("Delete failed: " + response.message);
            }
          },
          error: function(xhr, status, error) {
            alert("AJAX error: " + error);
          }
        });
      };
      
      // DELETE ALL (Bulk Delete) function for a series.
      window.deleteSaleListing = function(listingIds, btn) {
        if(!confirm("Warning: Deleting this series will remove all issues for sale in this series. Continue?")){
          return;
        }
        $.ajax({
          url: "../deleteSale.php",
          method: "POST",
          data: { listing_id: listingIds },
          dataType: "json",
          success: function(response) {
            if(response.status === "success") {
              // Remove the entire card.
              $(btn).closest(".card").fadeOut(function(){
                $(this).remove();
              });
            } else {
              alert("Delete failed: " + response.message);
            }
          },
          error: function(xhr, status, error) {
            alert("AJAX error: " + error);
          }
        });
      };
      
      // BULK EDIT MODAL using Shadow DOM for Bootstrap isolation.
      function initBulkEditModal() {
        var container = document.getElementById("bulkEditShadowContainer");
        if (!container.shadowRoot) {
          var shadow = container.attachShadow({ mode: "open" });
          shadow.innerHTML = `
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
            <style>
              .modal-content { background-color: #fff !important; }
              .form-control, .form-select {
                border: 1px solid #ccc;
                border-radius: 4px;
                padding: .375rem .75rem;
              }
              .modal.show { display: block !important; }
            </style>
            <div class="modal" id="bulkEditModal" tabindex="-1" aria-labelledby="bulkEditModalLabel" aria-hidden="true" style="display:none;">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="bulkEditModalLabel">Bulk Edit Series</h5>
                    <button type="button" class="btn-close" id="bulkShadowCloseBtn"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" id="shadowBulkEditListingIds" />
                    <div class="mb-3">
                      <label class="form-label">New Price</label>
                      <input type="number" class="form-control" id="shadowBulkEditPrice" placeholder="Enter Price">
                    </div>
                    <div class="mb-3">
                      <label class="form-label">New Condition</label>
                      <select class="form-select" id="shadowBulkEditCondition">
                        <option value="">Select Condition</option>
                        <?php foreach($gradeMapping as $score => $label): ?>
                          <option value="<?php echo $score; ?>">
                            <?php echo str_replace(".0","",$score).' ('.$label.')'; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" id="bulkShadowSaveEdit" class="btn btn-primary">Save</button>
                    <button type="button" id="bulkShadowCancelEdit" class="btn btn-secondary">Cancel</button>
                  </div>
                </div>
              </div>
            </div>
          `;
          shadow.querySelector("#bulkShadowCloseBtn").addEventListener("click", function(){
            shadow.querySelector("#bulkEditModal").style.display = "none";
          });
          shadow.querySelector("#bulkShadowCancelEdit").addEventListener("click", function(){
            shadow.querySelector("#bulkEditModal").style.display = "none";
          });
          shadow.querySelector("#bulkShadowSaveEdit").addEventListener("click", function(){
            var listingIds = shadow.querySelector("#shadowBulkEditListingIds").value;
            var price = shadow.querySelector("#shadowBulkEditPrice").value;
            var condition = shadow.querySelector("#shadowBulkEditCondition").value;
            $.ajax({
              url: "../editSale.php",
              method: "POST",
              data: { listing_id: listingIds, price: price, condition: condition },
              dataType: "json",
              success: function(response){
                if(response.status === "success"){
                  alert("Bulk update successful.");
                  shadow.querySelector("#bulkEditModal").style.display = "none";
                  location.reload();
                } else {
                  alert("Update failed: " + response.message);
                }
              },
              error: function(){
                alert("Server error during bulk update.");
              }
            });
          });
        }
      }
      
      function showBulkEditModal(listingIds) {
        initBulkEditModal();
        var shadow = document.getElementById("bulkEditShadowContainer").shadowRoot;
        shadow.querySelector("#shadowBulkEditListingIds").value = listingIds;
        shadow.querySelector("#shadowBulkEditPrice").value = "";
        shadow.querySelector("#shadowBulkEditCondition").value = "";
        shadow.querySelector("#bulkEditModal").style.display = "block";
      }
      
      // Override bulk edit function.
      window.editSaleListingGroup = function(listingIds, btn) {
        showBulkEditModal(listingIds);
      };
      
      // SINGLE EDIT MODAL using Shadow DOM for Bootstrap isolation.
      function initSingleEditModal() {
        var container = document.getElementById("singleEditShadowContainer");
        if(!container.shadowRoot) {
          var shadow = container.attachShadow({ mode: "open" });
          shadow.innerHTML = `
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
            <style>
              .modal-content { background-color: #fff !important; }
              .form-control, .form-select {
                border: 1px solid #ccc;
                border-radius: 4px;
                padding: .375rem .75rem;
              }
              .modal.show { display: block !important; }
            </style>
            <div class="modal" id="singleEditModal" tabindex="-1" aria-labelledby="singleEditModalLabel" aria-hidden="true" style="display:none;">
              <div class="modal-dialog">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="singleEditModalLabel">Edit Single Listing</h5>
                    <button type="button" class="btn-close" id="shadowCloseBtn"></button>
                  </div>
                  <div class="modal-body">
                    <input type="hidden" id="shadowSingleEditListingId" />
                    <div class="mb-3">
                      <label class="form-label">New Price</label>
                      <input type="number" class="form-control" id="shadowSingleEditPrice" placeholder="Enter Price">
                    </div>
                    <div class="mb-3">
                      <label class="form-label">New Condition</label>
                      <select class="form-select" id="shadowSingleEditCondition">
                        <option value="">Select Condition</option>
                        <?php foreach($gradeMapping as $score => $label): ?>
                          <option value="<?php echo $score; ?>">
                            <?php echo str_replace(".0","",$score).' ('.$label.')'; ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                  </div>
                  <div class="modal-footer">
                    <button type="button" id="shadowSaveSingleEdit" class="btn btn-primary">Save</button>
                    <button type="button" id="shadowCancelSingleEdit" class="btn btn-secondary">Cancel</button>
                  </div>
                </div>
              </div>
            </div>
          `;
          shadow.querySelector("#shadowCloseBtn").addEventListener("click", function(){
              shadow.querySelector("#singleEditModal").style.display = "none";
          });
          shadow.querySelector("#shadowCancelSingleEdit").addEventListener("click", function(){
              shadow.querySelector("#singleEditModal").style.display = "none";
          });
          shadow.querySelector("#shadowSaveSingleEdit").addEventListener("click", function(){
              var listingId = shadow.querySelector("#shadowSingleEditListingId").value;
              var price = shadow.querySelector("#shadowSingleEditPrice").value;
              var condition = shadow.querySelector("#shadowSingleEditCondition").value;
              $.ajax({
                  url: "../editSaleSingle.php",
                  method: "POST",
                  data: { listing_id: listingId, price: price, condition: condition },
                  dataType: "json",
                  success: function(response){
                      if(response.status === "success"){
                          alert("Update successful.");
                          shadow.querySelector("#singleEditModal").style.display = "none";
                          location.reload();
                      } else {
                          alert("Update failed: " + response.message);
                      }
                  },
                  error: function(){
                      alert("Server error during single update.");
                  }
              });
          });
        }
      }
      
      window.showSingleEditModal = function(listingId) {
        initSingleEditModal();
        var shadow = document.getElementById("singleEditShadowContainer").shadowRoot;
        shadow.querySelector("#shadowSingleEditListingId").value = listingId;
        shadow.querySelector("#shadowSingleEditPrice").value = "";
        shadow.querySelector("#shadowSingleEditCondition").value = "";
        shadow.querySelector("#singleEditModal").style.display = "block";
      };

      // NEW: Expand button click handler function.
      window.expandCardHandler = function(button) {
        var card = $(button).closest('.card');
        var title = card.find('h3').text();
        var contentDiv = card.find('.expandable-content');
        if (card.data('expanded')) {
          contentDiv.slideUp();
          card.data('expanded', false);
        } else {
          if (!contentDiv.data('loaded')) {
            $.ajax({
              url: 'loadSellingCovers.php',
              data: { title: title },
              dataType: "json",
              success: function(data) {
                var html = '<div class="cover-grid">';
                $.each(data, function(index, cover) {
                  html += '<img src="'+ cover.image +'" alt="Cover" ';
                  html += 'data-comic-title="'+ addslashes(cover.Comic_Title) +'" ';
                  html += 'data-issue-number="'+ addslashes(cover.Issue_Number) +'" ';
                  html += 'data-years="'+ addslashes(cover.Years) +'" ';
                  html += 'data-issue-url="'+ addslashes(cover.Issue_URL) +'" ';
                  html += 'data-id="'+ cover.id +'"';
                  html += '>';
                });
                html += '</div>';
                contentDiv.html(html);
                contentDiv.find('.cover-grid img').each(function(index){
                  $(this).data('coverData', data[index]);
                });
                contentDiv.data('loaded', true);
                contentDiv.slideDown();
                card.data('expanded', true);
              },
              error: function(xhr, status, error) {
                contentDiv.html('<p>Error loading covers.</p>');
                contentDiv.slideDown();
                card.data('expanded', true);
              }
            });
          } else {
            contentDiv.slideDown();
            card.data('expanded', true);
          }
        }
      };
    });
  </script>
</head>
<body>
  
  
  <!-- Header Row with Toggle Icons -->
  <div class="header-row">
    <div class="plus-icon">
      <i class="bi bi-plus-circle"></i>
    </div>
    <div class="view-toggle">
      <a href="javascript:void(0);" id="gridIcon" data-view="card" class="active">
        <i class="bi bi-grid-3x3-gap-fill"></i>
      </a>
      <a href="javascript:void(0);" id="listIcon" data-view="list">
        <i class="bi bi-list"></i>
      </a>
    </div>
  </div>
  
  <!-- Card/Grid View Section -->
  <div id="cardView" class="card-container">
    <?php if (!empty($sellingSeries)): ?>
      <?php foreach ($sellingSeries as $series): ?>
        <div class="card">
          <h3><?php echo htmlspecialchars($series['Comic_Title']); ?></h3>
          <p class="details">Years: <?php echo htmlspecialchars($series['Years']); ?></p>
          <p class="details">Issues: <?php echo htmlspecialchars($series['issues']); ?></p>
          <p class="count">Count: <?php echo htmlspecialchars($series['count']); ?></p>
          <!-- Action buttons for group editing/deleting -->
          <div class="actions">
            <button class="edit-btn" onclick="editSaleListingGroup('<?php echo addslashes(htmlspecialchars($series['listing_ids'])); ?>', this)">
              <i class="bi bi-pencil"></i> Edit All
            </button>
            <button class="delete-btn" onclick="deleteSaleListing('<?php echo addslashes(htmlspecialchars($series['listing_ids'])); ?>', this)">
              <i class="bi bi-trash"></i> Delete All
            </button>
            <button class="expand-btn" onclick="expandCardHandler(this)">Expand</button>
          </div>
          <!-- Expandable content for individual covers -->
          <div class="expandable-content"></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="text-align:center; padding:20px;">No selling comics found.</p>
    <?php endif; ?>
  </div>
  
  <!-- List View Section (without edit/delete actions) -->
  <div id="listView" class="list-view" style="display:none;">
    <?php if (!empty($sellingSeries)): ?>
      <table>
        <thead>
          <tr>
            <th>Comic Title</th>
            <th>Years</th>
            <th>Issues</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($sellingSeries as $series): ?>
            <tr>
              <td><?php echo htmlspecialchars($series['Comic_Title']); ?></td>
              <td><?php echo htmlspecialchars($series['Years']); ?></td>
              <td><?php echo htmlspecialchars($series['issues']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="text-align:center; padding:20px;">No selling comics found.</p>
    <?php endif; ?>
  </div>
  
  <!-- Bottom Navigation Bar -->
  <div class="bottom-nav">
  <a href="dashboard.php">
    <i class="bi bi-house-fill"></i>
    Home
  </a>
  <a href="wanted.php">
    <i class="bi bi-heart-fill"></i>
    Wanted
  </a>
  <a href="selling.php">
    <i class="bi bi-tag-fill"></i>
    Selling
  </a>
  <a href="matches.php">
    <i class="bi bi-people-fill"></i>
    Matches
  </a>
</div>
  
  <!-- Shadow DOM Container for Isolated Bootstrap Bulk Edit Modal -->
  <div id="bulkEditShadowContainer"></div>
  
  <!-- Shadow DOM Container for Isolated Bootstrap Single Edit Modal -->
  <div id="singleEditShadowContainer"></div>
  
  <!-- Modal Overlay for Cover Details (Popup with Swiper Slider) -->
  <div class="cover-modal" style="display:none;"></div>
</body>
</html>

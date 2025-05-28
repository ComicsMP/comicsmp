<?php
session_start();
require_once 'db_connection.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}
$userId = $_SESSION['user_id'];

// Fetch wanted comics data with adjusted ordering logic for issue numbers
$sql = "SELECT Comic_Title, MIN(Years) AS Years, 
        GROUP_CONCAT(Issue_Number 
            ORDER BY SUBSTRING(Issue_Number, 2)+0 ASC, (Issue_Number LIKE '%-%') ASC, Issue_Number ASC 
            SEPARATOR ', ') AS issues, 
        COUNT(*) AS count 
        FROM wanted_items 
        WHERE user_id = ? 
        GROUP BY Comic_Title
        ORDER BY Comic_Title ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$wantedSeries = [];
while ($row = $result->fetch_assoc()) {
    $wantedSeries[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Wanted Comics</title>
  <!-- Bootstrap Icons for toggle icons -->
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
    
    /* Header Row with Toggle Icons */
    .header-row {
      display: flex;
      justify-content: flex-end;
      align-items: center;
      background: #007BFF;
      padding: 10px 15px;
      color: #fff;
    }
    .header-row .plus-icon { margin-right: auto; font-size: 24px; }
    .header-row .view-toggle { display: flex; gap: 15px; }
    .header-row .view-toggle a {
      color: #fff;
      font-size: 20px;
      opacity: 0.7;
      cursor: pointer;
    }
    .header-row .view-toggle a.active { opacity: 1; }
    
    /* Card/Grid View Styles */
    .card-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      padding: 10px;
    }
    .card {
      background: #fff;
      margin: 5px;
      padding: 10px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: transform 0.2s ease;
      cursor: pointer;
      width: calc(100% - 5px);
      max-width: 600px;
      position: relative;
    }
    .card:hover { transform: translateY(-3px); }
    .card h3 { font-size: 18px; margin-bottom: 5px; color: #000; }
    .card .details { font-size: 14px; margin-bottom: 3px; color: #575757; }
    .card .count { font-size: 16px; font-weight: bold; color: #0056b3; }
    /* Bulk Delete button on front card */
    .card .actions {
      margin-top: 8px;
      display: flex;
      gap: 8px;
    }
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
    .expandable-content {
      display: none;
      margin-top: 10px;
      border-top: 1px solid #ddd;
      padding-top: 10px;
    }
    
    /* List View Styles */
    .list-view table {
      width: 100%;
      border-collapse: collapse;
      background: #fff;
      margin: 0;
    }
    .list-view th, .list-view td {
      padding: 10px;
      border: 1px solid #ddd;
      text-align: left;
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

    
    /* Modal Overlay for Cover Details (Swiper Popup) */
    .cover-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.5);
      display: none;
      z-index: 10000;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .cover-modal .modal-box {
      background: #fff;
      padding: 15px;
      border-radius: 8px;
      width: 90%;
      max-width: 400px;
      position: relative;
      text-align: center;
    }
    .cover-modal .modal-box img {
      width: 100%;
      height: auto;
      border-radius: 4px;
      display: block;
      margin-bottom: 10px;
    }
    .cover-modal .modal-box h4 {
      margin: 10px 0;
      text-align: center;
    }
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
    
    /* Responsive Cover Grid Styles */
    @media (max-width:480px) {
      .cover-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 5px;
      }
      .cover-grid img {
        width: 100%;
        height: auto;
      }
      .cover-modal .modal-box { width:90%; margin:auto; }
    }
    @media (min-width:481px) {
      .cover-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 5px;
      }
      .cover-grid img {
        width: 100%;
        height: auto;
      }
    }
    
    /* Adjust Swiper pagination to be pushed down modestly */
    .swiper-pagination {
      margin-top: 10px !important;
      position: relative;
      z-index: 1;
    }
  </style>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <!-- Swiper JS for modal slider -->
  <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
  <script>
    // Helper function to escape quotes for JavaScript.
    function addslashes(str) {
      return str.replace(/'/g, "\\'").replace(/"/g, '\\"');
    }
    
    // Delete function as originally implemented, with added removal from both the swiper slider and the cover grid.
    function deleteWanted(comicTitle, issueNumber, years, issueUrl, btn) {
      console.log("deleteWanted() called with:", comicTitle, issueNumber, years, issueUrl);
      $.ajax({
        url: "../deleteWanted.php",
        method: "POST",
        data: {
           comic_title: comicTitle,
           issue_number: issueNumber,
           years: years,
           issue_url: issueUrl
        },
        dataType: "json",
        success: function(response) {
          console.log("deleteWanted() response:", response);
          if(response.status === "success") {
            // Update the count in the parent card.
            var parentCard = $('.cover-modal').data('parentCard');
            if (parentCard) {
              var countElem = parentCard.find('.count');
              var currentCount = parseInt(countElem.text(), 10);
              if (!isNaN(currentCount) && currentCount > 0) {
                countElem.text(currentCount - 1);
              }
            }
            // Remove the corresponding cover from the expandable content (cover grid).
            $('.cover-grid img[data-issue-number="'+ issueNumber +'"]').fadeOut(function(){
              $(this).remove();
            });
            // Remove the corresponding swiper slide.
            $('.swiper-slide[data-issue-number="'+ issueNumber +'"]').fadeOut(function(){
              $(this).remove();
            });
            // Close the modal.
            $('.cover-modal').fadeOut();
          } else {
            alert("Delete failed: " + response.message);
          }
        },
        error: function(xhr, status, error) {
          console.error("deleteWanted() AJAX error:", status, error, xhr.responseText);
          alert("AJAX error: " + error);
        }
      });
    }
    
    $(document).ready(function(){
      console.log("wanted.php: Document ready.");
      
      // Set default view to card view.
      $('#cardView').show();
      $('#listView').hide();

      // Toggle views.
      $('.view-toggle a').click(function(){
        var view = $(this).data('view');
        console.log("Switching view to:", view);
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
      
      // Expand card to load covers when clicking anywhere on the card (except on a button).
      $('.card').click(function(e){
        if ($(e.target).closest('button').length > 0) return;
        var card = $(this);
        var title = card.find('h3').text();
        var contentDiv = card.find('.expandable-content');
        console.log("Card clicked for title:", title);
        if (!card.data('expanded')) {
          if (!contentDiv.data('loaded')) {
            $.ajax({
              url: 'loadWantedCovers.php',
              data: { title: title },
              dataType: "json",
              success: function(data) {
                console.log("AJAX success in loadWantedCovers.php:", data);
                // Build the cover grid using the new class.
                var html = '<div class="cover-grid">';
                $.each(data, function(index, cover) {
                  html += '<img src="'+ cover.image +'" alt="Cover" ';
                  html += 'data-comic-title="'+ addslashes(cover.Comic_Title) +'" ';
                  html += 'data-issue-number="'+ addslashes(cover.Issue_Number) +'" ';
                  html += 'data-years="'+ addslashes(cover.Years) +'" ';
                  html += 'data-issue-url="'+ addslashes(cover.Issue_URL) +'">';
                });
                html += '</div>';
                contentDiv.html(html);
                contentDiv.data('loaded', true);
                contentDiv.slideDown();
                card.data('expanded', true);
              },
              error: function(xhr, status, error) {
                console.error("AJAX error in loadWantedCovers.php:", status, error, xhr.responseText);
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
      
      // Bulk Delete for Wanted: Delete entire series.
      window.deleteWantedSeries = function(comicTitle, btn) {
        console.log("Bulk delete triggered for comicTitle:", comicTitle);
        if(!confirm("Warning: Deleting this series will remove all wanted items for this series. Continue?")){
          return;
        }
        $.ajax({
          url: "../deleteWanted.php",
          method: "POST",
          data: { comic_title: comicTitle },
          dataType: "json",
          success: function(response) {
            console.log("Bulk delete response:", response);
            if(response.status === "success") {
              $(btn).closest(".card").fadeOut(function(){
                $(this).remove();
              });
            } else {
              alert("Delete failed: " + response.message);
            }
          },
          error: function(xhr, status, error) {
            console.error("Bulk delete AJAX error:", status, error, xhr.responseText);
            alert("AJAX error: " + error);
          }
        });
      };

      // Delegate click on cover image in the cover grid to open the modal slider.
      $(document).on('click', '.cover-grid img', function(e){
        e.preventDefault();
        e.stopPropagation();
        const $img = $(this);
        const covers = [];
        let clickedIndex = 0;
        $img.closest('.cover-grid').find('img').each(function(index){
          covers.push({
            comic_title: $(this).data('comic-title'),
            issue_number: $(this).data('issue-number'),
            years: $(this).data('years'),
            issue_url: $(this).data('issue-url'),
            image: $(this).attr('src')
          });
          if(this === $img[0]){
            clickedIndex = index;
          }
        });
        openCoverModalSlider(covers, clickedIndex);
      });
      
      // Function to open modal with Swiper slider (one slide at a time) including red Delete button.
      function openCoverModalSlider(covers, initialSlide = 0) {
        let modalHtml = '<div class="modal-box">';
        modalHtml += '<button class="close-modal">&times;</button>';
        // Swiper container with overflow hidden so only one slide is visible.
        modalHtml += '<div class="swiper-container" style="overflow: hidden;"><div class="swiper-wrapper">';
        covers.forEach(function(cover) {
          modalHtml += '<div class="swiper-slide" style="width: 100%;" data-issue-number="'+ addslashes(cover.issue_number) +'">';
          modalHtml += '<img src="' + cover.image + '" alt="Cover" style="width: 100%;">';
          modalHtml += '<h4 style="text-align: center;">' + cover.comic_title + '</h4>';
          modalHtml += '<p style="text-align: center;">Years: ' + cover.years + '<br>Issue: ' + cover.issue_number + '</p>';
          modalHtml += '<div class="modal-actions">';
          modalHtml += '<button class="btn-delete" onclick="deleteWanted(\'' + addslashes(cover.comic_title) + '\', \'' + addslashes(cover.issue_number) + '\', \'' + addslashes(cover.years) + '\', \'' + addslashes(cover.issue_url) + '\', this);">Delete</button>';
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
      
      // Close cover modal when clicking on close button or outside modal box.
      $(document).on('click', '.cover-modal', function(e){
        if($(e.target).hasClass('cover-modal') || $(e.target).hasClass('close-modal')){
            console.log("Closing cover modal.");
            $('.cover-modal').fadeOut();
        }
      });

      // NEW: Expand button click handler function for Wanted page.
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
              url: 'loadWantedCovers.php',
              data: { title: title },
              dataType: "json",
              success: function(data) {
                var html = '<div class="cover-grid">';
                $.each(data, function(index, cover) {
                  html += '<img src="'+ cover.image +'" alt="Cover" ';
                  html += 'data-comic-title="'+ addslashes(cover.Comic_Title) +'" ';
                  html += 'data-issue-number="'+ addslashes(cover.Issue_Number) +'" ';
                  html += 'data-years="'+ addslashes(cover.Years) +'" ';
                  html += 'data-issue-url="'+ addslashes(cover.Issue_URL) +'">';
                });
                html += '</div>';
                contentDiv.html(html);
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
    <?php if (!empty($wantedSeries)): ?>
      <?php foreach ($wantedSeries as $series): ?>
        <div class="card">
          <h3><?php echo htmlspecialchars($series['Comic_Title']); ?></h3>
          <p class="details">Years: <?php echo htmlspecialchars($series['Years']); ?></p>
          <p class="details">Issues: <?php echo htmlspecialchars($series['issues']); ?></p>
          <p class="count">Count: <?php echo htmlspecialchars($series['count']); ?></p>
          <!-- Action button for bulk deletion ("Delete All") and Expand -->
          <div class="actions">
            <button class="delete-btn" onclick='deleteWantedSeries(<?php echo htmlspecialchars(json_encode($series['Comic_Title']), ENT_QUOTES, "UTF-8"); ?>, this)'>
              <i class="bi bi-trash"></i> Delete All
            </button>
            <button class="expand-btn" onclick="expandCardHandler(this)">Expand</button>
          </div>
          <!-- Expandable content for individual covers -->
          <div class="expandable-content"></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="text-align:center; padding:20px;">No wanted comics found.</p>
    <?php endif; ?>
  </div>
  
  <!-- List View Section -->
  <div id="listView" class="list-view" style="display:none;">
    <?php if (!empty($wantedSeries)): ?>
      <table>
        <thead>
          <tr>
            <th>Comic Title</th>
            <th>Years</th>
            <th>Issues</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($wantedSeries as $series): ?>
            <tr>
              <td><?php echo htmlspecialchars($series['Comic_Title']); ?></td>
              <td><?php echo htmlspecialchars($series['Years']); ?></td>
              <td><?php echo htmlspecialchars($series['issues']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="text-align:center; padding:20px;">No wanted comics found.</p>
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
  
  <!-- Modal Overlay for Cover Details (Swiper Popup) -->
  <div class="cover-modal" style="display:none;"></div>
</body>
</html>

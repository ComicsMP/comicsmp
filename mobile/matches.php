<?php
session_start();
require_once 'db_connection.php';

// Ensure the user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}
$userId = $_SESSION['user_id'];
// Load user’s default distance settings from profile
$defaultRadius = 500;
$distanceUnit = 'mi';

$userSettingsQuery = "SELECT default_max_radius, distance_unit FROM users WHERE id = ?";
$stmtPrefs = $conn->prepare($userSettingsQuery);
$stmtPrefs->bind_param("i", $userId);
$stmtPrefs->execute();
$stmtPrefs->bind_result($radius, $unit);
if ($stmtPrefs->fetch()) {
    $defaultRadius = $radius;
    $distanceUnit = $unit;
}
$stmtPrefs->close();


/**
 * Calculate distance using the Haversine formula.
 * Returns a string like "284 mi" or "457 km" (rounded to a whole number).
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2, $unit = 'mi') {
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    $dlon = $lon2 - $lon1;
    $dlat = $lat2 - $lat1;
    $a = pow(sin($dlat / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($dlon / 2), 2);
    $c = 2 * asin(sqrt($a));

    // Radius of Earth: 3956 miles or 6371 kilometers
    $r = ($unit === 'km') ? 6371 : 3956;
    return round($c * $r) . " " . $unit;
}

// Fetch match notifications (table match_notifications always has buyer_id and seller_id)
$sql = "
    SELECT 
        mn.buyer_id, 
        mn.seller_id, 
        mn.comic_title, 
        mn.issue_number, 
        mn.years, 
        mn.issue_url, 
        mn.cover_image,
        mn.match_time,
        buyer.city AS buyer_city,
        buyer.latitude AS buyer_lat,
        buyer.longitude AS buyer_lng,
        seller.city AS seller_city,
        seller.latitude AS seller_lat,
        seller.longitude AS seller_lng
    FROM match_notifications mn
    LEFT JOIN users buyer ON buyer.id = mn.buyer_id
    LEFT JOIN users seller ON seller.id = mn.seller_id
    WHERE mn.buyer_id = ? OR mn.seller_id = ?
    ORDER BY mn.match_time DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$matches = [];
$otherUserIds = [];
while ($row = $result->fetch_assoc()){
    // Patch missing keys if needed.
    if((!isset($row['buyer_id']) || !isset($row['seller_id'])) && isset($row['user_id'])) {
        if ($row['user_id'] == $userId) {
            $row['seller_id'] = $row['user_id'];
            $row['buyer_id'] = null;  
        } else {
            $row['buyer_id'] = $userId;
            $row['seller_id'] = $row['user_id'];
        }
    }
    $matches[] = $row;
    $otherUserId = ($row['buyer_id'] == $userId) ? $row['seller_id'] : $row['buyer_id'];
    $otherUserIds[] = $otherUserId;
}
$stmt->close();

// Fetch usernames, cities, and transaction/payment preferences for the other users.
$otherUserIds = array_unique($otherUserIds);
$userNamesMap = [];
if (!empty($otherUserIds)) {
    $in  = str_repeat('?,', count($otherUserIds) - 1) . '?';
    $sqlUsers = "SELECT id, username, city, preferred_transaction, preferred_payment FROM users WHERE id IN ($in)";
    $stmtU = $conn->prepare($sqlUsers);
    $types = str_repeat('i', count($otherUserIds));
    $stmtU->bind_param($types, ...$otherUserIds);
    $stmtU->execute();
    $resU = $stmtU->get_result();
    while ($u = $resU->fetch_assoc()){
        $userNamesMap[$u['id']] = [
            'username' => $u['username'],
            'city' => $u['city'],
            'preferred_transaction' => $u['preferred_transaction'],
            'preferred_payment' => $u['preferred_payment']
        ];
    }
    $stmtU->close();
}

// 1) load both hide/deleted flags
// 1) load both hide/deleted flags
$hiddenMatches  = [];
$deletedMatches = [];
$sqlH = "SELECT match_user_id, is_deleted FROM hidden_matches WHERE user_id = ?";
$stmtH = $conn->prepare($sqlH);
$stmtH->bind_param("i", $userId);
$stmtH->execute();
$resH = $stmtH->get_result();
while ($r = $resH->fetch_assoc()) {
    if ($r['is_deleted']) {
        $deletedMatches[] = (int)$r['match_user_id'];
    } else {
        $hiddenMatches[]  = (int)$r['match_user_id'];
    }
}
$stmtH->close();

// 2) group matches by the "other" user, skipping any permanently deleted
$groupedMatches = [];
foreach ($matches as $m) {
    $otherUserId = ($m['buyer_id'] == $userId) ? $m['seller_id'] : $m['buyer_id'];
    if (in_array($otherUserId, $deletedMatches, true)) {
        continue;
    }
    $groupedMatches[$otherUserId][] = $m;
}

// Helper: Get dynamic font size for the match count.
function getMatchFontSize($count) {
    $digits = strlen((string)$count);
    if ($digits == 1) {
        return "80px";
    } elseif ($digits == 2) {
        return "65px";
    } else {
        return "50px";
    }
}

// Calculate time-based stats for each match group (24h, wk, mo)
foreach ($groupedMatches as $otherId => &$matchArray) {
    $count24 = $countWeek = $countMonth = 0;
    $now = time();
    $actualMatches = array_filter($matchArray, function($item, $key) {
        return $key !== 'stats';
    }, ARRAY_FILTER_USE_BOTH);
    foreach ($actualMatches as $match) {
        if (isset($match['match_time'])) {
            $matchTime = strtotime($match['match_time']);
            if ($matchTime >= $now - 86400) { $count24++; }
            if ($matchTime >= $now - 604800) { $countWeek++; }
            if ($matchTime >= $now - 2592000) { $countMonth++; }
        }
    }
    $matchArray['stats'] = [
        '24h' => $count24,
        'wk' => $countWeek,
        'mo' => $countMonth
    ];
}
unset($matchArray);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- Mobile optimized meta -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>Matches</title>
  <!-- Bootstrap Icons & Swiper CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
  <link rel="stylesheet" href="https://unpkg.com/swiper/swiper-bundle.min.css">
  <style>
    /* Base reset */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Roboto', sans-serif; background: #f4f4f4; color: #333; padding-bottom: 60px; overflow-x: hidden; }
    a { text-decoration: none; color: inherit; }
    
    /* Top Header */
    .top-header { display: flex; justify-content: space-between; align-items: center; background: #1a1a1a; padding: 10px 15px; color: #fff; }
    .top-header .logo img { height: 40px; }
    .top-header .icons { display: flex; gap: 15px; }
    .top-header .icons a { color: #fff; font-size: 16px; }
    
    /* Header Row with Toggle Icons */
    .header-row { display: flex; justify-content: flex-end; align-items: center; background: #007BFF; padding: 10px 15px; color: #fff; }
    .header-row .plus-icon { margin-right: auto; font-size: 24px; }
    .header-row .view-toggle { display: flex; gap: 15px; }
    .header-row .view-toggle a { color: #fff; font-size: 20px; opacity: 0.7; cursor: pointer; }
    .header-row .view-toggle a.active { opacity: 1; }
    
    /* Filter Area */
    .filter-area { background: #eee; padding: 12px 10px; margin-bottom: 4px; }
    .filter-controls {
      display: flex;
      flex-direction: row;
      align-items: center;
      gap: 10px;
      padding: 0 10px;
      background: #f9f9f9;
      margin-bottom: 4px;
    }
    .filter-controls label { font-size: 14px; }
    .filter-controls input[type=range] {
      -webkit-appearance: none;
      width: 60%;
      height: 8px;
      background: #ddd;
      border-radius: 5px;
      outline: none;
      margin: 8px 0;
    }
    .filter-controls input[type=range]::-webkit-slider-thumb {
      -webkit-appearance: none;
      width: 20px;
      height: 20px;
      background: #007BFF;
      border-radius: 50%;
      cursor: pointer;
      border: 2px solid #fff;
    }
    .filter-controls input[type=range]::-moz-range-thumb {
      width: 20px;
      height: 20px;
      background: #007BFF;
      border-radius: 50%;
      cursor: pointer;
      border: 2px solid #fff;
    }
    .filter-controls span { font-size: 14px; }
    .filter-btn {
      background: #007BFF;
      color: #fff;
      border: none;
      padding: 6px 12px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
    }
    
    /* Card Container */
    .card-container { display: flex; flex-wrap: wrap; justify-content: center; padding: 10px; }
    .card { background: #fff; margin: 5px; padding: 10px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); transition: transform 0.2s ease; cursor: pointer; width: calc(100% - 10px); max-width: 600px; position: relative; }
    .card:hover { transform: translateY(-3px); }
    
    /* Card Header: Two-column layout */
.card-header { display: flex; gap: 10px; margin-bottom: 8px; align-items: flex-start; }
.matches-box { background: #575757; color: #fff; border-radius: 8px; padding: 10px; min-width: 100px; max-width: 120px; flex-shrink: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; }
.matches-title { font-size: 18px; margin-bottom: 4px; font-weight: bold; }
.matches-count { margin: 0; line-height: 1; font-weight: bold; color: #fff; }
.matches-stats { font-size: 12px; margin-top: 4px; color: #fff; }
.user-info { flex: 1; display: flex; flex-direction: column; gap: 4px; font-size: 14px; text-align: left; }
.user-info table { border-collapse: collapse; width: 100%; table-layout: fixed; }
.user-info table td { padding: 2px 4px; vertical-align: top; text-align: left; }
.user-info table td:first-child { width: 70px; font-weight: bold; }
.user-info table td:nth-child(2) { width: calc(100% - 70px); word-break: break-word; text-align: left; }

    
    
    /* Action Buttons */
    .actions { margin-top: 8px; display: flex; gap: 8px; }
    .actions button { font-size: 12px; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; }
    .message-btn { background: #007BFF; color: #fff; }
    /* Expand button now toggles between "Expand" and "Collapse" */
    .expand-btn { background: #28A745; color: #fff; }
    /* Renamed Hide Match to Hide */
    .hide-match-btn { background: #FF9800; color: #fff; }
    /* New Delete button style */
    .delete-match-btn { background: #D32F2F; color: #fff; }
    
    /* Expandable Content for Card View */
    .expandable-content { display: none; margin-top: 10px; border-top: 1px solid #ddd; padding-top: 10px; }
    .match-section { margin-bottom: 15px; }
    .match-section h4 { font-size: 16px; margin-bottom: 5px; color: #333; }
    .cover-grid { display: flex; flex-wrap: wrap; gap: 5px; }
    .cover-grid img { width: 70px; height: 100px; object-fit: cover; border-radius: 4px; cursor: pointer; -webkit-tap-highlight-color: transparent; touch-action: manipulation; user-select: none; }
    .message-btn-large { display: block; width: 100%; padding: 8px; margin-top: 10px; background: #007BFF; color: #fff; border: none; border-radius: 4px; font-size: 14px; text-align: center; }
    
    /* List View */
    .list-view table { width: 100%; border-collapse: collapse; background: #fff; margin: 0; font-family: sans-serif; font-size: 14px; }
    .list-view th, .list-view td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    .list-view th { background: #575757; color: #fff; }
    .expanded-cell { background: #f9f9f9; padding: 10px; }
    .expanded-section { margin: 15px 0; }
    .expanded-section h5 { margin: 0 0 5px 0; font-size: 16px; color: #333; }
    .sub-table { width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px; }
    .sub-table colgroup col:nth-child(1) { width: 60%; }
    .sub-table colgroup col:nth-child(2) { width: 15%; }
    .sub-table colgroup col:nth-child(3) { width: 25%; }
    .sub-table td { border: 1px solid #ddd; padding: 6px; text-align: left; }
    
    /* Bottom Navigation */
    .bottom-nav { position: fixed; bottom: 0; left: 0; right: 0; background: #575757; box-shadow: 0 -2px 4px rgba(0,0,0,0.3); display: flex; justify-content: space-around; padding: 10px 0; font-size: 12px; }
    .bottom-nav a { color: #fff; text-align: center; flex: 1; padding: 6px 0; transition: background 0.3s, transform 0.3s; }
    .bottom-nav a:hover { background: rgba(255,255,255,0.2); transform: scale(1.05); }
    .bottom-nav a.active { background: rgba(255,255,255,0.3); border-radius: 8px; }
    
    /* Modal Overlay for Cover Details (Swiper) */
    .cover-modal { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); display: none; z-index: 10000; align-items: center; justify-content: center; padding: 20px; }
    .cover-modal .modal-box { background: #fff; padding: 15px; border-radius: 8px; width: 90%; max-width: 400px; position: relative; text-align: center; }
    .cover-modal .modal-box img { width: 100%; height: auto; border-radius: 4px; display: block; }
    .cover-modal .modal-box h4 { margin: 10px 0; text-align: center; }
    .cover-modal .modal-box .modal-details { display: flex; justify-content: space-between; margin-bottom: 10px; }
    .cover-modal .modal-box .modal-details .column { width: 48%; text-align: left; }
    .cover-modal .modal-box .modal-details .column p { margin: 4px 0; font-size: 14px; text-align: left; }
    .image-container { position: relative; }
    .price-banner { position: absolute; top: 8px; left: 8px; background: green; color: #fff; padding: 6px 10px; border-radius: 4px; font-size: 16px; font-weight: bold; z-index: 2; }
    .cover-modal .modal-box .close-modal { position: absolute; top: 5px; right: 5px; background: #fff; border: 2px solid #000; font-size: 32px; line-height: 1; cursor: pointer; color: #000; border-radius: 50%; width: 40px; height: 40px; z-index: 3; }
    
    /* Swiper slider styles */
    .swiper-container { width: 100%; padding: 10px 0; }
    .swiper-slide { text-align: center; width: 100%; }
    .swiper-slide img { width: 100%; height: auto; border-radius: 4px; }
    
    @media (max-width:480px) { .cover-modal .modal-box { width:90%; margin:auto; } }
    @media (max-width: 480px) {
      .matches-stats { display: none; }
      .cover-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 5px; }
      .cover-grid img { width: 100%; height: auto; }
    }
    @media (min-width: 481px) {
      .cover-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 5px; }
      .cover-grid img { width: 100%; height: auto; }
    }
    
    /* New Filter Popup - Updated styling for a cleaner, mobile-friendly look */
    #filterPopup .modal-box {
      padding: 25px;
    }
    #filterPopup h3 {
      font-size: 22px;
      margin-bottom: 15px;
    }
    #filterPopup label {
      font-size: 18px;
      display: block;
      margin-bottom: 8px;
    }
    /* Place the two checkboxes on the same line */
    #filterPopup .checkbox-group {
      display: flex;
      align-items: center;
      gap: 15px;
      margin-bottom: 15px;
    }
    #filterPopup .checkbox-group label {
      margin: 0;
      font-size: 18px;
    }
    #filterPopup select, 
    #filterPopup input[type="checkbox"],
    #filterPopup input[type="radio"] {
      transform: scale(1.2);
    }
    #filterPopup button#applyFilterPopup {
      font-size: 18px;
      padding: 10px;
    }
  </style>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script src="https://unpkg.com/swiper/swiper-bundle.min.js"></script>
  <script>
    // Helper: escape quotes for JavaScript.
    function addslashes(str) {
      return String(str).replace(/'/g, "\\'").replace(/"/g, '\\"');
    }
    
    // Global filter options (default values)
    var sortOption = "newest"; // "newest", "closest", or "most"
    var filterIncludeActive = true;
    var filterIncludeHidden = false;
    
    // On page load, load filter status from localStorage
    function loadFilterStatus() {
      var savedStatus = localStorage.getItem('filterStatus');
      if (savedStatus === null) {
          savedStatus = "active";
          localStorage.setItem('filterStatus', savedStatus);
      }
      if (savedStatus === "active") {
          filterIncludeActive = true;
          filterIncludeHidden = false;
          $("#filterActiveCheckbox").prop("checked", true);
          $("#filterHiddenCheckbox").prop("checked", false);
      } else if (savedStatus === "hidden") {
          filterIncludeActive = false;
          filterIncludeHidden = true;
          $("#filterActiveCheckbox").prop("checked", false);
          $("#filterHiddenCheckbox").prop("checked", true);
      } else if (savedStatus === "both") {
          filterIncludeActive = true;
          filterIncludeHidden = true;
          $("#filterActiveCheckbox").prop("checked", true);
          $("#filterHiddenCheckbox").prop("checked", true);
      }
    }
    
    // Save current filter status to localStorage
    function saveFilterStatus() {
      var status;
      if(filterIncludeActive && filterIncludeHidden){
        status = "both";
      } else if(filterIncludeActive){
        status = "active";
      } else if(filterIncludeHidden){
        status = "hidden";
      } else {
        status = "active";
      }
      localStorage.setItem('filterStatus', status);
    }
    
    // Function to sort the cards in the card view based on sortOption.
    function sortCards() {
      var cards = $('.card-container .card').get();
      cards.sort(function(a, b) {
        if (sortOption === "newest") {
          return $(b).data('match-time') - $(a).data('match-time');
        } else if (sortOption === "closest") {
          return $(a).data('distance') - $(b).data('distance');
        } else if (sortOption === "most") {
          return $(b).data('shared-count') - $(a).data('shared-count');
        }
        return 0;
      });
      $.each(cards, function(idx, itm) {
        $('.card-container').append(itm);
      });
    }
    
    // Function to apply distance and status filter based on new options.
    function applyDistanceFilter(){
      var maxDistance = parseInt($('#distanceSlider').val());
      $('.card').each(function(){
        var cardDistance = parseInt($(this).attr('data-distance')) || 0;
        var isHidden = $(this).attr('data-hidden') === "true";
        if (((!isHidden && filterIncludeActive) || (isHidden && filterIncludeHidden)) && cardDistance <= maxDistance) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });
      $('.list-view tr.match-row').each(function(){
        var rowDistance = parseInt($(this).attr('data-distance')) || 0;
        var isHidden = $(this).attr('data-hidden') === "true";
        if (((!isHidden && filterIncludeActive) || (isHidden && filterIncludeHidden)) && rowDistance <= maxDistance) {
          $(this).show();
        } else {
          $(this).hide();
        }
      });
      
      // After filtering, sort the cards.
      sortCards();
    }
    
    $(document).ready(function(){
      // Load filter status from localStorage and update controls accordingly.
      loadFilterStatus();
      
      // On page load, restore the distance setting from localStorage (default 500 if not set)
      const slider = document.getElementById('distanceSlider');
const unit = slider?.dataset.unit || 'mi';
let radiusVal = localStorage.getItem('maxDistance') || slider?.value || 500;
$('#distanceSlider').val(radiusVal);
$('#distanceValue').text(radiusVal + " " + unit);


// Update localStorage only if nothing stored
if (!localStorage.getItem('maxDistance')) {
    localStorage.setItem('maxDistance', radiusVal);
} else {
    radiusVal = localStorage.getItem('maxDistance');
    $('#distanceSlider').val(radiusVal);
}

$('#distanceValue').text(radiusVal + " " + unit);

      
      // Use saved filter options (do not force active by default now).
      applyDistanceFilter();
      
      // Toggle view between card and list.
      $('#cardView').show();
      $('#listView').hide();
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
      
      // Distance Slider Change Handler.
      $('#distanceSlider').on('input change', function(){
  const unit = $(this).data('unit') || 'mi';
  const maxDistance = parseInt($(this).val());
  $('#distanceValue').text(maxDistance + " " + unit);
  localStorage.setItem('maxDistance', maxDistance);
  applyDistanceFilter();
});


applyDistanceFilter();  // ensures that filter applies based on default on first load

      
      // --- New Filter Popup Logic ---
      // When the filter button is clicked, ensure any active cover popup is closed, then open the filter popup.
      $('#filterBtn').click(function(e){
          e.stopPropagation();
          $('.cover-modal').not('#filterPopup').fadeOut();
          $('#filterPopup').fadeIn();
      });
      // Close the popup if clicking outside the modal-box or on the close button.
      $('#filterPopup, #filterPopup .close-modal').click(function(e){
          if(e.target === this) {
              $('#filterPopup').fadeOut();
          }
      });
      // When "Apply" is clicked in the popup.
      $('#applyFilterPopup').click(function(){
          // Get sort option from the select.
          sortOption = $('#popupSortSelect').val();
          // Get checkboxes for Active and Hidden.
          filterIncludeActive = $('#filterActiveCheckbox').is(':checked');
          filterIncludeHidden = $('#filterHiddenCheckbox').is(':checked');
          // Save the filter status.
          saveFilterStatus();
          // Close the popup.
          $('#filterPopup').fadeOut();
          // Apply filter and sort.
          applyDistanceFilter();
      });
      
      // Expand button click for card view.
      $(document).on('click', '.expand-btn', function(e){
        if ($(this).closest('.card').length) {
          e.stopPropagation();
          const card = $(this).closest('.card');
          const otherUserId = card.data('other-user-id');
          const contentDiv = card.find('.expandable-content');
          // Toggle the button text between "Expand" and "Collapse"
          if(contentDiv.is(':visible')){
            contentDiv.slideUp();
            $(this).text("Expand");
          } else {
            if (!card.data('expanded')) {
              $.ajax({
                url: 'getMatchCoversGrouped.php',
                data: { other_user_id: otherUserId },
                dataType: 'json',
                success: function(resp){
                  let html = '';
                  // Buy Section.
                  html += '<div class="match-section"><h4>Buy From ' + card.find('.user-info p:first').text() + '</h4>';
                  if (resp.buy && resp.buy.length > 0) {
                    html += '<div class="cover-grid">';
                    $.each(resp.buy, function(i, cover){
                      html += '<img src="' + cover.image + '" alt="Cover" ';
                      html += 'data-id="' + cover.id + '" ';
                      html += 'data-comic-title="' + addslashes(cover.comic_title) + '" ';
                      html += 'data-issue-number="' + addslashes(cover.issue_number) + '" ';
                      html += 'data-years="' + addslashes(cover.years) + '" ';
                      html += 'data-condition="' + addslashes(cover.condition) + '" ';
                      html += 'data-price="' + addslashes(cover.price) + '" ';
                      html += 'data-city="' + addslashes(cover.city) + '" ';
                      html += 'data-distance="' + addslashes(cover.distance) + '" ';
                      html += 'data-party="Seller: ' + card.find('.user-info p:first').text() + '">';
                    });
                    html += '</div>';
                  } else {
                    html += '<p style="font-size:14px;color:#777;">No comics available to buy.</p>';
                  }
                  html += '</div>';
                  // Sell Section.
                  html += '<div class="match-section"><h4>Sell To ' + card.find('.user-info p:first').text() + '</h4>';
                  if (resp.sell && resp.sell.length > 0) {
                    html += '<div class="cover-grid">';
                    $.each(resp.sell, function(i, cover){
                      html += '<img src="' + cover.image + '" alt="Cover" ';
                      html += 'data-id="' + cover.id + '" ';
                      html += 'data-comic-title="' + addslashes(cover.comic_title) + '" ';
                      html += 'data-issue-number="' + addslashes(cover.issue_number) + '" ';
                      html += 'data-years="' + addslashes(cover.years) + '" ';
                      html += 'data-condition="' + addslashes(cover.condition) + '" ';
                      html += 'data-price="' + addslashes(cover.price) + '" ';
                      html += 'data-city="' + addslashes(cover.city) + '" ';
                      html += 'data-distance="' + addslashes(cover.distance) + '" ';
                      html += 'data-party="Buyer: ' + card.find('.user-info p:first').text() + '">';
                    });
                    html += '</div>';
                  } else {
                    html += '<p style="font-size:14px;color:#777;">No comics available to sell.</p>';
                  }
                  html += '</div>';
                  // Message Button.
                  const userLine = card.find('.user-info p:first').text();
                  const simpleName = userLine.replace(/^User:\s*/i, '');
                  let intent = card.find('.message-btn').data('intent');
                  let displayName = card.find('.message-btn').data('displayname');
                  let buyMatches = card.find('.message-btn').data('buy-matches');
                  let sellMatches = card.find('.message-btn').data('sell-matches');
                  let url = 'matches_msg.php?to=' + card.data('other-user-id') +
                            '&intent=' + encodeURIComponent(intent) +
                            '&displayname=' + encodeURIComponent(displayName) +
                            '&buy_matches=' + encodeURIComponent(JSON.stringify(buyMatches)) +
                            '&sell_matches=' + encodeURIComponent(JSON.stringify(sellMatches));
                  html += '<button class="message-btn-large" onclick="window.location.href=\'' + url + '\'">Message: ' + simpleName + '</button>';
                  contentDiv.html(html).slideDown();
                  card.data('expanded', true);
                  // Change button text to "Collapse"
                  card.find('.expand-btn').text("Collapse");
                },
                error: function(xhr, status, error){
                  contentDiv.html('<p>Error loading match details.</p>').slideDown();
                  card.data('expanded', true);
                }
              });
            } else {
              contentDiv.slideDown();
              $(this).text("Collapse");
            }
          }
        }
      });
      
      // List View: Expand button click handler.
      $(document).on('click', '#listView .expand-btn', function(e) {
        e.stopPropagation();
        var otherUserId = $(this).data('other-user-id');
        $('#listExpand_' + otherUserId).toggle();
      });
      
      // Hide/Unhide Button Behavior.
      $(document).on('click', '.hide-match-btn', function(e) {
        e.stopPropagation();
        var btn = $(this);
        var matchUserId = btn.data('match-user-id');
        var isHidden = btn.data('hidden'); // false means active
        var endpoint = isHidden ? '/comicsmp/api/unhideMatch.php' : '/comicsmp/api/hideMatch.php';
        console.log("Sending AJAX request to " + endpoint + " with match_user_id: " + matchUserId);
        $.ajax({
          url: endpoint,
          method: 'POST',
          data: { match_user_id: matchUserId },
          dataType: 'json',
          success: function(response) {
            console.log("AJAX response:", response);
            if(response.status === 'success'){
              if(!isHidden){
                btn.text('Unhide').data('hidden', true);
                btn.closest('.card').attr('data-hidden', 'true');
                $('.list-view tr.match-row[data-other-user-id="'+ matchUserId +'"]').attr('data-hidden', 'true');
              } else {
                btn.text('Hide').data('hidden', false);
                btn.closest('.card').attr('data-hidden', 'false');
                $('.list-view tr.match-row[data-other-user-id="'+ matchUserId +'"]').attr('data-hidden', 'false');
              }
              // Reapply filter
              applyDistanceFilter();
            } else {
              alert("Server error: " + response.message);
            }
          },
          error: function(xhr, status, error) {
            console.error("AJAX Error:", status, error, xhr.responseText);
            alert('AJAX error: ' + error);
          }
        });
      });
      
      // New Delete Button Behavior with Confirmation Popup.
      $(document).on('click', '.delete-match-btn', function(e) {
        e.stopPropagation();
        var btn = $(this);
        var matchUserId = btn.data('match-user-id');
        // Confirmation popup message.
        var conf = confirm("Warning: Deleting this contact will permanently remove all match information and block any future matches with this person. We recommend using 'Hide' if you might want to see new matches later. Are you sure you want to delete?");
        if(conf){
          var endpoint = '/comicsmp/api/deleteMatch.php';
          console.log("Sending AJAX request to " + endpoint + " with match_user_id: " + matchUserId);
          $.ajax({
            url: endpoint,
            method: 'POST',
            data: { match_user_id: matchUserId },
            dataType: 'json',
            success: function(response) {
              console.log("Delete AJAX response:", response);
              if(response.status === 'success'){
                // For delete, we assume the match is permanently removed from the user's view.
                btn.closest('.card').hide();
                $('.list-view tr.match-row[data-other-user-id="'+ matchUserId +'"]').hide();
              } else {
                alert("Server error: " + response.message);
              }
            },
            error: function(xhr, status, error) {
              console.error("Delete AJAX Error:", status, error, xhr.responseText);
              alert('AJAX error: ' + error);
            }
          });
        }
      });
      
      // Delegate click on cover grid image to open modal slider.
      $(document).on('click', '.cover-grid img', function(e){
        e.preventDefault();
        e.stopPropagation();
        const $img = $(this);
        const covers = [];
        let clickedIndex = 0;
        $img.closest('.cover-grid').find('img').each(function(index){
          covers.push({
            id: $(this).data('id'),
            comic_title: $(this).data('comic-title'),
            issue_number: $(this).data('issue-number'),
            years: $(this).data('years'),
            condition: $(this).data('condition'),
            price: $(this).data('price'),
            city: $(this).data('city'),
            distance: $(this).data('distance'),
            party: $(this).data('party'),
            image: $(this).attr('src')
          });
          if(this === $img[0]){
            clickedIndex = index;
          }
        });
        openCoverModalSlider(covers, clickedIndex);
      });
      
      // Function to open modal with Swiper slider (cover popup remains unchanged).
      function openCoverModalSlider(covers, initialSlide = 0) {
        var gradeMapping = {
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

        let modalHtml = '<div class="modal-box">';
        modalHtml += '<button class="close-modal">&times;</button>';
        modalHtml += '<div class="swiper-container" style="overflow: hidden;"><div class="swiper-wrapper">';
        $.each(covers, function(i, cover) {
            let conditionDisplay = cover.condition;
            if (gradeMapping.hasOwnProperty(cover.condition)) {
                conditionDisplay += " (" + gradeMapping[cover.condition] + ")";
            }
            modalHtml += '<div class="swiper-slide" style="width: 100%;">';
            modalHtml += '<div class="image-container">';
            modalHtml += '<div class="price-banner">' + cover.price + '</div>';
            modalHtml += '<img src="' + cover.image + '" alt="Cover" style="width: 100%;">';
            modalHtml += '</div>';
            modalHtml += '<h4 style="text-align: center;">' + cover.comic_title + '</h4>';
            modalHtml += '<p style="text-align: center; font-size: 14px; margin-top: 10px; margin-bottom: 15px;">';
            modalHtml += 'Years: ' + cover.years + '<br>';
            modalHtml += 'Issue: ' + cover.issue_number + '<br>';
            modalHtml += 'Condition: ' + conditionDisplay;
            modalHtml += '</p>';
            modalHtml += '</div>';
        });
        modalHtml += '</div><div class="swiper-pagination"></div></div>';
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
      
      // Close modal on overlay or close button click.
      $(document).on('click', '.cover-modal', function(e){
        if ($(e.target).hasClass('cover-modal') || $(e.target).hasClass('close-modal')) {
          $('.cover-modal').fadeOut();
        }
      });
      
      // Message button click handler for card view.
      $(document).on('click', '.message-btn', function(e){
        e.stopPropagation();
        var button = $(this);
        var otherUserId = button.data('other-user-id');
        var intent = button.data('intent');
        var displayName = button.data('displayname');
        var buyMatches = button.data('buy-matches');
        var sellMatches = button.data('sell-matches');
        var url = 'matches_msg.php?to=' + otherUserId +
                  '&intent=' + encodeURIComponent(intent) +
                  '&displayname=' + encodeURIComponent(displayName) +
                  '&buy_matches=' + encodeURIComponent(JSON.stringify(buyMatches)) +
                  '&sell_matches=' + encodeURIComponent(JSON.stringify(sellMatches));
        window.location.href = url;
      });
    });
  </script>
</head>
<body>
  <!-- Top Header -->
  <div class="top-header">
    <div class="logo">
      <img src="../logo.png" alt="Logo">
    </div>
    <div class="icons">
      <a href="inbox.php">Inbox</a>
      <a href="settings.php">Profile</a>
    </div>
  </div>
  
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
  
  <!-- Filter Area -->
  <div class="filter-area">
    <div class="filter-controls" style="flex-direction: row; align-items: center; gap:10px;">
      <label for="distanceSlider" style="margin:0;">
  Max Distance: 
  <span id="distanceValue"><?php echo htmlspecialchars($defaultRadius); ?> <?php echo htmlspecialchars($distanceUnit); ?></span>
</label>
<input type="range" 
       id="distanceSlider" 
       min="0" 
       max="1000" 
       value="<?php echo htmlspecialchars($defaultRadius); ?>" 
       step="10" 
       data-unit="<?php echo htmlspecialchars($distanceUnit); ?>">

      <!-- New Filter button placed to the right of the slider -->
      <button class="filter-btn" id="filterBtn">Filter</button>
    </div>
  </div>
  
  <!-- New Filter Popup -->
  <div id="filterPopup" class="cover-modal" style="display:none;">
    <div class="modal-box" style="max-width:450px;">
      <button class="close-modal">&times;</button>
      <h3 style="margin-bottom:15px;">Filter Options</h3>
      <div style="margin-bottom:15px;">
        <label for="popupSortSelect" style="font-size:16px; display:block; margin-bottom:8px;">Sort By:</label>
        <select id="popupSortSelect" style="width:100%; padding:6px; font-size:16px;">
          <option value="newest">Newest Matches</option>
          <option value="closest">Closest Matches</option>
          <option value="most">Most Matches</option>
        </select>
      </div>
      <div style="margin-bottom:15px;">
        <label style="font-size:16px; display:block; margin-bottom:8px;">Include Matches:</label>
        <div class="checkbox-group">
          <div>
            <input type="checkbox" id="filterActiveCheckbox" style="transform:scale(1.2); margin-right:5px;">
            <label for="filterActiveCheckbox" style="font-size:16px;">Active</label>
          </div>
          <div>
            <input type="checkbox" id="filterHiddenCheckbox" style="transform:scale(1.2); margin-right:5px;">
            <label for="filterHiddenCheckbox" style="font-size:16px;">Hidden</label>
          </div>
         
        </div>
      </div>
      <button id="applyFilterPopup" style="background:#28A745; color:#fff; border:none; border-radius:4px; padding:10px; width:100%; font-size:18px; cursor:pointer;">Apply</button>
    </div>
  </div>
  
  <!-- Card/Grid View Section -->
  <div id="cardView" class="card-container">
    <?php if (!empty($groupedMatches)): ?>
      <?php foreach ($groupedMatches as $otherUserId => $matchArray):
            $actualMatches = array_filter($matchArray, function($item, $key) {
                return $key !== 'stats';
            }, ARRAY_FILTER_USE_BOTH);
            $count = count($actualMatches);
            $actualMatches = array_values($actualMatches);
            $first = $actualMatches[0];
            
            $buyerId = isset($first['buyer_id']) ? $first['buyer_id'] : null;
            $sellerId = isset($first['seller_id']) ? $first['seller_id'] : null;
            if (($buyerId === null || $sellerId === null)) {
                $buyerId = $userId;
                $sellerId = null;
            }
            if ($buyerId == $userId) {
    $otherCity = $first['seller_city'] ?? '';
    $distanceStr = "";
    if (!empty($first['buyer_lat']) && !empty($first['buyer_lng']) &&
        !empty($first['seller_lat']) && !empty($first['seller_lng'])) {
        $distanceStr = calculateDistance(
            $first['buyer_lat'], $first['buyer_lng'],
            $first['seller_lat'], $first['seller_lng'],
            $distanceUnit
        );
    }
}

           else {
    $otherCity = $first['buyer_city'] ?? '';
    $distanceStr = "";
    if (!empty($first['seller_lat']) && !empty($first['seller_lng']) &&
        !empty($first['buyer_lat']) && !empty($first['buyer_lng'])) {
        $distanceStr = calculateDistance(
            $first['seller_lat'], $first['seller_lng'],
            $first['buyer_lat'], $first['buyer_lng'],
            $distanceUnit
        );
    }
}

            $stats = $matchArray['stats'];
            $fontSize = getMatchFontSize($count);
            $userInfo = $userNamesMap[$otherUserId] ?? ['username' => "User #$otherUserId", 'city' => $otherCity, 'preferred_transaction' => '', 'preferred_payment' => ''];
            $displayName = $userInfo['username'];
            $buyMatches = array_filter($matchArray, function($m) use ($userId) {
                return isset($m['buyer_id']) && $m['buyer_id'] == $userId;
            });
            $sellMatches = array_filter($matchArray, function($m) use ($userId) {
                return isset($m['seller_id']) && $m['seller_id'] == $userId;
            });
            $intent = ($buyMatches && !$sellMatches) ? 'buy' : (($sellMatches && !$buyMatches) ? 'sell' : 'buy_sell');
            $matchTimestamp = strtotime($first['match_time']);
            $isHidden = in_array((int)$otherUserId, array_map('intval', $hiddenMatches));
      ?>
        <div class="card" 
             data-other-user-id="<?php echo $otherUserId; ?>" 
             data-distance="<?php echo $distanceStr !== "" ? explode(" ", $distanceStr)[0] : 0; ?>" 
             data-match-time="<?php echo $matchTimestamp; ?>"
             data-shared-count="<?php echo $count; ?>"
             data-hidden="<?php echo $isHidden ? 'true' : 'false'; ?>">
          <div class="card-header">
            <div class="matches-box">
              <div class="matches-title">Matches</div>
              <p class="matches-count" style="font-size:<?php echo $fontSize; ?>;"><?php echo $count; ?></p>
              <div class="matches-stats">
                24h: <?php echo $stats['24h']; ?> | wk: <?php echo $stats['wk']; ?> | mo: <?php echo $stats['mo']; ?>
              </div>
            </div>
            <div class="user-info">
              <table>
                <tr>
                  <td><strong>User:</strong></td>
                  <td><?php echo htmlspecialchars($displayName); ?></td>
                </tr>
                <tr>
                  <td><strong>City:</strong></td>
                  <td><?php echo htmlspecialchars($userInfo['city']); ?></td>
                </tr>
                <tr>
                  <td><strong>Distance:</strong></td>
                  <td><?php echo htmlspecialchars($distanceStr); ?></td>
                </tr>
                <tr>
                  <td><strong>Trans:</strong></td>
<td>
  <?php
    $transRaw = strtolower($userInfo['preferred_transaction'] ?? '');
    $icons = [];

    if (strpos($transRaw, 'shipping') !== false) {
        $icons[] = '<i class="bi bi-truck" title="Shipping"></i>';
    }
    if (strpos($transRaw, 'meetup') !== false) {
        $icons[] = '<i class="bi bi-people" title="Meetup"></i>';
    }
    if (strpos($transRaw, 'pickup') !== false) {
        $icons[] = '<i class="bi bi-house-door" title="Pickup"></i>'; // ✅ this one shows up reliably
    }

    echo implode(' ', $icons);
  ?>
</td>
                </tr>
                <tr>
  <td><strong>Pay:</strong></td>
  <td>
    <?php
      $payRaw = strtolower($userInfo['preferred_payment'] ?? '');
      $icons = [];

      if (strpos($payRaw, 'cash') !== false) {
          $icons[] = '<i class="bi bi-cash-coin" title="Cash"></i>';
      }
      if (strpos($payRaw, 'e-transfer') !== false || strpos($payRaw, 'etransfer') !== false) {
          $icons[] = '<i class="bi bi-bank" title="E-Transfer"></i>';
      }
      if (strpos($payRaw, 'paypal') !== false) {
          $icons[] = '<i class="bi bi-p-circle" title="PayPal"></i>';
      }

      echo implode(' ', $icons);
    ?>
  </td>
</tr>

              </table>
            </div>
          </div>
          <div class="actions">
            <button class="message-btn" 
                    data-other-user-id="<?php echo $otherUserId; ?>"
                    data-intent="<?php echo $intent; ?>"
                    data-displayname="<?php echo htmlspecialchars($displayName); ?>"
                    data-buy-matches='<?php echo json_encode(array_values($buyMatches)); ?>'
                    data-sell-matches='<?php echo json_encode(array_values($sellMatches)); ?>'>
              Message
            </button>
            <button class="expand-btn" data-other-user-id="<?php echo $otherUserId; ?>">Expand</button>
            <!-- Renamed Hide button -->
            <button class="hide-match-btn" data-hidden="<?php echo $isHidden ? 'true' : 'false'; ?>" data-match-user-id="<?php echo $otherUserId; ?>">
              <?php echo $isHidden ? 'Unhide' : 'Hide'; ?>
            </button>
            <!-- New Delete button added -->
            <button class="delete-match-btn" data-match-user-id="<?php echo $otherUserId; ?>">
              Delete
            </button>
          </div>
          <div class="expandable-content"></div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <p style="text-align:center; padding:20px;">No matches found.</p>
    <?php endif; ?>
  </div>
  
  <!-- List View Section -->
  <div id="listView" class="list-view" style="display:none;">
    <?php if (!empty($groupedMatches)): ?>
      <table>
        <thead>
          <tr>
            <th>Other Party</th>
            <th># Matches</th>
            <th>Contact</th>
            <th>Expand</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($groupedMatches as $otherUserId => $matchArray):
              $actualMatches = array_filter($matchArray, function($item, $key) {
                  return $key !== 'stats';
              }, ARRAY_FILTER_USE_BOTH);
              $cnt = count($actualMatches);
              $uname = $userNamesMap[$otherUserId]['username'] ?? "User #$otherUserId";
              
              $buyMatches = array_filter($matchArray, function($m) use ($userId) {
                  return isset($m['buyer_id']) && $m['buyer_id'] == $userId;
              });
              $sellMatches = array_filter($matchArray, function($m) use ($userId) {
                  return isset($m['seller_id']) && $m['seller_id'] == $userId;
              });
              $intent = ($buyMatches && !$sellMatches) ? 'buy' : (($sellMatches && !$buyMatches) ? 'sell' : 'buy_sell');
              $messageUrl = 'matches_msg.php?to=' . $otherUserId .
                  '&intent=' . urlencode($intent) .
                  '&displayname=' . urlencode($uname) .
                  '&buy_matches=' . urlencode(json_encode(array_values($buyMatches))) .
                  '&sell_matches=' . urlencode(json_encode(array_values($sellMatches)));
              
              // Group buy matches by comic title and years.
              $groupedBuy = [];
              foreach ($buyMatches as $m) {
                  if (!isset($m['comic_title'])) continue;
                  $key = $m['comic_title'] . '|' . $m['years'];
                  if (!isset($groupedBuy[$key])) {
                      $groupedBuy[$key] = [
                          'comic_title' => $m['comic_title'],
                          'years' => $m['years'],
                          'issues' => []
                      ];
                  }
                  $groupedBuy[$key]['issues'][] = $m['issue_number'];
              }
              foreach ($groupedBuy as &$group) {
                  $group['issues'] = implode(", ", array_unique($group['issues']));
              }
              unset($group);
              
              // Group sell matches similarly.
              $groupedSell = [];
              foreach ($sellMatches as $m) {
                  if (!isset($m['comic_title'])) continue;
                  $key = $m['comic_title'] . '|' . $m['years'];
                  if (!isset($groupedSell[$key])) {
                      $groupedSell[$key] = [
                          'comic_title' => $m['comic_title'],
                          'years' => $m['years'],
                          'issues' => []
                      ];
                  }
                  $groupedSell[$key]['issues'][] = $m['issue_number'];
              }
              foreach ($groupedSell as &$group) {
                  $group['issues'] = implode(", ", array_unique($group['issues']));
              }
              unset($group);
              // For list view, add data attributes to the row.
              $first = array_values($actualMatches)[0];
              $matchTimestamp = strtotime($first['match_time']);
              if(isset($first['buyer_id']) && $first['buyer_id'] == $userId){
                  if(!empty($first['buyer_lat']) && !empty($first['buyer_lng']) && !empty($first['seller_lat']) && !empty($first['seller_lng'])){
                      $distanceStr = calculateDistance($first['buyer_lat'], $first['buyer_lng'], $first['seller_lat'], $first['seller_lng']);
                  } else {
                      $distanceStr = "0 mi";
                  }
              } else {
                  if(!empty($first['seller_lat']) && !empty($first['seller_lng']) && !empty($first['buyer_lat']) && !empty($first['buyer_lng'])){
                      $distanceStr = calculateDistance($first['seller_lat'], $first['seller_lng'], $first['buyer_lat'], $first['buyer_lng']);
                  } else {
                      $distanceStr = "0 mi";
                  }
              }
              $listHidden = in_array($otherUserId, $hiddenMatches) ? 'true' : 'false';
        ?>
          <tr class="match-row" 
              data-other-user-id="<?php echo $otherUserId; ?>" 
              data-distance="<?php echo $distanceStr !== "" ? explode(" ", $distanceStr)[0] : 0; ?>" 
              data-match-time="<?php echo $matchTimestamp; ?>"
              data-shared-count="<?php echo $cnt; ?>"
              data-hidden="<?php echo $listHidden; ?>">
            <td><?php echo htmlspecialchars($uname); ?></td>
            <td><?php echo $cnt; ?></td>
            <td>
              <a href="<?php echo $messageUrl; ?>"
                 style="background:#007BFF;color:#fff;padding:4px 8px;border-radius:4px;text-decoration:none;">
                Message
              </a>
            </td>
            <td>
              <button class="expand-btn" data-other-user-id="<?php echo $otherUserId; ?>"
                      style="background:#28A745;color:#fff;padding:4px 8px;border:none;border-radius:4px;">
                Expand
              </button>
            </td>
          </tr>
          <!-- Expanded row for list view -->
          <tr id="listExpand_<?php echo $otherUserId; ?>" style="display:none;">
            <td colspan="4" class="expanded-cell">
              <div class="expanded-section">
                <h5>Buy From: <?php echo htmlspecialchars($uname); ?></h5>
                <?php if (!empty($groupedBuy)): ?>
                <table class="sub-table">
                  <colgroup>
                    <col style="width: 60%;">
                    <col style="width: 15%;">
                    <col style="width: 25%;">
                  </colgroup>
                  <tbody>
                    <?php foreach ($groupedBuy as $group): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($group['comic_title']); ?></td>
                      <td><?php echo htmlspecialchars($group['years']); ?></td>
                      <td><?php echo htmlspecialchars($group['issues']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php else: ?>
                <p>No comics available to buy.</p>
                <?php endif; ?>
              </div>
              <div class="expanded-section">
                <h5>Sell To: <?php echo htmlspecialchars($uname); ?></h5>
                <?php if (!empty($groupedSell)): ?>
                <table class="sub-table">
                  <colgroup>
                    <col style="width: 60%;">
                    <col style="width: 15%;">
                    <col style="width: 25%;">
                  </colgroup>
                  <tbody>
                    <?php foreach ($groupedSell as $group): ?>
                    <tr>
                      <td><?php echo htmlspecialchars($group['comic_title']); ?></td>
                      <td><?php echo htmlspecialchars($group['years']); ?></td>
                      <td><?php echo htmlspecialchars($group['issues']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
                <?php else: ?>
                <p>No comics available to sell.</p>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p style="text-align:center; padding:20px;">No matches found.</p>
    <?php endif; ?>
  </div>
  
  <!-- Bottom Navigation Bar -->
  <div class="bottom-nav">
    <a href="dashboard.php">Home</a>
    <a href="wanted.php">Wanted</a>
    <a href="selling.php">For Sale</a>
    <a class="active" href="matches.php">Matches</a>
  </div>
  
  <!-- Modal Overlay for Cover Details (Swiper) -->
  <div class="cover-modal" style="display:none;"></div>
</body>
</html>

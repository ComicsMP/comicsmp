<?php
session_start();
include 'db_connection.php';

// Ensure user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "<p>Please log in to use swipe matching.</p>";
    exit;
}

// Initialize session array for 'viewed_comics'
if (!isset($_SESSION['viewed_comics'])) {
    $_SESSION['viewed_comics'] = [];
}

// Query using issue_url to link match_notifications & comics_for_sale
$query = "
SELECT
    m.id              AS match_id,
    m.buyer_id,
    m.seller_id,
    m.comic_title,
    m.issue_number,
    m.years,
    m.issue_url       AS mn_url,
    m.cover_image     AS mn_cover,
    
    cs.Issue_URL      AS cs_url,
    cs.image_path     AS cs_cover,
    cs.comic_condition,
    cs.price,
    
    u.id              AS seller_uid,
    u.username
FROM match_notifications m
JOIN comics_for_sale cs
   ON BINARY m.issue_url = BINARY cs.Issue_URL
JOIN users u
   ON m.seller_id = u.id
LEFT JOIN skipped_comics sc
   ON sc.match_id = m.id
   AND sc.user_id = ?
   AND sc.status IN ('skipped', 'interested') 
WHERE
   m.buyer_id  = ?
   AND sc.match_id IS NULL
   AND m.seller_id != ?
ORDER BY RAND()
LIMIT 1
";
$stmt = $conn->prepare($query);
$stmt->bind_param('iii', $user_id, $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$listing = $result->fetch_assoc();

// Fetch dynamic counts

// --- Sell Count ---
$sell_query = "SELECT COUNT(*) AS sell_count FROM comics_for_sale WHERE user_id = ?";
$stmtSell = $conn->prepare($sell_query);
$stmtSell->bind_param("i", $user_id);
$stmtSell->execute();
$resultSell = $stmtSell->get_result();
$sell_row = $resultSell->fetch_assoc();
$sell_count = $sell_row['sell_count'] ?? 0;

// --- Wanted Count ---
$wanted_query = "SELECT COUNT(*) AS wanted_count FROM wanted_items WHERE user_id = ?";
$stmtWanted = $conn->prepare($wanted_query);
$stmtWanted->bind_param("i", $user_id);
$stmtWanted->execute();
$resultWanted = $stmtWanted->get_result();
$wanted_row = $resultWanted->fetch_assoc();
$wanted_count = $wanted_row['wanted_count'] ?? 0;

// --- New Messages Count ---
$message_query = "SELECT COUNT(*) AS messages_count FROM private_messages WHERE recipient_id = ? AND is_read = 0";
$stmtMsg = $conn->prepare($message_query);
$stmtMsg->bind_param("i", $user_id);
$stmtMsg->execute();
$resultMsg = $stmtMsg->get_result();
$msg_row = $resultMsg->fetch_assoc();
$messages_count = $msg_row['messages_count'] ?? 0;

// Decide which cover to display
function getFinalCover($mnCover, $csCover) {
    return !empty($csCover) ? $csCover : (!empty($mnCover) ? $mnCover : '/comicsmp/placeholder.jpg');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Swipe Match – Mobile & PC</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <style>
    /* Global */
    body {
      font-family: Arial, sans-serif;
      text-align: center;
      background: #f4f4f4;
      margin: 0;
      padding: 0;
    }
    .container {
      width: 100%;
      max-width: 420px;
      margin: 20px auto;
      padding: 20px;
      background: #ffffff;
      border-radius: 10px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      position: relative;
      overflow: hidden;
    }
    /* Hamburger and Side Menu within Container */
    .hamburger {
      position: absolute;
      top: 10px;
      left: 10px;
      z-index: 1100;
      font-size: 1.8rem;
      cursor: pointer;
      color: #333;
    }
    .side-menu {
      position: absolute;
      top: 0;
      left: -250px;
      width: 250px;
      height: 100%;
      background: #ffffff;
      box-shadow: 2px 0 5px rgba(0,0,0,0.3);
      padding: 20px;
      transition: left 0.3s ease;
      z-index: 1050;
    }
    .side-menu.active {
      left: 0;
    }
    .side-menu h4 {
      margin-bottom: 20px;
    }
    .side-menu a {
      display: block;
      margin-bottom: 10px;
      text-decoration: none;
      color: #333;
    }
    /* Comic Card */
    .comic-card {
      text-align: center;
      padding: 15px;
      user-select: none;
    }
    .comic-info {
      display: flex;
      justify-content: center;
      gap: 15px;
      font-size: 1.4rem;
      font-weight: bold;
      color: #ffffff;
      padding: 10px;
      border-radius: 8px;
      margin-bottom: 10px;
    }
    .grade {
      background: #3498db;
      padding: 10px;
      border-radius: 8px;
    }
    .price {
      background: #e74c3c;
      padding: 10px;
      border-radius: 8px;
    }
    .comic-cover img {
      width: 100%;
      max-width: 300px;
      height: 450px;
      object-fit: cover;
      border-radius: 8px;
    }
    /* Optional Seller Info */
    .seller-info {
      font-size: 0.8rem;
      color: #aaa;
      margin-top: 5px;
    }
    /* Footer Navigation */
    .footer-menu {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 15px;
      padding: 10px;
      background: #ffffff;
      border-top: 1px solid #e0e0e0;
      border-radius: 0 0 10px 10px;
    }
    .footer-menu a {
      flex: 1;
      text-decoration: none;
      color: #333;
      font-size: 1.1rem;
      padding: 10px;
      transition: background 0.3s, transform 0.2s;
      border-radius: 6px;
      margin: 0 5px;
      background: #f4f4f4;
      /* Force a two-line layout for all tabs */
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      height: 60px;
    }
    .footer-menu a.active {
      background: #3498db;
      color: #fff;
    }
    .footer-menu a .tab-label {
      font-weight: bold;
    }
    .footer-menu a .tab-count {
      font-size: 0.9rem;
      margin-top: 2px;
    }
    /* Swipe Handling */
    .swipe-container {
      touch-action: none;
    }
  </style>
</head>
<body>
<div class="container swipe-container">
  <!-- Hamburger Icon inside the container -->
  <div class="hamburger" onclick="toggleMenu()">☰</div>
  <h2 style="margin-bottom: 1rem;">Swipe Match</h2>
  <?php if ($listing): ?>
    <?php
      // Extract data from listing
      $matchId     = $listing['match_id'];
      $comicTitle  = $listing['comic_title'];
      $issueNumber = $listing['issue_number'];
      $condition   = $listing['comic_condition'] ?? 'N/A';
      $price       = $listing['price'] ?? 'N/A';
      $cover       = getFinalCover($listing['mn_cover'], $listing['cs_cover']);
      $sellerId    = $listing['seller_uid'];
    ?>
    <div class="comic-card" id="swipe-card">
      <div class="comic-info">
        <div class="grade">Grade: <?= htmlspecialchars($condition) ?></div>
        <div class="price">$<?= htmlspecialchars($price) ?></div>
      </div>
      <h3><?= htmlspecialchars($comicTitle) ?> #<?= htmlspecialchars($issueNumber) ?></h3>
      <!-- Optional Seller Info Display -->
      <div class="seller-info">Seller: <?= htmlspecialchars($listing['username']) ?></div>
      <div class="comic-cover">
        <img src="<?= $cover ?>" alt="Comic Cover" onerror="this.onerror=null; this.src='/comicsmp/placeholder.jpg';">
      </div>
    </div>
  <?php else: ?>
    <p>No matches found.</p>
  <?php endif; ?>
  
  <!-- Footer Navigation: Three large buttons showing dynamic counts in two-line format -->
  <div class="footer-menu">
    <a href="/sell.php">
      <div class="tab-label">Selling</div>
      <div class="tab-count">(<?= $sell_count ?>)</div>
    </a>
    <a href="/wanted.php">
      <div class="tab-label">Wanted</div>
      <div class="tab-count">(<?= $wanted_count ?>)</div>
    </a>
    <a href="/messages.php">
      <div class="tab-label">Messages</div>
      <div class="tab-count">(<?= $messages_count ?>)</div>
    </a>
  </div>
  
  <!-- Side Menu placed as a child of the container -->
  <div class="side-menu" id="sideMenu">
    <h4>Menu</h4>
    <a href="/profile.php">Profile</a>
    <a href="/settings.php">Settings</a>
    <a href="/help.php">Help</a>
    <a href="/about.php">About</a>
  </div>
</div>

<script>
let startX, startY;

document.querySelector("#swipe-card").addEventListener("touchstart", function(e) {
    startX = e.touches[0].clientX;
    startY = e.touches[0].clientY;
});
document.querySelector("#swipe-card").addEventListener("mousedown", function(e) {
    startX = e.clientX;
    startY = e.clientY;
});
document.querySelector("#swipe-card").addEventListener("touchend", function(e) {
    let endX = e.changedTouches[0].clientX;
    let endY = e.changedTouches[0].clientY;
    handleSwipe(endX - startX, endY - startY);
});
document.querySelector("#swipe-card").addEventListener("mouseup", function(e) {
    let endX = e.clientX;
    let endY = e.clientY;
    handleSwipe(endX - startX, endY - startY);
});

function handleSwipe(diffX, diffY) {
    let threshold = 50;
    if (Math.abs(diffX) > Math.abs(diffY)) {
        if (diffX > threshold) expressInterest(<?= $matchId ?>, <?= $sellerId ?>);
        else if (diffX < -threshold) skipComic(<?= $matchId ?>);
    } else if (diffY < -threshold) {
        maybeComic();
    }
}

function skipComic(matchId) {
    $.post('skipComic.php', { match_id: matchId, status: 'skipped' }, () => location.reload());
}
function maybeComic() {
    setTimeout(() => location.reload(), 200);
}
function expressInterest(matchId, sellerId) {
    $.post('expressInterest.php', { match_id: matchId, seller_id: sellerId }, () => location.reload());
}

// Side Menu Toggle
function toggleMenu() {
    document.getElementById("sideMenu").classList.toggle("active");
}
</script>
</body>
</html>
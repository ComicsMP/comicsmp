<?php
session_start();
require_once 'db_connection.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p>You must be logged in to view this content.</p>";
    exit;
}

$userId = $_SESSION['user_id'];

/**
 * Helper function: getFinalImagePathV2
 * Assumes the database stores a relative path (e.g., "comics/images/cover1.jpg")
 * and prepends "../" so that the image can be accessed from the mobile folder.
 */
if (!function_exists('getFinalImagePathV2')) {
    function getFinalImagePathV2($rawPath) {
        if (!empty($rawPath)) {
            // Remove the "comicsmp/" prefix if it exists.
            if (substr($rawPath, 0, 9) === "comicsmp/") {
                $rawPath = substr($rawPath, 9);
            }
            // Prepend "../" for access from the mobile folder.
            if (substr($rawPath, 0, 1) !== "/") {
                return "../" . $rawPath;
            }
            return "../" . ltrim($rawPath, '/');
        }
        return "../images/placeholder.jpg";
    }
}

// -------------------- Retrieve Stats --------------------

// Wanted Items
$sqlWantedTotal = "SELECT COUNT(*) AS total FROM wanted_items WHERE user_id = ?";
$stmt = $conn->prepare($sqlWantedTotal);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$wantedTotal = $result->fetch_assoc()['total'];

$sqlWanted24 = "SELECT COUNT(*) AS count24 FROM wanted_items WHERE user_id = ? AND `Timestamp` >= NOW() - INTERVAL 1 DAY";
$stmt = $conn->prepare($sqlWanted24);
$stmt->bind_param("i", $userId);
$stmt->execute();
$wanted24 = $stmt->get_result()->fetch_assoc()['count24'];

$sqlWantedWeek = "SELECT COUNT(*) AS countWeek FROM wanted_items WHERE user_id = ? AND `Timestamp` >= NOW() - INTERVAL 7 DAY";
$stmt = $conn->prepare($sqlWantedWeek);
$stmt->bind_param("i", $userId);
$stmt->execute();
$wantedWeek = $stmt->get_result()->fetch_assoc()['countWeek'];

$sqlWantedMonth = "SELECT COUNT(*) AS countMonth FROM wanted_items WHERE user_id = ? AND `Timestamp` >= NOW() - INTERVAL 30 DAY";
$stmt = $conn->prepare($sqlWantedMonth);
$stmt->bind_param("i", $userId);
$stmt->execute();
$wantedMonth = $stmt->get_result()->fetch_assoc()['countMonth'];

// Comics for Sale
$sqlSaleTotal = "SELECT COUNT(*) AS total FROM comics_for_sale WHERE user_id = ?";
$stmt = $conn->prepare($sqlSaleTotal);
$stmt->bind_param("i", $userId);
$stmt->execute();
$saleTotal = $stmt->get_result()->fetch_assoc()['total'];

$sqlSale24 = "SELECT COUNT(*) AS count24 FROM comics_for_sale WHERE user_id = ? AND created_at >= NOW() - INTERVAL 1 DAY";
$stmt = $conn->prepare($sqlSale24);
$stmt->bind_param("i", $userId);
$stmt->execute();
$sale24 = $stmt->get_result()->fetch_assoc()['count24'];

$sqlSaleWeek = "SELECT COUNT(*) AS countWeek FROM comics_for_sale WHERE user_id = ? AND created_at >= NOW() - INTERVAL 7 DAY";
$stmt = $conn->prepare($sqlSaleWeek);
$stmt->bind_param("i", $userId);
$stmt->execute();
$saleWeek = $stmt->get_result()->fetch_assoc()['countWeek'];

$sqlSaleMonth = "SELECT COUNT(*) AS countMonth FROM comics_for_sale WHERE user_id = ? AND created_at >= NOW() - INTERVAL 30 DAY";
$stmt = $conn->prepare($sqlSaleMonth);
$stmt->bind_param("i", $userId);
$stmt->execute();
$saleMonth = $stmt->get_result()->fetch_assoc()['countMonth'];

// Matches
$sqlMatchesTotal = "SELECT COUNT(*) AS total FROM match_notifications WHERE buyer_id = ? OR seller_id = ?";
$stmt = $conn->prepare($sqlMatchesTotal);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$matchesTotal = $stmt->get_result()->fetch_assoc()['total'];

$sqlMatches24 = "SELECT COUNT(*) AS count24 FROM match_notifications WHERE (buyer_id = ? OR seller_id = ?) AND match_time >= NOW() - INTERVAL 1 DAY";
$stmt = $conn->prepare($sqlMatches24);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$matches24 = $stmt->get_result()->fetch_assoc()['count24'];

$sqlMatchesWeek = "SELECT COUNT(*) AS countWeek FROM match_notifications WHERE (buyer_id = ? OR seller_id = ?) AND match_time >= NOW() - INTERVAL 7 DAY";
$stmt = $conn->prepare($sqlMatchesWeek);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$matchesWeek = $stmt->get_result()->fetch_assoc()['countWeek'];

$sqlMatchesMonth = "SELECT COUNT(*) AS countMonth FROM match_notifications WHERE (buyer_id = ? OR seller_id = ?) AND match_time >= NOW() - INTERVAL 30 DAY";
$stmt = $conn->prepare($sqlMatchesMonth);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$matchesMonth = $stmt->get_result()->fetch_assoc()['countMonth'];

// -------------------- Dynamic Font Size for Matches --------------------
$matchesDigits = strlen((string)$matchesTotal);
if ($matchesDigits == 1) {
    $matchesFontSize = "100px"; // One digit: very large (100px)
} elseif ($matchesDigits == 2) {
    $matchesFontSize = "80px";  // Two digits: slightly smaller
} else {
    $matchesFontSize = "60px";  // Three or more digits: smaller
}

// -------------------- Retrieve Recent Items --------------------

// Recent Wanted Comics
$sqlMyRecentWanted = "SELECT w.Comic_Title, w.Issue_Number, w.Issue_URL, c.Image_Path 
                      FROM wanted_items AS w 
                      LEFT JOIN comics AS c ON w.Issue_URL = c.Issue_URL 
                      WHERE w.user_id = ? 
                      ORDER BY w.ID DESC 
                      LIMIT 4";
$stmt = $conn->prepare($sqlMyRecentWanted);
$stmt->bind_param("i", $userId);
$stmt->execute();
$resultMyWanted = $stmt->get_result();
$myRecentWanted = [];
while ($row = $resultMyWanted->fetch_assoc()) {
    $myRecentWanted[] = $row;
}
$stmt->close();

// Recent Comics for Sale
$sqlMyRecentSales = "SELECT image_path FROM comics_for_sale WHERE user_id = ? ORDER BY id DESC LIMIT 4";
$stmt = $conn->prepare($sqlMyRecentSales);
$stmt->bind_param("i", $userId);
$stmt->execute();
$resultMySales = $stmt->get_result();
$myRecentSales = [];
while ($row = $resultMySales->fetch_assoc()) {
    $myRecentSales[] = $row;
}
$stmt->close();

// Recent Matches
$sqlMyRecentMatches = "SELECT m.comic_title, m.issue_number, m.match_time, c.Image_Path 
                       FROM match_notifications m 
                       LEFT JOIN comics c ON m.comic_title = c.comic_title AND m.issue_number = c.issue_number 
                       WHERE m.buyer_id = ? OR m.seller_id = ? 
                       ORDER BY m.match_time DESC 
                       LIMIT 4";
$stmt = $conn->prepare($sqlMyRecentMatches);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$resultMyMatches = $stmt->get_result();
$myRecentMatches = [];
while ($row = $resultMyMatches->fetch_assoc()) {
    $myRecentMatches[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mobile Dashboard</title>
  <style>
    /* Basic Reset */
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Roboto', sans-serif; background: #f4f4f4; color: #333; padding-bottom: 60px; }
    a { text-decoration: none; color: inherit; }
    
    /* Top Header with Logo & Icons */
    .top-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #1a1a1a;
      padding: 10px 15px;
      color: #fff;
    }
    .top-header .logo img {
      height: 40px;
    }
    .top-header .icons {
      display: flex;
      gap: 15px;
    }
    .top-header .icons a {
      color: #fff;
      font-size: 16px;
    }
    
    /* Top Section - Two Column Grid for Stats */
    .top-section {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      margin: 10px;
    }
    /* Left Column for Wanted & Sale */
    .left-column {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .left-column .card {
      background: #fff;
      padding: 10px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      text-align: center;
    }
    .left-column .card h3 { font-size: 16px; margin-bottom: 5px; }
    .left-column .card p { font-size: 20px; font-weight: bold; }
    .left-column .card small { font-size: 12px; color: #666; white-space: nowrap; }
    
    /* Right Column for Matches (Emphasized) */
    .right-column {
      background: #575757;
      padding: 10px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      text-align: center;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }
    .right-column .matches-header { 
      font-size: 26px; 
      margin-bottom: 5px; 
      color: #fff;
      padding: 5px;
      border-radius: 4px;
    }
    .right-column .matches-number {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      font-size: <?php echo $matchesFontSize; ?>;
      margin: 5px 0;
    }
    .right-column .stats-detail {
      font-size: 12px;
      color: #fff;
      white-space: nowrap;
    }
    
    /* Recent Sections */
    .recent-section {
      margin: 10px;
    }
    .recent-section h3 { margin-bottom: 10px; font-size: 16px; }
    .recent-list {
      display: flex;
      overflow-x: auto;
      gap: 10px;
      padding-bottom: 10px;
    }
    .recent-card {
      min-width: 120px;
      max-width: 120px;
      background: #fff;
      padding: 10px;
      border-radius: 8px;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      text-align: center;
      flex-shrink: 0;
    }
    .recent-card img {
      width: 100%;
      height: 150px; /* Fixed height for uniformity */
      object-fit: cover;
      border-radius: 4px;
    }
    
    /* Bottom Navigation Bar */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      background: #575757;
      box-shadow: 0 -2px 4px rgba(0,0,0,0.3);
      display: flex;
      justify-content: space-around;
      padding: 10px 0;
      font-size: 12px;
    }
    .bottom-nav a {
      color: #fff;
      text-align: center;
      flex: 1;
      text-decoration: none;
      padding: 6px 0;
      transition: background 0.3s, transform 0.3s;
    }
    .bottom-nav a:hover {
      background: rgba(255,255,255,0.2);
      transform: scale(1.05);
    }
  </style>
</head>
<body>
  <!-- Top Header with Logo and Icons -->
  <div class="top-header">
    <div class="logo">
      <img src="../logo.png" alt="Logo">
    </div>
    <div class="icons">
      <a href="inbox.php">Inbox</a>
      <a href="settings.php">Profile</a>
    </div>
  </div>
  
  <!-- Top Section: Two Columns -->
  <div class="top-section">
    <!-- Left Column: Wanted Comics & Comics for Sale -->
    <div class="left-column">
      <div class="card">
        <h3>Wanted Comics</h3>
        <p><?php echo $wantedTotal; ?></p>
        <small>24h: <?php echo $wanted24; ?> | Wk: <?php echo $wantedWeek; ?> | Mo: <?php echo $wantedMonth; ?></small>
      </div>
      <div class="card">
        <h3>For Sale</h3>
        <p><?php echo $saleTotal; ?></p>
        <small>24h: <?php echo $sale24; ?> | Wk: <?php echo $saleWeek; ?> | Mo: <?php echo $saleMonth; ?></small>
      </div>
    </div>
    <!-- Right Column: Matches (Emphasized) -->
    <div class="right-column">
      <div class="matches-header">Matches</div>
      <div class="matches-number"><?php echo $matchesTotal; ?></div>
      <div class="stats-detail">24h: <?php echo $matches24; ?> | Wk: <?php echo $matchesWeek; ?> | Mo: <?php echo $matchesMonth; ?></div>
    </div>
  </div>
  
  <!-- Recent Wanted Comics (Covers Only) -->
  <div class="recent-section">
    <h3>Recent Wanted</h3>
    <div class="recent-list">
      <?php if (!empty($myRecentWanted)): ?>
        <?php foreach ($myRecentWanted as $wanted):
                $rawPathWanted = $wanted['Image_Path'];
                $finalImageWanted = getFinalImagePathV2($rawPathWanted);
        ?>
          <div class="recent-card">
            <a href="wanted.php">
              <img src="<?php echo htmlspecialchars($finalImageWanted); ?>" alt="">
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No recent wanted comics.</p>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Recent Comics for Sale (Covers Only) -->
  <div class="recent-section">
    <h3>Recent For Sale</h3>
    <div class="recent-list">
      <?php if (!empty($myRecentSales)): ?>
        <?php foreach ($myRecentSales as $sale):
                $rawPathSale = $sale['image_path'];
                $finalImageSale = getFinalImagePathV2($rawPathSale);
        ?>
          <div class="recent-card">
            <a href="selling.php">
              <img src="<?php echo htmlspecialchars($finalImageSale); ?>" alt="">
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No recent for sale comics.</p>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Recent Matches (Covers Only) -->
  <div class="recent-section">
    <h3>Recent Matches</h3>
    <div class="recent-list">
      <?php if (!empty($myRecentMatches)): ?>
        <?php foreach ($myRecentMatches as $match):
                $rawPathMatch = $match['Image_Path'];
                $finalImageMatch = getFinalImagePathV2($rawPathMatch);
        ?>
          <div class="recent-card">
            <a href="matches.php">
              <img src="<?php echo htmlspecialchars($finalImageMatch); ?>" alt="">
            </a>
          </div>
        <?php endforeach; ?>
      <?php else: ?>
        <p>No recent matches.</p>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Bottom Navigation Bar (4 Items) -->
  <div class="bottom-nav">
    <a href="dashboard.php">Home</a>
    <a href="wanted.php">Wanted</a>
    <a href="selling.php">For Sale</a>
    <a href="matches.php">Matches</a>
  </div>
  
  <!-- JavaScript to auto-reduce font size for matches stats if needed -->
  <script>
    window.addEventListener("load", function() {
      const statsDetail = document.querySelector(".right-column .stats-detail");
      if (!statsDetail) return;
      
      // Function to adjust font size until the text fits its container
      function adjustFontSize() {
        // Get the computed style and current font size in pixels
        let fontSize = parseInt(window.getComputedStyle(statsDetail).fontSize);
        const containerWidth = statsDetail.parentElement.clientWidth;
        
        // Reduce font size until the text's scroll width is less than or equal to container width.
        while (statsDetail.scrollWidth > containerWidth && fontSize > 8) { // 8px as minimum
          fontSize--;
          statsDetail.style.fontSize = fontSize + "px";
        }
      }
      
      adjustFontSize();
      window.addEventListener("resize", adjustFontSize);
    });
  </script>
</body>
</html>

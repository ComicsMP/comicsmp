<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo "Please log in.";
    exit;
}

$user_id = $_SESSION['user_id'];

// --- Selling Overview ---
$selling_sql = "SELECT comic_title, COUNT(*) AS sale_count 
                FROM comics_for_sale 
                WHERE user_id = ? 
                GROUP BY comic_title 
                ORDER BY sale_count DESC LIMIT 5";
$stmt = $conn->prepare($selling_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultSelling = $stmt->get_result();
$sellingItems = [];
while ($row = $resultSelling->fetch_assoc()) {
    $sellingItems[] = $row;
}
$stmt->close();

// --- Wanted Overview ---
$wanted_sql = "SELECT comic_title, COUNT(*) AS wanted_count 
               FROM wanted_items 
               WHERE user_id = ? 
               GROUP BY comic_title 
               ORDER BY wanted_count DESC LIMIT 5";
$stmt = $conn->prepare($wanted_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultWanted = $stmt->get_result();
$wantedItems = [];
while ($row = $resultWanted->fetch_assoc()) {
    $wantedItems[] = $row;
}
$stmt->close();

// --- Matches Overview ---
$matches_sql = "SELECT comic_title, issue_number, match_time,
               CASE WHEN buyer_id = ? THEN 'Buy' ELSE 'Sell' END AS role
               FROM match_notifications
               WHERE buyer_id = ? OR seller_id = ?
               ORDER BY match_time DESC LIMIT 5";
$stmt = $conn->prepare($matches_sql);
$stmt->bind_param("iii", $user_id, $user_id, $user_id);
$stmt->execute();
$resultMatches = $stmt->get_result();
$matchesItems = [];
while ($row = $resultMatches->fetch_assoc()) {
    $matchesItems[] = $row;
}
$stmt->close();
?>
<div>
  <h4>Marketplace Overview</h4>
  <div class="row">
    <!-- Selling Section -->
    <div class="col-md-4">
      <h5>Selling</h5>
      <?php if (empty($sellingItems)): ?>
        <p>No comics listed for sale.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($sellingItems as $item): ?>
            <li><?php echo htmlspecialchars($item['comic_title']); ?> (<?php echo $item['sale_count']; ?> listings)</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <!-- Wanted Section -->
    <div class="col-md-4">
      <h5>Wanted</h5>
      <?php if (empty($wantedItems)): ?>
        <p>No comics on your wanted list.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($wantedItems as $item): ?>
            <li><?php echo htmlspecialchars($item['comic_title']); ?> (<?php echo $item['wanted_count']; ?> wanted)</li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
    <!-- Matches Section -->
    <div class="col-md-4">
      <h5>Matches</h5>
      <?php if (empty($matchesItems)): ?>
        <p>No matches found.</p>
      <?php else: ?>
        <ul>
          <?php foreach ($matchesItems as $item): ?>
            <li>
              <?php echo htmlspecialchars($item['comic_title']); ?>
              <?php if (!empty($item['issue_number'])): ?>
                #<?php echo htmlspecialchars($item['issue_number']); ?>
              <?php endif; ?>
              <br>
              <small><?php echo date("M d, Y H:i", strtotime($item['match_time'])); ?> (<?php echo $item['role']; ?>)</small>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</div>

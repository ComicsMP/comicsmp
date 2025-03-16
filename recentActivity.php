<?php
session_start();
require_once 'db_connection.php';
if (!isset($_SESSION['user_id'])) {
    echo "Please log in.";
    exit;
}

$user_id = $_SESSION['user_id'];

// For demonstration, we use a UNION of three queries.
// You may need to adjust the field names and timestamps as per your schema.
$activity_sql = "
    (SELECT 'Match' AS activity_type, comic_title, issue_number, match_time AS activity_time 
     FROM match_notifications 
     WHERE buyer_id = ? OR seller_id = ?)
    UNION
    (SELECT 'Sale' AS activity_type, comic_title, issue_number, sent_at AS activity_time 
     FROM comics_for_sale 
     WHERE user_id = ?)
    UNION
    (SELECT 'Wanted' AS activity_type, comic_title, '' AS issue_number, created_at AS activity_time 
     FROM wanted_items 
     WHERE user_id = ?)
    ORDER BY activity_time DESC LIMIT 5
";
$stmt = $conn->prepare($activity_sql);
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$resultActivity = $stmt->get_result();
$activityItems = [];
while($row = $resultActivity->fetch_assoc()){
    $activityItems[] = $row;
}
$stmt->close();
?>
<div>
  <h4>Recent Activity</h4>
  <?php if (empty($activityItems)): ?>
    <p>No recent activity.</p>
  <?php else: ?>
    <ul>
      <?php foreach ($activityItems as $act): ?>
        <li>
          <strong><?php echo htmlspecialchars($act['activity_type']); ?></strong>: 
          <?php echo htmlspecialchars($act['comic_title']); ?>
          <?php if (!empty($act['issue_number'])): ?>
            #<?php echo htmlspecialchars($act['issue_number']); ?>
          <?php endif; ?>
          <br>
          <small><?php echo date("M d, Y H:i", strtotime($act['activity_time'])); ?></small>
        </li>
      <?php endforeach; ?>
    </ul>
  <?php endif; ?>
</div>

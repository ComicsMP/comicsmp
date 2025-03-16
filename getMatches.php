<?php
// getMatches.php
require_once 'db_connection.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? 0;

// --- FETCH MATCHES (same as in profile.php) ---
$sqlMatches = "
    SELECT 
        mn.id, 
        mn.comic_title, 
        mn.issue_number, 
        mn.years, 
        mn.issue_url, 
        mn.cover_image, 
        mn.match_time, 
        mn.status,
        mn.buyer_id,
        mn.seller_id,
        cs.comic_condition,
        cs.graded,
        cs.price
    FROM match_notifications mn
    LEFT JOIN comics_for_sale cs ON (cs.issue_url = mn.issue_url AND cs.user_id = mn.seller_id)
    WHERE (mn.buyer_id = ? OR mn.seller_id = ?)
    ORDER BY mn.match_time DESC
";
$stmtMatches = $conn->prepare($sqlMatches);
$stmtMatches->bind_param("ii", $user_id, $user_id);
$stmtMatches->execute();
$resultMatches = $stmtMatches->get_result();
$matches = [];
$otherUserIds = [];

while ($row = $resultMatches->fetch_assoc()) {
    $matches[] = $row;
    if ($row['buyer_id'] == $user_id) {
        $otherUserIds[] = $row['seller_id'];
    } else {
        $otherUserIds[] = $row['buyer_id'];
    }
}
$stmtMatches->close();

// Build a map of user_id => username
$otherUserIds = array_unique($otherUserIds);
$userNamesMap = [];
if (!empty($otherUserIds)) {
    $placeholders = implode(',', array_fill(0, count($otherUserIds), '?'));
    $types = str_repeat('i', count($otherUserIds));
    $sqlUsers = "SELECT id, username FROM users WHERE id IN ($placeholders)";
    $stmtUsers = $conn->prepare($sqlUsers);
    $stmtUsers->bind_param($types, ...$otherUserIds);
    $stmtUsers->execute();
    $resUsers = $stmtUsers->get_result();
    while ($u = $resUsers->fetch_assoc()) {
        $userNamesMap[$u['id']] = $u['username'];
    }
    $stmtUsers->close();
}

// Group matches by the “other” user
$groupedMatches = [];
foreach ($matches as $m) {
    $otherId = ($m['buyer_id'] == $user_id) ? $m['seller_id'] : $m['buyer_id'];
    $groupedMatches[$otherId][] = $m;
}
?>
<!-- Output the matches table HTML -->
<table class="table table-striped" id="matchesTable">
  <thead>
    <tr>
      <th>Other Party</th>
      <th># of Issues Matched</th>
      <th>Contact</th>
      <th>Expand</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($groupedMatches as $otherUserId => $matchesArray): ?>
      <tr class="match-main-row" data-index="<?php echo $otherUserId; ?>">
        <td><?php echo htmlspecialchars($userNamesMap[$otherUserId] ?? ('User #'.$otherUserId)); ?></td>
        <td><?php echo count($matchesArray); ?></td>
        <td>
          <a href="sendMessage.php?to=<?php echo $otherUserId; ?>" class="btn btn-sm btn-primary">PM</a>
        </td>
        <td>
          <button class="btn btn-info btn-sm expand-match-btn" data-other-user-id="<?php echo $otherUserId; ?>">Expand</button>
        </td>
      </tr>
      <tr class="expand-match-row" id="expand-match-<?php echo $otherUserId; ?>" style="display:none;">
        <td colspan="4">
          <!-- You can output detailed match info here -->
          <?php echo "Detailed match info here for user $otherUserId."; ?>
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

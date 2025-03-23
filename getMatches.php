<?php
// getMatches.php
require_once 'db_connection.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$user_id = $_SESSION['user_id'] ?? 0;

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
        cs.price,
        c.Variant AS variant,
        c.UPC AS upc
    FROM match_notifications mn
    LEFT JOIN comics_for_sale cs 
        ON (cs.issue_url = mn.issue_url AND cs.user_id = mn.seller_id)
    LEFT JOIN comics c 
        ON TRIM(mn.issue_url) = TRIM(c.issue_url)
    WHERE (mn.buyer_id = ? OR mn.seller_id = ?)
      AND mn.status IN ('new', 'viewed')
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

$groupedMatches = [];
foreach ($matches as $m) {
    $otherId = ($m['buyer_id'] == $user_id) ? $m['seller_id'] : $m['buyer_id'];
    $groupedMatches[$otherId][] = $m;
}
?>
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
          Detailed match info here for user <?php echo $otherUserId; ?>.
        </td>
      </tr>
    <?php endforeach; ?>
  </tbody>
</table>

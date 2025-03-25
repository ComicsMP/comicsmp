<?php
require_once 'db_connection.php';

// Fetch match data
$query = "SELECT * FROM match_notifications WHERE buyer_id = ? OR seller_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

$matches = [];
while ($row = $result->fetch_assoc()) {
    $matches[] = $row;
}
$stmt->close();

// Group matches by other user
$groupedMatches = [];
foreach ($matches as $m) {
    $otherUserId = $m['buyer_id'] == $user_id ? $m['seller_id'] : $m['buyer_id'];
    $groupedMatches[$otherUserId][] = $m;
}

// You might need a user name lookup array here
$userNamesMap = [];

?>
<div class="accordion" id="matchesAccordion">
<?php foreach ($groupedMatches as $otherUserId => $matchesArray):
    $displayName = $userNamesMap[$otherUserId] ?? ('User #'.$otherUserId);
    $buyMatches = array_filter($matchesArray, fn($m) => $m['buyer_id'] == $user_id);
    $sellMatches = array_filter($matchesArray, fn($m) => $m['seller_id'] == $user_id);
    $intent = ($buyMatches && !$sellMatches) ? 'buy' : (($sellMatches && !$buyMatches) ? 'sell' : 'buy_sell');
?>
  <div class="accordion-item">
    <h2 class="accordion-header" id="heading-<?php echo $otherUserId; ?>">
      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?php echo $otherUserId; ?>">
        <?php echo htmlspecialchars($displayName); ?> (<?php echo count($matchesArray); ?> matches)
      </button>
    </h2>
    <div id="collapse-<?php echo $otherUserId; ?>" class="accordion-collapse collapse" data-bs-parent="#matchesAccordion">
      <div class="accordion-body">
        <p>Buy/Sell info goes here...</p>
        <!-- You can reuse your original match tab layout from mainContent.php here -->
      </div>
    </div>
  </div>
<?php endforeach; ?>
</div>

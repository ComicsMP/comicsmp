<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(403);
  echo '<p class="text-danger">Access denied.</p>';
  exit;
}

$userId = $_SESSION['user_id'];

// Fetch user favorites
$favorites = [];
$stmt = $conn->prepare("
  SELECT comic_title, years
  FROM user_favorite_titles
  WHERE user_id = ?
  ORDER BY favorited_at DESC
");
$stmt->bind_param("i", $userId);
$stmt->execute();
$res = $stmt->get_result();
while ($r = $res->fetch_assoc()) {
  $favorites[] = $r;
}
$stmt->close();

if (empty($favorites)) {
  echo '<p class="text-center py-4 text-muted">You have not added any favorites yet.</p>';
  exit;
}
?>

<!-- ✅ Matches Manual/Cover layout and padding -->
<div class="tab-content active px-0" style="display:block; background:#fff;">
  <div class="border rounded bg-white shadow-sm p-3 mb-3 mx-2" style="border-left: 4px solid #007BFF;">
    <div class="text-center fw-bold mb-3" style="font-size: 1.2rem;">My Favorite Series</div>

    <?php foreach ($favorites as $fav): ?>
      <div class="d-flex justify-content-between align-items-center border-bottom py-2">
        <div class="flex-grow-1">
          <div class="fw-semibold" style="font-size: 0.95rem;"><?= htmlspecialchars($fav['comic_title']) ?></div>
          <div class="text-muted small"><?= htmlspecialchars($fav['years']) ?></div>
        </div>
        <div class="ms-2 flex-shrink-0">
          <button class="btn btn-sm btn-primary apply-fav-search"
            data-title="<?= htmlspecialchars($fav['comic_title'], ENT_QUOTES) ?>"
            data-year="<?= htmlspecialchars($fav['years'], ENT_QUOTES) ?>"
            data-country="USA">
            Search
          </button>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

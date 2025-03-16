<?php
session_start();
include 'db_connection.php';

$user_id = $_SESSION['user_id'] ?? null;
if(!$user_id) exit;

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
JOIN comics_for_sale cs ON BINARY m.issue_url = BINARY cs.Issue_URL
JOIN users u ON m.seller_id = u.id
LEFT JOIN skipped_comics sc ON sc.match_id = m.id
   AND sc.user_id = ?
   AND sc.status IN ('skipped','interested')
WHERE m.buyer_id = ?
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

function getFinalCover($mnCover, $csCover) {
    return !empty($csCover) ? $csCover : (!empty($mnCover) ? $mnCover : '/comicsmp/placeholder.jpg');
}

if ($listing) {
    $cover = getFinalCover($listing['mn_cover'], $listing['cs_cover']);
    ?>
    <div class="comic-card" id="swipe-card">
      <div class="comic-info">
        <div class="grade">Grade: <?= htmlspecialchars($listing['comic_condition'] ?? 'N/A') ?></div>
        <div class="price">$<?= htmlspecialchars($listing['price'] ?? 'N/A') ?></div>
      </div>
      <h3><?= htmlspecialchars($listing['comic_title']) ?> #<?= htmlspecialchars($listing['issue_number']) ?></h3>
      <div class="seller-info">Seller: <?= htmlspecialchars($listing['username']) ?></div>
      <div class="comic-cover">
        <img src="<?= $cover ?>" alt="Comic Cover" onerror="this.onerror=null; this.src='/comicsmp/placeholder.jpg';">
      </div>
    </div>
    <?php
} else {
    echo "<p>No matches found.</p>";
}
?>

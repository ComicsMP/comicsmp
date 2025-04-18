<?php
// matches.php — table-based layout for Matches tab

// Ensure session and login
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}

// Grade mapping
$gradeMapping = [
    "10.0" => "Gem Mint", "9.9" => "Mint", "9.8" => "NM/M", "9.6" => "NM+",
    "9.4" => "NM", "9.2" => "NM-", "9.0" => "VF/NM", "8.5" => "VF+",
    "8.0" => "VF", "7.5" => "VF-", "7.0" => "FN/VF", "6.5" => "FN+",
    "6.0" => "FN", "5.5" => "FN-", "5.0" => "VG/FN", "4.5" => "VG+",
    "4.0" => "VG", "3.5" => "VG-", "3.0" => "G/VG", "2.5" => "G",
    "2.0" => "G", "1.8" => "G-", "1.5" => "Fa/G", "1.0" => "Fa",
    "0.5" => "Poor"
];

function formatScore($score) {
    if (is_numeric($score)) {
        $f = floatval($score);
        return ($f == intval($f)) ? intval($f) : $score;
    }
    return $score;
}

function getScoreKey($score) {
    return is_numeric($score) ? number_format(floatval($score), 1) : $score;
}

function fixImagePath($path) {
    $fixed = str_replace("images/images", "images", $path);
    return "/comicsmp/" . ltrim($fixed, '/');
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    $dlat = $lat2 - $lat1;
    $dlon = $lon2 - $lon1;
    $a = pow(sin($dlat / 2), 2) + cos($lat1) * cos($lat2) * pow(sin($dlon / 2), 2);
    $c = 2 * asin(sqrt($a));
    return round($c * 3956) . " mi";
}

require_once '../db_connection.php';

$sql = <<<SQL
SELECT mn.*, buyer.preferred_payment AS buyer_payment, seller.preferred_payment AS seller_payment,
       seller.preferred_currency AS seller_currency,
       cfs.price, cfs.comic_condition, cfs.graded,

       buyer.city AS buyer_city, buyer.latitude AS buyer_lat, buyer.longitude AS buyer_lng,
       seller.city AS seller_city, seller.latitude AS seller_lat, seller.longitude AS seller_lng,
       mn.cover_image
FROM match_notifications mn
LEFT JOIN comics_for_sale cfs
  ON cfs.issue_url = mn.issue_url AND cfs.user_id IN (mn.buyer_id, mn.seller_id)
LEFT JOIN users buyer  ON buyer.id  = mn.buyer_id
LEFT JOIN users seller ON seller.id = mn.seller_id
WHERE mn.buyer_id = ? OR mn.seller_id = ?
ORDER BY mn.match_time DESC
SQL;

$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

$groupedMatches = [];
while ($row = $result->fetch_assoc()) {
    $other = ($row['buyer_id'] == $userId) ? $row['seller_id'] : $row['buyer_id'];
    $groupedMatches[$other][] = $row;
}
$stmt->close();

$userNamesMap = [];
if (!empty($groupedMatches)) {
    $ids = array_keys($groupedMatches);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $sqlU = "SELECT id, username, city FROM users WHERE id IN ($placeholders)";
    $stmtU = $conn->prepare($sqlU);
    $stmtU->bind_param(str_repeat('i', count($ids)), ...$ids);
    $stmtU->execute();
    $resU = $stmtU->get_result();
    while ($u = $resU->fetch_assoc()) {
        $userNamesMap[$u['id']] = $u;
    }
    $stmtU->close();
}
?>


<div id="matchesContainer">
<?php if (empty($groupedMatches)): ?>
  <p>No matches found.</p>
<?php else: ?>
  <table class="table table-striped" id="matchesTable">
    <thead>
      <tr>
        <th>Matches</th>
        <th>User Name</th>
        <th>City – Distance</th>
        <th>Transaction</th>
        <th>Payment</th>
        <th>PM</th>
        <th>Expand</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($groupedMatches as $otherId => $group):
      $first = reset($group);
      $displayName = $userNamesMap[$otherId]['username'] ?? 'User #'.$otherId;
      $city = $first['buyer_id'] == $userId ? ($first['seller_city'] ?? 'N/A') : ($first['buyer_city'] ?? 'N/A');
      $dist = (!empty($first['buyer_lat']) && !empty($first['seller_lat']))
            ? calculateDistance($first['buyer_lat'], $first['buyer_lng'], $first['seller_lat'], $first['seller_lng'])
            : 'N/A';
      $buyCount = count(array_filter($group, fn($m) => $m['buyer_id'] == $userId));
      $sellCount = count(array_filter($group, fn($m) => $m['seller_id'] == $userId));
      $transaction = trim(($buyCount ? "Buy ($buyCount)" : "") . ($sellCount ? " / Sell ($sellCount)" : ""));
      $payment = ($first['buyer_id'] == $userId)
               ? htmlspecialchars($first['seller_payment'] ?? '-')
               : htmlspecialchars($first['buyer_payment'] ?? '-');
    ?>
      <tr class="main-row" data-user-id="<?= $otherId ?>">
        <td><?= count($group) ?></td>
        <td><strong><?= htmlspecialchars($displayName) ?></strong></td>
        <td><?= htmlspecialchars($city) ?> – <?= htmlspecialchars($dist) ?></td>
        <td><?= htmlspecialchars($transaction) ?></td>
        <td><?= $payment ?></td>
        <td><button class="btn btn-sm btn-outline-secondary pm-btn" data-user-id="<?= $otherId ?>">✉️</button></td>
        <td><button class="btn btn-sm btn-info expand-btn" data-user-id="<?= $otherId ?>">▶</button></td>
      </tr>
      <tr class="detail-row" id="detail-<?= $otherId ?>" style="display:none;">
        <td colspan="7">
          <table class="table table-bordered table-sm mb-0">
            <thead class="table-light">
              <tr><th>Cover</th><th>Comic Title</th><th>Years</th><th>Issue #</th><th>Condition</th><th>Price</th></tr>
            </thead>
            <tbody>
            <?php foreach ($group as $m):
              $key = getScoreKey($m['comic_condition'] ?? '');
              $cond = $gradeMapping[$key] ?? $m['comic_condition'] ?? 'N/A';
              $price = isset($m['price']) ? number_format($m['price'], 2) : 'N/A';
            ?>
              <tr>
  <td><img src="<?= htmlspecialchars(fixImagePath($m['cover_image'])) ?>"
           class="match-cover-img"
           data-bs-toggle="modal"
           data-bs-target="#coverModal"
           data-img-src="<?= htmlspecialchars(fixImagePath($m['cover_image'])) ?>"
           data-comic-title="<?= htmlspecialchars($m['comic_title']) ?>"
           data-years="<?= htmlspecialchars($m['years']) ?>"
           data-issue-number="<?= htmlspecialchars($m['issue_number']) ?>"
           data-condition="<?= htmlspecialchars($cond) ?>"
           data-graded="<?= ($m['graded'] === '1' || $m['graded'] === 1) ? 'Yes' : (($m['graded'] === '0' || $m['graded'] === 0) ? 'No' : 'N/A') ?>"

           data-price="<?= htmlspecialchars($price . ' ' . ($m['seller_currency'] ?? '')) ?>"

           style="width:40px;height:60px;object-fit:cover;cursor:pointer;"></td>
  <td><?= htmlspecialchars($m['comic_title']) ?></td>
  <td><?= htmlspecialchars($m['years']) ?></td>
  <td><?= htmlspecialchars($m['issue_number']) ?></td>
  <td><?= htmlspecialchars($cond) ?></td>
  <td><?= '$' . htmlspecialchars($price) . ' ' . htmlspecialchars($m['seller_currency'] ?? '') ?></td>

</tr>

            <?php endforeach; ?>
            </tbody>
          </table>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
</div>

<!-- Modal -->
<div class="modal fade" id="coverModal" tabindex="-1" aria-labelledby="coverModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="coverModalLabel">Cover Preview</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" alt="Cover Image" id="coverModalImage" class="img-fluid">
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="comicDetailsPopup" tabindex="-1" aria-labelledby="comicDetailsPopupLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="comicDetailsPopupLabel">Comic Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p><strong>Tab:</strong> <span id="popupTab">Loading...</span></p>
        <p><strong>Variant:</strong> <span id="popupVariant">Loading...</span></p>
        <p><strong>Date:</strong> <span id="popupDate">Loading...</span></p>
        <p><strong>UPC:</strong> <span id="popupUPC"></span></p>
      </div>
    </div>
  </div>
</div>

<script>
$(document).ready(function() {
    $('.expand-btn').on('click', function() {
        const uid = $(this).data('user-id');
        $('#detail-' + uid).toggle();
    });

    $('.pm-btn').on('click', function() {
        const uid = $(this).data('user-id');
        alert('Open PM to user ' + uid);
    });

    $('.match-cover-img').on('click', function() {
        const img = $(this);
        const src = img.data('img-src');
        $('#coverModalImage').attr('src', src);

        const comicTitle = img.data('comic-title');
        const years = img.data('years');
        const issueNumber = img.data('issue-number');

        $.ajax({
            url: 'getMatchComicDetails.php',
            method: 'GET',
            data: {
                comic_title: comicTitle,
                years: years,
                issue_number: issueNumber
            },
            dataType: 'json',
            success: function(data) {
                $('#popupTab').text(data.Tab || 'N/A');
                $('#popupVariant').text(data.Variant || 'N/A');
                $('#popupDate').text(data.Date || 'N/A');
                $('#popupUPC').text(data.upc || 'N/A');
                $('#comicDetailsPopup').modal('show');
            },
            error: function() {
                $('#popupTab, #popupVariant, #popupDate, #popupUPC').text('Error');
            }
        });
    });
});
</script>

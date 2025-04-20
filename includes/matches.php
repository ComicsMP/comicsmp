<?php
// matches.php — table-based layout for Matches tab, with client‑side filters

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
    "10.0"=>"Gem Mint","9.9"=>"Mint","9.8"=>"NM/M","9.6"=>"NM+",
    "9.4"=>"NM","9.2"=>"NM-","9.0"=>"VF/NM","8.5"=>"VF+","8.0"=>"VF",
    "7.5"=>"VF-","7.0"=>"FN/VF","6.5"=>"FN+","6.0"=>"FN","5.5"=>"FN-",
    "5.0"=>"VG/FN","4.5"=>"VG+","4.0"=>"VG","3.5"=>"VG-","3.0"=>"G/VG",
    "2.5"=>"G","2.0"=>"G","1.8"=>"G-","1.5"=>"Fa/G","1.0"=>"Fa","0.5"=>"Poor"
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
    $fixed = str_replace("images/images","images",$path);
    return "/comicsmp/".ltrim($fixed,'/');
}
function calculateDistance($lat1,$lon1,$lat2,$lon2) {
    $lat1=deg2rad($lat1); $lon1=deg2rad($lon1);
    $lat2=deg2rad($lat2); $lon2=deg2rad($lon2);
    $dlat=$lat2-$lat1; $dlon=$lon2-$lon1;
    $a=pow(sin($dlat/2),2)+cos($lat1)*cos($lat2)*pow(sin($dlon/2),2);
    $c=2*asin(sqrt($a));
    return round($c*3956)." mi";
}

require_once '../db_connection.php';

// Fetch match notifications
$sql = <<<SQL
SELECT mn.*, buyer.preferred_payment   AS buyer_payment,
       seller.preferred_payment         AS seller_payment,
       seller.preferred_currency        AS seller_currency,
       seller.preferred_transaction     AS seller_transaction,
       buyer.preferred_transaction      AS buyer_transaction,
       cfs.price, cfs.comic_condition, cfs.graded,
       buyer.city   AS buyer_city,
       buyer.latitude  AS buyer_lat,
       buyer.longitude AS buyer_lng,
       seller.city  AS seller_city,
       seller.latitude  AS seller_lat,
       seller.longitude AS seller_lng,
       mn.cover_image
  FROM match_notifications mn
  LEFT JOIN comics_for_sale cfs
    ON cfs.issue_url = mn.issue_url
   AND cfs.user_id IN(mn.buyer_id,mn.seller_id)
  LEFT JOIN users buyer  ON buyer.id  = mn.buyer_id
  LEFT JOIN users seller ON seller.id = mn.seller_id
 WHERE mn.buyer_id = ? OR mn.seller_id = ?
 ORDER BY mn.match_time DESC
SQL;
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii',$userId,$userId);
$stmt->execute();
$result = $stmt->get_result();

// 1) load both hide/deleted flags
$hidden  = [];
$deleted = [];
$sqlH = "SELECT match_user_id, is_deleted
         FROM hidden_matches
         WHERE user_id = ?";
$stmtH = $conn->prepare($sqlH);
$stmtH->bind_param("i", $userId);
$stmtH->execute();
$resH = $stmtH->get_result();
while ($r = $resH->fetch_assoc()) {
    if ($r['is_deleted']) {
        $deleted[] = (int)$r['match_user_id'];
    } else {
        $hidden[]  = (int)$r['match_user_id'];
    }
}
$stmtH->close();

// 2) group matches, skipping any permanently deleted
$groupedMatches = [];
while ($row = $result->fetch_assoc()) {
    $other = ($row['buyer_id']==$userId)
           ? $row['seller_id']
           : $row['buyer_id'];

    // skip rows marked deleted
    if (in_array($other, $deleted, true)) {
        continue;
    }

    // flag reversible hides
    $row['is_hidden'] = in_array($other, $hidden, true) ? 1 : 0;
    $groupedMatches[$other][] = $row;
}
$stmt->close();

// Fetch user info for grouped keys
$userNamesMap = [];
if (!empty($groupedMatches)) {
    $ids = array_keys($groupedMatches);
    $ph = implode(',',array_fill(0,count($ids),'?'));
    $sqlU = "SELECT id,username,city FROM users WHERE id IN($ph)";
    $stmtU = $conn->prepare($sqlU);
    $stmtU->bind_param(str_repeat('i',count($ids)),...$ids);
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
    <!-- Controls: Sort, Radius, Active/Hidden -->
    <div class="mb-3 d-flex flex-wrap justify-content-end align-items-center">
      <label for="matchSortSelect" class="me-2 mb-0 fw-bold">Sort By:</label>
      <select id="matchSortSelect" class="form-select form-select-sm me-4" style="width:auto;">
        <option value="newest">Newest</option>
        <option value="closest">Closest</option>
        <option value="most">Most Matches</option>
      </select>

      <label class="me-2 mb-0 fw-bold" for="distanceSlider">Max Radius:</label>
      <span id="distanceValue" class="me-2">750</span>mi
      <input type="range" id="distanceSlider" class="form-range me-4" min="0" max="1000" value="750" style="width:150px;">

      <div class="form-check form-check-inline me-2">
        <input class="form-check-input" type="checkbox" id="activeCheckbox" checked>
        <label class="form-check-label" for="activeCheckbox">Active</label>
      </div>
      <div class="form-check form-check-inline">
        <input class="form-check-input" type="checkbox" id="hiddenCheckbox">
        <label class="form-check-label" for="hiddenCheckbox">Hidden</label>
      </div>
    </div>

    <table class="table table-striped" id="matchesTable">
      <thead>
        <tr>
          <th>Matches</th><th>User</th><th>City – Distance</th>
          <th>Type</th><th>Payment</th><th>Exchange</th>
          <th>PM</th><th>Manage</th><th>Expand</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($groupedMatches as $otherId => $group):
          $first    = reset($group);
          $name     = $userNamesMap[$otherId]['username'] ?? "User #{$otherId}";
          $city     = ($first['buyer_id']==$userId)
                      ? ($first['seller_city'] ?? 'N/A')
                      : ($first['buyer_city']  ?? 'N/A');
          $distTxt  = (!empty($first['buyer_lat']) && !empty($first['seller_lat']))
                      ? calculateDistance(
                          $first['buyer_lat'],$first['buyer_lng'],
                          $first['seller_lat'],$first['seller_lng']
                        ) : 'N/A';
          $distNum  = is_numeric(substr($distTxt,0,strpos($distTxt,' ')))
                      ? intval(substr($distTxt,0,strpos($distTxt,' '))) : 9999;
          $buyCount = count(array_filter($group, fn($m)=>$m['buyer_id']==$userId));
          $sellCount= count(array_filter($group, fn($m)=>$m['seller_id']==$userId));
          $typeText = "{$buyCount} Buy / {$sellCount} Sell";

          // Payment & Exchange
          $rawPay = ($first['buyer_id']==$userId) ? $first['seller_payment']    : $first['buyer_payment'];
          $paymentList = $rawPay ? array_map('trim',explode(',',$rawPay)) : ['-'];
          $payment     = implode(', ',$paymentList);

          $rawEx = ($first['buyer_id']==$userId) ? $first['seller_transaction'] : $first['buyer_transaction'];
          $exchangeList = $rawEx ? array_map('trim',explode(',',$rawEx)) : ['-'];
          $exchange     = implode(', ',$exchangeList);
      ?>
        <tr class="main-row"
    data-user-id="<?= $otherId ?>"
    data-distance="<?= $distNum ?>"
    data-match-time="<?= strtotime($first['match_time']) ?>"
    data-match-count="<?= count($group) ?>"
    data-hidden="<?= $first['is_hidden'] ?>"
    <?= $first['is_hidden'] ? 'style="display:none;"' : '' ?>>

          <td><?= count($group) ?></td>
          <td><strong><?= htmlspecialchars($name) ?></strong></td>
          <td><?= htmlspecialchars($city) ?> – <?= htmlspecialchars($distTxt) ?></td>
          <td><?= htmlspecialchars($typeText) ?></td>
          <td><?= htmlspecialchars($payment) ?></td>
          <td><?= htmlspecialchars($exchange) ?></td>
          <td>
            <button class="btn btn-sm btn-outline-secondary pm-btn" data-user-id="<?= $otherId ?>">
              <i class="bi bi-envelope"></i>
            </button>
          </td>
          <td>
            <button class="btn btn-warning btn-sm hide-btn" data-other-user-id="<?= $otherId ?>">
              <?= $first['is_hidden'] ? 'Unhide' : 'Hide' ?>
            </button>
            <button class="btn btn-danger btn-sm delete-match-btn" data-match-user-id="<?= $otherId ?>">
              Delete
            </button>
          </td>
          <td>
            <button class="btn btn-sm btn-info expand-btn" data-user-id="<?= $otherId ?>">▶</button>
          </td>
        </tr>
        <tr class="detail-row" id="detail-<?= $otherId ?>" style="display:none;">
          <td colspan="9">
            <table class="table table-bordered table-sm mb-0">
              <thead class="table-light">
                <tr>
                  <th>Cover</th><th>Title</th><th>Year</th>
                  <th>Issue</th><th>Condition</th><th>Price</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($group as $m):
                  $k     = getScoreKey($m['comic_condition'] ?? '');
                  $g     = $gradeMapping[$k] ?? null;
                  $cond  = $g ? "$k ($g)" : ($m['comic_condition'] ?? 'N/A');
                  $price = isset($m['price']) ? number_format($m['price'],2) : 'N/A';
              ?>
                <tr>
                  <td>
                    <img
                      class="match-cover-img"
                      src="<?= htmlspecialchars(fixImagePath($m['cover_image'])) ?>"
                      data-img-src="<?= htmlspecialchars(fixImagePath($m['cover_image'])) ?>"
                      data-comic-title="<?= htmlspecialchars($m['comic_title'] ?? '') ?>"
                      data-years="<?= htmlspecialchars($m['years'] ?? '') ?>"
                      data-issue-number="<?= htmlspecialchars($m['issue_number'] ?? '') ?>"
                      data-variant="<?= htmlspecialchars($m['variant'] ?? '') ?>"
                      data-date="<?= htmlspecialchars($m['date'] ?? '') ?>"
                      data-upc="<?= htmlspecialchars($m['upc'] ?? '') ?>"
                      data-condition="<?= htmlspecialchars($cond) ?>"
                      data-graded="<?= ($m['graded']=='1') ? 'Yes' : 'No' ?>"
                      data-price="<?= htmlspecialchars('$'.$price) ?>"
                      data-currency="<?= htmlspecialchars($m['seller_currency'] ?? '') ?>"

                      style="width:40px;height:60px;object-fit:cover;cursor:pointer;"
                    />
                  </td>
                  <td><?= htmlspecialchars($m['comic_title']) ?></td>
                  <td><?= htmlspecialchars($m['years']) ?></td>
                  <td><?= htmlspecialchars($m['issue_number']) ?></td>
                  <td><?= htmlspecialchars($cond) ?></td>
                  <td><?= '$'.htmlspecialchars($price).' '.htmlspecialchars($m['seller_currency']??'') ?></td>
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

<!-- Client-side filtering & sorting must remain below -->
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
  function filterMatches() {
    const maxD   = parseFloat($('#distanceSlider').val()) || 0;
    const showA  = $('#activeCheckbox').is(':checked');
    const showH  = $('#hiddenCheckbox').is(':checked');

    $('#matchesContainer .main-row').each(function() {
      const $row     = $(this);
      const dist     = +$row.data('distance') || 0;
      const isHidden = $row.data('hidden') === 1;
      const ok       = (dist <= maxD) && ((isHidden && showH) || (!isHidden && showA));
      $row.toggle(ok);
      $('#detail-'+$row.data('user-id')).toggle(ok && $('#detail-'+$row.data('user-id')).is(':visible'));
    });
  }
  function sortMatches() {
    const mode = $('#matchSortSelect').val(),
          $tb  = $('#matchesTable tbody'),
          rows = $tb.find('.main-row').get();

    rows.sort((a,b) => {
      const A = $(a), B = $(b);
      if (mode==='newest')  return B.data('match-time')  - A.data('match-time');
      if (mode==='closest') return A.data('distance')    - B.data('distance');
      if (mode==='most')    return B.data('match-count') - A.data('match-count');
      return 0;
    });
    rows.forEach(r => {
      const id = $(r).data('user-id');
      $tb.append(r).append($('#detail-'+id));
    });
  }

  // initialize on page load
  sortMatches();
  filterMatches();

  // wire controls
  $(document)
    .on('change', '#matchSortSelect', sortMatches)
    .on('input change', '#distanceSlider', function() {
      $('#distanceValue').text(this.value);
      filterMatches();
    })
    .on('change', '#activeCheckbox, #hiddenCheckbox', filterMatches);
</script>

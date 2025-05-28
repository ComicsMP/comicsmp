<?php
session_start();
require_once 'setup.php';

// Optionally enable error reporting during development
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<p>You must be logged in to view this content.</p>";
    exit;
}
$userId = $_SESSION['user_id'];

// ---------------------------------------------
// Get user's preferred currency
// ---------------------------------------------
$sql = "SELECT preferred_currency FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$preferredCurrency = $stmt->get_result()->fetch_assoc()['preferred_currency'];
$stmt->close();

// ---------------------------------------------
// 1) Wanted items stats
// ---------------------------------------------
foreach (['Total','24','Week','Month'] as $period) {
    $sql = match($period) {
        'Total' => "SELECT COUNT(*) AS cnt FROM wanted_items WHERE user_id=?",
        '24'    => "SELECT COUNT(*) AS cnt FROM wanted_items WHERE user_id=? AND `Timestamp`>=NOW()-INTERVAL 1 DAY",
        'Week'  => "SELECT COUNT(*) AS cnt FROM wanted_items WHERE user_id=? AND `Timestamp`>=NOW()-INTERVAL 7 DAY",
        'Month' => "SELECT COUNT(*) AS cnt FROM wanted_items WHERE user_id=? AND `Timestamp`>=NOW()-INTERVAL 30 DAY",
    };
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    ${"wanted$period"} = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

// ---------------------------------------------
// 2) Comics for sale stats
// ---------------------------------------------
foreach (['Total','24','Week','Month'] as $period) {
    $sql = match($period) {
        'Total' => "SELECT COUNT(*) AS cnt FROM comics_for_sale WHERE user_id=?",
        '24'    => "SELECT COUNT(*) AS cnt FROM comics_for_sale WHERE user_id=? AND created_at>=NOW()-INTERVAL 1 DAY",
        'Week'  => "SELECT COUNT(*) AS cnt FROM comics_for_sale WHERE user_id=? AND created_at>=NOW()-INTERVAL 7 DAY",
        'Month' => "SELECT COUNT(*) AS cnt FROM comics_for_sale WHERE user_id=? AND created_at>=NOW()-INTERVAL 30 DAY",
    };
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    ${"sale$period"} = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

// ---------------------------------------------
// 3) Matches stats
// ---------------------------------------------
foreach (['Total','24','Week','Month'] as $period) {
    $sql = match($period) {
        'Total' => "SELECT COUNT(*) AS cnt FROM match_notifications WHERE buyer_id=? OR seller_id=?",
        '24'    => "SELECT COUNT(*) AS cnt FROM match_notifications WHERE (buyer_id=? OR seller_id=?) AND match_time>=NOW()-INTERVAL 1 DAY",
        'Week'  => "SELECT COUNT(*) AS cnt FROM match_notifications WHERE (buyer_id=? OR seller_id=?) AND match_time>=NOW()-INTERVAL 7 DAY",
        'Month' => "SELECT COUNT(*) AS cnt FROM match_notifications WHERE (buyer_id=? OR seller_id=?) AND match_time>=NOW()-INTERVAL 30 DAY",
    };
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    ${"matches$period"} = $stmt->get_result()->fetch_assoc()['cnt'];
    $stmt->close();
}

// ---------------------------------------------
// 4) My Recent Wanted Comics
// ---------------------------------------------
$sql = "
  SELECT c.Image_Path
    FROM wanted_items w
    LEFT JOIN comics c ON w.Issue_URL=c.Issue_URL
   WHERE w.user_id=?
   ORDER BY w.ID DESC
   LIMIT 4
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$myRecentWanted = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------------------------------------------
// 5) My Recent Comics for Sale
// ---------------------------------------------
$sql = "SELECT image_path FROM comics_for_sale WHERE user_id=? ORDER BY id DESC LIMIT 4";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $userId);
$stmt->execute();
$myRecentSales = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------------------------------------------
// 6) My Recent Matches
// ---------------------------------------------
$sql = "
  SELECT c.Image_Path
    FROM match_notifications m
    LEFT JOIN comics c ON m.comic_title=c.comic_title AND m.issue_number=c.issue_number
   WHERE m.buyer_id=? OR m.seller_id=?
   ORDER BY m.match_time DESC
   LIMIT 4
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$myRecentMatches = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------------------------------------------
// 7) Latest 15 Comics for Sale (global)
// ---------------------------------------------
$sql = "
  SELECT c.comic_title, c.issue_number, c.price, c.image_path, c.comic_condition, u.preferred_currency
    FROM comics_for_sale AS c
    JOIN users AS u ON c.user_id = u.id
   ORDER BY c.created_at DESC
   LIMIT 15
";
$recent15Sales = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// ---------------------------------------------
// 8) Top 15 Most Wanted (global)
// ---------------------------------------------
$sql = "
  SELECT 
    w.Comic_Title,
    w.Issue_Number,
    c.Years,
    c.Image_Path
  FROM wanted_items w
  JOIN comics c ON w.Issue_URL = c.Issue_URL
  GROUP BY w.Comic_Title, w.Issue_Number, c.Years, c.Image_Path
  ORDER BY COUNT(*) DESC
  LIMIT 15
";
$topWanted = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
// ---------------------------------------------
// 9) Top 15 Most For Sale (global)
// ---------------------------------------------
$sql = "
  SELECT 
    s.comic_title   AS Comic_Title,
    s.issue_number  AS Issue_Number,
    c.Years,
    c.Image_Path
  FROM comics_for_sale AS s
  JOIN comics AS c 
    ON s.Issue_URL = c.Issue_URL
  GROUP BY s.comic_title, s.issue_number, c.Years, c.Image_Path
  ORDER BY COUNT(*) DESC
  LIMIT 15
";
$topForSale = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

// ---------------------------------------------
// 10) Hottest Comics (This Week vs Last Week Ranks - Unique Rankings)
// ---------------------------------------------
$sql = "
WITH this_week AS (
  SELECT 
    w.Issue_URL,
    COUNT(*) - COALESCE(s.count, 0) AS score,
    ROW_NUMBER() OVER (ORDER BY COUNT(*) - COALESCE(s.count, 0) DESC) AS this_rank
  FROM wanted_items w
  LEFT JOIN (
    SELECT issue_url, COUNT(*) AS count
    FROM comics_for_sale
    WHERE created_at >= NOW() - INTERVAL 7 DAY
    GROUP BY issue_url
  ) s ON w.Issue_URL = s.issue_url
  WHERE w.Timestamp >= NOW() - INTERVAL 7 DAY
  GROUP BY w.Issue_URL
),
last_week AS (
  SELECT 
    w.Issue_URL,
    COUNT(*) - COALESCE(s.count, 0) AS score,
    ROW_NUMBER() OVER (ORDER BY COUNT(*) - COALESCE(s.count, 0) DESC) AS last_rank
  FROM wanted_items w
  LEFT JOIN (
    SELECT issue_url, COUNT(*) AS count
    FROM comics_for_sale
    WHERE created_at BETWEEN NOW() - INTERVAL 14 DAY AND NOW() - INTERVAL 7 DAY
    GROUP BY issue_url
  ) s ON w.Issue_URL = s.issue_url
  WHERE w.Timestamp BETWEEN NOW() - INTERVAL 14 DAY AND NOW() - INTERVAL 7 DAY
  GROUP BY w.Issue_URL
)
SELECT 
  c.Comic_Title,
  c.Issue_Number,
  c.Years,
  c.Image_Path,
  t.this_rank,
  COALESCE(l.last_rank, NULL) AS last_rank
FROM this_week t
LEFT JOIN last_week l ON t.Issue_URL = l.Issue_URL
JOIN comics c ON c.Issue_URL = t.Issue_URL
ORDER BY t.this_rank
LIMIT 15
";
$hottest = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);


// Grade mapping
$gradeMapping = [
  "10.0"=>"Gem Mint","9.9"=>"Mint","9.8"=>"NM/M","9.6"=>"NM+","9.4"=>"NM",
  "9.2"=>"NM-","9.0"=>"VF/NM","8.5"=>"VF+","8.0"=>"VF","7.5"=>"VF-",
  "7.0"=>"FN/VF","6.5"=>"FN+","6.0"=>"FN","5.5"=>"FN-","5.0"=>"VG/FN",
  "4.5"=>"VG+","4.0"=>"VG","3.5"=>"VG-","3.0"=>"G/VG","2.5"=>"G",
  "2.0"=>"G","1.8"=>"G-","1.5"=>"Fa/G","1.0"=>"Fa","0.5"=>"Poor"
];
?>
<!-- BEGIN: Dashboard Full-Width Layout -->
<div class="container-fluid py-4">
  <div class="row gx-4 gy-4">

    <!-- Left Column (unchanged) -->
    <div class="col-lg-6">
      <h2 class="mb-4">Dashboard Overview</h2>
      <div class="row mb-4">
        <!-- Wanted -->
        <div class="col-md-4 mb-3">
          <div class="card text-center shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Wanted Comics</h5>
              <p class="display-4 mb-0"><?= $wantedTotal ?></p>
              <small class="text-muted">24h: <?= $wanted24 ?> | Week: <?= $wantedWeek ?> | Month: <?= $wantedMonth ?></small>
            </div>
          </div>
        </div>
        <!-- For Sale -->
        <div class="col-md-4 mb-3">
          <div class="card text-center shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Comics for Sale</h5>
              <p class="display-4 mb-0"><?= $saleTotal ?></p>
              <small class="text-muted">24h: <?= $sale24 ?> | Week: <?= $saleWeek ?> | Month: <?= $saleMonth ?></small>
            </div>
          </div>
        </div>
        <!-- Matches -->
        <div class="col-md-4 mb-3">
          <div class="card text-center shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Matches</h5>
              <p class="display-4 mb-0"><?= $matchesTotal ?></p>
              <small class="text-muted">24h: <?= $matches24 ?> | Week: <?= $matchesWeek ?> | Month: <?= $matchesMonth ?></small>
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="mt-4 mb-5">
        <h3 class="mb-3">Quick Actions</h3>
        <div class="d-flex flex-wrap gap-3">
          <a href="#wanted" class="btn btn-outline-primary">Manage Wanted List</a>
          <a href="#selling" class="btn btn-outline-success">Manage Listings</a>
          <a href="#matches" class="btn btn-outline-info">View Matches</a>
        </div>
      </div>

      <!-- My Recent Wanted Comics -->
      <h3 class="mb-3">My Recent Wanted Comics</h3>
      <div class="row">
        <?php if ($myRecentWanted): foreach ($myRecentWanted as $w): ?>
          <div class="col-sm-6 col-md-3 mb-3">
            <div class="card shadow-sm">
              <img src="<?= htmlspecialchars(getFinalImagePathV2($w['Image_Path'])) ?>" class="card-img-top" alt="">
            </div>
          </div>
        <?php endforeach; else: ?>
          <p class="text-muted">No recent wanted comics available.</p>
        <?php endif; ?>
      </div>

      <!-- My Recent Comics for Sale -->
      <h3 class="mb-3 mt-5">My Recent Comics for Sale</h3>
      <div class="row">
        <?php if ($myRecentSales): foreach ($myRecentSales as $s): ?>
          <div class="col-sm-6 col-md-3 mb-3">
            <div class="card shadow-sm">
              <img src="<?= htmlspecialchars(getFinalImagePathV2($s['image_path'])) ?>" class="card-img-top" alt="">
            </div>
          </div>
        <?php endforeach; else: ?>
          <p class="text-muted">No recent sales available.</p>
        <?php endif; ?>
      </div>

      <!-- My Recent Matches -->
      <h3 class="mb-3 mt-5">My Recent Comic Matches</h3>
      <div class="row">
        <?php if ($myRecentMatches): foreach ($myRecentMatches as $m): ?>
          <div class="col-sm-6 col-md-3 mb-3">
            <div class="card shadow-sm">
              <img src="<?= htmlspecialchars(getFinalImagePathV2($m['Image_Path'])) ?>" class="card-img-top" alt="">
            </div>
          </div>
        <?php endforeach; else: ?>
          <p class="text-muted">No recent comic matches available.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Column with Tabs -->
    <div class="col-lg-6">
      <ul class="nav nav-tabs mb-3" id="rightTab" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#latest" type="button">Latest 15</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#globalWanted" type="button">Top Wanted</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#globalForSale" type="button">Top For Sale</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#hottest" type="button">Hottest</button>
        </li>
      </ul>
      <div class="tab-content">
        <!-- Latest 15 -->
        <div class="tab-pane fade show active" id="latest">
          <div class="table-responsive mb-5">
            <table class="table table-striped table-bordered">
              <thead class="table-dark">
                <tr>
                  <th style="width:60px;">Image</th>
                  <th>Comic Title / Issue / Condition</th>
                  <th style="width:80px;">Price</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recent15Sales as $s):
                  $img = getFinalImagePathV2($s['image_path']);
                  $g = floatval($s['comic_condition']);
                  $disp = $g==floor($g)?number_format($g,0):$s['comic_condition'];
                  $abbr = $gradeMapping[number_format(round($g,1),1,'.','')] ?? '';
                ?>
                <tr>
                  <td><?= $img?'<img src="'.htmlspecialchars($img).'" style="width:50px;">':'No Image'?></td>
                  <td>
                    <?= htmlspecialchars($s['comic_title']) ?><br>
                    <small>Issue: <?= htmlspecialchars($s['issue_number']) ?></small><br>
                    <small class="text-muted"><?= $disp ?><?= $abbr?" ($abbr)":"" ?></small>
                  </td>
                  <td>$<?= number_format($s['price'],2) ?> <?= htmlspecialchars($s['preferred_currency']) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Top Wanted -->
        <div class="tab-pane fade" id="globalWanted">
          <div class="table-responsive mb-5">
            <table class="table table-striped table-bordered">
              <thead class="table-dark">
                <tr>
                  <th style="width:60px;">Image</th>
                  <th>Comic Title / Issue / Year</th>
                  <th style="width:80px;">Rank</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($topWanted as $i => $w): ?>
  <tr>
    <td>
      <img src="<?= htmlspecialchars(getFinalImagePathV2($w['Image_Path'])) ?>"
           style="width:50px;">
    </td>
    <td>
      <?= htmlspecialchars($w['Comic_Title']) ?><br>
      <small>Issue: <?= htmlspecialchars($w['Issue_Number']) ?></small><br>
      <?php if (!empty($w['Years'])): ?>
        <small>Year: <?= htmlspecialchars($w['Years']) ?></small>
      <?php endif; ?>
    </td>
    <td>#<?= $i + 1 ?></td>
  </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Top For Sale -->
        <div class="tab-pane fade" id="globalForSale">
          <div class="table-responsive mb-5">
            <table class="table table-striped table-bordered">
              <thead class="table-dark">
                <tr>
                  <th style="width:60px;">Image</th>
                  <th>Comic Title / Issue / Year</th>
                  <th style="width:80px;">Rank</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($topForSale as $i => $f): ?>
  <tr>
    <td>
      <img src="<?= htmlspecialchars(getFinalImagePathV2($f['Image_Path'])) ?>"
           style="width:50px;">
    </td>
    <td>
      <?= htmlspecialchars($f['Comic_Title']) ?><br>
      <small>Issue: <?= htmlspecialchars($f['Issue_Number']) ?></small><br>
      <?php if (!empty($f['Years'])): ?>
        <small>Year: <?= htmlspecialchars($f['Years']) ?></small>
      <?php endif; ?>
    </td>
    <td>#<?= $i + 1 ?></td>
  </tr>
<?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Hottest -->
        <div class="tab-pane fade" id="hottest">
          <div class="table-responsive mb-5">
            <table class="table table-striped table-bordered">
              <thead class="table-dark">
                <tr>
                  <th style="width:60px;">Image</th>
                  <th>Comic Title / Issue / Year</th>
                  <th style="width:80px;">Rank</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($hottest as $i => $h): ?>
    <tr>
      <td>
        <img src="<?= htmlspecialchars(getFinalImagePathV2($h['Image_Path'])) ?>" style="width:50px;">
      </td>
      <td>
        <?= htmlspecialchars($h['Comic_Title']) ?><br>
        <small>Issue: <?= htmlspecialchars($h['Issue_Number']) ?></small><br>
        <small>Year: <?= htmlspecialchars($h['Years']) ?></small>
      </td>
      <td class="text-start">#<?= $i + 1 ?></td>
    </tr>
  <?php endforeach; ?>




              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

  </div>
</div>
<!-- END: Dashboard Full-Width Layout -->

<script>
  // Tab-bridge script
  $(document).on("click", ".btn-tab-bridge", function(e) {
    e.preventDefault();
    var target = $(this).attr("href");
    $('.nav-tabs button[data-bs-target="'+target+'"]').tab('show');
  });
</script>

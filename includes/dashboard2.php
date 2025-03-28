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
// 1) Get WANTED ITEMS stats from "wanted_items"
// ---------------------------------------------
$sqlWantedTotal = "SELECT COUNT(*) AS total FROM wanted_items WHERE user_id = ?";
$sqlWanted24    = "SELECT COUNT(*) AS count24 FROM wanted_items 
                   WHERE user_id = ? AND `Timestamp` >= NOW() - INTERVAL 1 DAY";
$sqlWantedWeek  = "SELECT COUNT(*) AS countWeek FROM wanted_items 
                   WHERE user_id = ? AND `Timestamp` >= NOW() - INTERVAL 7 DAY";
$sqlWantedMonth = "SELECT COUNT(*) AS countMonth FROM wanted_items 
                   WHERE user_id = ? AND `Timestamp` >= NOW() - INTERVAL 30 DAY";

$stmt = $conn->prepare($sqlWantedTotal);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$wantedTotal = $row['total'];

$stmt = $conn->prepare($sqlWanted24);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$wanted24 = $row['count24'];

$stmt = $conn->prepare($sqlWantedWeek);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$wantedWeek = $row['countWeek'];

$stmt = $conn->prepare($sqlWantedMonth);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$wantedMonth = $row['countMonth'];

// ---------------------------------------------
// 2) Get COMICS FOR SALE stats from "comics_for_sale"
// ---------------------------------------------
$sqlSaleTotal = "SELECT COUNT(*) AS total FROM comics_for_sale WHERE user_id = ?";
$sqlSale24    = "SELECT COUNT(*) AS count24 FROM comics_for_sale 
                 WHERE user_id = ? AND created_at >= NOW() - INTERVAL 1 DAY";
$sqlSaleWeek  = "SELECT COUNT(*) AS countWeek FROM comics_for_sale 
                 WHERE user_id = ? AND created_at >= NOW() - INTERVAL 7 DAY";
$sqlSaleMonth = "SELECT COUNT(*) AS countMonth FROM comics_for_sale 
                 WHERE user_id = ? AND created_at >= NOW() - INTERVAL 30 DAY";

$stmt = $conn->prepare($sqlSaleTotal);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$saleTotal = $row['total'];

$stmt = $conn->prepare($sqlSale24);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$sale24 = $row['count24'];

$stmt = $conn->prepare($sqlSaleWeek);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$saleWeek = $row['countWeek'];

$stmt = $conn->prepare($sqlSaleMonth);
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$saleMonth = $row['countMonth'];

// ---------------------------------------------
// 3) Get MATCHES stats from "match_notifications"
// ---------------------------------------------
$sqlMatchesTotal = "SELECT COUNT(*) AS total FROM match_notifications 
                    WHERE buyer_id = ? OR seller_id = ?";
$sqlMatches24    = "SELECT COUNT(*) AS count24 FROM match_notifications 
                    WHERE (buyer_id = ? OR seller_id = ?) 
                      AND match_time >= NOW() - INTERVAL 1 DAY";
$sqlMatchesWeek  = "SELECT COUNT(*) AS countWeek FROM match_notifications 
                    WHERE (buyer_id = ? OR seller_id = ?) 
                      AND match_time >= NOW() - INTERVAL 7 DAY";
$sqlMatchesMonth = "SELECT COUNT(*) AS countMonth FROM match_notifications 
                    WHERE (buyer_id = ? OR seller_id = ?) 
                      AND match_time >= NOW() - INTERVAL 30 DAY";

$stmt = $conn->prepare($sqlMatchesTotal);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$matchesTotal = $row['total'];

$stmt = $conn->prepare($sqlMatches24);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$matches24 = $row['count24'];

$stmt = $conn->prepare($sqlMatchesWeek);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$matchesWeek = $row['countWeek'];

$stmt = $conn->prepare($sqlMatchesMonth);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$matchesMonth = $row['countMonth'];

$stmt->close();

// ---------------------------------------------
// 4) Get Latest Comics Released from "wanted_items"
//    (Only show those with an actual image)
// ---------------------------------------------
$sqlLatestComics = "SELECT Comic_Title AS comic_title, Issue_Number AS issue_number, Image_Path AS image_path, `Timestamp` 
                    FROM wanted_items 
                    WHERE user_id = ? 
                      AND Image_Path <> 'images/default.jpg'
                    ORDER BY `Timestamp` DESC 
                    LIMIT 4";
$stmt = $conn->prepare($sqlLatestComics);
$stmt->bind_param("i", $userId);
$stmt->execute();
$resultLatest = $stmt->get_result();
$latestComics = [];
while ($row = $resultLatest->fetch_assoc()) {
    $latestComics[] = $row;
}
$stmt->close();

// ---------------------------------------------
// NEW: Get My Recent Wanted Comics using Issue_URL join
//    Match wanted_items.Issue_URL with comics.Issue_URL and use the comics.Image_Path
// ---------------------------------------------
$sqlMyRecentWanted = "SELECT w.Comic_Title, w.Issue_Number, w.Issue_URL, c.Image_Path 
                      FROM wanted_items AS w 
                      LEFT JOIN comics AS c 
                        ON w.Issue_URL = c.Issue_URL 
                      WHERE w.user_id = ? 
                      ORDER BY w.ID DESC 
                      LIMIT 4";
$stmt = $conn->prepare($sqlMyRecentWanted);
$stmt->bind_param("i", $userId);
$stmt->execute();
$resultMyWanted = $stmt->get_result();
$myRecentWanted = [];
while ($row = $resultMyWanted->fetch_assoc()) {
    $myRecentWanted[] = $row;
}
$stmt->close();

// ---------------------------------------------
// 5) Get My Recent Comics for Sale from "comics_for_sale"
//    (Only select the image_path for the logged-in user)
// ---------------------------------------------
$sqlMyRecentSales = "SELECT image_path FROM comics_for_sale WHERE user_id = ? ORDER BY id DESC LIMIT 4";
$stmt = $conn->prepare($sqlMyRecentSales);
$stmt->bind_param("i", $userId);
$stmt->execute();
$resultMySales = $stmt->get_result();
$myRecentSales = [];
while ($row = $resultMySales->fetch_assoc()) {
    $myRecentSales[] = $row;
}
$stmt->close();

// ---------------------------------------------
// 6) Get the most recent 20 comics listed for sale (all users)
// ---------------------------------------------
$sqlRecent20 = "SELECT comic_title, issue_number, price, image_path, created_at 
                FROM comics_for_sale 
                ORDER BY created_at DESC 
                LIMIT 20";
$stmt = $conn->prepare($sqlRecent20);
$stmt->execute();
$resultRecent20 = $stmt->get_result();
$recent20Sales = [];
while ($row = $resultRecent20->fetch_assoc()) {
    $recent20Sales[] = $row;
}
$stmt->close();

// ---------------------------------------------
// 7) Get My Recent Comic Matches from "match_notifications"
//    Join with the comics table to get the cover image, ordering by match_time
// ---------------------------------------------
$sqlMyRecentMatches = "SELECT m.comic_title, m.issue_number, m.match_time, c.Image_Path 
                       FROM match_notifications m 
                       LEFT JOIN comics c 
                         ON m.comic_title = c.comic_title AND m.issue_number = c.issue_number 
                       WHERE m.buyer_id = ? OR m.seller_id = ? 
                       ORDER BY m.match_time DESC 
                       LIMIT 4";
$stmt = $conn->prepare($sqlMyRecentMatches);
$stmt->bind_param("ii", $userId, $userId);
$stmt->execute();
$resultMyMatches = $stmt->get_result();
$myRecentMatches = [];
while ($row = $resultMyMatches->fetch_assoc()) {
    $myRecentMatches[] = $row;
}
$stmt->close();
?>

<!-- BEGIN: Dashboard Full-Width Layout -->
<div class="container-fluid py-4">
  <div class="row">
    <!-- Left Column: Dashboard Overview and Other Sections -->
    <div class="col-lg-6">
      <h2 class="mb-4">Dashboard Overview</h2>
      <div class="row mb-4">
        <!-- Wanted Items Card -->
        <div class="col-md-4 mb-3">
          <div class="card text-center shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Wanted Comics</h5>
              <p class="display-4 mb-0"><?php echo $wantedTotal; ?></p>
              <small class="text-muted">
                24hrs: <?php echo $wanted24; ?> | Week: <?php echo $wantedWeek; ?> | Month: <?php echo $wantedMonth; ?>
              </small>
            </div>
          </div>
        </div>
        
        <!-- Comics for Sale Card -->
        <div class="col-md-4 mb-3">
          <div class="card text-center shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Comics for Sale</h5>
              <p class="display-4 mb-0"><?php echo $saleTotal; ?></p>
              <small class="text-muted">
                24hrs: <?php echo $sale24; ?> | Week: <?php echo $saleWeek; ?> | Month: <?php echo $saleMonth; ?>
              </small>
            </div>
          </div>
        </div>
        
        <!-- Matches Card -->
        <div class="col-md-4 mb-3">
          <div class="card text-center shadow-sm">
            <div class="card-body">
              <h5 class="card-title">Matches</h5>
              <p class="display-4 mb-0"><?php echo $matchesTotal; ?></p>
              <small class="text-muted">
                24hrs: <?php echo $matches24; ?> | Week: <?php echo $matchesWeek; ?> | Month: <?php echo $matchesMonth; ?>
              </small>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Quick Actions Section -->
      <div class="mt-4">
        <h3 class="mb-3">Quick Actions</h3>
        <div class="d-flex flex-wrap gap-3">
          <a href="#wanted" class="btn-tab-bridge btn btn-outline-primary">Manage Wanted List</a>
          <a href="#selling" class="btn-tab-bridge btn btn-outline-success">Manage Listings</a>
          <a href="#matches" class="btn-tab-bridge btn btn-outline-info">View Matches</a>
        </div>
      </div>
      
      <!-- NEW: My Recent Wanted Comics Section -->
      <h3 class="mb-3 mt-5">My Recent Wanted Comics</h3>
      <div class="row">
        <?php if (!empty($myRecentWanted)): ?>
          <?php foreach ($myRecentWanted as $wanted):
                  $rawPathWanted = $wanted['Image_Path'];
                  $finalImageWanted = getFinalImagePathV2($rawPathWanted);
                  if ($finalImageWanted === '/comicsmp/images/comicsmp/placeholder.jpg') {
                      echo '<!-- DEBUG: Wanted Comic raw image_path: ' . htmlspecialchars($rawPathWanted) . ' | Final: ' . htmlspecialchars($finalImageWanted) . ' -->';
                  }
          ?>
            <div class="col-md-3 col-sm-6 mb-3">
              <div class="card shadow-sm">
                <img src="<?php echo htmlspecialchars($finalImageWanted); ?>"
                     class="card-img-top"
                     alt="<?php echo htmlspecialchars($wanted['Comic_Title']); ?>">
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted">No recent wanted comics available.</p>
        <?php endif; ?>
      </div>
      
      <!-- My Recent Comics for Sale Section -->
      <h3 class="mb-3 mt-5">My Recent Comics for Sale</h3>
      <div class="row">
        <?php if (!empty($myRecentSales)): ?>
          <?php foreach ($myRecentSales as $sale):
                  $rawPathSale = $sale['image_path'];
                  $finalImageSale = getFinalImagePathV2($rawPathSale);
                  if ($finalImageSale === '/comicsmp/images/comicsmp/placeholder.jpg') {
                      echo '<!-- DEBUG: Sale Comic raw image_path: ' . htmlspecialchars($rawPathSale) . ' | Final: ' . htmlspecialchars($finalImageSale) . ' -->';
                  }
          ?>
            <div class="col-md-3 col-sm-6 mb-3">
              <div class="card shadow-sm">
                <img src="<?php echo htmlspecialchars($finalImageSale); ?>"
                     class="card-img-top"
                     alt="Comic Image">
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted">No recent sales available.</p>
        <?php endif; ?>
      </div>
      
      <!-- NEW: My Recent Comic Matches Section -->
      <h3 class="mb-3 mt-5">My Recent Comic Matches</h3>
      <div class="row">
        <?php if (!empty($myRecentMatches)): ?>
          <?php foreach ($myRecentMatches as $match):
                  $rawPathMatch = $match['Image_Path'];
                  $finalImageMatch = getFinalImagePathV2($rawPathMatch);
          ?>
            <div class="col-md-3 col-sm-6 mb-3">
              <div class="card shadow-sm">
                <img src="<?php echo htmlspecialchars($finalImageMatch); ?>"
                     class="card-img-top"
                     alt="<?php echo htmlspecialchars($match['comic_title']); ?>">
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted">No recent comic matches available.</p>
        <?php endif; ?>
      </div>
      
      <!-- Latest Comics Released Section from wanted_items -->
      <h3 class="mb-3 mt-5">Latest Comics Released (from Wanted Items)</h3>
      <div class="row">
        <?php if (!empty($latestComics)): ?>
          <?php foreach ($latestComics as $comic):
                  $rawPath = $comic['image_path'];
                  $finalImage = getFinalImagePathV2($rawPath);
                  if ($finalImage === '/comicsmp/images/comicsmp/placeholder.jpg') {
                      echo '<!-- DEBUG: Wanted Comic "' . htmlspecialchars($comic['comic_title']) .
                           '" raw image_path: ' . htmlspecialchars($rawPath) . ' | Final: ' . htmlspecialchars($finalImage) . ' -->';
                  }
          ?>
            <div class="col-md-3 col-sm-6 mb-3">
              <div class="card shadow-sm">
                <img src="<?php echo htmlspecialchars($finalImage); ?>"
                     class="card-img-top"
                     alt="<?php echo htmlspecialchars($comic['comic_title']); ?>">
                <div class="card-body">
                  <h5 class="card-title"><?php echo htmlspecialchars($comic['comic_title']); ?></h5>
                  <p class="card-text">Issue <?php echo htmlspecialchars($comic['issue_number']); ?></p>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <p class="text-muted">No recent comics available from Wanted Items.</p>
        <?php endif; ?>
      </div>
      
    </div>
    
    <!-- Right Column: Full Length Table for 20 Recent Comics Listed for Sale -->
    <div class="col-lg-6">
      <h2 class="mb-4">Latest 20 Comics for Sale</h2>
      <div class="table-responsive">
        <table class="table table-striped table-bordered">
          <thead class="thead-dark">
            <tr>
              <th>Image</th>
              <th>Comic Title</th>
              <th>Issue Number</th>
              <th>Price</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($recent20Sales)): ?>
              <?php foreach ($recent20Sales as $sale):
                      $rawPath20 = $sale['image_path'];
                      $finalImage20 = getFinalImagePathV2($rawPath20);
                      if ($finalImage20 === '/comicsmp/images/comicsmp/placeholder.jpg') {
                          echo '<!-- DEBUG: Recent20 Comic "' . htmlspecialchars($sale['comic_title']) .
                               '" raw image_path: ' . htmlspecialchars($rawPath20) . ' | Final: ' . htmlspecialchars($finalImage20) . ' -->';
                      }
              ?>
                <tr>
                  <td>
                    <?php
                      if (empty($finalImage20)) {
                          echo "No Image";
                      } else {
                          echo '<img src="' . htmlspecialchars($finalImage20) . '" alt="' . htmlspecialchars($sale['comic_title']) . '" style="width:50px;height:auto;">';
                      }
                    ?>
                  </td>
                  <td><?php echo htmlspecialchars($sale['comic_title']); ?></td>
                  <td><?php echo htmlspecialchars($sale['issue_number']); ?></td>
                  <td>$<?php echo number_format($sale['price'], 2); ?></td>
                </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="4" class="text-center">No comics listed for sale.</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    
  </div> <!-- end row -->
</div>
<!-- END: Dashboard Full-Width Layout -->

<!-- SIMPLE "TAB BRIDGE" SCRIPT -->
<script>
  $(document).on("click", ".btn-tab-bridge", function(e) {
    e.preventDefault();
    var targetTab = $(this).attr("href");
    var $parentLink = $('a.nav-link[href="' + targetTab + '"]');
    if ($parentLink.length) {
      $parentLink.tab('show');
    } else {
      console.warn("Tab link not found for " + targetTab);
    }
  });
</script>

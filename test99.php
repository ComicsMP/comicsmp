<?php
// =====================================
// INITIAL SETUP & QUERIES
// =====================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connection.php';

$user_id = $_SESSION['user_id'] ?? 0;

// --- Helper: Normalize image paths ---
function getFinalImagePath($rawPath) {
    $raw = trim($rawPath ?? '');
    if (empty($raw) || strtolower($raw) === 'null') {
        return '/comicsmp/placeholder.jpg';
    }
    if (filter_var($raw, FILTER_VALIDATE_URL)) {
        $raw = str_replace('/images/images/', '/images/', $raw);
        return $raw;
    }
    $raw = str_replace('/images/images/', '/images/', $raw);
    $raw = preg_replace('#^(images/){2,}#i', 'images/', $raw);
    if (strpos($raw, '/comicsmp/images/') === 0) {
        $final = $raw;
    } elseif (strpos($raw, 'images/') === 0) {
        $final = '/comicsmp/' . $raw;
    } else {
        $final = '/comicsmp/images/' . ltrim($raw, '/');
    }
    $ext = pathinfo($final, PATHINFO_EXTENSION);
    if (empty($ext)) {
        $final .= '.jpg';
    }
    return $final;
}

// --- For Advanced Search: Get distinct countries ---
$queryCountries = "SELECT DISTINCT Country FROM comics ORDER BY Country ASC";
$resultCountries = mysqli_query($conn, $queryCountries);

// --- FETCH WANTED LIST ---
$sql = "
    SELECT comic_title, years, 
           GROUP_CONCAT(issue_number SEPARATOR ', ') AS issues,
           GROUP_CONCAT(Issue_URL SEPARATOR ',') AS issue_urls,
           COUNT(*) AS count
    FROM wanted_items
    WHERE user_id = ?
    GROUP BY comic_title, years
    ORDER BY comic_title ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultWanted = $stmt->get_result();
$wantedSeries = [];
while ($row = $resultWanted->fetch_assoc()) {
    $issuesArr = array_map(function ($iss) {
        $iss = trim($iss);
        return (strpos($iss, '#') === 0) ? $iss : '#' . $iss;
    }, explode(',', $row['issues']));
    $row['issues'] = implode(', ', $issuesArr);
    $wantedSeries[] = $row;
}
$stmt->close();

// --- FETCH SALE LISTINGS ---
$sql2 = "
    SELECT comic_title, years, 
           GROUP_CONCAT(issue_number SEPARATOR ', ') AS issues,
           GROUP_CONCAT(Issue_URL SEPARATOR ',') AS issue_urls,
           COUNT(*) AS count
    FROM comics_for_sale
    WHERE user_id = ?
    GROUP BY comic_title, years
    ORDER BY comic_title ASC
";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$resultSale = $stmt2->get_result();
$saleGroups = [];
while ($row = $resultSale->fetch_assoc()) {
    $issuesArr = array_map(function ($iss) {
        $iss = trim($iss);
        return (strpos($iss, '#') === 0) ? $iss : '#' . $iss;
    }, explode(',', $row['issues']));
    $row['issues'] = implode(', ', $issuesArr);
    $saleGroups[] = $row;
}
$stmt2->close();

// --- FETCH MATCHES ---
$sqlMatches = "
    SELECT 
        mn.id, 
        mn.comic_title, 
        mn.issue_number, 
        mn.years, 
        mn.issue_url, 
        mn.cover_image, 
        cs.image_path, 
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

// --- Get user's currency ---
$currency = '';
$stmtUser = $conn->prepare("SELECT currency FROM users WHERE id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
if ($rowUser = $resultUser->fetch_assoc()) {
    $currency = $rowUser['currency'];
}
$stmtUser->close();
if (!$currency) {
    $currency = 'USD';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard - ComicsMP</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
  <style>
    /* Base Styles */
    body { font-family: 'Roboto', sans-serif; background: #f0f2f5; color: #333; }
    a { text-decoration: none; color: inherit; }
    /* Header */
    .header { background: #1a1a1a; color: #fff; padding: 1rem 0; text-align: center; }
    .header h1 { font-size: 2.5rem; margin: 0; }
    /* Layout */
    .main-container { display: flex; margin-top: 1rem; }
    /* Sidebar Navigation */
    .sidebar {
      width: 220px; background-color: #333; color: #fff; min-height: 100vh; padding: 20px;
    }
    .sidebar h2 { font-size: 1.4rem; margin-bottom: 1.5rem; text-align: center; }
    .sidebar .nav-link {
      color: #fff; margin-bottom: 0.5rem; padding: 0.5rem 0.8rem; border-radius: 4px; cursor: pointer;
    }
    .sidebar .nav-link:hover, .sidebar .nav-link.active { background-color: #575757; }
    /* Main Content */
    .main-content { flex: 1; padding: 20px; background-color: #f8f9fa; min-height: 100vh; }
    /* Offcanvas for Search Filters */
    .offcanvas-header { background: #1a1a1a; color: #fff; }
    .offcanvas-body { padding: 1rem; }
    .advanced-search .modern-input {
      border: 2px solid #ccc; border-radius: 5px; padding: 0.75rem 1rem; font-size: 1.1rem;
      width: 100%; outline: none; transition: border-color 0.3s ease; margin-bottom: 1rem;
    }
    .advanced-search .modern-input:focus { border-color: #007bff; }
    .advanced-search .search-mode-group { margin-bottom: 1rem; display: flex; gap: 5px; justify-content: center; }
    .advanced-search .search-mode-group .btn { flex: 1; font-size: 0.9rem; padding: 0.5rem; }
    .advanced-search .filter-group { margin-bottom: 1rem; }
    .advanced-search .filter-group label { font-weight: 500; }
    .advanced-search .filter-group select {
      border-radius: 5px; border: 1px solid #ccc; padding: 0.5rem; width: 100%; margin-top: 0.5rem;
    }
    /* Auto-Suggest */
    .search-input-container { position: relative; }
    #suggestions {
      position: absolute; top: 100%; left: 0; right: 0; background: #fff;
      border: 1px solid #ddd; border-top: none; border-radius: 0 0 8px 8px;
      max-height: 250px; overflow-y: auto; z-index: 100;
    }
    #suggestions .suggestion-item { padding: 0.75rem 1rem; cursor: pointer; border-bottom: 1px solid #eee; transition: background 0.2s ease; }
    #suggestions .suggestion-item:hover { background: #f7f7f7; }
    /* Gallery: 8 covers per row (adjustable) */
    :root {
      --covers-per-row: 8;
      --gap: 15px;
    }
    .gallery { display: flex; flex-wrap: wrap; gap: var(--gap); margin-top: 1.5rem; }
    .gallery-item {
      width: calc((100% - (var(--covers-per-row) - 1) * var(--gap)) / var(--covers-per-row));
      min-height: 350px; background: #fafafa; border: 1px solid #ddd;
      border-radius: 8px; padding: 0.5rem; text-align: center; position: relative;
      transition: transform 0.3s ease, box-shadow 0.3s ease; cursor: pointer;
    }
    .gallery-item:hover { transform: translateY(-3px); box-shadow: 0 3px 10px rgba(0,0,0,0.12); }
    .gallery-item img { width: 100%; height: 250px; object-fit: contain; border-radius: 5px; background: #fff; }
    .button-wrapper { display: flex; justify-content: center; gap: 10px; margin-top: 0.5rem; }
    .button-wrapper button { padding: 0.4rem 0.8rem; font-size: 0.9rem; }
    /* Table styles for Wanted, Sale, Matches */
    .table-striped tbody tr:nth-of-type(odd) { background-color: rgba(0,0,0,.05); }
    .expand-row { background-color: #f1f1f1; }
    .cover-container { display: flex; flex-wrap: wrap; justify-content: center; }
    .nested-table thead { background-color: #eee; }
    /* Responsive */
    @media (max-width: 992px) { .gallery-item { width: calc((100% - (var(--covers-per-row) - 1) * var(--gap)) / var(--covers-per-row)); } }
    @media (max-width: 768px) {
      .main-container { flex-direction: column; }
      .sidebar { margin-right: 0; margin-bottom: 1rem; }
      .gallery-item { width: calc(33.33% - var(--gap)); }
      .main-content { padding: 10px; }
    }
    @media (max-width: 576px) { .gallery-item { width: calc(50% - var(--gap)); } }
    @media (max-width: 400px) { .gallery-item { width: 100%; } }
    /* Modal Styles */
    .popup-modal-body { display: flex; gap: 20px; flex-wrap: wrap; }
    .popup-image-container { flex: 0 0 40%; display: flex; align-items: center; justify-content: center; }
    .popup-image-container img { max-width: 100%; max-height: 350px; object-fit: contain; cursor: pointer; border-radius: 5px; }
    .popup-details-container { flex: 1; }
    .popup-details-container table { font-size: 1rem; }
    .similar-issues { margin-top: 20px; }
    .similar-issue-thumb { width: 80px; height: 120px; margin: 5px; object-fit: cover; cursor: pointer; }
    #showAllSimilarIssues { text-align: right; width: 100%; cursor: pointer; color: blue; margin-top: 5px; font-size: 0.9rem; }
  </style>
</head>
<body class="bg-light">
  <!-- HEADER -->
  <header class="header">
    <h1>2025 Comic Explorer</h1>
  </header>
  <div class="d-flex">
    <!-- SIDEBAR NAVIGATION -->
    <div class="sidebar">
      <h2>ComicsMP</h2>
      <nav class="nav flex-column">
        <a class="nav-link active" href="#dashboard" data-bs-toggle="tab" id="navDashboard">Dashboard</a>
        <a class="nav-link" href="#search" data-bs-toggle="tab" id="navSearch">Search</a>
        <a class="nav-link" href="#wanted" data-bs-toggle="tab">Wanted List</a>
        <a class="nav-link" href="#selling" data-bs-toggle="tab">Comics for Sale</a>
        <a class="nav-link" href="#matches" data-bs-toggle="tab">Matches</a>
        <a class="nav-link" href="#profile" data-bs-toggle="tab">Profile</a>
      </nav>
    </div>
    <!-- MAIN CONTENT AREA -->
    <div class="main-content">
      <div class="tab-content" id="profileTabContent">
        <!-- DASHBOARD TAB -->
        <div class="tab-pane fade show active" id="dashboard" role="tabpanel">
          <h2>Dashboard</h2>
          <p>Welcome to the Dashboard. Customize this section as needed.</p>
        </div>
        <!-- SEARCH TAB -->
        <div class="tab-pane fade" id="search" role="tabpanel">
          <!-- The offcanvas panel will open automatically when Search is activated -->
          <section class="content-area">
            <div id="resultsGallery" class="gallery"></div>
          </section>
        </div>
        <!-- WANTED TAB -->
        <div class="tab-pane fade" id="wanted" role="tabpanel">
          <h2 class="mt-4">My Wanted Comics</h2>
          <?php if (empty($wantedSeries)): ?>
            <p>No wanted items found.</p>
          <?php else: ?>
            <table class="table table-striped" id="wantedTable">
              <thead>
                <tr>
                  <th>Comic Title</th>
                  <th>Years</th>
                  <th>Issue Numbers</th>
                  <th>Count</th>
                  <th>Expand</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($wantedSeries as $index => $series): ?>
                  <tr class="main-row" data-index="<?php echo $index; ?>">
                    <td><?php echo htmlspecialchars($series['comic_title']); ?></td>
                    <td><?php echo htmlspecialchars($series['years']); ?></td>
                    <td><?php echo htmlspecialchars($series['issues']); ?></td>
                    <td><?php echo htmlspecialchars($series['count']); ?></td>
                    <td>
                      <button class="btn btn-info btn-sm expand-btn" 
                              data-comic-title="<?php echo htmlspecialchars($series['comic_title']); ?>" 
                              data-years="<?php echo htmlspecialchars($series['years']); ?>" 
                              data-issue-urls="<?php echo htmlspecialchars($series['issue_urls']); ?>"
                              data-index="<?php echo $index; ?>">
                        Expand
                      </button>
                    </td>
                  </tr>
                  <tr class="expand-row" id="expand-<?php echo $index; ?>" style="display:none;">
                    <td colspan="5">
                      <div class="cover-container" id="covers-<?php echo $index; ?>">
                        <!-- Wanted covers loaded via AJAX -->
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <!-- COMICS FOR SALE TAB -->
        <div class="tab-pane fade" id="selling" role="tabpanel">
          <h2 class="mt-4">Comics for Sale</h2>
          <?php if (empty($saleGroups)): ?>
            <p>No comics listed for sale.</p>
          <?php else: ?>
            <table class="table table-striped" id="sellingTable">
              <thead>
                <tr>
                  <th>Comic Title</th>
                  <th>Years</th>
                  <th>Issue Numbers</th>
                  <th>Count</th>
                  <th>Expand</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($saleGroups as $index => $group): ?>
                  <tr class="main-row" data-index="<?php echo $index; ?>">
                    <td><?php echo htmlspecialchars($group['comic_title']); ?></td>
                    <td><?php echo htmlspecialchars($group['years']); ?></td>
                    <td><?php echo htmlspecialchars($group['issues']); ?></td>
                    <td><?php echo htmlspecialchars($group['count']); ?></td>
                    <td>
                      <button class="btn btn-info btn-sm sale-expand-btn"
                              data-comic-title="<?php echo htmlspecialchars($group['comic_title']); ?>"
                              data-years="<?php echo htmlspecialchars($group['years']); ?>"
                              data-issue-urls="<?php echo htmlspecialchars($group['issue_urls']); ?>"
                              data-index="<?php echo $index; ?>">
                        Expand
                      </button>
                    </td>
                  </tr>
                  <tr class="expand-row" id="expand-sale-<?php echo $index; ?>" style="display:none;">
                    <td colspan="5">
                      <button class="btn btn-warning btn-sm bulk-edit-btn"
                              data-comic-title="<?php echo htmlspecialchars($group['comic_title']); ?>"
                              data-years="<?php echo htmlspecialchars($group['years']); ?>"
                              data-index="<?php echo $index; ?>">
                        Bulk Edit Series
                      </button>
                      <div class="cover-container" id="sale-covers-<?php echo $index; ?>">
                        <!-- Sale covers loaded via AJAX -->
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <!-- MATCHES TAB -->
        <div class="tab-pane fade" id="matches" role="tabpanel">
          <h2 class="mt-4">Your Matches</h2>
          <?php if (empty($groupedMatches)): ?>
            <p>No matches found at this time.</p>
          <?php else: ?>
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
                <?php foreach ($groupedMatches as $otherUserId => $matchesArray):
                        $displayName = $userNamesMap[$otherUserId] ?? ('User #'.$otherUserId);
                ?>
                  <tr class="match-main-row" data-index="<?php echo $otherUserId; ?>">
                    <td><?php echo htmlspecialchars($displayName); ?></td>
                    <td><?php echo count($matchesArray); ?></td>
                    <td>
                      <button class="btn btn-sm btn-primary send-message-btn"
                              data-other-user-id="<?php echo $otherUserId; ?>"
                              data-other-username="<?php echo htmlspecialchars($displayName); ?>"
                              data-matches='<?php echo json_encode($matchesArray); ?>'>
                        PM
                      </button>
                    </td>
                    <td>
                      <button class="btn btn-info btn-sm expand-match-btn"
                              data-other-user-id="<?php echo $otherUserId; ?>">
                        Expand
                      </button>
                    </td>
                  </tr>
                  <tr class="expand-match-row" id="expand-match-<?php echo $otherUserId; ?>" style="display:none;">
                    <td colspan="4">
                      <?php 
                        $buyMatches = array_filter($matchesArray, function($m) use ($user_id) {
                            return $m['buyer_id'] == $user_id;
                        });
                        $sellMatches = array_filter($matchesArray, function($m) use ($user_id) {
                            return $m['seller_id'] == $user_id;
                        });
                      ?>
                      <?php if (!empty($buyMatches)): ?>
                        <h5>Comics You Can Buy From <?php echo htmlspecialchars($displayName); ?></h5>
                        <table class="table table-bordered nested-table">
                          <thead>
                            <tr>
                              <th>Cover</th>
                              <th>Comic Title</th>
                              <th>Issue #</th>
                              <th>Year</th>
                              <th>Condition</th>
                              <th>Graded</th>
                              <th>Price</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($buyMatches as $m): ?>
                              <tr>
                                <td style="width:70px;">
                                  <img class="match-cover-img"
                                       src="<?php echo htmlspecialchars(getFinalImagePath($m['image_path'])); ?>"
                                       data-context="buy"
                                       data-other-user-id="<?php echo $otherUserId; ?>"
                                       data-comic-title="<?php echo htmlspecialchars($m['comic_title']); ?>"
                                       data-years="<?php echo htmlspecialchars($m['years']); ?>"
                                       data-issue-number="<?php echo htmlspecialchars($m['issue_number']); ?>"
                                       data-condition="<?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?>"
                                       data-graded="<?php echo ($m['graded'] == '1') ? 'Yes' : 'No'; ?>"
                                       data-price="<?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?>"
                                       alt="Cover">
                                </td>
                                <td><?php echo htmlspecialchars($m['comic_title']); ?></td>
                                <td><?php echo htmlspecialchars($m['issue_number']); ?></td>
                                <td><?php echo htmlspecialchars($m['years']); ?></td>
                                <td><?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?></td>
                                <td><?php echo ($m['graded'] == '1') ? 'Yes' : 'No'; ?></td>
                                <td><?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      <?php endif; ?>

                      <?php if (!empty($sellMatches)): ?>
                        <h5>Comics You Can Sell To <?php echo htmlspecialchars($displayName); ?></h5>
                        <table class="table table-bordered nested-table">
                          <thead>
                            <tr>
                              <th>Cover</th>
                              <th>Comic Title</th>
                              <th>Issue #</th>
                              <th>Year</th>
                              <th>Condition</th>
                              <th>Graded</th>
                              <th>Price</th>
                            </tr>
                          </thead>
                          <tbody>
                            <?php foreach ($sellMatches as $m): ?>
                              <tr>
                                <td style="width:70px;">
                                  <img class="match-cover-img"
                                       src="<?php echo htmlspecialchars(getFinalImagePath($m['image_path'])); ?>"
                                       data-context="sell"
                                       data-other-user-id="<?php echo $otherUserId; ?>"
                                       data-comic-title="<?php echo htmlspecialchars($m['comic_title']); ?>"
                                       data-years="<?php echo htmlspecialchars($m['years']); ?>"
                                       data-issue-number="<?php echo htmlspecialchars($m['issue_number']); ?>"
                                       data-condition="<?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?>"
                                       data-graded="<?php echo ($m['graded'] == '1') ? 'Yes' : 'No'; ?>"
                                       data-price="<?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?>"
                                       alt="Cover">
                                </td>
                                <td><?php echo htmlspecialchars($m['comic_title']); ?></td>
                                <td><?php echo htmlspecialchars($m['issue_number']); ?></td>
                                <td><?php echo htmlspecialchars($m['years']); ?></td>
                                <td><?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?></td>
                                <td><?php echo ($m['graded'] == '1') ? 'Yes' : 'No'; ?></td>
                                <td><?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.$currency : 'N/A'; ?></td>
                              </tr>
                            <?php endforeach; ?>
                          </tbody>
                        </table>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
        <!-- PROFILE TAB -->
        <div class="tab-pane fade" id="profile" role="tabpanel">
          <h2>Profile</h2>
          <p>Profile content goes here. (This could include user settings, activity, etc.)</p>
        </div>
      </div>
    </div>
  </div>
  <!-- END MAIN CONTENT -->

  <!-- Offcanvas Search Filters -->
  <div class="offcanvas offcanvas-start" tabindex="-1" id="searchFiltersOffcanvas" aria-labelledby="searchFiltersLabel">
    <div class="offcanvas-header">
      <h5 class="offcanvas-title" id="searchFiltersLabel">Search Filters</h5>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body">
      <div class="advanced-search">
        <div class="search-input-container">
          <input type="text" id="comicTitle" class="modern-input" placeholder="Start typing comic title..." autocomplete="off">
          <div id="suggestions"></div>
        </div>
        <!-- Search Mode Toggle -->
        <div class="search-mode-group">
          <button type="button" class="btn btn-outline-primary search-mode" data-mode="allWords">All Words</button>
          <button type="button" class="btn btn-outline-primary search-mode" data-mode="anyWords">Any Words</button>
          <button type="button" class="btn btn-outline-primary search-mode active" data-mode="startsWith">Starts With</button>
        </div>
        <!-- Filters -->
        <div class="filter-group">
          <label for="countrySelect">Country</label>
          <select id="countrySelect" class="form-select">
            <?php
              if ($resultCountries) {
                while ($row = mysqli_fetch_assoc($resultCountries)) {
                  $country = $row['Country'];
                  $selected = ($country == "USA") ? "selected" : "";
                  echo "<option value=\"$country\" $selected>$country</option>";
                }
              }
            ?>
          </select>
        </div>
        <div class="filter-group" id="yearFilterGroup" style="display:none;">
          <label for="yearSelect">Year</label>
          <select id="yearSelect" class="form-select">
            <option value="">Select a year</option>
          </select>
        </div>
        <div class="filter-group">
          <label for="tabSelect">Tab</label>
          <select id="tabSelect" class="form-select">
            <option value="All" selected>All</option>
            <option value="Issues">Issues</option>
          </select>
        </div>
        <div class="filter-group" id="issueFilterGroup" style="display:none;">
          <label for="issueSelect">Issue Number</label>
          <select id="issueSelect" class="form-select">
            <option value="All">All</option>
          </select>
        </div>
        <div class="filter-group" id="variantGroup">
          <label>Variants</label>
          <button type="button" class="btn btn-outline-primary w-100" id="variantToggle" data-enabled="0">Include Variants</button>
        </div>
      </div>
    </div>
  </div>
  <!-- End Offcanvas -->

  <!-- MODALS -->
  <!-- Edit Sale Listing Modal -->
  <div class="modal fade" id="editSaleModal" tabindex="-1" aria-labelledby="editSaleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="editSaleForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="editSaleModalLabel">Edit Sale Listing</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="editListingId" name="listing_id">
            <div class="mb-3">
              <label for="editCondition" class="form-label">Condition</label>
              <select class="form-select" id="editCondition" name="condition" required>
                <option value="10">10</option>
                <option value="9.9">9.9</option>
                <option value="9.8">9.8</option>
                <option value="9.6">9.6</option>
                <option value="9.4">9.4</option>
                <option value="9.2">9.2</option>
                <option value="9.0">9.0</option>
                <option value="8.5">8.5</option>
                <option value="8.0">8.0</option>
                <option value="7.5">7.5</option>
                <option value="7.0">7.0</option>
                <option value="6.5">6.5</option>
                <option value="6.0">6.0</option>
                <option value="5.5">5.5</option>
                <option value="5.0">5.0</option>
                <option value="4.5">4.5</option>
                <option value="4.0">4.0</option>
                <option value="3.5">3.5</option>
                <option value="3.0">3.0</option>
                <option value="2.5">2.5</option>
                <option value="2.0">2.0</option>
                <option value="1.8">1.8</option>
                <option value="1.5">1.5</option>
                <option value="1.0">1.0</option>
                <option value="0.5">0.5</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="editGraded" class="form-label">Graded</label>
              <select class="form-select" id="editGraded" name="graded" required>
                <option value="0">Not Graded</option>
                <option value="1">Graded</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="editPrice" class="form-label">Price</label>
              <input type="number" step="0.01" class="form-control" id="editPrice" name="price" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save Changes</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <!-- Bulk Edit Series Modal -->
  <div class="modal fade" id="bulkEditSaleModal" tabindex="-1" aria-labelledby="bulkEditSaleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="bulkEditSaleForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="bulkEditSaleModalLabel">Bulk Edit Series</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="bulkEditComicTitle" name="comic_title">
            <input type="hidden" id="bulkEditYears" name="years">
            <div class="mb-3">
              <label for="bulkEditCondition" class="form-label">Condition</label>
              <select class="form-select" id="bulkEditCondition" name="condition" required>
                <option value="10">10</option>
                <option value="9.9">9.9</option>
                <option value="9.8">9.8</option>
                <option value="9.6">9.6</option>
                <option value="9.4">9.4</option>
                <option value="9.2">9.2</option>
                <option value="9.0">9.0</option>
                <option value="8.5">8.5</option>
                <option value="8.0">8.0</option>
                <option value="7.5">7.5</option>
                <option value="7.0">7.0</option>
                <option value="6.5">6.5</option>
                <option value="6.0">6.0</option>
                <option value="5.5">5.5</option>
                <option value="5.0">5.0</option>
                <option value="4.5">4.5</option>
                <option value="4.0">4.0</option>
                <option value="3.5">3.5</option>
                <option value="3.0">3.0</option>
                <option value="2.5">2.5</option>
                <option value="2.0">2.0</option>
                <option value="1.8">1.8</option>
                <option value="1.5">1.5</option>
                <option value="1.0">1.0</option>
                <option value="0.5">0.5</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="bulkEditGraded" class="form-label">Graded</label>
              <select class="form-select" id="bulkEditGraded" name="graded" required>
                <option value="0">Not Graded</option>
                <option value="1">Graded</option>
              </select>
            </div>
            <div class="mb-3">
              <label for="bulkEditPrice" class="form-label">Price</label>
              <input type="number" step="0.01" class="form-control" id="bulkEditPrice" name="price" required>
            </div>
            <p class="text-muted">This will update all issues in the selected series.</p>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save Changes for Series</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </div>
      </form>
    </div>
  </div>
  <!-- Cover Popup Modal -->
  <div class="modal fade" id="coverPopupModal" tabindex="-1" aria-labelledby="coverPopupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="coverPopupModalLabel">Comic Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body popup-modal-body">
          <!-- Left Column: Large Cover Image -->
          <div class="popup-image-container">
            <img id="popupMainImage" src="" alt="Comic Cover">
          </div>
          <!-- Right Column: Details & Similar Issues -->
          <div class="popup-details-container">
            <table class="table table-sm">
              <tr>
                <th>Comic Title:</th>
                <td id="popupComicTitle"></td>
              </tr>
              <tr>
                <th>Years:</th>
                <td id="popupYears"></td>
              </tr>
              <tr>
                <th>Issue Number:</th>
                <td id="popupIssueNumber"></td>
              </tr>
              <tr>
                <th>Tab:</th>
                <td id="popupTab"></td>
              </tr>
              <tr>
                <th>Variant:</th>
                <td id="popupVariant"></td>
              </tr>
              <tr>
                <th>Date:</th>
                <td id="popupDate"></td>
              </tr>
              <tr>
                <th>UPC:</th>
                <td id="popupUPC"></td>
              </tr>
            </table>
            <div class="similar-issues">
              <h6>Similar Issues</h6>
              <div id="similarIssues" class="d-flex flex-wrap"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <!-- Send Message Modal for Matches -->
  <div class="modal fade" id="sendMessageModal" tabindex="-1" aria-labelledby="sendMessageModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <form id="sendMessageForm">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="sendMessageModalLabel">Send Message</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="recipient_id" id="recipient_id" value="">
            <div id="messageInfo" class="mb-3">
              <p>You're messaging <strong id="recipientName"></strong> about your matched comics.</p>
            </div>
            <div id="matchComicSelection" class="mb-3">
              <!-- Matched comics checkboxes will be loaded dynamically -->
            </div>
            <div class="mb-3">
              <label for="messagePreview" class="form-label">Message Preview</label>
              <textarea id="messagePreview" name="message" class="form-control" rows="5"></textarea>
              <small class="form-text text-muted">You can edit the message if needed before sending.</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Send Message</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- REQUIRED JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
  <script>
    document.addEventListener("DOMContentLoaded", () => {
      let searchMode = "startsWith";
      let autoSuggestRequest = null;

      // Function to perform live search and update gallery results.
      function performSearch() {
        const comicTitle = $("#comicTitle").val();
        const year = $("#yearSelect").val();
        const issueNumber = $("#issueSelect").val();
        const includeVariants = $("#variantToggle").attr("data-enabled") === "1" ? 1 : 0;
        const params = {
          comic_title: comicTitle,
          tab: $("#tabSelect").val(),
          issue_number: issueNumber,
          include_variants: includeVariants,
          mode: searchMode,
          year: year,
          country: $("#countrySelect").val()
        };
        if (issueNumber !== "All" && includeVariants == 1) {
          params.base_issue = issueNumber;
        }
        $.ajax({
          url: "searchResults.php",
          method: "GET",
          data: params,
          success: function(html) {
            $("#resultsGallery").html(html);
            // For each gallery item, attach action buttons
            $(".gallery-item").each(function() {
              const $item = $(this);
              $item.find(".button-wrapper").remove();
              const comicTitle = $item.data("comic-title");
              const issueNumber = $item.data("issue-number");
              const seriesYear = $item.data("years");
              const issueUrl = $item.data("issue_url");
              let wantedBtn = $(`<button class="btn btn-primary add-to-wanted">Wanted</button>`)
                    .attr("data-series-name", comicTitle)
                    .attr("data-issue-number", issueNumber)
                    .attr("data-series-year", seriesYear)
                    .attr("data-issue-url", issueUrl);
              if ($item.data("wanted") == 1) {
                wantedBtn = $(`<button class="btn btn-success add-to-wanted" disabled>Added</button>`);
              }
              let sellBtn = $(`<button class="btn btn-secondary sell-button">Sell</button>`);
              const buttonWrapper = $('<div class="button-wrapper text-center"></div>');
              buttonWrapper.append(wantedBtn).append(sellBtn);
              $item.append(buttonWrapper);
              if ($item.find(".sell-form").length === 0) {
                const sellFormHtml = `
                  <div class="sell-form" style="display: none;">
                    <form class="sell-comic-form">
                      <div class="mb-2">
                        <label>Condition:</label>
                        <select name="condition" class="form-select" required>
                          <option value="">Select Condition</option>
                          <option value="10">10</option>
                          <option value="9.9">9.9</option>
                          <option value="9.8">9.8</option>
                          <option value="9.6">9.6</option>
                          <option value="9.4">9.4</option>
                          <option value="9.2">9.2</option>
                          <option value="9.0">9.0</option>
                          <option value="8.5">8.5</option>
                          <option value="8.0">8.0</option>
                          <option value="7.5">7.5</option>
                          <option value="7.0">7.0</option>
                          <option value="6.5">6.5</option>
                          <option value="6.0">6.0</option>
                          <option value="5.5">5.5</option>
                          <option value="5.0">5.0</option>
                          <option value="4.5">4.5</option>
                          <option value="4.0">4.0</option>
                          <option value="3.5">3.5</option>
                          <option value="3.0">3.0</option>
                          <option value="2.5">2.5</option>
                          <option value="2.0">2.0</option>
                          <option value="1.8">1.8</option>
                          <option value="1.5">1.5</option>
                          <option value="1.0">1.0</option>
                          <option value="0.5">0.5</option>
                        </select>
                      </div>
                      <div class="mb-2">
                        <label>Graded:</label>
                        <select name="graded" class="form-select" required>
                          <option value="0" selected>Not Graded</option>
                          <option value="1">Graded</option>
                        </select>
                      </div>
                      <div class="mb-2">
                        <label>Price:</label>
                        <input type="number" name="price" class="form-control" required>
                      </div>
                      <input type="hidden" name="comic_title" value="${comicTitle}">
                      <input type="hidden" name="issue_number" value="${issueNumber}">
                      <input type="hidden" name="years" value="${seriesYear}">
                      <input type="hidden" name="issue_url" value="${issueUrl}">
                      <button type="submit" class="btn btn-success">Submit Listing</button>
                    </form>
                  </div>
                `;
                $item.append(sellFormHtml);
              }
            });
          },
          error: function() {
            $("#resultsGallery").html("<p class='text-danger'>Error loading results.</p>");
          }
        });
      }
      
      $(".search-mode").on("click", function() {
        if (autoSuggestRequest) { autoSuggestRequest.abort(); }
        $(".search-mode").removeClass("active");
        $(this).addClass("active");
        searchMode = $(this).data("mode");
        $("#suggestions").html("");
        performSearch();
      });
      
      $("#comicTitle").on("input", function() {
        const val = $(this).val();
        if (val.length > 2) {
          if (autoSuggestRequest) { autoSuggestRequest.abort(); }
          autoSuggestRequest = $.ajax({
            url: "suggest.php",
            method: "GET",
            data: { q: val, mode: searchMode, country: $("#countrySelect").val() },
            success: function(data) { $("#suggestions").html(data); },
            error: function() { $("#suggestions").html("<p class='text-danger'>No suggestions available.</p>"); },
            complete: function() { autoSuggestRequest = null; }
          });
          performSearch();
        } else {
          $("#suggestions").html("");
          $("#resultsGallery").html("");
        }
      });
      
      $(document).on("click", ".suggestion-item", function() {
        const title = $(this).text();
        $("#comicTitle").val(title);
        $("#suggestions").html("");
        $.get("getYears.php", { comic_title: title, country: $("#countrySelect").val() }, function(data) {
          $("#yearSelect").html('<option value="">Select a year</option>' + data);
          $("#yearFilterGroup").show();
          performSearch();
        });
        $.get("getTabs.php", { comic_title: title, country: $("#countrySelect").val() }, function(data) {
          $("#tabSelect").html(data);
          $("#tabSelect").val("All");
          performSearch();
        });
      });
      
      $("#yearSelect").on("change", function(){
        performSearch();
        const selectedYear = $(this).val();
        const comicTitle = $("#comicTitle").val();
        if(comicTitle && selectedYear){
          $.get("getTabs.php", { comic_title: comicTitle, year: selectedYear, country: $("#countrySelect").val() }, function(data){
            $("#tabSelect").html(data);
            if($("#tabSelect option[value='All']").length > 0){
              $("#tabSelect").val("All");
            } else {
              $("#tabSelect").prop("selectedIndex", 0);
            }
            performSearch();
          });
        }
      });
      
      $("#tabSelect, #countrySelect").on("change", function(){
        if($("#tabSelect").val() === "Issues"){
          $("#issueFilterGroup").show();
          $("#variantGroup").show();
          loadMainIssues();
        } else {
          $("#issueFilterGroup").hide();
          $("#issueSelect").html("");
          if($("#tabSelect").val() === "All"){
            $("#variantGroup").hide();
          } else {
            $("#variantGroup").show();
          }
          performSearch();
        }
      });
      
      $("#issueSelect").on("change", function(){ performSearch(); });
      
      function loadMainIssues() {
        const comicTitle = $("#comicTitle").val();
        const year = $("#yearSelect").val();
        const params = { comic_title: comicTitle, only_main: 1, year: year, country: $("#countrySelect").val() };
        $.get("getIssues.php", params, function(data) {
          $("#issueSelect").html(data);
          performSearch();
        });
      }
      
      $("#variantToggle").on("click", function() {
        let enabled = $(this).attr("data-enabled") === "1" ? 0 : 1;
        $(this).attr("data-enabled", enabled);
        if(enabled == 1){
          $(this).removeClass("btn-outline-primary").addClass("btn-primary");
        } else {
          $(this).removeClass("btn-primary").addClass("btn-outline-primary");
        }
        performSearch();
      });
      
      // -------------------------------
      // OTHER FUNCTIONALITY (Wanted, Sale, Matches, Modals)
      // -------------------------------
      $(document).on("click", ".add-to-wanted", function(e) {
        e.preventDefault();
        const btn = $(this);
        if (btn.is(":disabled")) return;
        const comicTitle = btn.data("series-name");
        const issueNumber = btn.data("issue-number");
        const seriesYear = btn.data("series-year");
        const tab = btn.data("tab") || "";
        const variant = btn.data("variant") || "";
        const issueUrl = btn.data("issue-url") || "";
        $.ajax({
          url: "addToWanted.php",
          method: "POST",
          data: { comic_title: comicTitle, issue_number: issueNumber, years: seriesYear, tab: tab, variant: variant, issue_url: issueUrl },
          success: function(response) {
            btn.replaceWith('<button class="btn btn-success add-to-wanted" disabled>Added</button>');
          },
          error: function() { alert("Error adding comic to wanted list"); }
        });
      });
      
      $(document).on("click", ".sell-button", function(e) {
        e.preventDefault();
        $(this).closest(".gallery-item").find(".sell-form").slideToggle();
      });
      
      $(document).on("submit", ".sell-comic-form", function(e) {
        e.preventDefault();
        const form = $(this);
        $.ajax({
          url: "addListing.php",
          method: "POST",
          data: form.serialize(),
          success: function(response) {
            form.closest(".sell-form").html('<div class="alert alert-success">Listed for Sale</div>');
          },
          error: function() { alert("Error listing comic for sale"); }
        });
      });
      
      function loadSimilarIssues(comicTitle, years, issueNumber, loadAll) {
        let requestData = { comic_title: comicTitle, years: years, issue_number: issueNumber };
        if (loadAll) { requestData.limit = "all"; }
        $.ajax({
          url: "getSimilarIssues.php",
          method: "GET",
          data: requestData,
          success: function(similarHtml) {
            if (!loadAll) { similarHtml += "<div id='showAllSimilarIssues'>Show All Similar Issues</div>"; }
            $("#similarIssues").html(similarHtml);
          },
          error: function() { $("#similarIssues").html("<p class='text-danger'>Could not load similar issues.</p>"); }
        });
      }
      
      // Popup Modal for Cover Image with Details & Similar Issues.
      // Use bootstrap.Modal.getOrCreateInstance() to ensure the modal is properly instantiated.
      $(document).on("click", ".gallery-item img", function(e) {
        if ($(e.target).closest("button").length) return;
        const parent = $(this).closest(".gallery-item");
        const fullImageUrl = $(this).attr("src");
        const comicTitle = parent.data("comic-title") || "N/A";
        const years = parent.data("years") || "N/A";
        const issueNumber = parent.data("issue-number") || "N/A";
        const tab = parent.data("tab") || "N/A";
        const variant = parent.data("variant") || "N/A";
        const date = parent.attr("data-date") || "N/A";
        
        $("#popupMainImage").attr("src", fullImageUrl);
        $("#popupComicTitle").text(comicTitle);
        $("#popupYears").text(years);
        $("#popupIssueNumber").text(issueNumber);
        $("#popupTab").text(tab);
        $("#popupVariant").text(variant);
        $("#popupDate").text(date);
        const upc = parent.data("upc") || "N/A"; 
        $("#popupUPC").text(upc);
        
        loadSimilarIssues(comicTitle, years, issueNumber, false);
        // Use getOrCreateInstance to ensure the modal opens
        var modalEl = document.getElementById("coverPopupModal");
        var modalInstance = bootstrap.Modal.getOrCreateInstance(modalEl);
        modalInstance.show();
      });
      
      // Handler for "Show All Similar Issues".
      $(document).on("click", "#showAllSimilarIssues", function() {
        const comicTitle = $("#popupComicTitle").text() || "";
        const years = $("#popupYears").text() || "";
        const issueNumber = $("#popupIssueNumber").text() || "";
        loadSimilarIssues(comicTitle, years, issueNumber, true);
      });
      
      // When a similar issue thumbnail is clicked, update the popup details.
      $(document).on("click", ".similar-issue-thumb", function() {
        const thumb = $(this);
        const comicTitle = thumb.data("comic-title") || "N/A";
        const years = thumb.data("years") || "N/A";
        const issueNumber = thumb.data("issue-number") || "N/A";
        const tab = thumb.data("tab") || "N/A";
        const variant = thumb.data("variant") || "N/A";
        const date = thumb.data("date") || "N/A";
        const upc = thumb.data("upc") || "N/A";
        
        $("#popupMainImage").attr("src", thumb.attr("src"));
        $("#popupComicTitle").text(comicTitle);
        $("#popupYears").text(years);
        $("#popupIssueNumber").text(issueNumber);
        $("#popupTab").text(tab);
        $("#popupVariant").text(variant);
        $("#popupDate").text(date);
        $("#popupUPC").text(upc);
      });
      
      // Allow clicking the main popup image to open full-size in a new window.
      $(document).on("click", "#popupMainImage", function() {
        const src = $(this).attr("src");
        if(src) { window.open(src, '_blank'); }
      });
      
      $(document).on("click", ".expand-btn", function(e) {
        e.stopPropagation();
        var btn = $(this);
        var index = btn.data("index");
        var comicTitle = btn.data("comic-title");
        var years = btn.data("years");
        var issueUrls = btn.data("issue-urls");
        var rowSelector = "#expand-" + index;
        if ($(rowSelector).is(":visible")) {
          $(rowSelector).slideUp();
          return;
        }
        $.ajax({
          url: "getSeriesCovers.php",
          method: "GET",
          data: { comic_title: comicTitle, years: years, issue_urls: issueUrls },
          success: function (html) {
            $("#covers-" + index).html(html);
            $(rowSelector).slideDown();
          },
          error: function () {
            alert("Error loading series covers.");
          }
        });
      });
      
      $(document).on("click", ".sale-expand-btn", function(e) {
        e.stopPropagation();
        var btn = $(this);
        var index = btn.data("index");
        var comicTitle = btn.data("comic-title");
        var years = btn.data("years");
        var issueUrls = btn.data("issue-urls");
        var rowSelector = "#expand-sale-" + index;
        if ($(rowSelector).is(":visible")) {
          $(rowSelector).slideUp();
          return;
        }
        $.ajax({
          url: "getSaleCovers.php",
          method: "GET",
          data: { comic_title: comicTitle, years: years, issue_urls: issueUrls },
          success: function (html) {
            $("#sale-covers-" + index).html(html);
            $(rowSelector).slideDown();
          },
          error: function () {
            alert("Error loading sale covers.");
          }
        });
      });
      
      $(document).on("click", ".expand-match-btn", function() {
        var otherUserId = $(this).data("other-user-id");
        var rowSelector = "#expand-match-" + otherUserId;
        if ($(rowSelector).is(":visible")) {
          $(rowSelector).slideUp();
        } else {
          $(rowSelector).slideDown();
        }
      });
      
      $(document).on("click", ".send-message-btn", function(e) {
        var recipientId = $(this).data("other-user-id");
        var recipientName = $(this).data("other-username");
        $("#recipient_id").val(recipientId);
        $("#recipientName").text(recipientName);
        var matchesData = $(this).data("matches");
        var matchesArray = (typeof matchesData === "string") ? JSON.parse(matchesData) : matchesData;
        currentMatches = matchesArray;
    
        var html = "";
        if (matchesArray.length) {
          matchesArray.forEach(function(match, idx) {
            var issueNum = match.issue_number ? match.issue_number.replace(/^#+/, '') : '';
            var line = '<div class="form-check mb-2 d-flex align-items-start" style="gap: 10px;">';
            line += '<input class="form-check-input mt-1 match-checkbox" type="checkbox" value="'+ idx +'" id="match_'+idx+'">';
            line += '<label class="form-check-label d-flex align-items-center" for="match_'+idx+'" style="gap: 10px;">';
            line += '<img src="'+ getFinalImagePathJS(match.cover_image) +'" alt="Cover" style="width:50px; height:75px; object-fit:cover;">';
            line += '<span>'+ match.comic_title + " (" + match.years + ") Issue #" + issueNum;
            if(match.comic_condition) { line += " (Condition: " + match.comic_condition + ")"; }
            if(match.price) { 
              var priceVal = parseFloat(match.price);
              var priceFormatted = "$" + priceVal.toFixed(2) + " " + (match.currency ? match.currency : (userCurrency ? userCurrency : "USD"));
              line += " (Price: " + priceFormatted + ")";
            }
            line += '</span></label></div>';
            html += line;
          });
        }
        $("#matchComicSelection").html(html);
        updateMessagePreview();
        var sendModal = new bootstrap.Modal(document.getElementById("sendMessageModal"));
        sendModal.show();
      });
      
      function updateMessagePreview() {
        var forSaleText = "";
        var wantedText = "";
        $("#matchComicSelection input.match-checkbox:checked").each(function(){
          var idx = $(this).val();
          var match = currentMatches[idx];
          var issueNum = match.issue_number ? match.issue_number.replace(/^#+/, '') : '';
          var line = "- " + match.comic_title + " (" + match.years + ") Issue #" + issueNum;
          if(match.comic_condition) { line += " (Condition: " + match.comic_condition + ")"; }
          if(match.price) {
            var priceVal = parseFloat(match.price);
            line += " (Price: $" + priceVal.toFixed(2) + " " + (match.currency ? match.currency : (userCurrency ? userCurrency : "USD")) + ")";
          }
          line += "\n";
          if (parseInt(match.buyer_id) === parseInt(currentUserId)) { forSaleText += line; }
          else if (parseInt(match.seller_id) === parseInt(currentUserId)) { wantedText += line; }
        });
        var recipientName = $("#recipientName").text() || "there";
        var messageText = "Hi " + recipientName + ",\n\n";
        if (forSaleText) { messageText += "I'm interested in buying the following comics:\n" + forSaleText + "\n"; }
        if (wantedText) { messageText += "I'm interested in selling the following comics:\n" + wantedText + "\n"; }
        messageText += "Please let me know if you're interested.";
        $("#messagePreview").val(messageText);
      }
      
      $(document).on("change", "#matchComicSelection input.match-checkbox", function() {
        updateMessagePreview();
      });
      
      $("#sendMessageForm").on("submit", function(e) {
        e.preventDefault();
        var formData = $(this).serialize();
        $.ajax({
          url: "sendMessage.php",
          method: "POST",
          data: formData,
          dataType: "json",
          success: function(response) {
            if (response.status === 'success') {
              alert("Message sent successfully.");
              $("#sendMessageModal").modal("hide");
            } else {
              alert(response.message);
            }
          },
          error: function() {
            alert("Failed to send message.");
          }
        });
      });
      
      // -------------------------------
      // Automatically open the offcanvas when "Search" is activated
      // -------------------------------
      var searchOffcanvas = new bootstrap.Offcanvas(document.getElementById('searchFiltersOffcanvas'));
      $('#navSearch').on('shown.bs.tab', function() {
        searchOffcanvas.show();
      });
    });
  </script>
</body>
</html>

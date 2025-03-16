<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connection.php';

$user_id = $_SESSION['user_id'] ?? 0;

/**
 * Helper function to normalize image paths (PHP side).
 */
function getFinalImagePath($rawPath) {
    $raw = trim($rawPath ?? '');
    if (empty($raw) || strtolower($raw) === 'null') {
        return '/comicsmp/placeholder.jpg';
    }
    if (filter_var($raw, FILTER_VALIDATE_URL)) {
        // Remove any double /images/images/ occurrences
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
$result = $stmt->get_result();
$wantedSeries = [];
while ($row = $result->fetch_assoc()) {
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
$result2 = $stmt2->get_result();
$saleGroups = [];
while ($row = $result2->fetch_assoc()) {
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

// Get user's currency (also passed to JavaScript)
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
  <title>Profile - Wanted, Selling & Matches</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    .cover-img {
      width: 150px;
      height: 225px;
      object-fit: cover;
      margin: 5px;
      border: 1px solid #ddd;
      border-radius: 4px;
      cursor: pointer;
    }
    .cover-wrapper {
      position: relative;
      display: inline-block;
      width: 150px;
    }
    .remove-cover, .remove-sale {
      position: absolute;
      top: 2px;
      right: 2px;
      background: rgba(255,0,0,0.8);
      color: white;
      border: none;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 12px;
      cursor: pointer;
      line-height: 18px;
      text-align: center;
      z-index: 10;
    }
    .edit-sale {
      position: absolute;
      top: 2px;
      right: 26px;
      background: rgba(0,123,255,0.8);
      color: white;
      border: none;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 12px;
      cursor: pointer;
      line-height: 18px;
      text-align: center;
      z-index: 10;
    }
    .expand-row {
      background-color: #f1f1f1;
    }
    .cover-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
    }
    .popup-modal-body {
      display: flex;
      gap: 20px;
    }
    .popup-image-container {
      flex: 0 0 40%;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .popup-image-container img {
      max-width: 100%;
      max-height: 350px;
      object-fit: contain;
      cursor: pointer;
    }
    .popup-details-container {
      flex: 1;
    }
    .popup-details-container table {
      font-size: 1rem;
    }
    /* Removed Similar Issues section */
    .similar-issues {
      display: none;
    }
    .expand-match-row {
      background-color: #f9f9f9;
    }
    .nested-table thead {
      background-color: #eee;
    }
    #popupConditionRow,
    #popupGradedRow,
    #popupPriceRow {
      display: none;
    }
  </style>
</head>
<body class="bg-light">
<div class="container my-4">
  <h1 class="text-center">Profile</h1>
  <!-- Nav Tabs -->
  <ul class="nav nav-tabs" id="profileTab" role="tablist">
    <li class="nav-item" role="presentation">
      <button class="nav-link active" id="wanted-tab" data-bs-toggle="tab" data-bs-target="#wanted" type="button" role="tab" aria-controls="wanted" aria-selected="true">Wanted List</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="selling-tab" data-bs-toggle="tab" data-bs-target="#selling" type="button" role="tab" aria-controls="selling" aria-selected="false">Comics for Sale</button>
    </li>
    <li class="nav-item" role="presentation">
      <button class="nav-link" id="matches-tab" data-bs-toggle="tab" data-bs-target="#matches" type="button" role="tab" aria-controls="matches" aria-selected="false">Matches</button>
    </li>
  </ul>

  <!-- Tab Panes -->
  <div class="tab-content" id="profileTabContent">
    <!-- Wanted List Tab -->
    <div class="tab-pane fade show active" id="wanted" role="tabpanel" aria-labelledby="wanted-tab">
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
                          data-index="<?php echo $index; ?>">Expand</button>
                </td>
              </tr>
              <tr class="expand-row" id="expand-<?php echo $index; ?>" style="display:none;">
                <td colspan="5">
                  <div class="cover-container" id="covers-<?php echo $index; ?>">
                    <!-- Wanted cover images loaded via AJAX will appear here -->
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    
    <!-- Comics for Sale Tab -->
    <div class="tab-pane fade" id="selling" role="tabpanel" aria-labelledby="selling-tab">
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
                          data-index="<?php echo $index; ?>">Expand</button>
                </td>
              </tr>
              <tr class="expand-row" id="expand-sale-<?php echo $index; ?>" style="display:none;">
                <td colspan="5">
                  <button class="btn btn-warning btn-sm bulk-edit-btn" 
                          data-comic-title="<?php echo htmlspecialchars($group['comic_title']); ?>" 
                          data-years="<?php echo htmlspecialchars($group['years']); ?>" 
                          data-index="<?php echo $index; ?>">Bulk Edit Series</button>
                  <div class="cover-container" id="sale-covers-<?php echo $index; ?>">
                    <!-- Sale cover images loaded via AJAX will appear here -->
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
    
    <!-- Matches Tab -->
    <div class="tab-pane fade" id="matches" role="tabpanel" aria-labelledby="matches-tab">
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
                                   data-price="<?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.htmlspecialchars($currency) : 'N/A'; ?>"
                                   alt="Cover"
                                   style="width:60px; height:90px; object-fit:cover;">
                            </td>
                            <td><?php echo htmlspecialchars($m['comic_title']); ?></td>
                            <td><?php echo htmlspecialchars($m['issue_number']); ?></td>
                            <td><?php echo htmlspecialchars($m['years']); ?></td>
                            <td><?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?></td>
                            <td><?php echo ($m['graded'] == '1') ? 'Yes' : 'No'; ?></td>
                            <td><?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.htmlspecialchars($currency) : 'N/A'; ?></td>
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
                                   data-price="<?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.htmlspecialchars($currency) : 'N/A'; ?>"
                                   alt="Cover"
                                   style="width:60px; height:90px; object-fit:cover;">
                            </td>
                            <td><?php echo htmlspecialchars($m['comic_title']); ?></td>
                            <td><?php echo htmlspecialchars($m['issue_number']); ?></td>
                            <td><?php echo htmlspecialchars($m['years']); ?></td>
                            <td><?php echo htmlspecialchars($m['comic_condition'] ?? 'N/A'); ?></td>
                            <td><?php echo ($m['graded'] == '1') ? 'Yes' : 'No'; ?></td>
                            <td><?php echo !empty($m['price']) ? '$'.number_format($m['price'],2).' '.htmlspecialchars($currency) : 'N/A'; ?></td>
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
  </div>
</div>

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

<!-- Profile Modal for Cover Image (Similar Issues section removed) -->
<div class="modal fade" id="profileImageModal" tabindex="-1" aria-labelledby="profilePopupModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="profilePopupModalLabel">Comic Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body popup-modal-body">
        <div class="popup-image-container">
          <img id="popupMainImage" src="" alt="Comic Cover">
        </div>
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
            <tr id="popupConditionRow">
              <th>Condition:</th>
              <td id="popupCondition"></td>
            </tr>
            <tr id="popupGradedRow">
              <th>Graded:</th>
              <td id="popupGraded"></td>
            </tr>
            <tr id="popupPriceRow">
              <th>Price:</th>
              <td id="popupPrice"></td>
            </tr>
          </table>
          <!-- Similar issues section removed -->
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
          <!-- Hidden recipient field -->
          <input type="hidden" name="recipient_id" id="recipient_id" value="">
          <!-- Display recipient information -->
          <div id="messageInfo" class="mb-3">
            <p>You're messaging <strong id="recipientName"></strong> about your matched comics.</p>
          </div>
          <!-- Container where matched comics will be listed as checkboxes, divided into groups -->
          <div id="matchComicSelection" class="mb-3">
            <!-- Populated dynamically -->
          </div>
          <!-- Message Preview Textarea -->
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

<!-- Required JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
  // JavaScript helper to mimic PHP's getFinalImagePath logic
  function getFinalImagePathJS(raw) {
    raw = raw ? raw.trim() : '';
    if (!raw || raw.toLowerCase() === 'null') {
      return '/comicsmp/placeholder.jpg';
    }
    if (raw.startsWith('http://') || raw.startsWith('https://')) {
      raw = raw.replace('/images/images/', '/images/');
    } else {
      raw = raw.replace('/images/images/', '/images/');
      if (raw.startsWith('/comicsmp/images/')) {
        // do nothing
      } else if (raw.startsWith('/images/')) {
        raw = '/comicsmp' + raw;
      } else if (raw.startsWith('images/')) {
        raw = '/comicsmp/' + raw;
      } else {
        raw = '/comicsmp/images/' + raw.replace(/^\/+/, '');
      }
    }
    if (!raw.match(/\.(jpg|jpeg|png|gif)$/i)) {
      raw += '.jpg';
    }
    return raw;
  }

  // Set current user ID and currency from PHP
  var currentUserId = <?php echo json_encode($user_id); ?>;
  var userCurrency = <?php echo json_encode($currency); ?>;
  
  // Global variable to hold current matches for the modal
  var currentMatches = [];
  
  // --- Update Matches via AJAX --- 
  // This call runs update_matches.php after window load to update match notifications.
  $(window).on('load', function(){
    setTimeout(function(){
      $.ajax({
        url: "update_matches.php",
        method: "GET",
        cache: false,
        success: function(response) {
          console.log("Update matches response:", response);
          var inserted = 0, deleted = 0;
          var matchmakerMatch = response.match(/(\d+)\s+matches inserted/i);
          if(matchmakerMatch){
            inserted = parseInt(matchmakerMatch[1]);
          }
          var cleanupMatch = response.match(/(\d+)\s+match notifications deleted/i);
          if(cleanupMatch){
            deleted = parseInt(cleanupMatch[1]);
          }
          if(inserted > 0 || deleted > 0){
            location.reload();
          }
        },
        error: function(xhr, status, error) {
          console.error("Update matches error:", error);
        }
      });
    }, 1000);
  });
  
  $(document).ready(function(){
    if (window.location.hash === "#selling") {
      $('#selling-tab').tab('show');
    }
  
    // Expand for Wanted List
    $('#wantedTable').on("click", ".expand-btn", function (e) {
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
  
    // Expand for Sales List
    $('#sellingTable').on("click", ".sale-expand-btn", function (e) {
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
  
    // Delete sale entry
    $(document).on("click", ".remove-sale", function () {
      var button = $(this);
      var listingId = button.data("listing-id");
      if (confirm("Are you sure you want to delete this sale listing?")) {
        $.ajax({
          url: "deleteSale.php",
          method: "POST",
          data: { listing_id: listingId },
          success: function (response) {
            if (response.status === 'success') {
              button.closest(".cover-wrapper").remove();
            } else {
              alert(response.message);
            }
          },
          error: function () {
            alert("Failed to delete the sale listing.");
          }
        });
      }
    });
  
    // Delete wanted entry
    $(document).off("click", ".remove-cover").on("click", ".remove-cover", function(e) {
      e.preventDefault();
      e.stopPropagation();
      var button = $(this);
      var comicTitle = button.data("comic-title");
      var issueNumber = button.data("issue-number");
      var years = button.data("years");
      var issueUrl = button.data("issue_url");
      if (confirm("Are you sure you want to delete this wanted listing?")) {
        $.ajax({
          url: "deleteWanted.php",
          method: "POST",
          dataType: "json",
          data: { comic_title: comicTitle, issue_number: issueNumber, years: years, issue_url: issueUrl },
          success: function(response) {
            if (response.status === 'success') {
              button.closest(".cover-wrapper").remove();
            } else {
              alert(response.message);
            }
          },
          error: function() {
            alert("Failed to delete the wanted listing.");
          }
        });
      }
    });
  
    // Edit sale (single) - open modal
    $(document).on("click", ".edit-sale", function (e) {
      e.preventDefault();
      e.stopPropagation();
      var button = $(this);
      var listingId = button.data("listing-id");
      var price = button.data("price");
      var condition = button.data("condition");
      var graded = button.data("graded");
      $("#editListingId").val(listingId);
      $("#editCondition").val(condition);
      $("#editGraded").val(graded);
      $("#editPrice").val(price);
      var editModal = new bootstrap.Modal(document.getElementById('editSaleModal'));
      editModal.show();
    });
  
    // Bulk edit series - open modal
    $(document).on("click", ".bulk-edit-btn", function (e) {
      e.preventDefault();
      e.stopPropagation();
      var button = $(this);
      var comicTitle = button.data("comic-title");
      var years = button.data("years");
      $("#bulkEditComicTitle").val(comicTitle);
      $("#bulkEditYears").val(years);
      var bulkEditModal = new bootstrap.Modal(document.getElementById('bulkEditSaleModal'));
      bulkEditModal.show();
    });
  
    // Submit single edit form
    $("#editSaleForm").on("submit", function (e) {
      e.preventDefault();
      var formData = $(this).serialize();
      $.ajax({
        url: "editSale.php",
        method: "POST",
        data: formData,
        success: function (response) {
          if (response.status === 'success') {
            alert("Sale listing updated successfully.");
            window.location.hash = 'selling';
            location.reload();
          } else {
            alert(response.message);
          }
        },
        error: function () {
          alert("Failed to update the sale listing.");
        }
      });
    });
  
    // Submit bulk edit form
    $("#bulkEditSaleForm").on("submit", function (e) {
      e.preventDefault();
      var formData = $(this).serialize();
      $.ajax({
        url: "editSaleBulk.php",
        method: "POST",
        data: formData,
        success: function (response) {
          if (response.status === 'success') {
            alert("Bulk update successful.");
            window.location.hash = 'selling';
            location.reload();
          } else {
            alert(response.message);
          }
        },
        error: function () {
          alert("Failed to update the series.");
        }
      });
    });
  
    // Matches tab: Expand for grouped matches
    $(document).on("click", ".expand-match-btn", function() {
      var otherUserId = $(this).data("other-user-id");
      var rowSelector = "#expand-match-" + otherUserId;
      if ($(rowSelector).is(":visible")) {
        $(rowSelector).slideUp();
      } else {
        $(rowSelector).slideDown();
      }
    });
  
    // Popup Modal for cover images (Wanted/Sale)
    $(document).on("click", ".cover-img", function (e) {
      if ($(e.target).is("button") || $(e.target).closest("button").length > 0) {
          return;
      }
      var $wrapper = $(this).closest(".cover-wrapper");
      var containerId = $wrapper.closest(".cover-container").attr("id") || "";
      
      if (containerId.indexOf("sale-covers-") === 0) {
          $("#popupConditionRow").show();
          $("#popupGradedRow").show();
          $("#popupPriceRow").show();
      } else {
          $("#popupConditionRow").hide();
          $("#popupGradedRow").hide();
          $("#popupPriceRow").hide();
      }
      
      var src = $(this).attr("src");
      var comicTitle = $wrapper.data("comic-title") || "N/A";
      var years = $wrapper.data("years") || "N/A";
      var issueNumber = $wrapper.data("issue-number") || "N/A";
      var tab = $wrapper.data("tab") || "N/A";
      var variant = $wrapper.data("variant") || "N/A";
      var date = $wrapper.attr("data-date") || "N/A";
      var condition = $wrapper.attr("data-condition") || "N/A";
      var graded = $wrapper.attr("data-graded") || "N/A";
      var price = $wrapper.attr("data-price") || "N/A";
      
      $("#popupMainImage").attr("src", src);
      $("#popupComicTitle").text(comicTitle);
      $("#popupYears").text(years);
      $("#popupIssueNumber").text(issueNumber);
      $("#popupTab").text(tab);
      $("#popupVariant").text(variant);
      $("#popupDate").text(date);
      $("#popupCondition").text(condition);
      $("#popupGraded").text(graded);
      $("#popupPrice").text(price);
      
      var modal = new bootstrap.Modal(document.getElementById("profileImageModal"));
      modal.show();
    });
  
    // Popup Modal for match cover images (Matches Tab)
    $(document).on("click", ".match-cover-img", function(e) {
      e.preventDefault();
      $("#popupConditionRow").show();
      $("#popupGradedRow").show();
      $("#popupPriceRow").show();
      
      var $img = $(this);
      var src = $img.attr("src");
      var context = $img.data("context");
      var otherUserId = $img.data("other-user-id");
      var comicTitle = $img.data("comic-title") || "N/A";
      var years = $img.data("years") || "N/A";
      var issueNumber = $img.data("issue-number") || "N/A";
      var condition = $img.data("condition") || "N/A";
      var graded = $img.data("graded") || "N/A";
      var price = $img.data("price") || "N/A";
      
      $("#popupMainImage").attr("src", src);
      $("#popupComicTitle").text(comicTitle);
      $("#popupYears").text(years);
      $("#popupIssueNumber").text(issueNumber);
      $("#popupTab").text("Loading...");
      $("#popupVariant").text("Loading...");
      $("#popupDate").text("Loading...");
      $("#popupCondition").text(condition);
      $("#popupGraded").text(graded);
      $("#popupPrice").text(price);
      
      $.ajax({
        url: "getUsername.php",
        method: "GET",
        data: { user_id: otherUserId },
        success: function(username) {
          // No heading update needed.
        },
        error: function(){
          // Do nothing.
        }
      });
      
      $.ajax({
        url: "getMatchComicDetails.php",
        method: "GET",
        dataType: "json",
        data: { comic_title: comicTitle, years: years, issue_number: issueNumber },
        success: function(data) {
          $("#popupTab").text(data.Tab || "N/A");
          $("#popupVariant").text(data.Variant || "N/A");
          $("#popupDate").text(data.Date || "N/A");
          if(data.comic_condition) {
            $("#popupCondition").text(data.comic_condition);
          }
          if(data.graded) {
            $("#popupGraded").text(data.graded);
          }
          if(data.price) {
            $("#popupPrice").text(data.price);
          }
        },
        error: function() {
          $("#popupTab").text("N/A");
          $("#popupVariant").text("N/A");
          $("#popupDate").text("N/A");
        }
      });
      
      var modal = new bootstrap.Modal(document.getElementById("profileImageModal"));
      modal.show();
    });
  
    // Allow opening the large image in a new tab
    $(document).on("click", "#popupMainImage", function() {
      var src = $(this).attr("src");
      if(src) {
        window.open(src, '_blank');
      }
    });
  
    // --- Improved Send Message Modal from Matches ---
    $(document).on("click", ".send-message-btn", function(e) {
      var recipientId = $(this).data("other-user-id");
      var recipientName = $(this).data("other-username");
      $("#recipient_id").val(recipientId);
      $("#recipientName").text(recipientName);
      var matchesData = $(this).data("matches");
      var matchesArray = (typeof matchesData === "string") ? JSON.parse(matchesData) : matchesData;
      currentMatches = matchesArray; // store globally
  
      // Separate matches into groups: For Sale (when current user is buyer) and Wanted (when current user is seller)
      var forSaleMatches = [];
      var wantedMatches = [];
      for (var i = 0; i < matchesArray.length; i++) {
        var match = matchesArray[i];
        if (parseInt(match.buyer_id) === parseInt(currentUserId)) {
           forSaleMatches.push({match: match, index: i});
        } else if (parseInt(match.seller_id) === parseInt(currentUserId)) {
           wantedMatches.push({match: match, index: i});
        }
      }
  
      var html = "";
      if (forSaleMatches.length > 0) {
        html += "<h6>Comics For Sale from " + recipientName + ":</h6>";
        forSaleMatches.forEach(function(item) {
           var idx = item.index;
           var match = item.match;
           var imagePath = getFinalImagePathJS(match.cover_image);
           var issueNum = match.issue_number ? match.issue_number.replace(/^#+/, '') : '';
           html += '<div class="form-check mb-2 d-flex align-items-start" style="gap: 10px;">';
           html += '<input class="form-check-input mt-1 match-checkbox" type="checkbox" value="'+ idx +'" id="match_'+idx+'">';
           html += '<label class="form-check-label d-flex align-items-center" for="match_'+idx+'" style="gap:10px;">';
           html += '<img src="'+ imagePath +'" alt="Cover" style="width:50px; height:75px; object-fit:cover;">';
           html += '<span>';
           html += match.comic_title + " (" + match.years + ") Issue #" + issueNum;
           if(match.comic_condition) {
              html += " (Condition: " + match.comic_condition + ")";
           }
           if(match.price) {
              var priceVal = parseFloat(match.price);
              var priceFormatted = "$" + priceVal.toFixed(2) + " " + (match.currency ? match.currency : (userCurrency ? userCurrency : "USD"));
              html += " (Price: " + priceFormatted + ")";
           }
           html += '</span></label></div>';
        });
      }
      if (wantedMatches.length > 0) {
        html += "<h6>Comics Wanted by " + recipientName + ":</h6>";
        wantedMatches.forEach(function(item) {
           var idx = item.index;
           var match = item.match;
           var imagePath = getFinalImagePathJS(match.cover_image);
           var issueNum = match.issue_number ? match.issue_number.replace(/^#+/, '') : '';
           html += '<div class="form-check mb-2 d-flex align-items-start" style="gap: 10px;">';
           html += '<input class="form-check-input mt-1 match-checkbox" type="checkbox" value="'+ idx +'" id="match_'+idx+'">';
           html += '<label class="form-check-label d-flex align-items-center" for="match_'+idx+'" style="gap:10px;">';
           html += '<img src="'+ imagePath +'" alt="Cover" style="width:50px; height:75px; object-fit:cover;">';
           html += '<span>';
           html += match.comic_title + " (" + match.years + ") Issue #" + issueNum;
           if(match.comic_condition) {
              html += " (Condition: " + match.comic_condition + ")";
           }
           if(match.price) {
              var priceVal = parseFloat(match.price);
              var priceFormatted = "$" + priceVal.toFixed(2) + " " + (match.currency ? match.currency : (userCurrency ? userCurrency : "USD"));
              html += " (Price: " + priceFormatted + ")";
           }
           html += '</span></label></div>';
        });
      }
      $("#matchComicSelection").html(html);
  
      // Update message preview initially
      updateMessagePreview();
  
      var sendModal = new bootstrap.Modal(document.getElementById("sendMessageModal"));
      sendModal.show();
    });
  
    // Update message preview based on selected checkboxes
    function updateMessagePreview() {
      var forSaleText = "";
      var wantedText = "";
      $("#matchComicSelection input.match-checkbox:checked").each(function(){
           var idx = $(this).val();
           var match = currentMatches[idx];
           var issueNum = match.issue_number ? match.issue_number.replace(/^#+/, '') : '';
           var line = "- " + match.comic_title + " (" + match.years + ") Issue #" + issueNum;
           if(match.comic_condition) {
              line += " (Condition: " + match.comic_condition + ")";
           }
           if(match.price) {
              var priceVal = parseFloat(match.price);
              var priceFormatted = "$" + priceVal.toFixed(2) + " " + (match.currency ? match.currency : (userCurrency ? userCurrency : "USD"));
              line += " (Price: " + priceFormatted + ")";
           }
           line += "\n";
           if (parseInt(match.buyer_id) === parseInt(currentUserId)) {
              forSaleText += line;
           } else if (parseInt(match.seller_id) === parseInt(currentUserId)) {
              wantedText += line;
           }
      });
      var recipientName = $("#recipientName").text() || "there";
      var messageText = "Hi " + recipientName + ",\n\n";
      if (forSaleText) {
         messageText += "I'm interested in buying the following comics:\n" + forSaleText + "\n";
      }
      if (wantedText) {
         messageText += "I'm interested in selling the following comics:\n" + wantedText + "\n";
      }
      messageText += "Please let me know if you're interested.";
      $("#messagePreview").val(messageText);
    }
  
    // When any checkbox changes, update message preview
    $(document).on("change", "#matchComicSelection input.match-checkbox", function() {
        updateMessagePreview();
    });
  
    // Handle Send Message Form submission
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
  }); // end document ready
</script>
</body>
</html>

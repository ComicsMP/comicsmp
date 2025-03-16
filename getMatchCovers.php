<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Enable MySQLi error reporting
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

session_start();
require_once 'db_connection.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "Error: You must be logged in.";
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;
$other_user_id = $_GET['other_user_id'] ?? '';
if (!$other_user_id || !is_numeric($other_user_id)) {
    echo "Error: Missing or invalid other_user_id parameter.";
    exit;
}
$other_user_id = (int) $other_user_id;

// Get the current user's currency
$currency = '';
$stmtUser = $conn->prepare("SELECT currency FROM users WHERE id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$resUser = $stmtUser->get_result();
if ($rowUser = $resUser->fetch_assoc()) {
    $currency = $rowUser['currency'] ?: 'USD';
}
$stmtUser->close();

// Fetch matches between current user and the other user
$sql = "
    SELECT
        mn.id AS match_id,
        mn.comic_title,
        mn.issue_number,
        mn.years,
        mn.issue_url,
        mn.cover_image,
        cs.image_path,
        cs.comic_condition,
        cs.graded,
        cs.price,
        mn.match_time,
        mn.buyer_id,
        mn.seller_id
    FROM match_notifications mn
    LEFT JOIN comics_for_sale cs
        ON (cs.issue_url = mn.issue_url AND cs.user_id = ?)
    WHERE
        ((mn.buyer_id = ? AND mn.seller_id = ?)
        OR (mn.seller_id = ? AND mn.buyer_id = ?))
    ORDER BY CAST(REGEXP_SUBSTR(mn.issue_number, '^[0-9]+') AS UNSIGNED),
             mn.issue_number ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiiii", $other_user_id, $user_id, $other_user_id, $user_id, $other_user_id);
$stmt->execute();
$result = $stmt->get_result();

// Start building output: a full-width row of cards
$output = '<div class="row row-cols-2 row-cols-md-4 g-3" style="width:100%;">';

while ($row = $result->fetch_assoc()) {
    // Decide which image to display
    $rawPath = trim($row['image_path'] ?: $row['cover_image']);
    if (!$rawPath || strtolower($rawPath) === 'null') {
        $imgPath = '/comicsmp/placeholder.jpg';
    } elseif (filter_var($rawPath, FILTER_VALIDATE_URL)) {
        $imgPath = $rawPath;
    } elseif (strpos($rawPath, '/comicsmp/') === 0) {
        $imgPath = $rawPath;
    } elseif (strpos($rawPath, '/images/') === 0) {
        $imgPath = '/comicsmp' . $rawPath;
    } elseif (strpos($rawPath, 'images/') === 0) {
        $imgPath = '/comicsmp/' . $rawPath;
    } else {
        $imgPath = '/comicsmp/images/' . ltrim($rawPath, '/');
    }

    // Ensure issue number has a '#' prefix
    $issue = trim($row['issue_number'] ?? '');
    if (!empty($issue) && strpos($issue, '#') !== 0) {
        $issue = '#' . $issue;
    }

    $condition = htmlspecialchars($row['comic_condition'] ?? 'N/A');
    $graded = ($row['graded'] == '1') ? 'Yes' : 'No';
    $priceVal = floatval($row['price'] ?? 0);
    $priceFormatted = number_format($priceVal, 2);
    $price = '$' . $priceFormatted . ' ' . htmlspecialchars($currency);

    // Each match is displayed as a card in a Bootstrap grid column
    $output .= '<div class="col">';
    $output .= '  <div class="card h-100">';
    $output .= '    <img src="' . htmlspecialchars($imgPath) . '" alt="Cover" class="card-img-top match-cover-img" style="cursor:pointer;"';
    $output .= '         data-other-user-id="' . htmlspecialchars($other_user_id) . '"';
    $output .= '         data-comic-title="' . htmlspecialchars($row['comic_title'] ?? 'N/A') . '"';
    $output .= '         data-years="' . htmlspecialchars($row['years'] ?? 'N/A') . '"';
    $output .= '         data-issue-number="' . htmlspecialchars($issue) . '"';
    $output .= '         data-condition="' . $condition . '"';
    $output .= '         data-graded="' . $graded . '"';
    $output .= '         data-price="' . $price . '">';
    $output .= '    <div class="card-body p-2">';
    $output .= '      <p class="card-text small mb-1 text-center">Issue: ' . htmlspecialchars($issue) . '</p>';
    $output .= '      <p class="card-text small mb-1 text-center">Condition: ' . $condition . '</p>';
    $output .= '      <p class="card-text small mb-1 text-center">Graded: ' . $graded . '</p>';
    $output .= '      <p class="card-text small mb-1 text-center">Price: ' . $price . '</p>';
    $output .= '    </div>';
    $output .= '  </div>';
    $output .= '</div>';
}
$output .= '</div>';

$stmt->close();
$conn->close();

echo $output;
?>

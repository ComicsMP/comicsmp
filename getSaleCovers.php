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

$user_id = $_SESSION['user_id'];

// Get user's currency from the users table
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
    $currency = ''; // default if not set
}

// Get GET parameters for sale covers
$comic_title = $_GET['comic_title'] ?? '';
$years = $_GET['years'] ?? '';
$issue_urls_str = $_GET['issue_urls'] ?? '';

if (empty($comic_title) || empty($years) || empty($issue_urls_str)) {
    echo "Error: Missing parameters.";
    exit;
}

// Convert the comma-separated Issue_URLs into an array and prepare placeholders
$issue_urls = array_map('trim', explode(',', $issue_urls_str));
$placeholders = implode(',', array_fill(0, count($issue_urls), '?'));

// Use the Date from the comics table by joining and using backticks.
$sql = "
    SELECT s.id AS listing_id, s.Image_Path, s.Issue_Number, s.comic_condition, s.price, s.graded,
           c.Tab, c.Variant, c.`Date` AS comic_date, s.Issue_URL AS issue_url
    FROM comics_for_sale s
    LEFT JOIN comics c ON s.Issue_URL = c.Issue_URL
    WHERE s.Comic_Title = ? AND s.Years = ? AND s.Issue_URL IN ($placeholders)
    GROUP BY s.Issue_URL
    ORDER BY LENGTH(s.Issue_Number), s.Issue_Number ASC
";

$stmt = $conn->prepare($sql);
if(!$stmt) {
    echo "DB Error: " . $conn->error;
    exit;
}
$types = 'ss' . str_repeat('s', count($issue_urls));
$params = array_merge([$comic_title, $years], $issue_urls);

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$output = '<div class="d-flex flex-wrap justify-content-center">';
while ($row = $result->fetch_assoc()) {
    // Determine image path.
    $rawPath = trim($row['Image_Path'] ?? '');
    if(empty($rawPath) || strtolower($rawPath) === 'null') {
        $imgPath = '/comicsmp/placeholder.jpg';
    } elseif(filter_var($rawPath, FILTER_VALIDATE_URL)) {
        $imgPath = $rawPath;
    } elseif(strpos($rawPath, '/comicsmp/images/') === 0) {
        $imgPath = $rawPath;
    } elseif(strpos($rawPath, '/images/') === 0) {
        $imgPath = '/comicsmp' . $rawPath;
    } elseif(strpos($rawPath, 'images/') === 0) {
        $imgPath = '/comicsmp/' . $rawPath;
    } else {
        $imgPath = '/comicsmp/images/' . $rawPath;
    }
    $issue = trim($row['Issue_Number'] ?? '');
    if(!empty($issue) && strpos($issue, '#') !== 0) {
        $issue = '#' . $issue;
    }
    $tab = htmlspecialchars($row['Tab'] ?? 'N/A');
    $variant = htmlspecialchars($row['Variant'] ?? 'N/A');
    $comic_date = htmlspecialchars($row['comic_date'] ?? 'N/A');
    $issue_url = htmlspecialchars($row['issue_url'] ?? '');
    
    // Retrieve additional sale details
    $listing_id = htmlspecialchars($row['listing_id'] ?? '');
    $condition = htmlspecialchars($row['comic_condition'] ?? '');
    // Convert the price value to a float and format it to 2 decimals.
    $price_val = floatval($row['price'] ?? 0);
    $priceFormatted = number_format($price_val, 2);
    $graded = htmlspecialchars($row['graded'] ?? '');
    $gradedText = ($graded == "1") ? "Yes" : "No";
    
    // Output the cover with data attributes (including price with currency)
    $output .= '<div class="position-relative m-2 cover-wrapper" style="width: 150px;" 
                 data-comic-title="' . htmlspecialchars($comic_title) . '" 
                 data-years="' . htmlspecialchars($years) . '" 
                 data-issue-number="' . htmlspecialchars($issue) . '" 
                 data-tab="' . $tab . '" 
                 data-variant="' . $variant . '"
                 data-issue_url="' . $issue_url . '"
                 data-date="' . $comic_date . '"
                 data-condition="' . $condition . '"
                 data-graded="' . $gradedText . '"
                 data-price="$' . $priceFormatted . ' ' . htmlspecialchars($currency) . '">';
    
    // Add cover image
    $output .= '<img src="' . htmlspecialchars($imgPath) . '" alt="Issue ' . htmlspecialchars($issue) . '" class="cover-img popup-trigger" style="width: 150px; height: 225px; cursor: pointer;">';
    
    // Add Edit and Delete icons at top right for sale items
    $output .= '<button class="edit-sale" style="position: absolute; top: 2px; right: 26px; background: rgba(0,123,255,0.8); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; cursor: pointer; line-height: 18px; text-align: center;" data-listing-id="' . $listing_id . '" data-price="$' . $priceFormatted . ' ' . htmlspecialchars($currency) . '" data-condition="' . $condition . '" data-graded="' . $graded . '">E</button>';
    $output .= '<button class="remove-sale" style="position: absolute; top: 2px; right: 2px; background: rgba(255,0,0,0.8); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 12px; cursor: pointer; line-height: 18px; text-align: center;" data-listing-id="' . $listing_id . '">&times;</button>';
    
    // Display additional details under the cover, with currency appended to price.
    $output .= '<div class="text-center small">Issue: ' . htmlspecialchars($issue) . '</div>';
    $output .= '<div class="text-center small">Condition: ' . $condition . '</div>';
    $output .= '<div class="text-center small">Graded: ' . $gradedText . '</div>';
    $output .= '<div class="text-center small">Price: $' . $priceFormatted . ' ' . htmlspecialchars($currency) . '</div>';
    
    $output .= '</div>';
}
$output .= '</div>';

$stmt->close();
$conn->close();
echo $output;
?>

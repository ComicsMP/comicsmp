<?php 
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once 'db_connection.php';

$current_user = $_SESSION['user_id'] ?? 0;

$context = $_GET['context'] ?? ''; // 'buy' or 'sell'
$other_user_id = $_GET['other_user_id'] ?? '';
$main_cover_url = $_GET['main_cover_url'] ?? ''; // The cover image to exclude

if (empty($context) || empty($other_user_id)) {
    echo "";
    exit;
}

// Normalize the main cover URL so comparison works properly
$normalizedMainCover = trim($main_cover_url);
if (!empty($normalizedMainCover) && empty(pathinfo($normalizedMainCover, PATHINFO_EXTENSION))) {
    $normalizedMainCover .= '.jpg';
}

if ($context === 'buy') {
    // Fetch ALL comics available from this seller (no limit)
    $sql = "SELECT 
                cs.Issue_Number, 
                cs.Image_Path, 
                cs.comic_condition, 
                cs.graded, 
                cs.price, 
                c.Tab, 
                c.Variant, 
                c.`Date`,
                cs.Comic_Title
            FROM comics_for_sale cs
            LEFT JOIN comics c ON (c.Issue_URL = cs.Issue_URL)
            WHERE cs.user_id = ?
            ORDER BY cs.Comic_Title ASC, cs.Issue_Number ASC";
    $stmt = $conn->prepare($sql);
    if(!$stmt){
        echo "";
        exit;
    }
    $stmt->bind_param("i", $other_user_id);
} else if ($context === 'sell') {
    // For the "sell" context:
    // Return only match notifications where:
    // 1. The seller (current user) still has an active listing.
    // 2. The buyer still has an active wanted listing for that comic.
    // 3. Retrieve the seller's currency from the users table.
    $sql = "SELECT 
                mn.issue_number, 
                mn.cover_image,
                mn.issue_url,
                mn.comic_title,
                cs.comic_condition,
                cs.graded,
                cs.price,
                u.currency,
                c.Tab,
                c.Variant,
                c.`Date`
            FROM match_notifications mn
            INNER JOIN comics_for_sale cs 
                ON cs.issue_url = mn.issue_url 
                AND cs.user_id = mn.seller_id
            INNER JOIN users u
                ON u.id = mn.seller_id
            INNER JOIN wanted_items w 
                ON w.user_id = mn.buyer_id 
                AND LOWER(TRIM(w.comic_title)) = LOWER(TRIM(mn.comic_title))
                AND LOWER(TRIM(w.issue_number)) = LOWER(TRIM(mn.issue_number))
                AND LOWER(TRIM(w.years)) = LOWER(TRIM(mn.years))
            LEFT JOIN comics c 
                ON c.Comic_Title = mn.comic_title 
                AND c.Years = mn.years 
                AND c.Issue_Number = mn.issue_number
            WHERE mn.seller_id = ? 
              AND mn.buyer_id = ?
            ORDER BY mn.comic_title ASC, mn.issue_number ASC";
    
    $stmt = $conn->prepare($sql);
    if(!$stmt){
        echo "";
        exit;
    }
    $stmt->bind_param("ii", $current_user, $other_user_id);
} else {
    echo "";
    exit;
}

$stmt->execute();
$result = $stmt->get_result();

$output = '';
$match_count = 0;

while ($row = $result->fetch_assoc()){
    // Normalize issue number
    $issue = trim($row['Issue_Number'] ?? $row['issue_number']);
    if (!empty($issue) && strpos($issue, '#') !== 0) {
        $issue = '#' . $issue;
    }

    // Get the raw cover image path (from either field)
    $rawPath = trim($row['Image_Path'] ?? $row['cover_image'] ?? '');
    // If cover image is empty (after trimming) or equals 'null', skip this record.
    if ($rawPath === '' || strtolower($rawPath) === 'null') {
        continue;
    }
    
    // Normalize cover image path
    $rawPath = str_replace('/images/images/', '/images/', $rawPath);
    $rawPath = preg_replace('#^(images/){2,}#i', 'images/', $rawPath);
    if (filter_var($rawPath, FILTER_VALIDATE_URL)) {
        $imgPath = $rawPath;
    } elseif (strpos($rawPath, '/comicsmp/images/') === 0) {
        $imgPath = $rawPath;
    } elseif (strpos($rawPath, 'images/') === 0) {
        $imgPath = '/comicsmp/' . $rawPath;
    } else {
        $imgPath = '/comicsmp/images/' . ltrim($rawPath, '/');
    }
    if (empty(pathinfo($imgPath, PATHINFO_EXTENSION))) {
        $imgPath .= '.jpg';
    }

    // If a main cover was provided, skip if it matches (case-insensitive)
    if (!empty($normalizedMainCover) && strcasecmp($imgPath, $normalizedMainCover) == 0) {
        continue;
    }
    
    // Extra check: if the resulting image path equals the placeholder, skip it.
    if ($imgPath === '/comicsmp/placeholder.jpg') {
        continue;
    }

    // Normalize comic title
    $comicTitle = $row['Comic_Title'] ?? $row['comic_title'];

    // Prepare additional fields
    $comicCondition = $row['comic_condition'] ?? 'N/A';
    $graded = ($row['graded'] == "1") ? "Yes" : "No";
    
    // Format price: include the dollar sign and currency from the seller's account.
    if (!empty($row['price'])) {
        $price = "$" . htmlspecialchars($row['price']) . " " . htmlspecialchars($row['currency'] ?? "USD");
    } else {
        $price = "N/A";
    }

    $output .= "<img src='" . htmlspecialchars($imgPath) . "' alt='Issue $issue' class='similar-issue-thumb'
                data-comic-title='" . htmlspecialchars($comicTitle) . "' 
                data-years='" . htmlspecialchars($row['Date'] ?? 'N/A') . "' 
                data-issue-number='" . htmlspecialchars($issue) . "' 
                data-tab='" . htmlspecialchars($row['Tab'] ?? 'N/A') . "' 
                data-variant='" . htmlspecialchars($row['Variant'] ?? 'N/A') . "' 
                data-date='" . htmlspecialchars($row['Date'] ?? 'N/A') . "'
                data-condition='" . htmlspecialchars($comicCondition) . "'
                data-graded='" . htmlspecialchars($graded) . "'
                data-price='" . htmlspecialchars($price) . "'
                style='width:80px; height:120px; object-fit:cover; margin:5px; cursor:pointer;'>";

    $match_count++;
}

// If there are no matches, display a message
if ($match_count === 0) {
    $output .= "<p class='text-muted'>No other matched comics available.</p>";
}

$stmt->close();
$conn->close();
echo $output;
?>

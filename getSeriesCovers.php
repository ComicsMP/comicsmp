<?php
session_start();
require_once 'db_connection.php';

$user_id    = $_SESSION['user_id'] ?? 0;
$comic_title= $_GET['comic_title'] ?? '';
$years      = $_GET['years'] ?? '';
$issue_urls_str = $_GET['issue_urls'] ?? '';

if (!$user_id || !$comic_title || !$years || empty($issue_urls_str)) {
    echo "Invalid parameters";
    exit;
}

$issue_urls = array_map('trim', explode(',', $issue_urls_str));
$placeholders = implode(',', array_fill(0, count($issue_urls), '?'));

// Force a '#' prefix for sorting: if the trimmed Issue_Number does not start with '#',
// we prepend it. Then extract the numeric portion.
$sql = "
    SELECT c.Image_Path, c.Issue_Number, c.Variant, c.Tab, c.`Date` AS comic_date, c.Issue_URL AS issue_url, c.UPC
    FROM Comics c
    WHERE c.Comic_Title = ? AND c.Years = ? AND c.Issue_URL IN ($placeholders)
    ORDER BY 
      CAST(
        REGEXP_SUBSTR(
          IF(LEFT(TRIM(c.Issue_Number),1) = '#', TRIM(c.Issue_Number), CONCAT('#', TRIM(c.Issue_Number))),
          '^[#]*([0-9]+)'
        ) AS UNSIGNED
      ) ASC,
      IF(LEFT(TRIM(c.Issue_Number),1) = '#', TRIM(c.Issue_Number), CONCAT('#', TRIM(c.Issue_Number))) COLLATE utf8mb4_bin ASC
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
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
    // Normalize and determine image path.
    $rawPath = trim($row['Image_Path'] ?? '');
    if (empty($rawPath) || strtolower($rawPath) === 'null') {
        $imgPath = '/comicsmp/placeholder.jpg';
    } elseif (filter_var($rawPath, FILTER_VALIDATE_URL)) {
        $imgPath = $rawPath;
    } elseif (strpos($rawPath, '/comicsmp/images/') === 0) {
        $imgPath = $rawPath;
    } elseif (strpos($rawPath, '/images/') === 0) {
        $imgPath = '/comicsmp' . $rawPath;
    } elseif (strpos($rawPath, 'images/') === 0) {
        $imgPath = '/comicsmp/' . $rawPath;
    } else {
        $imgPath = '/comicsmp/images/' . $rawPath;
    }
    
    // Process Issue_Number: trim and force a '#' prefix for display.
    $issue_raw = trim($row['Issue_Number'] ?? '');
    $issue = (strpos($issue_raw, '#') === 0) ? $issue_raw : '#' . $issue_raw;
    
    $tab = htmlspecialchars($row['Tab'] ?? 'N/A');
    $variant = htmlspecialchars($row['Variant'] ?? 'N/A');
    $comic_date = htmlspecialchars($row['comic_date'] ?? 'N/A');
    $issue_url = htmlspecialchars($row['issue_url'] ?? '');
    $upc = htmlspecialchars($row['UPC'] ?? 'N/A'); // ✅ Added UPC field

    $output .= '<div class="position-relative m-2 cover-wrapper" style="width: 150px;" 
                 data-comic-title="' . htmlspecialchars($comic_title) . '" 
                 data-years="' . htmlspecialchars($years) . '" 
                 data-issue-number="' . htmlspecialchars($issue) . '" 
                 data-tab="' . $tab . '" 
                 data-variant="' . $variant . '"
                 data-issue_url="' . $issue_url . '"
                 data-date="' . $comic_date . '"
                 data-upc="' . $upc . '">';  // ✅ Added UPC as a data attribute
    $output .= '<img src="' . htmlspecialchars($imgPath) . '" alt="Issue ' . htmlspecialchars($issue) . '" class="cover-img popup-trigger" style="width: 150px; height: 225px; cursor: pointer;">';
    $output .= '<button class="remove-cover" style="position: absolute; top: 5px; right: 5px; background: rgba(255,0,0,0.8); color: white; border: none; border-radius: 50%; width: 20px; height: 20px; font-size: 14px; cursor: pointer; line-height: 18px; text-align: center;" data-comic-title="' . htmlspecialchars($comic_title) . '" data-issue-number="' . htmlspecialchars($issue) . '" data-years="' . htmlspecialchars($years) . '" data-issue_url="' . $issue_url . '">&times;</button>';
    $output .= '<div class="text-center small">Issue: ' . htmlspecialchars($issue) . '</div>';
    $output .= '<div class="text-center small">Tab: ' . $tab . '</div>';
    $output .= '</div>';
}
$output .= '</div>';

$stmt->close();
$conn->close();
echo $output;
?>

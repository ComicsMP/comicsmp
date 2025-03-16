<?php
session_start();
require_once 'db_connection.php';

// Retrieve GET parameters
$comic_title   = $_GET['comic_title'] ?? '';
$years         = $_GET['years'] ?? '';
$issue_number  = $_GET['issue_number'] ?? '';
$isSale        = isset($_GET['sale']) && $_GET['sale'] == '1';

if (empty($comic_title) || empty($years)) {
    echo "";
    exit;
}

if ($isSale) {
    // For sale items, join with comics_for_sale to fetch extra fields.
    $sql = "
      SELECT s.Issue_Number, s.Image_Path, s.comic_condition, s.price, s.graded,
             c.Tab, c.Variant, c.`Date` AS comic_date
      FROM comics_for_sale s
      LEFT JOIN comics c ON s.Issue_URL = c.Issue_URL
      WHERE s.Comic_Title = ? AND s.Years = ? AND s.Issue_Number <> ?
      ORDER BY s.Issue_Number ASC
      LIMIT 4
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $comic_title, $years, $issue_number);
} else {
    // For non-sale items, query the comics table.
    $sql = "
      SELECT Issue_Number, Image_Path, Tab, Variant, `Date`
      FROM comics
      WHERE Comic_Title = ? AND Years = ? AND Issue_Number <> ?
      ORDER BY Issue_Number ASC
      LIMIT 4
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $comic_title, $years, $issue_number);
}

$stmt->execute();
$result = $stmt->get_result();

$output = '';

while ($row = $result->fetch_assoc()) {
    // Ensure Issue_Number has a '#' prefix.
    $issue = trim($row['Issue_Number']);
    if (!empty($issue) && strpos($issue, '#') !== 0) {
        $issue = '#' . $issue;
    }
    
    // Determine image path
    $rawPath = trim($row['Image_Path'] ?? '');
    if (empty($rawPath) || strtolower($rawPath) === 'null') {
        $imgPath = '/comicsmp/placeholder.jpg';
    } elseif (filter_var($rawPath, FILTER_VALIDATE_URL)) {
        $imgPath = $rawPath;
    } else {
        $imgPath = '/comicsmp/' . ltrim($rawPath, '/');
    }
    
    // For sale items, get condition, graded, price; otherwise default to "N/A"
    if ($isSale) {
        $condition = htmlspecialchars($row['comic_condition'] ?? 'N/A');
        $price = htmlspecialchars($row['price'] ?? 'N/A');
        $gradedVal = $row['graded'] ?? 0;
        $graded = ($gradedVal == "1") ? "Yes" : "No";
    } else {
        $condition = "N/A";
        $price = "N/A";
        $graded = "N/A";
    }
    
    // Use Tab, Variant, and Date (or comic_date if sale)
    $tab = htmlspecialchars($row['Tab'] ?? 'N/A');
    $variant = htmlspecialchars($row['Variant'] ?? 'N/A');
    $comic_date = htmlspecialchars(($isSale ? $row['comic_date'] : $row['Date']) ?? 'N/A');
    
    // Build the thumbnail with extra data attributes
    $output .= "<img src='" . htmlspecialchars($imgPath) . "' alt='Issue $issue' class='similar-issue-thumb'
                data-comic-title='" . htmlspecialchars($comic_title) . "'
                data-years='" . htmlspecialchars($years) . "'
                data-issue-number='" . htmlspecialchars($issue) . "'
                data-tab='$tab'
                data-variant='$variant'
                data-date='$comic_date'
                data-condition='$condition'
                data-graded='$graded'
                data-price='$price'
                style='width:80px; height:120px; object-fit:cover; margin:5px; cursor:pointer;'>";
}

$stmt->close();
$conn->close();

echo $output;
?>

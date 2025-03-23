<?php
// getMatchComicDetails.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'db_connection.php';

// Retrieve GET parameters
$comic_title = $_GET['comic_title'] ?? '';
$years = $_GET['years'] ?? '';
$issue_number = $_GET['issue_number'] ?? '';

if (empty($comic_title) || empty($years) || empty($issue_number)) {
    echo json_encode([]);
    exit;
}

// Query the comics table for the details, including UPC
$sql = "SELECT Tab, Variant, `Date`, UPC AS upc FROM comics WHERE Comic_Title = ? AND Years = ? AND Issue_Number = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $comic_title, $years, $issue_number);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();
$stmt->close();
$conn->close();

echo json_encode($data);
?>


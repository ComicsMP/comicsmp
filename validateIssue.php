<?php
include 'db_connection.php';

$series = $_GET['series'];
$year = $_GET['year'];
$issue = $_GET['issue'];

$sql = "SELECT COUNT(*) AS count FROM Comics WHERE Series_Name = ? AND Series_Year = ? AND Series_Issue = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $series, $year, $issue);
$stmt->execute();
$result = $stmt->get_result();
$data = $result->fetch_assoc();

if ($data['count'] > 0) {
    echo json_encode(['valid' => true]);
} else {
    echo json_encode(['valid' => false, 'message' => 'Issue number not found for the selected series and year.']);
}
?>

<?php
include 'db_connection.php';

$series_name = $_GET['series_name'] ?? null;
$series_year = $_GET['series_year'] ?? null;

if (!$series_name || !$series_year) {
    echo json_encode([]);
    exit;
}

try {
    $query = "SELECT id, issue_number FROM wanted_items WHERE series_name = ? AND series_year = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $series_name, $series_year);
    $stmt->execute();
    $result = $stmt->get_result();

    $issues = [];
    while ($row = $result->fetch_assoc()) {
        $issues[] = $row;
    }

    echo json_encode($issues);
} catch (Exception $e) {
    echo json_encode([]);
}

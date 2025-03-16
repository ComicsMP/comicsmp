<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['upc']) || empty(trim($_GET['upc']))) {
    echo json_encode(['success' => false, 'message' => 'No UPC provided']);
    exit;
}

$upc = trim($_GET['upc']);

// Adjust the query and table/column names according to your database schema
$query = "SELECT comic_title, volume, issue_number, image_path FROM comics WHERE upc = ?";
if($stmt = $conn->prepare($query)) {
    $stmt->bind_param("s", $upc);
    $stmt->execute();
    $result = $stmt->get_result();
    if($result->num_rows > 0) {
        $comic = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'comic_title' => $comic['comic_title'],
            'volume' => $comic['volume'],
            'issue_number' => $comic['issue_number'],
            'image_path' => $comic['image_path']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Comic not found']);
    }
    $stmt->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}

$conn->close();
?>

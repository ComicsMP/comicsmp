<?php
session_start();
require_once 'db_connection.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
$listing_id = $_POST['listing_id'] ?? '';
$price = $_POST['price'] ?? '';
$condition = $_POST['condition'] ?? '';

if (!$user_id || empty($listing_id) || $price === '' || empty($condition)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters or not logged in.']);
    exit;
}

$sql = "UPDATE comics_for_sale SET price = ?, comic_condition = ? WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
if(!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'SQL prepare failed: ' . $conn->error]);
    exit;
}
$stmt->bind_param("dsii", $price, $condition, $listing_id, $user_id);
$stmt->execute();

if ($stmt->affected_rows >= 0) {
    echo json_encode(['status' => 'success', 'message' => 'Sale listing updated successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No changes made or update failed.']);
}
$stmt->close();
$conn->close();
?>

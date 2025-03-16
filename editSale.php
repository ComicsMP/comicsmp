<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
$listing_id = $_POST['listing_id'] ?? 0;
$price = $_POST['price'] ?? 0;
$condition = $_POST['condition'] ?? '';

if (!$user_id || !$listing_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

try {
    $sql = "UPDATE comics_for_sale SET price = ?, comic_condition = ? WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dsii", $price, $condition, $listing_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Sale listing updated.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No changes made.']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>

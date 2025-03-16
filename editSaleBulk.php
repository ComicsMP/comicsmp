<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
$comic_title = $_POST['comic_title'] ?? '';
$years = $_POST['years'] ?? '';
$price = $_POST['price'] ?? 0;
$condition = $_POST['condition'] ?? '';

if (!$user_id || empty($comic_title) || empty($years)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

try {
    $sql = "UPDATE comics_for_sale 
            SET price = ?, comic_condition = ? 
            WHERE user_id = ? AND comic_title = ? AND years = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("dsiss", $price, $condition, $user_id, $comic_title, $years);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Bulk update successful.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No changes made or no matching records.']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>

<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
$listing_id = $_POST['listing_id'] ?? 0;

if (!$user_id || !$listing_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid sale ID or not logged in.']);
    exit;
}

try {
    $sql = "DELETE FROM comics_for_sale WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $listing_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Sale listing deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Sale not found or permission denied.']);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>

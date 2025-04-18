<?php
session_start();
require_once 'db_connection.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
$listing_id = $_POST['listing_id'] ?? '';

if (!$user_id || empty($listing_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid sale ID or not logged in.']);
    exit;
}

try {
    // Delete only the single sale listing from comics_for_sale.
    $stmt = $conn->prepare("DELETE FROM comics_for_sale WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $listing_id, $user_id);
    $stmt->execute();
    $affectedRows = $stmt->affected_rows;
    $stmt->close();

    if ($affectedRows > 0) {
        echo json_encode(['status' => 'success', 'message' => 'Sale listing deleted successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Sale not found or permission denied.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>

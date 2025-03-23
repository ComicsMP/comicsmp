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
    // First, get the comic details to identify related matches
    $stmt = $conn->prepare("SELECT comic_title, issue_number, years, issue_url FROM comics_for_sale WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $listing_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comic = $result->fetch_assoc();
    $stmt->close();

    if (!$comic) {
        echo json_encode(['status' => 'error', 'message' => 'Sale not found or permission denied.']);
        exit;
    }

    // Delete the sale listing
    $stmt = $conn->prepare("DELETE FROM comics_for_sale WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $listing_id, $user_id);
    $stmt->execute();
    $stmt->close();

    // Delete related match_notifications
    $stmt = $conn->prepare("DELETE FROM match_notifications WHERE comic_title = ? AND issue_number = ? AND years = ? AND issue_url = ?");
    $stmt->bind_param("ssss", $comic['comic_title'], $comic['issue_number'], $comic['years'], $comic['issue_url']);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'message' => 'Sale listing and matches deleted successfully.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Delete failed: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>

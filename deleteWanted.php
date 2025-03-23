<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $comic_title = $_POST['comic_title'] ?? '';
    $issue_number = $_POST['issue_number'] ?? '';
    $years = $_POST['years'] ?? '';
    $issue_url = $_POST['issue_url'] ?? '';

    if (!$user_id || !$comic_title || !$issue_number || !$years || !$issue_url) {
        echo json_encode(["status" => "error", "message" => "Invalid parameters or not logged in"]);
        exit;
    }

    // First, delete the wanted item
    $stmt = $conn->prepare("DELETE FROM wanted_items WHERE user_id = ? AND comic_title = ? AND issue_number = ? AND years = ? AND issue_url = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "DB Error: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("issss", $user_id, $comic_title, $issue_number, $years, $issue_url);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    // If deleted, also remove any matching notifications
    if ($deleted) {
        $stmt = $conn->prepare("DELETE FROM match_notifications WHERE comic_title = ? AND issue_number = ? AND years = ? AND issue_url = ?");
        $stmt->bind_param("ssss", $comic_title, $issue_number, $years, $issue_url);
        $stmt->execute();
        $stmt->close();

        echo json_encode(["status" => "success", "message" => "Wanted item and related matches deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Deletion failed, item not found"]);
    }

    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>


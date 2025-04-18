<?php
session_start();
require_once '../db_connection.php';

// Set JSON header
header('Content-Type: application/json');

// Check if the user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["status" => "error", "message" => "Forbidden"]);
    exit;
}

$userId = $_SESSION['user_id'];

// Check if match_user_id is provided.
if (!isset($_POST['match_user_id']) || empty($_POST['match_user_id'])) {
    echo json_encode(["status" => "error", "message" => "Missing match_user_id parameter."]);
    exit;
}

$matchUserId = intval($_POST['match_user_id']);

// Insert a record into hidden_matches table if it doesn't already exist.
// You can use INSERT IGNORE if you want to prevent duplicate entries.
$sql = "INSERT IGNORE INTO hidden_matches (user_id, match_user_id) VALUES (?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(["status" => "error", "message" => "Database error: " . $conn->error]);
    exit;
}
$stmt->bind_param("ii", $userId, $matchUserId);
if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Match deleted successfully."]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to delete match."]);
}
$stmt->close();
$conn->close();
?>

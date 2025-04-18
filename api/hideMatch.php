<?php
session_start();
require_once '../db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
    exit;
}

$userId = $_SESSION['user_id'];
$matchUserId = $_POST['match_user_id'] ?? null;

if (!$matchUserId) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid match ID']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO hidden_matches (user_id, match_user_id) VALUES (?, ?)");
$stmt->bind_param("ii", $userId, $matchUserId);
if ($stmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
$stmt->close();
?>

<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg_id = $_POST['msg_id'] ?? 0;

if (!$msg_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid message ID']);
    exit;
}

// Mark message as read
$sql = "UPDATE private_messages SET is_read = 1 WHERE id = ? AND recipient_id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $msg_id, $user_id);
$stmt->execute();
$stmt->close();
$conn->close();

echo json_encode(['status' => 'success']);
?>
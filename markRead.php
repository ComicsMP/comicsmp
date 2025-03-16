<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo "Not logged in";
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = isset($_POST['conversation_id']) ? trim($_POST['conversation_id']) : '';

if (empty($conversation_id)) {
    echo "Missing conversation ID";
    exit;
}

// Update messages in the given conversation for the current user.
$sql = "UPDATE private_messages SET is_read = 1 WHERE conversation_id = ? AND recipient_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "DB error: " . $conn->error;
    exit;
}

// Assuming conversation_id is stored as a string
$stmt->bind_param("si", $conversation_id, $user_id);
$stmt->execute();
$stmt->close();
$conn->close();

echo "success";
?>

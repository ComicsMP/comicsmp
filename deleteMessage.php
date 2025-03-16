<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$msg_id = $_GET['msg_id'] ?? 0;

if (!$msg_id) {
    header("Location: messages.php");
    exit;
}

// Ensure the user owns the message before deleting
$sql = "DELETE FROM private_messages WHERE id = ? AND (sender_id = ? OR recipient_id = ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iii", $msg_id, $user_id, $user_id);
$stmt->execute();
$stmt->close();
$conn->close();

header("Location: messages.php");
exit;
?>
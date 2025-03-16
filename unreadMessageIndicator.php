<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch unread message count
$sql = "SELECT COUNT(*) AS unread_count FROM private_messages WHERE recipient_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$unread = $result->fetch_assoc()['unread_count'] ?? 0;
$stmt->close();
$conn->close();

echo json_encode(['unread' => $unread]);
?>

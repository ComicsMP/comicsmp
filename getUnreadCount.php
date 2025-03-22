<?php
session_start();
require_once 'db_connection.php';
header('Content-Type: application/json; charset=utf-8');

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    echo json_encode(["unread_count" => 0]);
    exit;
}

$sql = "SELECT COUNT(*) AS unread_count FROM private_messages WHERE recipient_id = ? AND is_read = 0";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$unread_count = $row['unread_count'] ?? 0;

$stmt->close();
$conn->close();

echo json_encode(["unread_count" => $unread_count]);
?>

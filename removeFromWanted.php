<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $comic_title = $_POST['comic_title'] ?? '';
    $issue_number = $_POST['issue_number'] ?? '';
    $years = $_POST['years'] ?? '';

    if (!$user_id || !$comic_title || !$issue_number || !$years) {
        http_response_code(400);
        echo "Invalid parameters";
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM wanted_items WHERE user_id = ? AND comic_title = ? AND issue_number = ? AND years = ?");
    $stmt->bind_param("isss", $user_id, $comic_title, $issue_number, $years);
    if ($stmt->execute()) {
        echo "Removed";
    } else {
        http_response_code(500);
        echo "Error";
    }
    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo "Method not allowed";
}
?>

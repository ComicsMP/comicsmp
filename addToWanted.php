<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $comic_title = $_POST['comic_title'] ?? '';
    $issue_number = $_POST['issue_number'] ?? '';
    $years = $_POST['years'] ?? '';
    $issue_url = $_POST['issue_url'] ?? '';  // Required parameter

    if (!$user_id || !$comic_title || !$issue_number || !$years || !$issue_url) {
        http_response_code(400);
        echo "Invalid parameters";
        exit;
    }

    // Check if the comic is already in the wanted list.
    $stmt = $conn->prepare("SELECT id FROM wanted_items WHERE user_id = ? AND comic_title = ? AND issue_number = ? AND years = ? AND issue_url = ?");
    $stmt->bind_param("issss", $user_id, $comic_title, $issue_number, $years, $issue_url);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        // Already exists; return success.
        echo "Already added";
        exit;
    }
    $stmt->close();

    // Insert the comic into wanted_items.
    $stmt = $conn->prepare("INSERT INTO wanted_items (user_id, comic_title, issue_number, years, issue_url) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $comic_title, $issue_number, $years, $issue_url);
    if ($stmt->execute()) {
        echo "Added";
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

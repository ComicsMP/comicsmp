<?php
session_start();
header('Content-Type: application/json');

require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$userId     = $_SESSION['user_id'];
$comicTitle = trim($_POST['comic_title'] ?? '');
$years      = trim($_POST['years'] ?? '');
$country    = trim($_POST['country'] ?? '');
$action     = $_POST['action'] ?? '';

$country = trim($_POST['country'] ?? '');

if (!$comicTitle || !$years || !in_array($action, ['add', 'remove'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
    exit;
}

if ($action === 'add') {
    $stmt = $conn->prepare("
        INSERT IGNORE INTO user_favorite_titles
            (user_id, comic_title, years, country)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->bind_param("isss", $userId, $comicTitle, $years, $country);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Favorited']);
} else {
    $stmt = $conn->prepare("
        DELETE FROM user_favorite_titles
         WHERE user_id     = ?
           AND comic_title = ?
           AND years       = ?
           AND country     = ?
    ");
    $stmt->bind_param("isss", $userId, $comicTitle, $years, $country);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['status' => 'success', 'message' => 'Unfavorited']);
}

$conn->close();

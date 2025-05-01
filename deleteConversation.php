<?php
session_start();
header('Content-Type: application/json');
require_once 'db_connection.php';

// ✅ TEMP DEBUG: Log full POST data
file_put_contents("debug_delete.log", print_r($_POST, true), FILE_APPEND);

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
$conversationId = $_POST['conversation_id'] ?? null;

if (!$conversationId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing conversation ID']);
    exit;
}

// ✅ Verify user is part of conversation
$stmt = $conn->prepare("SELECT id FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)");
$stmt->bind_param("iii", $conversationId, $userId, $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'Conversation not found']);
    exit;
}
$stmt->close();

// ✅ Soft delete: Add user ID to deleted_for_user column (CSV logic)
$stmt = $conn->prepare("
    UPDATE private_messages 
    SET deleted_for_user = 
        CASE 
            WHEN deleted_for_user IS NULL OR deleted_for_user = '' 
                THEN ?
            WHEN NOT FIND_IN_SET(?, deleted_for_user) 
                THEN CONCAT(deleted_for_user, ',', ?)
            ELSE deleted_for_user
        END
    WHERE conversation_id = ?
");
$stmt->bind_param("sssi", $userId, $userId, $userId, $conversationId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Nothing changed.']);
}

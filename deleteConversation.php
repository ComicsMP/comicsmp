<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: messages.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$conversation_id = $_POST['conversation_id'] ?? 0;

if (!$conversation_id) {
    header("Location: messages.php");
    exit;
}

// Retrieve the current deleted_for_user list
$sqlFetch = "SELECT deleted_for_user FROM private_messages WHERE conversation_id = ? LIMIT 1";
$stmtFetch = $conn->prepare($sqlFetch);
$stmtFetch->bind_param("i", $conversation_id);
$stmtFetch->execute();
$resultFetch = $stmtFetch->get_result();
$row = $resultFetch->fetch_assoc();
$stmtFetch->close();

$deletedUsers = [];
if (!empty($row['deleted_for_user'])) {
    $deletedUsers = explode(',', $row['deleted_for_user']);
}

// If the user hasn't already deleted the conversation, add them to the list
if (!in_array($user_id, $deletedUsers)) {
    $deletedUsers[] = $user_id;
}

// Convert the array back to a string
$deletedUsersString = implode(',', $deletedUsers);

// Update only for the current user
$sqlUpdate = "UPDATE private_messages SET deleted_for_user = ? WHERE conversation_id = ?";
$stmtUpdate = $conn->prepare($sqlUpdate);
$stmtUpdate->bind_param("si", $deletedUsersString, $conversation_id);
$stmtUpdate->execute();
$stmtUpdate->close();

// Check if BOTH users deleted the conversation
$sqlCheck = "SELECT COUNT(*) AS remaining FROM private_messages WHERE conversation_id = ? AND (deleted_for_user IS NULL OR NOT FIND_IN_SET(sender_id, deleted_for_user) OR NOT FIND_IN_SET(recipient_id, deleted_for_user))";
$stmtCheck = $conn->prepare($sqlCheck);
$stmtCheck->bind_param("i", $conversation_id);
$stmtCheck->execute();
$resultCheck = $stmtCheck->get_result();
$rowCheck = $resultCheck->fetch_assoc();
$stmtCheck->close();

// If no users remain in the conversation, delete it entirely
if ($rowCheck['remaining'] == 0) {
    $sqlDelete = "DELETE FROM private_messages WHERE conversation_id = ?";
    $stmtDelete = $conn->prepare($sqlDelete);
    $stmtDelete->bind_param("i", $conversation_id);
    $stmtDelete->execute();
    $stmtDelete->close();
}

$conn->close();
header("Location: messages.php?deleted=success");
exit;

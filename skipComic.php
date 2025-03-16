<?php
session_start();
include 'db_connection.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit;
}

$user_id = $_SESSION['user_id'];
$match_id = $_POST['match_id'] ?? null;

if (!$match_id) {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}

// Insert into skipped_comics table
$insertQuery = "
    INSERT INTO skipped_comics (user_id, match_id, status) 
    VALUES (?, ?, 'skipped') 
    ON DUPLICATE KEY UPDATE status = 'skipped'
";
$stmt = $conn->prepare($insertQuery);
$stmt->bind_param('ii', $user_id, $match_id);
$stmt->execute();

// Check success
if ($stmt->affected_rows > 0 || $stmt->errno == 0) { // Ensure even updates work
    echo json_encode(["status" => "success", "message" => "Comic skipped successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to skip comic."]);
}
?>

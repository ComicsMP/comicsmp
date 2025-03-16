<?php
session_start();
include 'db_connection.php';

if (!isset($_POST['user_id'])) {
    http_response_code(400);
    echo json_encode(["error" => "User ID required"]);
    exit;
}

$user_id = $_POST['user_id'];

// Example: Undo the most recent "skipped" action for this user.
// Adjust this logic to match your undo requirements.
$query = "DELETE FROM skipped_comics 
          WHERE id = (
              SELECT id FROM (
                  SELECT id FROM skipped_comics 
                  WHERE user_id = ? 
                  ORDER BY id DESC LIMIT 1
              ) AS t
          )";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false, "error" => $conn->error]);
}
?>

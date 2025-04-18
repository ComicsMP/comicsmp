<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? '';
    $user_id = $_SESSION['user_id'] ?? 0;
    
    if (!$user_id || !$id) {
        echo json_encode(["status" => "error", "message" => "Invalid parameters or not logged in"]);
        exit;
    }
    
    // First, retrieve the wanted item details so we can delete related match notifications.
    $stmt = $conn->prepare("SELECT comic_title, issue_number, years, issue_url FROM wanted_items WHERE ID = ? AND user_id = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "DB Error (select): " . $conn->error]);
        exit;
    }
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Wanted item not found"]);
        exit;
    }
    $wantedItem = $result->fetch_assoc();
    $stmt->close();
    
    // Delete the wanted item
    $stmt = $conn->prepare("DELETE FROM wanted_items WHERE ID = ? AND user_id = ?");
    if (!$stmt) {
        echo json_encode(["status" => "error", "message" => "DB Error (delete): " . $conn->error]);
        exit;
    }
    $stmt->bind_param("ii", $id, $user_id);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();
    
    if ($deleted) {
        // Now delete any match notifications corresponding to this wanted item.
        $stmt = $conn->prepare("DELETE FROM match_notifications WHERE buyer_id = ? AND comic_title = ? AND issue_number = ? AND years = ? AND LOWER(TRIM(issue_url)) = LOWER(TRIM(?))");
        if ($stmt) {
            $stmt->bind_param("issss", $user_id, $wantedItem['comic_title'], $wantedItem['issue_number'], $wantedItem['years'], $wantedItem['issue_url']);
            $stmt->execute();
            $stmt->close();
        }
        echo json_encode(["status" => "success", "message" => "Wanted item and related match notifications deleted"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Deletion failed, item not found"]);
    }
    
    $conn->close();
} else {
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>

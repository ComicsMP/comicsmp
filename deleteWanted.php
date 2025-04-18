<?php
session_start();
require_once 'db_connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get the session user id and posted comic title.
    $user_id = $_SESSION['user_id'] ?? 0;
    $comic_title = $_POST['comic_title'] ?? '';
    // The extra parameters for single deletion.
    $issue_number = isset($_POST['issue_number']) ? $_POST['issue_number'] : '';
    $years = isset($_POST['years']) ? $_POST['years'] : '';
    $issue_url = isset($_POST['issue_url']) ? $_POST['issue_url'] : '';

    // Only require user_id and comic_title; extra parameters may be empty for bulk delete.
    if (!$user_id || !$comic_title) {
        header('Content-Type: application/json');
        echo json_encode(["status" => "error", "message" => "Invalid parameters or not logged in"]);
        exit;
    }

    // If extra parameters are provided, perform a single delete.
    if ($issue_number !== '' && $years !== '' && $issue_url !== '') {
        $stmt = $conn->prepare("DELETE FROM wanted_items WHERE user_id = ? AND comic_title = ? AND issue_number = ? AND years = ? AND issue_url = ?");
        if (!$stmt) {
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "message" => "DB Error: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("issss", $user_id, $comic_title, $issue_number, $years, $issue_url);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        if ($deleted) {
            $stmt = $conn->prepare("DELETE FROM match_notifications WHERE comic_title = ? AND issue_number = ? AND years = ? AND issue_url = ?");
            if($stmt){
                $stmt->bind_param("ssss", $comic_title, $issue_number, $years, $issue_url);
                $stmt->execute();
                $stmt->close();
            }
            header('Content-Type: application/json');
            echo json_encode(["status" => "success", "message" => "Single deletion successful: wanted item and related matches deleted"]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "message" => "Single deletion failed, item not found"]);
        }
    } else {
        // Bulk delete: Delete all wanted items for this comic title and user.
        $stmt = $conn->prepare("DELETE FROM wanted_items WHERE user_id = ? AND comic_title = ?");
        if (!$stmt) {
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "message" => "DB Error: " . $conn->error]);
            exit;
        }
        $stmt->bind_param("is", $user_id, $comic_title);
        $stmt->execute();
        $deleted = $stmt->affected_rows > 0;
        $stmt->close();

        if ($deleted) {
            $stmt = $conn->prepare("DELETE FROM match_notifications WHERE comic_title = ?");
            if ($stmt) {
                $stmt->bind_param("s", $comic_title);
                $stmt->execute();
                $stmt->close();
            }
            header('Content-Type: application/json');
            echo json_encode(["status" => "success", "message" => "Bulk delete successful: wanted series and related matches deleted"]);
        } else {
            header('Content-Type: application/json');
            echo json_encode(["status" => "error", "message" => "Bulk deletion failed, items not found"]);
        }
    }
    $conn->close();
} else {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(["status" => "error", "message" => "Method not allowed"]);
}
?>

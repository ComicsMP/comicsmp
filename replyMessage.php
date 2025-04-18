<?php
session_start();
require_once 'db_connection.php';

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
    $conversation_id = isset($_POST['conversation_id']) ? intval($_POST['conversation_id']) : 0;
    $message = trim($_POST['reply_message'] ?? '');
    $attachments = [];

    if ($recipient_id <= 0 || empty($conversation_id) || (empty($message) && empty($_FILES['attachment']['name'][0]))) {
        echo json_encode(["status" => "error", "message" => "Missing required fields."]);
        exit;
    }

    // ✅ Validate conversation participants
    $sqlValidate = "SELECT id FROM conversations 
                    WHERE id = ? AND ((user1_id = ? AND user2_id = ?) OR (user1_id = ? AND user2_id = ?))";
    $stmtValidate = $conn->prepare($sqlValidate);
    $stmtValidate->bind_param("iiiii", $conversation_id, $user_id, $recipient_id, $recipient_id, $user_id);
    $stmtValidate->execute();
    $resValidate = $stmtValidate->get_result();
    if ($resValidate->num_rows === 0) {
        echo json_encode(["status" => "error", "message" => "Invalid conversation."]);
        exit;
    }
    $stmtValidate->close();

    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    if (isset($_FILES['attachment']) && !empty($_FILES['attachment']['name'][0])) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
        foreach ($_FILES['attachment']['name'] as $key => $file_name) {
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = uniqid() . '.' . $file_ext;
            $target_file = $upload_dir . $new_file_name;

            if (in_array($file_ext, $allowed_extensions)) {
                if (move_uploaded_file($_FILES['attachment']['tmp_name'][$key], $target_file)) {
                    $attachments[] = $new_file_name;
                }
            }
        }
    }

    $attachment_str = !empty($attachments) ? implode(',', $attachments) : NULL;

    $sqlInsert = "INSERT INTO private_messages (sender_id, recipient_id, message, conversation_id, attachment) 
                  VALUES (?, ?, ?, ?, ?)";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param("iisss", $user_id, $recipient_id, $message, $conversation_id, $attachment_str);
    $stmtInsert->execute();
    $stmtInsert->close();

    $conn->close();

    echo json_encode([
        "status" => "success",
        "message" => $message,
        "sender" => "You",
        "timestamp" => date("M d, Y H:i"),
        "attachments" => $attachments
    ]);
    exit;
}

echo json_encode(["status" => "error", "message" => "Invalid request."]);
exit;
?>

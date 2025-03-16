<?php
session_start();
require_once 'db_connection.php';

header("Content-Type: application/json");

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "Not logged in"]);
    exit;
}

$user_id = $_SESSION['user_id'];

// Process only POST requests.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Cast recipient_id to integer.
    $recipient_id = isset($_POST['recipient_id']) ? intval($_POST['recipient_id']) : 0;
    // Conversation ID is treated as a string.
    $conversation_id = isset($_POST['conversation_id']) ? trim($_POST['conversation_id']) : '';
    // Retrieve the reply message (can be empty if attachments exist).
    $message = trim($_POST['reply_message'] ?? '');
    $attachments = [];

    error_log("Reply received: sender=$user_id, recipient=$recipient_id, conv=$conversation_id, message='$message'");

    // Adjust the required fields check: require recipient and conversation; message may be empty if attachments exist.
    if ($recipient_id <= 0 || empty($conversation_id) || (empty($message) && empty($_FILES['attachment']['name'][0]))) {
        error_log("Missing required fields: recipient_id=$recipient_id, conversation_id='$conversation_id', message='$message'");
        echo json_encode(["status" => "error", "message" => "Missing required fields."]);
        exit;
    }

    // Ensure the uploads directory exists.
    $upload_dir = 'uploads/';
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create upload directory: $upload_dir");
        }
    }

    error_log("Upload Process Started");

    // Process file upload if attachments are provided.
    if (isset($_FILES['attachment']) && !empty($_FILES['attachment']['name'][0])) {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];
        foreach ($_FILES['attachment']['name'] as $key => $file_name) {
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $new_file_name = uniqid() . '.' . $file_ext;
            $target_file = $upload_dir . $new_file_name;
            
            if (in_array($file_ext, $allowed_extensions)) {
                if (move_uploaded_file($_FILES['attachment']['tmp_name'][$key], $target_file)) {
                    $attachments[] = $new_file_name;
                    error_log("File uploaded: " . $new_file_name);
                } else {
                    error_log("Upload failed for: " . $file_name . " with error code " . $_FILES['attachment']['error'][$key]);
                }
            } else {
                error_log("Invalid file type for: " . $file_name);
            }
        }
    } else {
        error_log("No files were uploaded.");
    }

    // Convert attachments array to a comma-separated string if any.
    $attachment_str = !empty($attachments) ? implode(',', $attachments) : NULL;
    error_log("Final attachment string: " . ($attachment_str ?? "NULL"));

    error_log("Inserting message into DB: sender=$user_id, recipient=$recipient_id, conv=$conversation_id, message='$message'");

    $sqlInsert = "INSERT INTO private_messages (sender_id, recipient_id, message, conversation_id, attachment) VALUES (?, ?, ?, ?, ?)";
    $stmtInsert = $conn->prepare($sqlInsert);
    if (!$stmtInsert) {
        error_log("Prepare failed: " . $conn->error);
        echo json_encode(["status" => "error", "message" => "Database error."]);
        exit;
    }
    // Bind parameters: sender_id (i), recipient_id (i), message (s), conversation_id (s), attachment (s)
    $stmtInsert->bind_param("iisss", $user_id, $recipient_id, $message, $conversation_id, $attachment_str);
    if (!$stmtInsert->execute()) {
        error_log("Database Insert Error: " . $stmtInsert->error);
        echo json_encode(["status" => "error", "message" => "Insert error."]);
        exit;
    }
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

<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch all users (except the current user)
$sqlUsers = "SELECT id, username FROM users WHERE id != ?";
$stmtUsers = $conn->prepare($sqlUsers);
$stmtUsers->bind_param("i", $user_id);
$stmtUsers->execute();
$resultUsers = $stmtUsers->get_result();
$users = $resultUsers->fetch_all(MYSQLI_ASSOC);
$stmtUsers->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $recipient_id = $_POST['recipient_id'] ?? 0;
    $message = trim($_POST['message'] ?? '');

    if (!$recipient_id || empty($message)) {
        header("Location: messages.php");
        exit;
    }

    // Check if an existing conversation exists
   $user1 = min($user_id, $recipient_id);
$user2 = max($user_id, $recipient_id);

// Check or create conversation
$sqlConv = "SELECT id FROM conversations WHERE user1_id = ? AND user2_id = ?";
$stmtConv = $conn->prepare($sqlConv);
$stmtConv->bind_param("ii", $user1, $user2);
$stmtConv->execute();
$resultConv = $stmtConv->get_result();
$existingConv = $resultConv->fetch_assoc();
$stmtConv->close();

if ($existingConv) {
    $conversation_id = $existingConv['id'];
} else {
    $sqlInsertConv = "INSERT INTO conversations (user1_id, user2_id) VALUES (?, ?)";
    $stmtInsertConv = $conn->prepare($sqlInsertConv);
    $stmtInsertConv->bind_param("ii", $user1, $user2);
    $stmtInsertConv->execute();
    $conversation_id = $stmtInsertConv->insert_id;
    $stmtInsertConv->close();
}


    // Insert the new message with the correct conversation_id
    $sqlInsert = "INSERT INTO private_messages (sender_id, recipient_id, message, conversation_id) VALUES (?, ?, ?, ?)";
    $stmtInsert = $conn->prepare($sqlInsert);
    $stmtInsert->bind_param("iiss", $user_id, $recipient_id, $message, $conversation_id);
    $stmtInsert->execute();
    $stmtInsert->close();

    $conn->close();

    // If the request is an AJAX request, return JSON; otherwise, redirect.
    if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
         echo json_encode(['status' => 'success', 'conversation_id' => $conversation_id]);
         exit;
    } else {
         header("Location: conversation.php?conversation_id=$conversation_id");
         exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Message</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .chat-container {
            max-width: 600px;
            margin: 40px auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .messages-container {
            display: flex;
            flex-direction: column;
            height: auto;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #fff;
        }
        .form-container {
            padding: 15px;
            border-radius: 5px;
            background-color: #e9ecef;
        }
        .form-container label {
            font-weight: bold;
        }
    </style>
</head>
<body>
<div class="container chat-container">
    <h4 class="text-center">New Message</h4>
    <a href="messages.php" class="btn btn-secondary mb-3">Back to Inbox</a>
    
    <div class="messages-container">
        <form method="post" class="form-container">
            <div class="mb-3">
                <label for="recipient" class="form-label">Select Recipient</label>
                <select name="recipient_id" id="recipient" class="form-control" required>
                    <option value="">-- Select User --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mb-3">
                <label for="message" class="form-label">Message</label>
                <textarea name="message" id="message" class="form-control" rows="4" required></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Send Message</button>
        </form>
    </div>
</div>
</body>
</html>

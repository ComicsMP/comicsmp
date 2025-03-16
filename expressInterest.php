<?php
session_start();
include 'db_connection.php';

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit;
}

$user_id   = $_SESSION['user_id'];
$seller_id = $_POST['seller_id'] ?? null;
$match_id  = $_POST['match_id']  ?? null;

// Validate request data
if (!$seller_id || !$match_id) {
    echo json_encode(["status" => "error", "message" => "Invalid request."]);
    exit;
}

// 1. Fetch correct comic details based on the new $match_id
$query = "
SELECT 
    m.comic_title, 
    m.issue_number, 
    m.years, 
    c.comic_condition, 
    c.price
FROM match_notifications m
JOIN comics_for_sale c 
    ON m.comic_title  = c.comic_title
   AND m.issue_number = c.issue_number
   AND m.years        = c.years
WHERE m.id = ?
";
$stmt = $conn->prepare($query);
$stmt->bind_param('i', $match_id);
$stmt->execute();
$result = $stmt->get_result();
$comic = $result->fetch_assoc();

if (!$comic) {
    echo json_encode(["status" => "error", "message" => "Comic not found."]);
    exit;
}

// 2. Format a fresh message using the newly fetched details
$message = "Interested in: " . htmlspecialchars($comic['comic_title'])
         . " (" . htmlspecialchars($comic['years']) . ") "
         . "Issue #" . htmlspecialchars($comic['issue_number']) . "\n"
         . "Condition: " . htmlspecialchars($comic['comic_condition']) . "\n"
         . "Price: $" . htmlspecialchars($comic['price']) . " CDN";

// 3. Check if there's an existing conversation between these two users
$conversationQuery = "
    SELECT conversation_id
    FROM private_messages
    WHERE 
       (sender_id = ? AND recipient_id = ?) 
       OR
       (sender_id = ? AND recipient_id = ?)
    LIMIT 1
";
$convStmt = $conn->prepare($conversationQuery);
$convStmt->bind_param('iiii', $user_id, $seller_id, $seller_id, $user_id);
$convStmt->execute();
$convResult   = $convStmt->get_result();
$conversation = $convResult->fetch_assoc();

// 4. If a conversation already exists, reuse its conversation_id; otherwise create a new one
if ($conversation && !empty($conversation['conversation_id'])) {
    $conversation_id = $conversation['conversation_id'];
} else {
    // Generate a fresh conversation_id if none found
    $conversation_id = uniqid();
}

// 5. Insert the interest message into private_messages with the proper conversation_id
$insertQuery = "
    INSERT INTO private_messages
    (sender_id, recipient_id, message, sent_at, is_read, conversation_id, status)
    VALUES (?, ?, ?, NOW(), 0, ?, 'sent')
";
$insertStmt = $conn->prepare($insertQuery);
$insertStmt->bind_param('iisi', $user_id, $seller_id, $message, $conversation_id);
$insertStmt->execute();

// 6. Store Skipped & Interested Comics in Database
$interestQuery = "
    INSERT INTO skipped_comics (user_id, match_id, status) 
    VALUES (?, ?, ?) 
    ON DUPLICATE KEY UPDATE status = VALUES(status)
";
$interestStmt = $conn->prepare($interestQuery);
$interestStatus = 'interested';
$interestStmt->bind_param('iis', $user_id, $match_id, $interestStatus);
$interestStmt->execute();

// 7. Provide a JSON response depending on success
if ($insertStmt->affected_rows > 0) {
    echo json_encode(["status" => "success", "message" => "Interest sent successfully!"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to send interest."]);
}
?>

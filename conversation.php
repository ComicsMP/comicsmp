<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo "<p>You must be logged in.</p>";
    exit;
}

if (!isset($_GET['conversation_id'])) {
    echo "<p>No conversation selected.</p>";
    exit;
}

$conversation_id = $_GET['conversation_id'];

// Query messages for this conversation, ordered chronologically.
$sql = "SELECT pm.sender_id, u.username, pm.message, pm.sent_at, pm.attachment 
        FROM private_messages pm 
        JOIN users u ON pm.sender_id = u.id 
        WHERE pm.conversation_id = ? 
        ORDER BY pm.sent_at ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $conversation_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// For the conversation header, we use a static subject (or derive it if you store it).
$subject = "Conversation Details";
$lastUpdated = count($messages) > 0 ? end($messages)['sent_at'] : date("Y-m-d H:i:s");

// Get unique participant names.
$participants = [];
foreach ($messages as $msg) {
    $participants[] = $msg['username'];
}
$participants = array_unique($participants);
?>
<div class="thread-header">
    <h5><?php echo htmlspecialchars($subject); ?></h5>
    <small>
        Between <?php echo implode(", ", $participants); ?> | Last updated: <?php echo date("M d, Y H:i", strtotime($lastUpdated)); ?>
    </small>
</div>

<?php if (count($messages) > 0): ?>
    <div id="messagesContainer">
        <?php foreach ($messages as $msg): ?>
            <div class="message-item">
                <div>
                    <span class="message-sender"><?php echo htmlspecialchars($msg['username']); ?></span>
                    <span class="message-time float-end"><?php echo date("M d, Y H:i", strtotime($msg['sent_at'])); ?></span>
                </div>
                <div class="message-body">
                    <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                </div>
                <?php if (!empty($msg['attachment']) && strtolower($msg['attachment']) !== 'null'): 
                    $attachmentFiles = explode(',', $msg['attachment']);
                ?>
                    <div class="attachment-container" data-attachments='<?php echo json_encode($attachmentFiles); ?>'>
                        <?php foreach ($attachmentFiles as $file): ?>
                            <img src="uploads/<?php echo htmlspecialchars($file); ?>" class="attachment-thumb" alt="Attachment">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Auto-scroll script -->
    <script>
    setTimeout(() => {
        var replyForm = document.getElementById("replyFormContainer"); 
        if (replyForm) {
            replyForm.scrollIntoView({ behavior: "smooth", block: "start" }); // Ensure reply form is in view
        }
    }, 500); // Small delay to allow rendering
</script>


<?php else: ?>
    <div class="message-item">
        <div class="message-body">No messages in this conversation.</div>
    </div>
<?php endif; ?>

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

$currentUser = $_SESSION['user_id'];
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

// For the conversation header, we use a static subject.
$subject = "Conversation Details";
$lastUpdated = count($messages) > 0 ? end($messages)['sent_at'] : date("Y-m-d H:i:s");

// Get unique participant names.
$participants = [];
foreach ($messages as $msg) {
    $participants[] = $msg['username'];
}
$participants = array_unique($participants);
?>

<!-- Inline CSS for conversation styling using full window width -->
<style>
  /* Use full viewport width while preventing horizontal overflow */
  .conversation-wrapper {
    width: 100%;
    padding: 0 15px;  /* add some horizontal padding */
    box-sizing: border-box;
    overflow-x: hidden;
  }
  /* Conversation header */
  .conversation-header {
    padding: 15px;
    border-bottom: 1px solid #ddd;
    background: #f5f5f5;
    margin-bottom: 15px;
  }
  .conversation-header h5 {
    margin: 0;
    font-size: 1.3rem;
  }
  .conversation-header small {
    color: #777;
    display: block;
    margin-top: 5px;
  }
  /* Messages container */
  #messagesContainer {
    display: flex;
    flex-direction: column;
    gap: 15px;
    padding: 10px 0;
    overflow-x: hidden;
  }
  /* Base style for message bubbles */
  .message-item {
    width: 100%;
    padding: 10px 15px;
    border-radius: 15px;
    box-sizing: border-box;
    word-wrap: break-word;
  }
  /* Layout for message info (name and time) */
  .message-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    margin-bottom: 5px;
  }
  .message-info strong {
    margin-right: 10px;
  }
  .message-body {
    font-size: 1rem;
    line-height: 1.4;
    overflow-wrap: break-word;
  }
  /* Sent messages: default blue */
  .message-item--sent {
    background-color: #cce5ff; /* light blue */
    align-self: flex-end;
    border-bottom-right-radius: 0;
  }
  /* Received messages: default light grey */
  .message-item--received {
    background-color: #f8f9fa; /* light grey */
    align-self: flex-start;
    border-bottom-left-radius: 0;
  }
  /* Attachment thumbnails */
  .attachment-container {
    margin-top: 8px;
    display: flex;
    flex-wrap: wrap;
    gap: 5px;
  }
  .attachment-container img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
  }
</style>

<div class="conversation-wrapper">
  <div class="conversation-header">
    <h5><?php echo htmlspecialchars($subject); ?></h5>
    <small>
      Between <?php echo implode(", ", $participants); ?> | Last updated: <?php echo date("M d, Y H:i", strtotime($lastUpdated)); ?>
    </small>
  </div>

  <?php if (count($messages) > 0): ?>
    <div id="messagesContainer">
      <?php foreach ($messages as $msg): 
        $isSent = ($msg['sender_id'] == $currentUser);
        $bubbleClass = $isSent ? "message-item message-item--sent" : "message-item message-item--received";
      ?>
        <div class="<?php echo $bubbleClass; ?>">
          <div class="message-info">
            <strong><?php echo htmlspecialchars($msg['username']); ?></strong>
            <span><?php echo date("M d, Y H:i", strtotime($msg['sent_at'])); ?></span>
          </div>
          <div class="message-body">
            <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
          </div>
          <?php if (!empty($msg['attachment']) && strtolower($msg['attachment']) !== 'null'): 
              $attachmentFiles = explode(',', $msg['attachment']);
          ?>
            <div class="attachment-container" data-attachments='<?php echo json_encode($attachmentFiles); ?>'>
              <?php foreach ($attachmentFiles as $file): ?>
                <img src="uploads/<?php echo htmlspecialchars($file); ?>" class="img-thumbnail" alt="Attachment">
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Auto-scroll script -->
    <script>
      setTimeout(() => {
        var container = document.getElementById("messagesContainer");
        if (container) {
          container.scrollTop = container.scrollHeight;
        }
      }, 500);
    </script>
  <?php else: ?>
    <div class="message-item">
      <div class="message-body">No messages in this conversation.</div>
    </div>
  <?php endif; ?>
</div>

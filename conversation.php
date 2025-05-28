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

$currentUser    = $_SESSION['user_id'];
$conversation_id = intval($_GET['conversation_id']);

$checkSql  = "SELECT id FROM conversations WHERE id = ? AND (user1_id = ? OR user2_id = ?)";
$checkStmt = $conn->prepare($checkSql);
$checkStmt->bind_param("iii", $conversation_id, $currentUser, $currentUser);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
if ($checkResult->num_rows === 0) {
    echo "<p>Access denied. You are not part of this conversation.</p>";
    exit;
}
$checkStmt->close();

$sql = "
  SELECT pm.sender_id, u.username, pm.message, pm.sent_at, pm.attachment
    FROM private_messages pm
    JOIN users u ON pm.sender_id = u.id
   WHERE pm.conversation_id = ?
   ORDER BY pm.sent_at ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $conversation_id);
$stmt->execute();
$result   = $stmt->get_result();
$messages = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<style>
  body {
    margin: 0;
    padding: 0;
    background: #ffffff;
    font-family: 'Segoe UI', sans-serif;
    overflow-x: hidden;
  }

  .conversation-top-bar {
    background: #f5f5f5;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    border-bottom: 1px solid #ddd;
    position: sticky;
    top: 0;
    z-index: 999;
  }

  .conversation-top-bar button {
    background: none;
    border: none;
    font-size: 18px;
    margin-right: 12px;
    color: #007bff;
  }

  .conversation-top-bar h6 {
    margin: 0;
    font-size: 1rem;
    color: #333;
    flex: 1;
    text-align: center;
  }

  #messagesContainer {
    display: flex;
    flex-direction: column;
    gap: 0;
    padding: 0;
    width: 100%;
    background: #ffffff;
  }

  .message-item {
    width: 100%;
    padding: 12px 16px;
    font-size: 0.95rem;
    box-sizing: border-box;
    border-top: 1px solid #f0f0f0;
  }

  .message-item--sent {
    background: #e6e6e6;
    text-align: right;
  }

  .message-item--received {
    background: #f9f9f9;
    text-align: left;
  }

  .message-info {
    font-size: 0.75rem;
    color: #666;
    display: flex;
    justify-content: space-between;
    margin-bottom: 4px;
  }

  .message-body {
    line-height: 1.5;
    font-size: 1rem;
    white-space: pre-wrap;
    word-break: break-word;
    padding-top: 2px;
  }

  .attachment-container {
    margin-top: 6px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
  }

  .attachment-container img {
    width: 65px;
    height: 65px;
    object-fit: cover;
    border: 1px solid #ccc;
    border-radius: 4px;
    cursor: pointer;
  }

  .modal-content.bg-dark {
    background: #000;
    border-radius: 0;
  }

  .btn-close-custom {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 48px;
    height: 48px;
    border-radius: 50%;
    background: #ffffff;
    color: #000;
    font-size: 26px;
    font-weight: bold;
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
  }

  .btn-close-custom:hover {
    background: #eee;
  }

  #modalAttachment {
    max-width: 100%;
    max-height: 90vh;
  }
</style>

<div class="conversation-top-bar">
  <button id="goBackInbox">&larr;</button>
  <h6>Conversation</h6>
</div>

<?php if (count($messages) > 0): ?>
  <div id="messagesContainer">
    <?php foreach ($messages as $msg):
      $isSent = $msg['sender_id'] == $currentUser;
      $bubbleClass = $isSent ? "message-item message-item--sent" : "message-item message-item--received";
    ?>
      <div class="<?= $bubbleClass ?>">
        <div class="message-info">
          <strong><?= htmlspecialchars($msg['username']) ?></strong>
          <span><?= date("M d, Y H:i", strtotime($msg['sent_at'])) ?></span>
        </div>
        <div class="message-body"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
        <?php if (!empty($msg['attachment']) && strtolower($msg['attachment']) !== 'null'):
          $files = explode(',', $msg['attachment']);
        ?>
          <div class="attachment-container">
            <?php foreach ($files as $f): ?>
              <img src="/comicsmp/uploads/<?= htmlspecialchars($f) ?>" alt="Attachment">
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    setTimeout(() => {
      const container = document.getElementById("messagesContainer");
      if (container) container.scrollTop = container.scrollHeight;
    }, 300);
  </script>
<?php else: ?>
  <div class="message-item message-item--received">
    <div class="message-body">No messages in this conversation yet.</div>
  </div>
<?php endif; ?>

<!-- Fullscreen Modal for Attachments -->
<div class="modal fade" id="attachmentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
    <div class="modal-content bg-dark position-relative">
      <button type="button"
              class="btn-close-custom"
              data-bs-dismiss="modal"
              aria-label="Close">×</button>
      <div class="modal-body p-0 d-flex justify-content-center align-items-center">
        <img id="modalAttachment" src="" class="img-fluid" alt="Attachment">
      </div>
    </div>
  </div>
</div>

<script>
  // Open image modal
  document.addEventListener("click", function (e) {
    if (e.target.matches(".attachment-container img")) {
      const src = e.target.getAttribute("src");
      const modalImg = document.getElementById("modalAttachment");
      modalImg.setAttribute("src", src);

      const modal = new bootstrap.Modal(document.getElementById("attachmentModal"));
      modal.show();

      document.getElementById("attachmentModal").addEventListener('hidden.bs.modal', function () {
        modalImg.setAttribute("src", "");
        document.body.classList.remove('modal-open');
        document.querySelectorAll('.modal-backdrop').forEach(el => el.remove());
      }, { once: true });
    }
  });

  // Back button logic
  document.getElementById("goBackInbox").addEventListener("click", function () {
    document.querySelector(".right-column").innerHTML =
      '<p class="text-center text-muted">Select a conversation above</p>';
    document.getElementById("replyFormContainer").style.display = "none";
    document.getElementById("newMessageContainer").style.display = "none";
    document.querySelector(".left-column").style.display = "block";
  });
</script>

<?php
// Only start a session if one isn't already active.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$folder = isset($_GET['folder']) ? strtolower(trim($_GET['folder'])) : 'inbox';
$items = [];

if ($folder == 'inbox') {
    $sql = "
        SELECT 
            pm.conversation_id, 
            u.username AS other_user, 
            (CASE WHEN pm.recipient_id = ? THEN pm.sender_id ELSE pm.recipient_id END) AS other_user_id,
            MAX(pm.sent_at) AS latest_msg_time,
            (SELECT message FROM private_messages WHERE conversation_id = pm.conversation_id ORDER BY sent_at DESC LIMIT 1) AS latest_message,
            (SELECT COUNT(*) FROM private_messages WHERE conversation_id = pm.conversation_id AND recipient_id = ? AND is_read = 0) AS unread_count,
            COUNT(*) AS total_messages,
            COUNT(CASE WHEN FIND_IN_SET(?, pm.deleted_for_user) > 0 THEN 1 END) AS deleted_count
        FROM private_messages pm
        JOIN users u ON (pm.sender_id = u.id OR pm.recipient_id = u.id) AND u.id != ?
        WHERE (pm.recipient_id = ?)
        GROUP BY pm.conversation_id, other_user
        HAVING total_messages > deleted_count
        ORDER BY latest_msg_time DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiiii", $user_id, $user_id, $user_id, $user_id, $user_id);
} elseif ($folder == 'sent') {
    $sql = "
        SELECT 
            pm.conversation_id, 
            (SELECT username FROM users WHERE id = pm.recipient_id) AS other_user, 
            pm.recipient_id AS other_user_id,
            MAX(pm.sent_at) AS latest_msg_time,
            (SELECT message FROM private_messages WHERE conversation_id = pm.conversation_id ORDER BY sent_at DESC LIMIT 1) AS latest_message
        FROM private_messages pm
        WHERE pm.sender_id = ?
        GROUP BY pm.conversation_id, other_user
        ORDER BY latest_msg_time DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
} elseif ($folder == 'trash') {
    $sql = "
        SELECT 
            pm.conversation_id, 
            u.username AS other_user, 
            (CASE WHEN pm.recipient_id = ? THEN pm.sender_id ELSE pm.recipient_id END) AS other_user_id,
            MAX(pm.sent_at) AS latest_msg_time,
            (SELECT message FROM private_messages WHERE conversation_id = pm.conversation_id ORDER BY sent_at DESC LIMIT 1) AS latest_message,
            (SELECT COUNT(*) FROM private_messages WHERE conversation_id = pm.conversation_id AND recipient_id = ? AND is_read = 0) AS unread_count
        FROM private_messages pm
        JOIN users u ON (pm.sender_id = u.id OR pm.recipient_id = u.id) AND u.id != ?
        WHERE FIND_IN_SET(?, pm.deleted_for_user)
        GROUP BY pm.conversation_id, other_user
        ORDER BY latest_msg_time DESC
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iiis", $user_id, $user_id, $user_id, $user_id);
} else {
    $items = [];
    $folder = 'drafts';
}
if ($folder != 'drafts') {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}

$currency = '';
$stmtUser = $conn->prepare("SELECT currency FROM users WHERE id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
if ($rowUser = $resultUser->fetch_assoc()) {
    $currency = $rowUser['currency'];
}
$stmtUser->close();
if (!$currency) {
    $currency = 'USD';
}
?>

<!-- Include default header -->


<!-- CSS to set a fixed width for the left column and a fluid right column -->
<style>
  .messages-container {
    display: flex;
    flex-wrap: nowrap;
    width: 100%;
    box-sizing: border-box;
    padding: 0 15px;
  }
  /* Left column fixed width (reduced to 200px) */
  .left-column {
    width: 200px;
    flex-shrink: 0;
    border-right: 1px solid #ddd;
    overflow-y: auto;
    background-color: #f9f9f9;
  }
  /* Right column fluid */
  .right-column {
    flex-grow: 1;
    max-width: calc(100% - 200px);
    overflow-x: hidden;
    padding-left: 15px;
  }
  /* Conversation List Styles */
  .conversation-item {
    padding: 15px;
    border-bottom: 1px solid #e9ecef;
  }
  .conversation-item .fw-bold {
    font-size: 1.1rem;
  }
  .conversation-item .small {
    font-size: 0.9rem;
  }
  .conversation-item .text-truncate {
    max-width: 100%;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
  }
  /* Chat Thread Container */
  #conversationThread {
    padding: 20px;
    background-color: #ffffff;
    overflow-x: hidden;
  }
  /* Message Bubble Base Style */
  .message-item {
    margin-bottom: 20px;
    padding: 10px;
    border-radius: 10px;
    max-width: 80%;
    word-wrap: break-word;
  }
  .message-item .fw-bold {
    font-size: 1rem;
    margin-bottom: 5px;
  }
  .message-item .small {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 5px;
  }
  .message-item .message-body {
    font-size: 0.95rem;
    line-height: 1.4;
    word-wrap: break-word;
  }
  /* Sent messages (your messages) */
  .message-item--sent {
    background-color: #e0f7fa; /* light blue */
    margin-left: auto;
    text-align: right;
  }
  /* Received messages (other user's messages) */
  .message-item--received {
    background-color: #f1f8e9; /* light green */
    margin-right: auto;
    text-align: left;
  }
  /* Attachment Thumbnail */
  .img-thumbnail {
    width: 60px !important;
    height: 60px !important;
    object-fit: cover;
    margin: 3px;
    cursor: pointer;
  }
</style>

<div class="messages-container">
  <!-- Left Column: Conversation List -->
  <div class="left-column">
    <div class="bg-primary text-white p-3">
      <button id="newMessageBtn" class="btn btn-light btn-sm mt-2">New Message</button>
    </div>
    <?php if ($folder == 'inbox' || $folder == 'sent' || $folder == 'trash'): ?>
      <?php if (empty($items)): ?>
        <div class="p-3">No conversations found.</div>
      <?php else: ?>
        <?php foreach ($items as $conv): ?>
          <div class="conversation-item" data-conv-id="<?php echo $conv['conversation_id']; ?>" data-recipient-id="<?php echo $conv['other_user_id']; ?>" style="cursor:pointer;">
            <button class="btn btn-link text-danger float-end p-0" onclick="event.stopPropagation(); deleteConversation('<?php echo $conv['conversation_id']; ?>');">×</button>
            <div class="fw-bold"><?php echo htmlspecialchars($conv['other_user']); ?></div>
            <div class="small text-muted"><?php echo date("M d, Y H:i", strtotime($conv['latest_msg_time'])); ?></div>
            <div class="text-truncate"><?php echo htmlspecialchars($conv['latest_message']); ?></div>
            <?php if (isset($conv['unread_count']) && $conv['unread_count'] > 0): ?>
              <div><span class="badge bg-info">New</span></div>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    <?php elseif ($folder == 'drafts'): ?>
      <div class="p-3">
        <h5>Drafts</h5>
        <p>No draft messages found.</p>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right Column: Conversation Details and Forms -->
  <div class="right-column">
    <div id="conversationThread" class="border p-3 mb-3" style="height:60vh; overflow-y:auto;">
      <div class="mb-3 border-bottom pb-2">
        <h5>Select a Conversation</h5>
        <small>The conversation messages will appear here.</small>
      </div>
      <div class="text-muted">Please select a conversation from the list.</div>
    </div>

    <!-- Reply Form -->
    <div id="replyFormContainer" class="mb-3" style="display:none;">
      <form id="replyForm" enctype="multipart/form-data">
        <div class="mb-2">
          <textarea class="form-control" name="reply_message" id="replyMessage" rows="3" placeholder="Type your reply here..."></textarea>
        </div>
        <div class="mb-2">
          <input type="file" class="form-control" name="attachment[]" id="replyAttachment" multiple accept=".jpg, .jpeg, .png, .pdf, .webp">
        </div>
        <button type="submit" class="btn btn-primary">Send Reply</button>
      </form>
    </div>

    <!-- New Message Form -->
    <div id="newMessageContainer" style="display:none;">
      <div class="mb-3 border-bottom pb-2">
        <h5>New Message</h5>
        <small>Compose your message below.</small>
      </div>
      <form id="newMessageForm" enctype="multipart/form-data">
        <div class="mb-3">
          <label for="recipient" class="form-label">Select Recipient</label>
          <select name="recipient_id" id="recipient" class="form-select" required>
            <option value="">-- Select Contact --</option>
            <?php
            $sqlUsers = "SELECT id, username FROM users WHERE id != ?";
            $stmtUsers = $conn->prepare($sqlUsers);
            $stmtUsers->bind_param("i", $user_id);
            $stmtUsers->execute();
            $resultUsers = $stmtUsers->get_result();
            while ($user = $resultUsers->fetch_assoc()):
            ?>
            <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['username']); ?></option>
            <?php endwhile; $stmtUsers->close(); ?>
          </select>
        </div>
        <div class="mb-3">
          <label for="newMessage" class="form-label">Message</label>
          <textarea name="message" id="newMessage" class="form-control" rows="4" required></textarea>
        </div>
        <div class="mb-3">
          <input type="file" name="attachment[]" id="newMessageAttachment" class="form-control" multiple accept=".jpg, .jpeg, .png, .pdf, .webp">
        </div>
        <button type="submit" class="btn btn-primary">Send Message</button>
      </form>
    </div>
  </div>
</div>

<!-- Lightbox Modal for Attachments -->
<div id="imageLightbox" class="modal" tabindex="-1" style="display:none;">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <span class="btn-close position-absolute top-0 end-0 m-3" onclick="closeLightbox()"></span>
      <img class="img-fluid" id="lightboxImg" alt="Attachment">
      <button class="btn btn-secondary position-absolute start-0 top-50 translate-middle-y" onclick="changeImage(-1)">&#10094;</button>
      <button class="btn btn-secondary position-absolute end-0 top-50 translate-middle-y" onclick="changeImage(1)">&#10095;</button>
    </div>
  </div>
</div>

<!-- Required JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>

<script>
  $(document).on("click", ".conversation-item", function() {
    $("#newMessageContainer").hide();
    $("#replyFormContainer").show();
    
    var convId = $(this).data("conv-id");
    var recipientId = $(this).data("recipient-id");

    $("#replyForm input[type='hidden']").remove();
    $("#replyForm").prepend('<input type="hidden" name="conversation_id" value="'+convId+'">');
    $("#replyForm").prepend('<input type="hidden" name="recipient_id" value="'+recipientId+'">');

    $("#conversationThread").html("<p class='text-center py-4'>Loading conversation...</p>");
    $.ajax({
        url: "conversation.php",
        method: "GET",
        data: { conversation_id: convId },
        success: function(response) {
            $("#conversationThread").html(response);
            setTimeout(() => {
                var thread = document.getElementById("conversationThread");
                if (thread) {
                    thread.scrollTo({ top: thread.scrollHeight, behavior: "smooth" });
                }
            }, 500);
        },
        error: function() {
            $("#conversationThread").html("<p class='text-danger text-center'>Failed to load conversation.</p>");
        }
    });

    $(this).find(".badge").remove();
    $.ajax({
        url: "markRead.php",
        method: "POST",
        data: { conversation_id: convId }
    });
  });

  $("#newMessageBtn").on("click", function() {
    $("#conversationThread").html("");
    $("#replyFormContainer").hide();
    $("#newMessageContainer").show();
  });

  $("#newMessageForm").on("submit", function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
      url: "sendMessage.php",
      method: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(response) {
        if (response.status === 'success') {
          var convId = response.conversation_id;
          $.ajax({
            url: "conversation.php",
            method: "GET",
            data: { conversation_id: convId },
            success: function(convResponse) {
              $("#conversationThread").html(convResponse);
              $("#replyFormContainer").show();
              $("#newMessageContainer").hide();
              $("#replyForm input[type='hidden']").remove();
              $("#replyForm").prepend('<input type="hidden" name="conversation_id" value="'+convId+'">');
              setTimeout(() => {
                var thread = document.getElementById("conversationThread");
                if (thread) {
                  thread.scrollTo({ top: thread.scrollHeight, behavior: "smooth" });
                }
              }, 500);
            },
            error: function() {
              alert("Failed to load conversation.");
            }
          });
        } else {
          alert(response.message);
        }
      },
      error: function() {
        alert("Failed to send message.");
      }
    });
  });

  $(document).on("submit", "#replyForm", function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
      url: "replyMessage.php",
      method: "POST",
      data: formData,
      processData: false,
      contentType: false,
      dataType: 'json',
      success: function(response) {
        if (response.status === 'success') {
          var newMessageHtml = `
            <div class="message-item message-item--sent">
              <div class="fw-bold">You</div>
              <div class="small text-muted">${response.timestamp}</div>
              <div class="message-body">${response.message}</div>`;
          if (response.attachments && response.attachments.length > 0) {
            var attachmentsHtml = "<div class='mt-2' data-attachments='" + JSON.stringify(response.attachments) + "'>";
            response.attachments.forEach(function(file) {
              attachmentsHtml += "<img src='uploads/" + file + "' class='img-thumbnail me-1' alt='Attachment'>";
            });
            attachmentsHtml += "</div>";
            newMessageHtml += attachmentsHtml;
          }
          newMessageHtml += "</div>";
          $("#conversationThread").append(newMessageHtml);
          $("#conversationThread").scrollTop($("#conversationThread")[0].scrollHeight);
          $("#replyMessage").val("");
          $("#replyAttachment").val("");
        } else {
          alert(response.message);
        }
      },
      error: function() {
        alert("Failed to send reply.");
      }
    });
  });

  function deleteConversation(convId) {
    if (confirm("Are you sure you want to delete this conversation?")) {
      $.ajax({
        url: "deleteConversation.php",
        method: "POST",
        data: { conversation_id: convId },
        success: function(response) {
          location.reload();
        },
        error: function() {
          alert("Failed to delete the conversation.");
        }
      });
    }
  }

  let imagesArray = [];
  let currentIndex = 0;
  function openLightbox(index, attachmentsArray) {
    imagesArray = attachmentsArray;
    currentIndex = index;
    document.getElementById("lightboxImg").src = "uploads/" + imagesArray[currentIndex];
    $("#imageLightbox").show();
  }
  function closeLightbox() {
    $("#imageLightbox").hide();
  }
  function changeImage(direction) {
    currentIndex += direction;
    if (currentIndex < 0) {
      currentIndex = imagesArray.length - 1;
    } else if (currentIndex >= imagesArray.length) {
      currentIndex = 0;
    }
    document.getElementById("lightboxImg").src = "uploads/" + imagesArray[currentIndex];
  }

  $(document).on("click", ".img-thumbnail", function(e) {
    e.stopPropagation();
    var container = $(this).closest("[data-attachments]");
    var attachmentsArray = container ? container.data("attachments") : [$(this).attr("src").replace('uploads/', '')];
    var index = $(this).index();
    openLightbox(index, attachmentsArray);
  });
</script>

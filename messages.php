<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Determine which folder to show. Default is "inbox".
$folder = isset($_GET['folder']) ? strtolower(trim($_GET['folder'])) : 'inbox';

// Initialize an array to hold the conversations.
$items = [];

if ($folder == 'inbox') {
    // In Inbox, the other party is the sender (if current user is recipient).
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
    // In Sent, the other party is the recipient.
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
    // drafts (placeholder)
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

// Get user's currency from the users table.
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
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Messenger - Conversations</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    body {
      background-color: #f5f6fa;
    }
    /* Provided snippet */
    .cover-img {
      width: 150px;
      height: 225px;
      object-fit: cover;
      margin: 5px;
      border: 1px solid #ddd;
      border-radius: 4px;
      cursor: pointer;
    }
    .cover-wrapper {
      position: relative;
      display: inline-block;
      width: 150px;
    }
    .remove-cover, .remove-sale {
      position: absolute;
      top: 2px;
      right: 2px;
      background: rgba(255,0,0,0.8);
      color: white;
      border: none;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 12px;
      cursor: pointer;
      line-height: 18px;
      text-align: center;
      z-index: 10;
    }
    .edit-sale {
      position: absolute;
      top: 2px;
      right: 26px;
      background: rgba(0,123,255,0.8);
      color: white;
      border: none;
      border-radius: 50%;
      width: 20px;
      height: 20px;
      font-size: 12px;
      cursor: pointer;
      line-height: 18px;
      text-align: center;
      z-index: 10;
    }
    .expand-row {
      background-color: #f1f1f1;
    }
    .cover-container {
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
    }
    .popup-modal-body {
      display: flex;
      gap: 20px;
    }
    .popup-image-container img {
      max-width: 100%;
      max-height: 350px;
      object-fit: contain;
      cursor: pointer;
    }
    .popup-details-container table {
      font-size: 1rem;
    }
    .similar-issues {
      display: none;
    }
    .expand-match-row {
      background-color: #f9f9f9;
    }
    .nested-table thead {
      background-color: #eee;
    }
    #popupConditionRow,
    #popupGradedRow,
    #popupPriceRow {
      display: none;
    }
    /* End snippet */

    /* Left Column: Conversation List */
    .conversation-list {
      background: #fff;
      border-right: 1px solid #dee2e6;
      height: 100vh;
      overflow-y: auto;
    }
    .conversation-item {
      padding: 10px 15px;
      border-bottom: 1px solid #e9ecef;
      cursor: pointer;
      transition: background-color 0.15s;
      position: relative;
    }
    .conversation-item:hover {
      background-color: #f1f1f1;
    }
    .conversation-item .sender {
      font-weight: bold;
      font-size: 1rem;
    }
    .conversation-item .time {
      font-size: 0.8rem;
      color: #6c757d;
    }
    .conversation-item .preview {
      font-size: 0.9rem;
      color: #555;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-top: 3px;
    }
    .delete-btn {
      background: none;
      border: none;
      color: #dc3545;
      float: right;
      font-size: 1.2rem;
      cursor: pointer;
    }
    .new-badge {
      background-color: #17a2b8;
      color: #fff;
      padding: 2px 6px;
      border-radius: 12px;
      font-size: 0.75rem;
    }
    /* Right Column: Conversation Details */
    .thread-container {
      background: #fff;
      height: 100vh;
      overflow-y: auto;
      padding: 20px;
    }
    .thread-header {
      border-bottom: 1px solid #dee2e6;
      padding-bottom: 10px;
      margin-bottom: 15px;
    }
    .thread-header h5 {
      margin: 0;
      font-size: 1.1rem;
    }
    .thread-header small {
      color: #6c757d;
      font-size: 0.85rem;
    }
    .message-item {
      margin-bottom: 15px;
    }
    .message-item .message-sender {
      font-weight: bold;
      font-size: 0.95rem;
    }
    .message-item .message-time {
      font-size: 0.8rem;
      color: #6c757d;
    }
    .message-item .message-body {
      margin-top: 5px;
      font-size: 0.95rem;
      line-height: 1.4;
      color: #333;
    }
    .attachment-thumb {
      max-width: 80px;
      max-height: 80px;
      margin-top: 10px;
      border: 1px solid #ddd;
      border-radius: 4px;
      cursor: pointer;
    }
    .reply-form {
      border-top: 1px solid #dee2e6;
      padding-top: 10px;
      margin-top: 15px;
    }
    .reply-form textarea {
      width: 100%;
      resize: none;
    }
    .inbox-container {
      height: 100vh;
    }
    /* Updated Modal CSS for Lightbox */
    .modal-dialog {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        min-height: 100vh;
        margin: auto !important;
        max-width: none !important;
    }
    .modal-dialog.modal-dialog-centered {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        max-width: none !important;
        width: auto !important;
        height: auto !important;
        margin: auto !important;
    }
    .modal-content {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        background: transparent !important;
        border: none !important;
        box-shadow: none !important;
        text-align: center;
        width: auto !important;
        height: auto !important;
        max-width: 100vw !important;
        max-height: 100vh !important;
    }
    .modal-body {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        width: auto !important;
        height: auto !important;
        padding: 0 !important;
    }
    .modal-carousel .carousel-item {
        display: flex !important;
        justify-content: center !important;
        align-items: center !important;
        width: auto !important;
        height: auto !important;
    }
    .modal-carousel .carousel-item img {
        width: auto !important;
        height: auto !important;
        max-width: 90vw !important;
        max-height: 90vh !important;
        object-fit: contain !important;
    }
    
    /* Lightbox CSS */
    .lightbox {
        display: none;
        position: fixed;
        z-index: 9999;
        padding-top: 10%;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.8);
        text-align: center;
    }
    .lightbox-content {
        max-width: 90%;
        max-height: 90%;
        margin: auto;
        display: block;
    }
    .close-lightbox {
        position: absolute;
        top: 20px;
        right: 30px;
        color: white;
        font-size: 35px;
        font-weight: bold;
        cursor: pointer;
    }
    .prev-lightbox, .next-lightbox {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        font-size: 30px;
        color: white;
        cursor: pointer;
        user-select: none;
    }
    .prev-lightbox {
        left: 15px;
    }
    .next-lightbox {
        right: 15px;
    }
  </style>
</head>
<body class="bg-light">

<div class="container my-4 inbox-container">
  <div class="row">
    <!-- Left Column: Conversation List -->
    <div class="col-md-4 conversation-list p-0">
      <div class="p-3 bg-primary text-white">
        <h5 class="mb-0">Conversations</h5>
        <!-- New Message button triggers the new thread view -->
        <button id="newMessageBtn" class="btn btn-light btn-sm mt-2">New Message</button>
      </div>
      <?php if ($folder == 'inbox' || $folder == 'sent' || $folder == 'trash'): ?>
        <?php if (empty($items)): ?>
          <div class="p-3">No conversations found.</div>
        <?php else: ?>
          <?php foreach ($items as $conv): ?>
            <div class="conversation-item" data-conv-id="<?php echo $conv['conversation_id']; ?>" data-recipient-id="<?php echo $conv['other_user_id']; ?>">
              <button class="delete-btn" onclick="event.stopPropagation(); deleteConversation('<?php echo $conv['conversation_id']; ?>');">×</button>
              <div class="sender"><?php echo htmlspecialchars($conv['other_user']); ?></div>
              <div class="time"><?php echo date("M d, Y H:i", strtotime($conv['latest_msg_time'])); ?></div>
              <div class="preview"><?php echo htmlspecialchars($conv['latest_message']); ?></div>
              <?php if (isset($conv['unread_count']) && $conv['unread_count'] > 0): ?>
                <div><span class="new-badge">New</span></div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php elseif ($folder == 'drafts'): ?>
        <div class="p-3"><h5>Drafts</h5><p>No draft messages found.</p></div>
      <?php endif; ?>
    </div>

    <!-- Right Column: Conversation Details and Forms -->
    <div class="col-md-8 thread-container">
      <div id="conversationThread">
        <div class="thread-header">
          <h5>Select a Conversation</h5>
          <small>The conversation messages will appear here.</small>
        </div>
        <div class="message-item">
          <div class="message-body">Please select a conversation from the list.</div>
        </div>
      </div>
      <!-- Reply Form (for existing conversations) -->
      <div id="replyFormContainer" class="reply-form" style="display:none;">
        <form id="replyForm" enctype="multipart/form-data">
          <!-- Hidden fields for conversation_id and recipient_id will be added via JS -->
          <div class="mb-2">
            <textarea class="form-control" name="reply_message" id="replyMessage" rows="3" placeholder="Type your reply here..."></textarea>
          </div>
          <div class="mb-2">
            <input type="file" class="form-control-file" name="attachment[]" id="replyAttachment" multiple accept=".jpg, .jpeg, .png, .pdf, .webp">
          </div>
          <button type="submit" class="btn btn-primary">Send Reply</button>
        </form>
      </div>
      <!-- New Message Form Container (for starting a new conversation) -->
      <div id="newMessageContainer" style="display:none;">
        <!-- New Message view styled like conversation view -->
        <div class="thread-header">
          <h5>New Message</h5>
          <small>Compose your message below.</small>
        </div>
        <form id="newMessageForm" enctype="multipart/form-data">
          <div class="mb-3">
            <label for="recipient" class="form-label">Select Recipient</label>
            <select name="recipient_id" id="recipient" class="form-control" required>
              <option value="">-- Select Contact --</option>
              <?php
              // Optional: Fetch only past contacts if available.
              // For now, we list all users except the current user.
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
            <input type="file" name="attachment[]" id="newMessageAttachment" class="form-control-file" multiple accept=".jpg, .jpeg, .png, .pdf, .webp">
          </div>
          <button type="submit" class="btn btn-primary">Send Message</button>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Lightbox Modal for Attachments (New Lightbox Implementation) -->
<div id="imageLightbox" class="lightbox">
    <span class="close-lightbox" onclick="closeLightbox()">&times;</span>
    <img class="lightbox-content" id="lightboxImg">
    <a class="prev-lightbox" onclick="changeImage(-1)">&#10094;</a>
    <a class="next-lightbox" onclick="changeImage(1)">&#10095;</a>
</div>

<!-- Required JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script>
  // When a conversation item is clicked, load its details into the right column.
  $(document).on("click", ".conversation-item", function() {
    // Hide new message form if visible.
    $("#newMessageContainer").hide();
    $("#replyFormContainer").show();
    
    var convId = $(this).data("conv-id");
    var recipientId = $(this).data("recipient-id");

    // Remove old hidden fields and insert new ones
    $("#replyForm input[type='hidden']").remove();
    $("#replyForm").prepend('<input type="hidden" name="conversation_id" value="'+convId+'">');
    $("#replyForm").prepend('<input type="hidden" name="recipient_id" value="'+recipientId+'">');

    // Load conversation messages
    $("#conversationThread").html("<p style='text-align:center; padding:20px;'>Loading conversation...</p>");
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
            $("#conversationThread").html("<p class='text-danger' style='text-align:center;'>Failed to load conversation.</p>");
        }
    });

    // Remove unread badge
    $(this).find(".new-badge").remove();
    $.ajax({
        url: "markRead.php",
        method: "POST",
        data: { conversation_id: convId }
    });
  });

  // New Message Button Click: Show new message form styled like the conversation view.
  $("#newMessageBtn").on("click", function() {
    // Hide conversation thread and reply form.
    $("#conversationThread").html("");
    $("#replyFormContainer").hide();
    // Show the new message form container.
    $("#newMessageContainer").show();
  });

  // Handle new message form submission; send message to DB and then load the conversation.
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
          // After sending the new message, load the conversation in the right column.
          var convId = response.conversation_id;
          $.ajax({
            url: "conversation.php",
            method: "GET",
            data: { conversation_id: convId },
            success: function(convResponse) {
              $("#conversationThread").html(convResponse);
              $("#replyFormContainer").show();
              $("#newMessageContainer").hide();
              // Set hidden fields for reply form.
              $("#replyForm input[type='hidden']").remove();
              $("#replyForm").prepend('<input type="hidden" name="conversation_id" value="'+convId+'">');
              // Scroll to the bottom after loading.
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

  // Delegate reply form submission (for existing conversations)
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
            <div class="message-item">
              <div class="message-sender">You</div>
              <div class="message-time">${response.timestamp}</div>
              <div class="message-body">${response.message}</div>
          `;
          if (response.attachments && response.attachments.length > 0) {
            var attachmentsHtml = "<div class='attachment-container' data-attachments='" + JSON.stringify(response.attachments) + "' style='margin-top:10px;'>";
            response.attachments.forEach(function(file) {
              attachmentsHtml += "<img src='uploads/" + file + "' class='attachment-thumb' alt='Attachment' style='margin-right:5px;'>";
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

  // Delete conversation via AJAX.
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

  // Delegate click event on attachment container to open the Lightbox.
  $(document).on("click", ".attachment-container", function() {
    var attachmentsArray = $(this).data("attachments");
    if (attachmentsArray && attachmentsArray.length > 0) {
      openLightbox(0, attachmentsArray);
    }
  });

  // If user clicks directly on an image (and not the container).
  $(document).on("click", ".attachment-thumb", function(e) {
    e.stopPropagation();
    var container = $(this).closest(".attachment-container");
    var attachmentsArray;
    if (container.length) {
      attachmentsArray = container.data("attachments");
    } else {
      attachmentsArray = [$(this).attr("src").replace('uploads/', '')];
    }
    var index = $(this).index();
    openLightbox(index, attachmentsArray);
  });

  // Lightbox functions
  let imagesArray = [];
  let currentIndex = 0;
  function openLightbox(index, attachmentsArray) {
    imagesArray = attachmentsArray;
    currentIndex = index;
    document.getElementById("lightboxImg").src = "uploads/" + imagesArray[currentIndex];
    document.getElementById("imageLightbox").style.display = "block";
  }
  function closeLightbox() {
    document.getElementById("imageLightbox").style.display = "none";
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
</script>
</body>
</html>

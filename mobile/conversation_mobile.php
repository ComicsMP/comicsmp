<?php
// mobile/conversation_mobile.php
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['conversation_id'])) {
    echo "Access error."; exit;
}

$currentUser    = $_SESSION['user_id'];
$conversation_id = intval($_GET['conversation_id']);

// Verify participation
$check = $conn->prepare("
  SELECT 1 FROM conversations
  WHERE id = ? AND (user1_id = ? OR user2_id = ?)
");
$check->bind_param("iii", $conversation_id, $currentUser, $currentUser);
$check->execute();
if (!$check->get_result()->num_rows) {
    echo "Access denied."; exit;
}
$check->close();

// Determine other participant
$otherStmt = $conn->prepare("
  SELECT IF(user1_id = ?, user2_id, user1_id) AS other_id
  FROM conversations
  WHERE id = ?
");
$otherStmt->bind_param("ii", $currentUser, $conversation_id);
$otherStmt->execute();
$otherRes = $otherStmt->get_result();
$otherRow = $otherRes->fetch_assoc();
$otherId  = $otherRow['other_id'];
$otherStmt->close();

// Fetch messages
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
$messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Conversation</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
        rel="stylesheet">
  <style>
    * { box-sizing:border-box; margin:0; padding:0 }
    html,body { height:100%; width:100%; overflow:hidden;
      background:#f2f2f2; font-family:'Segoe UI',sans-serif; }
    .header { position:fixed; top:0; left:0; right:0;
      height:56px; background:#fff; border-bottom:1px solid #ddd;
      display:flex; align-items:center; padding:0 16px;
      font-size:1.1rem; font-weight:600; z-index:100; }
    .header button { background:none; border:none;
      font-size:20px; color:#007bff; margin-right:16px; }
    .chat-body { position:absolute;
      top:56px; bottom:106px; left:0; right:0;
      padding:12px; overflow-y:auto;
      display:flex; flex-direction:column; gap:10px; }
    .message { max-width:80%; padding:10px 14px;
      border-radius:16px; line-height:1.4;
      word-break:break-word; position:relative; }
    .sent { align-self:flex-end; background:#daf1ff;
      border-bottom-right-radius:0; }
    .received { align-self:flex-start; background:#fff;
      border:1px solid #ddd; border-bottom-left-radius:0; }
    .meta { font-size:0.7rem; color:#666;
      margin-top:6px; text-align:right; }
    .att img { margin-top:8px; width:60px; height:60px;
      object-fit:cover; border:1px solid #ccc;
      border-radius:4px; cursor:pointer; }
    .reply-bar { position:fixed; bottom:56px; left:0; right:0;
      height:50px; background:#fff; border-top:1px solid #ddd;
      display:flex; align-items:center; padding:0 12px; gap:8px;
      z-index:100; }
    .reply-bar textarea { flex:1; height:36px;
      border:1px solid #ccc; border-radius:8px;
      padding:8px 12px; resize:none; font-size:14px; }
    .reply-bar label { width:38px; height:38px;
      background:#eee; border-radius:8px;
      display:flex; align-items:center;
      justify-content:center; cursor:pointer; }
    .reply-bar label i { font-size:20px; color:#555; }
    .reply-bar button { width:38px; height:38px;
      background:#007bff; border:none; color:#fff;
      border-radius:50%; display:flex;
      align-items:center; justify-content:center;
      font-size:18px; }
    .footer { position:fixed; bottom:0; left:0; right:0;
      height:56px; background:#000;
      display:flex; justify-content:space-around;
      align-items:center; z-index:100; }
    .footer a { flex:1; text-align:center;
      color:#fff; text-decoration:none; font-size:12px; }
    .footer i { display:block; font-size:18px;
      margin-bottom:0px; }
    .image-modal { display:none; position:fixed;
      top:0; left:0; right:0; bottom:0;
      background:rgba(0,0,0,0.8);
      justify-content:center; align-items:center;
      z-index:200; }
    .image-modal.show { display:flex; }
    .modal-img { max-width:90%; max-height:90%; }
    .modal-close { position:absolute; top:16px; right:16px;
      width:48px; height:48px; border-radius:24px;
      background:#fff; color:#000; font-size:26px;
      font-weight:bold; display:flex;
      align-items:center; justify-content:center;
      cursor:pointer; }
  </style>
</head>
<body>
  <div class="header">
    <button onclick="location.href='inbox.php'">
      <i class="bi bi-arrow-left"></i>
    </button>
    Conversation
  </div>

  <div class="chat-body" id="chatBody">
    <?php if ($messages): foreach ($messages as $m): ?>
      <div class="message <?= $m['sender_id']==$currentUser?'sent':'received' ?>">
        <?= nl2br(htmlspecialchars($m['message'])) ?>
        <?php if (!empty($m['attachment']) && strtolower($m['attachment'])!=='null'):
          foreach (explode(',', $m['attachment']) as $f): ?>
            <div class="att">
              <img src="/comicsmp/uploads/<?= htmlspecialchars($f) ?>" alt="">
            </div>
          <?php endforeach;
        endif; ?>
        <div class="meta"><?= date("M d, Y H:i", strtotime($m['sent_at'])) ?></div>
      </div>
    <?php endforeach; else: ?>
      <p style="color:#666; text-align:center; margin-top:20px;">
        No messages yet.
      </p>
    <?php endif; ?>
  </div>

  <div class="reply-bar">
    <label for="att"><i class="bi bi-paperclip"></i></label>
    <input type="file" id="att" multiple hidden>
    <textarea id="replyText" placeholder="Type a message…"></textarea>
    <button id="sendBtn"><i class="bi bi-send-fill"></i></button>
  </div>

  <div class="footer">
    <a href="../mobile/dashboard.php"><i class="bi bi-house-fill"></i>Home</a>
    <a href="../mobile/wanted.php"><i class="bi bi-heart-fill"></i>Wanted</a>
    <a href="../mobile/selling.php"><i class="bi bi-tag-fill"></i>Selling</a>
    <a href="../mobile/matches.php"><i class="bi bi-people-fill"></i>Matches</a>
  </div>

  <div class="image-modal" id="imageModal">
    <div class="modal-close" id="closeModal">×</div>
    <img class="modal-img" id="modalImg" src="" alt="">
  </div>

  <script>
    window.onload = () => {
      document.getElementById('chatBody').scrollTop =
        document.getElementById('chatBody').scrollHeight;
    };

    // Lightbox
    document.addEventListener('click', e => {
      const img = e.target.closest('.att img');
      if (img) {
        const modal = document.getElementById('imageModal');
        document.getElementById('modalImg').src = img.src;
        modal.classList.add('show');
      }
    });
    document.getElementById('closeModal').onclick = () => {
      const modal = document.getElementById('imageModal');
      modal.classList.remove('show');
      document.getElementById('modalImg').src = '';
    };

    // AJAX reply
    document.getElementById('sendBtn').addEventListener('click', () => {
      const text = document.getElementById('replyText').value.trim();
      const files = document.getElementById('att').files;
      if (!text && files.length === 0) return;

      const form = new FormData();
      form.append('conversation_id', <?= $conversation_id ?>);
      form.append('recipient_id', <?= $otherId ?>);
      form.append('reply_message', text);
      for (let i = 0; i < files.length; i++) {
        form.append('attachment[]', files[i]);
      }

      fetch('../replyMessage.php', {
        method: 'POST',
        body: form
      })
      .then(r => r.json())
      .then(data => {
        if (data.status === 'success') {
          const div = document.createElement('div');
          div.className = 'message sent';
          div.innerHTML = `
            ${data.message.replace(/\n/g,'<br>')}
            ${data.attachments ? data.attachments.map(f =>
              `<div class="att"><img src="/comicsmp/uploads/${f}"></div>`
            ).join('') : ''}
            <div class="meta">${data.timestamp}</div>`;
          const cb = document.getElementById('chatBody');
          cb.appendChild(div);
          cb.scrollTop = cb.scrollHeight;
          document.getElementById('replyText').value = '';
          document.getElementById('att').value = '';
        } else {
          alert(data.message || 'Send failed');
        }
      })
      .catch(() => alert('Send error'));
    });
  </script>
</body>
</html>

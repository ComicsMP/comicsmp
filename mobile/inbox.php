<?php
// mobile/inbox.php
session_start();
require_once '../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch inbox conversations, excluding soft‐deleted
$sql = "
  SELECT 
    c.id AS conversation_id,
    u.username AS other_user,
    MAX(pm.sent_at) AS latest_msg_time,
    (SELECT message
       FROM private_messages
       WHERE conversation_id = c.id
       ORDER BY sent_at DESC
       LIMIT 1
    ) AS latest_message,
    (SELECT COUNT(*)
       FROM private_messages
       WHERE conversation_id = c.id
         AND recipient_id = ?
         AND is_read = 0
    ) AS unread_count
  FROM conversations c
  JOIN users u
    ON u.id = IF(c.user1_id = ?, c.user2_id, c.user1_id)
  LEFT JOIN private_messages pm
    ON pm.conversation_id = c.id
  WHERE ? IN (c.user1_id, c.user2_id)
    AND (pm.deleted_for_user IS NULL 
         OR NOT FIND_IN_SET(?, pm.deleted_for_user))
  GROUP BY c.id
  ORDER BY latest_msg_time DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iiii", $user_id, $user_id, $user_id, $user_id);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Build your “recent contacts” list
$contactsSql = "
  SELECT DISTINCT u.id, u.username
  FROM conversations c
  JOIN users u
    ON u.id = IF(c.user1_id = ?, c.user2_id, c.user1_id)
  WHERE ? IN (c.user1_id, c.user2_id)
";
$cstmt = $conn->prepare($contactsSql);
$cstmt->bind_param("ii", $user_id, $user_id);
$cstmt->execute();
$contacts = $cstmt->get_result()->fetch_all(MYSQLI_ASSOC);
$cstmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Inbox</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css"
        rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    html,body{height:100%;width:100%;overflow:hidden;
      background:#f2f2f2;font-family:'Segoe UI',sans-serif;}
    .header{position:fixed;top:0;left:0;right:0;height:56px;
      background:#fff;border-bottom:1px solid #ddd;
      display:flex;align-items:center;padding:0 16px;
      font-size:1.2rem;font-weight:600;z-index:100;}
    .header button{background:none;border:none;
      font-size:20px;color:#007bff;margin-left:auto;}
    .list,.new-message{position:absolute;left:0;right:0;
      overflow-y:auto;background:#fff;}
    .list{top:56px;bottom:56px;}
    .new-message{top:56px;bottom:56px;padding:16px;display:none;}
    .item{padding:12px 16px;border-bottom:1px solid #eee;
      cursor:pointer;}
    .item:last-child{border-bottom:none;}
    .top{display:flex;align-items:center;
      justify-content:space-between;}
    .user{font-weight:500;font-size:1rem;}
    .time{font-size:0.75rem;color:#999;}
    .snippet{margin-top:4px;color:#555;font-size:0.9rem;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
    .badge{background:#007bff;color:#fff;border-radius:12px;
      padding:2px 6px;font-size:0.7rem;}
    .new-message h2{margin-bottom:12px;font-size:1.1rem;}
    .new-message label{display:block;margin:8px 0 4px;}
    .new-message select,
    .new-message textarea{width:100%;padding:8px;
      border:1px solid #ccc;border-radius:6px;font-size:1rem;}
    .button-group{display:flex;justify-content:flex-end;gap:8px;margin-top:12px;}
    .button-group button{padding:10px 16px;border:none;border-radius:6px;font-size:1rem;}
    #cancelNew{background:#ccc;color:#000;}
    #sendNew{background:#007bff;color:#fff;}
    .footer{position:fixed;bottom:0;left:0;right:0;
      height:56px;background:#000;
      display:flex;justify-content:space-around;
      align-items:center;z-index:100;}
    .footer a{flex:1;color:#fff;text-decoration:none;
      font-size:12px;text-align:center;}
    .footer i{display:block;font-size:18px;margin-bottom:0px;}
  </style>
</head>
<body>

  <div class="header">
    Messages
    <button id="showNew"><i class="bi bi-pencil-square"></i></button>
  </div>

  <div class="list">
    <?php if (empty($items)): ?>
      <p style="padding:16px;color:#666;text-align:center;">
        No conversations.
      </p>
    <?php else: foreach($items as $conv): ?>
      <div class="item" data-id="<?= $conv['conversation_id'] ?>">
        <div class="top">
          <span class="user"><?= htmlspecialchars($conv['other_user']) ?></span>
          <?php if ($conv['unread_count']): ?>
            <span class="badge"><?= $conv['unread_count'] ?></span>
          <?php endif; ?>
          <span class="time">
            <?= date("M d, Y H:i", strtotime($conv['latest_msg_time'])) ?>
          </span>
        </div>
        <div class="snippet"><?= htmlspecialchars($conv['latest_message']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="new-message" id="newMessage">
    <h2>New Message</h2>
    <form id="newForm" enctype="multipart/form-data">
      <label for="recipient">To</label>
      <select id="recipient" name="recipient_id" required>
        <option value="">— Select a contact —</option>
        <?php foreach($contacts as $c): ?>
          <option value="<?= $c['id'] ?>">
            <?= htmlspecialchars($c['username']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <label for="msg">Message</label>
      <textarea id="msg" name="message" rows="4"
                placeholder="Type your message…" required></textarea>
      <div class="button-group">
        <button type="button" id="cancelNew">Cancel</button>
        <button type="submit" id="sendNew">Send</button>
      </div>
    </form>
  </div>

  <div class="footer">
    <a href="dashboard.php"><i class="bi bi-house-fill"></i>Home</a>
    <a href="wanted.php"><i class="bi bi-heart-fill"></i>Wanted</a>
    <a href="selling.php"><i class="bi bi-tag-fill"></i>Selling</a>
    <a href="matches.php"><i class="bi bi-people-fill"></i>Matches</a>
  </div>

<script>
  // navigate to conversation
  document.querySelectorAll('.item').forEach(el => {
    el.onclick = () =>
      location.href = 'conversation_mobile.php?conversation_id=' + el.dataset.id;
  });

  // toggle new message form
  const listEl = document.querySelector('.list');
  const newEl  = document.getElementById('newMessage');
  document.getElementById('showNew').onclick = () => {
    listEl.style.display = 'none';
    newEl.style.display  = 'block';
  };
  document.getElementById('cancelNew').onclick = () => {
    newEl.style.display  = 'none';
    listEl.style.display = 'block';
  };

  // AJAX sendMessage + close form + reload inbox
  document.getElementById('newForm').onsubmit = e => {
    e.preventDefault();
    // close form immediately
    newEl.style.display  = 'none';
    listEl.style.display = 'block';

    const fd = new FormData(e.target);
    fetch('../sendMessage.php', { method:'POST', body:fd })
      .then(r => r.text())
      .then(txt => {
        let data;
        try { data = JSON.parse(txt.trim()); }
        catch { console.warn('Invalid JSON:', txt); return; }
        if (data.status === 'success') {
          // reload after a tiny delay so UI updates
          setTimeout(() => location.reload(), 200);
        } else {
          alert(data.message || 'Failed to send.');
        }
      })
      .catch(() => alert('Error sending message.'));
  };

  // long-press soft-delete
  let pressTimer;
  document.querySelectorAll('.item').forEach(item => {
    item.addEventListener('touchstart', () => {
      pressTimer = setTimeout(() => {
        const id = item.dataset.id;
        if (confirm('Delete this conversation?')) {
          fetch('../deleteConversation.php', {
            method:'POST',
            headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: 'conversation_id=' + encodeURIComponent(id)
          })
          .then(r => r.json())
          .then(res => {
            if (res.status === 'success') location.reload();
            else alert(res.message||'Delete failed');
          })
          .catch(() => alert('Error deleting'));
        }
      }, 800);
    });
    ['touchend','touchmove','touchcancel'].forEach(evt =>
      item.addEventListener(evt, () => clearTimeout(pressTimer))
    );
  });
</script>
</body>
</html>

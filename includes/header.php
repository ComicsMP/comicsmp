<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../db_connection.php';

$user = null;
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT username, profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>

<div class="header">
  <h1>
    <a href="dashboard.php">
      <img src="logo.png" alt="ComicsMP Logo" style="height: 50px;">
    </a>
  </h1>
  <?php if ($user): ?>
    <div class="header-icons d-flex align-items-center gap-3">
      <!-- Envelope icon linking to dashboard messages tab via hidden nav link -->
      <a href="#messages" data-bs-toggle="tab" id="headerMessagesLink" class="position-relative text-white" title="Messages">
        <i class="bi bi-envelope" style="font-size: 1.5rem;"></i>
        <span id="unreadCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
          0
        </span>
      </a>
      <!-- User dropdown -->
      <div class="dropdown">
        <button class="btn dropdown-toggle d-flex align-items-center gap-2" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Avatar" class="avatar">
          <?= htmlspecialchars($user['username']) ?>
        </button>
        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
          <li><a class="dropdown-item" href="dashboard.php">Dashboard</a></li>
          <li><a class="dropdown-item" href="#" data-bs-target="#profile">Profile</a></li>
          <li><a class="dropdown-item" href="logout.php">Logout</a></li>
        </ul>
      </div>
    </div>
    <script>
      // When the envelope icon is clicked, trigger the hidden Messages tab link
      document.getElementById("headerMessagesLink").addEventListener("click", function(e) {
        e.preventDefault();
        var hiddenMessagesLink = document.getElementById("navMessages");
        if (hiddenMessagesLink) {
          hiddenMessagesLink.click();
        } else {
          console.error("Hidden Messages tab link not found!");
        }
      });

      // Function to update the unread count badge using your unreadMessageIndicator.php endpoint
      function updateUnreadCount() {
        $.ajax({
          url: 'unreadMessageIndicator.php',
          method: 'GET',
          dataType: 'json',
          success: function(response) {
            var count = response.unread;
            var $badge = $('#unreadCount');
            $badge.text(count);
            if(count > 0) {
              $badge.show();
            } else {
              $badge.hide();
            }
          },
          error: function() {
            console.error('Error fetching unread count.');
          }
        });
      }
      
      // Initial update on page load
      updateUnreadCount();
      // Poll every 10 seconds (adjust interval as needed)
      setInterval(updateUnreadCount, 10000);
    </script>
  <?php else: ?>
    <a href="login.php" class="btn btn-outline-light btn-sm">Login / Register</a>
  <?php endif; ?>
</div>

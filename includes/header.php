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
  <?php else: ?>
    <a href="login.php" class="btn btn-outline-light btn-sm">Login / Register</a>
  <?php endif; ?>
</div>

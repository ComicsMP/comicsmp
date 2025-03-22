<?php
session_start();
require_once 'includes/setup.php';
require_once 'db_connection.php';

$showLoginForm = false;
$error = "";

if (!isset($_SESSION['user_id'])) {
    $showLoginForm = true;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email']);
        $password = $_POST['password'];

        if (!empty($email) && !empty($password)) {
            $query = "SELECT id, username, password_hash FROM users WHERE email = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                $user = $result->fetch_assoc();
                if (password_verify($password, $user['password_hash'])) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    header("Location: dashboard.php");
                    exit;
                } else {
                    $error = "Invalid password.";
                }
            } else {
                $error = "User not found.";
            }
        } else {
            $error = "All fields are required.";
        }
    }
}
?>

<?php include 'includes/layout_head.php'; ?>
<?php include 'includes/header.php'; ?>

<div class="d-flex">
  <?php if (!$showLoginForm): ?>
    <?php include 'includes/sidebar.php'; ?>
  <?php endif; ?>

  <div class="main-content">
    <?php if ($showLoginForm): ?>
      <h1 class="text-center mb-4">Login to ComicsMP</h1>
      <div class="row justify-content-center">
        <div class="col-md-6">
          <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <?php endif; ?>
          <form method="POST" action="dashboard.php">
            <div class="mb-3">
              <label for="email" class="form-label">Email</label>
              <input type="email" id="email" name="email" class="form-control" required>
            </div>
            <div class="mb-3">
              <label for="password" class="form-label">Password</label>
              <input type="password" id="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Login</button>
          </form>
          <p class="text-center mt-3">
            Don't have an account? <a href="register.php">Register here</a>.
          </p>
        </div>
      </div>
    <?php else: ?>
      <?php include 'includes/mainContent.php'; ?>
    <?php endif; ?>
  </div>
</div>

<?php include 'includes/offcanvas.php'; ?>
<?php include 'includes/modals.php'; ?>
<?php include 'includes/scripts.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const hash = window.location.hash;
  if (hash) {
    const tabLink = document.querySelector(`a.nav-link[href="${hash}"]`);
    if (tabLink) {
      const tab = new bootstrap.Tab(tabLink);
      tab.show();
    } else {
      const dropdownLink = document.querySelector(`.dropdown-item[data-bs-target="${hash}"]`);
      if (dropdownLink) {
        const tabTrigger = document.querySelector(`.nav-link[href="${hash}"]`);
        if (tabTrigger) {
          const tab = new bootstrap.Tab(tabTrigger);
          tab.show();
        }
      }
    }
  }

  // Activate tab from dropdown (in case no hash reload)
  document.querySelectorAll('.dropdown-item[data-bs-target]').forEach(item => {
    item.addEventListener('click', function (e) {
      e.preventDefault();
      const tabId = this.getAttribute('data-bs-target');
      const tabTrigger = document.querySelector(`.nav-link[href="${tabId}"]`);
      if (tabTrigger) {
        const tab = new bootstrap.Tab(tabTrigger);
        tab.show();
        history.replaceState(null, null, tabId); // update URL hash
      }
    });
  });
});
</script>

</body>
</html>

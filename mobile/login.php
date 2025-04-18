<?php
session_start();
$error = '';
if (isset($_SESSION['login_error'])) {
    $error = $_SESSION['login_error'];
    unset($_SESSION['login_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Mobile Login</title>
  <style>
    /* Basic Mobile Styling */
    body { font-family: Arial, sans-serif; background: #f4f4f4; padding: 20px; }
    .container {
      max-width: 400px;
      margin: 50px auto;
      background: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    }
    h1 { text-align: center; color: #007BFF; }
    .error {
      background: #f8d7da;
      color: #721c24;
      padding: 10px;
      margin-bottom: 15px;
      text-align: center;
      border-radius: 4px;
    }
    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: 12px;
      margin: 8px 0;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    button {
      width: 100%;
      padding: 12px;
      background: #007BFF;
      border: none;
      border-radius: 4px;
      color: #fff;
      font-size: 16px;
      margin-top: 10px;
      cursor: pointer;
    }
  </style>
</head>
<body>
  <div class="container">
    <h1>Login</h1>
    <?php if ($error): ?>
      <div class="error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form action="process_login.php" method="POST">
      <input type="email" name="email" placeholder="Email" required>
      <input type="password" name="password" placeholder="Password" required>
      <!-- Hidden field to signal a mobile login -->
      <input type="hidden" name="is_mobile" value="1">
      <button type="submit">Login</button>
    </form>
  </div>
</body>
</html>

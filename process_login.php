<?php
session_start();
require '../db_connection.php';  // db_connection.php is in the root

// Check if the login is coming from the mobile login form using the hidden field.
$is_mobile = false;
if (isset($_POST['is_mobile']) && $_POST['is_mobile'] === '1') {
    $is_mobile = true;
}

$error = '';

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
                // Successful login: redirect to mobile or desktop dashboard.
                if ($is_mobile) {
                    header("Location: mobile_dashboard.php");
                } else {
                    header("Location: ../dashboard.php");
                }
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

// On error, save the error message and redirect back to the proper login page.
$_SESSION['login_error'] = $error ?: 'Unknown error occurred.';
if ($is_mobile) {
    header("Location: mobile_login.php");
} else {
    header("Location: ../login.php");
}
exit;
?>

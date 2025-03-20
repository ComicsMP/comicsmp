<?php
session_start();
require_once 'includes/setup.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'] ?? 0;

// Fetch user data
$query = "SELECT username, email, phone, city, bio, profile_picture, joined_date,
                 preferred_currency, notifications, preferred_transaction,
                 preferred_payment, facebook, twitter, instagram, rating
          FROM users
          WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    die("User data not found.");
}

// Convert comma-separated sets into arrays
$user_transaction_methods = explode(',', $user['preferred_transaction'] ?? '');
$user_payment_methods     = explode(',', $user['preferred_payment'] ?? '');

// Fetch total wanted items
$stmt = $conn->prepare("SELECT COUNT(*) AS total_wanted FROM wanted_items WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$wanted_count = $result->fetch_assoc()['total_wanted'] ?? 0;

// Fetch total listings
$stmt = $conn->prepare("SELECT COUNT(*) AS total_listings FROM comics_for_sale WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$listings_count = $result->fetch_assoc()['total_listings'] ?? 0;

// Define lists to avoid undefined variable errors
$currencies = [
    "USD","CAD","EUR","GBP","AUD","JPY","CNY","INR","CHF","MXN",
    "SGD","HKD","NOK","SEK","NZD","KRW","BRL","ZAR","RUB","THB"
];
$transaction_methods = ["Shipping", "Pickup", "Meetup"];
$payment_methods = ["Cash", "E-Transfer", "PayPal"];

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic details update
    if (isset($_POST['nickname'], $_POST['email'], $_POST['city'], $_POST['preferred_currency'])) {
        $nickname = trim($_POST['nickname']);
        $email    = trim($_POST['email']);
        $phone    = trim($_POST['phone'] ?? '');
        $city     = trim($_POST['city']);
        $bio      = trim($_POST['bio'] ?? '');
        $preferred_currency = trim($_POST['preferred_currency']);
        $notifications      = isset($_POST['notifications']) ? 1 : 0;
        
        // Convert arrays to comma-separated strings
        $selected_transactions = isset($_POST['preferred_transaction'])
            ? implode(',', $_POST['preferred_transaction'])
            : '';
        $selected_payments = isset($_POST['preferred_payment'])
            ? implode(',', $_POST['preferred_payment'])
            : '';
        
        // Social media links
        $facebook  = trim($_POST['facebook']  ?? '');
        $twitter   = trim($_POST['twitter']   ?? '');
        $instagram = trim($_POST['instagram'] ?? '');
        
        $updateQuery = "UPDATE users
                        SET username = ?, email = ?, phone = ?, city = ?, bio = ?,
                            preferred_currency = ?, notifications = ?,
                            preferred_transaction = ?, preferred_payment = ?,
                            facebook = ?, twitter = ?, instagram = ?
                        WHERE id = ?";
        $stmt = $conn->prepare($updateQuery);
        $stmt->bind_param(
            "ssssssisssssi",
            $nickname,
            $email,
            $phone,
            $city,
            $bio,
            $preferred_currency,
            $notifications,
            $selected_transactions,
            $selected_payments,
            $facebook,
            $twitter,
            $instagram,
            $user_id
        );
        
        if ($stmt->execute()) {
            $_SESSION['username'] = $nickname;
            $success_message = "Profile updated successfully.";
        } else {
            $error_message = "Error updating profile.";
        }
    }
    
    // Update password if provided
    if (!empty($_POST['new_password'])) {
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $new_password, $user_id);
        if ($stmt->execute()) {
            $success_message = "Password updated successfully.";
        } else {
            $error_message = "Error updating password.";
        }
    }
    
    // Handle profile picture upload
    if (!empty($_FILES['profile_picture']['name'])) {
        $target_dir = "uploads/profile_pictures/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $fileName = basename($_FILES["profile_picture"]["name"]);
        $target_file = $target_dir . $user_id . "_" . time() . "_" . $fileName;
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmt->bind_param("si", $target_file, $user_id);
            $stmt->execute();
            $user['profile_picture'] = $target_file;
            $success_message = "Profile picture updated.";
        } else {
            $error_message = "Error uploading profile picture.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/header.php'; ?>
  <style>
    /* Profile-specific styling */
    .profile-section { margin-bottom: 1.5rem; }
    .profile-section h4 {
      border-bottom: 2px solid #007bff;
      padding-bottom: 0.5rem;
      color: #007bff;
    }
  </style>
</head>
<body class="bg-light">
  <div class="d-flex">
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- MAIN CONTENT AREA -->
    <div class="main-content p-4">
      <h2 class="mb-4">Profile</h2>
      
      <!-- Display success/error messages -->
      <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
      <?php endif; ?>
      <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
      <?php endif; ?>
      
      <form method="POST" enctype="multipart/form-data">
        <!-- Account Details -->
        <div class="profile-section">
          <h4>Account Details</h4>
          <div class="row">
            <div class="col-md-4 text-center">
              <?php if (!empty($user['profile_picture'])): ?>
                <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" class="rounded-circle img-fluid">
              <?php else: ?>
                <img src="uploads/profile_pictures/default.png" alt="Default Profile" class="rounded-circle img-fluid">
              <?php endif; ?>
              <input type="file" name="profile_picture" class="form-control mt-2">
            </div>
            <div class="col-md-8">
              <div class="mb-3">
                <label class="form-label">Nickname</label>
                <input type="text" name="nickname" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Phone Number (Optional)</label>
                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">City</label>
                <div class="input-group">
                  <input type="text" name="city" id="city" class="form-control" value="<?= htmlspecialchars($user['city']) ?>" required>
                  <button type="button" class="btn btn-outline-secondary" id="detectLocation">Detect My Location</button>
                </div>
              </div>
              <div class="mb-3">
                <label class="form-label">About Me (Optional)</label>
                <textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Preferences & Settings -->
        <div class="profile-section">
          <h4>Preferences & Settings</h4>
          <div class="row">
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Preferred Currency</label>
                <select name="preferred_currency" class="form-select">
                  <?php foreach ($currencies as $currency): ?>
                    <option value="<?= $currency ?>" <?= ($user['preferred_currency'] == $currency) ? 'selected' : '' ?>>
                      <?= $currency ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="mb-3">
                <label class="form-label">Preferred Transaction Methods</label><br>
                <?php foreach ($transaction_methods as $method): ?>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input"
                           type="checkbox"
                           name="preferred_transaction[]"
                           value="<?= $method ?>"
                           <?= in_array($method, $user_transaction_methods) ? 'checked' : '' ?>>
                    <label class="form-check-label"><?= $method ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
              <div class="mb-3">
                <label class="form-label">Preferred Payment Methods</label><br>
                <?php foreach ($payment_methods as $method): ?>
                  <div class="form-check form-check-inline">
                    <input class="form-check-input"
                           type="checkbox"
                           name="preferred_payment[]"
                           value="<?= $method ?>"
                           <?= in_array($method, $user_payment_methods) ? 'checked' : '' ?>>
                    <label class="form-check-label"><?= $method ?></label>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="notifications" id="notifications" <?= $user['notifications'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="notifications">Receive Email Notifications</label>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Security -->
        <div class="profile-section">
          <h4>Security</h4>
          <div class="mb-3">
            <label class="form-label">New Password (Leave blank if not changing)</label>
            <input type="password" name="new_password" class="form-control">
          </div>
        </div>
        
        <!-- Statistics & Social -->
        <div class="profile-section">
          <h4>Statistics & Social</h4>
          <div class="row">
            <div class="col-md-6">
              <p><strong>Joined Date:</strong> <?= htmlspecialchars($user['joined_date']) ?></p>
              <p><strong>Total Listings:</strong> <?= htmlspecialchars($listings_count) ?></p>
              <p><strong>Total Wanted Items:</strong> <?= htmlspecialchars($wanted_count) ?></p>
              <p><strong>Rating:</strong> <?= $user['rating'] ? htmlspecialchars($user['rating']) : 'No rating yet' ?></p>
            </div>
            <div class="col-md-6">
              <div class="mb-3">
                <label class="form-label">Facebook (Optional)</label>
                <input type="url" name="facebook" class="form-control" value="<?= htmlspecialchars($user['facebook'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Twitter (Optional)</label>
                <input type="url" name="twitter" class="form-control" value="<?= htmlspecialchars($user['twitter'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Instagram (Optional)</label>
                <input type="url" name="instagram" class="form-control" value="<?= htmlspecialchars($user['instagram'] ?? '') ?>">
              </div>
            </div>
          </div>
        </div>
        
        <button type="submit" class="btn btn-primary w-100 mt-3">Update Profile</button>
      </form>
    </div>
  </div>
  
  <?php include 'includes/offcanvas.php'; ?>
  <?php include 'includes/modals.php'; ?>
  <?php include 'includes/scripts.php'; ?>
  
  <!-- Location Detection Script (Optional) -->
  <script>
    document.getElementById("detectLocation").addEventListener("click", function() {
      if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(successCallback, errorCallback);
      } else {
        alert("Geolocation is not supported by this browser.");
      }
    });
    
    function successCallback(position) {
      var lat = position.coords.latitude;
      var lng = position.coords.longitude;
      var geocoder = new google.maps.Geocoder();
      var latlng = {lat: lat, lng: lng};
    
      geocoder.geocode({'location': latlng}, function(results, status) {
        if (status === 'OK') {
          if (results[0]) {
            var city = "";
            for (var i = 0; i < results[0].address_components.length; i++) {
              var component = results[0].address_components[i];
              if (component.types.indexOf('locality') > -1) {
                city = component.long_name;
                break;
              }
            }
            if (city) {
              document.getElementById('city').value = city;
            } else {
              alert("City not found.");
            }
          } else {
            alert("No results found.");
          }
        } else {
          alert("Geocoder failed due to: " + status);
        }
      });
    }
    
    function errorCallback(error) {
      alert("Error getting location: " + error.message);
    }
    
    // Adjust the sidebar for the Profile page:
    document.addEventListener("DOMContentLoaded", function(){
      // Find the Profile link in the sidebar
      var profileLink = document.querySelector('.sidebar a[href="profile_content.php"]');
      if(profileLink){
        // Remove the tab toggle attribute
        profileLink.removeAttribute("data-bs-toggle");
        // Remove active class from other links and mark Profile as active
        document.querySelectorAll('.sidebar a.nav-link').forEach(function(link){
          link.classList.remove('active');
        });
        profileLink.classList.add('active');
      }
    });
  </script>
</body>
</html>

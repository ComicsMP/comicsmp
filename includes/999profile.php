<?php
// profile_content.php – a partial included within your main layout.

// 1. Basic checks and DB logic
require_once 'includes/setup.php';

if (!isset($_SESSION['user_id'])) {
    echo "<p>Please log in to view your profile.</p>";
    return;
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
    echo "<p>User data not found.</p>";
    return;
}

// Convert comma-separated fields
$user_transaction_methods = explode(',', $user['preferred_transaction'] ?? '');
$user_payment_methods     = explode(',', $user['preferred_payment'] ?? '');

// Example: fetch counts (if you have these tables)
$stmt = $conn->prepare("SELECT COUNT(*) FROM wanted_items WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultWanted = $stmt->get_result();
$wanted_count = $resultWanted->fetch_row()[0] ?? 0;

$stmt = $conn->prepare("SELECT COUNT(*) FROM comics_for_sale WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultSale = $stmt->get_result();
$listings_count = $resultSale->fetch_row()[0] ?? 0;

// Define your dropdown data
$currencies = ["USD", "CAD", "EUR", "GBP", "AUD", "JPY", "CNY", "INR", "CHF", "MXN",
               "SGD", "HKD", "NOK", "SEK", "NZD", "KRW", "BRL", "ZAR", "RUB", "THB"];
$transaction_methods = ["Shipping", "Pickup", "Meetup"];
$payment_methods     = ["Cash", "E-Transfer", "PayPal"];

// 2. Handle POST updates
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic details
    if (isset($_POST['nickname'], $_POST['email'], $_POST['city'], $_POST['preferred_currency'])) {
        $nickname = trim($_POST['nickname']);
        $email    = trim($_POST['email']);
        $phone    = trim($_POST['phone'] ?? '');
        $city     = trim($_POST['city']);
        $bio      = trim($_POST['bio'] ?? '');
        $prefCurr = trim($_POST['preferred_currency']);
        $notif    = isset($_POST['notifications']) ? 1 : 0;

        $selected_transactions = isset($_POST['preferred_transaction'])
            ? implode(',', $_POST['preferred_transaction'])
            : '';
        $selected_payments = isset($_POST['preferred_payment'])
            ? implode(',', $_POST['preferred_payment'])
            : '';

        $facebook  = trim($_POST['facebook']  ?? '');
        $twitter   = trim($_POST['twitter']   ?? '');
        $instagram = trim($_POST['instagram'] ?? '');

        $updateQuery = "UPDATE users
                        SET username = ?, email = ?, phone = ?, city = ?, bio = ?,
                            preferred_currency = ?, notifications = ?,
                            preferred_transaction = ?, preferred_payment = ?,
                            facebook = ?, twitter = ?, instagram = ?
                        WHERE id = ?";
        $stmtUpd = $conn->prepare($updateQuery);
        $stmtUpd->bind_param(
            "ssssssisssssi",
            $nickname,
            $email,
            $phone,
            $city,
            $bio,
            $prefCurr,
            $notif,
            $selected_transactions,
            $selected_payments,
            $facebook,
            $twitter,
            $instagram,
            $user_id
        );

        if ($stmtUpd->execute()) {
            $_SESSION['username'] = $nickname;
            $success_message = "Profile updated successfully.";
        } else {
            $error_message = "Error updating profile.";
        }
    }

    // Update password
    if (!empty($_POST['new_password'])) {
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmtPass = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmtPass->bind_param("si", $new_password, $user_id);
        $stmtPass->execute();
        $success_message = "Password updated successfully.";
    }

    // Profile picture
    if (!empty($_FILES['profile_picture']['name'])) {
        $target_dir = "uploads/profile_pictures/";
        if (!is_dir($target_dir)) {
            mkdir($target_dir, 0777, true);
        }
        $fileName = basename($_FILES["profile_picture"]["name"]);
        $target_file = $target_dir . $user_id . "_" . time() . "_" . $fileName;
        if (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
            $stmtPic = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $stmtPic->bind_param("si", $target_file, $user_id);
            $stmtPic->execute();
            $user['profile_picture'] = $target_file;
            $success_message = "Profile picture updated.";
        } else {
            $error_message = "Error uploading profile picture.";
        }
    }
}
?>

<!-- Display success/error -->
<?php if (!empty($success_message)): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
<?php endif; ?>
<?php if (!empty($error_message)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
<?php endif; ?>

<h2 class="mb-4">Profile</h2>

<form method="POST" enctype="multipart/form-data">
  <style>
    .profile-section { margin-bottom: 1.5rem; }
    .profile-section h4 {
      border-bottom: 2px solid #007bff;
      padding-bottom: 0.5rem;
      color: #007bff;
      margin-bottom: 1rem;
    }
  </style>

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
              <input class="form-check-input" type="checkbox" name="preferred_transaction[]" value="<?= $method ?>"
                     <?= in_array($method, $user_transaction_methods) ? 'checked' : '' ?>>
              <label class="form-check-label"><?= $method ?></label>
            </div>
          <?php endforeach; ?>
        </div>
        <div class="mb-3">
          <label class="form-label">Preferred Payment Methods</label><br>
          <?php foreach ($payment_methods as $method): ?>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="checkbox" name="preferred_payment[]" value="<?= $method ?>"
                     <?= in_array($method, $user_payment_methods) ? 'checked' : '' ?>>
              <label class="form-check-label"><?= $method ?></label>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-md-6">
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="notifications" id="notifications"
                 <?= $user['notifications'] ? 'checked' : '' ?>>
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
        <p><strong>Joined Date:</strong> <?= htmlspecialchars($user['joined_date'] ?? '') ?></p>
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

<!-- 
  3. Your parent page (NOT this partial) must have:
     <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
     <script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBQ_S-MNLPXfeguaEQ1dOpww8vAo9bXJIw&libraries=places"></script>
-->

<script>
  // Delegated event binding for detectLocation
  $(document).on("click", "#detectLocation", function() {
    if (!navigator.geolocation) {
      alert("Geolocation is not supported by this browser.");
      return;
    }
    navigator.geolocation.getCurrentPosition(successCallback, errorCallback);
  });

  function successCallback(position) {
    console.log("Position obtained:", position);
    let lat = position.coords.latitude;
    let lng = position.coords.longitude;

    let geocoder = new google.maps.Geocoder();
    let latlng = {lat: lat, lng: lng};

    geocoder.geocode({'location': latlng}, function(results, status) {
      console.log("Geocoder status:", status, results);
      if (status === 'OK') {
        if (results[0]) {
          let city = "";
          results[0].address_components.forEach(function(component) {
            if (component.types.includes("locality") ||
                component.types.includes("postal_town") ||
                component.types.includes("administrative_area_level_1")) {
              city = component.long_name;
            }
          });
          if (city) {
            $("#city").val(city);
            console.log("City found:", city);
          } else {
            alert("City not found in address components.");
          }
        } else {
          alert("No results from geocoder.");
        }
      } else {
        alert("Geocoder failed due to: " + status);
      }
    });
  }

  function errorCallback(error) {
    console.log("Geolocation error:", error);
    alert("Error getting location: " + error.message);
  }
</script>

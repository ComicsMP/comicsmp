<?php
// profile_content_inner.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../db_connection.php';

if (!isset($_SESSION['user_id'])) {
    echo "<p>Please log in.</p>";
    return;
}

$user_id = $_SESSION['user_id'] ?? 0;

// Capture and immediately unset any flash messages so they are used only once.
$flash_success = $_SESSION['flash_success'] ?? '';
$flash_error   = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

// Fetch user data with merged currency field
$query = "SELECT username, email, phone, city, bio, profile_picture, joined_date,
                COALESCE(preferred_currency, currency) AS currency,
                notifications, preferred_transaction,
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

// Handle profile updates if form submitted via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic details update
    if (isset($_POST['nickname'], $_POST['email'], $_POST['city'], $_POST['currency'])) {
        $nickname = trim($_POST['nickname']);
        $email    = trim($_POST['email']);
        $phone    = trim($_POST['phone'] ?? '');
        $city     = trim($_POST['city']);
        $bio      = trim($_POST['bio'] ?? '');
        $currency = trim($_POST['currency']);
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
                            currency = ?, notifications = ?,
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
            $currency,
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
            $_SESSION['flash_success'] = "Profile updated successfully.";
        } else {
            $_SESSION['flash_error'] = "Error updating profile.";
        }
    }
    
    // Update password if provided
    if (!empty($_POST['new_password'])) {
        $new_password = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $stmt->bind_param("si", $new_password, $user_id);
        if ($stmt->execute()) {
            $_SESSION['flash_success'] = "Password updated successfully.";
        } else {
            $_SESSION['flash_error'] = "Error updating password.";
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
            $_SESSION['flash_success'] = "Profile picture updated.";
        } else {
            $_SESSION['flash_error'] = "Error uploading profile picture.";
        }
    }
    // Note: We do not output anything for the POST request.
}
?>

<div class="container-fluid" id="profileContentContainer">
  <!-- Display flash messages (if any) -->
  <?php if ($flash_success): ?>
    <div class="alert alert-success" id="flashMessage"><?= htmlspecialchars($flash_success) ?></div>
  <?php endif; ?>
  <?php if ($flash_error): ?>
    <div class="alert alert-danger" id="flashMessage"><?= htmlspecialchars($flash_error) ?></div>
  <?php endif; ?>

  <form id="profileForm" method="POST" enctype="multipart/form-data">
    <div class="accordion" id="profileAccordion">
      <!-- Account Details -->
      <div class="accordion-item mb-3">
        <h2 class="accordion-header" id="headingAccount">
          <button class="accordion-button collapsed bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAccount" aria-expanded="false" aria-controls="collapseAccount">
            Account Details
          </button>
        </h2>
        <div id="collapseAccount" class="accordion-collapse collapse" aria-labelledby="headingAccount" data-bs-parent="#profileAccordion">
          <div class="accordion-body">
            <div class="mb-3" style="text-align: left;">
              <?php if (!empty($user['profile_picture'])): ?>
                <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" style="width: 150px; height: 150px; object-fit: cover; display: block;">
              <?php else: ?>
                <img src="uploads/profile_pictures/default.png" alt="Default Profile" style="width: 150px; height: 150px; object-fit: cover; display: block;">
              <?php endif; ?>
              <input type="file" name="profile_picture" class="form-control mt-2" style="max-width: 150px;">
            </div>
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
          </div>
        </div>
      </div>
      
      <!-- Location & Bio -->
      <div class="accordion-item mb-3">
        <h2 class="accordion-header" id="headingLocation">
          <button class="accordion-button collapsed bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseLocation" aria-expanded="false" aria-controls="collapseLocation">
            Location & Bio
          </button>
        </h2>
        <div id="collapseLocation" class="accordion-collapse collapse" aria-labelledby="headingLocation" data-bs-parent="#profileAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label">City</label>
              <div class="input-group">
                <input type="text" name="city" id="city" class="form-control" value="<?= htmlspecialchars($user['city']) ?>" required>
                <button type="button" class="btn btn-outline-secondary" id="detectLocation">Detect My Location</button>
              </div>
              <!-- New status message area -->
              <div id="locationStatus" style="margin-top: 5px; font-size: 0.9em; color: #007bff;"></div>
            </div>
            <div class="mb-3">
              <label class="form-label">About Me</label>
              <textarea name="bio" class="form-control" rows="3"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Preferences & Settings -->
      <div class="accordion-item mb-3">
        <h2 class="accordion-header" id="headingPreferences">
          <button class="accordion-button collapsed bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePreferences" aria-expanded="false" aria-controls="collapsePreferences">
            Preferences & Settings
          </button>
        </h2>
        <div id="collapsePreferences" class="accordion-collapse collapse" aria-labelledby="headingPreferences" data-bs-parent="#profileAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label">Preferred Currency</label>
              <select name="currency" class="form-select">
                <?php foreach ($currencies as $curr): ?>
                  <option value="<?= $curr ?>" <?= ($user['currency'] == $curr) ? 'selected' : '' ?>>
                    <?= $curr ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Transaction Methods</label>
              <?php foreach ($transaction_methods as $method): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="preferred_transaction[]" value="<?= $method ?>" <?= in_array($method, $user_transaction_methods) ? 'checked' : '' ?>>
                  <label class="form-check-label"><?= $method ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="mb-3">
              <label class="form-label">Payment Methods</label>
              <?php foreach ($payment_methods as $method): ?>
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="preferred_payment[]" value="<?= $method ?>" <?= in_array($method, $user_payment_methods) ? 'checked' : '' ?>>
                  <label class="form-check-label"><?= $method ?></label>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="mb-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="notifications" id="notifications" <?= $user['notifications'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="notifications">Email Notifications</label>
              </div>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Security & Social -->
      <div class="accordion-item mb-3">
        <h2 class="accordion-header" id="headingSecurity">
          <button class="accordion-button collapsed bg-white text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSecurity" aria-expanded="false" aria-controls="collapseSecurity">
            Security & Social
          </button>
        </h2>
        <div id="collapseSecurity" class="accordion-collapse collapse" aria-labelledby="headingSecurity" data-bs-parent="#profileAccordion">
          <div class="accordion-body">
            <div class="mb-3">
              <label class="form-label">New Password (if changing)</label>
              <input type="password" name="new_password" class="form-control">
            </div>
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
            <div class="mt-3">
              <p><strong>Total Listings:</strong> <?= htmlspecialchars($listings_count) ?></p>
              <p><strong>Total Wanted Items:</strong> <?= htmlspecialchars($wanted_count) ?></p>
              <p><strong>Rating:</strong> <?= $user['rating'] ? htmlspecialchars($user['rating']) : 'No rating yet' ?></p>
            </div>
          </div>
        </div>
      </div>
    </div><!-- End Accordion -->
    <button type="submit" class="btn btn-primary w-100 mt-3">Update Profile</button>
  </form>
</div>

<!-- AJAX Form Submission & Flash Removal Script -->
<script>
// Intercept form submission and update content via AJAX
$("#profileForm").submit(function(e) {
  e.preventDefault();
  var formData = new FormData(this);
  $.ajax({
    url: '', // current URL will handle the POST
    type: 'POST',
    data: formData,
    contentType: false,
    processData: false,
    success: function(response) {
      $("#profileContentContainer").prepend('<div class="alert alert-success" id="flashMessage">Profile updated successfully.</div>');
      setTimeout(function(){
        var flash = document.getElementById("flashMessage");
        if (flash) {
          flash.style.transition = "opacity 0.5s ease-out";
          flash.style.opacity = 0;
          setTimeout(function(){ flash.remove(); }, 500);
        }
      }, 3000);
      $.get("includes/profile_content_inner.php", { t: new Date().getTime() }, function(html) {
        $("#profileContentContainer").html(html);
      });
    },
    error: function() {
      alert("Error updating profile.");
    }
  });
});

// Location Detection Script
document.getElementById("detectLocation").addEventListener("click", function() {
  // Update status message immediately
  document.getElementById("locationStatus").innerText = "Getting your location, please wait...";
  
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(successCallback, errorCallback);
  } else {
    document.getElementById("locationStatus").innerText = "";
    alert("Geolocation is not supported by this browser.");
  }
});

function successCallback(position) {
  var lat = position.coords.latitude;
  var lng = position.coords.longitude;
  
  if (typeof google !== "undefined" && google.maps) {
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
            document.getElementById("locationStatus").innerText = "Location detected: " + city;
          } else {
            document.getElementById("locationStatus").innerText = "";
            alert("City not found.");
          }
        } else {
          document.getElementById("locationStatus").innerText = "";
          alert("No results found.");
        }
      } else {
        document.getElementById("locationStatus").innerText = "";
        alert("Geocoder failed due to: " + status);
      }
    });
  } else {
    document.getElementById("locationStatus").innerText = "";
    alert("Google Maps API is not loaded.");
  }
}

function errorCallback(error) {
  document.getElementById("locationStatus").innerText = "";
  alert("Error getting location: " + error.message);
}
</script>

<!-- Include Google Maps API -->
<script async defer src="https://maps.googleapis.com/maps/api/js?key=AIzaSyBQ_S-MNLPXfeguaEQ1dOpww8vAo9bXJIw&libraries=places"></script>

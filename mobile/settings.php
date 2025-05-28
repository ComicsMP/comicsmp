<?php
// mobile/settings.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit;
}
require_once '../db_connection.php';

$userId = $_SESSION['user_id'];
// Fetch user data
$q = $conn->prepare("
  SELECT username,email,phone,city,bio,profile_picture,
         preferred_currency,default_max_radius,distance_unit,
         preferred_transaction,preferred_payment,
         facebook,twitter,instagram,latitude,longitude
    FROM users WHERE id = ?
");
$q->bind_param("i",$userId);
$q->execute();
$user = $q->get_result()->fetch_assoc();
$q->close();

// Explode comma‑lists
$user_tx  = $user['preferred_transaction'] ? explode(',',$user['preferred_transaction']) : [];
$user_pay = $user['preferred_payment']    ? explode(',',$user['preferred_payment'])    : [];

// Counts
$w = $conn->prepare("SELECT COUNT(*) AS c FROM wanted_items WHERE user_id=?");
$w->bind_param("i",$userId); $w->execute();
$wantedCount=(int)$w->get_result()->fetch_assoc()['c']; $w->close();
$s = $conn->prepare("SELECT COUNT(*) AS c FROM comics_for_sale WHERE user_id=?");
$s->bind_param("i",$userId); $s->execute();
$saleCount=(int)$s->get_result()->fetch_assoc()['c']; $s->close();

// Constants
$currencies  = ["USD","CAD","EUR","GBP","AUD","JPY","CNY","INR"];
$txMethods   = ["Shipping","Pickup","Meetup"];
$payMethods  = ["Cash","E-Transfer","PayPal","Trade"];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    * { box-sizing:border-box; margin:0; padding:0 }
    html,body { height:100%; background:#f2f2f2; font-family:'Segoe UI',sans-serif; }

    /* Top icon bar */
    .icon-bar {
      position:fixed; top:0; left:0; right:0;
      background:#fff; border-bottom:1px solid #ddd;
      display:flex; justify-content:space-around;
      padding:8px 0; z-index:100;
    }
    .icon-bar .tab {
      flex:1; text-align:center; opacity:.6;
      font-size:20px; padding:4px;
      cursor:pointer;
    }
    .icon-bar .active { opacity:1; color:#007bff; }

    /* Form container */
    .container { padding:72px 16px 120px; }

    .section { display:none; }
    .section.active { display:block; }

    .section h2 {
      font-size:1rem; font-weight:600; color:#333;
      margin-bottom:8px;
    }
    .field { margin-bottom:16px; }
    .field label {
      display:block; margin-bottom:4px;
      font-size:.9rem; color:#555;
    }
    .field input[type="text"],
    .field input[type="email"],
    .field input[type="password"],
    .field input[type="url"],
    .field select,
    .field textarea {
      width:100%; padding:8px;
      border:1px solid #ccc; border-radius:4px;
      font-size:.9rem;
    }
    .field textarea { resize:vertical; }

    .group-inline { display:flex; gap:8px; }
    .group-inline button {
      padding:8px; background:#007bff; color:#fff;
      border:none; border-radius:4px;
    }

    .checkbox-group {
      display:flex; flex-wrap:wrap; gap:12px;
      margin-top:8px;  /* added space from title */
    }
    .checkbox-group label {
      display:flex; align-items:center; gap:6px;
      font-size:.9rem;
    }

    .profile-pic {
      width:64px; height:64px;
      border-radius:50%; object-fit:cover;
      margin-bottom:8px;
    }

    /* Save bar */
    .btn-save {
      position:fixed; bottom:56px; left:0; right:0;
      background:#007bff; color:#fff;
      border:none; padding:14px 0;
      font-size:1rem; text-align:center;
    }

    /* Bottom navigation */
    .footer {
      position:fixed; bottom:0; left:0; right:0;
      height:56px; background:#000;
      display:flex; justify-content:space-around;
      align-items:center; z-index:100;
    }
    .footer a {
      flex:1; text-decoration:none; color:#fff;
      font-size:12px; text-align:center;
    }
    .footer i {
      display:block; font-size:18px;
      margin-bottom:0px;
    }

    /* Toast */
    .toast {
      position:fixed; top:60px; left:50%;
      transform:translateX(-50%);
      background:#28a745;color:#fff;
      padding:8px 16px;border-radius:4px;
      display:none; z-index:200; font-size:.9rem;
    }
  </style>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>

  <!-- Top icon bar -->
  <div class="icon-bar">
    <div class="tab active"       data-target="account"><i class="bi bi-person-fill"></i></div>
    <div class="tab" data-target="location"><i class="bi bi-geo-alt-fill"></i></div>
    <div class="tab" data-target="preferences"><i class="bi bi-currency-dollar"></i></div>
    <div class="tab" data-target="payments"><i class="bi bi-wallet2"></i></div>
    <div class="tab" data-target="social"><i class="bi bi-share-fill"></i></div>
    <div class="tab" data-target="notifications"><i class="bi bi-bell-fill"></i></div>
  </div>

  <div class="container">
    <form id="settingsForm" enctype="multipart/form-data">

      <!-- Account -->
      <div id="account" class="section active">
        <h2>Account</h2>
        <div class="field">
          <?php if ($user['profile_picture']): ?>
            <img src="../<?=htmlspecialchars($user['profile_picture'])?>" class="profile-pic" alt>
          <?php endif; ?>
          <input type="file" name="profile_picture">
        </div>
        <div class="field">
          <label>Nickname</label>
          <input type="text" name="nickname" value="<?=htmlspecialchars($user['username'])?>">
        </div>
        <div class="field">
          <label>Email</label>
          <input type="email" name="email" value="<?=htmlspecialchars($user['email'])?>">
        </div>
        <div class="field">
          <label>Phone</label>
          <input type="text" name="phone" value="<?=htmlspecialchars($user['phone'])?>">
        </div>
        <div class="field">
          <label>New Password</label>
          <input type="password" name="new_password">
        </div>
      </div>

      <!-- Location & Bio -->
      <div id="location" class="section">
        <h2>Location & Bio</h2>
        <div class="field">
          <label>City</label>
          <div class="group-inline">
            <input type="text" name="city" id="city" value="<?=htmlspecialchars($user['city'])?>">
            <button type="button" id="detect"><i class="bi bi-geo-alt"></i></button>
          </div>
        </div>
        <input type="hidden" name="latitude" id="latitude" value="<?=htmlspecialchars($user['latitude'])?>">
        <input type="hidden" name="longitude" id="longitude" value="<?=htmlspecialchars($user['longitude'])?>">
        <div class="field">
          <label>About Me</label>
          <textarea name="bio" rows="3"><?=htmlspecialchars($user['bio'])?></textarea>
        </div>
<div class="field">
  <label>Distance Unit</label>
  <select name="distance_unit">
    <option value="mi" <?= $user['distance_unit'] === 'mi' ? 'selected' : '' ?>>Miles</option>
    <option value="km" <?= $user['distance_unit'] === 'km' ? 'selected' : '' ?>>Kilometers</option>
  </select>
</div>
<div class="field" style="width:100%;">
  <label>Default Radius (<?= $user['distance_unit'] === 'km' ? 'km' : 'mi' ?>)</label>
  <input type="range" name="default_max_radius" min="5" max="1000" step="5"
         value="<?= htmlspecialchars($user['default_max_radius']) ?>"
         style="width:100%;"
         oninput="this.nextElementSibling.value=this.value">
  <output><?= htmlspecialchars($user['default_max_radius']) ?></output>
</div>

      </div>

      <!-- Preferences -->
      <div id="preferences" class="section">
        <h2>Preferences</h2>
        <div class="field">
          <label>Currency</label>
          <select name="preferred_currency">
            <?php foreach($currencies as $c): ?>
              <option value="<?=$c?>" <?=($user['preferred_currency']===$c?'selected':'')?>><?=$c?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

        


      <!-- Transactions & Payment -->
      <div id="payments" class="section">
        <h2>Transaction & Payment</h2>
        <div class="field">
          <label>Transaction Methods</label>
          <div class="checkbox-group">
            <?php foreach($txMethods as $m): ?>
              <label><input type="checkbox" name="preferred_transaction[]" value="<?=$m?>"
                <?=in_array($m,$user_tx)?'checked':''?>><?=$m?></label>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="field">
          <label>Payment Methods</label>
          <div class="checkbox-group">
            <?php foreach($payMethods as $m): ?>
              <label><input type="checkbox" name="preferred_payment[]" value="<?=$m?>"
                <?=in_array($m,$user_pay)?'checked':''?>><?=$m?></label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Social & Stats -->
      <div id="social" class="section">
        <h2>Social & Stats</h2>
        <div class="field">
          <label>Facebook</label>
          <input type="url" name="facebook" value="<?=htmlspecialchars($user['facebook'])?>">
        </div>
        <div class="field">
          <label>Twitter</label>
          <input type="url" name="twitter" value="<?=htmlspecialchars($user['twitter'])?>">
        </div>
        <div class="field">
          <label>Instagram</label>
          <input type="url" name="instagram" value="<?=htmlspecialchars($user['instagram'])?>">
        </div>
        <div class="field">
          <small>Listings: <?=$saleCount?> · Wanted: <?=$wantedCount?></small>
        </div>
      </div>

      <!-- Notifications -->
      <div id="notifications" class="section">
        <h2>Notifications (coming soon)</h2>
        <div class="checkbox-group">
          <label><input type="checkbox" disabled> Email</label>
          <label><input type="checkbox" disabled> SMS</label>
        </div>
      </div>

      <button type="submit" class="btn-save">Save Changes</button>
    </form>
  </div>

  <div class="footer">
    <a href="dashboard.php"><i class="bi bi-house-fill"></i>Home</a>
    <a href="wanted.php"><i class="bi bi-heart-fill"></i>Wanted</a>
    <a href="selling.php"><i class="bi bi-tag-fill"></i>Selling</a>
    <a href="matches.php"><i class="bi bi-people-fill"></i>Matches</a>
  </div>

  <div class="toast" id="toast"></div>

  <script>
    // Tab switching
    $('.icon-bar .tab').click(function(){
      $('.icon-bar .tab').removeClass('active');
      $(this).addClass('active');
      var tgt = $(this).data('target');
      $('.section').removeClass('active');
      $('#' + tgt).addClass('active');
    });

    // AJAX save
    function showToast(msg){
      $('#toast').text(msg).fadeIn(200).delay(2000).fadeOut(200);
    }
    $('#settingsForm').submit(function(e){
      e.preventDefault();
      var fd = new FormData(this);
      $.ajax({
        url: '../includes/profile_content_inner.php',
        method: 'POST',
        data: fd,
        contentType: false,
        processData: false,
        success:function(){ showToast('Profile updated'); },
        error:  function(){ showToast('Save failed'); }
      });
    });

    // Geo‑detect
    $('#detect').click(function(){
      if(!navigator.geolocation) return showToast('Not supported');
      navigator.geolocation.getCurrentPosition(pos=>{
        $('#latitude').val(pos.coords.latitude);
        $('#longitude').val(pos.coords.longitude);
        showToast('Coordinates set');
      },()=>showToast('Geo error'));
    });
  </script>
</body>
</html>

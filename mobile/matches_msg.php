<?php
session_start();
require_once 'db_connection.php';

// Ensure the user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo "Forbidden";
    exit;
}
$userId = $_SESSION['user_id'];

/**
 * Retrieve GET parameters.
 */
$recipientId = isset($_GET['to']) ? intval($_GET['to']) : 0;
$intent = isset($_GET['intent']) ? $_GET['intent'] : '';
$displayName = isset($_GET['displayname']) ? $_GET['displayname'] : 'Unknown User';

// Decode JSON arrays for buy_matches and sell_matches.
$buyMatches = [];
$sellMatches = [];
if (isset($_GET['buy_matches'])) {
    $buyMatches = json_decode($_GET['buy_matches'], true);
}
if (isset($_GET['sell_matches'])) {
    $sellMatches = json_decode($_GET['sell_matches'], true);
}

// Retrieve the recipient's preferred currency from users table.
$currency = '';
$stmt = $conn->prepare("SELECT preferred_currency FROM users WHERE id = ?");
$stmt->bind_param("i", $recipientId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $currency = $row['preferred_currency'];
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- Disable pinch/zoom -->
  <meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=no">
  <title>Message <?php echo htmlspecialchars($displayName); ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
  <style>
    body { padding: 20px; background: #f4f4f4; }
    .form-check { margin-bottom: 5px; }
  </style>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
  <div class="container">
    <h4 class="mb-3">Message <?php echo htmlspecialchars($displayName); ?></h4>
    <p class="small text-muted">
      Please select the issues you wish to reference. The subject and message will auto-populate.
    </p>
    <form id="matchesMessageForm">
      <input type="hidden" name="recipient_id" value="<?php echo $recipientId; ?>">
      
      <?php if (!empty($buyMatches)): ?>
      <div class="mb-3">
        <h6 class="text-secondary">Buy From <?php echo htmlspecialchars($displayName); ?></h6>
        <?php foreach ($buyMatches as $index => $m): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="selected_issues_buy[]" value="<?php echo $index; ?>" id="issue-buy-<?php echo $index; ?>">
            <label class="form-check-label" for="issue-buy-<?php echo $index; ?>">
              <?php
              // Retrieve comic_condition and price.
              $cond = isset($m['comic_condition']) ? $m['comic_condition'] : '';
              $price = isset($m['price']) ? $m['price'] : 'N/A';
              // If comic_condition is missing, attempt to fetch it from comics_for_sale table using issue_url.
              if(empty($cond)) {
                  $issue_url = isset($m['issue_url']) ? $m['issue_url'] : '';
                  if (!empty($issue_url)) {
                      $stmt = $conn->prepare("SELECT comic_condition, price FROM comics_for_sale WHERE issue_url = ?");
                      $stmt->bind_param("s", $issue_url);
                      $stmt->execute();
                      $res = $stmt->get_result();
                      if($row = $res->fetch_assoc()){
                          $cond = $row['comic_condition'] ?? '';
                          $price = $row['price'] ?? 'N/A';
                      }
                      $stmt->close();
                  }
              }
              echo htmlspecialchars(
                  ($m['comic_title'] ?? '') . " Issue " . 
                  ($m['issue_number'] ?? '') . " (" . 
                  ($m['years'] ?? '') . ") - " . 
                  "Condition: " . $cond . ", " . 
                  "Price: " . (!empty($price) && $price != 'N/A' ? '$'.number_format($price,2).' '.$currency : 'N/A')
              );
              ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      
      <?php if (!empty($sellMatches)): ?>
      <div class="mb-3">
        <h6 class="text-secondary">Sell To <?php echo htmlspecialchars($displayName); ?></h6>
        <?php foreach ($sellMatches as $index => $m): ?>
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="selected_issues_sell[]" value="<?php echo $index; ?>" id="issue-sell-<?php echo $index; ?>">
            <label class="form-check-label" for="issue-sell-<?php echo $index; ?>">
              <?php
              $cond = isset($m['comic_condition']) ? $m['comic_condition'] : '';
              $price = isset($m['price']) ? $m['price'] : 'N/A';
              if(empty($cond)) {
                  $issue_url = isset($m['issue_url']) ? $m['issue_url'] : '';
                  if (!empty($issue_url)) {
                      $stmt = $conn->prepare("SELECT comic_condition, price FROM comics_for_sale WHERE issue_url = ?");
                      $stmt->bind_param("s", $issue_url);
                      $stmt->execute();
                      $res = $stmt->get_result();
                      if($row = $res->fetch_assoc()){
                          $cond = $row['comic_condition'] ?? '';
                          $price = $row['price'] ?? 'N/A';
                      }
                      $stmt->close();
                  }
              }
              echo htmlspecialchars(
                  ($m['comic_title'] ?? '') . " Issue " . 
                  ($m['issue_number'] ?? '') . " (" . 
                  ($m['years'] ?? '') . ") - " . 
                  "Condition: " . $cond . ", " . 
                  "Price: " . (!empty($price) && $price != 'N/A' ? '$'.number_format($price,2).' '.$currency : 'N/A')
              );
              ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      
      <div class="mb-3">
        <label for="subject" class="form-label">Subject</label>
        <input type="text" class="form-control" name="subject" id="subject" placeholder="Enter subject">
      </div>
      
      <div class="mb-3">
        <label for="message" class="form-label">Message</label>
        <textarea class="form-control" name="message" id="message" rows="4" placeholder="Enter your message here"></textarea>
      </div>
      
      <button type="submit" class="btn btn-primary">Send Message</button>
    </form>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto-populate default subject and message based on checkbox selections.
    function updateDefaultText() {
      var intent = "<?php echo $intent; ?>";
      var displayName = "<?php echo addslashes($displayName); ?>";
      var subjectField = $("#subject");
      var messageField = $("#message");
      
      var selectedBuy = [];
      $('input[name="selected_issues_buy[]"]:checked').each(function(){
        var labelText = $(this).siblings("label").text();
        selectedBuy.push(labelText);
      });
      
      var selectedSell = [];
      $('input[name="selected_issues_sell[]"]:checked').each(function(){
        var labelText = $(this).siblings("label").text();
        selectedSell.push(labelText);
      });
      
      var overallIntent = intent;
      if(selectedBuy.length > 0 && selectedSell.length === 0) {
        overallIntent = "buy";
      } else if(selectedSell.length > 0 && selectedBuy.length === 0) {
        overallIntent = "sell";
      } else if(selectedBuy.length > 0 && selectedSell.length > 0) {
        overallIntent = "buy_sell";
      }
      
      var defaultSubject = "";
      if(overallIntent === "buy") {
        defaultSubject = "Inquiry: Interested in Purchasing Matched Comics";
      } else if(overallIntent === "sell") {
        defaultSubject = "Inquiry: Interested in Selling Matched Comics";
      } else {
        defaultSubject = "Inquiry: Buy & Sell Inquiry for Matched Comics";
      }
      subjectField.val(defaultSubject);
      
      var defaultMessage = "Hello " + displayName + ",\n\n";
      if(overallIntent === "buy") {
        defaultMessage += "I am interested in purchasing the following issues:\n\n";
        defaultMessage += selectedBuy.join("\n") + "\n\n";
      } else if(overallIntent === "sell") {
        defaultMessage += "I am interested in selling the following issues:\n\n";
        defaultMessage += selectedSell.join("\n") + "\n\n";
      } else {
        defaultMessage += "I am interested in both buying and selling the following issues:\n\n";
        if(selectedBuy.length > 0) {
          defaultMessage += "Buy:\n" + selectedBuy.join("\n") + "\n\n";
        }
        if(selectedSell.length > 0) {
          defaultMessage += "Sell:\n" + selectedSell.join("\n") + "\n\n";
        }
      }
      defaultMessage += "Please let me know if you are interested.\n\nThank you.";
      messageField.val(defaultMessage);
    }
    
    $(document).on("change", 'input[type="checkbox"]', function(){
      updateDefaultText();
    });
    
    $("#matchesMessageForm").on("submit", function(e){
      e.preventDefault();
      var formData = $(this).serialize();
      $.ajax({
        url: "../sendMessage.php",
        method: "POST",
        data: formData,
        dataType: "json",
        success: function(response) {
          if(response.status === 'success'){
            alert("Message sent successfully.");
            $("#matchesMessageForm")[0].reset();
            window.location.href = "matches.php";
          } else {
            alert("Error: " + response.message);
          }
        },
        error: function(){
          alert("Failed to send message.");
        }
      });
    });
    
    $(document).ready(function(){
      updateDefaultText();
    });
  </script>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/../db_connection.php';

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
$intent      = isset($_GET['intent']) ? $_GET['intent'] : '';
$displayName = isset($_GET['displayname']) ? $_GET['displayname'] : 'Unknown User';

// Decode JSON arrays for buy_matches and sell_matches.
$buyMatches  = isset($_GET['buy_matches'])  ? json_decode($_GET['buy_matches'],  true) : [];
$sellMatches = isset($_GET['sell_matches']) ? json_decode($_GET['sell_matches'], true) : [];

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
    .form-check { margin-bottom: .75rem; }
    .form-check-label { margin-left: .5rem; }
    .section {
      background: #fff;
      border: 1px solid #ddd;
      border-radius: .25rem;
      padding: 1rem;
      margin-bottom: 1.5rem;
    }
    .section h6 { margin-bottom: .75rem; font-weight: 600; }
    #message { min-height: 250px; }
  </style>
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
</head>
<body>
  <div class="container">
    <h4 class="mb-3">Message <?php echo htmlspecialchars($displayName); ?></h4>
    <p class="small text-muted">Please select the issues you wish to reference. The subject and message will auto‑populate.</p>
    <form id="matchesMessageForm">
      <input type="hidden" name="recipient_id" value="<?php echo $recipientId; ?>">

      <?php if (!empty($buyMatches)): ?>
      <div class="section">
        <h6>Buy From <?php echo htmlspecialchars($displayName); ?></h6>
        <?php foreach ($buyMatches as $i => $m): ?>
          <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   name="selected_issues_buy[]"
                   value="<?php echo $i; ?>"
                   id="issue-buy-<?php echo $i; ?>"
            >
            <label class="form-check-label" for="issue-buy-<?php echo $i; ?>">
              <?php
                $cond  = $m['comic_condition'] ?? '';
                $price = $m['price']             ?? 'N/A';
                if (empty($cond) && !empty($m['issue_url'])) {
                  $stmt2 = $conn->prepare("SELECT comic_condition, price FROM comics_for_sale WHERE issue_url = ?");
                  $stmt2->bind_param("s", $m['issue_url']);
                  $stmt2->execute();
                  $r = $stmt2->get_result()->fetch_assoc();
                  $cond  = $r['comic_condition'] ?? '';
                  $price = $r['price']           ?? 'N/A';
                  $stmt2->close();
                }
                echo htmlspecialchars(
                  ($m['comic_title'] ?? '') . " Issue " .
                  ($m['issue_number'] ?? '') . " (" .
                  ($m['years'] ?? '') . ") – Condition: " .
                  $cond . ", Price: " .
                  ($price !== 'N/A'
                    ? '$'.number_format($price,2).' '.htmlspecialchars($currency)
                    : 'N/A'
                  )
                );
              ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($sellMatches)): ?>
      <div class="section">
        <h6>Sell To <?php echo htmlspecialchars($displayName); ?></h6>
        <?php foreach ($sellMatches as $i => $m): ?>
          <div class="form-check">
            <input class="form-check-input"
                   type="checkbox"
                   name="selected_issues_sell[]"
                   value="<?php echo $i; ?>"
                   id="issue-sell-<?php echo $i; ?>"
            >
            <label class="form-check-label" for="issue-sell-<?php echo $i; ?>">
              <?php
                $cond  = $m['comic_condition'] ?? '';
                $price = $m['price']             ?? 'N/A';
                if (empty($cond) && !empty($m['issue_url'])) {
                  $stmt2 = $conn->prepare("SELECT comic_condition, price FROM comics_for_sale WHERE issue_url = ?");
                  $stmt2->bind_param("s", $m['issue_url']);
                  $stmt2->execute();
                  $r = $stmt2->get_result()->fetch_assoc();
                  $cond  = $r['comic_condition'] ?? '';
                  $price = $r['price']           ?? 'N/A';
                  $stmt2->close();
                }
                echo htmlspecialchars(
                  ($m['comic_title'] ?? '') . " Issue " .
                  ($m['issue_number'] ?? '') . " (" .
                  ($m['years'] ?? '') . ") – Condition: " .
                  $cond . ", Price: " .
                  ($price !== 'N/A'
                    ? '$'.number_format($price,2).' '.htmlspecialchars($currency)
                    : 'N/A'
                  )
                );
              ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <div class="mb-3">
        <label for="subject" class="form-label">Subject</label>
        <input type="text"
               class="form-control"
               name="subject"
               id="subject"
               placeholder="Enter subject"
        >
      </div>

      <div class="mb-3">
        <label for="message" class="form-label">Message</label>
        <textarea class="form-control"
                  name="message"
                  id="message"
                  rows="10"
                  placeholder="Enter your message here"
        ></textarea>
      </div>

      <button type="submit" class="btn btn-primary">Send Message</button>
      <button type="button" class="btn btn-secondary ms-2" id="cancelBtn">Cancel</button>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Auto‑populate default subject and message based on checkboxes
    function updateDefaultText() {
      var intent      = "<?php echo $intent ?>",
          displayName = "<?php echo addslashes($displayName) ?>",
          sub         = $("#subject"),
          msg         = $("#message"),
          buy         = [],
          sell        = [];

      // trim() here removes any leading spaces from your HTML indentation
      $('input[name="selected_issues_buy[]"]:checked').each(function(){
        buy.push($(this).siblings("label").text().trim());
      });
      $('input[name="selected_issues_sell[]"]:checked').each(function(){
        sell.push($(this).siblings("label").text().trim());
      });

      var overall = intent;
      if (buy.length && !sell.length)      overall = "buy";
      else if (sell.length && !buy.length) overall = "sell";
      else if (buy.length && sell.length)  overall = "buy_sell";

      // set subject
      var subjText = overall === "buy"
        ? "Inquiry: Interested in Purchasing Matched Comics"
        : overall === "sell"
          ? "Inquiry: Interested in Selling Matched Comics"
          : "Inquiry: Buy & Sell Inquiry for Matched Comics";
      sub.val(subjText);

      // build bullet list
      var body = "Hello " + displayName + ",\n\n";
      body += overall === "buy"
        ? "I am interested in purchasing the following issues:\n"
        : overall === "sell"
          ? "I am interested in selling the following issues:\n"
          : "I am interested in both buying and selling the following issues:\n";

      var list = overall === "buy"   ? buy
               : overall === "sell"  ? sell
               : buy.concat(sell);
      if (list.length) {
        body += "- " + list.join("\n- ") + "\n\n";
      }
      body += "Please let me know if you are interested.\n\nThank you.";
      msg.val(body);
    }

    $(document).on("change", 'input[type="checkbox"]', updateDefaultText);

    $("#matchesMessageForm").on("submit", function(e){
      e.preventDefault();
      $.ajax({
        url: "../sendMessage.php",
        method: "POST",
        data: $(this).serialize(),
        dataType: "json",
        success: function(res){
          if (res.status === 'success') {
            alert("Message sent successfully.");
            $("#matchesMessageForm")[0].reset();
            window.location.href = "/comicsmp/dashboard.php?tab=matches";
          } else {
            alert("Error: " + res.message);
          }
        },
        error: function(){
          alert("Failed to send message.");
        }
      });
    });

    $("#cancelBtn").on("click", function(){
      history.back();
    });

    $(document).ready(updateDefaultText);
  </script>
</body>
</html>

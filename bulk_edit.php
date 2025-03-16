<?php
// custom_year_grouped_condensed_full_auto_manual.php

// Database Connection Settings
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "comics_db";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$msg = "";

/**
 * Helper function to extract a year from a date string.
 * Assumes the date is in a format like "April 2022" or "July 2024".
 */
function extractYear($dateStr) {
    return substr(trim($dateStr), -4);
}

/**
 * Bulk update using computed year from the checkbox value.
 * The checkbox value holds the string "ids_str||computed_year".
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['approve_full_auto']) && isset($_POST['group_ids'])) {
    foreach ($_POST['group_ids'] as $group) {
        $parts = explode("||", $group);
        if (count($parts) === 2) {
            list($ids_str, $computed_year) = $parts;
            $computed_year = $conn->real_escape_string($computed_year);
            $sql_update = "UPDATE comics SET Years = '$computed_year' WHERE id IN ($ids_str)";
            $conn->query($sql_update);
            $msg .= "Updated " . $conn->affected_rows . " records with computed year $computed_year.<br>";
        }
    }
}

/**
 * Bulk update using manual new year input.
 * The manual update uses the provided new_year for all selected groups.
 */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['apply_manual']) && isset($_POST['group_ids']) && !empty($_POST['new_year'])) {
    $new_year = $conn->real_escape_string(trim($_POST['new_year']));
    $all_ids = [];
    foreach ($_POST['group_ids'] as $group) {
        // In manual mode, the checkbox value only contains the ids (or you can send the computed value too, but ignore it)
        $parts = explode("||", $group);
        $ids_str = $parts[0];
        $ids = explode(",", $ids_str);
        foreach ($ids as $id) {
            $all_ids[] = intval($id);
        }
    }
    $all_ids = array_unique($all_ids);
    if (!empty($all_ids)) {
        $ids_str = implode(",", $all_ids);
        $sql_update = "UPDATE comics SET Years = '$new_year' WHERE id IN ($ids_str)";
        $conn->query($sql_update);
        $msg .= "Manually updated " . $conn->affected_rows . " records to year span $new_year.<br>";
    }
}

// Fetch comic groups by year (search by provided year)
$groups = [];
$check_year = "";
if (isset($_POST['search_by_year']) && !empty($_POST['check_year'])) {
    $check_year = trim($_POST['check_year']);
    // The target format is assumed to be "YYYY-"
    $target = $check_year . "-";
    
    // Use a prepared statement to fetch records
    $stmt = $conn->prepare("SELECT id, Comic_Title, Issue_Number, Years, Date FROM comics WHERE Years = ? ORDER BY Comic_Title, Issue_Number");
    if ($stmt) {
        $stmt->bind_param("s", $target);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $groups[$row['Comic_Title']][] = $row;
        }
        $stmt->close();
    } else {
        $msg = "Query preparation failed: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Custom Year Grouped Bulk Edit - Auto & Manual</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2 { margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f4f4f4; }
    .bulk-btn { padding: 10px 15px; background-color: darkgreen; color: #fff; border: none; cursor: pointer; margin-right: 5px; }
    .bulk-btn:hover { background-color: green; }
    .manual-input { margin-bottom: 15px; }
  </style>
  <script>
    // Toggle all checkboxes in the group
    function toggleAll(source) {
      var checkboxes = document.getElementsByName('group_ids[]');
      for (var i = 0; i < checkboxes.length; i++) {
          checkboxes[i].checked = source.checked;
      }
    }
  </script>
</head>
<body>

<h2>Custom Year Grouped Bulk Edit - Auto & Manual Correction</h2>

<?php if ($msg != "") { echo "<p><strong>" . htmlspecialchars($msg) . "</strong></p>"; } ?>

<!-- Search Form -->
<form method="POST">
  <label for="check_year">Enter Year to Search (records with Years exactly "YYYY-"):</label>
  <input type="text" name="check_year" id="check_year" value="<?= htmlspecialchars($check_year) ?>" required>
  <button type="submit" name="search_by_year" class="bulk-btn">Search by Year</button>
</form>

<?php if (!empty($groups)) { ?>
  <!-- Bulk Update Form with both Auto and Manual options -->
  <form method="POST">
    <input type="hidden" name="check_year" value="<?= htmlspecialchars($check_year) ?>">
    <div class="manual-input">
      <label for="new_year">New Year to Update (for Manual Correction):</label>
      <input type="text" name="new_year" id="new_year" placeholder="e.g., 2022-2023">
    </div>
    <table>
      <tr>
        <th><input type="checkbox" onclick="toggleAll(this)"></th>
        <th>Comic Title</th>
        <th>Issue Count</th>
        <th>First Issue Date</th>
        <th>Last Issue Date</th>
        <th>Issues (ID and Issue Number)</th>
        <th>Computed Year</th>
      </tr>
      <?php 
      foreach ($groups as $comic_title => $issues) { 
          $ids = [];
          $issueSummary = [];
          foreach ($issues as $issue) {
              $ids[] = $issue['id'];
              $issueSummary[] = $issue['id'] . " (#" . htmlspecialchars($issue['Issue_Number']) . ")";
          }
          $ids_str = implode(",", $ids);
          $issueSummary_str = implode(", ", $issueSummary);
          $first_issue_date = reset($issues)['Date'];
          $last_issue_date = end($issues)['Date'];
          $first_year = extractYear($first_issue_date);
          $last_year  = extractYear($last_issue_date);
          $computed_year = $first_year . "-" . $last_year;
          // Checkbox value contains both IDs and computed year for auto update, separated by "||"
      ?>
        <tr>
          <td>
            <input type="checkbox" name="group_ids[]" value="<?= htmlspecialchars($ids_str . "||" . $computed_year) ?>">
          </td>
          <td><?= htmlspecialchars($comic_title) ?></td>
          <td><?= count($issues) ?></td>
          <td><?= htmlspecialchars($first_issue_date) ?></td>
          <td><?= htmlspecialchars($last_issue_date) ?></td>
          <td><?= $issueSummary_str ?></td>
          <td><?= htmlspecialchars($computed_year) ?></td>
        </tr>
      <?php } ?>
    </table>
    <!-- Two buttons: one for auto correction and one for manual update -->
    <button type="submit" name="approve_full_auto" class="bulk-btn">Approve Full Auto Correction</button>
    <button type="submit" name="apply_manual" class="bulk-btn">Apply Manual Correction</button>
  </form>
<?php } ?>
</body>
</html>
<?php $conn->close(); ?>

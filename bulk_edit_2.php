<?php
// bulk_edit.php

// Database Connection Settings
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "comics_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$msg = "";
$groups = [];
$search_field = "";
$search_value = "";

// Handle search via dropdown and text box
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_value']) && isset($_POST['search_field'])) {
    $search_field = $conn->real_escape_string($_POST['search_field']);
    $search_value = $conn->real_escape_string(trim($_POST['search_value']));
    
    // Allowed fields for security – adjust as needed
    $allowed_fields = ['Comic_Title', 'Issue_Number', 'Years', 'Volume', 'Country', 'Publisher_Name'];
    if (!in_array($search_field, $allowed_fields)) {
        die("Invalid search field.");
    }
    
    // Search using a LIKE query (you can change to exact match if desired)
    $sql = "SELECT id, Comic_Title, Issue_Number, Years, Volume, Country, Publisher_Name 
            FROM comics 
            WHERE $search_field LIKE '%$search_value%' 
            ORDER BY Comic_Title, Issue_Number";
    $result = $conn->query($sql);
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            // Group results by Comic_Title
            $groups[$row['Comic_Title']][] = $row;
        }
    }
}

// Handle Bulk Update if groups are selected
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['group_ids']) && isset($_POST['new_value']) && isset($_POST['update_field'])) {
    $all_ids = [];
    foreach($_POST['group_ids'] as $group) {
        // Each checkbox value is a comma‐separated string of IDs
        $ids = explode(",", $group);
        foreach($ids as $id) {
            $all_ids[] = intval($id);
        }
    }
    $all_ids = array_unique($all_ids);
    $update_field = $conn->real_escape_string($_POST['update_field']);
    $new_value = $conn->real_escape_string($_POST['new_value']);
    
    // Validate update field
    $allowed_fields = ['Comic_Title', 'Issue_Number', 'Years', 'Volume', 'Country', 'Publisher_Name'];
    if (!in_array($update_field, $allowed_fields)) {
        die("Invalid update field.");
    }
    
    if (!empty($all_ids)) {
        $ids_str = implode(",", $all_ids);
        $sql_update = "UPDATE comics SET $update_field = '$new_value' WHERE id IN ($ids_str)";
        $conn->query($sql_update);
        $msg = "Updated " . $conn->affected_rows . " records.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Bulk Edit Comics</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2, h3 { margin-bottom: 10px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
    th { background-color: #f4f4f4; }
    .bulk-btn { padding: 10px 15px; background-color: darkgreen; color: #fff; border: none; cursor: pointer; }
    .bulk-btn:hover { background-color: green; }
  </style>
  <script>
    function toggleAll(source) {
      var checkboxes = document.getElementsByName('group_ids[]');
      for (var i = 0; i < checkboxes.length; i++) {
          checkboxes[i].checked = source.checked;
      }
    }
  </script>
</head>
<body>

<h2>Bulk Edit Comics</h2>

<!-- Display update messages if any -->
<?php if ($msg != "") { echo "<p><strong>$msg</strong></p>"; } ?>

<!-- Search Form -->
<h3>Search Comics</h3>
<form method="POST">
  <label for="search_field">Select Field:</label>
  <select name="search_field" id="search_field" required>
    <option value="Comic_Title" <?= ($search_field=='Comic_Title')?'selected':''; ?>>Comic Title</option>
    <option value="Issue_Number" <?= ($search_field=='Issue_Number')?'selected':''; ?>>Issue Number</option>
    <option value="Years" <?= ($search_field=='Years')?'selected':''; ?>>Years</option>
    <option value="Volume" <?= ($search_field=='Volume')?'selected':''; ?>>Volume</option>
    <option value="Country" <?= ($search_field=='Country')?'selected':''; ?>>Country</option>
    <option value="Publisher_Name" <?= ($search_field=='Publisher_Name')?'selected':''; ?>>Publisher Name</option>
  </select>
  <br><br>
  <label for="search_value">Enter Search Value:</label>
  <input type="text" name="search_value" id="search_value" value="<?= htmlspecialchars($search_value) ?>" required>
  <br><br>
  <button type="submit" class="bulk-btn">Search</button>
</form>

<?php if (!empty($groups)) { ?>
  <h3>Search Results</h3>
  <form method="POST">
    <!-- Preserve search criteria -->
    <input type="hidden" name="search_field" value="<?= htmlspecialchars($search_field) ?>">
    <input type="hidden" name="search_value" value="<?= htmlspecialchars($search_value) ?>">
    <table>
      <tr>
        <th><input type="checkbox" onclick="toggleAll(this)"></th>
        <th>Comic Title</th>
        <th>Issue Count</th>
        <th>Issues (ID and Issue Number)</th>
      </tr>
      <?php 
      foreach ($groups as $comic_title => $issues) { 
          // Build a comma-separated string of IDs and a summary of issues
          $ids = array();
          $issueSummary = array();
          foreach ($issues as $issue) {
              $ids[] = $issue['id'];
              $issueSummary[] = $issue['id'] . " (#" . htmlspecialchars($issue['Issue_Number']) . ")";
          }
          $ids_str = implode(",", $ids);
          $issueSummary_str = implode(", ", $issueSummary);
      ?>
        <tr>
          <td><input type="checkbox" name="group_ids[]" value="<?= htmlspecialchars($ids_str) ?>"></td>
          <td><?= htmlspecialchars($comic_title) ?></td>
          <td><?= count($issues) ?></td>
          <td><?= $issueSummary_str ?></td>
        </tr>
      <?php } ?>
    </table>
    <h3>Bulk Update</h3>
    <p>
      <label for="update_field">Select Field to Update:</label>
      <select name="update_field" id="update_field" required>
        <option value="Comic_Title">Comic Title</option>
        <option value="Issue_Number">Issue Number</option>
        <option value="Years">Years</option>
        <option value="Volume">Volume</option>
        <option value="Country">Country</option>
        <option value="Publisher_Name">Publisher Name</option>
      </select>
    </p>
    <p>
      <label for="new_value">New Value:</label>
      <input type="text" name="new_value" id="new_value" required>
    </p>
    <button type="submit" class="bulk-btn">Update Selected Groups</button>
  </form>
<?php } else if(isset($_POST['search_value'])) { ?>
  <p>No records found for <?= htmlspecialchars($search_field) ?> matching "<?= htmlspecialchars($search_value) ?>"</p>
<?php } ?>

</body>
</html>
<?php
$conn->close();
?>

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
$search_fields = isset($_POST['search_field']) ? (array)$_POST['search_field'] : [];
$search_values = isset($_POST['search_value']) ? (array)$_POST['search_value'] : [];


// Handle Search
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_field']) && isset($_POST['search_value'])) {
    $allowed_fields = ['Comic_Title', 'Issue_Number', 'Years', 'Volume', 'Country', 'Publisher_Name'];
    $where_clauses = [];

    for ($i = 0; $i < count($search_fields); $i++) {
        $field = $conn->real_escape_string($search_fields[$i]);
        $value = $conn->real_escape_string(trim($search_values[$i]));
        if (in_array($field, $allowed_fields) && $value !== '') {
            $where_clauses[] = "$field LIKE '%$value%'";
        }
    }

    if (!empty($where_clauses)) {
        $where_sql = implode(" AND ", $where_clauses);
        $sql = "SELECT id, Comic_Title, Issue_Number, Years, Volume, Country, Publisher_Name 
                FROM comics 
                WHERE $where_sql 
                ORDER BY Comic_Title, Issue_Number";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $groups[$row['Comic_Title']][] = $row;
            }
        }
    }
}

// Handle Bulk Update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['group_ids']) && isset($_POST['new_value']) && isset($_POST['update_field'])) {
    $all_ids = [];
    foreach($_POST['group_ids'] as $group) {
        $ids = explode(",", $group);
        foreach($ids as $id) {
            $all_ids[] = intval($id);
        }
    }
    $all_ids = array_unique($all_ids);
    $update_field = $conn->real_escape_string($_POST['update_field']);
    $new_value = $conn->real_escape_string($_POST['new_value']);
    
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
    .pair-row { display: flex; gap: 10px; margin-bottom: 10px; }
    .pair-row select, .pair-row input { padding: 6px; }
  </style>
  <script>
    function toggleAll(source) {
      const checkboxes = document.getElementsByName('group_ids[]');
      checkboxes.forEach(checkbox => checkbox.checked = source.checked);
    }

    function addSearchPair() {
      const container = document.getElementById('search-pairs');
      const pair = document.createElement('div');
      pair.className = 'pair-row';
      pair.innerHTML = `
        <select name="search_field[]" required>
          <option value="Comic_Title">Comic Title</option>
          <option value="Issue_Number">Issue Number</option>
          <option value="Years">Years</option>
          <option value="Volume">Volume</option>
          <option value="Country">Country</option>
          <option value="Publisher_Name">Publisher Name</option>
        </select>
        <input type="text" name="search_value[]" required>
        <button type="button" onclick="this.parentElement.remove()">Remove</button>
      `;
      container.appendChild(pair);
    }
  </script>
</head>
<body>

<h2>Bulk Edit Comics</h2>

<?php if ($msg != "") { echo "<p><strong>$msg</strong></p>"; } ?>

<h3>Search Comics</h3>
<form method="POST">
  <div id="search-pairs">
    <?php
    if (!empty($search_fields) && is_array($search_fields)) {
        for ($i = 0; $i < count($search_fields); $i++) {
            echo '<div class="pair-row">';
            echo '<select name="search_field[]" required>';
            foreach (['Comic_Title', 'Issue_Number', 'Years', 'Volume', 'Country', 'Publisher_Name'] as $field) {
                $selected = ($field == $search_fields[$i]) ? 'selected' : '';
                echo "<option value=\"$field\" $selected>$field</option>";
            }
            echo '</select>';
            $value = htmlspecialchars($search_values[$i]);
            echo "<input type=\"text\" name=\"search_value[]\" value=\"$value\" required>";
            echo '<button type="button" onclick="this.parentElement.remove()">Remove</button>';
            echo '</div>';
        }
    } else {
        echo '<div class="pair-row">
          <select name="search_field[]" required>
            <option value="Comic_Title">Comic Title</option>
            <option value="Issue_Number">Issue Number</option>
            <option value="Years">Years</option>
            <option value="Volume">Volume</option>
            <option value="Country">Country</option>
            <option value="Publisher_Name">Publisher Name</option>
          </select>
          <input type="text" name="search_value[]" required>
          <button type="button" onclick="this.parentElement.remove()">Remove</button>
        </div>';
    }
    ?>
  </div>
  <button type="button" onclick="addSearchPair()">+ Add More Fields</button>
  <br><br>
  <button type="submit" class="bulk-btn">Search</button>
</form>

<?php if (!empty($groups)) { ?>
  <h3>Search Results</h3>
  <form method="POST">
    <?php
    foreach ($search_fields as $i => $field) {
        echo '<input type="hidden" name="search_field[]" value="' . htmlspecialchars($field) . '">';
        echo '<input type="hidden" name="search_value[]" value="' . htmlspecialchars($search_values[$i]) . '">';
    }
    ?>
    <table>
      <tr>
        <th><input type="checkbox" onclick="toggleAll(this)"></th>
        <th>Comic Title</th>
        <th>Issue Count</th>
        <th>Issues (ID and Issue Number)</th>
      </tr>
      <?php 
      foreach ($groups as $comic_title => $issues) { 
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
<?php } elseif ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_field'])) { ?>
  <p>No records found for search criteria.</p>
<?php } ?>

</body>
</html>
<?php
$conn->close();
?>

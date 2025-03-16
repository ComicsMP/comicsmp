<?php
// compare_duplicates.php

// Database Connection Settings
$servername = "localhost";
$username   = "root";
$password   = "";
$dbname     = "comics_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// AJAX Deletion Endpoint
if(isset($_POST['action']) && $_POST['action'] == 'delete') {
    $ids = $_POST['ids'];
    if(is_array($ids)) {
        $ids = array_map('intval', $ids);
        $ids_str = implode(",", $ids);
        $sql = "DELETE FROM comics WHERE id IN ($ids_str)";
        if($conn->query($sql) === TRUE) {
            echo json_encode(['success'=>true, 'deleted'=>$conn->affected_rows]);
        } else {
            echo json_encode(['success'=>false, 'error'=>$conn->error]);
        }
    } else {
        echo json_encode(['success'=>false, 'error'=>'No IDs provided']);
    }
    exit;
}

// Determine the search mode: by Issue_URL (default) or by Unique_ID
$mode = isset($_GET['mode']) && $_GET['mode'] == 'unique_id' ? 'unique_id' : 'issue_url';

// Get duplicate keys
$duplicates = [];
if($mode == 'issue_url'){
    $sql = "SELECT Issue_URL, COUNT(*) as cnt FROM comics GROUP BY Issue_URL HAVING cnt > 1 ORDER BY Issue_URL";
} else {
    $sql = "SELECT Unique_ID, COUNT(*) as cnt FROM comics GROUP BY Unique_ID HAVING cnt > 1 ORDER BY Unique_ID";
}
$result = $conn->query($sql);
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $key = ($mode == 'issue_url') ? $row['Issue_URL'] : $row['Unique_ID'];
        $duplicates[$key] = $row['cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Compare Duplicate Records</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .tab { margin-bottom: 20px; }
    .tab a { margin-right: 15px; text-decoration: none; font-weight: bold; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    th, td { border: 1px solid #ccc; padding: 8px; }
    th { background-color: #f4f4f4; }
    .toggle-btn { color: blue; cursor: pointer; text-decoration: underline; }
    .details { margin: 10px 0; display: none; }
    .delete-btn { padding: 5px 10px; background-color: red; color: white; border: none; cursor: pointer; }
    .delete-btn:hover { background-color: darkred; }
  </style>
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
  <script>
  $(document).ready(function(){
      // Toggle details display
      $('.toggle-btn').click(function(){
          var target = $(this).data('target');
          $('#' + target).toggle();
      });
      
      // Bulk deletion via AJAX
      $('.bulk-delete-btn').click(function(){
          var group = $(this).data('group');
          var checkboxes = $('input[name="dup_'+group+'[]"]:checked');
          if(checkboxes.length === 0) {
              alert("Please select at least one record to delete.");
              return;
          }
          var ids = [];
          checkboxes.each(function(){ ids.push($(this).val()); });
          if(confirm("Are you sure you want to delete the selected records?")) {
              $.ajax({
                  url: '',
                  type: 'POST',
                  data: { action: 'delete', ids: ids },
                  dataType: 'json',
                  success: function(response){
                      if(response.success){
                          // Remove deleted rows
                          checkboxes.each(function(){
                              $(this).closest('tr').fadeOut(500, function(){ $(this).remove(); });
                          });
                      } else {
                          alert("Error deleting records: " + response.error);
                      }
                  }
              });
          }
      });
  });
  </script>
</head>
<body>

<h2>Compare Duplicate Records</h2>
<div class="tab">
  <a href="?mode=issue_url" <?php if($mode == 'issue_url') echo 'style="color:blue;"'; ?>>By Issue_URL</a>
  <a href="?mode=unique_id" <?php if($mode == 'unique_id') echo 'style="color:blue;"'; ?>>By Unique_ID</a>
</div>

<?php if(empty($duplicates)) { ?>
    <p>No duplicates found in mode <?php echo $mode; ?>.</p>
<?php } else { ?>
    <table>
        <tr>
            <th><?php echo ($mode == 'issue_url') ? 'Issue_URL' : 'Unique_ID'; ?></th>
            <th>Duplicate Count</th>
            <th>Action</th>
        </tr>
        <?php foreach($duplicates as $dupKey => $count){ 
            // Create a safe group id from key
            $groupId = preg_replace('/\W+/', '_', $dupKey);
        ?>
        <tr>
            <td><?php echo htmlspecialchars($dupKey); ?></td>
            <td><?php echo $count; ?></td>
            <td><span class="toggle-btn" data-target="group_<?php echo $groupId; ?>">Show Details</span></td>
        </tr>
        <tr id="group_<?php echo $groupId; ?>" class="details">
            <td colspan="3">
                <?php 
                // Fetch all duplicate records for this key
                if($mode == 'issue_url'){
                    $sql_details = "SELECT id, Comic_Title, Issue_Number, Years, Unique_ID, Issue_URL FROM comics WHERE Issue_URL = '" . $conn->real_escape_string($dupKey) . "' ORDER BY id";
                } else {
                    $sql_details = "SELECT id, Comic_Title, Issue_Number, Years, Unique_ID, Issue_URL FROM comics WHERE Unique_ID = '" . $conn->real_escape_string($dupKey) . "' ORDER BY id";
                }
                $result_details = $conn->query($sql_details);
                if($result_details && $result_details->num_rows > 0){
                    echo '<table>';
                    echo '<tr><th>Select</th><th>ID</th><th>Comic Title</th><th>Issue Number</th><th>Years</th><th>Unique_ID</th><th>Issue_URL</th></tr>';
                    while($row = $result_details->fetch_assoc()){
                        echo '<tr>';
                        echo '<td><input type="checkbox" name="dup_'.$groupId.'[]" value="' . $row['id'] . '"></td>';
                        echo '<td>' . $row['id'] . '</td>';
                        echo '<td>' . htmlspecialchars($row['Comic_Title']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Issue_Number']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Years']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Unique_ID']) . '</td>';
                        echo '<td>' . htmlspecialchars($row['Issue_URL']) . '</td>';
                        echo '</tr>';
                    }
                    echo '</table>';
                    echo '<button type="button" class="delete-btn bulk-delete-btn" data-group="' . $groupId . '">Delete Selected</button>';
                } else {
                    echo 'No details found.';
                }
                ?>
            </td>
        </tr>
        <?php } ?>
    </table>
<?php } ?>

</body>
</html>
<?php $conn->close(); ?>

<?php
// Database Connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "comics_db";

$conn = new mysqli($servername, $username, $password, $dbname);

// Check Connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle Bulk Delete
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_ids'])) {
    $delete_ids = $_POST['delete_ids'];
    if (!empty($delete_ids)) {
        $ids_to_delete = implode(",", array_map('intval', $delete_ids)); // Ensure safety
        $sql_delete = "DELETE FROM comics WHERE id IN ($ids_to_delete)";
        if ($conn->query($sql_delete) === TRUE) {
            echo "<script>alert('Selected records deleted successfully'); window.location.href='duplicates.php';</script>";
        } else {
            echo "Error deleting records: " . $conn->error;
        }
    }
}

// Fetch Duplicate Issue_URL Records
$sql = "SELECT a.id AS ID_1, a.Comic_Title AS Title_1, a.Issue_Number AS Issue_1, a.Years AS Year_1, a.Issue_URL AS Issue_URL, 
               b.id AS ID_2, b.Comic_Title AS Title_2, b.Issue_Number AS Issue_2, b.Years AS Year_2
        FROM comics a
        JOIN comics b ON a.Issue_URL = b.Issue_URL 
        AND a.id <> b.id
        ORDER BY a.Issue_URL";

$result = $conn->query($sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Duplicate Issue_URLs</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #f4f4f4; }
        .delete-btn { padding: 5px 10px; color: white; background-color: red; border: none; cursor: pointer; }
        .delete-btn:hover { background-color: darkred; }
        .bulk-delete { padding: 10px 15px; color: white; background-color: darkred; border: none; cursor: pointer; margin-top: 10px; }
        .bulk-delete:hover { background-color: red; }
    </style>
    <script>
        function toggleCheckboxes(source) {
            checkboxes = document.getElementsByName('delete_ids[]');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</head>
<body>

<h2>Duplicate Issue_URLs</h2>

<form method="POST">
    <table>
        <tr>
            <th><input type="checkbox" onclick="toggleCheckboxes(this)"></th>
            <th>ID</th>
            <th>Comic Title</th>
            <th>Issue Number</th>
            <th>Years</th>
            <th>Issue URL</th>
        </tr>

        <?php
        if ($result->num_rows > 0) {
            $seen = []; // Prevent duplicate rows from appearing twice
            while ($row = $result->fetch_assoc()) {
                $key = $row["Issue_URL"];
                if (isset($seen[$key])) continue; // Skip duplicate display
                $seen[$key] = true;
                ?>
                <tr>
                    <td><input type="checkbox" name="delete_ids[]" value="<?= $row["ID_1"] ?>"></td>
                    <td><?= $row["ID_1"] ?></td>
                    <td><?= $row["Title_1"] ?></td>
                    <td><?= $row["Issue_1"] ?></td>
                    <td><?= $row["Year_1"] ?></td>
                    <td rowspan="2"><?= $row["Issue_URL"] ?></td>
                </tr>
                <tr>
                    <td><input type="checkbox" name="delete_ids[]" value="<?= $row["ID_2"] ?>"></td>
                    <td><?= $row["ID_2"] ?></td>
                    <td><?= $row["Title_2"] ?></td>
                    <td><?= $row["Issue_2"] ?></td>
                    <td><?= $row["Year_2"] ?></td>
                </tr>
                <tr><td colspan="6" style="background-color:#f4f4f4;"></td></tr> <!-- Separator Row -->
                <?php
            }
        } else {
            echo "<tr><td colspan='6'>No duplicate Issue_URLs found.</td></tr>";
        }
        $conn->close();
        ?>
    </table>

    <button type="submit" class="bulk-delete">Delete Selected</button>
</form>

</body>
</html>

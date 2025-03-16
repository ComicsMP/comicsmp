<?php
require_once 'db_connection.php';

$title = $_GET['comic_title'] ?? '';
if (!$title) { exit; }

$sql = "SELECT DISTINCT Years FROM Comics 
        WHERE Comic_Title = ?
        ORDER BY Years ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $title);
$stmt->execute();
$res = $stmt->get_result();

echo "<option value='' disabled selected>Select a Year</option>";
while ($row = $res->fetch_assoc()) {
    $y = htmlspecialchars($row['Years']);
    echo "<option value='$y'>$y</option>";
}
$stmt->close();
$conn->close();
?>

<?php
require_once 'db_connection.php';

$title = $_GET['comic_title'] ?? '';

if (!$title) exit;

$sql = "SELECT DISTINCT Country FROM Comics WHERE Comic_Title = ? ORDER BY Country ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $title);
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    $c = htmlspecialchars($row['Country']);
    echo "<option value='$c'>$c</option>";
}
$stmt->close();
$conn->close();
?>

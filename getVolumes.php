<?php
require_once 'db_connection.php';

$comic_title = $_GET['comic_title'] ?? '';

if (!$comic_title) {
    exit;
}

$sql = "SELECT DISTINCT Volume FROM Comics WHERE Comic_Title = ? AND Volume IS NOT NULL AND TRIM(Volume) <> '' ORDER BY Volume ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $comic_title);
$stmt->execute();
$result = $stmt->get_result();

$options = "<option value=''>Select Volume</option>";
while($row = $result->fetch_assoc()){
    // Ensure proper HTML escaping
    $vol = htmlspecialchars($row['Volume']);
    $options .= "<option value='$vol'>$vol</option>";
}

$stmt->close();
$conn->close();

echo $options;
?>

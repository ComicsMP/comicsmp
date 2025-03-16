<?php
require_once 'db_connection.php';

$title = $_GET['comic_title'] ?? '';
$year  = $_GET['year']        ?? '';

// Example: select distinct Tab from Comics
$sql = "SELECT DISTINCT Tab FROM Comics 
        WHERE Comic_Title = ?
";
$params = [$title];
$types  = "s";

if ($year) {
    $sql .= " AND Years = ? ";
    $params[] = $year;
    $types   .= "s";
}
$sql .= " ORDER BY Tab ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

echo "<option value='' disabled selected>Select a Tab</option>";
echo "<option value='All'>All</option>";
while ($row = $res->fetch_assoc()) {
    $tab = htmlspecialchars($row['Tab']);
    if ($tab) {
        echo "<option value='$tab'>$tab</option>";
    }
}
$stmt->close();
$conn->close();
?>

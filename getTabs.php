<?php
require_once 'db_connection.php';

$title   = $_GET['comic_title'] ?? '';
$year    = $_GET['year'] ?? '';
$country = $_GET['country'] ?? '';

$sql = "SELECT DISTINCT Tab FROM Comics WHERE Comic_Title = ?";
$params = [$title];
$types  = "s";

if ($year) {
    $sql .= " AND Years = ?";
    $params[] = $year;
    $types  .= "s";
}

if ($country) {
    $sql .= " AND Country = ?";
    $params[] = $country;
    $types  .= "s";
}

$sql .= " ORDER BY Tab ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$returnJson = isset($_GET['returnJson']) && $_GET['returnJson'] == 1;
$rows = $res->fetch_all(MYSQLI_ASSOC);

if ($returnJson) {
    // Build an array of tab options. Always include "All" first.
    $tabs = ["All"];
    foreach ($rows as $row) {
        $tab = trim($row['Tab']);
        if ($tab !== '') {
            $tabs[] = $tab;
        }
    }
    header('Content-Type: application/json');
    echo json_encode($tabs);
} else {
    echo "<option value='' disabled selected>Select a Tab</option>";
    echo "<option value='All'>All</option>";
    foreach ($rows as $row) {
        $tab = htmlspecialchars($row['Tab']);
        if ($tab) {
            echo "<option value='$tab'>$tab</option>";
        }
    }
}

$stmt->close();
$conn->close();
?>

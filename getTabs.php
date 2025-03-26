<?php
require_once 'db_connection.php';

$title   = $_GET['comic_title'] ?? '';
$country = $_GET['country'] ?? '';
$year    = $_GET['year'] ?? '';

if (empty($title)) {
    if (isset($_GET['returnJson']) && $_GET['returnJson'] == 1) {
        header('Content-Type: application/json');
        echo json_encode([]);
    } else {
        echo "<option value='' disabled selected>Select a Tab</option>";
    }
    exit;
}

// Convert title to lowercase for case-insensitive match
$titleLower = strtolower($title);

$sql = "SELECT DISTINCT Tab FROM Comics WHERE LOWER(Comic_Title) = ?";
$params = [$titleLower];
$types  = "s";

if (!empty($country)) {
    $sql .= " AND Country = ?";
    $params[] = $country;
    $types   .= "s";
}

if (!empty($year)) {
    $sql .= " AND Years = ?";
    $params[] = $year;
    $types   .= "s";
}

$sql .= " ORDER BY Tab ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$res = $stmt->get_result();

$returnJson = isset($_GET['returnJson']) && $_GET['returnJson'] == 1;
$rows = $res->fetch_all(MYSQLI_ASSOC);

if ($returnJson) {
    $tabs = [];
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
    foreach ($rows as $row) {
        $tab = htmlspecialchars($row['Tab']);
        if ($tab !== '') {
            echo "<option value='$tab'>$tab</option>";
        }
    }
}

$stmt->close();
$conn->close();
?>

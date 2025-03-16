<?php
include 'db_connection.php';

$series_name = $_GET['series_name'] ?? null;
$series_year = $_GET['series_year'] ?? null;

if (!$series_name || !$series_year) {
    die('Invalid request.');
}

$query = "SELECT issue_number, series_cover, image_path_200
          FROM wanted_items
          WHERE series_name = ? AND series_year = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ss', $series_name, $series_year);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "<table class='table'>";
    echo "<thead><tr><th>Cover</th><th>Issue</th><th>Description</th></tr></thead><tbody>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td><img src='" . htmlspecialchars($row['image_path_200']) . "' alt='Cover' style='width: 100px;'></td>";
        echo "<td>" . htmlspecialchars($row['issue_number']) . "</td>";
        echo "<td>" . htmlspecialchars($row['series_cover'] ?? 'N/A') . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
} else {
    echo "<p>No details available for this series.</p>";
}
?>

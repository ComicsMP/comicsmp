<?php
include 'db_connection.php';

// Get input parameters
$series = $_GET['series'];
$year = $_GET['year'];

// Query to retrieve distinct Image_Path values
$sql = "SELECT DISTINCT Image_Path, Series_Issue, Series_Cover 
        FROM Comics 
        WHERE Series_Name = ? AND Series_Year = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $series, $year);
$stmt->execute();
$result = $stmt->get_result();

// Display each image as a thumbnail
while ($row = $result->fetch_assoc()) {
    $imagePath = htmlspecialchars($row['Image_Path']);
    $issueNumber = htmlspecialchars($row['Series_Issue']);
    $coverDetails = htmlspecialchars($row['Series_Cover']);
    echo "<div class='thumbnail-container'>";
    echo "<img src='$imagePath' alt='Cover Image' class='thumbnail' />";
    echo "<p>Issue: $issueNumber<br>Details: $coverDetails</p>";
    echo "</div>";
}
?>

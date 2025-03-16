<?php
include 'db_connection.php';

// Get parameters
$series = trim($_GET['series'] ?? '');
$year = trim($_GET['year'] ?? '');

if (empty($series) || empty($year)) {
    echo "<option value='' disabled>No issues available</option>";
    exit;
}

try {
    // Fetch distinct issue numbers for the given series and year
    $query = "SELECT DISTINCT Series_Issue 
              FROM Comics 
              WHERE Series_Name = ? AND Series_Year = ?
              ORDER BY 
                  CAST(REGEXP_SUBSTR(Series_Issue, '[0-9]+(\\.[0-9]+)?') AS DECIMAL(10,2)) ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $series, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $issue = htmlspecialchars($row['Series_Issue']);
            echo "<option value='$issue'>$issue</option>";
        }
    } else {
        echo "<option value='' disabled>No issues available</option>";
    }

    $stmt->close();
} catch (Exception $e) {
    echo "<option value='' disabled>Error fetching issues</option>";
} finally {
    $conn->close();
}
?>

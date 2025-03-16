<?php
include 'db_connection.php';
session_start();

// Get the logged-in user ID
$user_id = $_SESSION['user_id'] ?? null;

// Get parameters
$series = $_GET['series'] ?? '';
$year = $_GET['year'] ?? '';

if (empty($series) || empty($year)) {
    echo "Invalid parameters provided.";
    exit;
}

try {
    // Fetch comics for the given series and year with proper sorting
    $sql = "SELECT Series_Name, Series_Year, Series_Issue, Series_Cover, Image_Path_200, Image_Path_400
            FROM Comics 
            WHERE Series_Name = ? AND Series_Year = ?
            ORDER BY 
                CAST(REGEXP_SUBSTR(Series_Issue, '[0-9]+(\\.[0-9]+)?') AS DECIMAL(10,2)) ASC,
                CASE 
                    WHEN Series_Cover IS NULL OR Series_Cover = '' THEN 1
                    WHEN Series_Cover = 'Cover A' THEN 2
                    ELSE 3
                END ASC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ss", $series, $year);
    $stmt->execute();
    $result = $stmt->get_result();

    // Check if the comic issue is already in the user's wanted list
    function checkIfAdded($series_name, $issue_number, $series_year, $series_cover, $user_id, $conn) {
        $check_query = "SELECT id FROM wanted_items 
                        WHERE user_id = ? AND series_name = ? AND issue_number = ? AND series_year = ? 
                        AND (series_cover = ? OR (series_cover IS NULL AND ? = ''))";
        $stmt = $conn->prepare($check_query);
        $series_cover = $series_cover ?? ''; // Treat NULL as an empty string
        $stmt->bind_param("isssss", $user_id, $series_name, $issue_number, $series_year, $series_cover, $series_cover);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }

    // Render comics
    if ($result->num_rows > 0) {
        while ($comic = $result->fetch_assoc()) {
            $series_cover = $comic['Series_Cover'] ?? ''; // Handle NULL cover
            $isAdded = $user_id ? checkIfAdded($comic['Series_Name'], $comic['Series_Issue'], $comic['Series_Year'], $series_cover, $user_id, $conn) : false;

            // Change button class and text based on whether the comic is already added
            $buttonClass = $isAdded ? 'btn-success' : 'btn-primary';
            $buttonText = $isAdded ? 'Already Added' : 'Add to Wanted List';
            $buttonDisabled = $isAdded ? 'disabled' : '';

            echo "<div class='gallery-item'>";
            echo "<img src='" . htmlspecialchars($comic['Image_Path_200']) . "' 
                      data-fullsize='" . htmlspecialchars($comic['Image_Path_400']) . "' 
                      data-series-name='" . htmlspecialchars($comic['Series_Name']) . "' 
                      data-series-issue='" . htmlspecialchars($comic['Series_Issue']) . "' 
                      data-series-year='" . htmlspecialchars($comic['Series_Year']) . "' 
                      data-series-cover='" . htmlspecialchars($series_cover) . "' 
                      alt='" . htmlspecialchars($comic['Series_Name']) . "'>";
            echo "<p class='series-issue'>Issue " . htmlspecialchars($comic['Series_Issue']) . "</p>";
            echo "<button class='btn $buttonClass add-to-wanted' $buttonDisabled 
                  data-series='" . htmlspecialchars($comic['Series_Name']) . "' 
                  data-year='" . htmlspecialchars($comic['Series_Year']) . "' 
                  data-issue='" . htmlspecialchars($comic['Series_Issue']) . "' 
                  data-series-cover='" . htmlspecialchars($series_cover) . "'>" 
                  . $buttonText . "</button>";
            echo "</div>";
        }
    } else {
        echo "<p class='text-warning'>No comics found for the provided criteria.</p>";
    }

    $stmt->close();
} catch (Exception $e) {
    echo "<p class='text-danger'>An error occurred: " . htmlspecialchars($e->getMessage()) . "</p>";
} finally {
    $conn->close();
}
?>

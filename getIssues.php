<?php
require_once 'db_connection.php';

$comic_title = $_GET['comic_title'] ?? '';
$year        = $_GET['year'] ?? '';
$volume      = $_GET['volume'] ?? '';
$include_var = $_GET['include_variants'] ?? 0;

// Check that a comic title is provided
if (!$comic_title) {
    echo "<option value='' disabled selected>No issues found</option>";
    exit;
}

// If no volume is provided, require year.
if (trim($volume) === "" && trim($year) === "") {
    echo "<option value='' disabled selected>No issues found</option>";
    exit;
}

try {
    // If volume is provided, use volume for filtering (year is optional)
    if (trim($volume) !== "") {
        if ($include_var) {
            // Show ALL issues (main + variants) for the given volume.
            $sql = "SELECT DISTINCT Issue_Number FROM Comics 
                    WHERE Comic_Title = ? AND Volume = ? 
                    ORDER BY CAST(SUBSTRING_INDEX(Issue_Number, ' ', 1) AS UNSIGNED), Issue_Number ASC";
        } else {
            // Show ONLY main issues for the given volume.
            $sql = "SELECT DISTINCT Issue_Number FROM Comics 
                    WHERE Comic_Title = ? AND Volume = ? 
                      AND (Issue_Number REGEXP '^#?[0-9]+$')
                    ORDER BY CAST(REPLACE(Issue_Number, '#', '') AS UNSIGNED) ASC";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo "<option value='' disabled>Error: " . $conn->error . "</option>";
            exit;
        }
        $stmt->bind_param("ss", $comic_title, $volume);
    } else {
        // No volume provided; use Year for filtering.
        if ($include_var) {
            $sql = "SELECT DISTINCT Issue_Number FROM Comics 
                    WHERE Comic_Title = ? AND Years = ? 
                    ORDER BY CAST(SUBSTRING_INDEX(Issue_Number, ' ', 1) AS UNSIGNED), Issue_Number ASC";
        } else {
            $sql = "SELECT DISTINCT Issue_Number FROM Comics 
                    WHERE Comic_Title = ? AND Years = ? 
                      AND (Issue_Number REGEXP '^#?[0-9]+$')
                    ORDER BY CAST(REPLACE(Issue_Number, '#', '') AS UNSIGNED) ASC";
        }
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            echo "<option value='' disabled>Error: " . $conn->error . "</option>";
            exit;
        }
        $stmt->bind_param("ss", $comic_title, $year);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        echo "<option value='' disabled selected>Select an Issue</option>";
        while ($row = $result->fetch_assoc()) {
            $issue = trim($row['Issue_Number']);
            // Ensure display always has '#' prefix.
            $displayIssue = (strpos($issue, '#') === 0) ? $issue : '#' . $issue;
            echo "<option value='" . htmlspecialchars($issue) . "'>" . htmlspecialchars($displayIssue) . "</option>";
        }
    } else {
        echo "<option value='' disabled selected>No issues found</option>";
    }

    $stmt->close();
} catch (Exception $e) {
    echo "<option value='' disabled>Error: " . htmlspecialchars($e->getMessage()) . "</option>";
} finally {
    $conn->close();
}
?>

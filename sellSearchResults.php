<?php
include 'db_connection.php';

$series = $_GET['series'] ?? '';
$year   = $_GET['year'] ?? '';
$issue  = $_GET['issue_number'] ?? '';  // Changed key to match JS
$tab    = $_GET['tab'] ?? '';
$include_variants = $_GET['include_variants'] ?? 0; // 0 = false

if(empty($series) || empty($year) || empty($tab)){
    echo "<p class='text-danger'>Invalid parameters provided.</p>";
    exit;
}

if($tab === "Issues") {
    // In the "Issues" tab case, we filter to show only issues.
    if($issue === ''){
        echo "<p class='text-warning'>Please select an Issue number.</p>";
        exit;
    }
    if($include_variants) {
        // Pattern: optional '#' then the selected issue then either end-of-string or a non-digit.
        $pattern = '^#?' . preg_quote($issue, '/') . '($|[^0-9])';
        $query = "SELECT * FROM Comics 
                  WHERE Comic_Title = ? 
                    AND Years = ? 
                    AND Tab = 'Issues'
                    AND Issue_Number REGEXP ?";
        $stmt = $conn->prepare($query);
        if(!$stmt){
            echo "<p class='text-danger'>Query preparation failed: " . $conn->error . "</p>";
            exit;
        }
        $stmt->bind_param("sss", $series, $year, $pattern);
    } else {
        // Only return the primary cover.
        // Compare the numeric part by trimming any leading '#' from Issue_Number.
        $query = "SELECT * FROM Comics 
                  WHERE Comic_Title = ? 
                    AND Years = ? 
                    AND Tab = 'Issues'
                    AND TRIM(LEADING '#' FROM Issue_Number) = ?";
        $stmt = $conn->prepare($query);
        if(!$stmt){
            echo "<p class='text-danger'>Query preparation failed: " . $conn->error . "</p>";
            exit;
        }
        $stmt->bind_param("sss", $series, $year, $issue);
    }
} elseif($tab === "All") {
    // For "All", show all records for the series and year.
    if($include_variants) {
        $query = "SELECT * FROM Comics 
                  WHERE Comic_Title = ? 
                    AND Years = ?
                  ORDER BY Tab ASC, TRIM(LEADING '#' FROM Issue_Number) ASC";
        $stmt = $conn->prepare($query);
        if(!$stmt){
            echo "<p class='text-danger'>Query preparation failed: " . $conn->error . "</p>";
            exit;
        }
        $stmt->bind_param("ss", $series, $year);
    } else {
        // Only primary covers: require Issue_Number (after trimming '#') be purely numeric.
        $query = "SELECT * FROM Comics 
                  WHERE Comic_Title = ? 
                    AND Years = ? 
                    AND TRIM(LEADING '#' FROM Issue_Number) REGEXP '^[0-9]+$'
                  ORDER BY Tab ASC, TRIM(LEADING '#' FROM Issue_Number) ASC";
        $stmt = $conn->prepare($query);
        if(!$stmt){
            echo "<p class='text-danger'>Query preparation failed: " . $conn->error . "</p>";
            exit;
        }
        $stmt->bind_param("ss", $series, $year);
    }
} else {
    // For other Tab values (like Annual, Hardcover, etc.), filter by Tab.
    if($include_variants) {
        $query = "SELECT * FROM Comics 
                  WHERE Comic_Title = ? 
                    AND Years = ? 
                    AND Tab = ?";
        $stmt = $conn->prepare($query);
        if(!$stmt){
            echo "<p class='text-danger'>Query preparation failed: " . $conn->error . "</p>";
            exit;
        }
        $stmt->bind_param("sss", $series, $year, $tab);
    } else {
        $query = "SELECT * FROM Comics 
                  WHERE Comic_Title = ? 
                    AND Years = ? 
                    AND Tab = ? 
                    AND TRIM(LEADING '#' FROM Issue_Number) REGEXP '^[0-9]+$'";
        $stmt = $conn->prepare($query);
        if(!$stmt){
            echo "<p class='text-danger'>Query preparation failed: " . $conn->error . "</p>";
            exit;
        }
        $stmt->bind_param("sss", $series, $year, $tab);
    }
}

$stmt->execute();
$result = $stmt->get_result();

$output = "";
if($result->num_rows > 0){
    while($comic = $result->fetch_assoc()){
        $image_filename = !empty($comic['Image_Path']) ? trim($comic['Image_Path']) : 'default.jpg';
        if (!filter_var($image_filename, FILTER_VALIDATE_URL)) {
            $image = 'http://localhost/comicsmp/images/' . basename($image_filename);
        } else {
            $image = $image_filename;
        }
        $variant = $comic['Variant'] ?? '';
        $tabValue = $comic['Tab'] ?? '';
        $output .= "<div class='gallery-item'>
                      <img src='" . htmlspecialchars($image) . "' 
                           onerror=\"this.onerror=null;this.src='http://localhost/comicsmp/images/default.jpg';\" 
                           data-fullsize='" . htmlspecialchars($image) . "'
                           data-series-name='" . htmlspecialchars($comic['Comic_Title']) . "'
                           data-issue-number='" . htmlspecialchars($comic['Issue_Number']) . "'
                           data-series-year='" . htmlspecialchars($comic['Years']) . "'
                           data-variant='" . htmlspecialchars($variant) . "'
                           alt='" . htmlspecialchars($comic['Comic_Title']) . "' class='comic-image'>
                      <p class='series-issue'>Issue " . htmlspecialchars($comic['Issue_Number']) . "</p>
                      <p class='series-tab'>Tab: " . htmlspecialchars($tabValue) . "</p>
                      <button class='btn btn-success' onclick='selectCoverForSale(this)'>Sell This Cover</button>
                    </div>";
    }
    echo $output;
} else {
    echo "<p class='text-warning'>No cover images found for the selected issue.</p>";
}
$stmt->close();
$conn->close();
?>

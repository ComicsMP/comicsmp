<?php
session_start();
require_once 'db_connection.php';

// Endpoint: fetch available tabs based on selected year.
if (isset($_GET['get_tabs'])) {
    $yearForTabs = $_GET['year'] ?? '';
    if (empty($yearForTabs)) {
        echo json_encode([]);
        exit;
    }
    $sqlTabs = "SELECT DISTINCT Tab FROM Comics WHERE Years = ?";
    $stmtTabs = $conn->prepare($sqlTabs);
    if (!$stmtTabs) {
        echo json_encode(['error' => $conn->error]);
        exit;
    }
    $stmtTabs->bind_param('s', $yearForTabs);
    $stmtTabs->execute();
    $resultTabs = $stmtTabs->get_result();
    $tabs = [];
    while ($row = $resultTabs->fetch_assoc()) {
        $tabs[] = $row['Tab'];
    }
    echo json_encode($tabs);
    exit;
}

$user_id     = $_SESSION['user_id'] ?? 0;
$comic_title = $_GET['comic_title']      ?? '';
$year        = $_GET['year']             ?? '';
$volume      = $_GET['volume']           ?? '';  // New parameter
$tab         = $_GET['tab']              ?? '';
$issue_number= $_GET['issue_number']     ?? '';
$include_var = $_GET['include_variants'] ?? 0;

// Save the last selected issue number when on the Issues tab.
if ($tab === 'Issues' && !empty($issue_number)) {
    $_SESSION['last_issue_number'] = str_replace('#', '', $issue_number);
}
// When switching to Variants without an issue number provided, use the last selected issue.
if ($tab === 'Variants' && empty($issue_number) && isset($_SESSION['last_issue_number'])) {
    $issue_number = $_SESSION['last_issue_number'];
}

// Ensure required parameters.
if (!$comic_title || (!$year && trim($volume) === "") || !$tab) {
    echo "<p class='text-danger'>Invalid parameters provided.</p>";
    exit;
}

try {
    $whereParts = [];
    $params     = [];
    $types      = '';

    // Filter by comic title.
    $whereParts[] = "c.Comic_Title = ?";
    $params[]     = $comic_title;
    $types       .= 's';

    // Filter by volume (if provided) or by year.
    if (trim($volume) !== "") {
        $whereParts[] = "c.Volume = ?";
        $params[]     = trim($volume);
        $types       .= 's';
    } else {
        $whereParts[] = "c.Years = ?";
        $params[]     = $year;
        $types       .= 's';
    }

    // Filter by tab (if not 'All').
    if ($tab !== 'All') {
        $whereParts[] = "c.Tab = ?";
        $params[]     = $tab;
        $types       .= 's';
    }

    // Issue number filtering.
    if ($issue_number) {
        if ($tab === 'Issues') {
            // For Issues: exact match on the simple issue number.
            $whereParts[] = "REPLACE(c.Issue_Number, '#', '') = ?";
            $params[]     = str_replace('#', '', $issue_number);
            $types       .= 's';
        } elseif ($tab === 'Variants') {
            // For Variants: use the numeric part so that both cover A and its variants are included.
            $whereParts[] = "CAST(REPLACE(c.Issue_Number, '#', '') AS UNSIGNED) = ?";
            $params[]     = (int) str_replace('#', '', $issue_number);
            $types       .= 'i';
        }
    }
    
    // For the Issues tab (when variants are not included), show only cover A.
    if ($tab === 'Issues' && !$include_var) {
        $whereParts[] = "REPLACE(c.Issue_Number, '#', '') REGEXP '^[0-9]+$'";
    }

    $whereClause = implode(' AND ', $whereParts);

    // Main query – note the use of GROUP BY c.ID to eliminate duplicates.
    // NOTE: UPC is now selected as c.UPC AS upc
    $sql = "
        SELECT
          c.ID            AS comic_id,
          c.Comic_Title   AS comic_title,
          c.Issue_Number  AS issue_number,
          c.Years         AS years,
          c.Volume        AS volume,
          c.Tab           AS tab,
          c.Variant       AS variant,
          c.Image_Path    AS image_path,
          c.Issue_URL     AS issue_url,
          c.`Date`        AS comic_date,
          c.UPC           AS upc,           -- Added UPC field
          MAX(w.id)       AS wanted_id
        FROM Comics c
        LEFT JOIN wanted_items w
          ON c.Comic_Title = w.comic_title
         AND REPLACE(c.Issue_Number, '#', '') = REPLACE(w.issue_number, '#', '')
         AND c.Years = w.years
         AND w.user_id = ?
         AND c.Issue_URL = w.issue_url
        WHERE $whereClause
        GROUP BY c.ID
        ORDER BY CAST(REPLACE(c.Issue_Number, '#', '') AS UNSIGNED) ASC,
                 (REPLACE(c.Issue_Number, '#', '') REGEXP '^[0-9]+$') DESC,
                 c.Issue_Number ASC
    ";

    // Prepend user_id.
    $types = 'i' . $types;
    array_unshift($params, $user_id);

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "<p class='text-danger'>DB error: {$conn->error}</p>";
        exit;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Output each result as a gallery item
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $title   = htmlspecialchars($row['comic_title']  ?? '');
            $issue   = htmlspecialchars($row['issue_number'] ?? '');
            $yrs     = htmlspecialchars($row['years']        ?? '');
            $vol     = htmlspecialchars($row['volume']       ?? '');
            $tabVal  = htmlspecialchars($row['tab']          ?? '');
            $variant = htmlspecialchars($row['variant']      ?? '');
            $wanted  = !empty($row['wanted_id']) ? 1 : 0;
            $issue_url = htmlspecialchars($row['issue_url'] ?? '');
            $comic_date = htmlspecialchars($row['comic_date'] ?? 'N/A');
            // Retrieve UPC value
            $upc = htmlspecialchars($row['upc'] ?? 'N/A');
            
            // Process image path
            $rawPath = trim($row['image_path'] ?? '');
            if (empty($rawPath) || strtolower($rawPath) === 'null') {
                $imgPath = '/comicsmp/placeholder.jpg';
            } elseif (filter_var($rawPath, FILTER_VALIDATE_URL)) {
                $imgPath = $rawPath;
            } elseif (strpos($rawPath, '/comicsmp/images/') === 0) {
                $imgPath = $rawPath;
            } elseif (strpos($rawPath, '/images/') === 0) {
                $imgPath = '/comicsmp' . $rawPath;
            } else {
                $imgPath = $rawPath;
            }
            if (strpos($issue, '#') !== 0) {
                $issue = '#' . $issue;
            }
            
            // Add the UPC as a data attribute (data-upc)
            echo "<div class='gallery-item' 
                          data-comic-title='{$title}' 
                          data-years='{$yrs}' 
                          data-volume='{$vol}'
                          data-issue-number='{$issue}'
                          data-tab='{$tabVal}'
                          data-variant='{$variant}'
                          data-wanted='{$wanted}'
                          data-full='" . htmlspecialchars($imgPath) . "'
                          data-issue_url='{$issue_url}'
                          data-date='{$comic_date}'
                          data-upc='{$upc}'>\n";  // <-- UPC added here
            echo "<img src='" . htmlspecialchars($imgPath) . "' alt='" . $title . "' class='comic-image' data-full='" . htmlspecialchars($imgPath) . "'>\n";
            echo "<p class='series-issue'>Issue: " . $issue . "</p>\n";
            echo "</div>\n";
        }
    } else {
        echo "<p class='text-warning'>No results found.</p>";
    }

    $stmt->close();
} catch (Exception $e) {
    echo "<p class='text-danger'>Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
} finally {
    $conn->close();
}
?>

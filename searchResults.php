<?php
session_start();
require_once 'db_connection.php';

// If this endpoint is used to fetch tabs, handle that first.
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

$user_id      = $_SESSION['user_id'] ?? 0;
$comic_title  = $_GET['comic_title']      ?? '';
$year         = $_GET['year']             ?? '';
$volume       = $_GET['volume']           ?? '';
$tab          = $_GET['tab']              ?? '';
$issue_number = $_GET['issue_number']     ?? '';
$include_var  = $_GET['include_variants'] ?? 0;

// Store the last selected issue number for switching between tabs.
if ($tab === 'Issues' && !empty($issue_number)) {
    $_SESSION['last_issue_number'] = str_replace('#', '', $issue_number);
}
if ($tab === 'Variants' && empty($issue_number) && isset($_SESSION['last_issue_number'])) {
    $issue_number = $_SESSION['last_issue_number'];
}

// Only require a comic_title, let year/volume/tab be optional
if (!$comic_title) {
    echo "";
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

    // Filter by volume or year.
    if (trim($volume) !== "") {
        $whereParts[] = "c.Volume = ?";
        $params[]     = trim($volume);
        $types       .= 's';
    } else {
        $whereParts[] = "c.Years = ?";
        $params[]     = $year;
        $types       .= 's';
    }

    // Filter by tab.
    if ($tab !== 'All') {
        $whereParts[] = "c.Tab = ?";
        $params[]     = $tab;
        $types       .= 's';
    }

    // Issue number filtering.
    if ($issue_number && $issue_number !== "All") {
        if ($tab === 'Issues') {
            $base_issue = str_replace('#', '', $issue_number);
            if (!$include_var) {
                // Only the exact main issue.
                $whereParts[] = "REPLACE(c.Issue_Number, '#', '') = ?";
                $params[] = $base_issue;
                $types .= 's';
            } else {
                // Fetch the exact issue plus valid variant formats.
                $whereParts[] = "(REPLACE(c.Issue_Number, '#', '') = ? 
                                OR REPLACE(c.Issue_Number, '#', '') LIKE ? 
                                OR REPLACE(c.Issue_Number, '#', '') REGEXP ?)";
                $params[] = $base_issue;
                $params[] = $base_issue . '[A-Z]%';
                $params[] = '^' . preg_quote($base_issue) . '(?:[-.]?[A-Za-z].*)?$';
                $types .= 'sss';
            }
        } elseif ($tab === 'Variants') {
            $whereParts[] = "CAST(REPLACE(c.Issue_Number, '#', '') AS UNSIGNED) = ?";
            $params[]     = (int) str_replace('#', '', $issue_number);
            $types       .= 'i';
        }
    } else {
        // When no specific issue number is provided (or "All" is selected)
        if ($tab === 'Issues' && !$include_var) {
            $whereParts[] = "REPLACE(c.Issue_Number, '#', '') REGEXP '^[0-9]+$'";
        }
    }

    $whereClause = implode(' AND ', $whereParts);

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
          c.UPC           AS upc,
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

    // Lazy Scroll / Pagination: Read limit and offset from GET parameters.
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sql .= " LIMIT ? OFFSET ?";

    // Prepend user_id (for the join condition) to our parameter list.
    // Previously, $types was built for the where clause; now we add two integers.
    $types = 'i' . $types . 'ii';
    array_unshift($params, $user_id);  // add user_id at beginning
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        echo "<p class='text-danger'>DB error: " . htmlspecialchars($conn->error) . "</p>";
        exit;
    }
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // Output results as HTML.
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
            $upc = htmlspecialchars($row['upc'] ?? 'N/A');

            // Process image path.
            $rawPath = trim($row['image_path'] ?? '');
            if (empty($rawPath) || strtolower($rawPath) === 'null') {
                $imgPath = "http://localhost/comicsmp/images/default.jpg";
            } elseif (filter_var($rawPath, FILTER_VALIDATE_URL)) {
                $imgPath = $rawPath;
            } else {
                if (strpos($rawPath, '/') !== 0) {
                    $rawPath = '/' . $rawPath;
                }
                $imgPath = "http://localhost/comicsmp" . $rawPath;
            }

            if (strpos($issue, '#') !== 0) {
                $issue = '#' . $issue;
            }

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
                          data-upc='{$upc}'>\n";
            echo "<img src='" . htmlspecialchars($imgPath) . "' alt='" . $title . "' class='comic-image' data-full='" . htmlspecialchars($imgPath) . "'>\n";
            echo "<p class='series-issue'>Issue: " . $issue . "</p>\n";
            echo "</div>\n";
        }
    } else {
        echo "";
    }

    $stmt->close();
} catch (Exception $e) {
    echo "<p class='text-danger'>Exception: " . htmlspecialchars($e->getMessage()) . "</p>";
} finally {
    $conn->close();
}
?>

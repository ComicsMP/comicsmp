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

$user_id      = $_SESSION['user_id']      ?? 0;
$comic_title  = $_GET['comic_title']      ?? '';
$year         = $_GET['year']             ?? '';
$volume       = $_GET['volume']           ?? '';
$tab          = $_GET['tab']              ?? '';
$issue_number = $_GET['issue_number']     ?? '';
$include_var  = $_GET['include_variants'] ?? 0;

// Remember last_issue_number for tab switching
if ($tab === 'Issues' && !empty($issue_number)) {
    $_SESSION['last_issue_number'] = str_replace('#','',$issue_number);
}
if ($tab === 'Variants' && empty($issue_number) && isset($_SESSION['last_issue_number'])) {
    $issue_number = $_SESSION['last_issue_number'];
}

// Must have comic_title
if (!$comic_title) {
    echo "";
    exit;
}

try {
    // Build WHERE
    $where = [];
    $params = [];
    $types  = '';

    $where[]   = "c.Comic_Title = ?";
    $params[]  = $comic_title; $types .= 's';

    if (trim($volume) !== "") {
        $where[]   = "c.Volume = ?";
        $params[]  = trim($volume); $types .= 's';
    } else {
        $where[]   = "c.Years = ?";
        $params[]  = $year; $types .= 's';
    }

    if ($tab !== 'All') {
        $where[]   = "c.Tab = ?";
        $params[]  = $tab; $types .= 's';
    }

    if ($issue_number && $issue_number !== "All") {
        if ($tab === 'Issues') {
            $base = str_replace('#','',$issue_number);
            if (!$include_var) {
                $where[]   = "REPLACE(c.Issue_Number,'#','') = ?";
                $params[]  = $base; $types .= 's';
            } else {
                $where[]   = "(REPLACE(c.Issue_Number,'#','') = ?
                              OR REPLACE(c.Issue_Number,'#','') LIKE ?
                              OR REPLACE(c.Issue_Number,'#','') REGEXP ?)";
                $params[]  = $base;
                $params[]  = $base.'[A-Z]%';
                $params[]  = '^'.preg_quote($base).'(?:[-.]?[A-Za-z].*)?$';
                $types    .= 'sss';
            }
        } else {
            $where[]   = "CAST(REPLACE(c.Issue_Number,'#','') AS UNSIGNED) = ?";
            $params[]  = (int)$issue_number; $types .= 'i';
        }
    } elseif ($tab==='Issues' && !$include_var) {
        $where[] = "REPLACE(c.Issue_Number,'#','') REGEXP '^[0-9]+$'";
    }

    $whereClause = implode(' AND ', $where);

    // Main SQL with pagination
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
       AND REPLACE(c.Issue_Number,'#','') = REPLACE(w.issue_number,'#','')
       AND c.Years = w.years
       AND w.user_id = ?
       AND c.Issue_URL = w.issue_url
      WHERE $whereClause
      GROUP BY c.ID
      ORDER BY
        CAST(SUBSTRING_INDEX(REPLACE(c.Issue_Number,'#',''),'-',1) AS UNSIGNED),
        CASE
          WHEN REPLACE(c.Issue_Number,'#','') NOT LIKE '%-%' THEN 0
          WHEN REPLACE(c.Issue_Number,'#','') REGEXP '^\\d+-[A-Z]+(-[A-Z0-9]+)?$' THEN 2
          WHEN REPLACE(c.Issue_Number,'#','') REGEXP '^\\d+-[0-9A-Z]+$' THEN 1
          ELSE 3
        END,
        c.Issue_Number
    ";

    $limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 20;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $sql   .= " LIMIT ? OFFSET ?";

    $types = 'i'.$types.'ii';
    array_unshift($params, $user_id);
    $params[] = $limit;
    $params[] = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {

        // ── SERIES TITLE AND SELECTED YEAR ──
if ($offset === 0) {
    $first       = $result->fetch_assoc();
    $seriesTitle = htmlspecialchars($first['comic_title']);
    $seriesYear  = htmlspecialchars($first['years']);

    // rewind pointer for gallery loop
    $result->data_seek(0);
    ?>
    <div class="series-header-box w-100 text-start mb-4">
      <div style="display:inline-block;">
        <h5 style="font-size:1.25rem;
                   font-weight:600;
                   display:inline-block;
                   margin-right:0.75rem;">
          <?= $seriesTitle ?> (<?= $seriesYear ?>)
        </h5>
        <hr style="margin:4px 0 0;
                   border:none;
                   border-top:1px solid #ccc;">
      </div>
    </div>
    <?php
}


        // ── GALLERY ITEMS ──
        while ($row = $result->fetch_assoc()) {
            $title   = htmlspecialchars($row['comic_title']);
            $issue   = '#'.ltrim($row['issue_number'],'#');
            $yrs     = htmlspecialchars($row['years']);
            $tabVal  = htmlspecialchars($row['tab']);
            $variant = htmlspecialchars($row['variant']);
            $wanted  = !empty($row['wanted_id']);
            $imgRaw  = trim($row['image_path']);
            if (!$imgRaw || strtolower($imgRaw)==='null') {
                $img = "http://localhost/comicsmp/images/default.jpg";
            } elseif (filter_var($imgRaw, FILTER_VALIDATE_URL)) {
                $img = $imgRaw;
            } else {
                $img = "http://localhost/comicsmp"
                     . (strpos($imgRaw,'/')===0
                        ? $imgRaw
                        : "/$imgRaw");
            }
            ?>
            <div class="gallery-item"
                 data-comic-title="<?= $title ?>"
                 data-years="<?= $yrs ?>"
                 data-issue-number="<?= $issue ?>"
                 data-tab="<?= $tabVal ?>"
                 data-variant="<?= $variant ?>"
                 data-wanted="<?= (int)$wanted ?>"
                 data-full="<?= htmlspecialchars($img) ?>"
                 data-issue-url="<?= htmlspecialchars($row['issue_url']) ?>"
                 data-date="<?= htmlspecialchars($row['comic_date']) ?>"
                 data-upc="<?= htmlspecialchars($row['upc']) ?>">
              <img src="<?= htmlspecialchars($img) ?>"
                   alt="<?= $title ?>"
                   class="comic-image">
              <p class="series-issue">Issue: <?= $issue ?></p>
              <div class="button-wrapper text-center">
                <?php if ($wanted): ?>
                  <button class="btn btn-success" disabled>Added</button>
                <?php else: ?>
                  <button class="btn btn-primary add-to-wanted"
                          data-series-name="<?= $title ?>"
                          data-issue-number="<?= $issue ?>"
                          data-series-year="<?= $yrs ?>"
                          data-issue-url="<?= htmlspecialchars($row['issue_url']) ?>">
                    Wanted
                  </button>
                <?php endif; ?>
                <button class="btn btn-secondary sell-button"
                        data-series-name="<?= $title ?>"
                        data-issue-number="<?= $issue ?>"
                        data-series-year="<?= $yrs ?>"
                        data-issue-url="<?= htmlspecialchars($row['issue_url']) ?>">
                  Sell
                </button>
              </div>
            </div>
            <?php
        }

    } else {
        echo "";
    }

    $stmt->close();

} catch (Exception $e) {
    echo "<p class='text-danger'>Exception: "
         . htmlspecialchars($e->getMessage())
         . "</p>";
} finally {
    $conn->close();
}
?>

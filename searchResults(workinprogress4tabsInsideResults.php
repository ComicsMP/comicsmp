<?php
session_start();
require_once 'db_connection.php';

// preserve full incoming query string so switchYear() still works
$baseQuery = $_SERVER['QUERY_STRING'];

// ── AJAX: FETCH TAB LIST ──
if (isset($_GET['get_tabs'])) {
    $yearForTabs = $_GET['year'] ?? '';
    if (!$yearForTabs) {
        echo json_encode([]);
        exit;
    }
    $stmtTabs = $conn->prepare("SELECT DISTINCT Tab FROM Comics WHERE Years = ?");
    $stmtTabs->bind_param('s', $yearForTabs);
    $stmtTabs->execute();
    $tabs = [];
    foreach ($stmtTabs->get_result() as $r) {
        $tabs[] = $r['Tab'];
    }
    echo json_encode($tabs);
    exit;
}

// ── COLLECT FILTERS & PAGINATION ──
$user_id      = $_SESSION['user_id']      ?? 0;
$comic_title  = $_GET['comic_title']      ?? '';
$year         = $_GET['year']             ?? '';
$volume       = $_GET['volume']           ?? '';
$tab          = $_GET['tab']              ?? '';
$issue_number = $_GET['issue_number']     ?? '';
// only include_variants=1 is treated as TRUE
$include_var  = (isset($_GET['include_variants']) && $_GET['include_variants']==='1') ? 1 : 0;

$limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 20;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

// persist last_issue_number for Variants tab
if ($tab==='Issues' && $issue_number!=='') {
    $_SESSION['last_issue_number'] = str_replace('#','',$issue_number);
}
if ($tab==='Variants'
    && empty($issue_number)
    && isset($_SESSION['last_issue_number'])
) {
    $issue_number = $_SESSION['last_issue_number'];
}

// must have a title
if (!$comic_title) {
    echo "";
    exit;
}

try {
    // ── BUILD WHERE CLAUSE ──
    $where  = [];
    $params = [];
    $types  = '';

    // filter by series title
    $where[]  = "c.Comic_Title = ?";
    $params[] = $comic_title; $types .= 's';

    // filter by volume or year
    if (trim($volume)!=='') {
        $where[]  = "c.Volume = ?";
        $params[] = trim($volume);  $types .= 's';
    } else {
        $where[]  = "c.Years = ?";
        $params[] = $year;          $types .= 's';
    }

    // filter by tab
    if ($tab!=='All') {
        $where[]  = "c.Tab = ?";
        $params[] = $tab;           $types .= 's';

        // ALWAYS apply pure-numeric filter on Issues unless variants are on
        if ($tab==='Issues' && !$include_var) {
            $where[] = "REPLACE(c.Issue_Number,'#','') REGEXP '^[0-9]+$'";
        }
    }

    // if a specific issue number is selected
    if ($issue_number && $issue_number!=='All') {
        if ($tab==='Issues' && $include_var) {
            // include variants for this base number
            $base     = str_replace('#','',$issue_number);
            $where[]  = "(REPLACE(c.Issue_Number,'#','') = ?
                          OR REPLACE(c.Issue_Number,'#','') LIKE ?
                          OR REPLACE(c.Issue_Number,'#','') REGEXP ?)";
            $params[] = $base;
            $params[] = $base.'[A-Z]%';
            $params[] = '^'.preg_quote($base).'(?:[-.]?[A-Za-z].*)?$';
            $types   .= 'sss';
        } elseif ($tab!=='Issues') {
            // exact numeric match on other tabs
            $where[]  = "CAST(REPLACE(c.Issue_Number,'#','') AS UNSIGNED) = ?";
            $params[] = (int)$issue_number; $types .= 'i';
        }
    }

    $whereClause = implode(' AND ', $where);

    // ── MAIN SQL w/ LIMIT/OFFSET ──
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
      LIMIT ? OFFSET ?
    ";

    // bind user_id + filters + pagination
    $types     = 'i'.$types.'ii';
    array_unshift($params, $user_id);
    $params[]  = $limit;
    $params[]  = $offset;

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    // no results
    if ($result->num_rows === 0) {
        echo "";
        exit;
    }

    // ── YEAR BUTTONS (first page only, variants OFF) ──
    if ($offset===0 && $include_var===0) {
        $first       = $result->fetch_assoc();
        $seriesTitle = htmlspecialchars($first['comic_title']);
        $seriesYear  = htmlspecialchars($first['years']);

        // fetch all years
        $stY = $conn->prepare("
          SELECT DISTINCT Years
            FROM Comics
           WHERE Comic_Title = ?
           ORDER BY Years ASC
        ");
        $stY->bind_param('s',$comic_title);
        $stY->execute();
        $resY = $stY->get_result();
        $years = [];
        while($r=$resY->fetch_assoc()) {
            $years[] = $r['Years'];
        }
        $stY->close();

        // rewind pointer
        $result->data_seek(0);
        ?>
        <script>
          function switchYear(year) {
            // build from the *current* search string,
            // so "tab=Issues" and "include_variants=0" stick around
            const p = new URLSearchParams(window.location.search);
            p.set('year', year);
            p.set('offset', 0);
            p.set('limit', <?= $limit ?>);
            fetch('searchResults.php?' + p.toString())
              .then(r => r.text())
              .then(html => {
                document.getElementById('search-results').innerHTML = html;
              });
          }
        </script>
        <div class="series-header-box w-100 text-start mb-4">
          <div style="display:inline-block;">
            <h5 style="font-size:1.25rem;font-weight:600;display:inline-block;margin-right:0.75rem;">
              <?= $seriesTitle ?>
            </h5>
            <div class="btn-group btn-group-sm d-inline-block" role="group">
              <?php foreach($years as $yr):
                $cls = ($yr==$seriesYear)?'btn-primary':'btn-outline-primary';
              ?>
                <button
                  type="button"
                  class="btn <?= $cls ?>"
                  onclick="switchYear('<?= htmlspecialchars($yr,ENT_QUOTES) ?>')">
                  <?= htmlspecialchars($yr) ?>
                </button>
              <?php endforeach; ?>
            </div>
            <hr style="margin:4px 0 0;border:none;border-top:1px solid #ccc;">
          </div>
        </div>
        <?php
    }

    // ── GALLERY WRAPPER (first page only) ──
    if ($offset===0) {
        echo '<div id="resultsGallery" class="gallery">';
    }

    // ── GALLERY ITEMS ──
    while($row=$result->fetch_assoc()){
        $title   = htmlspecialchars($row['comic_title']);
        $issue   = '#'.ltrim($row['issue_number'],'#');
        $yrs     = htmlspecialchars($row['years']);
        $tabVal  = htmlspecialchars($row['tab']);
        $variant = htmlspecialchars($row['variant']);
        $wanted  = !empty($row['wanted_id']);
        $imgRaw  = trim($row['image_path']);
        if (!$imgRaw||strtolower($imgRaw)==='null') {
            $img="/comicsmp/images/default.jpg";
        } elseif(filter_var($imgRaw,FILTER_VALIDATE_URL)) {
            $img=$imgRaw;
        } else {
            $img="/comicsmp".(strpos($imgRaw,'/')===0?$imgRaw:"/$imgRaw");
        }
        ?>
        <div class="gallery-item"
             data-comic-title="<?=$title?>"
             data-years="<?=$yrs?>"
             data-issue-number="<?=$issue?>"
             data-tab="<?=$tabVal?>"
             data-variant="<?=$variant?>"
             data-wanted="<?=(int)$wanted?>"
             data-full="<?=htmlspecialchars($img)?>"
             data-issue-url="<?=htmlspecialchars($row['issue_url'])?>"
             data-date="<?=htmlspecialchars($row['comic_date'])?>"
             data-upc="<?=htmlspecialchars($row['upc'])?>">
          <img src="<?=htmlspecialchars($img)?>" alt="<?=$title?>" class="comic-image">
          <p class="series-issue">Issue: <?=$issue?></p>
          <div class="button-wrapper text-center">
            <?php if($wanted):?>
              <button class="btn btn-success" disabled>Added</button>
            <?php else:?>
              <button class="btn btn-primary add-to-wanted"
                      data-series-name="<?=$title?>"
                      data-issue-number="<?=$issue?>"
                      data-series-year="<?=$yrs?>"
                      data-issue-url="<?=htmlspecialchars($row['issue_url'])?>">
                Wanted
              </button>
            <?php endif;?>
            <button class="btn btn-secondary sell-button"
                    data-series-name="<?=$title?>"
                    data-issue-number="<?=$issue?>"
                    data-series-year="<?=$yrs?>"
                    data-issue-url="<?=htmlspecialchars($row['issue_url'])?>">
              Sell
            </button>
          </div>
        </div>
        <?php
    }

    // ── CLOSE GALLERY ──
    if ($offset===0) {
        echo '</div>';
    }

    $stmt->close();
} catch(Exception $e) {
    echo "<p class='text-danger'>Exception: ".htmlspecialchars($e->getMessage())."</p>";
} finally {
    $conn->close();
}
?>

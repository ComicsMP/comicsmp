<?php
// =====================================
// INITIAL SETUP & QUERIES
// =====================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'db_connection.php';

$user_id = $_SESSION['user_id'] ?? 0;

// --- Helper: Normalize image paths ---
function getFinalImagePath($rawPath) {
    $raw = trim($rawPath ?? '');
    if (empty($raw) || strtolower($raw) === 'null') {
        return '/comicsmp/placeholder.jpg';
    }
    if (filter_var($raw, FILTER_VALIDATE_URL)) {
        $raw = str_replace('/images/images/', '/images/', $raw);
        return $raw;
    }
    $raw = str_replace('/images/images/', '/images/', $raw);
    $raw = preg_replace('#^(images/){2,}#i', 'images/', $raw);
    if (strpos($raw, '/comicsmp/images/') === 0) {
        $final = $raw;
    } elseif (strpos($raw, 'images/') === 0) {
        $final = '/comicsmp/' . $raw;
    } else {
        $final = '/comicsmp/images/' . ltrim($raw, '/');
    }
    $ext = pathinfo($final, PATHINFO_EXTENSION);
    if (empty($ext)) {
        $final .= '.jpg';
    }
    return $final;
}

// --- For Advanced Search: Get distinct countries ---
$queryCountries = "SELECT DISTINCT Country FROM comics ORDER BY Country ASC";
$resultCountries = mysqli_query($conn, $queryCountries);

// --- FETCH WANTED LIST ---
$sql = "
    SELECT comic_title, years, 
           GROUP_CONCAT(issue_number SEPARATOR ', ') AS issues,
           GROUP_CONCAT(Issue_URL SEPARATOR ',') AS issue_urls,
           COUNT(*) AS count
    FROM wanted_items
    WHERE user_id = ?
    GROUP BY comic_title, years
    ORDER BY comic_title ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$resultWanted = $stmt->get_result();
$wantedSeries = [];
while ($row = $resultWanted->fetch_assoc()) {
    $issuesArr = array_map(function ($iss) {
        $iss = trim($iss);
        return (strpos($iss, '#') === 0) ? $iss : '#' . $iss;
    }, explode(',', $row['issues']));
    $row['issues'] = implode(', ', $issuesArr);
    $wantedSeries[] = $row;
}
$stmt->close();

// --- FETCH SALE LISTINGS ---
$sql2 = "
    SELECT comic_title, years, 
           GROUP_CONCAT(issue_number SEPARATOR ', ') AS issues,
           GROUP_CONCAT(Issue_URL SEPARATOR ',') AS issue_urls,
           COUNT(*) AS count
    FROM comics_for_sale
    WHERE user_id = ?
    GROUP BY comic_title, years
    ORDER BY comic_title ASC
";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("i", $user_id);
$stmt2->execute();
$resultSale = $stmt2->get_result();
$saleGroups = [];
while ($row = $resultSale->fetch_assoc()) {
    $issuesArr = array_map(function ($iss) {
        $iss = trim($iss);
        return (strpos($iss, '#') === 0) ? $iss : '#' . $iss;
    }, explode(',', $row['issues']));
    $row['issues'] = implode(', ', $issuesArr);
    $saleGroups[] = $row;
}
$stmt2->close();

// --- FETCH MATCHES ---
$sqlMatches = "
    SELECT 
        mn.id, 
        mn.comic_title, 
        mn.issue_number, 
        mn.years, 
        mn.issue_url, 
        mn.cover_image, 
        cs.image_path, 
        mn.match_time, 
        mn.status,
        mn.buyer_id,
        mn.seller_id,
        cs.comic_condition,
        cs.graded,
        cs.price
    FROM match_notifications mn
    LEFT JOIN comics_for_sale cs ON (cs.issue_url = mn.issue_url AND cs.user_id = mn.seller_id)
    WHERE (mn.buyer_id = ? OR mn.seller_id = ?)
    ORDER BY mn.match_time DESC
";
$stmtMatches = $conn->prepare($sqlMatches);
$stmtMatches->bind_param("ii", $user_id, $user_id);
$stmtMatches->execute();
$resultMatches = $stmtMatches->get_result();
$matches = [];
$otherUserIds = [];
while ($row = $resultMatches->fetch_assoc()) {
    $matches[] = $row;
    if ($row['buyer_id'] == $user_id) {
        $otherUserIds[] = $row['seller_id'];
    } else {
        $otherUserIds[] = $row['buyer_id'];
    }
}
$stmtMatches->close();

// Build a map of user_id => username
$otherUserIds = array_unique($otherUserIds);
$userNamesMap = [];
if (!empty($otherUserIds)) {
    $placeholders = implode(',', array_fill(0, count($otherUserIds), '?'));
    $types = str_repeat('i', count($otherUserIds));
    $sqlUsers = "SELECT id, username FROM users WHERE id IN ($placeholders)";
    $stmtUsers = $conn->prepare($sqlUsers);
    $stmtUsers->bind_param($types, ...$otherUserIds);
    $stmtUsers->execute();
    $resUsers = $stmtUsers->get_result();
    while ($u = $resUsers->fetch_assoc()) {
        $userNamesMap[$u['id']] = $u['username'];
    }
    $stmtUsers->close();
}

// Group matches by the “other” user
$groupedMatches = [];
foreach ($matches as $m) {
    $otherId = ($m['buyer_id'] == $user_id) ? $m['seller_id'] : $m['buyer_id'];
    $groupedMatches[$otherId][] = $m;
}

// --- Get user's currency ---
$currency = '';
$stmtUser = $conn->prepare("SELECT currency FROM users WHERE id = ?");
$stmtUser->bind_param("i", $user_id);
$stmtUser->execute();
$resultUser = $stmtUser->get_result();
if ($rowUser = $resultUser->fetch_assoc()) {
    $currency = $rowUser['currency'];
}
$stmtUser->close();
if (!$currency) {
    $currency = 'USD';
}
?>

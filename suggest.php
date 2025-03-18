<?php
require_once 'db_connection.php';

// GET parameters
$q       = $_GET['q']       ?? '';
$mode    = $_GET['mode']    ?? 'allWords'; // default
$country = $_GET['country'] ?? '';         // new: country parameter

// If $q is too short, exit
if (strlen($q) < 2) {
    exit;
}

// Build base query
$sql = "SELECT DISTINCT Comic_Title FROM Comics WHERE 1=1";

// Add country filter if provided
if (!empty($country)) {
    $sql .= " AND Country = '" . $conn->real_escape_string($country) . "'";
}

// Build conditions based on mode
switch ($mode) {
    case 'allWords':
        $words = preg_split('/\s+/', $q);
        foreach ($words as $w) {
            $sql .= " AND Comic_Title LIKE '%" . $conn->real_escape_string($w) . "%'";
        }
        break;
    case 'anyWords':
        $words = preg_split('/\s+/', $q);
        $likeClauses = [];
        foreach ($words as $w) {
            $likeClauses[] = "Comic_Title LIKE '%" . $conn->real_escape_string($w) . "%'";
        }
        if (!empty($likeClauses)) {
            $sql .= " AND (" . implode(' OR ', $likeClauses) . ")";
        }
        break;
    case 'startsWith':
        $sql .= " AND Comic_Title LIKE '" . $conn->real_escape_string($q) . "%'";
        break;
    case 'exactPhrase':
        $sql .= " AND Comic_Title LIKE '%" . $conn->real_escape_string($q) . "%'";
        break;
    default:
        $words = preg_split('/\s+/', $q);
        foreach ($words as $w) {
            $sql .= " AND Comic_Title LIKE '%" . $conn->real_escape_string($w) . "%'";
        }
        break;
}

// Limit the number of suggestions returned
$sql .= " LIMIT 20";

// Run query
$result = $conn->query($sql);
if (!$result) {
    echo "<p class='text-danger'>DB error: " . $conn->error . "</p>";
    exit;
}

// Output suggestions
while ($row = $result->fetch_assoc()) {
    $title = htmlspecialchars($row['Comic_Title']);
    echo "<div class='suggestion-item'>$title</div>";
}
$result->close();
$conn->close();
?>

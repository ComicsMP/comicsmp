<?php
require_once 'db_connection.php';

// GET parameters
$q    = $_GET['q']    ?? '';
$mode = $_GET['mode'] ?? 'allWords'; // default

// If $q is too short, you can return nothing or a small subset
if (strlen($q) < 2) {
    exit; // or echo some small message
}

// Build your base query
// Example table: "Comics" or "Titles" or whatever you have for suggestions
$sql = "SELECT DISTINCT Comic_Title FROM Comics WHERE 1=1";

// We'll build conditions based on $mode
switch ($mode) {
    case 'allWords':
        // e.g. split $q into words
        // For each word, require it to appear in the Comic_Title
        // PSEUDO:
        $words = preg_split('/\s+/', $q);
        foreach ($words as $w) {
            $sql .= " AND Comic_Title LIKE '%" . $conn->real_escape_string($w) . "%'";
        }
        break;

    case 'anyWords':
        // e.g. any of the words can appear
        // PSEUDO:
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
        // e.g. only titles that start with $q
        $sql .= " AND Comic_Title LIKE '" . $conn->real_escape_string($q) . "%'";
        break;

    case 'exactPhrase':
        // e.g. treat $q as a single exact phrase
        $sql .= " AND Comic_Title LIKE '%" . $conn->real_escape_string($q) . "%'";
        break;

    default:
        // default to something like 'allWords'
        $words = preg_split('/\s+/', $q);
        foreach ($words as $w) {
            $sql .= " AND Comic_Title LIKE '%" . $conn->real_escape_string($w) . "%'";
        }
        break;
}

// Optionally limit results to e.g. 20
$sql .= " LIMIT 20";

// Run the query
$result = $conn->query($sql);
if (!$result) {
    echo "<p class='text-danger'>DB error: " . $conn->error . "</p>";
    exit;
}

// Output each matching Comic_Title as a clickable suggestion
while ($row = $result->fetch_assoc()) {
    $title = htmlspecialchars($row['Comic_Title']);
    echo "<div class='suggestion-item'>$title</div>";
}
$result->close();
$conn->close();

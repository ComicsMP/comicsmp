<?php
require_once 'db_connection.php';

// GET parameters
$q       = $_GET['q']       ?? '';
$mode    = $_GET['mode']    ?? 'startsWith'; // default to the most reliable
$country = $_GET['country'] ?? '';

if (strlen(trim($q)) < 2) exit;

// Normalize input for smart match
function normalize_query($text) {
    $s = preg_replace('/[\-:()]+/', ' ', $text); // Replace common punctuation with space
    $s = preg_replace('/\s+/', ' ', $s);         // Collapse multiple spaces
    return strtolower(trim($s));
}

$params = [];
$types  = "";

switch ($mode) {
    case 'smart':
        $normalized = normalize_query($q);
        $words = explode(' ', $normalized);
        $filtered = array_filter($words, fn($w) => strlen($w) > 1 && !in_array($w, ['a', 'an', 'the']));
        if (empty($filtered)) {
            exit; // nothing meaningful left to search
        }
        $boolean_query = '+' . implode('* +', $filtered) . '*';



        $sql = "SELECT DISTINCT original_title AS Comic_Title
                FROM Comic_SearchIndex
                WHERE MATCH(normalized_title) AGAINST(? IN BOOLEAN MODE)";
        $params[] = $boolean_query;
        $types   .= "s";
        break;

    case 'startsWith':
    default:
        $sql = "SELECT DISTINCT Comic_Title FROM Comics WHERE Comic_Title LIKE ?";
        $params[] = $q . '%';
        $types   .= "s";
        break;
}

$sql .= " ORDER BY Comic_Title ASC LIMIT 50";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<p class='text-danger'>DB error: " . htmlspecialchars($conn->error) . "</p>";
    exit;
}
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

while ($row = $res->fetch_assoc()) {
    echo "<div class='suggestion-item'>" . htmlspecialchars($row['Comic_Title']) . "</div>";
}

$stmt->close();
$conn->close();
?>

<?php
/**
 * fetchWantedExpansions.php
 * Returns each wanted item row for a given Comic_Title + Years + user_id,
 * including the item’s primary ID, so it can be deleted.
 */
require_once 'db_connection.php';
session_start();

header('Content-Type: application/json; charset=utf-8');

// We expect: comic_title, years, user_id
$comic_title = $_POST['comic_title'] ?? '';
$years       = $_POST['years'] ?? '';
$user_id     = $_POST['user_id'] ?? 0;

if (!$comic_title || !$years || !$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters.']);
    exit;
}

try {
    // We must select wanted_items.ID as well as the other columns
    $query = "
        SELECT
            w.ID,
            w.Comic_Title,
            w.Issue_Number,
            w.Years,
            w.Unique_ID,
            c.Image_Path
        FROM wanted_items w
        LEFT JOIN comics c ON w.Unique_ID = c.Unique_ID
        WHERE w.user_id = ?
          AND w.Comic_Title = ?
          AND w.Years = ?
        ORDER BY LENGTH(w.Issue_Number), w.Issue_Number ASC
    ";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(['status' => 'error', 'message' => 'DB error: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param("iss", $user_id, $comic_title, $years);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res->num_rows > 0) {
        $data = [];
        while ($row = $res->fetch_assoc()) {
            // The columns from wanted_items: ID, Comic_Title, Issue_Number, Years, Unique_ID
            // The left-joined comics table: c.Image_Path
            $data[] = $row;
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'No items found.']);
    }
    $stmt->close();
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Failed fetch expansions: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>

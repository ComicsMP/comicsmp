<?php
session_start();

if (!isset($_SESSION['viewed_comics'])) {
    $_SESSION['viewed_comics'] = [];
}

$match_id = $_POST['match_id'] ?? null;
if ($match_id) {
    $_SESSION['viewed_comics'][] = intval($match_id);  // ✅ Keep it from showing again
}

echo json_encode(["status" => "success"]);
?>

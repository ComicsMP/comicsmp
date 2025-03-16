<?php
session_start();
$_SESSION['last_comic_id'] = null; // Clear stored last comic to force fresh query
echo json_encode(["status" => "success"]);
?>

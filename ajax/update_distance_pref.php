<?php
// update_distance_pref.php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
$userId = $_SESSION['user_id'] ?? null;
if (!$userId) {
    http_response_code(403);
    exit;
}

// must receive both radius and unit
if (! isset($_POST['radius'], $_POST['unit'])) {
    http_response_code(400);
    exit;
}

$radius = (int) $_POST['radius'];
// only allow "km" or "mi"
$unit = ($_POST['unit'] === 'km') ? 'km' : 'mi';

require_once __DIR__ . '/../../db_connection.php';

$stmt = $conn->prepare("
    UPDATE users
       SET default_radius = ?,
           distance_unit  = ?
     WHERE id = ?
");
$stmt->bind_param("isi", $radius, $unit, $userId);
$stmt->execute();

<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit;
}

require_once 'db_connection.php';
require_once 'update_matches.php'; // this should contain updateMatchNotifications()

updateMatchNotifications($conn);

$user_id = $_SESSION['user_id'];
$currency = 'USD'; // or pull from session
include 'load_match_results.php'; // this will echo just the #matches tab content

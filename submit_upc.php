<?php
session_start();
require_once 'db_connection.php';

// Ensure the request is a POST request.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

// Only allow authenticated users to submit.
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] == 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

// Retrieve and trim POST inputs.
$upc       = isset($_POST['upc']) ? trim($_POST['upc']) : '';
$issue_url = isset($_POST['issue_url']) ? trim($_POST['issue_url']) : '';

// Check that both UPC and Issue URL are provided.
if (empty($upc) || empty($issue_url)) {
    echo json_encode(['status' => 'error', 'message' => 'Missing UPC or Issue URL.']);
    exit;
}

// Validate UPC:
// If a hyphen is present, require exactly 12 digits before it.
if (strpos($upc, '-') !== false) {
    if (!preg_match('/^\d{12}-\d+$/', $upc)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid UPC format with hyphen.']);
        exit;
    }
} else {
    // No hyphen: if length > 14, then error.
    if (strlen($upc) > 14) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid UPC format; if longer than 14 digits, include a hyphen.']);
        exit;
    }
}

// Update the Comics table using Issue_URL as the unique identifier.
$sqlUpdate = "UPDATE Comics SET UPC = ? WHERE Issue_URL = ?";
$stmt = $conn->prepare($sqlUpdate);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ss", $upc, $issue_url);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['status' => 'success', 'message' => 'UPC updated successfully.']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No update performed.']);
}

$stmt->close();
$conn->close();
?>

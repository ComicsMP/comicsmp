<?php
session_start();
require_once 'db_connection.php';
header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'] ?? 0;
    $comic_title = $_POST['comic_title'] ?? '';
    $issue_number = $_POST['issue_number'] ?? '';
    $years = $_POST['years'] ?? '';
    $issue_url = $_POST['issue_url'] ?? null;

// If not provided, try to fetch it from the comics table using the title/year/issue combo
if (!$issue_url) {
    $lookup = $conn->prepare("
        SELECT Issue_URL
        FROM comics
        WHERE Comic_Title = ? AND Years = ? AND REPLACE(Issue_Number, '#','') = REPLACE(?, '#','')
        LIMIT 1
    ");
    $lookup->bind_param("sss", $comic_title, $years, $issue_number);
    $lookup->execute();
    $res = $lookup->get_result();
    if ($found = $res->fetch_assoc()) {
        $issue_url = $found['Issue_URL'];
    }
    $lookup->close();
}


    if (!$user_id || !$comic_title || !$issue_number || !$years) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields',
        'debug' => [
            'user_id' => $user_id,
            'comic_title' => $comic_title,
            'issue_number' => $issue_number,
            'years' => $years
        ]
    ]);
    exit;
}


    // Check for duplicates
    if ($issue_url) {
        $stmt = $conn->prepare("SELECT id FROM wanted_items WHERE user_id = ? AND comic_title = ? AND issue_number = ? AND years = ? AND issue_url = ?");
        $stmt->bind_param("issss", $user_id, $comic_title, $issue_number, $years, $issue_url);
    } else {
        $stmt = $conn->prepare("SELECT id FROM wanted_items WHERE user_id = ? AND comic_title = ? AND issue_number = ? AND years = ?");
        $stmt->bind_param("isss", $user_id, $comic_title, $issue_number, $years);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
    echo json_encode([
        "status" => "duplicate",
        "message" => "Already in wanted list"
    ]);
    exit;
}

    $stmt->close();

    // Insert the record
    if ($issue_url) {
        $stmt = $conn->prepare("INSERT INTO wanted_items (user_id, comic_title, issue_number, years, issue_url) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $comic_title, $issue_number, $years, $issue_url);
    } else {
        $stmt = $conn->prepare("INSERT INTO wanted_items (user_id, comic_title, issue_number, years) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("isss", $user_id, $comic_title, $issue_number, $years);
    }

    if ($stmt->execute()) {
    echo json_encode([
        "status" => "success",
        "message" => "Added to wanted list"
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        "status" => "error",
        "message" => "Error adding to wanted list"
    ]);
}


    $stmt->close();
    $conn->close();
} else {
    http_response_code(405);
    echo "Method not allowed";
}

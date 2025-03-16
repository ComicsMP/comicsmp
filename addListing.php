<?php
// Enable error reporting for debugging (remove these lines in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connection.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "You must be logged in to post a listing."]);
    exit;
}

// Fetch form data (using the names from your sell form)
$user_id      = $_SESSION['user_id'];
$comic_title  = $_POST['comic_title'] ?? null;  // from hidden input
$issue_number = $_POST['issue_number'] ?? null;   // from hidden input
$years        = $_POST['years'] ?? null;          // from hidden input
$price        = $_POST['price'] ?? null;          // from price input
$condition    = $_POST['condition'] ?? null;      // from condition dropdown
$graded       = $_POST['graded'] ?? null;         // from graded dropdown
$issue_url    = $_POST['issue_url'] ?? '';        // new parameter

// Validate required form data
if (!$comic_title || !$issue_number || !$years || !$price || !$condition || $graded === null || !$issue_url) {
    echo json_encode(["status" => "error", "message" => "All required fields must be filled out."]);
    exit;
}

// ✅ Step 1: Fetch existing unique_id, image_path, and Issue_URL from the `comics` table
$unique_id = null;
$image_path = '/comicsmp/placeholder.jpg'; // Default placeholder

// Updated query: Issue_URL is now required to ensure uniqueness
$query = "SELECT Unique_ID, Image_Path, Issue_URL FROM comics WHERE Comic_Title = ? AND Years = ? AND Issue_Number = ? AND Issue_URL = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ssss", $comic_title, $years, $issue_number, $issue_url);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $comic = $result->fetch_assoc();
    $unique_id = $comic['Unique_ID'];
    $image_path = $comic['Image_Path'] ?: $image_path;
}
$stmt->close();

// ✅ Step 2: Insert the listing into the `comics_for_sale` table (including Issue_URL)
try {
    $conn->begin_transaction();

    // Escape values to prevent SQL injection
    $escaped_comic_title  = $conn->real_escape_string($comic_title);
    $escaped_issue_number = $conn->real_escape_string($issue_number);
    $escaped_years        = $conn->real_escape_string($years);
    $escaped_condition    = $conn->real_escape_string($condition);
    $escaped_price        = $conn->real_escape_string($price);
    $escaped_graded       = $conn->real_escape_string($graded);
    $escaped_image_path   = $conn->real_escape_string($image_path);
    $escaped_unique_id    = $conn->real_escape_string($unique_id);
    $escaped_issue_url    = $conn->real_escape_string($issue_url);

    // Build the query including the Issue_URL field
    $query = "INSERT INTO comics_for_sale 
              (user_id, comic_title, issue_number, years, comic_condition, price, graded, image_path, unique_id, Issue_URL, created_at) 
              VALUES ('$user_id', '$escaped_comic_title', '$escaped_issue_number', '$escaped_years', '$escaped_condition', '$escaped_price', '$escaped_graded', '$escaped_image_path', '$escaped_unique_id', '$escaped_issue_url', NOW())";

    // Log the query for debugging
    error_log("Insert Query: " . $query);

    if (!$conn->query($query)) {
        throw new Exception("Error inserting data: " . $conn->error);
    }

    $conn->commit();
    echo json_encode(["status" => "success", "message" => "Listing added successfully."]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Insert error: " . $e->getMessage());
    echo json_encode(["status" => "error", "message" => "Error adding listing: " . $e->getMessage()]);
} finally {
    $conn->close();
}
?>

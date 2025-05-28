<?php
// addListing.php
// Enable error reporting for debugging (remove these lines in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

include 'db_connection.php';
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        "status"  => "error",
        "message" => "You must be logged in to post a listing."
    ]);
    exit;
}

// Fetch and trim form data
$user_id      = $_SESSION['user_id'];
$comic_title  = isset($_POST['comic_title'])  ? trim($_POST['comic_title'])  : '';
$issue_number = isset($_POST['issue_number']) ? trim($_POST['issue_number']) : '';
$years        = isset($_POST['years'])        ? trim($_POST['years'])        : '';
$price        = isset($_POST['price'])        ? trim($_POST['price'])        : '';
$condition    = isset($_POST['condition'])    ? trim($_POST['condition'])    : '';
$graded       = isset($_POST['graded'])       ? trim($_POST['graded'])       : '';
$issue_url    = isset($_POST['issue_url'])    ? trim($_POST['issue_url'])    : '';

// Validate required form data (allow '0' as valid value)
$required_fields = [
    'comic_title'  => $comic_title,
    'issue_number' => $issue_number,
    'years'        => $years,
    'price'        => $price,
    'condition'    => $condition,
    'graded'       => $graded,
    'issue_url'    => $issue_url
];

foreach ($required_fields as $field_name => $field_value) {
    if ($field_value === '') {
        echo json_encode([
            "status"  => "error",
            "message" => "All required fields must be filled out."
        ]);
        exit;
    }
}

// Step 1: Fetch existing unique_id, image_path (and confirm issue_url) from the comics table
$unique_id   = null;
$image_path  = '/comicsmp/placeholder.jpg';

$query = "
    SELECT Unique_ID, Image_Path, Issue_URL
      FROM comics
     WHERE Comic_Title  = ?
       AND Years        = ?
       AND Issue_Number = ?
     LIMIT 1
";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $comic_title, $years, $issue_number);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    $unique_id  = $row['Unique_ID'];
    $image_path = $row['Image_Path'] ?: $image_path;
    // Optionally override front-end URL if you trust the DB as source of truth
    $issue_url  = $row['Issue_URL'] ?: $issue_url;
}
$stmt->close();

// Step 2: Insert the listing into comics_for_sale using a prepared statement
try {
    $conn->begin_transaction();

    $insertSql = "
        INSERT INTO comics_for_sale
            (user_id, comic_title, issue_number, years,
             comic_condition, price, graded,
             image_path, unique_id, Issue_URL, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ";
    $stmt = $conn->prepare($insertSql);
    $stmt->bind_param(
        "issssissss",
        $user_id,
        $comic_title,
        $issue_number,
        $years,
        $condition,
        $price,
        $graded,
        $image_path,
        $unique_id,
        $issue_url
    );

    if (!$stmt->execute()) {
        throw new Exception("Insert failed: " . $stmt->error);
    }

    $conn->commit();
    echo json_encode([
        "status"  => "success",
        "message" => "Listing added successfully."
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("addListing error: " . $e->getMessage());
    echo json_encode([
        "status"  => "error",
        "message" => "Error adding listing: " . $e->getMessage()
    ]);
} finally {
    if (isset($stmt) && $stmt instanceof mysqli_stmt) {
        $stmt->close();
    }
    $conn->close();
}
?>

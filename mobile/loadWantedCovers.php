<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden"]);
    exit;
}
$userId = $_SESSION['user_id'];

if (empty($_GET['title'])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing comic title."]);
    exit;
}
$comicTitle = $_GET['title'];

$sql = "SELECT w.Comic_Title, w.Issue_Number, w.Years, w.Issue_URL, c.Image_Path 
        FROM wanted_items w
        LEFT JOIN comics c ON w.Issue_URL = c.Issue_URL
        WHERE w.user_id = ? AND w.Comic_Title = ?
        ORDER BY w.Issue_Number+0 ASC, (w.Issue_Number LIKE '%-%') ASC, w.Issue_Number ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $userId, $comicTitle);
$stmt->execute();
$result = $stmt->get_result();

$covers = [];
while ($row = $result->fetch_assoc()) {
    $rawPath = $row['Image_Path'];
    if (!empty($rawPath)) {
        $cleanPath = ltrim($rawPath, '/');
        $imageSrc = "../" . $cleanPath;
    } else {
        $imageSrc = "../images/placeholder.jpg";
    }
    $covers[] = [
       "Comic_Title"  => $row['Comic_Title'],
       "Issue_Number" => $row['Issue_Number'],
       "Years"        => $row['Years'],
       "Issue_URL"    => $row['Issue_URL'],
       "image"        => $imageSrc
    ];
}
$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($covers);
?>

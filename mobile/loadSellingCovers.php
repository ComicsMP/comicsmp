<?php
session_start();
require_once 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden"]);
    exit;
}
$userId = $_SESSION['user_id'];

$comicTitle = '';
if (!empty($_GET['title'])) {
    $comicTitle = $_GET['title'];
} elseif (!empty($_GET['comic_title'])) {
    $comicTitle = $_GET['comic_title'];
}

if (empty($comicTitle)) {
    http_response_code(400);
    echo json_encode(["error" => "Missing comic title."]);
    exit;
}

// Join with users table to get the seller's preferred currency.
$sql = "SELECT cs.id, cs.Issue_Number, cs.Years, cs.Issue_URL, cs.image_path, cs.comic_condition, cs.price, cs.seller_currency, cs.graded, u.preferred_currency
        FROM comics_for_sale cs
        JOIN users u ON cs.user_id = u.id
        WHERE cs.user_id = ? AND cs.comic_title = ?
        ORDER BY cs.Issue_Number+0 ASC, (cs.Issue_Number LIKE '%-%') ASC, cs.Issue_Number ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("is", $userId, $comicTitle);
$stmt->execute();
$result = $stmt->get_result();

$covers = [];
while ($row = $result->fetch_assoc()) {
    $rawPath = $row['image_path'];
    if (!empty($rawPath)) {
        $cleanPath = ltrim($rawPath, '/');
        $imageSrc = "../" . $cleanPath;
    } else {
        $imageSrc = "../images/placeholder.jpg";
    }
    $covers[] = [
       "id"              => $row['id'],
       "Issue_Number"    => $row['Issue_Number'],
       "Years"           => $row['Years'],
       "Issue_URL"       => $row['Issue_URL'],
       "image"           => $imageSrc,
       "comic_condition" => $row['comic_condition'],
       "price"           => $row['price'],
       "seller_currency" => $row['seller_currency'],
       "graded"          => $row['graded'],
       "preferred_currency" => $row['preferred_currency']
    ];
}

$stmt->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode($covers);
?>

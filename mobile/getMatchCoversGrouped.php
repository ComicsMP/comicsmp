<?php
session_start();
require_once 'db_connection.php';

// Ensure the user is logged in.
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(["error" => "Forbidden"]);
    exit;
}
$user_id = $_SESSION['user_id'];

// ----------------------------------------
// Helper: Calculate Distance using Haversine Formula
// ----------------------------------------
function calculateDistance($lat1, $lon1, $lat2, $lon2, $unit = "M") {
    // Convert degrees to radians
    $lat1 = deg2rad($lat1);
    $lon1 = deg2rad($lon1);
    $lat2 = deg2rad($lat2);
    $lon2 = deg2rad($lon2);
    
    $dlon = $lon2 - $lon1;
    $dlat = $lat2 - $lat1;
    $a = pow(sin($dlat/2), 2) + cos($lat1) * cos($lat2) * pow(sin($dlon/2), 2);
    $c = 2 * asin(sqrt($a));
    // Radius of earth in miles
    $r = 3956;
    $miles = $c * $r;
    
    if ($unit == "K") {
        return $miles * 1.609344;
    } else if ($unit == "N") {
        return $miles * 0.8684;
    } else {
        return $miles;
    }
}

// ----------------------------------------
// Helper: Normalize image paths
// ----------------------------------------
function getFixedImagePath($rawPath) {
    $rawPath = trim($rawPath);
    // Remove any leading "images/" or "/images/" (case-insensitive)
    $rawPath = preg_replace('/^(\/)?images\//i', '', $rawPath);
    return '/comicsmp/images/' . $rawPath;
}

// ----------------------------------------
// Get current user's latitude and longitude
// ----------------------------------------
$currentUserLat = 0;
$currentUserLng = 0;
$stmtCurrent = $conn->prepare("SELECT latitude, longitude FROM users WHERE id = ?");
$stmtCurrent->bind_param("i", $user_id);
$stmtCurrent->execute();
$resultCurrent = $stmtCurrent->get_result();
if ($row = $resultCurrent->fetch_assoc()) {
    $currentUserLat = $row['latitude'];
    $currentUserLng = $row['longitude'];
}
$stmtCurrent->close();

// ----------------------------------------
// Initialize arrays for buy and sell covers
// ----------------------------------------
$buy = [];  // Covers when current user is buyer (buying from other party)
$sell = []; // Covers when current user is seller (selling to other party)

// Validate and get the other_user_id from GET.
$other_user_id = $_GET['other_user_id'] ?? '';
if (empty($other_user_id) || !is_numeric($other_user_id)) {
    echo json_encode(["error" => "Invalid other_user_id parameter"]);
    exit;
}
$other_user_id = (int)$other_user_id;

// ----------------------------------------
// Query for "buy" covers: current user is buyer and other_user is seller.
// ----------------------------------------
$sqlBuy = "SELECT 
    mn.id as match_id,
    mn.comic_title,
    mn.issue_number,
    mn.years,
    mn.issue_url,
    COALESCE(cs.image_path, mn.cover_image) as raw_image,
    cs.comic_condition,
    cs.price,
    cs.seller_currency,
    u.city,
    u.preferred_currency,
    u.latitude as seller_lat,
    u.longitude as seller_lng
FROM match_notifications mn
LEFT JOIN comics_for_sale cs 
    ON (cs.issue_url = mn.issue_url AND cs.user_id = mn.seller_id)
LEFT JOIN users u 
    ON u.id = mn.seller_id
WHERE mn.buyer_id = ? AND mn.seller_id = ?";
$stmtBuy = $conn->prepare($sqlBuy);
$stmtBuy->bind_param("ii", $user_id, $other_user_id);
$stmtBuy->execute();
$resultBuy = $stmtBuy->get_result();
while ($row = $resultBuy->fetch_assoc()) {
    $image = getFixedImagePath($row['raw_image']);
    // Use preferred_currency if available; otherwise fallback to seller_currency.
    $currency = !empty($row['preferred_currency']) ? $row['preferred_currency'] : ($row['seller_currency'] ?? '');
    $price = !empty($row['price']) ? '$' . number_format($row['price'], 2) . ' ' . $currency : 'N/A';
    // Calculate distance if coordinates are available.
    if (!empty($currentUserLat) && !empty($currentUserLng) && !empty($row['seller_lat']) && !empty($row['seller_lng'])) {
        $dist = calculateDistance($currentUserLat, $currentUserLng, $row['seller_lat'], $row['seller_lng'], "M");
        $distance = number_format($dist, 1) . " miles";
    } else {
        $distance = "N/A";
    }
    $buy[] = [
        "id" => $row['match_id'],
        "comic_title" => $row['comic_title'],
        "issue_number" => $row['issue_number'],
        "years" => $row['years'],
        "image" => $image,
        "condition" => $row['comic_condition'],
        "price" => $price,
        "city" => $row['city'] ?? '',
        "distance" => $distance
    ];
}
$stmtBuy->close();

// ----------------------------------------
// Query for "sell" covers: current user is seller and other_user is buyer.
// ----------------------------------------
$sqlSell = "SELECT 
    mn.id as match_id,
    mn.comic_title,
    mn.issue_number,
    mn.years,
    mn.issue_url,
    COALESCE(cs.image_path, mn.cover_image) as raw_image,
    cs.comic_condition,
    cs.price,
    cs.seller_currency,
    u.city,
    u.preferred_currency,
    u.latitude as buyer_lat,
    u.longitude as buyer_lng
FROM match_notifications mn
LEFT JOIN comics_for_sale cs 
    ON (cs.issue_url = mn.issue_url AND cs.user_id = mn.seller_id)
LEFT JOIN users u 
    ON u.id = mn.buyer_id
WHERE mn.seller_id = ? AND mn.buyer_id = ?";
$stmtSell = $conn->prepare($sqlSell);
$stmtSell->bind_param("ii", $user_id, $other_user_id);
$stmtSell->execute();
$resultSell = $stmtSell->get_result();
while ($row = $resultSell->fetch_assoc()) {
    $image = getFixedImagePath($row['raw_image']);
    $currency = !empty($row['preferred_currency']) ? $row['preferred_currency'] : ($row['seller_currency'] ?? '');
    $price = !empty($row['price']) ? '$' . number_format($row['price'], 2) . ' ' . $currency : 'N/A';
    if (!empty($currentUserLat) && !empty($currentUserLng) && !empty($row['buyer_lat']) && !empty($row['buyer_lng'])) {
        $dist = calculateDistance($currentUserLat, $currentUserLng, $row['buyer_lat'], $row['buyer_lng'], "M");
        $distance = number_format($dist, 1) . " miles";
    } else {
        $distance = "N/A";
    }
    $sell[] = [
        "id" => $row['match_id'],
        "comic_title" => $row['comic_title'],
        "issue_number" => $row['issue_number'],
        "years" => $row['years'],
        "image" => $image,
        "condition" => $row['comic_condition'],
        "price" => $price,
        "city" => $row['city'] ?? '',
        "distance" => $distance
    ];
}
$stmtSell->close();
$conn->close();

header('Content-Type: application/json');
echo json_encode(["buy" => $buy, "sell" => $sell]);
?>

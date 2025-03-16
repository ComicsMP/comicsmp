<?php
// update_matches.php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once 'db_connection.php';

// Initialize counters for inserted matches and deleted notifications.
$insertCount = 0;
$deleteCount = 0;

/* --- MATCHMAKER LOGIC --- */
// Get all wanted listings.
$query = "SELECT id, user_id, comic_title, issue_number, years, issue_url FROM wanted_items";
$result = $conn->query($query);
if (!$result) {
    die("Query error: " . $conn->error);
}

while ($wanted = $result->fetch_assoc()) {
    $buyer_id = $wanted['user_id'];
    // Standardize the URL.
    $wantedURL = strtolower(trim($wanted['issue_url']));
    
    // Find sale listings that match the wanted URL.
    $stmt = $conn->prepare("
      SELECT s.id AS sale_id, s.user_id AS seller_id, s.issue_url, s.image_path
      FROM comics_for_sale s
      WHERE LOWER(TRIM(s.issue_url)) = ?
    ");
    if (!$stmt) {
        die("Prepare error: " . $conn->error);
    }
    $stmt->bind_param("s", $wantedURL);
    $stmt->execute();
    $saleResult = $stmt->get_result();
    
    while ($sale = $saleResult->fetch_assoc()) {
        $seller_id = $sale['seller_id'];
        // Skip if buyer and seller are the same.
        if ($buyer_id == $seller_id) {
            continue;
        }
        
        // Check if a match record already exists.
        $checkStmt = $conn->prepare("
             SELECT id FROM match_notifications 
             WHERE buyer_id = ? AND seller_id = ? 
               AND LOWER(TRIM(issue_url)) = ?
        ");
        $checkStmt->bind_param("iis", $buyer_id, $seller_id, $wantedURL);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows == 0) {
            // Use the sale listing's image_path as cover image.
            $coverURL = trim($sale['image_path']);
            if (empty($coverURL)) {
                $coverURL = '/comicsmp/placeholder.jpg';
            }
            
            $insertStmt = $conn->prepare("INSERT INTO match_notifications 
                (buyer_id, seller_id, comic_title, issue_number, years, issue_url, cover_image, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'new')");
            if (!$insertStmt) {
                die("Insert prepare error: " . $conn->error);
            }
            $insertStmt->bind_param("iisssss", $buyer_id, $seller_id, $wanted['comic_title'], $wanted['issue_number'], $wanted['years'], $wanted['issue_url'], $coverURL);
            $insertStmt->execute();
            $insertStmt->close();
            $insertCount++;
        }
        $checkStmt->close();
    }
    $stmt->close();
}

/* --- CLEANUP MATCHES LOGIC --- */
// Retrieve all match notifications.
$sql = "SELECT id, buyer_id, seller_id, issue_url FROM match_notifications";
$result = $conn->query($sql);
if (!$result) {
    die("Query error: " . $conn->error);
}

while ($row = $result->fetch_assoc()) {
    $matchId = $row['id'];
    $buyer_id = $row['buyer_id'];
    $seller_id = $row['seller_id'];
    $matchIssueURL = strtolower(trim($row['issue_url']));

    // Check if a corresponding wanted item exists for the buyer.
    $stmt1 = $conn->prepare("SELECT COUNT(*) AS cnt FROM wanted_items WHERE user_id = ? AND LOWER(TRIM(issue_url)) = ?");
    if (!$stmt1) {
        die("Prepare error (wanted): " . $conn->error);
    }
    $stmt1->bind_param("is", $buyer_id, $matchIssueURL);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    $wantedCount = 0;
    if ($data1 = $res1->fetch_assoc()) {
        $wantedCount = $data1['cnt'];
    }
    $stmt1->close();

    // Check if a corresponding sale listing exists for the seller.
    $stmt2 = $conn->prepare("SELECT COUNT(*) AS cnt FROM comics_for_sale WHERE user_id = ? AND LOWER(TRIM(issue_url)) = ?");
    if (!$stmt2) {
        die("Prepare error (sale): " . $conn->error);
    }
    $stmt2->bind_param("is", $seller_id, $matchIssueURL);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    $saleCount = 0;
    if ($data2 = $res2->fetch_assoc()) {
        $saleCount = $data2['cnt'];
    }
    $stmt2->close();

    // If either listing is missing, delete the match notification.
    if ($wantedCount == 0 || $saleCount == 0) {
        $delStmt = $conn->prepare("DELETE FROM match_notifications WHERE id = ?");
        if ($delStmt) {
            $delStmt->bind_param("i", $matchId);
            $delStmt->execute();
            $delStmt->close();
            $deleteCount++;
        }
    }
}

$conn->close();

echo "Matchmaker inserted $insertCount matches; Cleanup deleted $deleteCount match notifications.";
?>

<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

$user_id   = $_SESSION['user_id'] ?? 0;
$listing_id = $_POST['listing_id'] ?? '';
$price      = $_POST['price'] ?? 0;
$condition  = $_POST['condition'] ?? '';

if (!$user_id || !$listing_id) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

try {
    // Split comma-separated IDs into an array.
    $idsArray = explode(',', $listing_id);
    $success = true;

    // Prepare the update statement outside the loop if possible.
    $sql = "UPDATE comics_for_sale SET price = ?, comic_condition = ? WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Failed to prepare statement.");
    }
    
    // Loop over each ID and perform the update.
    foreach ($idsArray as $id) {
        $id = trim($id);
        if (empty($id)) {
            continue;
        }
        $stmt->bind_param("dsii", $price, $condition, $id, $user_id);
        if (!$stmt->execute()) {
            $success = false;
            break;
        }
    }
    $stmt->close();

    if ($success) {
        echo json_encode(['status' => 'success', 'message' => 'Sale listings updated.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'One or more updates failed.']);
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Update failed: ' . $e->getMessage()]);
} finally {
    $conn->close();
}
?>


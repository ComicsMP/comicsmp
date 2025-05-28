<?php
session_start();
require_once 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['upc']) || empty(trim($_GET['upc']))) {
    echo json_encode([
        'success' => false,
        'message' => 'No UPC provided'
    ]);
    exit;
}

// 🔧 Normalize user input to match DB format (insert dash after 12 digits)
$rawInput = trim($_GET['upc']);
$digitsOnly = preg_replace('/\D/', '', $rawInput); // Remove all non-digit characters

if (strlen($digitsOnly) >= 13) {
    $upc = substr($digitsOnly, 0, 12) . '-' . substr($digitsOnly, 12);
} else {
    $upc = $digitsOnly; // Fallback (no dash if it's short or malformed)
}

$user_id = $_SESSION['user_id'] ?? 0;
error_log("🔍 Searching for normalized UPC: $upc");

// Step 1: Fetch all rows matching this UPC (including issue_url)
$query = "
    SELECT
        ID,
        comic_title,
        years,
        issue_number,
        image_path,
        issue_url
    FROM comics
    WHERE upc = ?
";
if (!$stmt = $conn->prepare($query)) {
    error_log("❌ SQL prepare error: " . $conn->error);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
    exit;
}

$stmt->bind_param("s", $upc);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("❌ No comic found for UPC: $upc");
    echo json_encode([
        'success' => false,
        'message' => 'Comic not found'
    ]);
    $stmt->close();
    $conn->close();
    exit;
}

// Collect all matching rows
$rows = [];
while ($row = $result->fetch_assoc()) {
    error_log("🧪 MATCHED ROW: " . print_r($row, true));
    $rows[] = $row;
}
$stmt->close();

// Step 2: Choose the preferred row (one with a non-default image)
$preferred = null;
foreach ($rows as $r) {
    if (
        isset($r['image_path']) &&
        $r['image_path'] !== '/images/default.jpg' &&
        stripos($r['image_path'], 'default') === false
    ) {
        $preferred = $r;
        error_log("✅ Using preferred row with real image.");
        break;
    }
}
if (!$preferred) {
    $preferred = $rows[0];
    error_log("⚠️ Using fallback row with default image.");
}

// Step 3: Check if this comic is in the user's wanted list
$isWanted = false;
if ($user_id && isset($preferred['comic_title'], $preferred['issue_number'], $preferred['years'])) {
    $check = $conn->prepare("
        SELECT id FROM wanted_items
        WHERE user_id = ? AND comic_title = ? AND issue_number = ? AND years = ?
        LIMIT 1
    ");
    $check->bind_param("isss", $user_id, $preferred['comic_title'], $preferred['issue_number'], $preferred['years']);
    $check->execute();
    $res = $check->get_result();
    $isWanted = $res->num_rows > 0;
    $check->close();
}

// Step 4: Build and send the JSON response
$response = [
    'success'      => true,
    'comic_title'  => $preferred['comic_title'],
    'years'        => $preferred['years'],
    'issue_number' => $preferred['issue_number'],
    'image_path'   => $preferred['image_path'],
    'issue_url'    => $preferred['issue_url'],
    'is_wanted'    => $isWanted
];

error_log("📤 Final JSON Response: " . json_encode($response));
echo json_encode($response);

$conn->close();
?>

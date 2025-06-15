<?php
header("Content-Type: application/json"); // Set header for JSON response

// --- 1. Configuration & Security ---
// It's a good practice to use a simple "API key" to prevent unauthorized requests.
// This key must be sent by your ESP32.
$API_KEY = "ESP32_SECRET_KEY"; // Change this to a random, secret key

// Include your database connection file
include 'db_connection.php'; 

// --- 2. Input Validation ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method. Please use POST.']);
    exit();
}

// Check for API key, device ID, and status in the POST data
if (empty($_POST['api_key']) || $_POST['api_key'] !== $API_KEY) {
    http_response_code(403); // Forbidden
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing API key.']);
    exit();
}

$device_id = trim($_POST['device_id'] ?? '');
$status = trim($_POST['status'] ?? '');

if (empty($device_id) || empty($status)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Device ID and status are required.']);
    exit();
}

// Validate the status against the allowed enum values from your database
$allowed_statuses = ['online', 'offline', 'error'];
if (!in_array($status, $allowed_statuses)) {
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => 'Invalid status value.']);
    exit();
}

// --- 3. Database Operation (Insert or Update) ---
// Using "INSERT ... ON DUPLICATE KEY UPDATE" is efficient.
// It inserts a new row if the device_id doesn't exist,
// or updates the existing row if it does.
// This relies on the UNIQUE key we added in Step 1.
$sql = "INSERT INTO device_status (device_id, status) 
        VALUES (?, ?) 
        ON DUPLICATE KEY UPDATE status = VALUES(status), last_checked = NOW()";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database prepare failed: ' . $conn->error]);
    exit();
}

$stmt->bind_param("ss", $device_id, $status);

if ($stmt->execute()) {
    http_response_code(200); // OK
    echo json_encode(['status' => 'success', 'message' => 'Device status updated successfully.']);
} else {
    http_response_code(500); // Internal Server Error
    echo json_encode(['status' => 'error', 'message' => 'Database execute failed: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
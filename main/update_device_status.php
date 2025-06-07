<?php
// --- update_device_status.php ---

// **IMPORTANT SECURITY NOTE:**
// In your update_device_status.php script
define('SECRET_API_KEY', 'aK3j7$sP9!zQ6hT2cE5gV8rWbN4xY1uF'); // The same generated API Key

header("Content-Type: application/json"); // Send JSON responses back to the Arduino

// --- 0. Include DB Connection ---
// Make sure db_connection.php is correctly pathed if this script is in a subdirectory
include 'db_connection.php'; // Or '../db_connection.php' etc.

$response = ['status' => 'error', 'message' => 'Invalid request'];

// --- 1. Check Request Method (Optional but good practice) ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Using POST is generally better for sending data
    $response['message'] = 'Invalid request method. Please use POST.';
    echo json_encode($response);
    exit();
}

// --- 2. Get Data from Arduino (POST request) ---
$api_key = $_POST['apikey'] ?? null;
$device_id_from_arduino = $_POST['device_id'] ?? null;
$status_from_arduino = $_POST['status'] ?? null;

// --- 3. Validate API Key ---
if ($api_key === null || $api_key !== SECRET_API_KEY) {
    $response['message'] = 'Unauthorized: Invalid API Key.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit();
}

// --- 4. Validate Inputs ---
if (empty($device_id_from_arduino) || empty($status_from_arduino)) {
    $response['message'] = 'Missing device_id or status.';
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}

// Validate status value against your ENUM options in the DB
$allowed_statuses = ['online', 'offline', 'error'];
if (!in_array(strtolower($status_from_arduino), $allowed_statuses)) {
    $response['message'] = 'Invalid status value. Allowed: online, offline, error.';
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit();
}
$status_from_arduino = strtolower($status_from_arduino); // Ensure lowercase to match ENUM

// --- 5. Interact with Database ---
if ($conn) {
    // Check if the device_id already exists
    $sql_check = "SELECT id FROM device_status WHERE device_id = ?";
    $stmt_check = $conn->prepare($sql_check);

    if ($stmt_check) {
        $stmt_check->bind_param("s", $device_id_from_arduino);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows > 0) {
            // Device exists, UPDATE it
            $stmt_check->close(); // Close previous statement

            $sql_update = "UPDATE device_status SET status = ?, last_checked = CURRENT_TIMESTAMP WHERE device_id = ?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("ss", $status_from_arduino, $device_id_from_arduino);
                if ($stmt_update->execute()) {
                    $response['status'] = 'success';
                    $response['message'] = 'Device status updated successfully.';
                    http_response_code(200); // OK
                } else {
                    $response['message'] = 'Database update error: ' . $stmt_update->error;
                    http_response_code(500); // Internal Server Error
                }
                $stmt_update->close();
            } else {
                $response['message'] = 'Database prepare error (UPDATE): ' . $conn->error;
                http_response_code(500);
            }
        } else {
            // Device does not exist, INSERT it
            $stmt_check->close(); // Close previous statement

            $sql_insert = "INSERT INTO device_status (device_id, status, last_checked) VALUES (?, ?, CURRENT_TIMESTAMP)";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                $stmt_insert->bind_param("ss", $device_id_from_arduino, $status_from_arduino);
                if ($stmt_insert->execute()) {
                    $response['status'] = 'success';
                    $response['message'] = 'New device registered successfully.';
                    http_response_code(201); // Created
                } else {
                    $response['message'] = 'Database insert error: ' . $stmt_insert->error;
                    http_response_code(500);
                }
                $stmt_insert->close();
            } else {
                $response['message'] = 'Database prepare error (INSERT): ' . $conn->error;
                http_response_code(500);
            }
        }
    } else {
        $response['message'] = 'Database prepare error (CHECK): ' . $conn->error;
        http_response_code(500);
    }
    $conn->close();
} else {
    $response['message'] = 'Database connection failed. Check db_connection.php.';
    http_response_code(500);
}

echo json_encode($response);
?>
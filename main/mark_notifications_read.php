<?php
session_start();
header('Content-Type: application/json');

// Include database connection
include 'db_connection.php'; // Adjust path if necessary

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$teacher_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response['message'] = "Invalid CSRF token. Please refresh the page and try again.";
        echo json_encode($response);
        exit();
    }

    // Prepare and execute the update query
    $stmt = $conn->prepare("UPDATE teacher_notifications SET is_read = TRUE WHERE teacher_id = ? AND is_read = FALSE");
    if ($stmt) {
        $stmt->bind_param("i", $teacher_id);
        if ($stmt->execute()) {
            $response['success'] = true;
            $response['message'] = "All notifications marked as read.";
            // Optionally, log the action
            error_log("Teacher ID: $teacher_id marked all notifications as read.");
        } else {
            $response['message'] = "Failed to mark notifications as read: " . $stmt->error;
            error_log("DB Error marking notifications read (teacher_id: $teacher_id): " . $stmt->error);
        }
        $stmt->close();
    } else {
        $response['message'] = "Database statement preparation failed: " . $conn->error;
        error_log("DB Prepare Error marking notifications read: " . $conn->error);
    }
} else {
    $response['message'] = "Invalid request action.";
}

$conn->close();
echo json_encode($response);
?>
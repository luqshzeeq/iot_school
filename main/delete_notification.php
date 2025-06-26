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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    // Validate CSRF token (if you want to add it for single delete)
     if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
         $response['message'] = "Invalid CSRF token. Please refresh the page and try again.";
         echo json_encode($response);
         exit();
     }

    $notification_id = filter_var($_POST['notification_id'], FILTER_VALIDATE_INT);

    if ($notification_id === false || $notification_id <= 0) {
        $response['message'] = "Invalid notification ID.";
        echo json_encode($response);
        exit();
    }

    // Delete the notification, ensuring it belongs to the logged-in teacher
    $stmt = $conn->prepare("DELETE FROM teacher_notifications WHERE id = ? AND teacher_id = ?");
    if ($stmt) {
        $stmt->bind_param("ii", $notification_id, $teacher_id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = "Notification deleted successfully.";
            } else {
                $response['message'] = "Notification not found or not owned by you.";
            }
        } else {
            $response['message'] = "Failed to delete notification: " . $stmt->error;
            error_log("DB Error deleting notification (ID: $notification_id, Teacher: $teacher_id): " . $stmt->error);
        }
        $stmt->close();
    } else {
        $response['message'] = "Database statement preparation failed: " . $conn->error;
        error_log("DB Prepare Error deleting notification: " . $conn->error);
    }
} else {
    $response['message'] = "Invalid request.";
}

$conn->close();
echo json_encode($response);
?>
<?php
session_start();
header('Content-Type: application/json'); // Ensure the response is treated as JSON

// Include your database connection file. Adjust the path if necessary.
include 'db_connection.php'; 

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

// Ensure database connection is successful
if (!$conn) {
    $response['message'] = "Database connection error. Please try again later.";
    echo json_encode($response);
    exit();
}

// Redirect if user is not logged in or is not a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    $response['message'] = "Unauthorized access. Please log in as a teacher.";
    echo json_encode($response);
    exit();
}

$teacher_id = $_SESSION['user_id']; // The ID of the teacher performing the action

// Validate CSRF token for all POST actions
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $response['message'] = "Invalid CSRF token. Please refresh the page and try again.";
    echo json_encode($response);
    exit();
}

$action = $_POST['action'] ?? '';
$setting_date = trim($_POST['setting_date'] ?? '');

// Basic date validation (important for SQL queries)
if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $setting_date)) {
    $response['message'] = "Invalid date format provided.";
    echo json_encode($response);
    exit();
}

switch ($action) {
    case 'delete':
        $stmt = $conn->prepare("DELETE FROM teacher_daily_languages WHERE setting_date = ?");
        if ($stmt) {
            $stmt->bind_param("s", $setting_date);
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = "Language setting for {$setting_date} deleted successfully.";
                } else {
                    $response['message'] = "No language setting found for {$setting_date} to delete.";
                }
            } else {
                $response['message'] = "Database error during deletion: " . $stmt->error;
                error_log("DB Error deleting daily language (date: $setting_date): " . $stmt->error);
            }
            $stmt->close();
        } else {
            $response['message'] = "Failed to prepare delete statement: " . $conn->error;
            error_log("DB Prepare Error for delete action: " . $conn->error);
        }
        break;

    // NOTE: The 'edit' action logic isn't strictly needed here IF you're re-using the main form's POST.
    // If you plan to have a separate AJAX 'update' action for edits without a page reload,
    // you would add a 'case 'update':' here.
    // For now, the existing 'set_language_action' in teacher_dashboard.php handles updates
    // when the form is submitted. The 'edit' button on the table just pre-fills that form.
    default:
        $response['message'] = "Invalid action specified.";
        break;
}

$conn->close();
echo json_encode($response);
exit();
?>
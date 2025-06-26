<?php
session_start();
header('Content-Type: application/json'); // Ensure the response is JSON

// Include database connection
include 'db_connection.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$teacher_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request_language') {
    // Validate CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $response['message'] = "Invalid CSRF token. Please try again.";
        echo json_encode($response);
        exit();
    }

    $language_name = trim($_POST['language_name'] ?? '');

    if (empty($language_name)) {
        $response['message'] = "Language name cannot be empty.";
        echo json_encode($response);
        exit();
    }

    // Check if the language already exists in the main languages table
    $stmt_check_existing = $conn->prepare("SELECT COUNT(*) FROM languages WHERE language_name = ?");
    if ($stmt_check_existing) {
        $stmt_check_existing->bind_param("s", $language_name);
        $stmt_check_existing->execute();
        $stmt_check_existing->bind_result($existing_count);
        $stmt_check_existing->fetch();
        $stmt_check_existing->close();
        if ($existing_count > 0) {
            $response['message'] = "Language '" . htmlspecialchars($language_name) . "' already exists in the system.";
            echo json_encode($response);
            exit();
        }
    } else {
        $response['message'] = "Database error during initial language check: " . $conn->error;
        echo json_encode($response);
        exit();
    }

    // Check if a pending request for this language already exists
    $stmt_check_pending = $conn->prepare("SELECT COUNT(*) FROM language_requests WHERE language_name = ? AND status = 'pending'");
    if ($stmt_check_pending) {
        $stmt_check_pending->bind_param("s", $language_name);
        $stmt_check_pending->execute();
        $stmt_check_pending->bind_result($pending_count);
        $stmt_check_pending->fetch();
        $stmt_check_pending->close();
        if ($pending_count > 0) {
            $response['message'] = "A request for '" . htmlspecialchars($language_name) . "' is already pending review.";
            echo json_encode($response);
            exit();
        }
    } else {
        $response['message'] = "Database error during pending request check: " . $conn->error;
        echo json_encode($response);
        exit();
    }


    // Insert the new language request
    $stmt_insert = $conn->prepare("INSERT INTO language_requests (language_name, requested_by) VALUES (?, ?)");
    if ($stmt_insert) {
        $stmt_insert->bind_param("si", $language_name, $teacher_id);
        if ($stmt_insert->execute()) {
            $response['success'] = true;
            $response['message'] = "Your request for '" . htmlspecialchars($language_name) . "' has been submitted for review.";
            // Optionally, log the request for debugging
            error_log("Teacher ID: $teacher_id requested new language: $language_name");
        } else {
            // Handle potential duplicate key error if UNIQUE constraint fails (though checked above, good to have)
            if ($conn->errno == 1062) { // MySQL error code for duplicate entry
                $response['message'] = "A request for '" . htmlspecialchars($language_name) . "' already exists or is being processed.";
            } else {
                $response['message'] = "Failed to submit request: " . $stmt_insert->error;
                error_log("DB Error submitting language request: " . $stmt_insert->error);
            }
        }
        $stmt_insert->close();
    } else {
        $response['message'] = "Database statement preparation failed: " . $conn->error;
        error_log("DB Prepare Error submitting language request: " . $conn->error);
    }
} else {
    $response['message'] = "Invalid request method or action.";
}

$conn->close();
echo json_encode($response);
?>
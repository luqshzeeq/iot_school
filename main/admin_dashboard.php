<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- FOR DEBUGGING: Enable all error reporting. REMOVE THIS ON PRODUCTION. ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --- END DEBUGGING SETTINGS ---

// --- 1. Admin Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Assuming ../index.php is the correct path
    exit(); // Always exit after a header redirect
}

// --- 2. DB Connection ---
include 'db_connection.php'; // Ensure this file exists

// Verify database connection
if (!$conn) {
    $_SESSION['error_message'] = "Database connection failed.";
    header("Location: admin_dashboard.php"); // Redirect to show the error
    exit();
}

// --- Messages for SweetAlert modals ---
// These are used for immediate feedback after a POST action and redirect
$modal_success_message = $_SESSION['success_message'] ?? null;
$modal_error_message = $_SESSION['error_message'] ?? null;
unset($_SESSION['success_message'], $_SESSION['error_message']); // Clear messages after retrieving

// Helper function to reconstruct URL with search query
function get_redirect_url_with_search($base_url, $query = null) {
    return $query ? $base_url . "?search_query=" . urlencode($query) : $base_url;
}

// --- Search query for 'Manage Languages' section ---
$search_query = trim($_GET['search_query'] ?? '');


// --- START: Main POST Request Handling Block ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Determine the base redirect URL and anchor for manage languages
    $base_redirect_url_manage = get_redirect_url_with_search("admin_dashboard.php", $_POST['search_query'] ?? '') . '#manage-languages';
    // Determine the base redirect URL and anchor for language requests
    $base_redirect_url_requests = get_redirect_url_with_search("admin_dashboard.php", $_POST['search_query'] ?? '') . '#language-requests-anchor'; // Anchor to the new position

    $action_status_type = '';
    $action_status_message = '';
    $redirect_url_after_action = ''; // Will store the final redirect URL

    // --- Existing Language Management (Add/Update/Delete) ---
    if (isset($_POST['add_language'])) {
        $new_language_name = trim($_POST['language_name'] ?? '');
        if (!empty($new_language_name)) {
            $stmt = $conn->prepare("INSERT INTO languages (language_name, created_by) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("si", $new_language_name, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $action_status_type = 'success';
                    $action_status_message = "Language '" . htmlspecialchars($new_language_name) . "' added successfully.";
                } else {
                    $action_status_type = 'error';
                    $action_status_message = "Error adding language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $action_status_type = 'error';
                $action_status_message = "Error preparing add language statement: " . $conn->error;
            }
        } else {
            $action_status_type = 'error';
            $action_status_message = "Language name cannot be empty.";
        }
        $redirect_url_after_action = $base_redirect_url_manage; // Still redirect to manage languages after this action
    }
    if (isset($_POST['delete_language'])) {
        $language_id = filter_var($_POST['language_id_to_delete'], FILTER_VALIDATE_INT);
        if ($language_id !== false) {
            $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM teacher_daily_languages WHERE language_id = ?");
            $stmt_check->bind_param("i", $language_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($result_check['count'] > 0) {
                   $action_status_type = 'error';
                   $action_status_message = "Cannot delete this language because it is currently in use by a teacher's daily settings.";
            } else {
                $stmt = $conn->prepare("DELETE FROM languages WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $language_id);
                    if ($stmt->execute()) {
                        $action_status_type = 'success';
                        $action_status_message = ($stmt->affected_rows > 0) ? "Language deleted successfully." : "Language not found or already deleted.";
                    } else {
                        $action_status_type = 'error';
                        $action_status_message = "Error deleting language: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $action_status_type = 'error';
                    $action_status_message = "Error preparing delete language statement: " . $conn->error;
                }
            }
        } else {
            $action_status_type = 'error';
            $action_status_message = "Invalid language ID for deletion.";
        }
        $redirect_url_after_action = $base_redirect_url_manage;
    }
    if (isset($_POST['update_language'])) {
        $language_id = filter_var($_POST['language_id_to_update'], FILTER_VALIDATE_INT);
        $updated_name = trim($_POST['language_name'] ?? '');
        if ($language_id !== false && !empty($updated_name)) {
            $stmt = $conn->prepare("UPDATE languages SET language_name = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $updated_name, $language_id);
                if ($stmt->execute()) {
                    $action_status_type = 'success';
                    $action_status_message = ($stmt->affected_rows > 0) ? "Language updated successfully." : "No changes were made to the language.";
                } else {
                    $action_status_type = 'error';
                    $action_status_message = "Error updating language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $action_status_type = 'error';
                $action_status_message = "Error preparing update language statement: " . $conn->error;
            }
        } else {
            $action_status_type = 'error';
            $action_status_message = "Invalid data for update.";
        }
        $redirect_url_after_action = $base_redirect_url_manage;
    }

    // --- Handle Language Request Approval/Rejection ---
    if (isset($_POST['approve_request']) || isset($_POST['reject_request'])) {
        $request_id = filter_var($_POST['request_id'], FILTER_VALIDATE_INT);
        $admin_id = $_SESSION['user_id'];

        if ($request_id !== false) {
            // First, get the language_name and requested_by (teacher_id) from the request
            $stmt_get_request_details = $conn->prepare("SELECT language_name, requested_by FROM language_requests WHERE id = ? AND status = 'pending'");
            $stmt_get_request_details->bind_param("i", $request_id);
            $stmt_get_request_details->execute();
            $result_get_request_details = $stmt_get_request_details->get_result();
            $request_data = $result_get_request_details->fetch_assoc();
            $stmt_get_request_details->close();

            if ($request_data) {
                $language_name_requested = $request_data['language_name'];
                $requested_by_teacher_id = $request_data['requested_by']; // Get the teacher's ID
                $action_type = isset($_POST['approve_request']) ? 'approved' : 'rejected';

                $conn->begin_transaction(); // Start transaction

                try {
                    if ($action_type === 'approved') {
                        $stmt_insert_lang = $conn->prepare("INSERT INTO languages (language_name, created_by) VALUES (?, ?)");
                        if (!$stmt_insert_lang) { throw new Exception("Error preparing insert language statement: " . $conn->error); }
                        $stmt_insert_lang->bind_param("si", $language_name_requested, $admin_id);
                        if (!$stmt_insert_lang->execute()) {
                            if ($conn->errno == 1062) { // MySQL error code for duplicate entry
                                throw new Exception("Language '" . htmlspecialchars($language_name_requested) . "' already exists.");
                            }
                            throw new Exception("Error adding language to master list: " . $stmt_insert_lang->error);
                        }
                        $stmt_insert_lang->close();
                    }

                    $stmt_update_request = $conn->prepare("UPDATE language_requests SET status = ?, approved_by = ?, approved_at = NOW() WHERE id = ?");
                    if (!$stmt_update_request) { throw new Exception("Error preparing update request statement: " . $conn->error); }
                    $stmt_update_request->bind_param("sii", $action_type, $admin_id, $request_id);
                    if (!$stmt_update_request->execute()) { throw new Exception("Error updating language request status: " . $stmt_update_request->error); }
                    $stmt_update_request->close();

                    // --- NEW: Insert notification for the teacher ---
                    $notification_type = ($action_type === 'approved') ? 'language_approved' : 'language_rejected';
                    $notification_message = ($action_type === 'approved')
                        ? "Your requested language '" . htmlspecialchars($language_name_requested) . "' has been approved and added to the system!"
                        : "Your requested language '" . htmlspecialchars($language_name_requested) . "' has been rejected.";

                    if ($requested_by_teacher_id) { // Only insert if we found the requester's ID
                        $stmt_insert_notification = $conn->prepare("INSERT INTO teacher_notifications (teacher_id, type, message, related_id) VALUES (?, ?, ?, ?)");
                        if (!$stmt_insert_notification) { throw new Exception("Error preparing notification insert statement: " . $conn->error); }
                        $stmt_insert_notification->bind_param("issi", $requested_by_teacher_id, $notification_type, $notification_message, $request_id);
                        if (!$stmt_insert_notification->execute()) { throw new Exception("Error inserting teacher notification: " . $stmt_insert_notification->error); }
                        $stmt_insert_notification->close();
                    }
                    // --- END NEW: Insert notification ---


                    $conn->commit(); // Commit transaction

                    $action_status_type = 'success';
                    $action_status_message = "Language request for '" . htmlspecialchars($language_name_requested) . "' " . $action_type . " successfully.";

                } catch (Exception $e) {
                    $conn->rollback(); // Rollback on error
                    $action_status_type = 'error';
                    $action_status_message = "Action failed: " . $e->getMessage();
                }
            } else {
                $action_status_type = 'error';
                $action_status_message = "Language request not found or already processed.";
            }
        } else {
            $action_status_type = 'error';
            $action_status_message = "Invalid request ID.";
        }
        $redirect_url_after_action = $base_redirect_url_requests; // Redirect to language requests section
    }

    // --- Store Messages and Redirect ---
    if ($action_status_type === 'success') {
        $_SESSION['success_message'] = $action_status_message;
    } elseif ($action_status_type === 'error') {
        $_SESSION['error_message'] = $action_status_message;
    }

    // Perform the redirect
    header("Location: " . $redirect_url_after_action);
    exit();
}
// --- END: Main POST Request Handling Block ---


// --- Existing Logic: Fetch language for editing (if an edit link was clicked) ---
$language_to_edit_id = null;
$language_to_edit_name = '';
if (isset($_GET['edit_language'])) {
    $language_to_edit_id = filter_var($_GET['edit_language'], FILTER_VALIDATE_INT);
    if ($language_to_edit_id !== false) {
        $stmt = $conn->prepare("SELECT language_name FROM languages WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $language_to_edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $language_to_edit_name = $row['language_name'];
            } else {
                $_SESSION['error_message'] = "Language not found for editing.";
                header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query) . '#manage-languages');
                exit();
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing fetch language for edit statement: " . $conn->error;
            header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query) . '#manage-languages');
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Invalid ID for editing.";
        header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query) . '#manage-languages');
        exit();
    }
}

// --- Existing Logic: Fetch all languages with optional search filter (Manage Languages) ---
$sql_all_languages = "SELECT id, language_name FROM languages";
if ($search_query !== '') { $sql_all_languages .= " WHERE language_name LIKE ?"; }
$sql_all_languages .= " ORDER BY id ASC";
$stmt_all_languages = $conn->prepare($sql_all_languages);
$result_all_languages = false; // Initialize
if ($stmt_all_languages) {
    if ($search_query !== '') {
        $search_param = "%" . $search_query . "%";
        $stmt_all_languages->bind_param("s", $search_param);
    }
    if ($stmt_all_languages->execute()) {
        $result_all_languages = $stmt_all_languages->get_result();
    } else {
        $modal_error_message = "Error executing fetch all languages statement: " . $stmt_all_languages->error;
        $result_all_languages = false;
    }
    $stmt_all_languages->close();
} else {
    $modal_error_message = "Error preparing fetch all languages statement: " . $conn->error;
    $result_all_languages = false;
}

// --- Fetch Language Requests for display (now always fetched, as it's part of the main view) ---
$pending_requests = [];
$processed_requests = [];

// Fetch pending requests
$stmt_pending = $conn->prepare("SELECT lr.id, lr.language_name, u.username AS requested_by_username, lr.requested_at
                                FROM language_requests lr
                                JOIN users u ON lr.requested_by = u.id
                                WHERE lr.status = 'pending'
                                ORDER BY lr.requested_at ASC");
if ($stmt_pending) {
    $stmt_pending->execute();
    $pending_requests = $stmt_pending->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_pending->close();
}

// Fetch recently processed requests (approved/rejected)
$stmt_processed = $conn->prepare("SELECT lr.id, lr.language_name, u_req.username AS requested_by_username, lr.requested_at, lr.status, u_app.username AS approved_by_username, lr.approved_at
                                  FROM language_requests lr
                                  JOIN users u_req ON lr.requested_by = u_req.id
                                  LEFT JOIN users u_app ON lr.approved_by = u_app.id
                                  WHERE lr.status != 'pending'
                                  ORDER BY lr.approved_at DESC LIMIT 10");
if ($stmt_processed) {
    $stmt_processed->execute();
    $processed_requests = $stmt_processed->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_processed->close();
}


// --- Existing Logic: Date Calculation (Last 7 Days for Dashboard Charts/Tables) ---
// These variables are no longer used for display on this page, but kept here
// in case other parts of the admin panel still rely on these calculations.
$today_timestamp = time();
$end_date_timestamp = $today_timestamp;
$start_date_timestamp = strtotime('-6 days', $today_timestamp);
$start_date_str = date('Y-m-d 00:00:00', $start_date_timestamp);
$end_date_str = date('Y-m-d 23:59:59', $end_date_timestamp);
$start_date_query_format = date('Y-m-d', $start_date_timestamp);
$end_date_query_format = date('Y-m-d', $end_date_timestamp);

$week_labels = [];
$week_user_data = [];
$week_lang_data = [];

$current_ts = $start_date_timestamp;
while ($current_ts <= $end_date_timestamp) {
    $date_key = date('Y-m-d', $current_ts);
    $week_labels[] = date('D (d M)', $current_ts);
    $week_user_data[$date_key] = 0;
    $week_lang_data[$date_key] = 0;
    $current_ts = strtotime('+1 day', $current_ts);
}

// --- Existing Logic: Fetch All Data (Dashboard Stats) ---
// These variables are no longer used for display on this page, but kept here
// in case other parts of the admin panel still rely on these calculations.
$stats = [
    'total_users' => 0, 'active_users' => 0, 'inactive_users' => 0,
    'total_languages' => 0,
    'total_devices' => 0, 'online_devices' => 0, 'offline_devices' => 0, 'error_devices' => 0,
];
$user_result = $conn->query("SELECT status, COUNT(*) as count FROM users WHERE role = 'teacher' GROUP BY status");
if ($user_result) { while($row = $user_result->fetch_assoc()) { if ($row['status'] === 'active') $stats['active_users'] = $row['count']; else $stats['inactive_users'] = $row['count']; $stats['total_users'] += $row['count']; } }
$lang_result = $conn->query("SELECT COUNT(*) as count FROM languages");
if ($lang_result) { $stats['total_languages'] = $lang_result->fetch_assoc()['count']; }
$device_result = $conn->query("SELECT status, COUNT(*) as count FROM device_status GROUP BY status");
if ($device_result) { while($row = $device_result->fetch_assoc()) { $status_key = strtolower($row['status']) . '_devices'; if (array_key_exists($status_key, $stats)) $stats[$status_key] = $row['count']; $stats['total_devices'] += $row['count']; } }

// --- Existing Logic: Fetch new users chart data / new languages chart data / password resets ---
// These are commented out as they were for the dashboard overview and daily settings tables
// that are no longer present on this specific page.
/*
$stmt_users_chart = $conn->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count FROM users WHERE role = 'teacher' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at)");
if ($stmt_users_chart) { $stmt_users_chart->bind_param("ss", $start_date_query_format, $end_date_query_format); if ($stmt_users_chart->execute()) { $result = $stmt_users_chart->get_result(); if ($result) { while($row = $result->fetch_assoc()) { if (array_key_exists($row['reg_date'], $week_user_data)) $week_user_data[$row['reg_date']] = $row['count']; } } } $stmt_users_chart->close(); }

$languages_by_teacher_week = [];
$stmt_langs_chart = $conn->prepare("SELECT l.language_name, l.created_at, u.username FROM languages l LEFT JOIN users u ON l.created_by = u.id WHERE l.created_at BETWEEN ? AND ? ORDER BY l.created_at DESC");
if ($stmt_langs_chart) { $stmt_langs_chart->bind_param("ss", $start_date_str, $end_date_str); if ($stmt_langs_chart->execute()) { $result = $stmt_langs_chart->get_result(); if ($result) { while($row = $result->fetch_assoc()) { $languages_by_teacher_week[] = $row; $date_key = date('Y-m-d', strtotime($row['created_at'])); if (array_key_exists($date_key, $week_lang_data)) { $week_lang_data[$date_key]++; } } } } $stmt_langs_chart->close(); }

$recent_password_resets = [];
$stmt_recent_resets = $conn->prepare("SELECT pr.expires_at, u.username, pr.email FROM password_resets pr JOIN users u ON pr.email = u.email WHERE pr.expires_at BETWEEN ? AND ? ORDER BY pr.expires_at DESC LIMIT 10");
if ($stmt_recent_resets) { $stmt_recent_resets->bind_param("ss", $start_date_str, $end_date_str); if ($stmt_recent_resets->execute()) { $result = $stmt_recent_resets->get_result(); if ($result) { while($row = $result->fetch_assoc()) $recent_password_resets[] = $row; } } $stmt_recent_resets->close(); }
*/

// --- Existing Logic: Prepare Data for JS Charts (These variables are now unused) ---
/*
$user_pie_data = ['labels' => ['Active', 'Inactive'], 'data' => [$stats['active_users'], $stats['inactive_users']]];
$device_pie_data = ['labels' => ['Online', 'Offline', 'Error'], 'data' => [$stats['online_devices'], $stats['offline_devices'], $stats['error_devices']]];
$user_bar_data = ['labels' => $week_labels, 'data' => array_values($week_user_data)];
$lang_bar_data = ['labels' => $week_labels, 'data' => array_values($week_lang_data)];
*/

// --- Include the Standardized Header ---
include 'header.php';
?>
<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
<script type="text/javascript" src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* Specific styles for language request status badges */
    .status-badge {
        padding: 0.25rem 0.75rem;
        border-radius: 9999px; /* Tailwind: rounded-full */
        font-size: 0.75rem; /* Tailwind: text-xs */
        font-weight: 600; /* Tailwind: font-semibold */
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .status-badge.pending {
        background-color: #fefcbf; /* yellow-100 */
        color: #92400e; /* yellow-800 */
    }
    .status-badge.approved {
        background-color: #d1fae5; /* green-100 */
        color: #065f46; /* green-800 */
    }
    .status-badge.rejected {
        background-color: #fee2e2; /* red-100 */
        color: #991b1b; /* red-800 */
    }

    /* --- DataTables Custom Styling --- */
    /* Remove default DataTables search label */
    .dataTables_wrapper .dataTables_filter label {
        display: none !important;
    }

    /* Style DataTables search input */
    .dataTables_wrapper .dataTables_filter {
        position: relative; /* For positioning the icon */
        text-align: right; /* Align search to the right */
        margin-bottom: 1rem;
    }
    .dataTables_wrapper .dataTables_filter input {
        border: 1px solid #d1d5db; /* gray-300 */
        border-radius: 0.375rem; /* rounded-md */
        padding: 0.5rem 1rem;
        padding-left: 2.5rem; /* Space for icon */
        font-size: 0.875rem; /* text-sm */
        line-height: 1.25rem; /* leading-5 */
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* shadow-sm */
        width: 16rem; /* Tailwind w-64 */
        transition: all 0.15s ease-in-out;
    }
    .dataTables_wrapper .dataTables_filter input:focus {
        outline: none;
        border-color: #4f46e5; /* indigo-600 */
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.5); /* ring-indigo-500/50 */
    }

    /* Position DataTables search icon */
    .dataTables_wrapper .dataTables_filter .fas.fa-search {
        position: absolute;
        left: auto; /* Override default */
        right: 14.5rem; /* Adjust to be inside the input, accounting for width */
        top: 50%;
        transform: translateY(-50%);
        color: #9ca3af; /* gray-400 */
        pointer-events: none;
        font-size: 1rem; /* text-base */
        z-index: 1;
    }

    /* Pagination styling */
    .dataTables_wrapper .dataTables_paginate {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        margin-top: 1.5rem;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button {
        display: flex; align-items: center; justify-content: center;
        min-width: 2.5rem; height: 2.5rem;
        padding: 0 0.75rem; margin: 0 0.25rem;
        border-radius: 0.5rem; border: 1px solid #cbd5e0;
        background-color: #ffffff; color: #4a5568;
        cursor: pointer; transition: all 0.2s ease-in-out;
        font-weight: 500; box-shadow: none;
        text-decoration: none; /* Ensure no underline */
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current {
        background-color: #2563eb; border-color: #2563eb; color: #ffffff;
        font-weight: 700; box-shadow: 0 2px 5px rgba(37, 99, 235, 0.2);
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.current:hover {
        background-color: #1d4ed8; border-color: #1d4ed8;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button.disabled {
        color: #a0aec0;
        cursor: not-allowed;
        background-color: #f0f4f8; border-color: #e2e8f0;
        box-shadow: none;
    }

    .dataTables_wrapper .dataTables_paginate .paginate_button:not(.current):not(.disabled):hover {
        background-color: #f7fafc;
        border-color: #a0aec0;
        color: #2d3748;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    /* Ensure info text and pagination align well within their common wrapper */
    .dataTables_info {
        flex-grow: 1; text-align: left; color: #718096; font-size: 0.875rem;
    }

    /* Combine length and filter controls on one row */
    .dataTables_length, .dataTables_filter {
        display: flex;
        align-items: center;
        /* margin-bottom handled by wrapper */
    }
    .dataTables_length select {
        margin-left: 0.5rem;
        margin-right: 0.5rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        padding: 0.3rem 0.8rem;
        font-size: 0.875rem;
        line-height: 1.25rem;
    }
    /* Overall wrapper for DataTables top and bottom controls */
    .dataTables_wrapper .dataTables_length + .dataTables_filter {
        margin-left: auto; /* Pushes filter to the right */
    }
    .dataTables_wrapper .dataTables_info + .dataTables_paginate {
        margin-left: auto;
    }

    .dataTables_wrapper .top-controls,
    .dataTables_wrapper .bottom-controls {
        display: flex;
        justify-content: space-between;
        align-items: flex-end; /* Align search input nicely */
        margin-bottom: 1rem; /* Gap between top controls and table */
    }
    .dataTables_wrapper .bottom-controls {
        align-items: center; /* Align info text and pagination */
        margin-top: 1rem; /* Gap between table and bottom controls */
    }
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto">
        <?php if ($modal_success_message): ?>
            <div id="successMessage" class="flash-message flash-success flex items-center">
                <i class="fas fa-check-circle fa-lg mr-3 py-1"></i>
                <div><p class="font-bold">Success</p><p class="text-sm"><?php echo htmlspecialchars($modal_success_message); ?></p></div>
            </div>
        <?php endif; ?>
        <?php if ($modal_error_message): ?>
            <div id="errorMessage" class="flash-message flash-error flex items-center">
                <i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i>
                <div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($modal_error_message); ?></p></div>
            </div>
        <?php endif; ?>

        <section id="language-requests-anchor" class="mb-12">
            <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Pending Language Requests</h2>
                <?php if (!empty($pending_requests)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested At</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($pending_requests as $request): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $request['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($request['language_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($request['requested_by_username']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($request['requested_at']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <button type="button" class="text-green-600 hover:text-green-900 approve-request-button"
                                                data-request-id="<?php echo $request['id']; ?>"
                                                data-language-name="<?php echo htmlspecialchars($request['language_name']); ?>">
                                                <i class="fas fa-check-circle mr-1"></i>Approve
                                            </button>
                                            <button type="button" class="text-red-600 hover:text-red-900 reject-request-button"
                                                data-request-id="<?php echo $request['id']; ?>"
                                                data-language-name="<?php echo htmlspecialchars($request['language_name']); ?>">
                                                <i class="fas fa-times-circle mr-1"></i>Reject
                                            </button>
                                            <form id="approveForm-<?php echo $request['id']; ?>" action="admin_dashboard.php#language-requests-anchor" method="POST" style="display: none;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="approve_request" value="1">
                                                <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                                            </form>
                                            <form id="rejectForm-<?php echo $request['id']; ?>" action="admin_dashboard.php#language-requests-anchor" method="POST" style="display: none;">
                                                <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                <input type="hidden" name="reject_request" value="1">
                                                <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-check-double fa-3x text-gray-400 mb-3"></i>
                        <p>No pending language requests at this time.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg mb-12">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Recently Processed Requests</h2>
                <?php if (!empty($processed_requests)): ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Requested By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processed By</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processed At</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($processed_requests as $request): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $request['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($request['language_name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($request['requested_by_username']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="status-badge <?php echo htmlspecialchars($request['status']); ?>">
                                                <?php echo ucfirst($request['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($request['approved_by_username'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($request['approved_at']))); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="fas fa-history fa-3x text-gray-400 mb-3"></i>
                        <p>No recently processed language requests.</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>




        <section id="manage-languages" class="mb-12">
            <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4"><?php echo $language_to_edit_id ? 'Edit Language' : 'Add New Language'; ?></h2>
                <form action="admin_dashboard.php#manage-languages" method="POST" class="space-y-4">
                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php if ($language_to_edit_id): ?><input type="hidden" name="language_id_to_update" value="<?php echo htmlspecialchars($language_to_edit_id); ?>"><?php endif; ?>
                    <div>
                        <label for="language_name_input" class="block text-sm font-medium text-gray-700">Language Name:</label>
                        <input type="text" id="language_name_input" name="language_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., English, Spanish, Malay" value="<?php echo htmlspecialchars($language_to_edit_name); ?>" required />
                    </div>
                    <div class="flex items-center">
                        <?php if ($language_to_edit_id): ?>
                            <button type="submit" name="update_language" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md"><i class="fas fa-save"></i>Update</button>
                            <a href="admin_dashboard.php?search_query=<?php echo urlencode($search_query); ?>#manage-languages" class="ml-3 border border-gray-300 py-2 px-4 rounded-md bg-white hover:bg-gray-50">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_language" class="btn-icon bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md"><i class="fas fa-plus-circle"></i>Add</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">Existing Languages</h2>
                    <form action="admin_dashboard.php#manage-languages" method="GET" class="flex items-center space-x-2 w-full md:w-1/3">
                        <div class="relative flex-grow">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                            <input type="text" name="search_query" placeholder="Type language name..." class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md">Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="admin_dashboard.php#manage-languages" class="text-gray-600 bg-gray-200 hover:bg-gray-300 py-2 px-4 rounded-md text-sm whitespace-nowrap">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($result_all_languages && $result_all_languages->num_rows > 0): while ($row = $result_all_languages->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $row['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($row['language_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-4">
                                        <a href="admin_dashboard.php?edit_language=<?php echo $row['id']; ?>&search_query=<?php echo urlencode($search_query); ?>#manage-languages" class="text-indigo-600 hover:text-indigo-900"><i class="fas fa-edit mr-1"></i>Edit</a>

                                        <button type="button" class="text-red-600 hover:text-red-900 delete-language-button" data-language-id="<?php echo $row['id']; ?>" data-language-name="<?php echo htmlspecialchars($row['language_name']); ?>">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete
                                        </button>
                                        <form id="deleteLanguageForm-<?php echo $row['id']; ?>" action="admin_dashboard.php#manage-languages" method="POST" style="display: none;">
                                            <input type="hidden" name="language_id_to_delete" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                                            <input type="hidden" name="delete_language" value="1">
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?><tr><td colspan="3" class="text-center py-8 text-gray-500">No languages found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // Existing sidebar toggle logic
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebarTexts = sidebar ? sidebar.querySelectorAll('.sidebar-text') : [];

    function toggleSidebarDesktop() {
        if (!sidebar || !sidebarToggle) return;
        const toggleIcon = sidebarToggle.querySelector('i');
        sidebar.classList.toggle('w-64');
        sidebar.classList.toggle('w-20');
        const isCollapsed = sidebar.classList.contains('w-20');
        sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
        if (toggleIcon) {
            toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
            toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
            toggleIcon.title = isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar';
        }
    }

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebarDesktop);
        const isCollapsed = sidebar.classList.contains('w-20');
        const toggleIcon = sidebarToggle.querySelector('i');
        if (toggleIcon) {
            toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
            toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
            toggleIcon.title = isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar';
        }
    }

    if (mobileSidebarToggle && sidebar) {
        sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
        mobileSidebarToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0');
            sidebar.classList.add('w-64');
            sidebar.classList.remove('w-20');
            sidebarTexts.forEach(text => text.classList.remove('hidden'));
        });
        document.addEventListener('click', (e) => {
            if (sidebar && !sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target) && sidebar.classList.contains('translate-x-0') && window.innerWidth < 1024) {
                sidebar.classList.add('-translate-x-full');
                sidebar.classList.remove('translate-x-0');
            }
        });
    }

    // Logic to display success/error messages from PHP after a POST request
    const phpSuccessMessage = <?php echo json_encode($modal_success_message); ?>;
    const phpErrorMessage = <?php echo json_encode($modal_error_message); ?>;

    if (phpSuccessMessage) {
        Swal.fire({
            title: 'Success!',
            text: phpSuccessMessage,
            icon: 'success',
            confirmButtonText: 'OK'
        });
    }

    if (phpErrorMessage) {
        Swal.fire({
            title: 'Error!',
            text: phpErrorMessage,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    }


    // --- Existing Language Delete Button Logic ---
    const deleteButtons = document.querySelectorAll('.delete-language-button');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const languageId = this.dataset.languageId;
            const languageName = this.dataset.languageName;
            const deleteForm = document.getElementById(`deleteLanguageForm-${languageId}`);

            if (deleteForm) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete "${languageName}". This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteForm.submit();
                    }
                });
            }
        });
    });

    // --- NEW: Approve/Reject Language Request Buttons ---
    document.querySelectorAll('.approve-request-button').forEach(button => {
        button.addEventListener('click', function() {
            const requestId = this.dataset.requestId;
            const languageName = this.dataset.languageName;
            const approveForm = document.getElementById(`approveForm-${requestId}`);

            Swal.fire({
                title: 'Approve Language Request?',
                text: `You are about to approve the request for "${languageName}". This will add it to the system.`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745', // Green for approve
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Approve!'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (approveForm) {
                        approveForm.submit();
                    }
                }
            });
        });
    });

    document.querySelectorAll('.reject-request-button').forEach(button => {
        button.addEventListener('click', function() {
            const requestId = this.dataset.requestId;
            const languageName = this.dataset.languageName;
            const rejectForm = document.getElementById(`rejectForm-${requestId}`);

            Swal.fire({
                title: 'Reject Language Request?',
                text: `You are about to reject the request for "${languageName}".`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545', // Red for reject
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Reject!'
            }).then((result) => {
                if (result.isConfirmed) {
                    if (rejectForm) {
                        rejectForm.submit();
                    }
                }
            });
        });
    });

    // --- Initialize DataTables for Daily Language Settings Table ---
    // Removed the initialization code for dailyLanguageSettingsTable
    // as the table itself has been removed from the HTML.
});
</script>

</body>
</html>
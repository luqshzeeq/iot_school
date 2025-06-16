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

// --- 3. Full PHP Logic for Managing Languages ---
$search_query = trim($_GET['search_query'] ?? '');
// $error_message = $_SESSION['error_message'] ?? null; // Flash messages will be handled differently for success
// $success_message = $_SESSION['success_message'] ?? null; // No longer retrieving success messages via session
unset($_SESSION['error_message'], $_SESSION['success_message']); // Clear existing messages just in case

$language_to_edit_id = null;
$language_to_edit_name = '';

// Added these variables for modal messages
$modal_success_message = null;
$modal_error_message = null;

function get_redirect_url_with_search($base_url, $query) {
    return $query ? $base_url . "?search_query=" . urlencode($query) : $base_url;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect_url = get_redirect_url_with_search("admin_dashboard.php", $_POST['search_query'] ?? '');
    
    // Use an array to store messages for direct display after setting
    // This allows PHP to set the message which JS then picks up.
    $action_status_type = '';
    $action_status_message = '';

    if (isset($_POST['add_language'])) {
        $new_language_name = trim($_POST['language_name'] ?? '');
        if (!empty($new_language_name)) {
            $stmt = $conn->prepare("INSERT INTO languages (language_name, created_by) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("si", $new_language_name, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    // Set variables for JS to pick up
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
    }
    if (isset($_POST['delete_language'])) {
        $language_id = filter_var($_POST['language_id_to_delete'], FILTER_VALIDATE_INT);
        if ($language_id !== false) {
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
        } else {
            $action_status_type = 'error';
            $action_status_message = "Invalid language ID for deletion.";
        }
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
    }

    // Instead of redirecting with session message, set them for client-side JavaScript
    if ($action_status_type === 'success') {
        $modal_success_message = $action_status_message;
    } elseif ($action_status_type === 'error') {
        $modal_error_message = $action_status_message;
    }
    // We do NOT redirect here after POST if we want to show a modal immediately.
    // The page will naturally reload GET parameters, or stay on same page.
    // If you need a full page reload (e.g. for updated data), you might still redirect.
    // For now, let's assume we want to show the modal on the current page immediately.
    // header("Location: " . $redirect_url); // Removed this redirect for immediate modal display
    // exit(); // Removed this exit for immediate modal display
}

// Fetch language for editing if 'edit_language' GET parameter is present.
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
                $_SESSION['error_message'] = "Language not found for editing."; // Still use session for errors on redirection
                header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
                exit();
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing fetch language for edit statement: " . $conn->error;
            header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Invalid ID for editing.";
        header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
        exit();
    }
}

// Fetch all languages with optional search filter.
$sql = "SELECT id, language_name FROM languages";
if ($search_query !== '') { $sql .= " WHERE language_name LIKE ?"; }
$sql .= " ORDER BY id ASC";
$stmt_all = $conn->prepare($sql);
if ($stmt_all) {
    if ($search_query !== '') {
        $search_param = "%" . $search_query . "%";
        $stmt_all->bind_param("s", $search_param);
    }
    if ($stmt_all->execute()) {
        $result_all_languages = $stmt_all->get_result();
    } else {
        $modal_error_message = "Error executing fetch all languages statement: " . $stmt_all->error; // Use modal error if possible
        $result_all_languages = false;
    }
    $stmt_all->close();
} else {
    $modal_error_message = "Error preparing fetch all languages statement: " . $conn->error; // Use modal error if possible
    $result_all_languages = false;
}

// --- 3. Date Calculation (Last 7 Days) ---
$today_timestamp = time();
$end_date_timestamp = $today_timestamp;
$start_date_timestamp = strtotime('-6 days', $today_timestamp);
$start_date_str = date('Y-m-d 00:00:00', $start_date_timestamp);
$end_date_str = date('Y-m-d 23:59:59', $end_date_timestamp);
$start_date_query_format = date('Y-m-d', $start_date_timestamp);
$end_date_query_format = date('Y-m-d', $end_date_timestamp);

// Initialize arrays for chart data
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

// --- 4. Fetch All Data (Dashboard Stats) ---
$stats = [
    'total_users' => 0, 'active_users' => 0, 'inactive_users' => 0,
    'total_languages' => 0,
    'total_devices' => 0, 'online_devices' => 0, 'offline_devices' => 0, 'error_devices' => 0,
];
// Fetch user status
$user_result = $conn->query("SELECT status, COUNT(*) as count FROM users WHERE role = 'teacher' GROUP BY status");
if ($user_result) { while($row = $user_result->fetch_assoc()) { if ($row['status'] === 'active') $stats['active_users'] = $row['count']; else $stats['inactive_users'] = $row['count']; $stats['total_users'] += $row['count']; } }
// Fetch language count
$lang_result = $conn->query("SELECT COUNT(*) as count FROM languages");
if ($lang_result) { $stats['total_languages'] = $lang_result->fetch_assoc()['count']; }
// Fetch device status
$device_result = $conn->query("SELECT status, COUNT(*) as count FROM device_status GROUP BY status");
if ($device_result) { while($row = $device_result->fetch_assoc()) { $status_key = strtolower($row['status']) . '_devices'; if (array_key_exists($status_key, $stats)) $stats[$status_key] = $row['count']; $stats['total_devices'] += $row['count']; } }

// Fetch new users chart data
$stmt_users_chart = $conn->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count FROM users WHERE role = 'teacher' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at)");
if ($stmt_users_chart) { $stmt_users_chart->bind_param("ss", $start_date_query_format, $end_date_query_format); if ($stmt_users_chart->execute()) { $result = $stmt_users_chart->get_result(); if ($result) { while($row = $result->fetch_assoc()) { if (array_key_exists($row['reg_date'], $week_user_data)) $week_user_data[$row['reg_date']] = $row['count']; } } } $stmt_users_chart->close(); }

// Fetch new languages chart data & table
$languages_by_teacher_week = [];
$stmt_langs_chart = $conn->prepare("SELECT l.language_name, l.created_at, u.username FROM languages l LEFT JOIN users u ON l.created_by = u.id WHERE l.created_at BETWEEN ? AND ? ORDER BY l.created_at DESC");
if ($stmt_langs_chart) { $stmt_langs_chart->bind_param("ss", $start_date_str, $end_date_str); if ($stmt_langs_chart->execute()) { $result = $stmt_langs_chart->get_result(); if ($result) { while($row = $result->fetch_assoc()) { $languages_by_teacher_week[] = $row; $date_key = date('Y-m-d', strtotime($row['created_at'])); if (array_key_exists($date_key, $week_lang_data)) { $week_lang_data[$date_key]++; } } } } $stmt_langs_chart->close(); }

// Fetch recent password reset requests for the table
$recent_password_resets = [];
$stmt_recent_resets = $conn->prepare("SELECT pr.expires_at, u.username, pr.email FROM password_resets pr JOIN users u ON pr.email = u.email WHERE pr.expires_at BETWEEN ? AND ? ORDER BY pr.expires_at DESC LIMIT 10");
if ($stmt_recent_resets) { $stmt_recent_resets->bind_param("ss", $start_date_str, $end_date_str); if ($stmt_recent_resets->execute()) { $result = $stmt_recent_resets->get_result(); if ($result) { while($row = $result->fetch_assoc()) $recent_password_resets[] = $row; } } $stmt_recent_resets->close(); }

// Fetch daily language settings table
$teacher_daily_lang_settings = [];
$sql_daily_langs = "SELECT tdl.setting_date, u.username AS teacher_username, l.language_name FROM teacher_daily_languages tdl JOIN users u ON tdl.teacher_id = u.id JOIN languages l ON tdl.language_id = l.id WHERE tdl.setting_date BETWEEN ? AND ? ORDER BY tdl.setting_date DESC, u.username ASC";
$stmt_daily_langs = $conn->prepare($sql_daily_langs);
if ($stmt_daily_langs) { $stmt_daily_langs->bind_param("ss", $start_date_query_format, $end_date_query_format); if ($stmt_daily_langs->execute()) { $result = $stmt_daily_langs->get_result(); if ($result) { while ($row = $result->fetch_assoc()) $teacher_daily_lang_settings[] = $row; } } $stmt_daily_langs->close(); }

// --- 5. Prepare Data for JS Charts ---
$user_pie_data = ['labels' => ['Active', 'Inactive'], 'data' => [$stats['active_users'], $stats['inactive_users']]];
$device_pie_data = ['labels' => ['Online', 'Offline', 'Error'], 'data' => [$stats['online_devices'], $stats['offline_devices'], $stats['error_devices']]];
$user_bar_data = ['labels' => $week_labels, 'data' => array_values($week_user_data)];
$lang_bar_data = ['labels' => $week_labels, 'data' => array_values($week_lang_data)];


// --- Include the Standardized Header ---
// This file will now provide the <DOCTYPE html>, <html>, <head>, <body>, and the initial layout divs.
include 'header.php';
?>

<!--
    No <head>, <body>, or outer <div>s needed here as they come from header.php.
    Only page-specific styles or additional head elements should go here if header.php doesn't include them.
    The <title> tag should ideally be set directly in header.php or via JavaScript on DOMContentLoaded
    if header.php provides a common title template. For now, it's handled by JS below.
-->

<!-- Page-specific styles -->
<style>
    /*
     * Styles below are kept here for demonstration purposes,
     * but ideally, all shared styles (like scrollbar, sidebar transitions, flash messages, and common input styles)
     * should be in a separate CSS file or directly within header.php's <style> block
     * to avoid duplication and ensure consistency across all pages that include header.php.
     */
    .btn-icon { display: inline-flex; align-items: center; justify-content: center; }
    .btn-icon i { margin-right: 0.5rem; }
    .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; transition: opacity 0.5s ease-out; }
    .flash-message.fade-out { opacity: 0; }
    .flash-success { background-color: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
    .flash-error { background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; }

    .input-std {
        display: block; width: 100%;
        padding-left: 0.75rem; padding-right: 0.75rem; padding-top: 0.5rem; padding-bottom: 0.5rem;
        background-color: #ffffff;
        border-width: 1px; border-color: #d1d5db; border-radius: 0.375rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
        outline: none;
    }
    .input-std:focus {
        --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
        --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color);
        box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
        border-color: #4f46e5;
    }

    /* Styles for the custom delete modal */
    .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        display: flex;
        justify-content: center;
        align-items: center;
        z-index: 1000;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.3s ease, visibility 0.3s ease;
    }
    .modal-overlay.active {
        opacity: 1;
        visibility: visible;
    }
    .modal-box {
        background-color: white;
        padding: 2rem;
        border-radius: 0.5rem;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        text-align: center;
        max-width: 400px;
        width: 90%;
        transform: translateY(-20px);
        opacity: 0;
        transition: transform 0.3s ease, opacity 0.3s ease;
    }
    .modal-overlay.active .modal-box {
        transform: translateY(0);
        opacity: 1;
    }
    .modal-box .icon-wrapper {
        background-color: #fee2e2; /* Red-100 */
        color: #ef4444; /* Red-500 */
        border-radius: 9999px; /* Full rounded */
        width: 56px; /* h-14 */
        height: 56px; /* w-14 */
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem; /* mx-auto mb-6 */
    }
    .modal-box .icon-wrapper i {
        font-size: 2rem; /* text-4xl */
    }

    /* Styles for the success modal */
    .success-modal .icon-wrapper {
        background-color: #d1fae5; /* Green-100 */
        color: #10b981; /* Green-500 */
    }
</style>

<!-- MAIN CONTENT AREA -->
<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto">
        <?php /* Old flash messages are removed, replaced by custom modals */ ?>
        <?php /*
        <?php if ($success_message): ?>
            <div id="successMessage" class="flash-message flash-success flex items-center">
                <i class="fas fa-check-circle fa-lg mr-3 py-1"></i>
                <div><p class="font-bold">Success</p><p class="text-sm"><?php echo htmlspecialchars($success_message); ?></p></div>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div id="errorMessage" class="flash-message flash-error flex items-center">
                <i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i>
                <div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p></div>
            </div>
        <?php endif; ?>
        */ ?>

        <section id="manage-languages" class="mb-12">
            <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4"><?php echo $language_to_edit_id ? 'Edit Language' : 'Add New Language'; ?></h2>
                <form action="admin_dashboard.php" method="POST" class="space-y-4">
                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php if ($language_to_edit_id): ?><input type="hidden" name="language_id_to_update" value="<?php echo htmlspecialchars($language_to_edit_id); ?>"><?php endif; ?>
                    <div>
                        <label for="language_name_input" class="block text-sm font-medium text-gray-700">Language Name:</label>
                        <input type="text" id="language_name_input" name="language_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., English, Spanish, Malay" value="<?php echo htmlspecialchars($language_to_edit_name); ?>" required />
                    </div>
                    <div class="flex items-center">
                        <?php if ($language_to_edit_id): ?>
                            <button type="submit" name="update_language" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md"><i class="fas fa-save"></i>Update</button>
                            <a href="admin_dashboard.php?search_query=<?php echo urlencode($search_query); ?>" class="ml-3 border border-gray-300 py-2 px-4 rounded-md bg-white hover:bg-gray-50">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_language" class="btn-icon bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md"><i class="fas fa-plus-circle"></i>Add</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">Existing Languages</h2>
                    <form action="admin_dashboard.php" method="GET" class="flex items-center space-x-2 w-full md:w-1/3">
                        <div class="relative flex-grow">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                            <input type="text" name="search_query" placeholder="Type language name..." class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md">Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="admin_dashboard.php" class="text-gray-600 bg-gray-200 hover:bg-gray-300 py-2 px-4 rounded-md text-sm whitespace-nowrap">Clear</a>
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
                                        
                                        <!-- The delete button now triggers the custom modal -->
                                        <button type="button" class="text-red-600 hover:text-red-900 delete-language-button" data-language-id="<?php echo $row['id']; ?>" data-language-name="<?php echo htmlspecialchars($row['language_name']); ?>">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete
                                        </button>
                                        <!-- The actual form for submission will be handled by JS -->
                                        <form id="deleteLanguageForm-<?php echo $row['id']; ?>" action="admin_dashboard.php" method="POST" style="display: none;">
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

<!-- Closing tags for HTML structure initiated in header.php -->
</div> <!-- Closes <div class="flex-1 flex flex-col overflow-hidden"> from header.php -->
</div> <!-- Closes <div class="flex h-screen overflow-hidden"> from header.php -->

<!-- Custom Delete Confirmation Modal HTML for Languages -->
<div id="deleteLanguageModal" class="modal-overlay">
    <div class="modal-box">
        <div class="icon-wrapper">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-3">Are you sure?</h3>
        <p class="text-sm text-gray-500 mb-6" id="deleteLanguageModalMessage">
            This action cannot be undone. All data associated with language <span class="font-semibold" id="deleteLanguageNamePlaceholder"></span> will be lost.
        </p>
        <div class="flex flex-col space-y-3">
            <button id="confirmDeleteLanguageButton" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                Delete Language
            </button>
            <button id="cancelDeleteLanguageButton" class="w-full border border-gray-300 py-2 px-4 rounded-md bg-white hover:bg-gray-50 text-gray-700 transition duration-150 ease-in-out">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Custom Success Modal HTML -->
<div id="successModal" class="modal-overlay">
    <div class="modal-box success-modal">
        <div class="icon-wrapper">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-3">Success</h3>
        <p class="text-sm text-gray-500 mb-6" id="successModalMessage">
            Action is done successfully!
        </p>
        <button id="confirmSuccessButton" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            Confirm
        </button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // This script block should contain JavaScript specific to admin_dashboard.php.
    // The user dropdown JS is now handled entirely within header.php.

    // Update the page title in the header h1 (id="page-title" from header.php)
    const pageTitleElement = document.getElementById('page-title');
    if (pageTitleElement) {
        pageTitleElement.textContent = 'Manage Languages';
    }
    // Update the browser tab title
    document.title = 'Manage Languages | Admin Dashboard';

    // Sidebar toggle logic (from original admin_dashboard.php, kept here for completeness if not moved globally)
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    // Ensure sidebarTexts is robust to sidebar being null (though unlikely in this setup)
    const sidebarTexts = sidebar ? sidebar.querySelectorAll('.sidebar-text') : [];
    
    function toggleSidebarDesktop() {
        if (!sidebar || !sidebarToggle) return;
        const toggleIcon = sidebarToggle.querySelector('i');
        sidebar.classList.toggle('w-64');
        sidebar.classList.toggle('w-20');
        const isCollapsed = sidebar.classList.contains('w-20');
        sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
        if (toggleIcon) {
            // Use classes for icons or simpler toggle logic if fa-chevron-left/right are used
            // This transform rotates the icon for a visual effect.
            toggleIcon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebarDesktop);
        // Set initial state of icon based on sidebar's initial collapsed state
        const isCollapsed = sidebar.classList.contains('w-20');
        const toggleIcon = sidebarToggle.querySelector('i');
        if (toggleIcon) {
            toggleIcon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }
    
    if (mobileSidebarToggle && sidebar) {
        // Ensure initial state is hidden on small screens
        sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
        mobileSidebarToggle.addEventListener('click', e => {
            e.stopPropagation(); // Stop event propagation to prevent immediate closing by document click
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0'); // Show sidebar by removing -translate-x-full
        });
        document.addEventListener('click', e => {
            // Close mobile sidebar if clicked outside, and it's currently open, and on a small screen
            if (sidebar && !sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target) && sidebar.classList.contains('translate-x-0')) {
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.add('-translate-x-full');
            }
        });
    }

    // Flash message auto-hide logic - These are now primarily controlled by the PHP-set variables
    // and displayed via the success/error modals.
    // The old flash message divs are commented out in the HTML.
    // const successAlert = document.getElementById('successMessage');
    // const errorAlert = document.getElementById('errorMessage');
    // function autoHide(el) {
    //     if(el) {
    //         setTimeout(() => {
    //             el.style.opacity = '0';
    //             setTimeout(() => el.remove(), 500);
    //         }, 4000);
    //     }
    // }
    // autoHide(successAlert);
    // autoHide(errorAlert);


    // If editing, scroll to edit section (if it exists)
    if (window.location.hash === '#manage-languages') { // Adjusted hash to match the section ID
        const manageLanguagesSection = document.getElementById('manage-languages');
        if (manageLanguagesSection) {
            manageLanguagesSection.scrollIntoView({ behavior: 'smooth' });
        }
    }

    // --- Custom Delete Confirmation Modal Logic for Languages ---
    const deleteLanguageModal = document.getElementById('deleteLanguageModal');
    const confirmDeleteLanguageButton = document.getElementById('confirmDeleteLanguageButton');
    const cancelDeleteLanguageButton = document.getElementById('cancelDeleteLanguageButton');
    const deleteLanguageNamePlaceholder = document.getElementById('deleteLanguageNamePlaceholder');
    let currentDeleteLanguageForm = null; // To store the form that needs to be submitted

    document.querySelectorAll('.delete-language-button').forEach(button => {
        button.addEventListener('click', function() {
            const languageId = this.dataset.languageId;
            const languageName = this.dataset.languageName;
            currentDeleteLanguageForm = document.getElementById(`deleteLanguageForm-${languageId}`);

            deleteLanguageNamePlaceholder.textContent = `'${languageName}'`;
            deleteLanguageModal.classList.add('active'); // Show the modal
        });
    });

    confirmDeleteLanguageButton.addEventListener('click', () => {
        if (currentDeleteLanguageForm) {
            currentDeleteLanguageForm.submit(); // Submit the form if confirmed
        }
        deleteLanguageModal.classList.remove('active'); // Hide modal
    });

    cancelDeleteLanguageButton.addEventListener('click', () => {
        deleteLanguageModal.classList.remove('active'); // Hide modal
        currentDeleteLanguageForm = null; // Clear the stored form
    });

    // Close modal if overlay is clicked
    deleteLanguageModal.addEventListener('click', (e) => {
        if (e.target === deleteLanguageModal) { // Check if the click was directly on the overlay
            deleteLanguageModal.classList.remove('active');
            currentDeleteLanguageForm = null;
        }
    });

    // --- Custom Success/Error Modal Display Logic ---
    const successModal = document.getElementById('successModal');
    const confirmSuccessButton = document.getElementById('confirmSuccessButton');
    const successModalMessage = document.getElementById('successModalMessage');
    // Assuming you might also want a generic error modal, it would be similar
    // const errorModal = document.getElementById('errorModal');
    // const confirmErrorButton = document.getElementById('confirmErrorButton');
    // const errorModalMessage = document.getElementById('errorModalMessage');

    // PHP variables for success/error messages, passed to JS
    const phpSuccessMessage = <?php echo json_encode($modal_success_message); ?>;
    const phpErrorMessage = <?php echo json_encode($modal_error_message); ?>;

    if (phpSuccessMessage) {
        successModalMessage.textContent = phpSuccessMessage;
        successModal.classList.add('active');
    }
    // else if (phpErrorMessage) {
    //     errorModalMessage.textContent = phpErrorMessage;
    //     errorModal.classList.add('active');
    // }

    confirmSuccessButton.addEventListener('click', () => {
        successModal.classList.remove('active');
        // Optionally reload the page or perform other actions after success confirmation
        // For 'add language', a reload is useful to see the new entry in the table
        window.location.href = window.location.pathname + window.location.search; // Reload current page, preserving GET params
    });

    // Handle clicks on the success modal overlay to close it
    successModal.addEventListener('click', (e) => {
        if (e.target === successModal) {
            successModal.classList.remove('active');
            window.location.href = window.location.pathname + window.location.search; // Reload on overlay click as well
        }
    });

    // (If you add a generic error modal, add similar event listeners for it)
});
</script>

</body>
</html>
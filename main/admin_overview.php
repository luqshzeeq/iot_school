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

// --- PHP Logic for Language Management (Add/Edit/Delete) ---
// Note: This part handles POST actions for language CRUD. GET parameters for search are separated below.
$language_to_edit_id = null;
$language_to_edit_name = '';

$modal_success_message = null;
$modal_error_message = null; // For errors from POST actions handled immediately

// Function to construct a redirect URL (redefined locally if not in header.php)
if (!function_exists('get_redirect_url_with_search')) {
    function get_redirect_url_with_search($base_url, $query) {
        $url = $base_url;
        $params = [];
        if ($query !== '') {
            $params['search_query'] = $query;
        }
        if (!empty($params)) {
            $url .= "?" . http_build_query($params);
        }
        return $url;
    }
}

// --- Date Calculation (For general dashboard stats - Last 7 Days) ---
// These are for user registrations, existing password resets, and daily language settings tables.
$today_timestamp = time();
$end_date_timestamp = $today_timestamp;
$start_date_timestamp = strtotime('-6 days', $today_timestamp); // Last 7 days
$start_date_str = date('Y-m-d 00:00:00', $start_date_timestamp);
$end_date_str = date('Y-m-d 23:59:59', $end_date_timestamp);
$start_date_query_format = date('Y-m-d', $start_date_timestamp);
$end_date_query_format = date('Y-m-d', $end_date_timestamp);

// Initialize arrays for weekly chart data
$week_labels = [];
$week_user_data = [];
// This will now be specific to the "New Teacher Registrations (Last 7 Days)" chart
$current_ts_weekly = $start_date_timestamp;
while ($current_ts_weekly <= $end_date_timestamp) {
    $date_key = date('Y-m-d', $current_ts_weekly);
    $week_labels[] = date('D (d M)', $current_ts_weekly);
    $week_user_data[$date_key] = 0; // Initialize with 0
    $current_ts_weekly = strtotime('+1 day', $current_ts_weekly);
}


// --- Date Calculation (For Monthly Languages Added Chart - Jan-Dec) ---
$current_year = date('Y');
$start_of_year_str = date('Y-01-01 00:00:00', strtotime($current_year . '-01-01'));
$end_of_year_str = date('Y-12-31 23:59:59', strtotime($current_year . '-12-31'));

$month_labels = [
    'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun',
    'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'
];
$monthly_lang_data = array_fill_keys(range(1, 12), 0); // Initialize all 12 months with 0


// Handle POST requests for language management
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $action_status_type = '';
    $action_status_message = '';

    if (isset($_POST['add_language'])) {
        $new_language_name = trim($_POST['language_name'] ?? '');
        if (!empty($new_language_name)) {
            $stmt = $conn->prepare("INSERT INTO languages (language_name, created_by, created_at) VALUES (?, ?, NOW())"); // Added NOW() for created_at
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
            $stmt = $conn->prepare("UPDATE languages SET language_name = ?, created_at = NOW() WHERE id = ?"); // Update created_at on update
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

    if ($action_status_type === 'success') {
        $modal_success_message = $action_status_message;
    } elseif ($action_status_type === 'error') {
        $modal_error_message = $action_status_message;
    }
}

// Fetch language for editing if 'edit_language' GET parameter is present.
$search_query_languages = trim($_GET['search_query_languages'] ?? ''); // Re-get for current request
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
                $_SESSION['error_message'] = "Language not found for editing."; // Use session for errors on redirection
                header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query_languages));
                exit();
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing fetch language for edit statement: " . $conn->error;
            header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query_languages));
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Invalid ID for editing.";
        header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query_languages));
        exit();
    }
}

// --- Data for Recent Language Setups Table ---
// Fetch all languages for the table.
$all_languages_data = [];
$sql_languages_table = "SELECT id, language_name, created_by, created_at FROM languages";
// The search filter for languages is handled client-side now via JS
$sql_languages_table .= " ORDER BY id ASC"; // Order by ID for consistency
$stmt_all_languages_table = $conn->prepare($sql_languages_table);
if ($stmt_all_languages_table) {
    if ($stmt_all_languages_table->execute()) {
        $result = $stmt_all_languages_table->get_result();
        while($row = $result->fetch_assoc()) {
             // Fetch username for created_by
            $created_by_username = 'N/A';
            if ($row['created_by']) {
                $stmt_user = $conn->prepare("SELECT username FROM users WHERE id = ?");
                if ($stmt_user) {
                    $stmt_user->bind_param("i", $row['created_by']);
                    $stmt_user->execute();
                    $user_result = $stmt_user->get_result();
                    if ($user_row = $user_result->fetch_assoc()) {
                        $created_by_username = $user_row['username'];
                    }
                    $stmt_user->close();
                }
            }
            $all_languages_data[] = [
                'id' => $row['id'],
                'language_name' => $row['language_name'],
                'added_by' => $created_by_username,
                'date_added' => $row['created_at']
            ];
        }
    } else {
        $modal_error_message = "Error executing fetch all languages statement: " . $stmt_all_languages_table->error;
    }
    $stmt_all_languages_table->close();
} else {
    $modal_error_message = "Error preparing fetch all languages statement: " . $conn->error;
}


// Fetching recent password reset requests
$recent_password_resets_data = [];
$search_query_resets = trim($_GET['search_query_resets'] ?? ''); // Unique param
$sql_recent_resets = "SELECT pr.expires_at, u.username, pr.email FROM password_resets pr JOIN users u ON pr.email = u.email WHERE pr.expires_at BETWEEN ? AND ?";

// Add search filters for the reset requests
if ($search_query_resets !== '') {
    $sql_recent_resets .= " AND (u.username LIKE ? OR pr.email LIKE ?)";
}
$sql_recent_resets .= " ORDER BY pr.expires_at DESC LIMIT 100"; // Limit to a reasonable number for client-side processing

$stmt_recent_resets = $conn->prepare($sql_recent_resets);
if ($stmt_recent_resets) {
    $bind_types = "ss";
    $bind_params = [$start_date_str, $end_date_str];
    if ($search_query_resets !== '') {
        $search_param_resets = "%" . $search_query_resets . "%";
        $bind_params[] = $search_param_resets;
        $bind_params[] = $search_param_resets;
        $bind_types .= "ss";
    }
    $stmt_recent_resets->bind_param($bind_types, ...$bind_params);

    if ($stmt_recent_resets->execute()) {
        $result = $stmt_recent_resets->get_result();
        while ($row = $result->fetch_assoc()) {
            // Determine the reset status
            $status = (strtotime($row['expires_at']) > time()) ? 'Pending' : 'Expired';
            
            $recent_password_resets_data[] = [
                'user' => $row['username'],
                'email' => $row['email'],
                'request_date' => $row['expires_at'],
                'status' => $status // Set the correct status
            ];
        }
    }
    $stmt_recent_resets->close();
} else {
    $modal_error_message = "Error preparing the SQL query for password resets: " . $conn->error;
}



// Fetch daily language settings table
$teacher_daily_lang_settings_data = [];
// CORRECTED: The "WHERE tdl.setting_date BETWEEN ? AND ?" clause is removed to fetch all data.
$sql_daily_langs = "SELECT tdl.setting_date, u.username AS teacher_username, l.language_name, tdl.set_by_teacher_id FROM teacher_daily_languages tdl LEFT JOIN users u ON tdl.set_by_teacher_id = u.id JOIN languages l ON tdl.language_id = l.id ORDER BY tdl.setting_date DESC, u.username ASC LIMIT 100";

$result_daily_langs = $conn->query($sql_daily_langs);
if ($result_daily_langs) {
    while ($row = $result_daily_langs->fetch_assoc()) {
        // Handle cases where a teacher might have been deleted but their settings remain
        $username_display = $row['teacher_username'] ?? 'N/A (ID: ' . ($row['set_by_teacher_id'] ?? '') . ')'; 
        $teacher_daily_lang_settings_data[] = [
            'setting_date' => $row['setting_date'],
            'teacher_username' => $username_display,
            'language_name' => $row['language_name']
        ];
    }
} else {
    $modal_error_message = "Error fetching daily language settings: " . $conn->error;
}
// --- 4. Fetch All Data (Dashboard Stats & Charts) ---
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

// Fetch new languages chart data (for chart) - This now needs to aggregate monthly
// Initialize monthly counts again to ensure it's clean for the chart's data
$monthly_lang_chart_data_counts = array_fill_keys(range(1, 12), 0);

$stmt_langs_chart_data_monthly = $conn->prepare("SELECT MONTH(created_at) as month_num, COUNT(*) as count FROM languages WHERE YEAR(created_at) = ? GROUP BY MONTH(created_at)");
if ($stmt_langs_chart_data_monthly) {
    $stmt_langs_chart_data_monthly->bind_param("i", $current_year);
    if ($stmt_langs_chart_data_monthly->execute()) {
        $result = $stmt_langs_chart_data_monthly->get_result();
        if ($result) {
            while($row = $result->fetch_assoc()) {
                $month_num = (int)$row['month_num'];
                if (array_key_exists($month_num, $monthly_lang_chart_data_counts)) {
                    $monthly_lang_chart_data_counts[$month_num] = $row['count'];
                }
            }
        }
    }
    $stmt_langs_chart_data_monthly->close();
}


// --- 5. Prepare Data for JS Charts ---
$user_pie_data = ['labels' => ['Active', 'Inactive'], 'data' => [$stats['active_users'], $stats['inactive_users']]];
$device_pie_data = ['labels' => ['Online', 'Offline', 'Error'], 'data' => [$stats['online_devices'], $stats['offline_devices'], $stats['error_devices']]];
$user_bar_data = ['labels' => $week_labels, 'data' => array_values($week_user_data)];
// Languages added chart data (monthly)
$lang_bar_data = ['labels' => $month_labels, 'data' => array_values($monthly_lang_chart_data_counts)];


// --- Include the Standardized Header ---
include 'header.php';
?>

<style>
    /* Standard card and chart styles */
    .stat-card { background-color: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); display: flex; align-items: center; }
    .stat-card i { font-size: 1.75rem; color: #fff; padding: 0.75rem; border-radius: 0.375rem; margin-right: 1rem; width: 50px; height: 50px; display: inline-flex; justify-content: center; align-items: center; }
    .stat-card .value { font-size: 1.875rem; font-weight: 700; color: #111827; }
    .stat-card .label { font-size: 0.875rem; color: #6b7280; }
    .chart-container { background-color: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); display: flex; flex-direction: column; align-items: center; justify-content: center; } /* Added flex for center content */
    #sidebarToggle i { transition: transform 0.3s ease-in-out; }

    /* Styles for status badges in tables */
    .status-badge {
        padding: 0.25rem 0.5rem;
        border-radius: 9999px; /* Full rounded */
        font-size: 0.75rem; /* text-xs */
        font-weight: 600; /* font-semibold */
        line-height: 1;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
    .status-badge.success { background-color: #d1fae5; color: #065f46; } /* green-100, green-800 */
    .status-badge.pending { background-color: #fef3c7; color: #92400e; } /* yellow-100, yellow-800 */
    .status-badge.expired { background-color: #fee2e2; color: #991b1b; } /* red-100, red-800 */
    .status-badge.failed { background-color: #fee2e2; color: #991b1b; } /* red-100, red-800 */

    /* Styles for the common modal structure (used by delete, success, and logout in header.php) */
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
        background-color: #e2e8f0; /* Gray-200 */
        color: #4a5568; /* Gray-700 */
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
    .success-modal .icon-wrapper {
        background-color: #d1fae5;
        color: #10b981;
    }
    .error-modal .icon-wrapper {
        background-color: #fee2e2;
        color: #ef4444;
    }

    /* Styles for the common search/export/pagination table header */
    .table-controls-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        flex-wrap: wrap; /* Allow wrapping on smaller screens */
        gap: 0.5rem; /* Spacing between items */
    }
    .table-search-input {
        flex-grow: 1; /* Allows search input to take available space */
        max-width: 250px; /* Limit max width for desktop */
        min-width: 150px; /* Ensure minimum width */
        padding-left: 2.5rem; /* Space for icon */
    }
    .export-button {
        padding: 0.5rem 1rem;
        background-color: #4CAF50; /* Green color for export */
        color: white;
        border-radius: 0.375rem;
        font-weight: 600;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        transition: background-color 0.2s;
    }
    .export-button:hover {
        background-color: #45a049;
    }

    /* Styles for pagination buttons */
    .pagination-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 1rem;
    }
    .pagination-numbers button {
        /* Styles for page number buttons */
        transition: background-color 0.2s, color 0.2s;
        border: 1px solid transparent; /* Default transparent border */
    }
    /* New styling for active pagination button */
    .pagination-numbers button.active-page {
        background-color: #e0f2f7; /* Light blue background */
        color: #2980b9; /* Blue text */
        border-color: #a7d9ee; /* Lighter blue border */
        font-weight: 700;
    }
    .prev-page-btn, .next-page-btn {
        /* Styles for Previous/Next buttons */
        padding: 0.5rem 1rem; /* More padding */
        border-radius: 0.5rem; /* More rounded */
        border: 1px solid #d1d5db;
        transition: background-color 0.2s, opacity 0.2s;
        background-color: white;
        color: #4a5568; /* Text color */
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); /* Subtle shadow */
    }
    .prev-page-btn:hover, .next-page-btn:hover {
        background-color: #f3f4f6; /* Light gray on hover */
    }
    .prev-page-btn:disabled, .next-page-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        box-shadow: none; /* No shadow when disabled */
    }
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto">
        <?php
        // Display session-based flash messages (from redirects, e.g., edit_language not found)
        if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) : ?>
            <div id="sessionErrorMessage" class="flash-message flash-error flex items-center">
                <i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i>
                <div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($_SESSION['error_message']); ?></p></div>
            </div>
        <?php
            unset($_SESSION['error_message']); // Clear it after displaying
        endif;
        ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="stat-card"><i class="fas fa-users bg-blue-500"></i><div><div class="value"><?php echo $stats['total_users']; ?></div><div class="label">Total Teachers</div></div></div>
            <div class="stat-card"><i class="fas fa-desktop bg-green-500"></i><div><div class="value"><?php echo $stats['total_devices']; ?></div><div class="label">Total Devices</div></div></div>
            <div class="stat-card"><i class="fas fa-language bg-purple-500"></i><div><div class="value"><?php echo $stats['total_languages']; ?></div><div class="label">Total Languages</div></div></div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="chart-container flex flex-col items-center">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">User Status (Teachers)</h3>
                <div class="relative w-full max-w-xs h-auto aspect-square">
                    <canvas id="userStatusChart"></canvas>
                </div>
            </div>
            <div class="chart-container flex flex-col items-center">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Device Status</h3>
                <div class="relative w-full max-w-xs h-auto aspect-square">
                    <canvas id="deviceStatusChart"></canvas>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="chart-container"><h3 class="text-lg font-semibold text-center text-gray-800 mb-4">New Teacher Registrations (Last 7 Days)</h3><canvas id="userWeeklyChart"></canvas></div>
            <div class="chart-container"><h3 class="text-lg font-semibold text-center text-gray-800 mb-4">Languages Added (<?php echo $current_year; ?>)</h3><canvas id="langWeeklyChart"></canvas></div>
        </div>
        
        <div class="space-y-6">

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="table-controls-header">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Language Setups</h3>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" id="languagesSearchInput" placeholder="Search language..." class="block w-full pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm table-search-input">
                        </div>
                        <button id="exportLanguages" class="export-button"><i class="fas fa-file-excel"></i>Export</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="languagesTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Added By</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Added</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            </tbody>
                    </table>
                    <div class="pagination-controls">
                        <button class="prev-page-btn" data-table-id="languagesTable">&larr; Previous</button>
                        <div class="pagination-numbers" data-table-id="languagesTable"></div>
                        <button class="next-page-btn" data-table-id="languagesTable">Next &rarr;</button>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="table-controls-header">
                    <h3 class="text-lg font-semibold text-gray-800">Recent Password Reset Requests</h3>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" id="resetsSearchInput" placeholder="Search user/email..." class="block w-full pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm table-search-input">
                        </div>
                        <button id="exportResets" class="export-button"><i class="fas fa-file-excel"></i>Export</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="resetsTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Request Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            </tbody>
                    </table>
                    <div class="pagination-controls">
                        <button class="prev-page-btn" data-table-id="resetsTable">&larr; Previous</button>
                        <div class="pagination-numbers" data-table-id="resetsTable"></div>
                        <button class="next-page-btn" data-table-id="resetsTable">Next &rarr;</button>
                    </div>
                </div>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="table-controls-header">
                    <h3 class="text-lg font-semibold text-gray-800">Daily Language Settings by Teacher</h3>
                    <div class="flex items-center gap-2">
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" id="dailyLangsSearchInput" placeholder="Search teacher/language..." class="block w-full pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm table-search-input">
                        </div>
                        <button id="exportDailyLangs" class="export-button"><i class="fas fa-file-excel"></i>Export</button>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table id="dailyLangsTable" class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Set</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            </tbody>
                    </table>
                    <div class="pagination-controls">
                        <button class="prev-page-btn" data-table-id="dailyLangsTable">&larr; Previous</button>
                        <div class="pagination-numbers" data-table-id="dailyLangsTable"></div>
                        <button class="next-page-btn" data-table-id="dailyLangsTable">Next &rarr;</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

</div> </div> <div id="successModal" class="modal-overlay">
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

<div id="errorModal" class="modal-overlay">
    <div class="modal-box error-modal">
        <div class="icon-wrapper">
            <i class="fas fa-times-circle"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-3">Error</h3>
        <p class="text-sm text-gray-500 mb-6" id="errorModalMessage">
            Something went wrong. Please try again.
        </p>
        <button id="confirmErrorButton" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
            Dismiss
        </button>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', () => {
    // Update the page title in the header h1 (id="page-title" from header.php)
    const pageTitleElement = document.getElementById('page-title');
    if (pageTitleElement) {
        pageTitleElement.textContent = 'Dashboard Overview';
    }
    // Update the browser tab title
    document.title = 'Admin Dashboard Overview';

    // Sidebar toggle logic (kept here for completeness if not moved globally)
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
            toggleIcon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebarDesktop);
        const isCollapsed = sidebar.classList.contains('w-20');
        const toggleIcon = sidebarToggle.querySelector('i');
        if (toggleIcon) {
            toggleIcon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }
    
    if (mobileSidebarToggle && sidebar) {
        sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
        mobileSidebarToggle.addEventListener('click', e => {
            e.stopPropagation();
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0');
        });
        document.addEventListener('click', e => {
            if (sidebar && !sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target) && sidebar.classList.contains('translate-x-0')) {
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.add('-translate-x-full');
            }
        });
    }

    // --- Session-based Error Message Display (from redirects) ---
    const sessionErrorMessageDiv = document.getElementById('sessionErrorMessage');
    if (sessionErrorMessageDiv) {
        setTimeout(() => {
            sessionErrorMessageDiv.classList.add('fade-out');
            setTimeout(() => sessionErrorMessageDiv.remove(), 500);
        }, 4000);
    }

    // --- Custom Success/Error Modal Display Logic (for POST actions on current page) ---
    const successModal = document.getElementById('successModal');
    const confirmSuccessButton = document.getElementById('confirmSuccessButton');
    const successModalMessage = document.getElementById('successModalMessage');

    const errorModal = document.getElementById('errorModal');
    const confirmErrorButton = document.getElementById('confirmErrorButton');
    const errorModalMessage = document.getElementById('errorModalMessage');

    // PHP variables for success/error messages, passed to JS
    const phpSuccessMessage = <?php echo json_encode($modal_success_message); ?>;
    const phpErrorMessage = <?php echo json_encode($modal_error_message); ?>;

    if (phpSuccessMessage) {
        successModalMessage.textContent = phpSuccessMessage;
        successModal.classList.add('active');
    } else if (phpErrorMessage) {
        errorModalMessage.textContent = phpErrorMessage;
        errorModal.classList.add('active');
    }

    // Success modal button/overlay click handler
    confirmSuccessButton.addEventListener('click', () => {
        successModal.classList.remove('active');
        window.location.href = window.location.pathname + window.location.search; // Reload to reflect changes
    });
    successModal.addEventListener('click', (e) => {
        if (e.target === successModal) {
            successModal.classList.remove('active');
            window.location.href = window.location.pathname + window.location.search;
        }
    });

    // Error modal button/overlay click handler
    confirmErrorButton.addEventListener('click', () => {
        errorModal.classList.remove('active');
    });
    errorModal.addEventListener('click', (e) => {
        if (e.target === errorModal) {
            errorModal.classList.remove('active');
        }
    });

    // --- Chart.js Data from PHP ---
    const userPieData = <?php echo json_encode($user_pie_data); ?>;
    const devicePieData = <?php echo json_encode($device_pie_data); ?>;
    const userBarData = <?php echo json_encode($user_bar_data); ?>;
    const langBarData = <?php echo json_encode($lang_bar_data); ?>;

    // --- Chart Initializations ---
    const barOptions = { responsive: true, maintainAspectRatio: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } };
    const doughnutOptions = { responsive: true, maintainAspectRatio: false, cutout: '70%', plugins: { legend: { position: 'right' } } };

    const userStatusCtx = document.getElementById('userStatusChart');
    if(userStatusCtx && userPieData.data.some(d => d > 0)) { new Chart(userStatusCtx, { type: 'doughnut', data: { labels: userPieData.labels, datasets: [{ data: userPieData.data, backgroundColor: ['#4CAF50', '#F44336'] }] }, options: doughnutOptions }); }
    
    const deviceStatusCtx = document.getElementById('deviceStatusChart');
    if(deviceStatusCtx && devicePieData.data.some(d => d > 0)) { new Chart(deviceStatusCtx, { type: 'doughnut', data: { labels: devicePieData.labels, datasets: [{ data: devicePieData.data, backgroundColor: ['#10B981', '#F87171', '#F59E0B'] }] }, options: doughnutOptions }); }

    const userWeeklyCtx = document.getElementById('userWeeklyChart');
    if (userWeeklyCtx) { new Chart(userWeeklyCtx, { type: 'bar', data: { labels: userBarData.labels, datasets: [{ label: 'New Users', data: userBarData.data, backgroundColor: '#60A5FA', borderRadius: 4 }] }, options: barOptions }); }

    const langWeeklyCtx = document.getElementById('langWeeklyChart');
    if (langWeeklyCtx) { new Chart(langWeeklyCtx, { type: 'bar', data: { labels: langBarData.labels, datasets: [{ label: 'New Languages', data: langBarData.data, backgroundColor: [
        '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9900',
        '#FF6347', '#6A5ACD', '#DA70D6', '#8A2BE2', '#00CED1', '#FFA07A'
    ], borderRadius: 4 }] }, options: barOptions }); }


    // --- Generic Table Renderer for Client-Side Search and Pagination ---
    function setupTable(tableId, rawData, columns, itemsPerPage = 5, searchableKeys = [], exportFileName = 'data') {
        const table = document.getElementById(tableId);
        if (!table) {
            console.error(`Table with ID "${tableId}" not found.`);
            return;
        }
        const tbody = table.querySelector('tbody');
        const searchInput = table.closest('.bg-white').querySelector('.table-search-input');
        const prevButton = table.closest('.bg-white').querySelector(`.prev-page-btn[data-table-id="${tableId}"]`);
        const nextButton = table.closest('.bg-white').querySelector(`.next-page-btn[data-table-id="${tableId}"]`);
        const paginationNumbersDiv = table.closest('.bg-white').querySelector(`.pagination-numbers[data-table-id="${tableId}"]`);
        const exportButton = table.closest('.bg-white').querySelector('.export-button');

        let currentPage = 1;
        let filteredData = [...rawData];

        function renderTableRows() {
            tbody.innerHTML = ''; // Clear existing rows
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const paginatedData = filteredData.slice(startIndex, endIndex);

            if (paginatedData.length === 0) {
                tbody.innerHTML = `<tr><td colspan="${columns.length}" class="px-6 py-4 text-center text-sm text-gray-500">No data found.</td></tr>`;
                return;
            }

            paginatedData.forEach(item => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50'; // Add hover effect
                columns.forEach(col => {
                    const cell = document.createElement('td');
                    cell.className = 'px-6 py-4 whitespace-nowrap text-sm';
                    let content = '';

                    if (col.render) { // Custom rendering for complex columns (e.g., icons, statuses)
                        content = col.render(item);
                    } else if (item[col.dataKey] !== undefined) {
                        content = item[col.dataKey];
                    } else {
                        content = ''; // Default to empty string if dataKey not found
                    }

                    if (col.className) { // Apply specific class if provided
                        cell.className += ` ${col.className}`;
                    }
                    
                    // ===================================================================
                    // THE ONLY FIX IS HERE: The condition is updated to include '<span>'
                    // ===================================================================
                    if (typeof content === 'string' && (content.includes('<svg') || content.includes('<i class="fas') || content.includes('<span'))) {
                        cell.innerHTML = content;
                    } else {
                        cell.textContent = content; // Use textContent for plain text to prevent XSS
                    }
                    // ===================================================================
                    
                    row.appendChild(cell);
                });
                tbody.appendChild(row);
            });
        }

        function renderPaginationControls() {
            const totalPages = Math.ceil(filteredData.length / itemsPerPage);
            paginationNumbersDiv.innerHTML = ''; // Clear existing page numbers

            let startPageNum = Math.max(1, currentPage - 2);
            let endPageNum = Math.min(totalPages, currentPage + 2);

            if (startPageNum > 1) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.className = 'px-2 py-1 text-gray-700';
                paginationNumbersDiv.appendChild(ellipsis);
            }

            for (let i = startPageNum; i <= endPageNum; i++) {
                const pageButton = document.createElement('button');
                pageButton.textContent = i;
                pageButton.className = `px-3 py-1 rounded-md transition-colors ${i === currentPage ? 'active-page' : 'bg-white text-gray-700 hover:bg-gray-200'}`;
                pageButton.addEventListener('click', () => {
                    currentPage = i;
                    renderTableRows();
                    renderPaginationControls();
                });
                paginationNumbersDiv.appendChild(pageButton);
            }

            if (endPageNum < totalPages) {
                const ellipsis = document.createElement('span');
                ellipsis.textContent = '...';
                ellipsis.className = 'px-2 py-1 text-gray-700';
                paginationNumbersDiv.appendChild(ellipsis);
            }

            if (prevButton) {
                prevButton.disabled = currentPage === 1;
                prevButton.classList.toggle('opacity-50', currentPage === 1);
            }
            if (nextButton) {
                nextButton.disabled = currentPage === totalPages || totalPages === 0;
                nextButton.classList.toggle('opacity-50', currentPage === totalPages || totalPages === 0);
            }
        }

        function applySearchAndRender() {
            const searchTerm = searchInput.value.toLowerCase();
            filteredData = rawData.filter(item => {
                return searchableKeys.some(key => {
                    const value = item[key];
                    return value && String(value).toLowerCase().includes(searchTerm);
                });
            });
            currentPage = 1; // Reset to first page on new search
            renderTableRows();
            renderPaginationControls();
        }

        function exportTableToCSV() {
            let csv = columns.map(col => `"${col.header.replace(/"/g, '""')}"`).join(',') + '\n';
            filteredData.forEach(item => {
                let row = columns.map(col => {
                    let cellData = '';
                    if (col.exportRender) { // Use exportRender if available for cleaner export data
                        cellData = col.exportRender(item);
                    } else if (item[col.dataKey] !== undefined) {
                        cellData = item[col.dataKey];
                    }
                    return `"${String(cellData).replace(/"/g, '""')}"`;
                }).join(',');
                csv += row + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.setAttribute('download', `${exportFileName}_${new Date().toISOString().slice(0,10)}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Event listeners
        if (searchInput) {
            searchInput.addEventListener('keyup', applySearchAndRender);
        }
        if (prevButton) {
            prevButton.addEventListener('click', () => {
                if (currentPage > 1) {
                    currentPage--;
                    renderTableRows();
                    renderPaginationControls();
                }
            });
        }
        if (nextButton) {
            nextButton.addEventListener('click', () => {
                const totalPages = Math.ceil(filteredData.length / itemsPerPage);
                if (currentPage < totalPages) {
                    currentPage++;
                    renderTableRows();
                    renderPaginationControls();
                }
            });
        }
        if (exportButton) {
            exportButton.addEventListener('click', exportTableToCSV);
        }

        // Initial render
        renderTableRows();
        renderPaginationControls();
    }


    // --- Define columns and data for each specific table ---

    // Recent Language Setups Table
    const languagesColumns = [
        { header: 'ID', dataKey: 'id', className: 'text-gray-900' },
        { header: 'Language', dataKey: 'language_name', className: 'font-medium text-gray-900' },
        { header: 'Added By', dataKey: 'added_by', className: 'text-gray-500' },
        { header: 'Date Added', dataKey: 'date_added', className: 'text-gray-500',
          render: (item) => new Date(item.date_added).toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }),
          exportRender: (item) => item.date_added
        },
    ];
    const phpAllLanguagesData = <?php echo json_encode($all_languages_data); ?>;
    setupTable('languagesTable', phpAllLanguagesData, languagesColumns, 5, ['language_name', 'added_by'], 'languages_report');


    // Recent Password Reset Requests Table
    const resetsColumns = [
        { header: 'User', dataKey: 'user', className: 'font-medium text-gray-900' },
        { header: 'Email', dataKey: 'email', className: 'text-gray-500' },
        { header: 'Request Date', dataKey: 'request_date', className: 'text-gray-500',
          render: (item) => new Date(item.request_date).toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }),
          exportRender: (item) => item.request_date
        },
        {
          header: 'Status', dataKey: 'status',
          render: (item) => {
              let statusClass = '';
              switch (item.status.toLowerCase()) {
                  case 'pending': statusClass = 'pending'; break;
                  case 'expired': statusClass = 'expired'; break;
                  default: statusClass = 'failed'; break;
              }
              return `<span class="status-badge ${statusClass}">${item.status}</span>`;
          },
          exportRender: (item) => item.status
        },
    ];
    const phpRecentPasswordResetsData = <?php echo json_encode($recent_password_resets_data); ?>;
    setupTable('resetsTable', phpRecentPasswordResetsData, resetsColumns, 5, ['user', 'email'], 'password_resets_report');


    // Daily Language Settings by Teacher Table
    const dailyLangsColumns = [
        { header: 'Date Set', dataKey: 'setting_date', className: 'text-gray-500',
          render: (item) => new Date(item.setting_date).toLocaleString('en-US', { month: 'short', day: '2-digit', year: 'numeric' }),
          exportRender: (item) => item.setting_date
        },
        { header: 'Teacher', dataKey: 'teacher_username', className: 'font-medium text-gray-900' },
        { header: 'Language', dataKey: 'language_name', className: 'text-gray-500' },
    ];
    const phpTeacherDailyLangSettingsData = <?php echo json_encode($teacher_daily_lang_settings_data); ?>;
    setupTable('dailyLangsTable', phpTeacherDailyLangSettingsData, dailyLangsColumns, 5, ['teacher_username', 'language_name'], 'daily_language_settings_report');
});
</script>

</body>
</html>
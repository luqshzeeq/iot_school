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
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']); // Clear messages after retrieving

$language_to_edit_id = null;
$language_to_edit_name = '';

function get_redirect_url_with_search($base_url, $query) {
    return $query ? $base_url . "?search_query=" . urlencode($query) : $base_url;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect_url = get_redirect_url_with_search("admin_dashboard.php", $_POST['search_query'] ?? '');
    if (isset($_POST['add_language'])) {
        $new_language_name = trim($_POST['language_name'] ?? '');
        if (!empty($new_language_name)) {
            $stmt = $conn->prepare("INSERT INTO languages (language_name, created_by) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("si", $new_language_name, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Language '" . htmlspecialchars($new_language_name) . "' added successfully.";
                } else {
                    $_SESSION['error_message'] = "Error adding language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing add language statement: " . $conn->error;
            }
        } else { $_SESSION['error_message'] = "Language name cannot be empty."; }
    }
    if (isset($_POST['delete_language'])) {
        $language_id = filter_var($_POST['language_id_to_delete'], FILTER_VALIDATE_INT);
        if ($language_id !== false) { // Check for valid integer
            $stmt = $conn->prepare("DELETE FROM languages WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $language_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = ($stmt->affected_rows > 0) ? "Language deleted successfully." : "Language not found or already deleted.";
                } else {
                    $_SESSION['error_message'] = "Error deleting language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing delete language statement: " . $conn->error;
            }
        } else { $_SESSION['error_message'] = "Invalid language ID for deletion."; }
    }
    if (isset($_POST['update_language'])) {
        $language_id = filter_var($_POST['language_id_to_update'], FILTER_VALIDATE_INT);
        $updated_name = trim($_POST['language_name'] ?? '');
        if ($language_id !== false && !empty($updated_name)) { // Check for valid integer and non-empty name
            $stmt = $conn->prepare("UPDATE languages SET language_name = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $updated_name, $language_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = ($stmt->affected_rows > 0) ? "Language updated successfully." : "No changes were made to the language.";
                } else {
                    $_SESSION['error_message'] = "Error updating language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing update language statement: " . $conn->error;
            }
        } else { $_SESSION['error_message'] = "Invalid data for update."; }
    }
    header("Location: " . $redirect_url);
    exit(); // Always exit after a header redirect
}

// Fetch language for editing if 'edit_language' GET parameter is present.
if (isset($_GET['edit_language'])) {
    $language_to_edit_id = filter_var($_GET['edit_language'], FILTER_VALIDATE_INT);
    if ($language_to_edit_id !== false) { // Ensure it's a valid integer
        $stmt = $conn->prepare("SELECT language_name FROM languages WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $language_to_edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $language_to_edit_name = $row['language_name'];
            } else {
                $_SESSION['error_message'] = "Language not found for editing.";
                header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
                exit(); // Exit after redirect
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing fetch language for edit statement: " . $conn->error;
            header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
            exit(); // Exit after redirect
        }
    } else {
        $_SESSION['error_message'] = "Invalid ID for editing.";
        header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
        exit(); // Exit after redirect
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
    if ($stmt_all->execute()) { // Check if execute was successful
        $result_all_languages = $stmt_all->get_result();
    } else {
        $error_message = "Error executing fetch all languages statement: " . $stmt_all->error;
        $result_all_languages = false; // Ensure result is false on failure
    }
    $stmt_all->close();
} else {
    $error_message = "Error preparing fetch all languages statement: " . $conn->error;
    $result_all_languages = false; // Ensure result is false on failure
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
    .stat-card { background-color: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); display: flex; align-items: center; }
    .stat-card i { font-size: 1.75rem; color: #fff; padding: 0.75rem; border-radius: 0.375rem; margin-right: 1rem; width: 50px; height: 50px; display: inline-flex; justify-content: center; align-items: center; }
    .stat-card .value { font-size: 1.875rem; font-weight: 700; color: #111827; }
    .stat-card .label { font-size: 0.875rem; color: #6b7280; }
    .chart-container { background-color: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); }
    /* RESTORED: Style for the sidebar toggle icon animation */
    #sidebarToggle i {
        transition: transform 0.3s ease-in-out;
    }
</style>

<!-- MAIN CONTENT AREA -->
<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto">
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

        <!-- Stat Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="stat-card"><i class="fas fa-users bg-blue-500"></i><div><div class="value"><?php echo $stats['total_users']; ?></div><div class="label">Total Teachers</div></div></div>
            <div class="stat-card"><i class="fas fa-desktop bg-green-500"></i><div><div class="value"><?php echo $stats['total_devices']; ?></div><div class="label">Total Devices</div></div></div>
            <div class="stat-card"><i class="fas fa-language bg-purple-500"></i><div><div class="value"><?php echo $stats['total_languages']; ?></div><div class="label">Total Languages</div></div></div>
        </div>

        <!-- Doughnut Charts -->
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

        <!-- Bar Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="chart-container"><h3 class="text-lg font-semibold text-center text-gray-800 mb-4">New Teacher Registrations (Last 7 Days)</h3><canvas id="userWeeklyChart"></canvas></div>
            <div class="chart-container"><h3 class="text-lg font-semibold text-center text-gray-800 mb-4">Languages Added (Last 7 Days)</h3><canvas id="langWeeklyChart"></canvas></div>
        </div>
        
        <!-- All Tables -->
        <div class="space-y-6">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Language Setups (Last 7 Days)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Added By</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Added</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($languages_by_teacher_week)): foreach ($languages_by_teacher_week as $lang_entry): ?>
                            <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><?php echo htmlspecialchars($lang_entry['language_name']); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($lang_entry['username'] ?? 'N/A'); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars(date('M d, Y', strtotime($lang_entry['created_at']))); ?></td></tr>
                            <?php endforeach; else: ?><tr><td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No languages added in the last 7 days.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Password Reset Requests (Last 7 Days)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">User</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Request Date</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($recent_password_resets)): foreach ($recent_password_resets as $reset_entry): ?>
                            <tr><td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><?php echo htmlspecialchars($reset_entry['username'] ?? 'N/A'); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($reset_entry['email'] ?? 'N/A'); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($reset_entry['expires_at']))); ?></td></tr>
                            <?php endforeach; else: ?><tr><td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No password resets in the last 7 days.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Daily Language Settings by Teacher (Last 7 Days)</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date Set</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Teacher</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($teacher_daily_lang_settings)): foreach ($teacher_daily_lang_settings as $setting_entry): ?>
                            <tr><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars(date('M d, Y', strtotime($setting_entry['setting_date']))); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm font-medium"><?php echo htmlspecialchars($setting_entry['teacher_username'] ?? 'N/A'); ?></td><td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($setting_entry['language_name'] ?? 'N/A'); ?></td></tr>
                            <?php endforeach; else: ?><tr><td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No daily language settings recorded in the last 7 days.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Closing tags for HTML structure initiated in header.php -->
</div> <!-- Closes <div class="flex-1 flex flex-col overflow-hidden"> from header.php -->
</div> <!-- Closes <div class="flex h-screen overflow-hidden"> from header.php -->

<script>
document.addEventListener('DOMContentLoaded', () => {
    // This script block should contain JavaScript specific to admin_dashboard.php.
    // The user dropdown JS is now handled entirely within header.php.

    // Update the page title in the header h1 (id="page-title" from header.php)
    const pageTitleElement = document.getElementById('page-title');
    if (pageTitleElement) {
        pageTitleElement.textContent = 'Dashboard Overview'; // Changed to 'Dashboard Overview' for dashboard page
    }
    // Update the browser tab title
    document.title = 'Admin Dashboard Overview'; // Changed title for dashboard page

    // Sidebar toggle logic (kept here as it's common but not directly related to dropdown)
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

    // Flash message auto-hide logic
    const successAlert = document.getElementById('successMessage');
    const errorAlert = document.getElementById('errorMessage');
    function autoHide(el) {
        if(el) {
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500);
            }, 4000);
        }
    }
    autoHide(successAlert);
    autoHide(errorAlert);

    // If editing, scroll to edit section (if it exists)
    if (window.location.hash === '#manage-languages') {
        const manageLanguagesSection = document.getElementById('manage-languages');
        if (manageLanguagesSection) {
            manageLanguagesSection.scrollIntoView({ behavior: 'smooth' });
        }
    }

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
    if (langWeeklyCtx) { new Chart(langWeeklyCtx, { type: 'bar', data: { labels: langBarData.labels, datasets: [{ label: 'New Languages', data: langBarData.data, backgroundColor: '#A78BFA', borderRadius: 4 }] }, options: barOptions }); }
});
</script>

</body>
</html>

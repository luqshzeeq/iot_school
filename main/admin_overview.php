<?php
session_start();

// --- 1. Admin Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// --- 2. DB Connection ---
include 'db_connection.php';

// --- Initialize messages ---
$error_message = null;
$success_message = null;

// --- 3. Date Calculation (Current Week Mon-Fri) ---
$today = time();
$monday_timestamp = strtotime('monday this week', $today);
$friday_timestamp = strtotime('friday this week', $today);
if (date('N', $today) >= 6) { // If Sat/Sun, show last week
    $monday_timestamp = strtotime('last monday', $today);
    $friday_timestamp = strtotime('last friday', $today);
}
$start_date_str = date('Y-m-d 00:00:00', $monday_timestamp);
$end_date_str = date('Y-m-d 23:59:59', $friday_timestamp);

// Date strings for queries on DATE columns (without time)
$start_date_query_format = date('Y-m-d', $monday_timestamp);
$end_date_query_format = date('Y-m-d', $friday_timestamp);


$week_labels = [];
$week_user_data = [];
$week_lang_data = [];
$week_reset_data = []; // Initialize for password reset data

$current_ts = $monday_timestamp;
while ($current_ts <= $friday_timestamp) {
    $date_key = date('Y-m-d', $current_ts);
    $week_labels[] = date('D (d M)', $current_ts);
    $week_user_data[$date_key] = 0;
    $week_lang_data[$date_key] = 0;
    $week_reset_data[$date_key] = 0;
    $current_ts = strtotime('+1 day', $current_ts);
}

// --- 4. Fetch Data ---
$stats = [
    'total_users' => 0, 'active_users' => 0, 'inactive_users' => 0,
    'total_languages' => 0,
    'total_devices' => 0, 'online_devices' => 0, 'offline_devices' => 0, 'error_devices' => 0,
    'total_password_resets_week' => 0
];

// Fetch user status counts
$user_result = $conn->query("SELECT status, COUNT(*) as count FROM users WHERE role = 'teacher' GROUP BY status");
if ($user_result) {
    while($row = $user_result->fetch_assoc()) {
        if ($row['status'] === 'active') $stats['active_users'] = $row['count'];
        else $stats['inactive_users'] = $row['count'];
        $stats['total_users'] += $row['count'];
    }
}

// Fetch total languages count
$lang_result = $conn->query("SELECT COUNT(*) as count FROM languages");
if ($lang_result) $stats['total_languages'] = $lang_result->fetch_assoc()['count'];

// Fetch device status counts
$device_result = $conn->query("SELECT status, COUNT(*) as count FROM device_status GROUP BY status");
if ($device_result) {
    while($row = $device_result->fetch_assoc()) {
        $status_key = strtolower($row['status']) . '_devices';
        if (array_key_exists($status_key, $stats)) $stats[$status_key] = $row['count'];
        $stats['total_devices'] += $row['count'];
    }
}

// Fetch new teacher registrations for the week
$stmt_users = $conn->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count FROM users WHERE role = 'teacher' AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at)");
$stmt_users->bind_param("ss", $start_date_str, $end_date_str);
$stmt_users->execute();
$result_users_week = $stmt_users->get_result();
if ($result_users_week) {
    while($row = $result_users_week->fetch_assoc()) {
        if (array_key_exists($row['reg_date'], $week_user_data)) $week_user_data[$row['reg_date']] = $row['count'];
    }
}
$stmt_users->close();

// Fetch languages added for the week
$languages_by_teacher_week = [];
$prerequisites_met = true;
$stmt_langs = $conn->prepare("SELECT l.language_name, l.created_at, u.username FROM languages l LEFT JOIN users u ON l.created_by = u.id WHERE l.created_at BETWEEN ? AND ? ORDER BY l.created_at DESC");
if ($stmt_langs) {
    $stmt_langs->bind_param("ss", $start_date_str, $end_date_str);
    if ($stmt_langs->execute()) {
        $result_langs_week = $stmt_langs->get_result();
        while($row = $result_langs_week->fetch_assoc()) {
            $languages_by_teacher_week[] = $row;
            $date_key = date('Y-m-d', strtotime($row['created_at']));
            if (array_key_exists($date_key, $week_lang_data)) { $week_lang_data[$date_key]++; }
        }
    } else {
        $error_message = "Could not fetch weekly language data (Execute Error: " . htmlspecialchars($stmt_langs->error) . "). Ensure prerequisites are met.";
        $prerequisites_met = false;
    }
    $stmt_langs->close();
} else {
    $error_message = "Could not prepare statement for weekly language data. Ensure 'languages' table has 'created_at'/'created_by' and 'Add Language' logic is updated. (DB Error: ".htmlspecialchars($conn->error).")";
    $prerequisites_met = false;
}

// Fetch Password Reset Data for the week
$recent_password_resets = [];
$password_reset_prerequisites_met = true;

$stmt_resets_week = $conn->prepare("SELECT DATE(expires_at) as reset_date, COUNT(*) as count FROM password_resets WHERE expires_at BETWEEN ? AND ? GROUP BY DATE(expires_at)");
if ($stmt_resets_week) {
    $stmt_resets_week->bind_param("ss", $start_date_str, $end_date_str);
    if ($stmt_resets_week->execute()) {
        $result_resets_week = $stmt_resets_week->get_result();
        if ($result_resets_week) {
            while($row = $result_resets_week->fetch_assoc()) {
                if (array_key_exists($row['reset_date'], $week_reset_data)) {
                    $week_reset_data[$row['reset_date']] = $row['count'];
                }
            }
            $stats['total_password_resets_week'] = array_sum($week_reset_data);
        }
    } else {
        $error_message = ($error_message ? $error_message . "<br>" : "") . "Could not fetch weekly password reset data (Execute Error: " . htmlspecialchars($stmt_resets_week->error) . ").";
        $password_reset_prerequisites_met = false;
    }
    $stmt_resets_week->close();
} else {
    $error_message = ($error_message ? $error_message . "<br>" : "") . "Could not prepare statement for weekly password reset data. (DB Error: ".htmlspecialchars($conn->error).")";
    $password_reset_prerequisites_met = false;
}

$stmt_recent_resets = $conn->prepare("SELECT pr.expires_at, u.username, pr.email FROM password_resets pr JOIN users u ON pr.email = u.email WHERE pr.expires_at BETWEEN ? AND ? ORDER BY pr.expires_at DESC LIMIT 10");
if ($stmt_recent_resets) {
    $stmt_recent_resets->bind_param("ss", $start_date_str, $end_date_str);
    if ($stmt_recent_resets->execute()) {
        $result_recent_resets = $stmt_recent_resets->get_result();
        if ($result_recent_resets) {
            while($row = $result_recent_resets->fetch_assoc()) {
                $recent_password_resets[] = $row;
            }
        }
    } else {
        $error_message = ($error_message ? $error_message . "<br>" : "") . "Could not fetch recent password reset data (Execute Error: " . htmlspecialchars($stmt_recent_resets->error) . ").";
        $password_reset_prerequisites_met = false;
    }
    $stmt_recent_resets->close();
} else {
    $error_message = ($error_message ? $error_message . "<br>" : "") . "Could not prepare statement for recent password reset data. (DB Error: ".htmlspecialchars($conn->error).")";
    $password_reset_prerequisites_met = false;
}

// --- NEW SECTION START: Fetch Teacher Daily Language Settings ---
$teacher_daily_lang_settings = [];
$teacher_daily_lang_prereq_met = true;

$sql_daily_langs = "SELECT tdl.setting_date, u.username AS teacher_username, l.language_name
                    FROM teacher_daily_languages tdl
                    JOIN users u ON tdl.teacher_id = u.id
                    JOIN languages l ON tdl.language_id = l.id
                    WHERE tdl.setting_date BETWEEN ? AND ?
                    ORDER BY tdl.setting_date DESC, u.username ASC";

$stmt_daily_langs = $conn->prepare($sql_daily_langs);

if ($stmt_daily_langs) {
    // Use date-only strings for binding with DATE column
    $stmt_daily_langs->bind_param("ss", $start_date_query_format, $end_date_query_format);
    if ($stmt_daily_langs->execute()) {
        $result_daily_langs = $stmt_daily_langs->get_result();
        if ($result_daily_langs) {
            while ($row = $result_daily_langs->fetch_assoc()) {
                $teacher_daily_lang_settings[] = $row;
            }
        }
    } else {
        $error_message = ($error_message ? $error_message . "<br>" : "") . "Could not fetch daily teacher language settings (Execute Error: " . htmlspecialchars($stmt_daily_langs->error) . ").";
        $teacher_daily_lang_prereq_met = false;
    }
    $stmt_daily_langs->close();
} else {
    $error_message = ($error_message ? $error_message . "<br>" : "") . "Could not prepare statement for daily teacher language settings. Ensure 'teacher_daily_languages' (with teacher_id, language_id, setting_date), 'users' (with id, username), and 'languages' (with id, language_name) tables exist. (DB Error: ".htmlspecialchars($conn->error).")";
    $teacher_daily_lang_prereq_met = false;
}
// --- NEW SECTION END ---


// --- 5. Prepare Data for JS ---
$user_pie_data = ['labels' => ['Active', 'Inactive'], 'data' => [$stats['active_users'], $stats['inactive_users']]];
$device_pie_data = ['labels' => ['Online', 'Offline', 'Error'], 'data' => [$stats['online_devices'], $stats['offline_devices'], $stats['error_devices']]];
$user_bar_data = ['labels' => $week_labels, 'data' => array_values($week_user_data)];
$lang_bar_data = ['labels' => $week_labels, 'data' => array_values($week_lang_data)];
$reset_bar_data = ['labels' => $week_labels, 'data' => array_values($week_reset_data)];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" /> <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Dashboard Overview | Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; } ::-webkit-scrollbar-track { background: #1f2937; } ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; } ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .sidebar { transition: width 0.3s ease-in-out; } .btn-icon i { margin-right: 0.5rem; } .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; transition: opacity 0.5s ease-out; } .flash-message.fade-out { opacity: 0; }
        .flash-success { background-color: #d1fae5; border-left: 4px solid #10b981; color: #065f46; } .flash-error { background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; }
        #sidebar { height: 100vh; } @media (max-width: 1023px) { #sidebar { position: fixed; top: 0; left: 0; z-index: 40; } }
        .stat-card { background-color: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); display: flex; align-items: center; }
        .stat-card i { font-size: 1.75rem; color: #fff; padding: 0.75rem; border-radius: 0.375rem; margin-right: 1rem; width: 50px; height: 50px; display: inline-flex; justify-content: center; align-items: center;}
        .stat-card .value { font-size: 1.875rem; font-weight: 700; color: #111827; } .stat-card .label { font-size: 0.875rem; color: #6b7280; }
        .chart-container { background-color: white; padding: 1.5rem; border-radius: 0.5rem; box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06); }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-md">
            <div class="container mx-auto px-6 py-3 flex items-center justify-between">
                <div class="flex items-center">
                    <button id="mobileSidebarToggle" class="text-gray-600 focus:outline-none lg:hidden mr-4"> <i class="fas fa-bars text-xl"></i> </button>
                    <h1 class="text-xl font-semibold text-gray-700 hidden lg:block">Dashboard Overview</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600 text-sm">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    <button class="block h-10 w-10 rounded-full overflow-hidden border-2"><img class="h-full w-full object-cover" src="https://placehold.co/40x40/718096/E2E8F0?text=A" alt="Avatar" /></button>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="container mx-auto">
                <?php if ($error_message): ?>
                <div id="errorMessage" class="flash-message flash-error mb-6">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i>
                        <div>
                            <p class="font-bold">Notice:</p>
                            <p class="text-sm"><?php echo $error_message; // Already htmlspecialchars in PHP logic for DB errors ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card"><i class="fas fa-users bg-blue-500"></i><div><div class="value"><?php echo $stats['total_users']; ?></div><div class="label">Total Teachers</div></div></div>
                    <div class="stat-card"><i class="fas fa-desktop bg-green-500"></i><div><div class="value"><?php echo $stats['total_devices']; ?></div><div class="label">Total Devices</div></div></div>
                    <div class="stat-card"><i class="fas fa-language bg-purple-500"></i><div><div class="value"><?php echo $stats['total_languages']; ?></div><div class="label">Total Languages</div></div></div>
                    <div class="stat-card"><i class="fas fa-key bg-red-500"></i><div><div class="value"><?php echo $stats['total_password_resets_week']; ?></div><div class="label">Resets This Week</div></div></div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="chart-container"><h3 class="text-lg font-semibold text-gray-800 mb-4">User Status (Teachers)</h3><canvas id="userStatusChart"></canvas></div>
                    <div class="chart-container"><h3 class="text-lg font-semibold text-gray-800 mb-4">Device Status</h3><canvas id="deviceStatusChart"></canvas></div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="chart-container"><h3 class="text-lg font-semibold text-gray-800 mb-4">New Teacher Registrations (This Week)</h3><canvas id="userWeeklyChart"></canvas></div>
                    <div class="chart-container"><h3 class="text-lg font-semibold text-gray-800 mb-4">Languages Added (This Week)</h3><canvas id="langWeeklyChart"></canvas></div>
                </div>

                <div class="grid grid-cols-1 gap-6 mb-8">
                    <div class="chart-container"><h3 class="text-lg font-semibold text-gray-800 mb-4">Password Resets (This Week)</h3><canvas id="passwordResetWeeklyChart"></canvas></div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Language Setups (This Week: Mon-Fri)</h3>
                    <?php if ($prerequisites_met): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Language</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Added By</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Added</th></tr></thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($languages_by_teacher_week)): ?>
                                        <?php foreach ($languages_by_teacher_week as $lang_entry): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($lang_entry['language_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($lang_entry['username'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('M d, Y', strtotime($lang_entry['created_at']))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No languages added this week (Mon-Fri).</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600"><i class="fas fa-wrench mr-2"></i>This feature requires database schema changes (languages table with 'created_at', 'created_by') and updates to the 'Add Language' functionality. Please check the prerequisite notice if displayed above.</p>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-lg mb-8">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Recent Password Reset Requests (This Week: Mon-Fri)</h3>
                    <?php if ($password_reset_prerequisites_met): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Request Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($recent_password_resets)): ?>
                                        <?php foreach ($recent_password_resets as $reset_entry): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($reset_entry['username'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($reset_entry['email'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($reset_entry['expires_at']))); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No password reset requests recorded this week (Mon-Fri).</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                         <p class="text-gray-600"><i class="fas fa-wrench mr-2"></i>This feature requires the `password_resets` table to have `email` and `expires_at` columns, and the `users` table to have an `email` column for linking. Please check the notice above if displayed.</p>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Daily Language Settings by Teacher (This Week: Mon-Fri)</h3>
                    <?php if ($teacher_daily_lang_prereq_met): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Set</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Teacher</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Language</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php if (!empty($teacher_daily_lang_settings)): ?>
                                        <?php foreach ($teacher_daily_lang_settings as $setting_entry): ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('M d, Y', strtotime($setting_entry['setting_date']))); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($setting_entry['teacher_username'] ?? 'N/A'); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($setting_entry['language_name'] ?? 'N/A'); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr><td colspan="3" class="px-6 py-4 text-center text-sm text-gray-500">No daily language settings recorded for this week (Mon-Fri).</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p class="text-gray-600"><i class="fas fa-wrench mr-2"></i>This feature requires the `teacher_daily_languages` table (with `teacher_id`, `language_id`, `setting_date` columns), the `users` table (with `id`, `username`), and the `languages` table (with `id`, `language_name`). Please check any error notices displayed above.</p>
                    <?php endif; ?>
                </div>
                </div>
        </main>
    </div>
</div>

<script>
    const userPieData = <?php echo json_encode($user_pie_data); ?>;
    const devicePieData = <?php echo json_encode($device_pie_data); ?>;
    const userBarData = <?php echo json_encode($user_bar_data); ?>;
    const langBarData = <?php echo json_encode($lang_bar_data); ?>;
    const resetBarData = <?php echo json_encode($reset_bar_data); ?>;

    document.addEventListener('DOMContentLoaded', () => {
        const commonOptions = { responsive: true, plugins: { legend: { position: 'top' } } };
        const barOptions = { ...commonOptions, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } };

        if (document.getElementById('userStatusChart') && userPieData.data.some(d => d > 0)) {
            new Chart(document.getElementById('userStatusChart'), { type: 'pie', data: { labels: userPieData.labels, datasets: [{ data: userPieData.data, backgroundColor: ['#34D399', '#EF4444'], hoverOffset: 4 }] }, options: commonOptions });
        }
        if (document.getElementById('deviceStatusChart') && devicePieData.data.some(d => d > 0)) {
            new Chart(document.getElementById('deviceStatusChart'), { type: 'doughnut', data: { labels: devicePieData.labels, datasets: [{ data: devicePieData.data, backgroundColor: ['#10B981', '#F87171', '#F59E0B'], hoverOffset: 4 }] }, options: commonOptions });
        }
        if (document.getElementById('userWeeklyChart') && userBarData.data.some(d => d > 0)) {
            new Chart(document.getElementById('userWeeklyChart'), { type: 'bar', data: { labels: userBarData.labels, datasets: [{ label: 'New Users', data: userBarData.data, backgroundColor: '#60A5FA' }] }, options: barOptions });
        }
        if (document.getElementById('langWeeklyChart') && langBarData.data.some(d => d > 0)) {
            new Chart(document.getElementById('langWeeklyChart'), { type: 'bar', data: { labels: langBarData.labels, datasets: [{ label: 'New Languages', data: langBarData.data, backgroundColor: '#A78BFA' }] }, options: barOptions });
        }
        if (document.getElementById('passwordResetWeeklyChart') && resetBarData.data.some(d => d > 0)) {
            new Chart(document.getElementById('passwordResetWeeklyChart'), { type: 'bar', data: { labels: resetBarData.labels, datasets: [{ label: 'Password Resets', data: resetBarData.data, backgroundColor: '#EC4899' }] }, options: barOptions });
        }

        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle'); // Assuming this ID exists in sidebar.php
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebarTexts = sidebar ? sidebar.querySelectorAll('.sidebar-text') : []; // Check if sidebar exists

        function toggleSidebarDesktop() {
            if (!sidebar || !sidebarToggle) return;
            const toggleIcon = sidebarToggle.querySelector('i');
            sidebar.classList.toggle('w-64'); sidebar.classList.toggle('w-20');
            const isCollapsed = sidebar.classList.contains('w-20');
            sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
            if (toggleIcon) {
                toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
                toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
            }
            sidebarToggle.title = isCollapsed ? 'Expand' : 'Collapse';
        }

        if (sidebarToggle && sidebar) {
            sidebarToggle.addEventListener('click', toggleSidebarDesktop);
            // Initial state update for desktop toggle icon and text
            const isCollapsed = sidebar.classList.contains('w-20');
            const toggleIcon = sidebarToggle.querySelector('i');
            sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
            if (toggleIcon) {
                toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
                toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
            }
            sidebarToggle.title = isCollapsed ? 'Expand' : 'Collapse';
        }

        if (mobileSidebarToggle && sidebar) {
            sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto');
            if (!sidebar.classList.contains('w-64') && !sidebar.classList.contains('w-20')) { // If not already set (e.g. by desktop)
                 sidebar.classList.add('w-64'); // Default to expanded on mobile toggle
            }
            sidebar.classList.add('-translate-x-full');


            mobileSidebarToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('translate-x-0');
                // Ensure sidebar is expanded when shown on mobile
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
        const successAlert = document.getElementById('successMessage'); // Assuming you might add success messages
        const errorAlert = document.getElementById('errorMessage');
        function autoHide(el, timeout = 4000) { if(el) { setTimeout(() => { el.classList.add('fade-out'); setTimeout(() => el.style.display = 'none', 500); }, timeout); } }
        autoHide(successAlert);
        // autoHide(errorAlert, 8000); // Optionally auto-hide error messages after a longer period

    });
</script>

</body>
</html>
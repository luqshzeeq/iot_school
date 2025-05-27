<?php
session_start();

// --- 1. Admin Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// --- 2. DB Connection ---
include 'db_connection.php';

// --- Initialize messages --- // <<<--- FIX: Initialize $error_message here
$error_message = null;
$success_message = null; // For consistency, though not directly causing this warning

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

$week_labels = []; $week_user_data = []; $week_lang_data = [];
$current_ts = $monday_timestamp;
while ($current_ts <= $friday_timestamp) {
    $date_key = date('Y-m-d', $current_ts);
    $week_labels[] = date('D (d M)', $current_ts);
    $week_user_data[$date_key] = 0; $week_lang_data[$date_key] = 0;
    $current_ts = strtotime('+1 day', $current_ts);
}

// --- 4. Fetch Data ---
$stats = [ 'total_users' => 0, 'active_users' => 0, 'inactive_users' => 0, 'total_languages' => 0, 'total_devices' => 0, 'online_devices' => 0, 'offline_devices' => 0, 'error_devices' => 0, ];
$user_result = $conn->query("SELECT status, COUNT(*) as count FROM users WHERE role = 'teacher' GROUP BY status");
if ($user_result) { while($row = $user_result->fetch_assoc()) { if ($row['status'] === 'active') $stats['active_users'] = $row['count']; else $stats['inactive_users'] = $row['count']; $stats['total_users'] += $row['count']; } }
$lang_result = $conn->query("SELECT COUNT(*) as count FROM languages");
if ($lang_result) $stats['total_languages'] = $lang_result->fetch_assoc()['count'];
$device_result = $conn->query("SELECT status, COUNT(*) as count FROM device_status GROUP BY status");
if ($device_result) { while($row = $device_result->fetch_assoc()) { $status_key = strtolower($row['status']) . '_devices'; if (array_key_exists($status_key, $stats)) $stats[$status_key] = $row['count']; $stats['total_devices'] += $row['count']; } }

$stmt_users = $conn->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count FROM users WHERE role = 'teacher' AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at)");
$stmt_users->bind_param("ss", $start_date_str, $end_date_str); $stmt_users->execute(); $result_users_week = $stmt_users->get_result();
if ($result_users_week) { while($row = $result_users_week->fetch_assoc()) { if (array_key_exists($row['reg_date'], $week_user_data)) $week_user_data[$row['reg_date']] = $row['count']; } }
$stmt_users->close();

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

// --- 5. Prepare Data for JS ---
$user_pie_data = ['labels' => ['Active', 'Inactive'], 'data' => [$stats['active_users'], $stats['inactive_users']]];
$device_pie_data = ['labels' => ['Online', 'Offline', 'Error'], 'data' => [$stats['online_devices'], $stats['offline_devices'], $stats['error_devices']]];
$user_bar_data = ['labels' => $week_labels, 'data' => array_values($week_user_data)];
$lang_bar_data = ['labels' => $week_labels, 'data' => array_values($week_lang_data)];

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
                <?php if ($error_message && !$prerequisites_met): ?>
                <div id="errorMessage" class="flash-message flash-error mb-6">
                    <div class="flex">
                        <i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i>
                        <div>
                            <p class="font-bold">Prerequisite or Data Fetching Notice</p>
                            <p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="stat-card"><i class="fas fa-users bg-blue-500"></i><div><div class="value"><?php echo $stats['total_users']; ?></div><div class="label">Total Teachers</div></div></div>
                    <div class="stat-card"><i class="fas fa-desktop bg-green-500"></i><div><div class="value"><?php echo $stats['total_devices']; ?></div><div class="label">Total Devices</div></div></div>
                    <div class="stat-card"><i class="fas fa-language bg-purple-500"></i><div><div class="value"><?php echo $stats['total_languages']; ?></div><div class="label">Total Languages</div></div></div>
                    <div class="stat-card"><i class="fas fa-plug bg-orange-500"></i><div><div class="value"><?php echo $stats['online_devices']; ?></div><div class="label">Devices Online</div></div></div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="chart-container"><h3 class="text-lg font-semibold text-gray-800 mb-4">User Status (Teachers)</h3><canvas id="userStatusChart"></canvas></div>
                    <div class="chart-container"><h3 class="text-lg font-semibold text-gray-800 mb-4">Device Status</h3><canvas id="deviceStatusChart"></canvas></div>
                </div>

                 <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
                    <div class="chart-container"><h3 class="text-lg font-semibold text-gray-800 mb-4">New Teacher Registrations (This Week)</h3><canvas id="userWeeklyChart"></canvas></div>
                    <div class="chart-container"><h3 class="text-lg font-semibold text-gray-800 mb-4">Languages Added (This Week)</h3><canvas id="langWeeklyChart"></canvas></div>
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
                        <p class="text-gray-600"><i class="fas fa-wrench mr-2"></i>This feature requires database schema changes and updates to the 'Add Language' functionality. Please check the prerequisite notice if displayed above.</p>
                    <?php endif; ?>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Password Resets</h3>
                    <p class="text-gray-600"><i class="fas fa-info-circle mr-2"></i>Password reset tracking requires a dedicated logging table. Once implemented, data on reset frequency can be displayed here.</p>
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

    document.addEventListener('DOMContentLoaded', () => {
        const commonOptions = { responsive: true, plugins: { legend: { position: 'top' } } };
        const barOptions = { ...commonOptions, scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } } };

        new Chart(document.getElementById('userStatusChart'), { type: 'pie', data: { labels: userPieData.labels, datasets: [{ data: userPieData.data, backgroundColor: ['#34D399', '#EF4444'], hoverOffset: 4 }] }, options: commonOptions });
        new Chart(document.getElementById('deviceStatusChart'), { type: 'doughnut', data: { labels: devicePieData.labels, datasets: [{ data: devicePieData.data, backgroundColor: ['#10B981', '#F87171', '#F59E0B'], hoverOffset: 4 }] }, options: commonOptions });
        new Chart(document.getElementById('userWeeklyChart'), { type: 'bar', data: { labels: userBarData.labels, datasets: [{ label: 'New Users', data: userBarData.data, backgroundColor: '#60A5FA' }] }, options: barOptions });
        new Chart(document.getElementById('langWeeklyChart'), { type: 'bar', data: { labels: langBarData.labels, datasets: [{ label: 'New Languages', data: langBarData.data, backgroundColor: '#A78BFA' }] }, options: barOptions });

        // --- Sidebar JS ---
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebarTexts = document.querySelectorAll('.sidebar-text');

        function toggleSidebarDesktop() {
            if (!sidebar || !sidebarToggle) return;
            const toggleIcon = sidebarToggle.querySelector('i');
            sidebar.classList.toggle('w-64'); sidebar.classList.toggle('w-20');
            const isCollapsed = sidebar.classList.contains('w-20');
            sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
            toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
            toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
            sidebarToggle.title = isCollapsed ? 'Expand' : 'Collapse';
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebarDesktop);
            const isCollapsed = sidebar.classList.contains('w-20'); const toggleIcon = sidebarToggle.querySelector('i');
            sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
            toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed); toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
            sidebarToggle.title = isCollapsed ? 'Expand' : 'Collapse';
        }

        if (mobileSidebarToggle && sidebar) {
            sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
            mobileSidebarToggle.addEventListener('click', (e) => {
                e.stopPropagation(); sidebar.classList.toggle('-translate-x-full'); sidebar.classList.toggle('translate-x-0');
                sidebar.classList.add('w-64'); sidebar.classList.remove('w-20'); sidebarTexts.forEach(text => text.classList.remove('hidden'));
            });
            document.addEventListener('click', (e) => {
                if (sidebar && !sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target) && sidebar.classList.contains('translate-x-0') && window.innerWidth < 1024) {
                    sidebar.classList.add('-translate-x-full'); sidebar.classList.remove('translate-x-0');
                }
            });
        }
        // --- Flash Messages ---
        const successAlert = document.getElementById('successMessage');
        // Note: Error alert for prerequisites is displayed directly in HTML, no auto-hide here for that one.
        // You might want separate handling if general errors can also appear here.
        function autoHide(el) { if(el) { setTimeout(() => { el.classList.add('fade-out'); setTimeout(() => el.style.display = 'none', 500); }, 4000); } }
        autoHide(successAlert); // Only auto-hide success message
    });
</script>

</body>
</html>
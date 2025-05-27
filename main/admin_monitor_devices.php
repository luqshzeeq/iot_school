<?php
session_start();

// --- 1. Admin Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Adjust path if needed
    exit();
}

// --- 2. DB Connection ---
include 'db_connection.php'; // Ensure this file exists

// --- 3. Search & Filter Query Handling ---
$search_query = trim($_GET['search_query'] ?? '');
$status_filter = trim($_GET['status_filter'] ?? '');

// --- 4. Messages ---
$error_message = null;
$success_message = null;

if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// --- 5. Fetch Device Statuses (with Search & Filter) ---
$sql = "SELECT id, device_id, status, last_checked FROM device_status";
$sql_params = [];
$sql_types = '';
$where_clauses = [];

if ($search_query !== '') {
    $where_clauses[] = "device_id LIKE ?";
    $search_like = "%" . $search_query . "%";
    $sql_params[] = $search_like;
    $sql_types .= "s";
}

if ($status_filter !== '' && in_array($status_filter, ['online', 'offline', 'error'])) {
    $where_clauses[] = "status = ?";
    $sql_params[] = $status_filter;
    $sql_types .= "s";
}

if (!empty($where_clauses)) {
    $sql .= " WHERE " . implode(" AND ", $where_clauses);
}

$sql .= " ORDER BY last_checked DESC";

$stmt_all = $conn->prepare($sql);
$result_all_devices = false;

if ($stmt_all) {
    if (!empty($sql_params)) {
        $stmt_all->bind_param($sql_types, ...$sql_params);
    }
    if ($stmt_all->execute()) {
        $result_all_devices = $stmt_all->get_result();
    } else {
        $error_message = "Error executing device fetch: " . htmlspecialchars($stmt_all->error);
    }
    $stmt_all->close();
} else {
    $error_message = "Error preparing statement for device fetch: " . htmlspecialchars($conn->error);
}

if (!$result_all_devices && $error_message === null) {
    $error_message = "Error fetching device statuses: " . htmlspecialchars($conn->error);
}

// --- 6. Helper for Time Ago ---
function time_ago($timestamp) {
    if (empty($timestamp)) return "Never";
    $last_checked_time = strtotime($timestamp);
    $now = time();
    $diff = $now - $last_checked_time;

    if ($diff < 2) return "just now";
    if ($diff < 60) return $diff . " sec ago";
    if ($diff < 3600) return floor($diff / 60) . " min ago";
    if ($diff < 86400) return floor($diff / 3600) . " hr ago";
    return floor($diff / 86400) . " days ago";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Monitor Devices | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #2d3748; }
        ::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #718096; }
        .sidebar { transition: width 0.3s ease-in-out; }
        .btn-icon { display: inline-flex; align-items: center; justify-content: center; }
        .btn-icon i { margin-right: 0.5rem; }
        .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: opacity 0.5s ease-out; }
        .flash-message.fade-out { opacity: 0; }
        .flash-success { background-color: #d1fae5; border-left-width: 4px; border-color: #10b981; color: #065f46; }
        .flash-error { background-color: #fee2e2; border-left-width: 4px; border-color: #ef4444; color: #991b1b; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; // Include your sidebar here ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-md">
            <div class="container mx-auto px-6 py-3 flex items-center justify-between">
                <div class="flex items-center">
                    <button id="mobileSidebarToggle" class="text-gray-600 focus:outline-none lg:hidden mr-4">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600 text-sm">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    <div class="relative">
                        <button class="block h-10 w-10 rounded-full overflow-hidden border-2 border-gray-300 focus:outline-none">
                            <img class="h-full w-full object-cover" src="https://placehold.co/40x40/718096/E2E8F0?text=A" alt="User avatar" />
                        </button>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="container mx-auto">
                <?php if ($success_message): ?>
                <div id="successMessage" class="flash-message flash-success" role="alert">
                    <div class="flex"><div class="py-1"><i class="fas fa-check-circle fa-lg mr-3"></i></div><div><p class="font-bold">Success</p><p class="text-sm"><?php echo htmlspecialchars($success_message); ?></p></div></div>
                </div>
                <?php endif; ?>
                <?php if ($error_message && $error_message !== "Error fetching device statuses: "): // Only show real errors ?>
                <div id="errorMessage" class="flash-message flash-error" role="alert">
                    <div class="flex"><div class="py-1"><i class="fas fa-exclamation-triangle fa-lg mr-3"></i></div><div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p></div></div>
                </div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Device Status Overview</h2>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-md border border-gray-200">
                           <div class="md:col-span-1">
                                <label for="search_query_input" class="block text-sm font-medium text-gray-700">Search Device ID:</label>
                                <div class="relative mt-1">
                                     <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                         <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                    <input
                                        type="text"
                                        name="search_query"
                                        id="search_query_input"
                                        placeholder="Enter Device ID..."
                                        class="block w-full pl-10 px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        value="<?php echo htmlspecialchars($search_query); ?>"
                                    >
                                </div>
                           </div>
                           <div class="md:col-span-1">
                                <label for="status_filter_select" class="block text-sm font-medium text-gray-700">Status:</label>
                                <select 
                                    name="status_filter" 
                                    id="status_filter_select"
                                    class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="online" <?php echo ($status_filter === 'online') ? 'selected' : ''; ?>>Online</option>
                                    <option value="offline" <?php echo ($status_filter === 'offline') ? 'selected' : ''; ?>>Offline</option>
                                    <option value="error" <?php echo ($status_filter === 'error') ? 'selected' : ''; ?>>Error</option>
                                    </select>
                           </div>
                           <div class="md:col-span-1 flex items-end space-x-3">
                                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm btn-icon justify-center">
                                    <i class="fas fa-filter"></i>Apply
                                </button>
                                <a href="admin_monitor_devices.php" class="flex-1 text-center text-gray-600 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-300 px-4 py-2 rounded-md text-sm btn-icon justify-center">
                                    <i class="fas fa-times"></i>Clear
                                </a>
                           </div>
                        </div>
                    </form>
                    <?php if ($result_all_devices && $result_all_devices->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">DB ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Device ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Checked</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($device = $result_all_devices->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($device['id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 font-mono"><?php echo htmlspecialchars($device['device_id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <?php
                                                    $status_class = 'bg-gray-100 text-gray-800';
                                                    $status_icon = '<i class="fas fa-question-circle text-gray-500 mr-2"></i>';
                                                    switch (strtolower($device['status'])) {
                                                        case 'online':
                                                            $status_class = 'bg-green-100 text-green-800';
                                                            $status_icon = '<i class="fas fa-circle text-green-500 mr-2"></i>';
                                                            break;
                                                        case 'offline':
                                                            $status_class = 'bg-red-100 text-red-800';
                                                            $status_icon = '<i class="fas fa-circle text-red-500 mr-2"></i>';
                                                            break;
                                                        case 'error':
                                                            $status_class = 'bg-yellow-100 text-yellow-800';
                                                            $status_icon = '<i class="fas fa-exclamation-triangle text-yellow-500 mr-2"></i>';
                                                            break;
                                                    }
                                                ?>
                                                <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                    <?php echo $status_icon; ?>
                                                    <?php echo htmlspecialchars(ucfirst($device['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php
                                                    $formatted_time = !empty($device['last_checked']) ? htmlspecialchars(date('M d, Y H:i:s', strtotime($device['last_checked']))) : 'Never';
                                                    $relative_time = time_ago($device['last_checked']);
                                                    echo $formatted_time;
                                                    if ($relative_time !== 'Never') {
                                                        echo " <span class='text-xs text-gray-400'>(" . $relative_time . ")</span>";
                                                    }
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-satellite-dish fa-3x text-gray-400 mb-3"></i>
                             <p class="text-gray-500">
                                <?php echo (!empty($search_query) || !empty($status_filter)) ? "No devices found matching your criteria." : "No device statuses found in the `device_status` table."; ?>
                             </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // --- JavaScript for Sidebar and Flash Messages ---
    const sidebar = document.getElementById('sidebar');
    // const sidebarToggle = document.getElementById('sidebarToggle'); // Optional Desktop Toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebarTexts = document.querySelectorAll('.sidebar-text'); // Ensure sidebar elements have this class

    if (mobileSidebarToggle && sidebar) {
        mobileSidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0');
            if (!sidebar.classList.contains('-translate-x-full')) {
                 sidebar.classList.add('w-64');
                 sidebar.classList.remove('w-20');
                 sidebarTexts.forEach(text => text.classList.remove('hidden'));
            }
        });
        sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
         if (!sidebar.classList.contains('lg:w-64') && !sidebar.classList.contains('lg:w-20')) {
             sidebar.classList.add('lg:w-64');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const successAlert = document.getElementById('successMessage');
        const errorAlert = document.getElementById('errorMessage');
        const autoHideDelay = 4000;

        function autoHide(alertElement) {
            if (alertElement) {
                setTimeout(() => {
                    alertElement.classList.add('fade-out');
                    setTimeout(() => { alertElement.style.display = 'none'; }, 500);
                }, autoHideDelay);
            }
        }

        autoHide(successAlert);
        autoHide(errorAlert);
    });
</script>

</body>
</html>
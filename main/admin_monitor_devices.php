<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- FOR DEBUGGING: Enable all error reporting. REMOVE THIS ON PRODUCTION. ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --- END DEBUGGING SETTINGS ---

// --- 1. Admin Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// --- 2. DB Connection ---
include 'db_connection.php'; // Ensure this file exists

// Verify database connection (improved check to exit if $conn is null)
if (!isset($conn) || !$conn) { // Check if $conn is set and not false (in case include fails)
    $_SESSION['error_message'] = "Database connection failed.";
    // Redirect to self to display error, preserving GET params if any
    header("Location: admin_monitor_devices.php" . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit();
}

// ... (rest of the admin_monitor_devices.php code remains the same) ...

// --- 3. Search & Filter Query Handling ---
$search_query = trim($_GET['search_query'] ?? '');
$status_filter = trim($_GET['status_filter'] ?? '');

// --- 4. Messages ---
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']); // Clear messages after retrieving

// --- 5. Fetch Device Statuses ---
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
$result_all_devices = false; // Initialize to false

if ($stmt_all) {
    if (!empty($sql_params)) {
        $stmt_all->bind_param($sql_types, ...$sql_params);
    }
    if ($stmt_all->execute()) {
        $result_all_devices = $stmt_all->get_result();
    } else {
        $error_message = "Error executing fetch devices: " . $stmt_all->error;
    }
    $stmt_all->close();
} else {
    $error_message = "Error preparing fetch devices statement: " . $conn->error;
}

// --- 6. Helper for Time Ago ---
function time_ago($timestamp) {
    if (empty($timestamp)) return "Never";
    $time = strtotime($timestamp);
    $now = time();
    $diff = $now - $time;
    if ($diff < 2) return "just now";
    if ($diff < 60) return $diff . " sec ago";
    if ($diff < 3600) return floor($diff / 60) . " min ago";
    if ($diff < 86400) return floor($diff / 3600) . " hr ago";
    return floor($diff / 86400) . " days ago";
}

// --- Include the Standardized Header ---
include 'header.php'; // This file will provide the <DOCTYPE html>, <html>, <head>, <body>, and the initial layout divs.
?>

<style>
    /* ... (Your existing CSS) ... */
</style>

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

        <div class="bg-white p-6 rounded-lg shadow-lg">
            <h2 class="text-xl font-semibold text-gray-700 mb-4">Device Status Overview</h2>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 bg-gray-50 p-4 rounded-md border border-gray-200">
                    <div class="md:col-span-1">
                        <label for="search_query_input" class="block text-sm font-medium text-gray-700">Search Device ID:</label>
                        <div class="relative mt-1">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                            <input type="text" name="search_query" id="search_query_input" placeholder="Enter Device ID..." class="block w-full pl-10 px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                    </div>
                    <div class="md:col-span-1">
                        <label for="status_filter_select" class="block text-sm font-medium text-gray-700">Status:</label>
                        <select name="status_filter" id="status_filter_select" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                            <option value="">All Statuses</option>
                            <option value="online" <?php echo ($status_filter === 'online') ? 'selected' : ''; ?>>Online</option>
                            <option value="offline" <?php echo ($status_filter === 'offline') ? 'selected' : ''; ?>>Offline</option>
                            <option value="error" <?php echo ($status_filter === 'error') ? 'selected' : ''; ?>>Error</option>
                        </select>
                    </div>
                    <div class="md:col-span-1">
                        <label class="block text-sm font-medium text-gray-700">&nbsp;</label>
                        <div class="flex items-center space-x-3 mt-1">
                            <button type="submit" class="btn-icon flex-1 justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm"><i class="fas fa-filter"></i>Apply</button>
                            <a href="admin_monitor_devices.php" class="btn-icon flex-1 justify-center text-center text-gray-600 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-300 py-2 px-4 rounded-md text-sm shadow-sm"><i class="fas fa-times"></i>Clear</a>
                        </div>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto">
                <?php if ($result_all_devices && $result_all_devices->num_rows > 0): ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">DB ID</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Device ID</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Last Checked</th></tr></thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php while ($device = $result_all_devices->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $device['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono"><?php echo htmlspecialchars($device['device_id']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                                        <?php
                                            $status_class = 'bg-gray-100 text-gray-800'; $status_icon = 'fa-question-circle text-gray-500';
                                            switch (strtolower($device['status'])) {
                                                case 'online': $status_class = 'bg-green-100 text-green-800'; $status_icon = 'fa-circle text-green-500'; break;
                                                case 'offline': $status_class = 'bg-red-100 text-red-800'; $status_icon = 'fa-circle text-red-500'; break;
                                                case 'error': $status_class = 'bg-yellow-100 text-yellow-800'; $status_icon = 'fa-exclamation-triangle text-yellow-500'; break;
                                            }
                                        ?>
                                        <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>"><i class="fas <?php echo $status_icon; ?> mr-2"></i><?php echo ucfirst($device['status']); ?></span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($device['last_checked']))); ?>
                                        <span class="text-xs text-gray-400">(<?php echo time_ago($device['last_checked']); ?>)</span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="text-center py-8"><i class="fas fa-satellite-dish fa-3x text-gray-400 mb-3"></i><p class="text-gray-500"><?php echo (!empty($search_query) || !empty($status_filter)) ? "No devices found matching criteria." : "No device statuses found."; ?></p></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>

</div> </div> <script>
    document.addEventListener('DOMContentLoaded', () => {
        // Update the page title in the header h1 (id="page-title" from header.php)
        const pageTitleElement = document.getElementById('page-title');
        if (pageTitleElement) {
            pageTitleElement.textContent = 'Monitor Devices';
        }
        // Update the browser tab title
        document.title = 'Monitor Devices | Admin Dashboard';

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
                toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
                toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
                toggleIcon.title = isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'; // Update title here
            }
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebarDesktop);
            // Initial state based on class (optional, if you want it to remember state via class)
            const isCollapsed = sidebar.classList.contains('w-20');
            const toggleIcon = sidebarToggle.querySelector('i');
            if (toggleIcon) {
                toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
                toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
                toggleIcon.title = isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar'; // Set initial title
            }
        }

        if (mobileSidebarToggle && sidebar) {
            sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
            mobileSidebarToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('translate-x-0');
                // When mobile sidebar opens, ensure it's full width and texts are visible
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

        // Flash message auto-hide
        const successAlert = document.getElementById('successMessage');
        const errorAlert = document.getElementById('errorMessage');
        function autoHide(el) {
            if(el) {
                setTimeout(() => {
                    el.classList.add('fade-out');
                    setTimeout(() => el.remove(), 500); // Use .remove()
                }, 4000);
            }
        }
        autoHide(successAlert);
        autoHide(errorAlert);
    });
</script>

</body>
</html>
<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

include 'db_connection.php';

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

$result_all_devices = $conn->query("SELECT id, device_id, status, last_checked FROM device_status ORDER BY last_checked DESC");
if (!$result_all_devices) {
    $error_message = "Error fetching device statuses: " . htmlspecialchars($conn->error);
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
        .btn-icon i { margin-right: 0.25rem; }
        .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: opacity 0.5s ease-out; }
        .flash-message.fade-out { opacity: 0; }
        .flash-success { background-color: #d1fae5; border-left-width: 4px; border-color: #10b981; color: #065f46; }
        .flash-error { background-color: #fee2e2; border-left-width: 4px; border-color: #ef4444; color: #991b1b; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; ?>

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
                        <div class="flex">
                            <div class="py-1"><i class="fas fa-check-circle fa-lg mr-3"></i></div>
                            <div>
                                <p class="font-bold">Success</p>
                                <p class="text-sm"><?php echo htmlspecialchars($success_message); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                    <div id="errorMessage" class="flash-message flash-error" role="alert">
                         <div class="flex"><div class="py-1"><i class="fas fa-exclamation-triangle fa-lg mr-3"></i></div><div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p></div></div>
                    </div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Device Status Overview</h2>
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
                                                    $status_class = '';
                                                    $status_icon = '';
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
                                                        default:
                                                            $status_class = 'bg-gray-100 text-gray-800';
                                                            $status_icon = '<i class="fas fa-question-circle text-gray-500 mr-2"></i>';
                                                    }
                                                ?>
                                                <span class="px-3 py-1 inline-flex items-center text-xs leading-5 font-semibold rounded-full <?php echo $status_class; ?>">
                                                    <?php echo $status_icon; ?>
                                                    <?php echo htmlspecialchars(ucfirst($device['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php
                                                    if (!empty($device['last_checked'])) {
                                                        $last_checked_time = strtotime($device['last_checked']);
                                                        $now = time();
                                                        $diff = $now - $last_checked_time;
                                                        $time_ago = '';

                                                        if ($diff < 2) {
                                                            $time_ago = "just now";
                                                        } elseif ($diff < 60) {
                                                            $time_ago = $diff . " sec ago";
                                                        } elseif ($diff < 3600) {
                                                            $time_ago = floor($diff / 60) . " min ago";
                                                        } elseif ($diff < 86400) {
                                                            $time_ago = floor($diff / 3600) . " hr ago";
                                                        } else {
                                                            $time_ago = floor($diff / 86400) . " days ago";
                                                        }
                                                        echo htmlspecialchars(date('M d, Y H:i:s', $last_checked_time)) . " <span class='text-xs text-gray-400'>(" . $time_ago . ")</span>";
                                                    } else {
                                                        echo "Never";
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
                            <p class="text-gray-500">No device statuses found in the `device_status` table.</p>
                            <?php if ((!isset($conn) || !$conn) && empty($error_message) && empty($success_message)): ?>
                            <p class="text-red-500 mt-2">Note: Could not display device statuses due to a database connection issue.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </main>
        </div>
    </div>

<script>
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebarTexts = document.querySelectorAll('.sidebar-text');

    function toggleSidebar() {
        const isCollapsed = sidebar.classList.contains('w-20');
        sidebar.classList.toggle('w-64', isCollapsed);
        sidebar.classList.toggle('w-20', !isCollapsed);
        sidebarTexts.forEach(text => text.classList.toggle('hidden', !isCollapsed));
        const toggleIcon = sidebarToggle.querySelector('i');
        toggleIcon.classList.toggle('fa-chevron-left', isCollapsed);
        toggleIcon.classList.toggle('fa-chevron-right', !isCollapsed);
    }

    if (sidebarToggle) sidebarToggle.addEventListener('click', toggleSidebar);

    if (mobileSidebarToggle) {
        mobileSidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0');
            if (!sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.add('w-64');
                sidebar.classList.remove('w-20');
                sidebarTexts.forEach(text => text.classList.remove('hidden'));
                const toggleIcon = sidebarToggle.querySelector('i');
                toggleIcon.classList.remove('fa-chevron-right');
                toggleIcon.classList.add('fa-chevron-left');
            }
        });
        sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', '-translate-x-full', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto');
    }

    document.addEventListener('DOMContentLoaded', () => {
        const successAlert = document.getElementById('successMessage');
        const errorAlert = document.getElementById('errorMessage');
        const autoHideDelay = 4000;

        function autoHide(alertElement) {
            if (alertElement) {
                setTimeout(() => {
                    alertElement.classList.add('fade-out');
                    setTimeout(() => {
                        alertElement.style.display = 'none';
                    }, 500);
                }, autoHideDelay);
            }
        }

        autoHide(successAlert);
        autoHide(errorAlert);
    });
</script>

</body>
</html>

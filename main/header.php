<?php
// This file contains the standardized header for all admin pages.
// session_start() is expected to be called by the including page (e.g., admin_manage_users.php)

// Ensure $conn is available from the including script (e.g., admin_dashboard.php, admin_monitor_devices.php)
// The including page should have already called db_connection.php and set up $conn.
if (!isset($conn) || !$conn) {
    // Fallback or error handling if $conn is not set.
    // In a real application, you might redirect to an error page or show a degraded state.
    error_log("header.php: Database connection (\$conn) is not available.");
    // Attempt to include db_connection.php as a fallback if it wasn't included by the parent script.
    // This is generally not ideal, as the parent script should manage its dependencies.
    // For now, we'll assume the parent script *will* include db_connection.php.
    // If you uncomment this, ensure db_connection.php only connects and doesn't exit.
    // include_once 'db_connection.php';
}

// --- Fetch Notification Data ---
// 1. Pending Password Reset Requests (existing logic)
$pending_password_resets_count = 0;
$pending_password_resets = [];
if (isset($conn) && $conn) {
    $stmt = $conn->prepare("SELECT u.username, pr.email, pr.expires_at
                            FROM password_resets pr
                            JOIN users u ON pr.email = u.email
                            WHERE pr.expires_at > NOW()
                            ORDER BY pr.expires_at DESC LIMIT 5");
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $pending_password_resets = $result->fetch_all(MYSQLI_ASSOC);
        $pending_password_resets_count = count($pending_password_resets);
        $stmt->close();
    }
}

// 2. NEW: Pending Language Requests
$pending_language_requests_count = 0;
$pending_language_requests = [];
if (isset($conn) && $conn) {
    $stmt = $conn->prepare("SELECT lr.id, lr.language_name, u.username AS requested_by_username, lr.requested_at
                            FROM language_requests lr
                            JOIN users u ON lr.requested_by = u.id
                            WHERE lr.status = 'pending'
                            ORDER BY lr.requested_at ASC LIMIT 5"); // Get up to 5 oldest pending requests
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        $pending_language_requests = $result->fetch_all(MYSQLI_ASSOC);
        $pending_language_requests_count = count($pending_language_requests);
        $stmt->close();
    }
}

// Total notifications for the badge
$total_notifications_count = $pending_password_resets_count + $pending_language_requests_count;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <link rel="icon" type="image/png" href="/assets/images/brand/adminlogo.jpg">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        /* Base styles for sidebar and scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #1f2937; }
        ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; }
        .sidebar { transition: width 0.3s ease-in-out; }
        #sidebar { height: 100vh; display: flex; flex-direction: column; }
        @media (max-width: 1023px) { #sidebar { position: fixed; top: 0; left: 0; z-index: 40; } }

        /* --- STYLES FOR DROPDOWN MENUS (both user and new notification) --- */
        .dropdown-container {
            position: relative;
            display: inline-block;
        }

        .dropdown-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 1.5rem); /* Position below the button */
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            padding: 0.5rem;
            z-index: 50;
            width: 16rem; /* Default width */
            opacity: 0;
            transform: scale(0.95);
            transition: opacity 0.1s ease-out, transform 0.1s ease-out;
            pointer-events: none;
            transform-origin: top right;
        }
        .dropdown-menu.active {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
        }
        .dropdown-header {
            padding: 0.5rem 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }
        .dropdown-item {
            display: flex;
            align-items: center;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            color: #374151;
            font-size: 0.875rem;
            text-decoration: none;
            transition: background-color 0.15s ease-in-out;
        }
        .dropdown-item:hover { background-color: #f3f4f6; }
        .dropdown-item i {
            margin-right: 0.75rem;
            color: #9ca3af;
            width: 1.25rem; /* Consistent icon spacing */
        }
        /* Specific styles for notification items */
        .notification-item {
            display: block;
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f3f4f6;
            font-size: 0.875rem;
            color: #4b5563;
            text-decoration: none;
            text-align: left; /* Ensure text aligns left in notification items */
        }
        .notification-item:last-child {
            border-bottom: none;
        }
        .notification-item:hover {
            background-color: #f9fafb;
        }
        .notification-item strong {
            color: #1f2937;
        }
        .notification-item span {
            font-size: 0.75rem;
            color: #6b7280;
            display: block;
            margin-top: 0.25rem;
        }
        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: #ef4444; /* Red color */
            color: white;
            font-size: 0.7rem;
            font-weight: bold;
            border-radius: 9999px; /* Full rounded */
            padding: 0.1rem 0.4rem;
            line-height: 1;
            min-width: 18px; /* Ensures minimum size for single digits */
            text-align: center;
        }

        /* Styles for the common modal structure (used by delete, success, and logout) */
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
            background-color: #e0f2f7; /* Light blue background */
            color: #2980b9; /* A shade of blue for the icon */
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
    </style>
</head>
<body>
<div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm">
            <div class="container mx-auto px-6 py-3 flex items-center justify-between">
                <div class="flex items-center">
                    <button id="mobileSidebarToggle" class="text-gray-600 focus:outline-none lg:hidden mr-4"> <i class="fas fa-bars text-xl"></i> </button>
                    <h1 id="page-title" class="text-xl font-semibold text-gray-800 hidden lg:block">Admin Dashboard</h1>
                </div>

                <div class="flex items-center space-x-6">

                    <div class="dropdown-container">
                        <button id="notificationMenuButton" type="button" class="relative p-2 text-gray-600 hover:text-gray-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 rounded-full">
                            <i class="fas fa-bell text-lg"></i>
                            <?php if ($total_notifications_count > 0): ?>
                                <span class="notification-count"><?php echo $total_notifications_count; ?></span>
                            <?php endif; ?>
                        </button>
                        <div id="notificationDropdownMenu" class="dropdown-menu w-72"> <div class="dropdown-header">
                                <p class="font-semibold text-sm text-gray-800">Notifications</p>
                            </div>
                            <div class="py-1 max-h-60 overflow-y-auto"> <?php if ($total_notifications_count > 0): ?>
                                    <?php if (!empty($pending_language_requests)): ?>
                                        <p class="font-semibold text-gray-700 px-4 py-2 text-xs uppercase border-b border-gray-100">Language Requests</p>
                                        <?php foreach ($pending_language_requests as $request): ?>
                                            <a href="admin_dashboard.php?page=language_requests#language-requests-section" class="notification-item">
                                                <strong>New Language Request</strong>
                                                <span>"<?php echo htmlspecialchars($request['language_name']); ?>"</span>
                                                <span>By: <?php echo htmlspecialchars($request['requested_by_username']); ?></span>
                                                <span class="text-xs text-gray-400">On: <?php echo htmlspecialchars(date('M d, Y', strtotime($request['requested_at']))); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>

                                    <?php if (!empty($pending_password_resets)): ?>
                                        <?php if (!empty($pending_language_requests)): ?>
                                            <p class="font-semibold text-gray-700 px-4 py-2 text-xs uppercase border-t border-b border-gray-100">Password Resets</p>
                                        <?php endif; ?>
                                        <?php foreach ($pending_password_resets as $reset): ?>
                                            <a href="#" class="notification-item">
                                                <strong>Password Reset Request</strong>
                                                <span>User: <?php echo htmlspecialchars($reset['username'] ?? 'N/A'); ?></span>
                                                <span>Email: <?php echo htmlspecialchars($reset['email']); ?></span>
                                                <span class="text-xs text-gray-400">Expires: <?php echo htmlspecialchars(date('M d, Y H:i', strtotime($reset['expires_at']))); ?></span>
                                            </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p class="text-center text-gray-500 py-4 text-sm">No new notifications.</p>
                                <?php endif; ?>
                            </div>
                            <?php if ($total_notifications_count > 0): ?>
                            <div class="dropdown-footer text-center border-t border-gray-100 mt-1 pt-2">
                                <a href="admin_dashboard.php?page=language_requests#language-requests-section" class="text-indigo-600 hover:text-indigo-800 text-sm">View All Language Requests</a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="dropdown-container">
                        <button id="userMenuButton" type="button" class="flex items-center space-x-2 p-1 border-2 border-transparent rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            <div class="h-10 w-10 rounded-full overflow-hidden bg-gray-200 flex items-center justify-center">
                                <span class="font-semibold text-gray-600"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1))); ?></span>
                            </div>
                            <i id="userMenuChevron" class="fas fa-chevron-down text-gray-500 text-xs ml-1 transition-transform duration-200"></i>
                        </button>
                        <div id="userDropdownMenu" class="dropdown-menu">
                            <div class="dropdown-header">
                                <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin User'); ?></p>
                                <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? 'adminsystem@gmail.com'); ?></p>
                            </div>

                            <div class="py-1">
                                <a href="logout_admin.php" id="logoutTrigger" class="dropdown-item text-red-600">
                                    <i class="fas fa-sign-out-alt"></i> Sign out
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div id="logoutModal" class="modal-overlay">
    <div class="modal-box logout-modal">
        <div class="icon-wrapper">
            <svg class="h-14 w-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
            </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Sign Out</h3>
        <p class="text-sm text-gray-500 mb-6">
            Are you sure you want to sign out?
        </p>
        <div class="flex flex-col space-y-3">
            <button id="cancelLogoutButton" class="w-full border border-blue-500 py-2 px-4 rounded-md bg-white hover:bg-blue-50 text-blue-600 font-semibold transition duration-150 ease-in-out">
                Cancel
            </button>
            <button id="confirmLogoutButton" class="w-full bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 px-4 rounded-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                Sign out
            </button>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // User dropdown functionality (existing)
        const userMenuButton = document.getElementById('userMenuButton');
        const userDropdownMenu = document.getElementById('userDropdownMenu');
        const userMenuChevron = document.getElementById('userMenuChevron');

        if (userMenuButton && userDropdownMenu && userMenuChevron) {
            userMenuButton.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent document click from closing it immediately
                userDropdownMenu.classList.toggle('active');
                userMenuChevron.classList.toggle('fa-chevron-down');
                userMenuChevron.classList.toggle('fa-chevron-up');

                // Close notification dropdown if open
                const notificationDropdownMenu = document.getElementById('notificationDropdownMenu');
                if (notificationDropdownMenu && notificationDropdownMenu.classList.contains('active')) {
                    notificationDropdownMenu.classList.remove('active');
                }
            });
        }

        // NEW: Notification dropdown functionality
        const notificationMenuButton = document.getElementById('notificationMenuButton');
        const notificationDropdownMenu = document.getElementById('notificationDropdownMenu');

        if (notificationMenuButton && notificationDropdownMenu) {
            notificationMenuButton.addEventListener('click', (e) => {
                e.stopPropagation(); // Prevent document click from closing it immediately
                notificationDropdownMenu.classList.toggle('active');

                // Close user dropdown if open
                if (userDropdownMenu.classList.contains('active')) {
                    userDropdownMenu.classList.remove('active');
                    userMenuChevron.classList.remove('fa-chevron-up');
                    userMenuChevron.classList.add('fa-chevron-down');
                }
            });
        }

        // Close ALL dropdowns if the user clicks outside of them
        document.addEventListener('click', (e) => {
            // Close User Dropdown
            if (userMenuButton && userDropdownMenu && !userMenuButton.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                if (userDropdownMenu.classList.contains('active')) {
                    userDropdownMenu.classList.remove('active');
                    userMenuChevron.classList.remove('fa-chevron-up');
                    userMenuChevron.classList.add('fa-chevron-down');
                }
            }
            // Close Notification Dropdown
            if (notificationMenuButton && notificationDropdownMenu && !notificationMenuButton.contains(e.target) && !notificationDropdownMenu.contains(e.target)) {
                if (notificationDropdownMenu.classList.contains('active')) {
                    notificationDropdownMenu.classList.remove('active');
                }
            }
        });

        // --- Logout Confirmation Modal Logic ---
        const logoutTrigger = document.getElementById('logoutTrigger');
        const logoutModal = document.getElementById('logoutModal');
        const confirmLogoutButton = document.getElementById('confirmLogoutButton');
        const cancelLogoutButton = document.getElementById('cancelLogoutButton');

        if (logoutTrigger && logoutModal && confirmLogoutButton && cancelLogoutButton) {
            logoutTrigger.addEventListener('click', (e) => {
                e.preventDefault();
                userDropdownMenu.classList.remove('active'); // Close user dropdown
                logoutModal.classList.add('active'); // Show logout modal
            });

            cancelLogoutButton.addEventListener('click', () => {
                logoutModal.classList.remove('active');
            });

            confirmLogoutButton.addEventListener('click', () => {
                window.location.href = logoutTrigger.href;
            });

            logoutModal.addEventListener('click', (e) => {
                if (e.target === logoutModal) {
                    logoutModal.classList.remove('active');
                }
            });
        }
    });
</script>
</body>
</html>
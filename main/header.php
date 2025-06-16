<?php
// This file contains the standardized header for all admin pages.
// session_start() is expected to be called by the including page (e.g., admin_manage_users.php)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <!-- The Title will be set on each individual page via JavaScript or PHP variable -->
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

        /* --- STYLES FOR THE CORRECT DROPDOWN MENU --- */
        .dropdown-menu {
            position: absolute;
            right: 0;
            top: calc(100% + 0.5rem); /* Position below the button */
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            padding: 0.5rem;
            z-index: 50;
            width: 16rem;
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
            /* Default icon wrapper style, applying the desired consistent blue color */
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

        /* Specific overrides for other modals if needed (e.g., delete modal icon) */
        /* If you want delete/success icons to have different colors, define them here explicitly: */
        /*
        .delete-modal .icon-wrapper {
            background-color: #fee2e2;
            color: #ef4444;
        }
        .success-modal .icon-wrapper {
            background-color: #d1fae5;
            color: #10b981;
        }
        */
    </style>
</head>
<body> <!-- Body tag starts here, closed at the very end of this file -->

<div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-sm">
            <div class="container mx-auto px-6 py-3 flex items-center justify-between">
                <div class="flex items-center">
                    <button id="mobileSidebarToggle" class="text-gray-600 focus:outline-none lg:hidden mr-4"> <i class="fas fa-bars text-xl"></i> </button>
                    <h1 id="page-title" class="text-xl font-semibold text-gray-800 hidden lg:block">Admin Dashboard</h1>
                </div>

                <!-- --- THE CORRECT DROPDOWN HTML --- -->
                <div class="relative inline-block text-left">
                    <button id="userMenuButton" type="button" class="flex items-center space-x-2 p-1 border-2 border-transparent rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <div class="h-10 w-10 rounded-full overflow-hidden bg-gray-200 flex items-center justify-center">
                            <!-- Dynamically display first letter of username -->
                            <span class="font-semibold text-gray-600"><?php echo htmlspecialchars(strtoupper(substr($_SESSION['username'] ?? 'A', 0, 1))); ?></span>
                        </div>
                        <i id="userMenuChevron" class="fas fa-chevron-down text-gray-500 text-xs ml-1 transition-transform duration-200"></i>
                    </button>
                    <div id="userDropdownMenu" class="dropdown-menu">
                        <div class="dropdown-header">
                            <p class="font-semibold text-sm text-gray-800"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin User'); ?></p>
                            <p class="text-xs text-gray-500 truncate"><?php echo htmlspecialchars($_SESSION['email'] ?? 'admin@example.com'); ?></p>
                        </div>
                        <div class="py-1">
                            <a href="/admin/profile.php" class="dropdown-item">
                                <i class="fas fa-user-edit"></i> Edit profile
                            </a>
                            <a href="/admin/settings.php" class="dropdown-item">
                                <i class="fas fa-cog"></i> Account settings
                            </a>
                        </div>
                        <div class="border-t border-gray-200"></div>
                        <div class="py-1">
                            <!-- Change this to a button or span to trigger the modal -->
                            <a href="logout_admin.php" id="logoutTrigger" class="dropdown-item text-red-600">
                                <i class="fas fa-sign-out-alt"></i> Sign out
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- The main content area placeholder. Actual content will be included by the calling page. -->
        <!-- This div remains open, to be closed by the including page (e.g., admin_manage_users.php) -->
        <!-- <main> tag and its content are part of the including page. -->

<!-- Logout Confirmation Modal HTML -->
<div id="logoutModal" class="modal-overlay">
    <div class="modal-box logout-modal">
        <div class="icon-wrapper">
            <!-- Icon resembling the image: a door with an arrow -->
            <svg class="h-14 w-14" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
            </svg>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Oh no! You're leaving...</h3>
        <p class="text-sm text-gray-500 mb-6">
            Are you sure?
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
            });

            // Close the dropdown if the user clicks outside of it
            document.addEventListener('click', (e) => {
                if (!userMenuButton.contains(e.target) && !userDropdownMenu.contains(e.target)) {
                    if (userDropdownMenu.classList.contains('active')) {
                        userDropdownMenu.classList.remove('active');
                        userMenuChevron.classList.remove('fa-chevron-up');
                        userMenuChevron.classList.add('fa-chevron-down');
                    }
                }
            });
        }

        // --- Logout Confirmation Modal Logic ---
        const logoutTrigger = document.getElementById('logoutTrigger'); // The "Sign out" link in the dropdown
        const logoutModal = document.getElementById('logoutModal');
        const confirmLogoutButton = document.getElementById('confirmLogoutButton');
        const cancelLogoutButton = document.getElementById('cancelLogoutButton');

        if (logoutTrigger && logoutModal && confirmLogoutButton && cancelLogoutButton) {
            logoutTrigger.addEventListener('click', (e) => {
                e.preventDefault(); // Prevent default link behavior (navigating immediately)
                userDropdownMenu.classList.remove('active'); // Close the user dropdown menu
                logoutModal.classList.add('active'); // Show the logout modal
            });

            cancelLogoutButton.addEventListener('click', () => {
                logoutModal.classList.remove('active'); // Hide the modal
            });

            confirmLogoutButton.addEventListener('click', () => {
                // If confirmed, navigate to the logout page
                window.location.href = logoutTrigger.href; // Use the href from the original link
            });

            // Close modal if overlay is clicked
            logoutModal.addEventListener('click', (e) => {
                if (e.target === logoutModal) { // Check if the click was directly on the overlay
                    logoutModal.classList.remove('active');
                }
            });
        }
    });
</script>
</body>
</html>

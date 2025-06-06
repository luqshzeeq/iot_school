<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$menu_items = [
    'admin_overview.php'        => ['label' => 'Dashboard', 'icon' => 'fa-tachometer-alt'], // <-- ADD THIS LINE
    'admin_dashboard.php'       => ['label' => 'Manage Languages', 'icon' => 'fa-language'],
    'admin_manage_users.php'    => ['label' => 'Manage Users', 'icon' => 'fa-users-cog'],
    'admin_monitor_devices.php' => ['label' => 'Monitor Devices', 'icon' => 'fa-desktop'],
    'logout_admin.php'          => ['label' => 'Logout', 'icon' => 'fa-sign-out-alt'],
];
?>

<aside id="sidebar" class="sidebar w-64 bg-gray-800 text-gray-100 flex-shrink-0 overflow-y-auto">
    <div class="p-4">
        <a href="admin_dashboard.php" class="flex items-center space-x-2 text-white text-2xl font-semibold">
            <i class="fas fa-shield-alt"></i>
            <span class="sidebar-text">Admin Panel</span>
        </a>
    </div>
    <nav class="mt-4">
        <?php foreach ($menu_items as $file => $item):
            $active_class = ($file === $current_page) ? 'bg-gray-700 text-white' : 'text-gray-300 hover:bg-gray-700 hover:text-white';
        ?>
            <a href="<?php echo $file; ?>" class="flex items-center px-4 py-3 rounded-md transition duration-150 <?php echo $active_class; ?>">
                <i class="fas <?php echo $item['icon']; ?> w-6 text-center"></i>
                <span class="ml-3 sidebar-text"><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <div class="p-4 mt-auto border-t border-gray-700">
        <button id="sidebarToggle" class="text-gray-400 hover:text-white focus:outline-none">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
</aside>

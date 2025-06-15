<?php
// sidebar.php
$current_page = basename($_SERVER['PHP_SELF']);
$menu_items = [
    'admin_overview.php'        => ['label' => 'Dashboard', 'icon' => 'fa-tachometer-alt'],
    'admin_dashboard.php'       => ['label' => 'Manage Languages', 'icon' => 'fa-language'],
    'admin_manage_users.php'    => ['label' => 'Manage Users', 'icon' => 'fa-users-cog'],
    'admin_monitor_devices.php' => ['label' => 'Monitor Devices', 'icon' => 'fa-desktop'],
    
];
?>

<aside id="sidebar" class="sidebar w-64 bg-slate-800 text-slate-100 flex-shrink-0 overflow-y-auto flex flex-col h-full">
    <div class="p-4">
        <a href="admin_overview.php" class="flex items-center space-x-2 text-white text-2xl font-semibold">
            <i class="fa-solid fa-user-secret"></i> <span class="sidebar-text">Admin Panel</span>
        </a>
    </div>
    <nav class="mt-4">
        <?php foreach ($menu_items as $file => $item):
            $base_classes = "text-slate-300 hover:bg-slate-700 hover:text-white";
            $active_classes = "bg-sky-600 text-white"; // Using a sky blue for active item accent

            $link_classes = ($file === $current_page) ? $active_classes : $base_classes;
        ?>
            <a href="<?php echo $file; ?>" class="flex items-center px-4 py-3 rounded-md transition duration-150 <?php echo $link_classes; ?>">
                <i class="fas <?php echo $item['icon']; ?> w-6 text-center"></i>
                <span class="ml-3 sidebar-text"><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <!-- THIS IS THE TOGGLE BUTTON AT THE VERY BOTTOM, NOW CENTERED -->
    <!-- Added 'flex' and 'justify-center' to horizontally center the button -->
    <div class="p-4 mt-auto flex justify-center">
        <button id="sidebarToggle" class="text-slate-400 hover:text-white focus:outline-none">
            <i class="fas fa-chevron-left"></i>
        </button>
    </div>
</aside>

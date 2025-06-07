<?php
session_start();
include 'db_connection.php'; // Ensure this path is correct for your database connection

// Redirect if user is not logged in or is not a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];

// Retrieve and unset session messages for feedback to the user
$language_message = $_SESSION['language_message'] ?? '';
$language_error = $_SESSION['language_error'] ?? '';
$profile_update_success = $_SESSION['profile_update_success'] ?? '';
$profile_update_errors = $_SESSION['profile_update_errors'] ?? [];

unset($_SESSION['language_message'], $_SESSION['language_error'], $_SESSION['profile_update_success'], $_SESSION['profile_update_errors']);

// Generate CSRF token if not already present in the session
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// --- START: POST handling for setting daily language ---
// This block processes the form submission from set_language_content.php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_language_action'])) {
    // Validate CSRF token to prevent cross-site request forgery attacks
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['language_error'] = "Invalid CSRF token. Please try again.";
        header("Location: teacher_dashboard.php?page=set_language");
        exit();
    }

    $setting_date = $_POST['setting_date'] ?? '';
    $language_id = $_POST['language_id'] ?? '';

    // Basic input validation
    if (empty($setting_date) || empty($language_id) || !preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $setting_date)) {
        $_SESSION['language_error'] = "Invalid date or language selected.";
        header("Location: teacher_dashboard.php?page=set_language&setting_date=" . urlencode($setting_date));
        exit();
    }

    // Check if a language is already set for this date for this teacher
    // We select teacher_id because it's part of the primary key and indicates existence.
    $sql_check = "SELECT teacher_id FROM teacher_daily_languages WHERE teacher_id = ? AND setting_date = ?";
    $stmt_check = $conn->prepare($sql_check);

    if ($stmt_check) {
        $stmt_check->bind_param("is", $teacher_id, $setting_date);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($row_check = $result_check->fetch_assoc()) {
            // If a record exists, update the language_id.
            // Removed 'set_at = NOW()' as 'set_at' column does not exist in your table.
            $sql_update = "UPDATE teacher_daily_languages SET language_id = ? WHERE teacher_id = ? AND setting_date = ?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("iis", $language_id, $teacher_id, $setting_date);
                if ($stmt_update->execute()) {
                    $_SESSION['language_message'] = "Daily language updated successfully for " . htmlspecialchars($setting_date) . "!";
                } else {
                    $_SESSION['language_error'] = "Failed to update daily language: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $_SESSION['language_error'] = "Database error (update prepare): " . $conn->error;
            }
        } else {
            // If no record exists, insert a new one.
            // CORRECTED: Removed NOW() from the VALUES clause to match the 3 columns specified.
            $sql_insert = "INSERT INTO teacher_daily_languages (teacher_id, setting_date, language_id) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                $stmt_insert->bind_param("isi", $teacher_id, $setting_date, $language_id);
                if ($stmt_insert->execute()) {
                    $_SESSION['language_message'] = "Daily language set successfully for " . htmlspecialchars($setting_date) . "!";
                } else {
                    $_SESSION['language_error'] = "Failed to set daily language: " . $stmt_insert->error;
                }
                $stmt_insert->close();
            } else {
                $_SESSION['language_error'] = "Database error (insert prepare): " . $conn->error;
            }
        }
        $stmt_check->close();
    } else {
        $_SESSION['language_error'] = "Database error (check prepare): " . $conn->error;
    }

    // Redirect back to the set_language page for the selected date to show feedback
    header("Location: teacher_dashboard.php?page=set_language&setting_date=" . urlencode($setting_date));
    exit();
}
// --- END: POST handling for setting daily language ---

// Initialize teacher profile variables with defaults
$teacher_username = "Teacher";
$teacher_email = "teacher@example.com";
$teacher_profile_pic_initial = 'T'; // Initial for default avatar
$teacher_actual_profile_pic = ''; // Path to actual profile pic

// Fetch teacher's profile data
$sql_profile = "SELECT username, email, profile_pic_path FROM users WHERE id = ?";
$stmt_profile = $conn->prepare($sql_profile);
if ($stmt_profile) {
    $stmt_profile->bind_param("i", $teacher_id);
    if ($stmt_profile->execute()) {
        $result_profile = $stmt_profile->get_result();
        if ($profile_data_row = $result_profile->fetch_assoc()) {
            $teacher_username = htmlspecialchars($profile_data_row['username']);
            $teacher_email = htmlspecialchars($profile_data_row['email'] ?? 'email_not_set@example.com');

            // Check if profile picture path exists and file is accessible
            if (!empty($profile_data_row['profile_pic_path']) && file_exists($profile_data_row['profile_pic_path'])) {
                $teacher_actual_profile_pic = htmlspecialchars($profile_data_row['profile_pic_path']);
            } else {
                $teacher_actual_profile_pic = ''; // Reset if path is invalid or file missing
            }

            // Set initial for avatar if username is available
            if (!empty($teacher_username)) {
                $teacher_profile_pic_initial = strtoupper(substr($teacher_username, 0, 1));
            }
        }
    }
    $stmt_profile->close();
}

// Determine profile picture URLs for display
$topbar_avatar_url = !empty($teacher_actual_profile_pic) ? $teacher_actual_profile_pic : 'https://placehold.co/40x40/A0AEC0/FFFFFF?text=' . $teacher_profile_pic_initial;
$profile_page_pic_url = !empty($teacher_actual_profile_pic) ? $teacher_actual_profile_pic : 'https://placehold.co/120x120/4299E1/FFFFFF?text=' . $teacher_profile_pic_initial;

// Determine current page and set page title
$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';
$page_title = ucfirst($page);
if ($page === 'set_language') {
    $page_title = "Set Daily Language";
} elseif ($page === 'language_usage') {
    $page_title = "Language Usage";
} elseif ($page === 'profile') {
    $page_title = "My Profile";
}

// Handle selected date for language setting (defaults to today)
$selected_date_str = isset($_GET['setting_date']) ? $_GET['setting_date'] : date('Y-m-d');
// Validate date format, revert to today if invalid
if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $selected_date_str)) {
    $selected_date_str = date('Y-m-d');
}

// Initialize variables for displaying current language setting
$current_language_name_for_selected_date_display = "No language set for today"; // Updated default for dashboard/profile
$current_language_for_selected_date_id = ''; // Default ID for set_language page

// Determine which date to fetch language for (today for dashboard/profile, or selected date for set_language)
$date_to_fetch_language_for = ($page === 'dashboard' || $page === 'profile') ? date('Y-m-d') : $selected_date_str;
$display_date_str_for_language = htmlspecialchars($date_to_fetch_language_for); // Used for date string in display

// Fetch current language setting for the determined date
$sql_current_setting = "SELECT l.id, l.language_name
                            FROM teacher_daily_languages tdl
                            JOIN languages l ON tdl.language_id = l.id
                            WHERE tdl.teacher_id = ? AND tdl.setting_date = ?";
$stmt_fetch_lang = $conn->prepare($sql_current_setting);
if ($stmt_fetch_lang) {
    $stmt_fetch_lang->bind_param("is", $teacher_id, $date_to_fetch_language_for);
    if ($stmt_fetch_lang->execute()) {
        $result_current_setting = $stmt_fetch_lang->get_result();
        if ($row_current = $result_current_setting->fetch_assoc()) {
            $current_language_for_selected_date_id = $row_current['id']; // Store ID for dropdown selection
            // Format display based on page context
            if ($page === 'dashboard' || $page === 'profile') {
               $current_language_name_for_selected_date_display = htmlspecialchars($row_current['language_name']) . " (" . $display_date_str_for_language . ")"; // Changed "for Today" to just date
            } else { // For set_language page, it shows for the $selected_date_str
               $current_language_name_for_selected_date_display = htmlspecialchars($row_current['language_name']);
            }
        } else { // No language set for the specific date
            if ($page === 'dashboard' || $page === 'profile') {
                $current_language_name_for_selected_date_display = "No language set for today"; // Simplified message for dashboard
            } else { // For set_language page
                $current_language_name_for_selected_date_display = "Not Set for " . $display_date_str_for_language;
            }
        }
    }
    $stmt_fetch_lang->close();
}

// --- Fetch all available languages (used for dropdowns and chart data processing) ---
$available_languages = [];
$sql_available_languages = "SELECT id, language_name FROM languages ORDER BY language_name ASC";
$result_available_languages = $conn->query($sql_available_languages);

if ($result_available_languages) {
    while ($row = $result_available_languages->fetch_assoc()) {
        $available_languages[] = $row;
    }
} else {
    error_log("Error fetching available languages: " . $conn->error);
}

// Get total number of unique languages available
$total_languages = count($available_languages);

// Calculate days language was set this month
$days_set_this_month = 0;
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t');
$sql_days_set = "SELECT COUNT(DISTINCT setting_date) as count FROM teacher_daily_languages WHERE teacher_id = ? AND setting_date BETWEEN ? AND ?";
$stmt_days_set = $conn->prepare($sql_days_set);
if($stmt_days_set) {
    $stmt_days_set->bind_param("iss", $teacher_id, $current_month_start, $current_month_end);
    if($stmt_days_set->execute()){
        $result_days_set = $stmt_days_set->get_result();
        if($row_days_set = $result_days_set->fetch_assoc()){
            $days_set_this_month = $row_days_set['count'];
        }
    }
    $stmt_days_set->close();
}


// --- START: Chart Data Fetching and Preparation for Language Trend ---
$chart_labels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'];
$chart_data_raw = []; // Stores date => language_name mappings from DB

// Calculate start and end dates for the current week (Monday to Friday)
$today_dt = new DateTime(); // Use a separate DateTime object for calculations
// Set $today_dt to Monday of the current week
if ($today_dt->format('N') != 1) { // If not Monday (1 is Monday for ISO-8601)
    $today_dt->modify('last monday');
}
$week_start = $today_dt->format('Y-m-d');
$today_dt->modify('+4 days'); // Move to Friday
$week_end = $today_dt->format('Y-m-d');

// Fetch language settings for the current week (Monday to Friday)
$sql_chart_data = "SELECT tdl.setting_date, l.language_name
                   FROM teacher_daily_languages tdl
                   JOIN languages l ON tdl.language_id = l.id
                   WHERE tdl.teacher_id = ? AND tdl.setting_date BETWEEN ? AND ?
                   ORDER BY tdl.setting_date ASC";

$stmt_chart_data = $conn->prepare($sql_chart_data);
if ($stmt_chart_data) {
    $stmt_chart_data->bind_param("iss", $teacher_id, $week_start, $week_end);
    if ($stmt_chart_data->execute()) {
        $result_chart_data = $stmt_chart_data->get_result();
        while ($row = $result_chart_data->fetch_assoc()) {
            $chart_data_raw[$row['setting_date']] = $row['language_name'];
        }
    } else {
        error_log("Error fetching chart data: " . $stmt_chart_data->error);
    }
    $stmt_chart_data->close();
}

// Prepare data structure for Chart.js datasets
$chart_datasets = [];
$all_language_names = array_column($available_languages, 'language_name');

// Initialize data array for each language across the 5 days of the week
$language_data_for_chart = [];
foreach ($all_language_names as $lang_name) {
    $language_data_for_chart[$lang_name] = [0, 0, 0, 0, 0]; // 5 days (Monday-Friday)
}

// Populate the chart data based on fetched settings
$current_day_iter = new DateTime($week_start); // Use a new DateTime object for iterating
for ($i = 0; $i < 5; $i++) { // Loop through Monday to Friday
    $current_date_str_iter = $current_day_iter->format('Y-m-d');
    $day_of_week_index = $current_day_iter->format('N') - 1; // 0 for Monday, 1 for Tuesday, ..., 4 for Friday

    if (isset($chart_data_raw[$current_date_str_iter])) {
        $language_set = $chart_data_raw[$current_date_str_iter];
        // Mark 1 for the day if that language was set
        if (isset($language_data_for_chart[$language_set])) {
            $language_data_for_chart[$language_set][$day_of_week_index] = 1;
        }
    }
    $current_day_iter->modify('+1 day');
}

// Define consistent colors for languages in the chart
$chart_colors = [
    'Bahasa Melayu' => ['backgroundColor' => '#bee3f8', 'borderColor' => '#90cdf4'],
    'English' => ['backgroundColor' => '#b2f5ea', 'borderColor' => '#81e6d9'],
    'Mandarin' => ['backgroundColor' => '#fed7aa', 'borderColor' => '#fbbf24'],
    'Tamil' => ['backgroundColor' => '#e9d5ff', 'borderColor' => '#d6bcfa'],
    // Add more colors here for any other languages you might add
];

// Create the final datasets array for Chart.js
foreach ($language_data_for_chart as $lang_name => $data_array) {
    $colors = $chart_colors[$lang_name] ?? ['backgroundColor' => '#cccccc', 'borderColor' => '#999999']; // Fallback color
    $chart_datasets[] = [
        'label' => $lang_name,
        'data' => $data_array,
        'backgroundColor' => $colors['backgroundColor'],
        'borderColor' => $colors['borderColor'],
        'borderWidth' => 1
    ];
}

// Encode PHP arrays to JSON for direct use in JavaScript
$chart_datasets_json = json_encode($chart_datasets);
$chart_labels_json = json_encode($chart_labels);
// --- END: Chart Data Fetching and Preparation for Language Trend ---

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Panel - <?php echo htmlspecialchars($page_title); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7fafc;  }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #edf2f7; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #a0aec0; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #718096; }

        
        .sidebar {
    background-color: #1e3a8a; /* darker blue */
    border-right: 1px solid #cce7f0;
}

.sidebar-header {
    border-bottom: none !important;
}
       .sidebar-item,
.sidebar-item i {
    color: #f8fafc;
}
.sidebar-item:hover {
    background-color: #3b82f6;
    color: #ffffff;
}
.sidebar-item:hover i {
    color: #ffffff;
}
.sidebar-item.active {
    background-color: #2563eb;
    color: #ffffff;
}
.sidebar-item.active i {
    color: #ffffff;
}


.sidebar-item:hover {
    background-color: #2a65f3; /* Darker blue */
    border-left-color: #ffffff;
}
.sidebar-item.active {
    background-color: #3a75ff; /* Active highlight */
    color: #ffffff;
    border-left-color: #ffffff;
}
        .sidebar.collapsed { width: 5rem; }
        .sidebar.collapsed .sidebar-header .logo-text,
        .sidebar.collapsed .sidebar-item span,
        .sidebar.collapsed .section-header { display: none; }
        .sidebar.collapsed .sidebar-header { justify-content: center; }
        .sidebar.collapsed .sidebar-header .sidebar-logo-icon { margin-right: 0 !important; }
        .sidebar.collapsed .sidebar-item { justify-content: center; padding-left: 0; padding-right: 0; }
        .sidebar.collapsed .sidebar-item i { margin-right: 0 !important; }
        .sidebar.collapsed .sidebar-item.active { border-left-width: 0px; border-bottom: 4px solid #4299e1; border-radius: 0.5rem; }

        .content-area { padding-left: 0; transition: margin-left 0.1s ease-in-out; }

        select {
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%234A5568%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.4-12.8z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat; background-position: right .9em top 50%; background-size: .65em auto; padding-right: 2.5em !important;
        }
        input[type="date"]::-webkit-calendar-picker-indicator { cursor: pointer; opacity: 0.6; filter: invert(0.4); }
        input[type="date"]:hover::-webkit-calendar-picker-indicator { opacity: 1; }
        .card { background-color: #ffffff; border-radius: 0.75rem; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); transition: box-shadow 0.3s ease-in-out; }
        .card:hover { box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        .card-header { padding: 1rem 1.5rem; border-bottom: 1px solid #e2e8f0; }
        .card-body { padding: 1.5rem; }

        .stat-card.blue { background-color: #ebf8ff; color: #2b6cb0; }
        .stat-card.blue i { color: #4299e1; }
        .stat-card.green { background-color: #f0fff4; color: #2f855a; }
        .stat-card.green i { color: #48bb78; }
        .stat-card.purple { background-color: #faf5ff; color: #6b46c1; }
        .stat-card.purple i { color: #805ad5; }
        .stat-card .text-sm { color: inherit; opacity: 0.8; }
        .stat-card .text-xl { color: inherit; }

        #profileDropdownMenu a i { transition: color 0.1s ease-in-out; }
        #profileDropdownMenu a:hover i { color: #3182ce; }
        #nav-logout-dropdown:hover i { color: #c53030; }

        /* General Calendar Grid style - day-specific styles including .current-day will be in dashboard_content.php */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem; /* Tailwind's gap-2 */
        }
        /* The specific .calendar-day.current-day styles are moved to dashboard_content.php */
    </style>
</head>
<body class="flex h-screen overflow-hidden">

    <aside class="sidebar w-64 text-gray-700 flex flex-col fixed h-full md:relative md:translate-x-0 z-30 flex-shrink-0" id="sidebar">
        <div class="sidebar-header p-5 flex items-center justify-center ">
            <i class="fas fa-graduation-cap text-4xl text-white mr-3 sidebar-logo-icon"></i>
<span class="logo-text text-xl font-bold text-white">Teacher Panel</span>

        </div>
        <nav class="flex-grow p-4 space-y-1.5 overflow-y-auto">
            <a href="teacher_dashboard.php?page=dashboard" id="nav-dashboard" class="sidebar-item flex items-center px-4 py-2.5 rounded-md text-sm">
                <i class="fas fa-tachometer-alt w-5 text-center mr-3"></i>
                <span>Dashboard</span>
            </a>
            <a href="teacher_dashboard.php?page=set_language" id="nav-set-language" class="sidebar-item flex items-center px-4 py-2.5 rounded-md text-sm">
                <i class="fas fa-language w-5 text-center mr-3"></i>
                <span>Set Daily Language</span>
            </a>
            <a href="teacher_dashboard.php?page=language_usage" id="nav-language-usage" class="sidebar-item flex items-center px-4 py-2.5 rounded-md text-sm">
                <i class="fas fa-chart-area w-5 text-center mr-3"></i>
                <span>Language Usage</span>
            </a>
            <a href="teacher_dashboard.php?page=profile" id="nav-profile" class="sidebar-item flex items-center px-4 py-2.5 rounded-md text-sm">
                <i class="fas fa-user-circle w-5 text-center mr-3"></i>
                <span>My Profile</span>
            </a>
        </nav>
        <div class="p-4  text-center">
            <button id="sidebarToggleBottom" class="text-white focus:outline-none p-2 rounded-full hover:bg-gray-200">
                <i class="fas fa-angle-left text-xl"></i>
            </button>
        </div>
    </aside>

    <div class="flex-1 flex flex-col overflow-hidden content-area">
        <header class="bg-white shadow-md p-5 flex justify-between items-center z-20">
            <div class="flex items-center">
                <button id="menuButton" class="text-gray-600 focus:outline-none md:hidden mr-4">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <h1 class="text-xl font-semibold text-gray-700"><?php echo htmlspecialchars($page_title); ?></h1>
            </div>
            <div class="flex items-center space-x-4">
                <div class="relative" id="profileDropdownContainer">
                    <button id="profileDropdownButton" class="flex items-center space-x-2 focus:outline-none">
                        <span class="text-sm text-gray-700 font-medium hidden sm:inline"><?php echo htmlspecialchars($teacher_username); ?></span>
                        <img src="<?php echo $topbar_avatar_url; ?>" alt="User Avatar" class="w-9 h-9 rounded-full object-cover border-2 border-transparent hover:border-blue-500 transition-colors" onerror="this.onerror=null; this.src='https://placehold.co/40x40/CBD5E0/4A5568?text=<?php echo $teacher_profile_pic_initial; ?>';">
                    </button>
                    <div id="profileDropdownMenu" class="absolute right-0 mt-2 w-60 bg-white rounded-md shadow-xl z-50 hidden origin-top-right ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="profileDropdownButton">
                        <div class="py-1" role="none">
                            <div class="px-4 py-3 border-b border-gray-200">
                                <p class="text-sm font-semibold text-gray-800" role="none"><?php echo htmlspecialchars($teacher_username); ?></p>
                                <p class="text-xs text-gray-500 truncate" role="none"><?php echo htmlspecialchars($teacher_email); ?></p>
                            </div>
                            <a href="teacher_dashboard.php?page=profile" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900" role="menuitem" tabindex="-1" id="dropdown-profile-link">
                                <i class="fas fa-user-circle w-5 mr-3 text-gray-400"></i>View Profile
                            </a>
                            <a href="#" class="flex items-center px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900" role="menuitem" tabindex="-1" id="dropdown-settings-link"> <i class="fas fa-cog w-5 mr-3 text-gray-400"></i>Account Settings
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <form action="logout.php" method="post" role="none"> <button type="submit" id="nav-logout-dropdown" class="w-full text-left flex items-center px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 hover:text-red-700" role="menuitem" tabindex="-1">
                                    <i class="fas fa-sign-out-alt w-5 mr-3"></i>Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-6 md:p-8 overflow-y-auto">
            <?php
            // Display success or error messages
            if (!empty($language_message) && $page === 'set_language') {
                echo "<div class='mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>" . htmlspecialchars($language_message) . "</div>";
            }
            if (!empty($language_error) && $page === 'set_language') {
                echo "<div class='mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>" . htmlspecialchars($language_error) . "</div>";
            }

            if (!empty($profile_update_success) && $page === 'profile') {
                echo "<div class='mb-4 p-4 text-sm text-green-700 bg-green-100 rounded-lg' role='alert'>" . htmlspecialchars($profile_update_success) . "</div>";
            }
            if (!empty($profile_update_errors) && $page === 'profile') {
                echo "<div class='mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'><ul>";
                foreach ($profile_update_errors as $error) {
                    echo "<li>" . htmlspecialchars($error) . "</li>";
                }
                echo "</ul></div>";
            }

            $content_loaded = false;
            $base_views_path = 'teacher_views/'; // Define base path for views

            // Include the appropriate content based on the 'page' GET parameter
            switch ($page) {
                case 'set_language':
                    if (file_exists($base_views_path . 'set_language_content.php')) {
                        // Pass the available_languages to the included file
                        $available_languages_list = $available_languages;
                        include $base_views_path . 'set_language_content.php';
                        $content_loaded = true;
                    }
                    break;
                case 'language_usage':
                   if (file_exists($base_views_path . 'language_usage_content.php')) {
                        include $base_views_path . 'language_usage_content.php';
                        $content_loaded = true;
                    }
                    break;
                case 'profile':
                    // Profile content is kept inline as per user's previous structure
                    ?>
                    <div class="container mx-auto">
                        <div class="card p-6 md:p-8">
                            <h2 class="text-2xl font-semibold text-gray-800 mb-6">My Profile</h2>
                            <div id="profileView">
                                <div class="flex flex-col items-center mb-6">
                                    <img src="<?php echo $profile_page_pic_url; ?>" alt="Profile Picture" class="w-32 h-32 rounded-full object-cover border-4 border-blue-400 mb-4" id="profilePicPreview" onerror="this.onerror=null; this.src='https://placehold.co/120x120/4299E1/FFFFFF?text=<?php echo $teacher_profile_pic_initial; ?>';">
                                    <h3 class="text-xl font-bold text-gray-800"><?php echo htmlspecialchars($teacher_username); ?></h3>
                                    <p class="text-gray-600 text-sm"><?php echo htmlspecialchars($teacher_email); ?></p>
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-gray-700 mb-6">
                                    <div><p class="font-semibold text-gray-500 text-xs uppercase mb-1">Username</p><p class="text-lg"><?php echo htmlspecialchars($teacher_username); ?></p></div>
                                    <div><p class="font-semibold text-gray-500 text-xs uppercase mb-1">Email</p><p class="text-lg"><?php echo htmlspecialchars($teacher_email); ?></p></div>
                                </div>
                                <div class="flex justify-end">
                                    <button id="editProfileButton" class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200 flex items-center shadow-md"><i class="fas fa-edit mr-2"></i>Edit Profile</button>
                                </div>
                            </div>
                            <div id="profileEdit" class="hidden">
                                <form action="update_profile.php" method="POST" enctype="multipart/form-data" class="space-y-6">
                                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                    <input type="hidden" name="teacher_id" value="<?php echo htmlspecialchars($teacher_id); ?>">
                                    <input type="hidden" name="update_profile_action" value="1"> <input type="hidden" name="source_page" value="profile">
                                    <div class="flex flex-col items-center mb-6">
                                        <img src="<?php echo $profile_page_pic_url; ?>" alt="Profile Picture" class="w-32 h-32 rounded-full object-cover border-4 border-blue-400 mb-4" id="profilePicPreview" onerror="this.onerror=null; this.src='https://placehold.co/120x120/4299E1/FFFFFF?text=<?php echo $teacher_profile_pic_initial; ?>';">
                                        <label for="profile_pic" class="cursor-pointer bg-blue-100 text-blue-700 px-4 py-2 rounded-md hover:bg-blue-200 transition-colors"><i class="fas fa-camera mr-2"></i>Change Picture</label>
                                        <input type="file" name="profile_pic" id="profile_pic" class="hidden" accept="image/*">
                                    </div>
                                    <div><label for="name" class="block text-sm font-medium text-gray-700 mb-1">Username</label><input type="text" name="name" id="name" value="<?php echo htmlspecialchars($teacher_username); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required></div>
                                    <div><label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email</label><input type="email" name="email" id="email" value="<?php echo htmlspecialchars($teacher_email); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" required></div>
                                    <div><label for="password" class="block text-sm font-medium text-gray-700 mb-1">New Password (blank to keep current)</label><input type="password" name="password" id="password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div><label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label><input type="password" name="confirm_password" id="confirm_password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div class="flex justify-end space-x-3"><button type="button" id="cancelEditButton" class="px-5 py-2.5 rounded-md text-gray-700 bg-gray-200 hover:bg-gray-300">Cancel</button><button type="submit" class="px-5 py-2.5 rounded-md bg-green-600 text-white hover:bg-green-700 flex items-center shadow-md"><i class="fas fa-save mr-2"></i>Save Changes</button></div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <script>
                        // JavaScript for profile edit functionality
                        document.addEventListener('DOMContentLoaded', () => {
                            const editBtn = document.getElementById('editProfileButton');
                            const cancelBtn = document.getElementById('cancelEditButton');
                            const viewDiv = document.getElementById('profileView');
                            const editDiv = document.getElementById('profileEdit');
                            const picInput = document.getElementById('profile_pic');
                            const picPreview = document.getElementById('profilePicPreview');

                            if(editBtn && cancelBtn && viewDiv && editDiv) {
                                editBtn.addEventListener('click', () => {
                                    viewDiv.classList.add('hidden');
                                    editDiv.classList.remove('hidden');
                                });
                                cancelBtn.addEventListener('click', () => {
                                    editDiv.classList.add('hidden');
                                    viewDiv.classList.remove('hidden');
                                });
                            }
                            if(picInput && picPreview) {
                                picInput.addEventListener('change', function(e) {
                                    if (e.target.files && e.target.files[0]) {
                                        const reader = new FileReader();
                                        reader.onload = function(event) {
                                            picPreview.src = event.target.result;
                                        }
                                        reader.readAsDataURL(e.target.files[0]);
                                    }
                                });
                            }
                        });
                    </script>
                    <?php
                    $content_loaded = true;
                    break;
                case 'dashboard':
                default:
                    if (file_exists($base_views_path . 'dashboard_content.php')) {
                        // Pass the chart data variables to the included file
                        include $base_views_path . 'dashboard_content.php';
                        $content_loaded = true;
                    }
                    break;
            }

            // Fallback message if no content is loaded for the requested page
            if (!$content_loaded && $page !== 'profile') { // Profile content is handled inline
                echo "<div class='p-4 text-sm text-yellow-700 bg-yellow-100 rounded-lg' role='alert'>Content for '<strong>" . htmlspecialchars($page) . "</strong>' not found at expected location.</div>";
            }
            ?>
        </main>
    </div>

    <script>
        // JavaScript for sidebar and dropdown functionality
        document.addEventListener('DOMContentLoaded', function () {
            const sidebar = document.getElementById('sidebar');
            const menuButton = document.getElementById('menuButton'); // For mobile sidebar toggle
            const sidebarToggleBottom = document.getElementById('sidebarToggleBottom'); // For desktop sidebar collapse/expand

            const profileDropdownButton = document.getElementById('profileDropdownButton');
            const profileDropdownMenu = document.getElementById('profileDropdownMenu');
            const profileDropdownContainer = document.getElementById('profileDropdownContainer');

            // Function to toggle desktop sidebar collapse/expand
            function toggleDesktopSidebar() {
                sidebar.classList.toggle('collapsed');
                const icon = sidebarToggleBottom.querySelector('i');
                if (sidebar.classList.contains('collapsed')) {
                    icon.classList.remove('fa-angle-left');
                    icon.classList.add('fa-angle-right');
                } else {
                    icon.classList.remove('fa-angle-right');
                    icon.classList.add('fa-angle-left');
                }
            }
            
            // Event listener for desktop sidebar toggle button
            if (sidebarToggleBottom) {
                sidebarToggleBottom.addEventListener('click', toggleDesktopSidebar);
            }

            // Event listener for mobile menu button to show/hide sidebar
            if (menuButton && sidebar) {
                menuButton.addEventListener('click', () => {
                    sidebar.classList.toggle('-translate-x-full'); // Tailwind class for off-canvas toggle
                });
            }
            
            // Event listeners for profile dropdown menu
            if (profileDropdownButton && profileDropdownMenu && profileDropdownContainer) {
                profileDropdownButton.addEventListener('click', function (event) {
                    event.stopPropagation(); // Prevent document click from closing it immediately
                    profileDropdownMenu.classList.toggle('hidden');
                });
                // Close dropdown if clicked outside
                document.addEventListener('click', function (event) {
                    if (!profileDropdownContainer.contains(event.target) && !profileDropdownMenu.classList.contains('hidden')) {
                        profileDropdownMenu.classList.add('hidden');
                    }
                });
            }

            // Highlight active sidebar item based on current page
            const currentPageForNav = '<?php echo $page; ?>';
            const navLinks = {
                'dashboard': document.getElementById('nav-dashboard'),
                'set_language': document.getElementById('nav-set-language'),
                'language_usage': document.getElementById('nav-language-usage'),
                'profile': document.getElementById('nav-profile')
            };
            if (navLinks[currentPageForNav]) {
                navLinks[currentPageForNav].classList.add('active');
            }
        });
    </script>
</body>
</html>

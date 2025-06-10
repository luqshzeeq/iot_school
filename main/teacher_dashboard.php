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
// language_error is kept here to be displayed as a normal banner for errors,
// as the modal is specifically for success messages.
$language_error = $_SESSION['language_error'] ?? '';
$profile_update_success = $_SESSION['profile_update_success'] ?? '';
$profile_update_errors = $_SESSION['profile_update_errors'] ?? [];

// UNSET language_error if it has been read, to prevent it from persisting on refresh
// NOTE: language_set_success is managed within set_language_content.php for modal display.
unset($_SESSION['language_error'], $_SESSION['profile_update_success'], $_SESSION['profile_update_errors']);


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
    $sql_check = "SELECT teacher_id FROM teacher_daily_languages WHERE teacher_id = ? AND setting_date = ?";
    $stmt_check = $conn->prepare($sql_check);

    if ($stmt_check) {
        $stmt_check->bind_param("is", $teacher_id, $setting_date);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($row_check = $result_check->fetch_assoc()) {
            // If a record exists, update the language_id.
            $sql_update = "UPDATE teacher_daily_languages SET language_id = ? WHERE teacher_id = ? AND setting_date = ?";
            $stmt_update = $conn->prepare($sql_update);
            if ($stmt_update) {
                $stmt_update->bind_param("iis", $language_id, $teacher_id, $setting_date);
                if ($stmt_update->execute()) {
                    // Set a specific session flag for the modal to pick up
                    $_SESSION['language_set_success'] = true; // THIS IS THE KEY CHANGE
                } else {
                    $_SESSION['language_error'] = "Failed to update daily language: " . $stmt_update->error;
                }
                $stmt_update->close();
            } else {
                $_SESSION['language_error'] = "Database error (update prepare): " . $conn->error;
            }
        } else {
            // If no record exists, insert a new one.
            $sql_insert = "INSERT INTO teacher_daily_languages (teacher_id, setting_date, language_id) VALUES (?, ?, ?)";
            $stmt_insert = $conn->prepare($sql_insert);
            if ($stmt_insert) {
                $stmt_insert->bind_param("isi", $teacher_id, $setting_date, $language_id);
                if ($stmt_insert->execute()) {
                    // Set a specific session flag for the modal to pick up
                    $_SESSION['language_set_success'] = true; // THIS IS THE KEY CHANGE
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
               $current_language_name_for_selected_date_display = htmlspecialchars($row_current['language_name']) . " (" . $display_date_str_for_language . ")";
            } else { // For set_language page, it shows for the $selected_date_str
               $current_language_name_for_selected_date_display = htmlspecialchars($row_current['language_name']);
            }
        } else { // No language set for the specific date
            if ($page === 'dashboard' || $page === 'profile') {
                $current_language_name_for_selected_date_display = "No language set for today";
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
    <link rel="icon" href="assets/images/brand/unimaplogo.png" type="image/unimaplogo">
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/2.0.8/css/dataTables.dataTables.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f7fafc; }
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
        .sidebar.collapsed .sidebar-item.active { border-left-width: 0px; border-bottom: 4px solidrgb(255, 255, 255); border-radius: 0.5rem; }

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

        /* General Calendar Grid style - day-specific styles including .current-day will be in dashboard_content.php */
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem; /* Tailwind's gap-2 */
        }
        /* The specific .calendar-day.current-day styles are moved to dashboard_content.php */


        /* Rotate animation for the sidebar toggle icon */
        #sidebarToggleBottom i {
            transition: transform 0.3s ease-in-out; /* Add transition for smooth rotation */
        }

        .sidebar.collapsed #sidebarToggleBottom i {
            transform: rotate(180deg); /* Rotate the icon when the sidebar is collapsed */
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Transparent overlay */
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }

        .modal-content {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            width: 300px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .icon {
            width: 50px;
            height: 50px;
            margin-bottom: 20px;
        }

        p {
            font-size: 16px;
            margin-bottom: 20px;
        }

        .buttons {
            display: flex;
            justify-content: space-around;
        }

        .btn {
            padding: 10px 20px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }

        .btn:hover {
            background-color: #45a049;
        }

        #cancelBtn {
            background-color: #FF6F61;
        }

        #cancelBtn:hover {
            background-color: #ff5c4b;
        }

        /* Styles for the dropdown button text and arrow when active/hover */
        #dropdownAvatarNameButton {
            color: #1f2937; /* Default gray/dark color for text */
            transition: color 0.1s ease-in-out; /* Smooth transition for text color */
        }

        #dropdownAvatarNameButton.active-dropdown-button,
        #dropdownAvatarNameButton:hover {
            color: #2563eb; /* Blue color on active or hover */
        }

        #dropdownAvatarNameButton svg {
            transition: stroke 0.1s ease-in-out; /* Smooth transition for SVG stroke color */
        }

        #dropdownAvatarNameButton.active-dropdown-button svg,
        #dropdownAvatarNameButton:hover svg {
            stroke: #2563eb; /* Blue color for the SVG arrow on active or hover */
        }

        /* NEW/MODIFIED: Specific styles for the dropdown menu background and items */
        #dropdownAvatarName {
            /* Changed from dark:bg-gray-700 to a more visible color */
            background-color: #ffffff; /* White background for good contrast */
            border: 1px solid #e5e7eb; /* Subtle border */
        }

        #dropdownAvatarName .px-4.py-3 {
            background-color: #f9fafb; /* Very light gray for the header background */
            border-bottom: 1px solid #e5e7eb; /* Separator for the header */
            color: #1f2937; /* Ensure username/email text is dark and visible */
        }

        #dropdownAvatarName .px-4.py-3 .font-medium {
             color: #1f2937; /* Darker color for username */
        }
        #dropdownAvatarName .px-4.py-3 .truncate {
            color: #4b5563; /* Slightly lighter for email */
        }


        #dropdownAvatarName .py-2 ul {
            background-color: #ffffff; /* Ensure ul background matches dropdown */
        }

        #dropdownAvatarName .py-2 a {
            display: flex; /* To align icon and text */
            align-items: center; /* Vertically align items */
            color: #4a5568; /* Default text color for items */
            transition: background-color 0.1s ease-in-out, color 0.1s ease-in-out; /* Smooth transitions */
        }

        /* Hover state for dropdown menu items */
        #dropdownAvatarName .py-2 a:hover {
            background-color: #e0f2f7; /* Light blue on hover for better contrast */
            color: #0c4a6e; /* Darker blue text on hover */
        }

        #dropdownAvatarName .py-2 a i {
            margin-right: 0.75rem; /* Space between icon and text */
            width: 1.25rem; /* Standard icon width */
            text-align: center; /* Center the icon */
            color: #6b7280; /* A slightly darker gray for icons */
            transition: color 0.1s ease-in-out;
        }

        #dropdownAvatarName .py-2 a:hover i {
            color: #0c4a6e; /* Darker blue icon color on hover */
        }
        
        /* Style for the Sign out button */
        #logoutForm #nav-logout-dropdown { /* Targeted specifically for the anchor within the form */
            display: flex; /* To align icon and text */
            align-items: center; /* Vertically align items */
            color: #4a5568; /* Default text color for sign out */
            transition: background-color 0.1s ease-in-out, color 0.1s ease-in-out;
            width: 100%; /* Ensure button takes full width */
            text-align: left; /* Align text to the left */
            padding: 0.5rem 1rem; /* Adjust padding for consistency */
        }

        #logoutForm #nav-logout-dropdown:hover {
            background-color: #ffe4e6; /* Light red on hover for sign out */
            color: #ef4444; /* Red text on hover */
        }
        
        #logoutForm #nav-logout-dropdown i {
            margin-right: 0.75rem;
            width: 1.25rem;
            text-align: center;
            color: #6b7280;
            transition: color 0.1s ease-in-out;
        }

        #logoutForm #nav-logout-dropdown:hover i {
            color: #ef4444; /* Red icon color on hover */
        }
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
        <div class="p-4 text-center">
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
                    <button id="dropdownAvatarNameButton" data-dropdown-toggle="dropdownAvatarName" class="flex items-center text-sm pe-1 font-medium text-gray-900 rounded-full hover:text-blue-600 dark:hover:text-blue-500 md:me-0 focus:ring-4 focus:ring-gray-100 dark:focus:ring-gray-700 dark:text-white" type="button">
                        <span class="sr-only">Open user menu</span>
                        <img class="w-8 h-8 me-2 rounded-full" src="<?php echo $profile_page_pic_url; ?>" alt="user photo" onerror="this.onerror=null; this.src='https://placehold.co/32x32/4299E1/FFFFFF?text=<?php echo $teacher_profile_pic_initial; ?>';">
                        <?php echo htmlspecialchars($teacher_username); ?>
                        <svg class="w-2.5 h-2.5 ms-3" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 10 6">
                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m1 1 4 4 4-4"/>
                        </svg>
                    </button>

                    <div id="dropdownAvatarName" class="z-10 hidden absolute right-0 mt-5 bg-white divide-y divide-gray-200 rounded-lg shadow-sm w-44">
                        <div class="px-4 py-3 text-sm text-gray-900">
                            <div class="font-medium "><?php echo htmlspecialchars($teacher_username); ?></div>
                            <div class="truncate"><?php echo htmlspecialchars($teacher_email); ?></div>
                        </div>
                        <ul class="py-2 text-sm text-gray-700" aria-labelledby="dropdownInformdropdownAvatarNameButtonationButton">
                            <li>
                                <a href="teacher_dashboard.php?page=dashboard" class="block px-4 py-2 hover:bg-gray-100">
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </li>
                            <li>
                                <a href="teacher_dashboard.php?page=profile" class="block px-4 py-2 hover:bg-gray-100">
                                    <i class="fas fa-user-cog"></i> Profile Settings
                                </a>
                            </li>
                        </ul>
                        <div class="py-2">
                             <form action="logout_teacher.php" method="post" role="none" id="logoutForm">
                                <a href="#" id="nav-logout-dropdown" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 w-full text-left">
                                    <i class="fas fa-sign-out-alt"></i> Sign out
                                </a>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main class="flex-1 p-6 md:p-8 overflow-y-auto">
            <?php
            // Display error messages (not success messages for set_language, as that's handled by the modal)
            if (!empty($language_error) && $page === 'set_language') {
                echo "<div class='mb-4 p-4 text-sm text-red-700 bg-red-100 rounded-lg' role='alert'>" . htmlspecialchars($language_error) . "</div>";
            }

            // Display profile update success/error messages
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
                        // For the set_language page, we also pass the language_set_success session variable
                        // directly to the included file so it can trigger the modal.
                        $language_set_success = $_SESSION['language_set_success'] ?? false;
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
                                    <div><label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label><input type="password" name="current_password" id="current_password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Required to update profile"></div>
                                    <div><label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password </label><input type="password" name="new_password" id="new_password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" placeholder="Leave blank to keep current"></div>
                                    <div><label for="confirm_new_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label><input type="password" name="confirm_new_password" id="confirm_new_password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                                    <div class="flex justify-end space-x-3">
                                        <button type="button" id="cancelEditButton" class="px-6 py-3 bg-gray-300 text-gray-800 rounded-md hover:bg-gray-400 transition-colors duration-200">Cancel</button>
                                        <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors duration-200 flex items-center shadow-md"><i class="fas fa-save mr-2"></i>Save </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php
                    $content_loaded = true;
                    break;
                case 'dashboard':
                default:
                    if (file_exists($base_views_path . 'dashboard_content.php')) {
                        include $base_views_path . 'dashboard_content.php';
                        $content_loaded = true;
                    }
                    break;
            }

            if (!$content_loaded) {
                // Fallback for unknown pages
                echo "<div class='text-center py-10 text-gray-500'>Page not found or content not available.</div>";
            }
            ?>
        </main>
    </div>

    <script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const menuButton = document.getElementById('menuButton');
    const sidebarToggleBottom = document.getElementById('sidebarToggleBottom');
    
    const dropdownAvatarNameButton = document.getElementById('dropdownAvatarNameButton');
    const dropdownAvatarName = document.getElementById('dropdownAvatarName');
    const profileDropdownContainer = document.getElementById('profileDropdownContainer');
    
    const navItems = document.querySelectorAll('.sidebar-item');

    // Set active navigation item based on current page
    const currentPage = new URLSearchParams(window.location.search).get('page') || 'dashboard';
    const activeNavItem = document.getElementById(`nav-${currentPage}`);
    if (activeNavItem) {
        activeNavItem.classList.add('active');
    }

    // Sidebar toggle functionality
    menuButton.addEventListener('click', function() {
        sidebar.classList.toggle('-translate-x-full');
    });

    sidebarToggleBottom.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        sidebar.classList.toggle('w-64');
        sidebar.classList.toggle('w-20');
    });

    // Profile dropdown functionality
    if (dropdownAvatarNameButton && dropdownAvatarName) {
        dropdownAvatarNameButton.addEventListener('click', function() {
            dropdownAvatarName.classList.toggle('hidden');
            dropdownAvatarNameButton.classList.toggle('active-dropdown-button');
        });

        // Close dropdown if clicked outside
        document.addEventListener('click', function(event) {
            if (!profileDropdownContainer.contains(event.target)) {
                dropdownAvatarName.classList.add('hidden');
                dropdownAvatarNameButton.classList.remove('active-dropdown-button');
            }
        });
    }

    // Profile edit/view toggle
    const editProfileButton = document.getElementById('editProfileButton');
    const cancelEditButton = document.getElementById('cancelEditButton');
    const profileView = document.getElementById('profileView');
    const profileEdit = document.getElementById('profileEdit');
    const profilePicInput = document.getElementById('profile_pic');
    const profilePicPreview = document.getElementById('profilePicPreview');

    if (editProfileButton) {
        editProfileButton.addEventListener('click', function() {
            profileView.classList.add('hidden');
            profileEdit.classList.remove('hidden');
        });
    }

    if (cancelEditButton) {
        cancelEditButton.addEventListener('click', function() {
            profileEdit.classList.add('hidden');
            profileView.classList.remove('hidden');
        });
    }

    if (profilePicInput && profilePicPreview) {
        profilePicInput.addEventListener('change', function(event) {
            if (event.target.files && event.target.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profilePicPreview.src = e.target.result;
                };
                reader.readAsDataURL(event.target.files[0]);
            }
        });
    }

    // --- Logout Confirmation Modal --- //
    const logoutModal = document.getElementById('logoutConfirmationModal');
    const logoutButton = document.getElementById('nav-logout-dropdown'); // This is now an anchor tag
    const cancelLogoutButton = document.getElementById('cancelLogoutBtn');
    const confirmLogoutButton = document.getElementById('confirmLogoutBtn');
    const logoutForm = document.getElementById('logoutForm');

    if (logoutButton && logoutModal && logoutForm) {
        logoutButton.addEventListener('click', function(event) {
            event.preventDefault(); // Prevent default link behavior
            logoutModal.classList.remove('hidden');
            logoutModal.style.display = 'flex';
        });

        cancelLogoutButton.addEventListener('click', function() {
            logoutModal.classList.add('hidden');
            logoutModal.style.display = 'none';
        });

        confirmLogoutButton.addEventListener('click', function() {
            logoutForm.submit();
        });

        logoutModal.addEventListener('click', function(event) {
            if (event.target === logoutModal) {
               logoutModal.classList.add('hidden');
               logoutModal.style.display = 'none';
            }
        });
    }
});

    </script>

<div id="logoutConfirmationModal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white p-8 rounded-lg shadow-xl text-center max-w-sm w-full mx-4">
        <div class="flex justify-center mb-4">
            <svg class="w-16 h-16 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
            </svg>
        </div>
        <h2 class="text-2xl font-bold text-gray-800 mb-2">Logout</h2>
        <p class="text-gray-600 mb-6">Are you sure you want to log out?</p>
        <div class="flex flex-col space-y-3">
             <button id="confirmLogoutBtn" class="px-4 py-3 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition-colors duration-200">
                Log Out
            </button>
            <button id="cancelLogoutBtn" class="px-4 py-3 bg-gray-300 text-gray-800 font-semibold rounded-lg hover:bg-gray-400 transition-colors duration-200">
                Cancel
            </button>
        </div>
    </div>
</div>
<script type="text/javascript" src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script type="text/javascript" src="https://cdn.datatables.net/2.0.8/js/dataTables.min.js"></script>
</body>
</html>
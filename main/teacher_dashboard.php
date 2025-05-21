<?php
session_start();
include 'db_connection.php'; // Ensure this path is correct

// Redirect if not logged in or role is not 'teacher'
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
$language_message = '';
$language_error = '';

// Determine the date to work with: from GET parameter or default to today
$selected_date_str = isset($_GET['setting_date']) ? $_GET['setting_date'] : date('Y-m-d');
// Validate the date format (basic validation)
if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $selected_date_str)) {
    $selected_date_str = date('Y-m-d'); // Default to today if format is invalid
    // Optionally set an error message if you want to inform the user about the default
    // $language_error = "Invalid date format provided in URL. Defaulting to today.";
}


// Handle form submission for setting Language of the Day
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['set_language_action'])) {
    $language_id_input = $_POST['language_id']; 
    $setting_date_input_str = $_POST['setting_date']; 

    if (!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])$/", $setting_date_input_str)) {
        $language_error = "Invalid date submitted. Please try again.";
    } elseif (empty($language_id_input)) {
        $language_error = "Please select a language.";
    } else {
        $sql_upsert = "INSERT INTO teacher_daily_languages (teacher_id, setting_date, language_id) VALUES (?, ?, ?)
                       ON DUPLICATE KEY UPDATE language_id = VALUES(language_id)";
        
        $stmt_upsert = $conn->prepare($sql_upsert);
        if ($stmt_upsert) {
            $stmt_upsert->bind_param("isi", $teacher_id, $setting_date_input_str, $language_id_input);
            if ($stmt_upsert->execute()) {
                $language_message = "Language for " . htmlspecialchars($setting_date_input_str) . " updated successfully!";
                $selected_date_str = $setting_date_input_str; // Ensure current view reflects the submitted date
            } else {
                $language_error = "Failed to update language: " . $stmt_upsert->error;
            }
            $stmt_upsert->close();
        } else {
            $language_error = "Error preparing SQL statement: " . $conn->error;
        }
    }
}

// Fetch the list of available languages
$sql_languages = "SELECT id, language_name FROM languages ORDER BY language_name ASC";
$languages_result = mysqli_query($conn, $sql_languages);
$available_languages = [];
if ($languages_result && mysqli_num_rows($languages_result) > 0) {
    while ($lang_row = mysqli_fetch_assoc($languages_result)) {
        $available_languages[] = $lang_row;
    }
}

// Fetch the current language set for the $selected_date_str
$current_language_for_selected_date_id = '';
$current_language_name_for_selected_date_display = "Not Set for " . htmlspecialchars($selected_date_str);

$sql_current_setting = "SELECT l.id, l.language_name 
                        FROM teacher_daily_languages tdl
                        JOIN languages l ON tdl.language_id = l.id 
                        WHERE tdl.teacher_id = ? AND tdl.setting_date = ?";
$stmt_current_setting = $conn->prepare($sql_current_setting);
if ($stmt_current_setting) {
    $stmt_current_setting->bind_param("is", $teacher_id, $selected_date_str);
    if ($stmt_current_setting->execute()) {
        $result_current_setting = $stmt_current_setting->get_result();
        if ($row_current = $result_current_setting->fetch_assoc()) {
            $current_language_for_selected_date_id = $row_current['id'];
            $current_language_name_for_selected_date_display = htmlspecialchars($row_current['language_name']) . " (ID: " . htmlspecialchars($row_current['id']) . ") for " . htmlspecialchars($selected_date_str);
        }
    }
    $stmt_current_setting->close();
}


// Fetch teacher profile details
$teacher_username = "Teacher"; 
$teacher_email = "teacher@example.com"; 
$teacher_profile_pic_initial = 'T';
$teacher_actual_profile_pic = ''; 

$sql_profile = "SELECT username, email FROM users WHERE id = ?";
$stmt_profile = $conn->prepare($sql_profile);
if ($stmt_profile) {
    $stmt_profile->bind_param("i", $teacher_id);
    if ($stmt_profile->execute()) {
        $result_profile = $stmt_profile->get_result();
        if ($profile_data_row = $result_profile->fetch_assoc()) {
            $teacher_username = htmlspecialchars($profile_data_row['username']);
            $teacher_email = htmlspecialchars($profile_data_row['email'] ?? 'email_not_set@example.com');
            if (!empty($teacher_username)) {
                $teacher_profile_pic_initial = strtoupper(substr($teacher_username, 0, 1));
            }
        }
    }
    $stmt_profile->close();
}

$teacher_profile_pic_url = !empty($teacher_actual_profile_pic) ? htmlspecialchars($teacher_actual_profile_pic) : 'https://placehold.co/120x120/007bff/ffffff?text=' . $teacher_profile_pic_initial;
$sidebar_avatar_url = !empty($teacher_actual_profile_pic) ? htmlspecialchars($teacher_actual_profile_pic) : 'https://placehold.co/40x40/7f9cf5/ffffff?text=' . $teacher_profile_pic_initial;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - <?php echo $teacher_username; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background-color: #f0f2f5; }
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #888; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #555; }
        .sidebar-item { transition: background-color 0.2s ease-in-out, color 0.2s ease-in-out; }
        .sidebar-item.active, .sidebar-item:hover { background-color: #4a5568; color: #ffffff; }
        .sidebar-item.active i, .sidebar-item:hover i { color: #ffffff; }
        #languageSelectPage { 
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23007AFF%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13-5.4H18.4c-5%200-9.3%201.8-12.9%205.4A17.6%2017.6%200%200%200%200%2082.2c0%205%201.8%209.3%205.4%2012.9l128%20127.9c3.6%203.6%207.8%205.4%2012.8%205.4s9.2-1.8%2012.8-5.4L287%2095c3.5-3.5%205.4-7.8%205.4-12.8%200-5-1.9-9.2-5.4-12.8z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat; background-position: right .7em top 50%; background-size: .65em auto; padding-right: 2.5em;
        }
        input[type="date"]::-webkit-calendar-picker-indicator {
            cursor: pointer;
            opacity: 0.6;
            filter: invert(0.4); 
        }
        input[type="date"]:hover::-webkit-calendar-picker-indicator {
            opacity: 1;
        }
    </style>
</head>
<body class="flex h-screen">

    <aside class="w-64 bg-gray-800 text-gray-100 flex flex-col fixed h-full md:relative transition-transform duration-300 ease-in-out -translate-x-full md:translate-x-0" id="sidebar">
        <div class="p-4 flex items-center justify-center">
            <img src="img/teacher_sidebar.png" alt="School Logo" class="h-12 w-auto" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';" >
            <span class="ml-3 text-xl font-bold" style="display:none;">Teacher Panel</span>
        </div>
        <nav class="flex-grow p-4 space-y-1">
            <a href="#" id="nav-dashboard" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300 active">
                <i class="fas fa-tachometer-alt w-6 text-center text-gray-400"></i>
                <span>Dashboard</span>
            </a>
            <a href="#" id="nav-set-language" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300">
                <i class="fas fa-calendar-alt w-6 text-center text-gray-400"></i> 
                <span>Set Daily Language</span>
            </a>
            <a href="#" id="nav-language-usage" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300">
                <i class="fas fa-chart-bar w-6 text-center text-gray-400"></i>
                <span>Language Usage</span>
            </a>
            <a href="logout_teacher.php" id="nav-logout" class="sidebar-item flex items-center space-x-3 px-4 py-3 rounded-lg text-gray-300">
                <i class="fas fa-sign-out-alt w-6 text-center text-gray-400"></i>
                <span>Logout</span>
            </a>
        </nav>
        <div class="p-4 border-t border-gray-700">
            <div class="flex items-center space-x-3">
                <img src="<?php echo $sidebar_avatar_url; ?>" alt="Teacher Avatar" id="sidebarAvatar" class="w-10 h-10 rounded-full object-cover" onerror="this.src='https://placehold.co/40x40/7f9cf5/ffffff?text=<?php echo $teacher_profile_pic_initial; ?>'; this.onerror=null;">
                <div><p class="text-sm font-semibold" id="sidebarUserName"><?php echo $teacher_username; ?></p><p class="text-xs text-gray-400" id="sidebarUserEmail"><?php echo $teacher_email; ?></p></div>
            </div>
        </div>
    </aside>

    <main class="flex-1 p-4 md:p-8 overflow-y-auto">
        <div class="md:hidden flex justify-between items-center mb-6">
            <div class="flex items-center"><img src="img/teacher_sidebar.png" alt="School Logo" class="h-8 w-auto mr-2" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';"><span class="text-xl font-bold text-gray-700" style="display:none;">Teacher Panel</span></div>
            <button id="menuButton" class="text-gray-600 focus:outline-none"><i class="fas fa-bars text-2xl"></i></button>
        </div>

        <div id="content-dashboard" class="content-section">
            <header class="mb-8"><h1 class="text-3xl font-bold text-gray-800">Dashboard</h1><p class="text-gray-600">Welcome back, <span id="profileNameHeader"><?php echo $teacher_username; ?></span>!</p></header>
            <div class="grid grid-cols-1 gap-6"> 
                <div class="bg-white p-6 rounded-xl shadow-lg"> 
                    <div class="flex flex-col sm:flex-row items-center sm:items-start space-y-4 sm:space-y-0 sm:space-x-6 mb-6">
                        <div class="relative"><img id="profileImage" src="<?php echo $teacher_profile_pic_url; ?>" alt="Profile Picture" class="w-32 h-32 rounded-full object-cover border-4 border-blue-500" onerror="this.src='https://placehold.co/120x120/007bff/ffffff?text=<?php echo $teacher_profile_pic_initial; ?>'; this.onerror=null;"><label for="profileImageUpload" class="absolute bottom-0 right-0 bg-blue-600 text-white p-2 rounded-full cursor-pointer hover:bg-blue-700 transition"><i class="fas fa-camera"></i></label><input type="file" id="profileImageUpload" class="hidden" accept="image/*"></div>
                        <div class="flex-1 text-center sm:text-left"><h2 class="text-2xl font-semibold text-gray-800" id="profileNameDisplay"><?php echo $teacher_username; ?></h2><p class="text-gray-600" id="profileEmailDisplay"><?php echo $teacher_email; ?></p><p class="text-sm text-gray-500 mt-1">Role: Teacher</p></div>
                        <button id="editProfileButton" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition duration-200 self-center sm:self-start"><i class="fas fa-edit mr-2"></i>Edit Profile</button>
                    </div>
                    <div id="profileView">
                        <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Profile Details</h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div><label class="block text-sm font-medium text-gray-500">Full Name</label><p class="mt-1 text-gray-800" id="viewFullName"><?php echo $teacher_username; ?></p></div>
                            <div><label class="block text-sm font-medium text-gray-500">Email Address</label><p class="mt-1 text-gray-800" id="viewEmail"><?php echo $teacher_email; ?></p></div>
                            <div><label class="block text-sm font-medium text-gray-500">Language for <?php echo htmlspecialchars($selected_date_str); ?></label><p class="mt-1 text-gray-800" id="viewPreferredLanguage"><?php echo $current_language_name_for_selected_date_display; ?></p></div>
                            <div><label class="block text-sm font-medium text-gray-500">Account Status</label><p class="mt-1 text-green-600 font-semibold">Active</p></div>
                        </div>
                    </div>
                    <div id="profileEdit" class="hidden">
                        <h3 class="text-xl font-semibold text-gray-700 mb-4 border-b pb-2">Edit Profile Information</h3>
                        <form id="profileEditForm" method="POST" action="update_profile.php" enctype="multipart/form-data">
                            <input type="hidden" name="teacher_id" value="<?php echo $teacher_id; ?>">
                            <div class="mb-4"><label for="editName" class="block text-sm font-medium text-gray-700">Full Name</label><input type="text" id="editName" name="name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" value="<?php echo $teacher_username; ?>"></div>
                            <div class="mb-4"><label for="editEmail" class="block text-sm font-medium text-gray-700">Email Address</label><input type="email" id="editEmail" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm" value="<?php echo $teacher_email; ?>"></div>
                            <div class="mb-4"><label for="editProfilePic" class="block text-sm font-medium text-gray-700">New Profile Picture (optional)</label><input type="file" id="editProfilePic" name="profile_pic" class="mt-1 block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"></div>
                            <div class="mb-6"><label for="editPassword" class="block text-sm font-medium text-gray-700">New Password (optional)</label><input type="password" id="editPassword" name="password" placeholder="Leave blank to keep current password" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"></div>
                            <div class="flex justify-end space-x-3"><button type="button" id="cancelEditButton" class="bg-gray-300 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-400 transition duration-200">Cancel</button><button type="submit" class="bg-green-500 text-white px-6 py-2 rounded-lg hover:bg-green-600 transition duration-200">Save Changes</button></div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="mt-8 bg-white p-6 rounded-xl shadow-lg"><h3 class="text-xl font-semibold text-gray-700 mb-4">Recent Activity</h3><p class="text-gray-600">Activity feed will be displayed here...</p></div>
        </div>

        <div id="content-set-language" class="content-section hidden">
            <header class="mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Set Daily Language</h1>
                <p class="text-gray-600">Select a date and choose the language for that day.</p>
            </header>
            <div class="bg-white p-6 md:p-8 rounded-xl shadow-lg max-w-xl mx-auto">
                <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?setting_date=<?php echo htmlspecialchars($selected_date_str); ?>" id="dailyLanguageForm">
                    <input type="hidden" name="set_language_action" value="1">
                    
                    <div class="mb-6">
                        <label for="setting_date_picker" class="block text-sm font-medium text-gray-700 mb-1">Select Date:</label>
                        <input type="date" id="setting_date_picker" name="setting_date" 
                               value="<?php echo htmlspecialchars($selected_date_str); ?>" 
                               class="w-full bg-gray-50 border border-gray-300 text-gray-900 p-3 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm"
                               required>
                    </div>

                    <div class="mb-6">
                        <label for="languageSelectPage" class="block text-sm font-medium text-gray-700 mb-1">Select Language for <span id="selectedDateDisplay" class="font-semibold"><?php echo htmlspecialchars($selected_date_str); ?></span>:</label>
                        <select id="languageSelectPage" name="language_id" class="w-full bg-gray-50 border border-gray-300 text-gray-900 p-3 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm" required>
                            <option value="">-- Choose a Language --</option>
                            <?php foreach ($available_languages as $lang): ?>
                                <option value="<?php echo htmlspecialchars($lang['id']); ?>" <?php echo $current_language_for_selected_date_id == $lang['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($lang['language_name']); ?> (ID: <?php echo htmlspecialchars($lang['id']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-4 rounded-lg text-base transition duration-150">
                        Save Language
                    </button>
                    <?php if (!empty($language_message)): ?>
                        <p class='text-green-600 text-sm mt-4 text-center'><?php echo $language_message; ?></p>
                    <?php endif; ?>
                    <?php if (!empty($language_error)): ?>
                        <p class='text-red-600 text-sm mt-4 text-center'><?php echo $language_error; ?></p>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div id="content-language-usage" class="content-section hidden">
            <header class="mb-8"><h1 class="text-3xl font-bold text-gray-800">Language Usage Data</h1><p class="text-gray-600">Analytics and reports on language interactions.</p></header>
            <div class="bg-white p-6 rounded-xl shadow-lg"><p class="text-gray-700">Language usage statistics and charts will be displayed here...</p><div class="mt-6"><h4 class="text-lg font-semibold text-gray-700 mb-2">Example: Language Distribution</h4><div class="w-full h-64 bg-gray-200 rounded-lg flex items-center justify-center"><p class="text-gray-500">Chart Placeholder</p></div></div></div>
        </div>
    </main>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const sidebar = document.getElementById('sidebar');
        const menuButton = document.getElementById('menuButton');
        const mainContentNavLinks = document.querySelectorAll('#nav-dashboard, #nav-set-language, #nav-language-usage'); 
        const logoutLink = document.getElementById('nav-logout');
        const contentSections = document.querySelectorAll('.content-section');

        const profileImage = document.getElementById('profileImage');
        const profileImageUpload = document.getElementById('profileImageUpload');
        const sidebarAvatar = document.getElementById('sidebarAvatar');

        const editProfileButton = document.getElementById('editProfileButton');
        const cancelEditButton = document.getElementById('cancelEditButton');
        const profileView = document.getElementById('profileView');
        const profileEdit = document.getElementById('profileEdit');
        const profileEditForm = document.getElementById('profileEditForm');
        
        const initialProfileData = {
            name: "<?php echo addslashes($teacher_username); ?>",
            email: "<?php echo addslashes($teacher_email); ?>",
            profilePic: "<?php echo addslashes($teacher_profile_pic_url); ?>"
        };

        const datePicker = document.getElementById('setting_date_picker');
        const selectedDateDisplay = document.getElementById('selectedDateDisplay');
        // const selectedDateButtonDisplay = document.getElementById('selectedDateButtonDisplay'); // This span is now removed from button
        const dailyLanguageForm = document.getElementById('dailyLanguageForm');

        if (menuButton) {
            menuButton.addEventListener('click', () => {
                sidebar.classList.toggle('-translate-x-full');
            });
        }
        
        function updateProfileImageDisplays(newSrc) {
            if (profileImage) profileImage.src = newSrc;
            if (sidebarAvatar) sidebarAvatar.src = newSrc;
        }

        function showContent(targetId) {
            contentSections.forEach(section => section.classList.add('hidden'));
            const activeSection = document.getElementById(targetId);
            if (activeSection) activeSection.classList.remove('hidden');
        }

        mainContentNavLinks.forEach(item => {
            item.addEventListener('click', function (e) {
                e.preventDefault(); 
                mainContentNavLinks.forEach(i => i.classList.remove('active'));
                this.classList.add('active');
                
                const targetContentId = 'content-' + this.id.substring(4); 
                showContent(targetContentId);

                if (this.id === 'nav-set-language') {
                    const urlParams = new URLSearchParams(window.location.search);
                    const dateFromUrl = urlParams.get('setting_date') || '<?php echo date('Y-m-d'); ?>';
                    if (datePicker) datePicker.value = dateFromUrl;
                    if (selectedDateDisplay) selectedDateDisplay.textContent = dateFromUrl;
                    // if (selectedDateButtonDisplay) selectedDateButtonDisplay.textContent = dateFromUrl; // Not needed anymore
                     if (dailyLanguageForm) { 
                        dailyLanguageForm.action = `<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?setting_date=${dateFromUrl}`;
                    }
                }
                
                if (window.innerWidth < 768 && !sidebar.classList.contains('-translate-x-full')) {
                    sidebar.classList.add('-translate-x-full');
                }
            });
        });

        if(logoutLink) {
            logoutLink.addEventListener('click', function(e) {
                if (!confirm('Are you sure you want to logout?')) {
                    e.preventDefault(); 
                }
            });
        }

        if (datePicker) {
            datePicker.addEventListener('change', function() {
                const newDate = this.value;
                window.location.href = `<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?setting_date=${newDate}&nav=set-language`;
            });
        }

        if (profileImageUpload) {
            profileImageUpload.addEventListener('change', function (event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function (e) { updateProfileImageDisplays(e.target.result); }
                    reader.readAsDataURL(file);
                }
            });
        }

        if (editProfileButton) {
            editProfileButton.addEventListener('click', () => {
                if(profileView) profileView.classList.add('hidden');
                if(profileEdit) profileEdit.classList.remove('hidden');
                editProfileButton.classList.add('hidden');
            });
        }

        if (cancelEditButton) {
            cancelEditButton.addEventListener('click', () => {
                if(profileView) profileView.classList.remove('hidden');
                if(profileEdit) profileEdit.classList.add('hidden');
                if(editProfileButton) editProfileButton.classList.remove('hidden');
                if(profileEditForm) {
                    profileEditForm.reset(); 
                    document.getElementById('editName').value = initialProfileData.name;
                    document.getElementById('editEmail').value = initialProfileData.email;
                }
                updateProfileImageDisplays(initialProfileData.profilePic); 
            });
        }
        
        const urlParams = new URLSearchParams(window.location.search);
        const navParam = urlParams.get('nav');
        const dateSubmitted = urlParams.get('setting_date');

        if (navParam === 'set-language' || (dateSubmitted && (<?php echo json_encode(!empty($language_message) || !empty($language_error)); ?>))) {
            document.getElementById('nav-set-language').click();
            if (datePicker && dateSubmitted) { 
                datePicker.value = dateSubmitted;
                 if (selectedDateDisplay) selectedDateDisplay.textContent = dateSubmitted;
                 // if (selectedDateButtonDisplay) selectedDateButtonDisplay.textContent = dateSubmitted; // Not needed
                 if (dailyLanguageForm) {
                    dailyLanguageForm.action = `<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?setting_date=${dateSubmitted}`;
                }
            }
        } else {
            document.getElementById('nav-dashboard').click(); 
        }
    });
    </script>
</body>
</html>
<?php
if (isset($conn)) {
    $conn->close(); 
}
?>

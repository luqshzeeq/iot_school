<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

// --- FOR DEBUGGING: Enable all error reporting. REMOVE THIS ON PRODUCTION. ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --- END DEBUGGING SETTINGS ---

// --- 1. Admin Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Assuming ../index.php is the correct path
    exit(); // Always exit after a header redirect
}

// --- 2. DB Connection ---
include 'db_connection.php'; // Ensure this file exists

// Verify database connection
if (!$conn) {
    $_SESSION['error_message'] = "Database connection failed.";
    header("Location: admin_dashboard.php"); // Redirect to show the error
    exit();
}

// --- 3. Full PHP Logic for Managing Languages ---
$search_query = trim($_GET['search_query'] ?? '');
unset($_SESSION['error_message'], $_SESSION['success_message']); // Clear existing messages just in case

$language_to_edit_id = null;
$language_to_edit_name = '';

// Added these variables for modal messages
$modal_success_message = null;
$modal_error_message = null;

function get_redirect_url_with_search($base_url, $query) {
    return $query ? $base_url . "?search_query=" . urlencode($query) : $base_url;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect_url = get_redirect_url_with_search("admin_dashboard.php", $_POST['search_query'] ?? '');
    
    $action_status_type = '';
    $action_status_message = '';

    if (isset($_POST['add_language'])) {
        $new_language_name = trim($_POST['language_name'] ?? '');
        if (!empty($new_language_name)) {
            $stmt = $conn->prepare("INSERT INTO languages (language_name, created_by) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("si", $new_language_name, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $action_status_type = 'success';
                    $action_status_message = "Language '" . htmlspecialchars($new_language_name) . "' added successfully.";
                } else {
                    $action_status_type = 'error';
                    $action_status_message = "Error adding language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $action_status_type = 'error';
                $action_status_message = "Error preparing add language statement: " . $conn->error;
            }
        } else {
            $action_status_type = 'error';
            $action_status_message = "Language name cannot be empty.";
        }
    }
    if (isset($_POST['delete_language'])) {
        $language_id = filter_var($_POST['language_id_to_delete'], FILTER_VALIDATE_INT);
        if ($language_id !== false) {
            // **IMPROVEMENT**: Check if the language is in use before deleting
            $stmt_check = $conn->prepare("SELECT COUNT(*) as count FROM teacher_daily_languages WHERE language_id = ?");
            $stmt_check->bind_param("i", $language_id);
            $stmt_check->execute();
            $result_check = $stmt_check->get_result()->fetch_assoc();
            $stmt_check->close();

            if ($result_check['count'] > 0) {
                 $action_status_type = 'error';
                 $action_status_message = "Cannot delete this language because it is currently in use by a teacher's daily settings.";
            } else {
                $stmt = $conn->prepare("DELETE FROM languages WHERE id = ?");
                if ($stmt) {
                    $stmt->bind_param("i", $language_id);
                    if ($stmt->execute()) {
                        $action_status_type = 'success';
                        $action_status_message = ($stmt->affected_rows > 0) ? "Language deleted successfully." : "Language not found or already deleted.";
                    } else {
                        $action_status_type = 'error';
                        $action_status_message = "Error deleting language: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $action_status_type = 'error';
                    $action_status_message = "Error preparing delete language statement: " . $conn->error;
                }
            }
        } else {
            $action_status_type = 'error';
            $action_status_message = "Invalid language ID for deletion.";
        }
    }
    if (isset($_POST['update_language'])) {
        $language_id = filter_var($_POST['language_id_to_update'], FILTER_VALIDATE_INT);
        $updated_name = trim($_POST['language_name'] ?? '');
        if ($language_id !== false && !empty($updated_name)) {
            $stmt = $conn->prepare("UPDATE languages SET language_name = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $updated_name, $language_id);
                if ($stmt->execute()) {
                    $action_status_type = 'success';
                    $action_status_message = ($stmt->affected_rows > 0) ? "Language updated successfully." : "No changes were made to the language.";
                } else {
                    $action_status_type = 'error';
                    $action_status_message = "Error updating language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $action_status_type = 'error';
                $action_status_message = "Error preparing update language statement: " . $conn->error;
            }
        } else {
            $action_status_type = 'error';
            $action_status_message = "Invalid data for update.";
        }
    }

    if ($action_status_type === 'success') {
        $modal_success_message = $action_status_message;
    } elseif ($action_status_type === 'error') {
        $modal_error_message = $action_status_message;
    }
}

// Fetch language for editing if 'edit_language' GET parameter is present.
if (isset($_GET['edit_language'])) {
    $language_to_edit_id = filter_var($_GET['edit_language'], FILTER_VALIDATE_INT);
    if ($language_to_edit_id !== false) {
        $stmt = $conn->prepare("SELECT language_name FROM languages WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $language_to_edit_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $language_to_edit_name = $row['language_name'];
            } else {
                $_SESSION['error_message'] = "Language not found for editing."; 
                header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
                exit();
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing fetch language for edit statement: " . $conn->error;
            header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Invalid ID for editing.";
        header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
        exit();
    }
}

// Fetch all languages with optional search filter.
$sql = "SELECT id, language_name FROM languages";
if ($search_query !== '') { $sql .= " WHERE language_name LIKE ?"; }
$sql .= " ORDER BY id ASC";
$stmt_all = $conn->prepare($sql);
if ($stmt_all) {
    if ($search_query !== '') {
        $search_param = "%" . $search_query . "%";
        $stmt_all->bind_param("s", $search_param);
    }
    if ($stmt_all->execute()) {
        $result_all_languages = $stmt_all->get_result();
    } else {
        $modal_error_message = "Error executing fetch all languages statement: " . $stmt_all->error;
        $result_all_languages = false;
    }
    $stmt_all->close();
} else {
    $modal_error_message = "Error preparing fetch all languages statement: " . $conn->error;
    $result_all_languages = false;
}

// --- 3. Date Calculation (Last 7 Days) ---
$today_timestamp = time();
$end_date_timestamp = $today_timestamp;
$start_date_timestamp = strtotime('-6 days', $today_timestamp);
$start_date_str = date('Y-m-d 00:00:00', $start_date_timestamp);
$end_date_str = date('Y-m-d 23:59:59', $end_date_timestamp);
$start_date_query_format = date('Y-m-d', $start_date_timestamp);
$end_date_query_format = date('Y-m-d', $end_date_timestamp);

// Initialize arrays for chart data
$week_labels = [];
$week_user_data = [];
$week_lang_data = [];

$current_ts = $start_date_timestamp;
while ($current_ts <= $end_date_timestamp) {
    $date_key = date('Y-m-d', $current_ts);
    $week_labels[] = date('D (d M)', $current_ts);
    $week_user_data[$date_key] = 0;
    $week_lang_data[$date_key] = 0;
    $current_ts = strtotime('+1 day', $current_ts);
}

// --- 4. Fetch All Data (Dashboard Stats) ---
$stats = [
    'total_users' => 0, 'active_users' => 0, 'inactive_users' => 0,
    'total_languages' => 0,
    'total_devices' => 0, 'online_devices' => 0, 'offline_devices' => 0, 'error_devices' => 0,
];
// Fetch user status
$user_result = $conn->query("SELECT status, COUNT(*) as count FROM users WHERE role = 'teacher' GROUP BY status");
if ($user_result) { while($row = $user_result->fetch_assoc()) { if ($row['status'] === 'active') $stats['active_users'] = $row['count']; else $stats['inactive_users'] = $row['count']; $stats['total_users'] += $row['count']; } }
// Fetch language count
$lang_result = $conn->query("SELECT COUNT(*) as count FROM languages");
if ($lang_result) { $stats['total_languages'] = $lang_result->fetch_assoc()['count']; }
// Fetch device status
$device_result = $conn->query("SELECT status, COUNT(*) as count FROM device_status GROUP BY status");
if ($device_result) { while($row = $device_result->fetch_assoc()) { $status_key = strtolower($row['status']) . '_devices'; if (array_key_exists($status_key, $stats)) $stats[$status_key] = $row['count']; $stats['total_devices'] += $row['count']; } }

// Fetch new users chart data
$stmt_users_chart = $conn->prepare("SELECT DATE(created_at) as reg_date, COUNT(*) as count FROM users WHERE role = 'teacher' AND DATE(created_at) BETWEEN ? AND ? GROUP BY DATE(created_at)");
if ($stmt_users_chart) { $stmt_users_chart->bind_param("ss", $start_date_query_format, $end_date_query_format); if ($stmt_users_chart->execute()) { $result = $stmt_users_chart->get_result(); if ($result) { while($row = $result->fetch_assoc()) { if (array_key_exists($row['reg_date'], $week_user_data)) $week_user_data[$row['reg_date']] = $row['count']; } } } $stmt_users_chart->close(); }

// Fetch new languages chart data & table
$languages_by_teacher_week = [];
$stmt_langs_chart = $conn->prepare("SELECT l.language_name, l.created_at, u.username FROM languages l LEFT JOIN users u ON l.created_by = u.id WHERE l.created_at BETWEEN ? AND ? ORDER BY l.created_at DESC");
if ($stmt_langs_chart) { $stmt_langs_chart->bind_param("ss", $start_date_str, $end_date_str); if ($stmt_langs_chart->execute()) { $result = $stmt_langs_chart->get_result(); if ($result) { while($row = $result->fetch_assoc()) { $languages_by_teacher_week[] = $row; $date_key = date('Y-m-d', strtotime($row['created_at'])); if (array_key_exists($date_key, $week_lang_data)) { $week_lang_data[$date_key]++; } } } } $stmt_langs_chart->close(); }

// Fetch recent password reset requests for the table
$recent_password_resets = [];
$stmt_recent_resets = $conn->prepare("SELECT pr.expires_at, u.username, pr.email FROM password_resets pr JOIN users u ON pr.email = u.email WHERE pr.expires_at BETWEEN ? AND ? ORDER BY pr.expires_at DESC LIMIT 10");
if ($stmt_recent_resets) { $stmt_recent_resets->bind_param("ss", $start_date_str, $end_date_str); if ($stmt_recent_resets->execute()) { $result = $stmt_recent_resets->get_result(); if ($result) { while($row = $result->fetch_assoc()) $recent_password_resets[] = $row; } } $stmt_recent_resets->close(); }

// Fetch daily language settings table
$teacher_daily_lang_settings = [];
$sql_daily_langs = "SELECT tdl.setting_date, l.language_name 
                    FROM teacher_daily_languages tdl 
                    JOIN languages l ON tdl.language_id = l.id 
                    WHERE tdl.setting_date BETWEEN ? AND ? 
                    ORDER BY tdl.setting_date DESC";
$stmt_daily_langs = $conn->prepare($sql_daily_langs);
if ($stmt_daily_langs) { 
    $stmt_daily_langs->bind_param("ss", $start_date_query_format, $end_date_query_format); 
    if ($stmt_daily_langs->execute()) { 
        $result = $stmt_daily_langs->get_result(); 
        if ($result) { 
            while ($row = $result->fetch_assoc()) {
                $teacher_daily_lang_settings[] = $row; 
            }
        } 
    } 
    $stmt_daily_langs->close(); 
}

// --- 5. Prepare Data for JS Charts ---
$user_pie_data = ['labels' => ['Active', 'Inactive'], 'data' => [$stats['active_users'], $stats['inactive_users']]];
$device_pie_data = ['labels' => ['Online', 'Offline', 'Error'], 'data' => [$stats['online_devices'], $stats['offline_devices'], $stats['error_devices']]];
$user_bar_data = ['labels' => $week_labels, 'data' => array_values($week_user_data)];
$lang_bar_data = ['labels' => $week_labels, 'data' => array_values($week_lang_data)];


// --- Include the Standardized Header ---
include 'header.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    /* ... (Your existing styles remain unchanged) ... */
</style>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto">
        
        <section id="daily-language-settings" class="mb-12">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Daily Language Settings (Last 7 Days)</h2>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Setting Date</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (!empty($teacher_daily_lang_settings)): ?>
                                <?php foreach ($teacher_daily_lang_settings as $setting): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars(date('d M Y', strtotime($setting['setting_date']))); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($setting['language_name']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="2" class="text-center py-8 text-gray-500">No daily languages have been set in the last 7 days.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section id="manage-languages" class="mb-12">
            <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4"><?php echo $language_to_edit_id ? 'Edit Language' : 'Add New Language'; ?></h2>
                <form action="admin_dashboard.php#manage-languages" method="POST" class="space-y-4">
                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php if ($language_to_edit_id): ?><input type="hidden" name="language_id_to_update" value="<?php echo htmlspecialchars($language_to_edit_id); ?>"><?php endif; ?>
                    <div>
                        <label for="language_name_input" class="block text-sm font-medium text-gray-700">Language Name:</label>
                        <input type="text" id="language_name_input" name="language_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., English, Spanish, Malay" value="<?php echo htmlspecialchars($language_to_edit_name); ?>" required />
                    </div>
                    <div class="flex items-center">
                        <?php if ($language_to_edit_id): ?>
                            <button type="submit" name="update_language" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md"><i class="fas fa-save"></i>Update</button>
                            <a href="admin_dashboard.php?search_query=<?php echo urlencode($search_query); ?>#manage-languages" class="ml-3 border border-gray-300 py-2 px-4 rounded-md bg-white hover:bg-gray-50">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_language" class="btn-icon bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md"><i class="fas fa-plus-circle"></i>Add</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">Existing Languages</h2>
                    <form action="admin_dashboard.php#manage-languages" method="GET" class="flex items-center space-x-2 w-full md:w-1/3">
                        <div class="relative flex-grow">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                            <input type="text" name="search_query" placeholder="Type language name..." class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md">Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="admin_dashboard.php#manage-languages" class="text-gray-600 bg-gray-200 hover:bg-gray-300 py-2 px-4 rounded-md text-sm whitespace-nowrap">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if ($result_all_languages && $result_all_languages->num_rows > 0): while ($row = $result_all_languages->fetch_assoc()): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $row['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($row['language_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-4">
                                        <a href="admin_dashboard.php?edit_language=<?php echo $row['id']; ?>&search_query=<?php echo urlencode($search_query); ?>#manage-languages" class="text-indigo-600 hover:text-indigo-900"><i class="fas fa-edit mr-1"></i>Edit</a>
                                        
                                        <button type="button" class="text-red-600 hover:text-red-900 delete-language-button" data-language-id="<?php echo $row['id']; ?>" data-language-name="<?php echo htmlspecialchars($row['language_name']); ?>">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete
                                        </button>
                                        <form id="deleteLanguageForm-<?php echo $row['id']; ?>" action="admin_dashboard.php#manage-languages" method="POST" style="display: none;">
                                            <input type="hidden" name="language_id_to_delete" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                                            <input type="hidden" name="delete_language" value="1">
                                        </form>
                                    </td>
                                </tr>
                            <?php endwhile; else: ?><tr><td colspan="3" class="text-center py-8 text-gray-500">No languages found.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
        
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // --- (Your existing JavaScript for charts etc. would go here) ...

    // --- NEW AND IMPROVED JAVASCRIPT ---

    // 1. Logic to handle the delete button click
    const deleteButtons = document.querySelectorAll('.delete-language-button');
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const languageId = this.dataset.languageId;
            const languageName = this.dataset.languageName;
            const deleteForm = document.getElementById(`deleteLanguageForm-${languageId}`);

            if (deleteForm) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete "${languageName}". This action cannot be undone.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Yes, delete it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        deleteForm.submit();
                    }
                });
            }
        });
    });

    // 2. Logic to display success/error messages from PHP after a POST request
    const phpSuccessMessage = <?php echo json_encode($modal_success_message); ?>;
    const phpErrorMessage = <?php echo json_encode($modal_error_message); ?>;

    if (phpSuccessMessage) {
        Swal.fire({
            title: 'Success!',
            text: phpSuccessMessage,
            icon: 'success',
            confirmButtonText: 'OK'
        });
    }

    if (phpErrorMessage) {
        Swal.fire({
            title: 'Error!',
            text: phpErrorMessage,
            icon: 'error',
            confirmButtonText: 'OK'
        });
    }
});
</script>

</body>
</html>
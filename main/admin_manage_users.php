<?php
session_start(); // Ensure this is the very first line of any page that uses sessions

// --- FOR DEBUGGING: Enable all error reporting. REMOVE THIS ON PRODUCTION. ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --- END DEBUGGING SETTINGS ---

// --- 1. Admin Access Check ---
// Redirect non-admin users or unauthenticated users to the index page.
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

// --- 2. DB Connection ---
// This file is assumed to contain the database connection details ($conn).
include 'db_connection.php';

// Verify database connection
if (!$conn) {
    $_SESSION['error_message'] = "Database connection failed.";
    header("Location: admin_manage_users.php"); // Redirect to show the error
    exit();
}

// --- 3. Search & Filter Query Handling ---
// Retrieve and sanitize search and filter parameters from the GET request.
$search_query = trim($_GET['search_query'] ?? '');
$status_filter = trim($_GET['status_filter'] ?? '');

// --- 4. Messages & Edit Variables ---
// Initialize variables for displaying success/error messages and for populating the edit form.
// $error_message = null; // These are now handled by $modal_error_message or $_SESSION for redirects
// $success_message = null; // Replaced by $modal_success_message
// Old session-based flash messages are no longer retrieved directly here for POST actions
unset($_SESSION['success_message'], $_SESSION['error_message']); // Clear existing messages for new POST actions

$user_to_edit_id = null;
$user_to_edit_username = '';
$user_to_edit_email = '';
$user_to_edit_status = '';

// Added these variables for custom modal messages
$modal_success_message = null;
$modal_error_message = null; // For errors that occur on the same page from POST

// --- 6. Helper for Redirect URL ---
// Function to construct a redirect URL while preserving existing search and filter parameters.
function get_user_redirect_url($base_url, $query, $status) {
    $url = $base_url;
    $params = [];
    if ($query !== '') {
        $params['search_query'] = $query;
    }
    if ($status !== '') {
        $params['status_filter'] = $status;
    }
    if (!empty($params)) {
        $url .= "?" . http_build_query($params);
    }
    return $url;
}
// Define the base path for this script for redirects.
$base_script_path = "/admin_manage_users.php";
$redirect_url = get_user_redirect_url($base_script_path, $search_query, $status_filter);

// --- 7. POST Handling (Update User) ---
// Process form submission for updating a teacher's details.
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if the request is an update, delete, or other POST action
    if (isset($_POST['update_user'])) {
        $user_id_to_update = filter_var($_POST['user_id_to_update'] ?? '', FILTER_VALIDATE_INT);
        $updated_username = trim($_POST['username'] ?? '');
        $updated_email = trim($_POST['email'] ?? '');
        $updated_status = $_POST['status'] ?? '';

        // Validate input fields.
        if ($user_id_to_update === false || $updated_username === '' || !filter_var($updated_email, FILTER_VALIDATE_EMAIL) || !in_array($updated_status, ['active', 'inactive'])) {
            $modal_error_message = "Invalid data. Please check all fields."; // Set error for modal display
        } else {
            // Check if the updated username or email already exists for another user (excluding the current user being updated).
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            if ($check_stmt) {
                $check_stmt->bind_param("ssi", $updated_username, $updated_email, $user_id_to_update);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                if ($check_result->num_rows > 0) {
                    $modal_error_message = "Username or Email already taken by another user."; // Set error for modal display
                } else {
                    // Proceed with updating the teacher's information.
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE id = ? AND role = 'teacher'");
                    if ($stmt) {
                        $stmt->bind_param("sssi", $updated_username, $updated_email, $updated_status, $user_id_to_update);
                        if ($stmt->execute()) {
                            $modal_success_message = ($stmt->affected_rows > 0) ? "Teacher details updated successfully." : "No changes were made to the teacher details.";
                        } else {
                            $modal_error_message = "Error updating teacher: " . $stmt->error;
                        }
                        $stmt->close();
                    } else {
                        $modal_error_message = "Error preparing update statement: " . $conn->error;
                    }
                }
                $check_stmt->close();
            } else {
                $modal_error_message = "Error preparing uniqueness check: " . $conn->error;
            }
        }
        // IMPORTANT: No redirect here if we want to show the modal immediately.
        // The page will implicitly reload the GET parameters to reflect changes after modal dismissal.
        // If a direct redirect is needed, set $_SESSION['success_message'] / $_SESSION['error_message'] instead of $modal_... and uncomment header/exit.

    } elseif (isset($_POST['delete_user'])) { // Separate block for delete user
        $user_id_to_delete = filter_var($_POST['user_id_to_delete'] ?? '', FILTER_VALIDATE_INT);
        if ($user_id_to_delete === false) {
            $modal_error_message = "Invalid User ID for deletion.";
        } else if ($user_id_to_delete == $_SESSION['user_id']) { // Prevent admin from deleting self
            $modal_error_message = "You cannot delete your own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
            if ($stmt) {
                $stmt->bind_param("i", $user_id_to_delete);
                if ($stmt->execute()) {
                    $modal_success_message = ($stmt->affected_rows > 0) ? "Teacher deleted successfully." : "Teacher not found or already deleted.";
                } else {
                    $modal_error_message = "Error deleting teacher: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $modal_error_message = "Error preparing delete statement: " . $conn->error;
            }
        }
        // IMPORTANT: No redirect here for delete either. The page will reload after modal dismissal.
    }
    // No exit() here after POST, unless a redirect occurs.
    // PHP variables are passed to JS below.
}

// --- 9. GET Handling (Fetch User for Edit) ---
// This part populates the edit form when an 'edit_user' GET parameter is present.
if (isset($_GET['edit_user'])) {
    $user_to_edit_id_from_get = filter_var($_GET['edit_user'], FILTER_VALIDATE_INT);
    if ($user_to_edit_id_from_get !== false) {
        $stmt = $conn->prepare("SELECT id, username, email, status FROM users WHERE id = ? AND role = 'teacher'");
        if ($stmt) {
            $stmt->bind_param("i", $user_to_edit_id_from_get);
            $stmt->execute();
            $result_edit = $stmt->get_result();
            if ($row_edit = $result_edit->fetch_assoc()) {
                $user_to_edit_id = $row_edit['id'];
                $user_to_edit_username = $row_edit['username'];
                $user_to_edit_email = $row_edit['email'];
                $user_to_edit_status = $row_edit['status'];
            } else {
                $_SESSION['error_message'] = "Teacher not found for editing."; // Use session for errors on redirection
                header("Location: " . get_user_redirect_url($base_script_path, $search_query, $status_filter));
                exit();
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing to fetch user for edit: " . $conn->error;
            header("Location: " . get_user_redirect_url($base_script_path, $search_query, $status_filter));
            exit();
        }
    } else {
        $_SESSION['error_message'] = "Invalid ID for editing.";
        header("Location: " . get_user_redirect_url($base_script_path, $search_query, $status_filter));
        exit();
    }
}


// --- 10. Fetch Teachers (with Search & Filter) ---
// Construct the SQL query to fetch teacher data, applying search and status filters.
$sql = "SELECT id, username, email, role, status, created_at FROM users WHERE role = 'teacher'";
$sql_params = [];
$sql_types = '';
$where_clauses = [];

if ($search_query !== '') {
    $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
    $search_like = "%" . $search_query . "%";
    $sql_params[] = $search_like;
    $sql_params[] = $search_like;
    $sql_types .= "ss";
}
if ($status_filter !== '' && in_array($status_filter, ['active', 'inactive'])) {
    $where_clauses[] = "status = ?";
    $sql_params[] = $status_filter;
    $sql_types .= "s";
}
if (!empty($where_clauses)) {
    $sql .= " AND " . implode(" AND ", $where_clauses);
}
$sql .= " ORDER BY id ASC"; // Order by ID for consistent listing.

$stmt_all = $conn->prepare($sql);
$result_all_teachers = false; // Initialize result variable.

if ($stmt_all) {
    if (!empty($sql_params)) {
        // Dynamically bind parameters based on the number and type of filters applied.
        $stmt_all->bind_param($sql_types, ...$sql_params);
    }
    if ($stmt_all->execute()) {
        $result_all_teachers = $stmt_all->get_result();
    } else {
        // Use modal error if possible for this kind of error (not a redirect-triggering one)
        $modal_error_message = "Error fetching teachers list: " . $stmt_all->error;
    }
    $stmt_all->close();
} else {
    $modal_error_message = "Error preparing statement to fetch teachers: " . $conn->error;
}

?>

<?php include 'header.php'; // Includes <DOCTYPE html>, <html>, <head>, <body>, and the common header with dropdown ?>

    <!-- The <head> and <body> opening tags, and initial layout divs are provided by header.php. -->
    <!-- Only page-specific styles or additional head elements should go here if header.php doesn't include them. -->
    <!-- For this setup, the <head> tag and its content (except <title>) are typically handled by header.php. -->
    <!-- The <title> tag should be dynamically set or directly placed in header.php for consistency. -->
    <!-- In this context, the <title> tag should be included directly here as it's not part of the 'included' body structure. -->
    <head>
        <title>Manage Users | Admin Dashboard</title> <!-- Specific title for this page -->
        <style>
            /*
             * Styles below are kept here for demonstration purposes,
             * but ideally, all shared styles (like scrollbar, sidebar transitions, flash messages)
             * should be in a separate CSS file or directly within header.php's <style> block
             * to avoid duplication and ensure consistency across all pages that include header.php.
             */
            ::-webkit-scrollbar { width: 8px; height: 8px; }
            ::-webkit-scrollbar-track { background: #1f2937; }
            ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; }
            ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
            .sidebar { transition: width 0.3s ease-in-out; }
            .btn-icon { display: inline-flex; align-items: center; justify-content: center; }
            .btn-icon i { margin-right: 0.5rem; }
            .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; transition: opacity 0.5s ease-out; }
            .flash-message.fade-out { opacity: 0; }
            .flash-success { background-color: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
            .flash-error { background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; }
            #sidebar { height: 100vh; }
            @media (max-width: 1023px) { #sidebar { position: fixed; top: 0; left: 0; z-index: 40; } }
            .input-std {
                display: block; width: 100%;
                padding-left: 0.75rem; padding-right: 0.75rem; padding-top: 0.5rem; padding-bottom: 0.5rem;
                background-color: #ffffff;
                border-width: 1px; border-color: #d1d5db; border-radius: 0.375rem;
                box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
                outline: none;
            }
            .input-std:focus {
                --tw-ring-offset-shadow: var(--tw-ring-inset) 0 0 0 var(--tw-ring-offset-width) var(--tw-ring-offset-color);
                --tw-ring-shadow: var(--tw-ring-inset) 0 0 0 calc(1px + var(--tw-ring-offset-width)) var(--tw-ring-color);
                box-shadow: var(--tw-ring-offset-shadow), var(--tw-ring-shadow), var(--tw-shadow, 0 0 #0000);
                border-color: #4f46e5;
            }

            /* Styles for the custom delete modal */
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
                background-color: #fee2e2; /* Red-100 */
                color: #ef4444; /* Red-500 */
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

            /* Styles for the success modal */
            .success-modal .icon-wrapper {
                background-color: #d1fae5; /* Green-100 */
                color: #10b981; /* Green-500 */
            }
            /* Styles for the error modal */
            .error-modal .icon-wrapper {
                background-color: #fee2e2; /* Red-100 */
                color: #ef4444; /* Red-500 */
            }
        </style>
    </head>
    <!--
        The <body> tag opening is handled by header.php.
        The outer <div class="flex h-screen overflow-hidden"> is also started in header.php.
        The <div class="flex-1 flex flex-col overflow-hidden"> and <header> are also started/defined in header.php.
    -->

    <!-- The main content area begins here -->
    <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
        <div class="container mx-auto">
            <?php /* Old flash messages are removed, replaced by custom modals */ ?>
            <?php /* These are now handled by the JS triggered modals based on $modal_success_message/$modal_error_message */ ?>
            <?php /*
            <?php if ($success_message): ?><div id="successMessage" class="flash-message flash-success flex items-center"><i class="fas fa-check-circle fa-lg mr-3 py-1"></i><div><p class="font-bold">Success</p><p class="text-sm"><?php echo htmlspecialchars($success_message); ?></p></div></div><?php endif; ?>
            <?php if ($error_message && !$user_to_edit_id): ?>
            <div id="errorMessage" class="flash-message flash-error flex items-center"><i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i><div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p></div></div>
            <?php endif; ?>
            */ ?>
            <?php
            // Display session-based error messages (from redirects, e.g., edit_user not found)
            if (isset($_SESSION['error_message']) && !empty($_SESSION['error_message'])) : ?>
                <div id="sessionErrorMessage" class="flash-message flash-error flex items-center">
                    <i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i>
                    <div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($_SESSION['error_message']); ?></p></div>
                </div>
            <?php
                unset($_SESSION['error_message']); // Clear it after displaying
            endif;
            ?>

            <?php if ($user_to_edit_id): ?>
            <div class="bg-white p-6 rounded-lg shadow-lg mb-6" id="edit-section">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Edit Teacher: <?php echo htmlspecialchars($user_to_edit_username); ?></h2>
                <?php /* This error display is now handled by the modal for POST actions */ ?>
                <?php /* if ($error_message): ?><div class="flash-message flash-error"><div class="flex"><i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i><div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p></div></div></div><?php endif; */ ?>

                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                    <input type="hidden" name="user_id_to_update" value="<?php echo htmlspecialchars($user_to_edit_id); ?>">
                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                    <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                    
                    <div>
                        <label for="edit_username" class="block text-sm font-medium text-gray-700">Username:</label>
                        <input type="text" id="edit_username" name="username" class="mt-1 input-std" value="<?php echo htmlspecialchars($user_to_edit_username); ?>" required />
                    </div>
                    <div>
                        <label for="edit_email" class="block text-sm font-medium text-gray-700">Email:</label>
                        <input type="email" id="edit_email" name="email" class="mt-1 input-std" value="<?php echo htmlspecialchars($user_to_edit_email); ?>" required />
                    </div>
                    <div>
                        <label for="edit_status" class="block text-sm font-medium text-gray-700">Status:</label>
                        <select id="edit_status" name="status" class="mt-1 input-std">
                            <option value="active" <?php echo ($user_to_edit_status === 'active') ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo ($user_to_edit_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                    <div class="flex items-center space-x-3 pt-2">
                        <button type="submit" name="update_user" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <i class="fas fa-save"></i>Update Teacher
                        </button>
                        <a href="<?php echo get_user_redirect_url($base_script_path, $search_query, $status_filter); ?>" class="btn-icon border border-gray-300 py-2 px-4 rounded-md bg-white hover:bg-gray-50 text-gray-700 shadow-sm">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
            <?php endif; ?>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Registered Teachers</h2>
                
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 bg-gray-50 p-4 rounded-md border border-gray-200">
                        <div>
                            <label for="search_query_input" class="block text-sm font-medium text-gray-700">Search:</label>
                            <div class="relative mt-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                                <input type="text" name="search_query" id="search_query_input" placeholder="Username or Email..." class="block w-full pl-10 px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($search_query); ?>">
                            </div>
                        </div>
                        <div>
                            <label for="status_filter_select" class="block text-sm font-medium text-gray-700">Status:</label>
                            <select name="status_filter" id="status_filter_select" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="">All Statuses</option>
                                <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="md:pt-5"> <div class="flex items-center space-x-3 mt-1">
                                    <button type="submit" class="btn-icon flex-1 justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <i class="fas fa-filter"></i>Apply Filters
                                    </button>
                                    <a href="<?php echo $base_script_path; ?>" class="btn-icon flex-1 justify-center text-center text-gray-600 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-300 py-2 px-4 rounded-md text-sm shadow-sm">
                                        <i class="fas fa-times"></i>Clear
                                    </a>
                                </div>
                        </div>
                    </div>
                </form>
                
                <div class="overflow-x-auto">
                    <?php if ($result_all_teachers && $result_all_teachers->num_rows > 0): ?>
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php while ($teacher = $result_all_teachers->fetch_assoc()): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo $teacher['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($teacher['username']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($teacher['email']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($teacher['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                                <?php echo ucfirst($teacher['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <a href="<?php echo get_user_redirect_url($base_script_path, $search_query, $status_filter) . (strpos($redirect_url, '?') === false ? '?' : '&') . 'edit_user=' . $teacher['id']; ?>#edit-section" class="text-indigo-600 hover:text-indigo-900"><i class="fas fa-edit mr-1"></i>Edit</a>
                                            
                                            <!-- The delete button now triggers the custom modal -->
                                            <button type="button" class="text-red-600 hover:text-red-900 delete-button" data-user-id="<?php echo $teacher['id']; ?>" data-username="<?php echo htmlspecialchars($teacher['username']); ?>">
                                                <i class="fas fa-trash-alt mr-1"></i>Delete
                                            </button>
                                            <!-- The actual form for submission will be handled by JS -->
                                            <form id="deleteForm-<?php echo $teacher['id']; ?>" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" style="display: none;">
                                                <input type="hidden" name="user_id_to_delete" value="<?php echo $teacher['id']; ?>">
                                                <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                                                <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                                                <input type="hidden" name="delete_user" value="1">
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-users-slash fa-3x text-gray-400 mb-3"></i>
                            <p class="text-gray-500">
                                <?php echo (!empty($search_query) || !empty($status_filter)) ? "No teachers found matching your criteria." : "No teachers are currently registered."; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
    <!-- The closing </div> tags for the layout started in header.php -->
    </div> <!-- Closes <div class="flex-1 flex flex-col overflow-hidden"> from header.php -->
    </div> <!-- Closes <div class="flex h-screen overflow-hidden"> from header.php -->

<!-- Custom Delete Confirmation Modal HTML -->
<div id="deleteModal" class="modal-overlay">
    <div class="modal-box">
        <div class="icon-wrapper">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-3">Are you sure?</h3>
        <p class="text-sm text-gray-500 mb-6" id="deleteModalMessage">
            This action cannot be undone. All data associated with <span class="font-semibold" id="deleteUsernamePlaceholder"></span> will be lost.
        </p>
        <div class="flex flex-col space-y-3">
            <button id="confirmDeleteButton" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                Delete
            </button>
            <button id="cancelDeleteButton" class="w-full border border-gray-300 py-2 px-4 rounded-md bg-white hover:bg-gray-50 text-gray-700 transition duration-150 ease-in-out">
                Cancel
            </button>
        </div>
    </div>
</div>

<!-- Custom Success Modal HTML -->
<div id="successModal" class="modal-overlay">
    <div class="modal-box success-modal">
        <div class="icon-wrapper">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-3">Success</h3>
        <p class="text-sm text-gray-500 mb-6" id="successModalMessage">
            Action is done successfully!
        </p>
        <button id="confirmSuccessButton" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
            Confirm
        </button>
    </div>
</div>

<!-- Custom Error Modal HTML (for immediate errors from POST, not redirects) -->
<div id="errorModal" class="modal-overlay">
    <div class="modal-box error-modal">
        <div class="icon-wrapper">
            <i class="fas fa-times-circle"></i> <!-- A different icon for error -->
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-3">Error</h3>
        <p class="text-sm text-gray-500 mb-6" id="errorModalMessage">
            Something went wrong. Please try again.
        </p>
        <button id="confirmErrorButton" class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-md transition duration-150 ease-in-out focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
            Dismiss
        </button>
    </div>
</div>


<script>
    document.addEventListener('DOMContentLoaded', () => {
        // This script block should contain JavaScript specific to admin_manage_users.php.
        // The user dropdown JS is now handled in header.php.

        // Set the page title in the header h1 (id="page-title" from header.php)
        const pageTitleElement = document.getElementById('page-title');
        if (pageTitleElement) {
            pageTitleElement.textContent = 'Manage Users';
        }
        // Update the browser tab title
        document.title = 'Manage Users | Admin Dashboard';

        // Sidebar toggle logic (from original admin_manage_users.php, kept here for completeness if not moved globally)
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle'); // Assumes sidebar.php contains this ID
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebarTexts = document.querySelectorAll('.sidebar-text'); // Assumes sidebar.php contains these classes

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
            }
            sidebarToggle.title = isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar';
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebarDesktop);
            // Set initial state
            const isCollapsed = sidebar.classList.contains('w-20');
            const toggleIcon = sidebarToggle.querySelector('i');
            if (toggleIcon) {
                toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
                toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
            }
            sidebarToggle.title = isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar';
        }

        if (mobileSidebarToggle && sidebar) {
            sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'transform', '-translate-x-full', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto');
            
            mobileSidebarToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('translate-x-0');
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

        // --- Session-based Error Message Display (from redirects) ---
        const sessionErrorMessageDiv = document.getElementById('sessionErrorMessage');
        if (sessionErrorMessageDiv) {
            // Apply auto-hide and remove functionality similar to old flash messages
            setTimeout(() => {
                sessionErrorMessageDiv.classList.add('fade-out');
                setTimeout(() => sessionErrorMessageDiv.remove(), 500);
            }, 4000);
        }

        // Scroll to edit section logic
        if (window.location.hash === '#edit-section') {
            const editSection = document.getElementById('edit-section');
            if (editSection) {
                editSection.scrollIntoView({ behavior: 'smooth' });
            }
        }

        // --- Custom Delete Confirmation Modal Logic ---
        const deleteModal = document.getElementById('deleteModal');
        const confirmDeleteButton = document.getElementById('confirmDeleteButton');
        const cancelDeleteButton = document.getElementById('cancelDeleteButton');
        const deleteUsernamePlaceholder = document.getElementById('deleteUsernamePlaceholder');
        let currentDeleteForm = null; // To store the form that needs to be submitted

        document.querySelectorAll('.delete-button').forEach(button => {
            button.addEventListener('click', function() {
                const userId = this.dataset.userId;
                const username = this.dataset.username;
                currentDeleteForm = document.getElementById(`deleteForm-${userId}`);

                deleteUsernamePlaceholder.textContent = `'${username}'`;
                deleteModal.classList.add('active'); // Show the modal
            });
        });

        confirmDeleteButton.addEventListener('click', () => {
            if (currentDeleteForm) {
                currentDeleteForm.submit(); // Submit the form if confirmed
            }
            deleteModal.classList.remove('active'); // Hide modal
        });

        cancelDeleteButton.addEventListener('click', () => {
            deleteModal.classList.remove('active'); // Hide modal
            currentDeleteForm = null; // Clear the stored form
        });

        // Close modal if overlay is clicked
        deleteModal.addEventListener('click', (e) => {
            if (e.target === deleteModal) { // Check if the click was directly on the overlay
                deleteModal.classList.remove('active');
                currentDeleteForm = null;
            }
        });

        // --- Custom Success/Error Modal Display Logic (for POST actions on current page) ---
        const successModal = document.getElementById('successModal');
        const confirmSuccessButton = document.getElementById('confirmSuccessButton');
        const successModalMessage = document.getElementById('successModalMessage');

        const errorModal = document.getElementById('errorModal');
        const confirmErrorButton = document.getElementById('confirmErrorButton');
        const errorModalMessage = document.getElementById('errorModalMessage');

        // PHP variables for success/error messages, passed to JS
        const phpSuccessMessage = <?php echo json_encode($modal_success_message); ?>;
        const phpErrorMessage = <?php echo json_encode($modal_error_message); ?>;

        if (phpSuccessMessage) {
            successModalMessage.textContent = phpSuccessMessage;
            successModal.classList.add('active');
        } else if (phpErrorMessage) {
            errorModalMessage.textContent = phpErrorMessage;
            errorModal.classList.add('active');
        }

        // Success modal button/overlay click handler
        confirmSuccessButton.addEventListener('click', () => {
            successModal.classList.remove('active');
            // Reload the page to reflect changes and clear POST data
            window.location.href = window.location.pathname + window.location.search;
        });
        successModal.addEventListener('click', (e) => {
            if (e.target === successModal) {
                successModal.classList.remove('active');
                window.location.href = window.location.pathname + window.location.search;
            }
        });

        // Error modal button/overlay click handler
        confirmErrorButton.addEventListener('click', () => {
            errorModal.classList.remove('active');
        });
        errorModal.addEventListener('click', (e) => {
            if (e.target === errorModal) {
                errorModal.classList.remove('active');
            }
        });
    });
</script>

</body>
</html>

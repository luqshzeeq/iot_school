<?php
session_start();

// --- 1. Admin Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Adjust path if needed
    exit();
}

// --- 2. DB Connection ---
include 'db_connection.php'; // Ensure this file exists

// --- 3. Search & Filter Query Handling ---
$search_query = '';
$status_filter = '';

// Prioritize POST values (from hidden fields during redirects) then GET
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $search_query = trim($_POST['search_query'] ?? '');
    $status_filter = trim($_POST['status_filter'] ?? '');
}
// If not found in POST (or it was empty), check GET
if ($search_query === '' && isset($_GET['search_query'])) {
    $search_query = trim($_GET['search_query']);
}
if ($status_filter === '' && isset($_GET['status_filter'])) {
    $status_filter = trim($_GET['status_filter']);
}

// --- 4. Messages & Edit Variables ---
$error_message = null;
$success_message = null;
$user_to_edit_id = null;
$user_to_edit_username = '';
$user_to_edit_email = '';
$user_to_edit_status = '';

// --- 5. Session Messages ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// --- 6. Helper for Redirect URL ---
function get_user_redirect_url($base_url, $query, $status) {
    $url = $base_url;
    $params = [];
    if ($query !== '') $params['search_query'] = $query;
    if ($status !== '') $params['status_filter'] = $status;
    if (!empty($params)) {
        $url .= "?" . http_build_query($params);
    }
    return $url;
}

$redirect_url = get_user_redirect_url("admin_manage_users.php", $search_query, $status_filter);

// --- 7. POST Handling (Update User) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $user_id_to_update = filter_var($_POST['user_id_to_update'] ?? '', FILTER_VALIDATE_INT);
    $updated_username = trim($_POST['username'] ?? '');
    $updated_email = trim($_POST['email'] ?? '');
    $updated_status = $_POST['status'] ?? '';

    if ($user_id_to_update === false || $updated_username === '' || !filter_var($updated_email, FILTER_VALIDATE_EMAIL) || !in_array($updated_status, ['active', 'inactive'])) {
        $_SESSION['error_message'] = "Invalid data for updating user. Please check all fields.";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        if ($check_stmt) {
            $check_stmt->bind_param("ssi", $updated_username, $updated_email, $user_id_to_update);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            if ($check_result->num_rows > 0) {
                $_SESSION['error_message'] = "Username or Email already taken by another user.";
            } else {
                $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE id = ? AND role = 'teacher'");
                if ($stmt) {
                    $stmt->bind_param("sssi", $updated_username, $updated_email, $updated_status, $user_id_to_update);
                    if ($stmt->execute()) {
                        $_SESSION['success_message'] = $stmt->affected_rows > 0 ? "Teacher details updated successfully!" : "No changes made or teacher not found.";
                    } else {
                        $_SESSION['error_message'] = "Error updating teacher: " . htmlspecialchars($stmt->error);
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error_message'] = "Error preparing statement for user update: " . htmlspecialchars($conn->error);
                }
            }
            $check_stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing uniqueness check: " . htmlspecialchars($conn->error);
        }
    }
    header("Location: " . $redirect_url);
    exit();
}

// --- 8. POST Handling (Delete User) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id_to_delete = filter_var($_POST['user_id_to_delete'] ?? '', FILTER_VALIDATE_INT);
    if ($user_id_to_delete === false) {
        $_SESSION['error_message'] = "Invalid user ID for deletion.";
    } else if ($user_id_to_delete == $_SESSION['user_id']) {
        $_SESSION['error_message'] = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
        if ($stmt) {
            $stmt->bind_param("i", $user_id_to_delete);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = $stmt->affected_rows > 0 ? "Teacher deleted successfully!" : "Teacher not found or not a teacher.";
            } else {
                $_SESSION['error_message'] = "Error deleting teacher: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing statement for user delete: " . htmlspecialchars($conn->error);
        }
    }
    header("Location: " . $redirect_url);
    exit();
}

// --- 9. GET Handling (Fetch User for Edit) ---
if (isset($_GET['edit_user']) && filter_var($_GET['edit_user'], FILTER_VALIDATE_INT)) {
    $user_to_edit_id_from_get = (int)$_GET['edit_user'];
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
            $_SESSION['error_message'] = "Teacher not found for editing.";
            header("Location: " . $redirect_url);
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Error preparing statement for user edit: " . htmlspecialchars($conn->error);
        header("Location: " . $redirect_url);
        exit();
    }
}

// --- 10. Fetch Teachers (with Search & Filter) ---
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

$sql .= " ORDER BY id ASC";

$stmt_all = $conn->prepare($sql);
$result_all_teachers = false;

if ($stmt_all) {
    if (!empty($sql_params)) {
        $stmt_all->bind_param($sql_types, ...$sql_params);
    }
    if ($stmt_all->execute()) {
        $result_all_teachers = $stmt_all->get_result();
    } else {
        $error_message = "Error executing teacher fetch: " . htmlspecialchars($stmt_all->error);
    }
    $stmt_all->close();
} else {
    $error_message = "Error preparing statement for teacher fetch: " . htmlspecialchars($conn->error);
}

if (!$result_all_teachers && $error_message === null) {
    $error_message = "Error fetching teachers: " . htmlspecialchars($conn->error);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Users | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #2d3748; }
        ::-webkit-scrollbar-thumb { background: #4a5568; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #718096; }
        .sidebar { transition: width 0.3s ease-in-out; }
        .btn-icon { display: inline-flex; align-items: center; justify-content: center; }
        .btn-icon i { margin-right: 0.5rem; }
        .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: opacity 0.5s ease-out; }
        .flash-message.fade-out { opacity: 0; }
        .flash-success { background-color: #d1fae5; border-left-width: 4px; border-color: #10b981; color: #065f46; }
        .flash-error { background-color: #fee2e2; border-left-width: 4px; border-color: #ef4444; color: #991b1b; }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; // Include your sidebar here ?>

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
                    <div class="flex"><div class="py-1"><i class="fas fa-check-circle fa-lg mr-3"></i></div><div><p class="font-bold">Success</p><p class="text-sm"><?php echo htmlspecialchars($success_message); ?></p></div></div>
                </div>
                <?php endif; ?>
                <?php if ($error_message): ?>
                <div id="errorMessage" class="flash-message flash-error" role="alert">
                    <div class="flex"><div class="py-1"><i class="fas fa-exclamation-triangle fa-lg mr-3"></i></div><div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p></div></div>
                </div>
                <?php endif; ?>

                <?php if ($user_to_edit_id): ?>
                <div class="bg-white p-6 rounded-lg shadow-lg mb-6" id="edit-section">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Edit Teacher: <?php echo htmlspecialchars($user_to_edit_username); ?></h2>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                        <input type="hidden" name="user_id_to_update" value="<?php echo htmlspecialchars($user_to_edit_id); ?>">
                        <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                        <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <div>
                            <label for="edit_username" class="block text-sm font-medium text-gray-700">Username:</label>
                            <input type="text" id="edit_username" name="username" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($user_to_edit_username); ?>" required />
                        </div>
                        <div>
                            <label for="edit_email" class="block text-sm font-medium text-gray-700">Email:</label>
                            <input type="email" id="edit_email" name="email" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($user_to_edit_email); ?>" required />
                        </div>
                        <div>
                            <label for="edit_status" class="block text-sm font-medium text-gray-700">Status:</label>
                            <select id="edit_status" name="status" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                <option value="active" <?php echo ($user_to_edit_status === 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($user_to_edit_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="flex items-center">
                            <button type="submit" name="update_user" class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm btn-icon">
                                <i class="fas fa-save"></i>Update Teacher
                            </button>
                            <a href="<?php echo get_user_redirect_url('admin_manage_users.php', $search_query, $status_filter); ?>" class="ml-3 inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Registered Teachers</h2>

                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 bg-gray-50 p-4 rounded-md border border-gray-200">
                           <div class="md:col-span-1">
                                <label for="search_query_input" class="block text-sm font-medium text-gray-700">Search:</label>
                                <div class="relative mt-1">
                                     <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                         <i class="fas fa-search text-gray-400"></i>
                                    </div>
                                    <input
                                        type="text"
                                        name="search_query"
                                        id="search_query_input"
                                        placeholder="Username or Email..."
                                        class="block w-full pl-10 px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        value="<?php echo htmlspecialchars($search_query); ?>"
                                    >
                                </div>
                           </div>
                           <div class="md:col-span-1">
                                <label for="status_filter_select" class="block text-sm font-medium text-gray-700">Status:</label>
                                <select 
                                    name="status_filter" 
                                    id="status_filter_select"
                                    class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                >
                                    <option value="">All Statuses</option>
                                    <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                           </div>
                           <div class="md:col-span-1 flex items-end space-x-3">
                                <button type="submit" class="flex-1 bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm btn-icon justify-center">
                                    <i class="fas fa-filter"></i>Apply
                                </button>
                                <a href="admin_manage_users.php" class="flex-1 text-center text-gray-600 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-300 px-4 py-2 rounded-md text-sm btn-icon justify-center">
                                    <i class="fas fa-times"></i>Clear
                                </a>
                           </div>
                        </div>
                    </form>
                    <?php if ($result_all_teachers && $result_all_teachers->num_rows > 0): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registered At</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($teacher = $result_all_teachers->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($teacher['id']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($teacher['username']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?php echo htmlspecialchars($teacher['email']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(ucfirst($teacher['role'])); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($teacher['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>">
                                                    <?php echo htmlspecialchars(ucfirst($teacher['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars(date('M d, Y H:i', strtotime($teacher['created_at']))); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                <a href="<?php echo get_user_redirect_url('admin_manage_users.php', $search_query, $status_filter) . '&edit_user=' . htmlspecialchars($teacher['id']); ?>#edit-section" class="text-indigo-600 hover:text-indigo-900 bg-indigo-100 hover:bg-indigo-200 px-3 py-1 rounded-md text-xs btn-icon">
                                                    <i class="fas fa-edit"></i>Edit
                                                </a>
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="inline-block">
                                                    <input type="hidden" name="user_id_to_delete" value="<?php echo htmlspecialchars($teacher['id']); ?>">
                                                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                                                    <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                                                    <button
                                                        type="submit"
                                                        name="delete_user"
                                                        class="text-red-600 hover:text-red-900 bg-red-100 hover:bg-red-200 px-3 py-1 rounded-md text-xs btn-icon"
                                                        onclick="return confirm('Are you sure you want to delete the teacher \'<?php echo htmlspecialchars(addslashes($teacher['username'])); ?>\'? This action cannot be undone.');"
                                                    >
                                                        <i class="fas fa-trash-alt"></i>Delete
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <i class="fas fa-users-slash fa-3x text-gray-400 mb-3"></i>
                            <p class="text-gray-500">
                                <?php echo (!empty($search_query) || !empty($status_filter)) ? "No teachers found matching your criteria." : "No teachers found."; ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    // --- JavaScript for Sidebar and Flash Messages ---
    const sidebar = document.getElementById('sidebar');
    // const sidebarToggle = document.getElementById('sidebarToggle'); // Optional Desktop Toggle
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    const sidebarTexts = document.querySelectorAll('.sidebar-text'); // Ensure sidebar elements have this class

    // if (sidebarToggle) { // Optional Desktop Toggle Logic
    //     function toggleSidebar() { /* ... */ }
    //     sidebarToggle.addEventListener('click', toggleSidebar);
    // }

    if (mobileSidebarToggle && sidebar) {
        mobileSidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0');
            if (!sidebar.classList.contains('-translate-x-full')) {
                 sidebar.classList.add('w-64');
                 sidebar.classList.remove('w-20');
                 sidebarTexts.forEach(text => text.classList.remove('hidden'));
            }
        });
        sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
         if (!sidebar.classList.contains('lg:w-64') && !sidebar.classList.contains('lg:w-20')) {
             sidebar.classList.add('lg:w-64');
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        const successAlert = document.getElementById('successMessage');
        const errorAlert = document.getElementById('errorMessage');
        const autoHideDelay = 4000;

        function autoHide(alertElement) {
            if (alertElement) {
                setTimeout(() => {
                    alertElement.classList.add('fade-out');
                    setTimeout(() => { alertElement.style.display = 'none'; }, 500);
                }, autoHideDelay);
            }
        }

        autoHide(successAlert);
        autoHide(errorAlert);
    });
</script>
</body>
</html>
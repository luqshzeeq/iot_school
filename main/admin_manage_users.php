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
$search_query = trim($_GET['search_query'] ?? '');
$status_filter = trim($_GET['status_filter'] ?? '');
if ($_SERVER["REQUEST_METHOD"] == "POST") { // Allow POST to carry over filter state if needed, though forms use GET now
    $search_query = trim($_POST['search_query'] ?? $search_query);
    $status_filter = trim($_POST['status_filter'] ?? $status_filter);
}

// --- 4. Messages & Edit Variables ---
$error_message = null; $success_message = null; $user_to_edit_id = null;
$user_to_edit_username = ''; $user_to_edit_email = ''; $user_to_edit_status = '';

// --- 5. Session Messages ---
if (isset($_SESSION['success_message'])) { $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message'])) { $error_message = $_SESSION['error_message']; unset($_SESSION['error_message']); }

// --- 6. Helper for Redirect URL ---
function get_user_redirect_url($base_url, $query, $status) {
    $url = $base_url; $params = [];
    if ($query !== '') $params['search_query'] = $query;
    if ($status !== '') $params['status_filter'] = $status;
    if (!empty($params)) $url .= "?" . http_build_query($params);
    return $url;
}
$redirect_url = get_user_redirect_url("admin_manage_users.php", $search_query, $status_filter);

// --- 7. POST Handling (Update User) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_user'])) {
    $user_id_to_update = filter_var($_POST['user_id_to_update'] ?? '', FILTER_VALIDATE_INT);
    $updated_username = trim($_POST['username'] ?? ''); $updated_email = trim($_POST['email'] ?? ''); $updated_status = $_POST['status'] ?? '';

    if ($user_id_to_update === false || $updated_username === '' || !filter_var($updated_email, FILTER_VALIDATE_EMAIL) || !in_array($updated_status, ['active', 'inactive'])) {
        $_SESSION['error_message'] = "Invalid data. Please check all fields.";
    } else {
        $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
        $check_stmt->bind_param("ssi", $updated_username, $updated_email, $user_id_to_update); $check_stmt->execute(); $check_result = $check_stmt->get_result();
        if ($check_result->num_rows > 0) $_SESSION['error_message'] = "Username or Email already taken.";
        else {
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE id = ? AND role = 'teacher'");
            $stmt->bind_param("sssi", $updated_username, $updated_email, $updated_status, $user_id_to_update);
            $_SESSION['success_message'] = $stmt->execute() ? ($stmt->affected_rows > 0 ? "Teacher updated." : "No changes made.") : "Error: " . $stmt->error;
            $stmt->close();
        }
        $check_stmt->close();
    }
    header("Location: " . $redirect_url); exit();
}

// --- 8. POST Handling (Delete User) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_user'])) {
    $user_id_to_delete = filter_var($_POST['user_id_to_delete'] ?? '', FILTER_VALIDATE_INT);
    if ($user_id_to_delete === false) $_SESSION['error_message'] = "Invalid ID.";
    else if ($user_id_to_delete == $_SESSION['user_id']) $_SESSION['error_message'] = "Cannot delete self.";
    else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
        $stmt->bind_param("i", $user_id_to_delete);
        $_SESSION['success_message'] = $stmt->execute() ? ($stmt->affected_rows > 0 ? "Teacher deleted." : "Teacher not found.") : "Error: " . $stmt->error;
        $stmt->close();
    }
    header("Location: " . $redirect_url); exit();
}

// --- 9. GET Handling (Fetch User for Edit) ---
if (isset($_GET['edit_user']) && filter_var($_GET['edit_user'], FILTER_VALIDATE_INT)) {
    $user_to_edit_id_from_get = (int)$_GET['edit_user'];
    $stmt = $conn->prepare("SELECT id, username, email, status FROM users WHERE id = ? AND role = 'teacher'");
    $stmt->bind_param("i", $user_to_edit_id_from_get); $stmt->execute(); $result_edit = $stmt->get_result();
    if ($row_edit = $result_edit->fetch_assoc()) {
        $user_to_edit_id = $row_edit['id']; $user_to_edit_username = $row_edit['username'];
        $user_to_edit_email = $row_edit['email']; $user_to_edit_status = $row_edit['status'];
    } else {
        $_SESSION['error_message'] = "Teacher not found."; header("Location: " . $redirect_url); exit();
    }
    $stmt->close();
}

// --- 10. Fetch Teachers (with Search & Filter) ---
$sql = "SELECT id, username, email, role, status, created_at FROM users WHERE role = 'teacher'";
$sql_params = []; $sql_types = ''; $where_clauses = [];
if ($search_query !== '') {
    $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
    $search_like = "%" . $search_query . "%";
    $sql_params[] = $search_like; $sql_params[] = $search_like; $sql_types .= "ss";
}
if ($status_filter !== '' && in_array($status_filter, ['active', 'inactive'])) {
    $where_clauses[] = "status = ?"; $sql_params[] = $status_filter; $sql_types .= "s";
}
if (!empty($where_clauses)) $sql .= " AND " . implode(" AND ", $where_clauses);
$sql .= " ORDER BY id ASC";
$stmt_all = $conn->prepare($sql); $result_all_teachers = false;
if ($stmt_all) {
    if (!empty($sql_params)) $stmt_all->bind_param($sql_types, ...$sql_params);
    if ($stmt_all->execute()) $result_all_teachers = $stmt_all->get_result();
    else $error_message = "Error fetching teachers: " . $stmt_all->error;
    $stmt_all->close();
} else $error_message = "Error preparing fetch: " . $conn->error;

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
        ::-webkit-scrollbar { width: 8px; height: 8px; } ::-webkit-scrollbar-track { background: #1f2937; } ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; } ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .sidebar { transition: width 0.3s ease-in-out; } .btn-icon { display: inline-flex; align-items: center; justify-content: center; } .btn-icon i { margin-right: 0.5rem; }
        .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; transition: opacity 0.5s ease-out; } .flash-message.fade-out { opacity: 0; }
        .flash-success { background-color: #d1fae5; border-left: 4px solid #10b981; color: #065f46; } .flash-error { background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; }
        #sidebar { height: 100vh; } @media (max-width: 1023px) { #sidebar { position: fixed; top: 0; left: 0; z-index: 40; } }
        .input-std { display: block; width: 100%; padding-left: 0.75rem; padding-right: 0.75rem; padding-top: 0.5rem; padding-bottom: 0.5rem; background-color: #ffffff; border-width: 1px; border-color: #d1d5db; border-radius: 0.375rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); outline: none; &:focus { --tw-ring-color: #4f46e5; border-color: #4f46e5; } }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-md">
             <div class="container mx-auto px-6 py-3 flex items-center justify-between">
                <div class="flex items-center">
                    <button id="mobileSidebarToggle" class="text-gray-600 focus:outline-none lg:hidden mr-4"> <i class="fas fa-bars text-xl"></i> </button>
                     <h1 class="text-xl font-semibold text-gray-700 hidden lg:block">Manage Users</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <span class="text-gray-600 text-sm">Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>!</span>
                    <button class="block h-10 w-10 rounded-full overflow-hidden border-2"><img class="h-full w-full object-cover" src="https://placehold.co/40x40/718096/E2E8F0?text=A" alt="Avatar" /></button>
                </div>
            </div>
        </header>

        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
            <div class="container mx-auto">
                <?php if ($success_message): ?><div id="successMessage" class="flash-message flash-success"><div class="flex"><i class="fas fa-check-circle fa-lg mr-3 py-1"></i><div><p class="font-bold">Success</p><p class="text-sm"><?php echo htmlspecialchars($success_message); ?></p></div></div></div><?php endif; ?>
                <?php if ($error_message): ?><div id="errorMessage" class="flash-message flash-error"><div class="flex"><i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i><div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p></div></div></div><?php endif; ?>

                <?php if ($user_to_edit_id): ?>
                <div class="bg-white p-6 rounded-lg shadow-lg mb-6" id="edit-section">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Edit Teacher: <?php echo htmlspecialchars($user_to_edit_username); ?></h2>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                        <input type="hidden" name="user_id_to_update" value="<?php echo htmlspecialchars($user_to_edit_id); ?>">
                        <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                        <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                        <div><label for="edit_username" class="block text-sm font-medium text-gray-700">Username:</label><input type="text" id="edit_username" name="username" class="mt-1 input-std" value="<?php echo htmlspecialchars($user_to_edit_username); ?>" required /></div>
                        <div><label for="edit_email" class="block text-sm font-medium text-gray-700">Email:</label><input type="email" id="edit_email" name="email" class="mt-1 input-std" value="<?php echo htmlspecialchars($user_to_edit_email); ?>" required /></div>
                        <div><label for="edit_status" class="block text-sm font-medium text-gray-700">Status:</label><select id="edit_status" name="status" class="mt-1 input-std"><option value="active" <?php echo ($user_to_edit_status === 'active') ? 'selected' : ''; ?>>Active</option><option value="inactive" <?php echo ($user_to_edit_status === 'inactive') ? 'selected' : ''; ?>>Inactive</option></select></div>
                        <div class="flex items-center"><button type="submit" name="update_user" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm"><i class="fas fa-save"></i>Update</button><a href="<?php echo get_user_redirect_url('admin_manage_users.php', $search_query, $status_filter); ?>" class="ml-3 btn-icon border border-gray-300 py-2 px-4 rounded-md bg-white hover:bg-gray-50 text-gray-700">Cancel</a></div>
                    </form>
                </div>
                <?php endif; ?>

                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Registered Teachers</h2>
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-x-6 gap-y-4 bg-gray-50 p-4 rounded-md border border-gray-200">
                           <div class="md:col-span-1">
                                <label for="search_query_input" class="block text-sm font-medium text-gray-700">Search:</label>
                                <div class="relative mt-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                                    <input type="text" name="search_query" id="search_query_input" placeholder="Username or Email..." class="block w-full pl-10 px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                           </div>
                           <div class="md:col-span-1">
                                <label for="status_filter_select" class="block text-sm font-medium text-gray-700">Status:</label>
                                <select name="status_filter" id="status_filter_select" class="mt-1 block w-full px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                    <option value="">All Statuses</option> <option value="active" <?php echo ($status_filter === 'active') ? 'selected' : ''; ?>>Active</option> <option value="inactive" <?php echo ($status_filter === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                           </div>
                           <div class="md:col-span-1">
                                <label class="block text-sm font-medium text-gray-700">&nbsp;</label>
                                <div class="flex items-center space-x-3 mt-1">
                                    <button type="submit" class="btn-icon flex-1 justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm"><i class="fas fa-filter"></i>Apply</button>
                                    <a href="admin_manage_users.php" class="btn-icon flex-1 justify-center text-center text-gray-600 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-300 py-2 px-4 rounded-md text-sm shadow-sm"><i class="fas fa-times"></i>Clear</a>
                                </div>
                            </div>
                        </div>
                    </form>
                    
                    <div class="overflow-x-auto">
                        <?php if ($result_all_teachers && $result_all_teachers->num_rows > 0): ?>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Username</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Email</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Registered</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th></tr></thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php while ($teacher = $result_all_teachers->fetch_assoc()): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $teacher['id']; ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($teacher['username']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($teacher['email']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm"><span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo ($teacher['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'); ?>"><?php echo ucfirst($teacher['status']); ?></span></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($teacher['created_at'])); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                <a href="<?php echo get_user_redirect_url('admin_manage_users.php', $search_query, $status_filter) . '&edit_user=' . $teacher['id']; ?>#edit-section" class="text-indigo-600 hover:text-indigo-900"><i class="fas fa-edit mr-1"></i>Edit</a>
                                                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="inline-block">
                                                    <input type="hidden" name="user_id_to_delete" value="<?php echo $teacher['id']; ?>">
                                                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                                                    <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($status_filter); ?>">
                                                    <button type="submit" name="delete_user" class="text-red-600 hover:text-red-900" onclick="return confirm('Delete \'<?php echo addslashes($teacher['username']); ?>\'?');"><i class="fas fa-trash-alt mr-1"></i>Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="text-center py-8"><i class="fas fa-users-slash fa-3x text-gray-400 mb-3"></i><p class="text-gray-500"><?php echo (!empty($search_query) || !empty($status_filter)) ? "No teachers found matching criteria." : "No teachers found."; ?></p></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebarTexts = document.querySelectorAll('.sidebar-text');

        function toggleSidebarDesktop() {
            if (!sidebar || !sidebarToggle) return;
            const toggleIcon = sidebarToggle.querySelector('i');
            sidebar.classList.toggle('w-64'); sidebar.classList.toggle('w-20');
            const isCollapsed = sidebar.classList.contains('w-20');
            sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
            toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
            toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
            sidebarToggle.title = isCollapsed ? 'Expand' : 'Collapse';
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebarDesktop);
            const isCollapsed = sidebar.classList.contains('w-20');
            const toggleIcon = sidebarToggle.querySelector('i');
            sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
            toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
            toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
            sidebarToggle.title = isCollapsed ? 'Expand' : 'Collapse';
        }

        if (mobileSidebarToggle && sidebar) {
            sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
            mobileSidebarToggle.addEventListener('click', (e) => {
                e.stopPropagation(); sidebar.classList.toggle('-translate-x-full'); sidebar.classList.toggle('translate-x-0');
                sidebar.classList.add('w-64'); sidebar.classList.remove('w-20'); sidebarTexts.forEach(text => text.classList.remove('hidden'));
            });
            document.addEventListener('click', (e) => {
                if (sidebar && !sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target) && sidebar.classList.contains('translate-x-0') && window.innerWidth < 1024) {
                    sidebar.classList.add('-translate-x-full'); sidebar.classList.remove('translate-x-0');
                }
            });
        }

        const successAlert = document.getElementById('successMessage');
        const errorAlert = document.getElementById('errorMessage');
        function autoHide(el) { if(el) { setTimeout(() => { el.classList.add('fade-out'); setTimeout(() => el.style.display = 'none', 500); }, 4000); } }
        autoHide(successAlert); autoHide(errorAlert);
    });
</script>

</body>
</html>
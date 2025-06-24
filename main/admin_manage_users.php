<?php
session_start(); // Ensure this is the very first line

// --- FOR DEBUGGING: Enable all error reporting. REMOVE THIS ON PRODUCTION. ---
ini_set('display_errors', 1);
error_reporting(E_ALL);
// --- END DEBUGGING SETTINGS ---

// --- 1. Admin Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: /index.php");
    exit();
}

// --- 2. DB Connection ---
include 'db_connection.php';

// Verify database connection
if (!$conn) {
    // We can't use a modal here as the page won't render, so a session message is appropriate
    $_SESSION['error_message'] = "Database connection failed.";
    header("Location: /admin_dashboard.php"); // Redirect to a page that can show the error
    exit();
}

// --- 3. Search & Filter Query Handling ---
$search_query = trim($_GET['search_query'] ?? '');
$status_filter = trim($_GET['status_filter'] ?? '');

// --- 4. Messages & Edit Variables ---
unset($_SESSION['success_message']); // Not used
$user_to_edit_id = null;
$user_to_edit_username = '';
$user_to_edit_email = '';
$user_to_edit_status = '';

// Variables for SweetAlert2 messages
$modal_success_message = null;
$modal_error_message = null;

// --- 6. Helper for Redirect URL ---
function get_user_redirect_url($base_url, $query, $status) {
    $params = [];
    if ($query !== '') $params['search_query'] = $query;
    if ($status !== '') $params['status_filter'] = $status;
    return $base_url . (!empty($params) ? "?" . http_build_query($params) : '');
}
$base_script_path = "/admin_manage_users.php";

// --- 7. POST Handling (Update & Delete User) ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- UPDATE USER LOGIC ---
    if (isset($_POST['update_user'])) {
        $user_id_to_update = filter_var($_POST['user_id_to_update'] ?? '', FILTER_VALIDATE_INT);
        $updated_username = trim($_POST['username'] ?? '');
        $updated_email = trim($_POST['email'] ?? '');
        $updated_status = $_POST['status'] ?? '';

        if (!$user_id_to_update || $updated_username === '' || !filter_var($updated_email, FILTER_VALIDATE_EMAIL) || !in_array($updated_status, ['active', 'inactive'])) {
            $modal_error_message = "Invalid data submitted. Please check all fields.";
        } else {
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE (username = ? OR email = ?) AND id != ?");
            if ($check_stmt) {
                $check_stmt->bind_param("ssi", $updated_username, $updated_email, $user_id_to_update);
                $check_stmt->execute();
                if ($check_stmt->get_result()->num_rows > 0) {
                    $modal_error_message = "Username or Email is already in use by another account.";
                } else {
                    $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, status = ? WHERE id = ? AND role = 'teacher'");
                    if ($stmt) {
                        $stmt->bind_param("sssi", $updated_username, $updated_email, $updated_status, $user_id_to_update);
                        if ($stmt->execute()) {
                            $modal_success_message = ($stmt->affected_rows > 0) ? "Teacher details updated successfully." : "No changes were made.";
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
    } 
    // --- DELETE USER LOGIC ---
    elseif (isset($_POST['delete_user'])) {
        $user_id_to_delete = filter_var($_POST['user_id_to_delete'] ?? '', FILTER_VALIDATE_INT);
        if (!$user_id_to_delete) {
            $modal_error_message = "Invalid User ID for deletion.";
        } else if ($user_id_to_delete == $_SESSION['user_id']) {
            $modal_error_message = "Action denied. You cannot delete your own account.";
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'teacher'");
            if ($stmt) {
                $stmt->bind_param("i", $user_id_to_delete);
                if ($stmt->execute()) {
                    $modal_success_message = ($stmt->affected_rows > 0) ? "Teacher account deleted successfully." : "Teacher not found or already deleted.";
                } else {
                    $modal_error_message = "Error deleting teacher: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $modal_error_message = "Error preparing delete statement: " . $conn->error;
            }
        }
    }
}

// --- 9. GET Handling (Fetch User for Edit) ---
if (isset($_GET['edit_user'])) {
    $user_to_edit_id_from_get = filter_var($_GET['edit_user'], FILTER_VALIDATE_INT);
    if ($user_to_edit_id_from_get) {
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
                $_SESSION['error_message'] = "Teacher not found for editing."; // Session error for redirect
                header("Location: " . get_user_redirect_url($base_script_path, $search_query, $status_filter));
                exit();
            }
            $stmt->close();
        }
    } else {
        $_SESSION['error_message'] = "Invalid ID provided for editing.";
        header("Location: " . get_user_redirect_url($base_script_path, $search_query, $status_filter));
        exit();
    }
}

// --- 10. Fetch All Teachers (with Search & Filter) ---
$sql = "SELECT id, username, email, role, status, created_at FROM users WHERE role = 'teacher'";
$sql_params = [];
$sql_types = '';
$where_clauses = [];

if ($search_query !== '') {
    $where_clauses[] = "(username LIKE ? OR email LIKE ?)";
    $search_like = "%" . $search_query . "%";
    array_push($sql_params, $search_like, $search_like);
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
        $modal_error_message = "Error fetching teachers list: " . $stmt_all->error;
    }
    $stmt_all->close();
} else {
    $modal_error_message = "Error preparing to fetch teachers: " . $conn->error;
}
?>

<?php include 'header.php'; ?>
<head>
    <title>Manage Users | Admin Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Your existing styles (scrollbar, buttons, etc.) can remain here */
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #1f2937; }
        ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; }
        .btn-icon { display: inline-flex; align-items: center; justify-content: center; }
        .btn-icon i { margin-right: 0.5rem; }
        /* Style for session-based flash messages */
        .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; transition: opacity 0.5s ease-out; }
        .flash-message.fade-out { opacity: 0; }
        .flash-error { background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; }
    </style>
</head>

<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto">
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
            <form action="<?php echo get_user_redirect_url($base_script_path, $search_query, $status_filter) . (strpos(get_user_redirect_url($base_script_path, $search_query, $status_filter), '?') ? '&' : '?') . 'edit_user=' . $user_to_edit_id; ?>#edit-section" method="POST" class="space-y-4">
                <input type="hidden" name="user_id_to_update" value="<?php echo htmlspecialchars($user_to_edit_id); ?>">
                <div>
                    <label for="edit_username" class="block text-sm font-medium text-gray-700">Username:</label>
                    <input type="text" id="edit_username" name="username" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($user_to_edit_username); ?>" required />
                </div>
                <div>
                    <label for="edit_email" class="block text-sm font-medium text-gray-700">Email:</label>
                    <input type="email" id="edit_email" name="email" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($user_to_edit_email); ?>" required />
                </div>
                <div>
                    <label for="edit_status" class="block text-sm font-medium text-gray-700">Status:</label>
                    <select id="edit_status" name="status" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
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
            
            <form action="<?php echo htmlspecialchars($base_script_path); ?>" method="GET" class="mb-6">
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
                                        <a href="<?php echo get_user_redirect_url($base_script_path, $search_query, $status_filter) . (strpos(get_user_redirect_url($base_script_path, $search_query, $status_filter), '?') === false ? '?' : '&') . 'edit_user=' . $teacher['id']; ?>#edit-section" class="text-indigo-600 hover:text-indigo-900"><i class="fas fa-edit mr-1"></i>Edit</a>
                                        
                                        <button type="button" class="text-red-600 hover:text-red-900 delete-button" data-user-id="<?php echo $teacher['id']; ?>" data-username="<?php echo htmlspecialchars($teacher['username']); ?>">
                                            <i class="fas fa-trash-alt mr-1"></i>Delete
                                        </button>

                                        <form id="deleteForm-<?php echo $teacher['id']; ?>" action="<?php echo get_user_redirect_url($base_script_path, $search_query, $status_filter); ?>" method="POST" style="display: none;">
                                            <input type="hidden" name="user_id_to_delete" value="<?php echo $teacher['id']; ?>">
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
</div> <script>
document.addEventListener('DOMContentLoaded', () => {
    // --- (Any global scripts like sidebar toggling from your original code can remain) ---
    const sidebar = document.getElementById('sidebar');
        const sidebarToggle = document.getElementById('sidebarToggle');
        const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
        const sidebarTexts = sidebar ? sidebar.querySelectorAll('.sidebar-text') : [];

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
                toggleIcon.title = isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar';
            }
        }

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', toggleSidebarDesktop);
            // Initial state based on class (optional, if you want it to remember state via class)
            const isCollapsed = sidebar.classList.contains('w-20');
            const toggleIcon = sidebarToggle.querySelector('i');
            if (toggleIcon) {
                toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed);
                toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
                toggleIcon.title = isCollapsed ? 'Expand Sidebar' : 'Collapse Sidebar';
            }
        }

        if (mobileSidebarToggle && sidebar) {
            sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
            mobileSidebarToggle.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('-translate-x-full');
                sidebar.classList.toggle('translate-x-0');
                // When mobile sidebar opens, ensure it's full width and texts are visible
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
    
    // --- Title Management ---
    const pageTitleElement = document.getElementById('page-title');
    if (pageTitleElement) {
        pageTitleElement.textContent = 'Manage Users';
    }
    document.title = 'Manage Users | Admin Dashboard';

    // --- Session-based Error Message Auto-hide ---
    const sessionErrorMessageDiv = document.getElementById('sessionErrorMessage');
    if (sessionErrorMessageDiv) {
        setTimeout(() => {
            sessionErrorMessageDiv.style.transition = 'opacity 0.5s ease-out';
            sessionErrorMessageDiv.style.opacity = '0';
            setTimeout(() => sessionErrorMessageDiv.remove(), 500);
        }, 5000);
    }

    // --- Smooth Scroll to Edit Section ---
    if (window.location.hash === '#edit-section') {
        document.getElementById('edit-section')?.scrollIntoView({ behavior: 'smooth' });
    }

    // --- MODIFIED: SweetAlert2 Delete Confirmation ---
    document.querySelectorAll('.delete-button').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const username = this.dataset.username;
            const deleteForm = document.getElementById(`deleteForm-${userId}`);

            if (deleteForm) {
                Swal.fire({
                    title: 'Are you sure?',
                    text: `You are about to delete the teacher: "${username}". `,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Delete'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // If confirmed, submit the specific hidden form
                        deleteForm.submit();
                    }
                });
            }
        });
    });

    // --- MODIFIED: SweetAlert2 Logic for POST Action Results ---
    const phpSuccessMessage = <?php echo json_encode($modal_success_message); ?>;
    const phpErrorMessage = <?php echo json_encode($modal_error_message); ?>;

    const currentUrl = new URL(window.location.href);

    if (phpSuccessMessage) {
        Swal.fire({
            title: 'Success!',
            text: phpSuccessMessage,
            icon: 'success',
            confirmButtonText: 'OK'
        }).then(() => {
            // After successful update, redirect to the clean URL without edit params
            if (currentUrl.searchParams.has('edit_user')) {
                currentUrl.searchParams.delete('edit_user');
                // Remove hash as well
                currentUrl.hash = '';
                window.location.href = currentUrl.toString();
            }
        });
    } else if (phpErrorMessage) {
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
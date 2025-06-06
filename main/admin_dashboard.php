<?php
session_start();

// --- 1. Admin Access Check ---
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php"); // Adjust path if needed
    exit();
}

// --- 2. DB Connection ---
include 'db_connection.php'; // Ensure this file exists

// --- 3. Search Query Handling ---
$search_query = '';
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['search_query'])) {
    $search_query = trim($_POST['search_query']);
} elseif (isset($_GET['search_query'])) {
    $search_query = trim($_GET['search_query']);
}

// --- 4. Messages & Edit Variables ---
$error_message = null;
$success_message = null;
$language_to_edit_id = null;
$language_to_edit_name = '';

// --- 5. Session Messages ---
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

// --- 6. Helper for Redirect URL (mainly for search query preservation) ---
function get_redirect_url_with_search($base_url, $query) {
    $url = $base_url;
    if ($query !== '') {
        $url .= "?search_query=" . urlencode($query);
    }
    return $url;
}

// --- 7. POST Handling (Add Language) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_language'])) {
    $new_language_name = trim($_POST['language_name'] ?? '');
    $created_by_user_id = $_SESSION['user_id'];

    if ($new_language_name === '') {
        $_SESSION['error_message'] = "Language name cannot be empty.";
    } elseif (empty($created_by_user_id)) {
        $_SESSION['error_message'] = "User session error. Cannot add language.";
    } else {
        $stmt = $conn->prepare("INSERT INTO languages (language_name, created_by) VALUES (?, ?)");
        if ($stmt) {
            $stmt->bind_param("si", $new_language_name, $created_by_user_id);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = "Language '" . htmlspecialchars($new_language_name) . "' added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding language: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing statement for add: " . htmlspecialchars($conn->error);
        }
    }
    header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
    exit();
}

// --- 8. POST Handling (Delete Language) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['delete_language'])) {
    $language_id_to_delete = filter_var($_POST['language_id_to_delete'] ?? '', FILTER_VALIDATE_INT);
    if ($language_id_to_delete === false) {
        $_SESSION['error_message'] = "Invalid language ID for deletion.";
    } else {
        $stmt = $conn->prepare("DELETE FROM languages WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $language_id_to_delete);
            if ($stmt->execute()) {
                $_SESSION['success_message'] = $stmt->affected_rows > 0 ? "Language deleted successfully!" : "Language not found or already deleted.";
            } else {
                $_SESSION['error_message'] = "Error deleting language: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing statement for delete: " . htmlspecialchars($conn->error);
        }
    }
    header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
    exit();
}

// --- 9. GET Handling (Fetch Language for Edit) ---
if (isset($_GET['edit_language']) && filter_var($_GET['edit_language'], FILTER_VALIDATE_INT)) {
    $language_to_edit_id_from_get = (int)$_GET['edit_language'];
    $stmt = $conn->prepare("SELECT id, language_name FROM languages WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $language_to_edit_id_from_get);
        $stmt->execute();
        $result_edit = $stmt->get_result();
        if ($row_edit = $result_edit->fetch_assoc()) {
            $language_to_edit_id = $row_edit['id'];
            $language_to_edit_name = $row_edit['language_name'];
        } else {
            $_SESSION['error_message'] = "Language not found for editing.";
            header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
            exit();
        }
        $stmt->close();
    } else {
        $_SESSION['error_message'] = "Error preparing statement for language edit: " . htmlspecialchars($conn->error);
        header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
        exit();
    }
}

// --- 10. POST Handling (Update Language) ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_language'])) {
    $language_id_to_update = filter_var($_POST['language_id_to_update'] ?? '', FILTER_VALIDATE_INT);
    $updated_language_name = trim($_POST['language_name'] ?? '');
    if ($language_id_to_update === false || $updated_language_name === '') {
        $_SESSION['error_message'] = "Invalid data for updating language.";
    } else {
        $stmt = $conn->prepare("UPDATE languages SET language_name = ? WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $updated_language_name, $language_id_to_update);
            if ($stmt->execute()) {
                 $_SESSION['success_message'] = $stmt->affected_rows > 0 ? "Language '" . htmlspecialchars($updated_language_name) . "' updated successfully!" : "No changes made.";
            } else {
                $_SESSION['error_message'] = "Error updating language: " . htmlspecialchars($stmt->error);
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing statement for update: " . htmlspecialchars($conn->error);
        }
    }
    header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
    exit();
}

// --- 11. Fetch Languages (with Search) ---
$sql = "SELECT id, language_name FROM languages";
$params = []; $types = '';
if ($search_query !== '') {
    $sql .= " WHERE language_name LIKE ?";
    $search_param = "%" . $search_query . "%";
    $params[] = $search_param; $types .= "s";
}
$sql .= " ORDER BY id ASC";

$stmt_all = $conn->prepare($sql); $result_all_languages = false;
if ($stmt_all) {
    if (!empty($params)) $stmt_all->bind_param($types, ...$params);
    if ($stmt_all->execute()) $result_all_languages = $stmt_all->get_result();
    else $error_message = "Error executing fetch: " . ($stmt_all->error ?? $conn->error);
    $stmt_all->close();
} else {
    $error_message = "Error preparing fetch: " . htmlspecialchars($conn->error);
}
if (!$result_all_languages && $error_message === null) {
     $error_message = "Could not fetch languages data.";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Manage Languages | Admin Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
    <style>
        ::-webkit-scrollbar { width: 8px; height: 8px; } ::-webkit-scrollbar-track { background: #1f2937; } ::-webkit-scrollbar-thumb { background: #4b5563; border-radius: 4px; } ::-webkit-scrollbar-thumb:hover { background: #6b7280; }
        .sidebar { transition: width 0.3s ease-in-out; } .btn-icon { display: inline-flex; align-items: center; justify-content: center; } .btn-icon i { margin-right: 0.5rem; }
        .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; box-shadow: 0 1px 3px rgba(0,0,0,0.1); transition: opacity 0.5s ease-out; } .flash-message.fade-out { opacity: 0; }
        .flash-success { background-color: #d1fae5; border-left: 4px solid #10b981; color: #065f46; } .flash-error { background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; }
        #sidebar { height: 100vh; } @media (max-width: 1023px) { #sidebar { position: fixed; top: 0; left: 0; z-index: 40; } }
        .input-std { display: block; width: 100%; padding-left: 0.75rem; padding-right: 0.75rem; padding-top: 0.5rem; padding-bottom: 0.5rem; background-color: #ffffff; border-width: 1px; border-color: #d1d5db; border-radius: 0.375rem; box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05); outline: none; &:focus { --tw-ring-color: #4f46e5; border-color: #4f46e5; } }
    </style>
</head>
<body class="bg-gray-100 font-sans antialiased">

<div class="flex h-screen overflow-hidden">
    <?php include 'sidebar.php'; // Your sidebar.php ?>

    <div class="flex-1 flex flex-col overflow-hidden">
        <header class="bg-white shadow-md">
             <div class="container mx-auto px-6 py-3 flex items-center justify-between">
                <div class="flex items-center">
                    <button id="mobileSidebarToggle" class="text-gray-600 focus:outline-none lg:hidden mr-4"> <i class="fas fa-bars text-xl"></i> </button>
                     <h1 class="text-xl font-semibold text-gray-700 hidden lg:block">Manage Languages</h1>
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

                <section id="manage-languages" class="mb-12">
                    <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4"><?php echo $language_to_edit_id ? 'Edit Language' : 'Add New Language'; ?></h2>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-4">
                            <?php if ($language_to_edit_id): ?><input type="hidden" name="language_id_to_update" value="<?php echo htmlspecialchars($language_to_edit_id); ?>"><?php endif; ?>
                            <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                            <div>
                                <label for="language_name_input" class="block text-sm font-medium text-gray-700">Language Name:</label>
                                <input type="text" id="language_name_input" name="language_name" class="mt-1 input-std" placeholder="e.g., English, Spanish, Malay" value="<?php echo htmlspecialchars($language_to_edit_name); ?>" required />
                            </div>
                            <div class="flex items-center">
                                <?php if ($language_to_edit_id): ?>
                                    <button type="submit" name="update_language" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm"><i class="fas fa-save"></i>Update Language</button>
                                    <a href="<?php echo get_redirect_url_with_search('admin_dashboard.php', $search_query); ?>" class="ml-3 btn-icon border border-gray-300 py-2 px-4 rounded-md bg-white hover:bg-gray-50 text-gray-700">Cancel Edit</a>
                                <?php else: ?>
                                    <button type="submit" name="add_language" class="btn-icon bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm"><i class="fas fa-plus-circle"></i>Add Language</button>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>

                    <div class="bg-white p-6 rounded-lg shadow-lg">
                        <h2 class="text-xl font-semibold text-gray-700 mb-4">Existing Languages</h2>
                        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="GET" class="mb-6">
                            <div class="flex items-center space-x-3 bg-gray-50 p-4 rounded-md border border-gray-200">
                                <div class="relative flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                                    <input type="text" name="search_query" id="search_query_input" placeholder="Search languages..." class="block w-full pl-10 px-3 py-2 bg-white border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" value="<?php echo htmlspecialchars($search_query); ?>">
                                </div>
                                <button type="submit" class="btn-icon bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm"><i class="fas fa-search"></i>Search</button>
                                <?php if (!empty($search_query)): ?>
                                <a href="admin_dashboard.php" class="btn-icon text-gray-600 hover:text-gray-900 bg-white hover:bg-gray-100 border border-gray-300 py-2 px-4 rounded-md text-sm shadow-sm"><i class="fas fa-times"></i>Clear</a>
                                <?php endif; ?>
                            </div>
                        </form>

                        <div class="overflow-x-auto">
                            <?php if ($result_all_languages && $result_all_languages->num_rows > 0): ?>
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50"><tr><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">ID</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Language Name</th><th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th></tr></thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php while ($row = $result_all_languages->fetch_assoc()): ?>
                                            <?php
                                                // --- FIX FOR EDIT LINK ---
                                                $edit_params = ['edit_language' => $row['id']];
                                                if ($search_query !== '') {
                                                    $edit_params['search_query'] = $search_query;
                                                }
                                                $edit_url = 'admin_dashboard.php?' . http_build_query($edit_params);
                                            ?>
                                            <tr>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo $row['id']; ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm"><?php echo htmlspecialchars($row['language_name']); ?></td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                                    <a href="<?php echo htmlspecialchars($edit_url); ?>#manage-languages" class="text-indigo-600 hover:text-indigo-900"><i class="fas fa-edit mr-1"></i>Edit</a>
                                                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="inline-block">
                                                        <input type="hidden" name="language_id_to_delete" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                                                        <button type="submit" name="delete_language" class="text-red-600 hover:text-red-900" onclick="return confirm('Delete \'<?php echo addslashes(htmlspecialchars($row['language_name'])); ?>\'?');"><i class="fas fa-trash-alt mr-1"></i>Delete</button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="text-center py-8"><i class="fas fa-box-open fa-3x text-gray-400 mb-3"></i><p class="text-gray-500"><?php echo !empty($search_query) ? "No languages found matching your search." : "No languages found."; ?></p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </section>
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
            const isCollapsed = sidebar.classList.contains('w-20'); const toggleIcon = sidebarToggle.querySelector('i');
            sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
            toggleIcon.classList.toggle('fa-chevron-left', !isCollapsed); toggleIcon.classList.toggle('fa-chevron-right', isCollapsed);
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
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
$error_message = $_SESSION['error_message'] ?? null;
$success_message = $_SESSION['success_message'] ?? null;
unset($_SESSION['error_message'], $_SESSION['success_message']); // Clear messages after retrieving

$language_to_edit_id = null;
$language_to_edit_name = '';

function get_redirect_url_with_search($base_url, $query) {
    return $query ? $base_url . "?search_query=" . urlencode($query) : $base_url;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $redirect_url = get_redirect_url_with_search("admin_dashboard.php", $_POST['search_query'] ?? '');
    if (isset($_POST['add_language'])) {
        $new_language_name = trim($_POST['language_name'] ?? '');
        if (!empty($new_language_name)) {
            $stmt = $conn->prepare("INSERT INTO languages (language_name, created_by) VALUES (?, ?)");
            if ($stmt) {
                $stmt->bind_param("si", $new_language_name, $_SESSION['user_id']);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "Language '" . htmlspecialchars($new_language_name) . "' added successfully.";
                } else {
                    $_SESSION['error_message'] = "Error adding language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing add language statement: " . $conn->error;
            }
        } else { $_SESSION['error_message'] = "Language name cannot be empty."; }
    }
    if (isset($_POST['delete_language'])) {
        $language_id = filter_var($_POST['language_id_to_delete'], FILTER_VALIDATE_INT);
        if ($language_id !== false) { // Check for valid integer
            $stmt = $conn->prepare("DELETE FROM languages WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("i", $language_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = ($stmt->affected_rows > 0) ? "Language deleted successfully." : "Language not found or already deleted.";
                } else {
                    $_SESSION['error_message'] = "Error deleting language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing delete language statement: " . $conn->error;
            }
        } else { $_SESSION['error_message'] = "Invalid language ID for deletion."; }
    }
    if (isset($_POST['update_language'])) {
        $language_id = filter_var($_POST['language_id_to_update'], FILTER_VALIDATE_INT);
        $updated_name = trim($_POST['language_name'] ?? '');
        if ($language_id !== false && !empty($updated_name)) { // Check for valid integer and non-empty name
            $stmt = $conn->prepare("UPDATE languages SET language_name = ? WHERE id = ?");
            if ($stmt) {
                $stmt->bind_param("si", $updated_name, $language_id);
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = ($stmt->affected_rows > 0) ? "Language updated successfully." : "No changes were made to the language.";
                } else {
                    $_SESSION['error_message'] = "Error updating language: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $_SESSION['error_message'] = "Error preparing update language statement: " . $conn->error;
            }
        } else { $_SESSION['error_message'] = "Invalid data for update."; }
    }
    header("Location: " . $redirect_url);
    exit(); // Always exit after a header redirect
}

// Fetch language for editing if 'edit_language' GET parameter is present.
if (isset($_GET['edit_language'])) {
    $language_to_edit_id = filter_var($_GET['edit_language'], FILTER_VALIDATE_INT);
    if ($language_to_edit_id !== false) { // Ensure it's a valid integer
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
                exit(); // Exit after redirect
            }
            $stmt->close();
        } else {
            $_SESSION['error_message'] = "Error preparing fetch language for edit statement: " . $conn->error;
            header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
            exit(); // Exit after redirect
        }
    } else {
        $_SESSION['error_message'] = "Invalid ID for editing.";
        header("Location: " . get_redirect_url_with_search("admin_dashboard.php", $search_query));
        exit(); // Exit after redirect
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
    if ($stmt_all->execute()) { // Check if execute was successful
        $result_all_languages = $stmt_all->get_result();
    } else {
        $error_message = "Error executing fetch all languages statement: " . $stmt_all->error;
        $result_all_languages = false; // Ensure result is false on failure
    }
    $stmt_all->close();
} else {
    $error_message = "Error preparing fetch all languages statement: " . $conn->error;
    $result_all_languages = false; // Ensure result is false on failure
}

// --- Include the Standardized Header ---
// This file will now provide the <DOCTYPE html>, <html>, <head>, <body>, and the initial layout divs.
include 'header.php';
?>

<!--
    No <head>, <body>, or outer <div>s needed here as they come from header.php.
    Only page-specific styles or additional head elements should go here if header.php doesn't include them.
    The <title> tag should ideally be set directly in header.php or via JavaScript on DOMContentLoaded
    if header.php provides a common title template. For now, it's handled by JS below.
-->

<!-- Page-specific styles -->
<style>
    /*
     * Styles below are kept here for demonstration purposes,
     * but ideally, all shared styles (like scrollbar, sidebar transitions, flash messages, and common input styles)
     * should be in a separate CSS file or directly within header.php's <style> block
     * to avoid duplication and ensure consistency across all pages that include header.php.
     */
    .btn-icon { display: inline-flex; align-items: center; justify-content: center; }
    .btn-icon i { margin-right: 0.5rem; }
    .flash-message { padding: 1rem; margin-bottom: 1rem; border-radius: 0.375rem; transition: opacity 0.5s ease-out; }
    .flash-message.fade-out { opacity: 0; }
    .flash-success { background-color: #d1fae5; border-left: 4px solid #10b981; color: #065f46; }
    .flash-error { background-color: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; }

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
</style>

<!-- MAIN CONTENT AREA -->
<main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 p-6">
    <div class="container mx-auto">
        <?php if ($success_message): ?>
            <div id="successMessage" class="flash-message flash-success flex items-center">
                <i class="fas fa-check-circle fa-lg mr-3 py-1"></i>
                <div><p class="font-bold">Success</p><p class="text-sm"><?php echo htmlspecialchars($success_message); ?></p></div>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div id="errorMessage" class="flash-message flash-error flex items-center">
                <i class="fas fa-exclamation-triangle fa-lg mr-3 py-1"></i>
                <div><p class="font-bold">Error</p><p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p></div>
            </div>
        <?php endif; ?>

        <section id="manage-languages" class="mb-12">
            <div class="bg-white p-6 rounded-lg shadow-lg mb-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4"><?php echo $language_to_edit_id ? 'Edit Language' : 'Add New Language'; ?></h2>
                <form action="admin_dashboard.php" method="POST" class="space-y-4">
                    <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                    <?php if ($language_to_edit_id): ?><input type="hidden" name="language_id_to_update" value="<?php echo htmlspecialchars($language_to_edit_id); ?>"><?php endif; ?>
                    <div>
                        <label for="language_name_input" class="block text-sm font-medium text-gray-700">Language Name:</label>
                        <input type="text" id="language_name_input" name="language_name" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" placeholder="e.g., English, Spanish, Malay" value="<?php echo htmlspecialchars($language_to_edit_name); ?>" required />
                    </div>
                    <div class="flex items-center">
                        <?php if ($language_to_edit_id): ?>
                            <button type="submit" name="update_language" class="btn-icon bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-md"><i class="fas fa-save"></i>Update</button>
                            <a href="admin_dashboard.php?search_query=<?php echo urlencode($search_query); ?>" class="ml-3 border border-gray-300 py-2 px-4 rounded-md bg-white hover:bg-gray-50">Cancel</a>
                        <?php else: ?>
                            <button type="submit" name="add_language" class="btn-icon bg-green-600 hover:bg-green-700 text-white font-semibold py-2 px-4 rounded-md"><i class="fas fa-plus-circle"></i>Add</button>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-semibold text-gray-700">Existing Languages</h2>
                    <form action="admin_dashboard.php" method="GET" class="flex items-center space-x-2 w-full md:w-1/3">
                        <div class="relative flex-grow">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none"><i class="fas fa-search text-gray-400"></i></div>
                            <input type="text" name="search_query" placeholder="Type language name..." class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" value="<?php echo htmlspecialchars($search_query); ?>">
                        </div>
                        <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md">Search</button>
                        <?php if (!empty($search_query)): ?>
                            <a href="admin_dashboard.php" class="text-gray-600 bg-gray-200 hover:bg-gray-300 py-2 px-4 rounded-md text-sm whitespace-nowrap">Clear</a>
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
                                        <form action="admin_dashboard.php" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this language?');">
                                            <input type="hidden" name="language_id_to_delete" value="<?php echo $row['id']; ?>">
                                            <input type="hidden" name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                                            <button type="submit" name="delete_language" class="text-red-600 hover:text-red-900"><i class="fas fa-trash-alt mr-1"></i>Delete</button>
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

<!-- Closing tags for HTML structure initiated in header.php -->
</div> <!-- Closes <div class="flex-1 flex flex-col overflow-hidden"> from header.php -->
</div> <!-- Closes <div class="flex h-screen overflow-hidden"> from header.php -->

<script>
document.addEventListener('DOMContentLoaded', () => {
    // This script block should contain JavaScript specific to admin_dashboard.php.
    // The user dropdown JS is now handled entirely within header.php.

    // Update the page title in the header h1 (id="page-title" from header.php)
    const pageTitleElement = document.getElementById('page-title');
    if (pageTitleElement) {
        pageTitleElement.textContent = 'Manage Languages';
    }
    // Update the browser tab title
    document.title = 'Manage Languages | Admin Dashboard';

    // Sidebar toggle logic (from original admin_dashboard.php, kept here for completeness if not moved globally)
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileSidebarToggle = document.getElementById('mobileSidebarToggle');
    // Ensure sidebarTexts is robust to sidebar being null (though unlikely in this setup)
    const sidebarTexts = sidebar ? sidebar.querySelectorAll('.sidebar-text') : [];
    
    function toggleSidebarDesktop() {
        if (!sidebar || !sidebarToggle) return;
        const toggleIcon = sidebarToggle.querySelector('i');
        sidebar.classList.toggle('w-64');
        sidebar.classList.toggle('w-20');
        const isCollapsed = sidebar.classList.contains('w-20');
        sidebarTexts.forEach(text => text.classList.toggle('hidden', isCollapsed));
        if (toggleIcon) {
            // Use classes for icons or simpler toggle logic if fa-chevron-left/right are used
            // This transform rotates the icon for a visual effect.
            toggleIcon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebarDesktop);
        // Set initial state of icon based on sidebar's initial collapsed state
        const isCollapsed = sidebar.classList.contains('w-20');
        const toggleIcon = sidebarToggle.querySelector('i');
        if (toggleIcon) {
            toggleIcon.style.transform = isCollapsed ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }
    
    if (mobileSidebarToggle && sidebar) {
        // Ensure initial state is hidden on small screens
        sidebar.classList.add('fixed', 'inset-y-0', 'left-0', 'z-30', 'lg:translate-x-0', 'lg:static', 'lg:inset-auto', '-translate-x-full');
        mobileSidebarToggle.addEventListener('click', e => {
            e.stopPropagation(); // Stop event propagation to prevent immediate closing by document click
            sidebar.classList.toggle('-translate-x-full');
            sidebar.classList.toggle('translate-x-0'); // Show sidebar by removing -translate-x-full
        });
        document.addEventListener('click', e => {
            // Close mobile sidebar if clicked outside, and it's currently open, and on a small screen
            if (sidebar && !sidebar.contains(e.target) && !mobileSidebarToggle.contains(e.target) && sidebar.classList.contains('translate-x-0')) {
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.add('-translate-x-full');
            }
        });
    }

    // Flash message auto-hide logic
    const successAlert = document.getElementById('successMessage');
    const errorAlert = document.getElementById('errorMessage');
    function autoHide(el) {
        if(el) {
            setTimeout(() => {
                el.style.opacity = '0';
                setTimeout(() => el.remove(), 500); // Use .remove() instead of .style.display = 'none'
            }, 4000);
        }
    }
    autoHide(successAlert);
    autoHide(errorAlert);

    // If editing, scroll to edit section (if it exists)
    if (window.location.hash === '#manage-languages') { // Adjusted hash to match the section ID
        const manageLanguagesSection = document.getElementById('manage-languages');
        if (manageLanguagesSection) {
            manageLanguagesSection.scrollIntoView({ behavior: 'smooth' });
        }
    }
});
</script>

</body>
</html>

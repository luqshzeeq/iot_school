<?php
// This file is included by teacher_dashboard.php when $page is 'profile'.
// Expected variables from teacher_dashboard.php:
// $conn, $teacher_id, $teacher_username, $teacher_email, $profile_page_pic_url, $csrf_token
// $profile_update_success, $profile_update_errors (for messages)
// $show_password_error_popup_from_session (passed from dashboard.php)

// Fetch latest profile details if not already passed or if page is directly loaded
// These variables are typically already set by teacher_dashboard.php before including this file.
$current_username = $teacher_username;
$current_email = $teacher_email;
$current_profile_pic_path = $profile_page_pic_url;

// This variable is now directly received from teacher_dashboard.php
$show_password_error_popup = $show_password_error_popup_from_session ?? false;

// Handle form submission for profile updates
// IMPORTANT: No header() or exit() calls in this included file's POST handling.
// All redirects are managed by the parent teacher_dashboard.php.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_action'])) {
    // CSRF Token Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['profile_update_errors'][] = "Security error: Invalid CSRF token. Please try again.";
        // No redirect here; let the parent script continue.
    } else {
        $new_username = htmlspecialchars(trim($_POST['username']));
        $new_email = htmlspecialchars(trim($_POST['email']));
        $current_password_input = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_new_password = $_POST['confirm_password'] ?? ''; // Corrected form field name

        $errors = [];
        $success_messages = [];

        // --- Input Validations ---
        if (empty($new_username)) {
            $errors[] = "Username is required.";
        }
        if (empty($new_email)) {
            $errors[] = "Email is required.";
        } elseif (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format.";
        }

        // Fetch current user data, especially the plaintext password and profile picture path
        $sql_fetch_user = "SELECT password, profile_pic_path FROM users WHERE id = ?";
        $stmt_fetch_user = $conn->prepare($sql_fetch_user);
        $stored_plaintext_password = '';
        $old_profile_pic_path = '';

        if ($stmt_fetch_user) {
            $stmt_fetch_user->bind_param("i", $teacher_id);
            $stmt_fetch_user->execute();
            $result_user = $stmt_fetch_user->get_result();
            if ($user_data = $result_user->fetch_assoc()) {
                $stored_plaintext_password = $user_data['password']; // Retrieve plaintext password
                $old_profile_pic_path = $user_data['profile_pic_path'];
            }
            $stmt_fetch_user->close();
        } else {
            $errors[] = "Database error fetching user data for update.";
        }

        // --- Password Change Logic (PLAIN TEXT - INSECURE) ---
        $update_password_sql_part = "";
        $password_to_save = null;

        $intends_to_change_password = (!empty($current_password_input) || !empty($new_password) || !empty($confirm_new_password));

        if ($intends_to_change_password) {
            // 1. Verify Current Password (plaintext comparison)
            if (empty($current_password_input)) {
                $errors[] = "Please enter your current password to change it.";
            } elseif ($current_password_input !== $stored_plaintext_password) { // Plaintext comparison
                $errors[] = "Incorrect current password.";
                $show_password_error_popup = true; // Trigger popup
            }

            // 2. Validate New Password (only if current password is correct or no other errors yet)
            // This condition ensures new password validation only proceeds if current password is correct,
            // or if the *only* error so far is the current password itself.
            if ($show_password_error_popup === false || (count($errors) == 1 && in_array("Incorrect current password.", $errors))) {
                if (empty($new_password)) {
                    $errors[] = "New password cannot be empty if you are changing it.";
                } elseif (strlen($new_password) < 6) {
                    $errors[] = "New password must be at least 6 characters long.";
                } elseif ($new_password !== $confirm_new_password) {
                    $errors[] = "New password and confirm new password do not match.";
                } else {
                    $password_to_save = $new_password; // ⚠️ STORING PLAIN TEXT PASSWORD
                    $update_password_sql_part = "password = ?";
                }
            }
        }

        // --- Profile Picture Upload Handling ---
        $profile_pic_path_to_save_in_db = null;
        $update_profile_pic_sql_part = "";

        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
            $upload_dir = "uploads/profile_pics/";
            if (!is_dir($upload_dir)) {
                if (!mkdir($upload_dir, 0755, true)) {
                    $errors[] = "Failed to create upload directory. Check server permissions.";
                }
            }

            if (empty($errors)) {
                $tmp_name = $_FILES['profile_pic']['tmp_name'];
                $original_name = basename($_FILES['profile_pic']['name']);
                $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                $max_file_size = 5 * 1024 * 1024; // 5 MB

                if (!in_array($file_extension, $allowed_extensions)) {
                    $errors[] = "Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.";
                } elseif ($_FILES['profile_pic']['size'] > $max_file_size) {
                    $errors[] = "Profile picture file is too large (max 5MB).";
                } else {
                    $new_filename = "user_" . $teacher_id . "_" . uniqid('', true) . "." . $file_extension;
                    $destination_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($tmp_name, $destination_path)) {
                        $profile_pic_path_to_save_in_db = $destination_path;
                        $update_profile_pic_sql_part = "profile_pic_path = ?";
                        $success_messages[] = "Profile picture updated.";
                    } else {
                        $errors[] = "Failed to upload profile picture.";
                    }
                }
            }
        }

        // If any errors occurred during validation or file upload, store them
        if (!empty($errors)) {
            $_SESSION['profile_update_errors'] = array_merge(($_SESSION['profile_update_errors'] ?? []), $errors);
            $_SESSION['show_password_error_popup'] = $show_password_error_popup;
            // Clean up potentially uploaded file if there's an error preventing DB save
            if ($profile_pic_path_to_save_in_db && file_exists($profile_pic_path_to_save_in_db)) {
                unlink($profile_pic_path_to_save_in_db);
            }
            // No redirect here
        } else {
            // --- Build SQL Update Statement ---
            $sql_parts = [];
            $params = [];
            $types = "";

            $sql_parts[] = "username = ?";
            $params[] = $new_username;
            $types .= "s";

            $sql_parts[] = "email = ?";
            $params[] = $new_email;
            $types .= "s";

            if (!empty($update_password_sql_part) && $password_to_save !== null) {
                $sql_parts[] = $update_password_sql_part;
                $params[] = $password_to_save;
                $types .= "s";
            }

            if (!empty($update_profile_pic_sql_part) && $profile_pic_path_to_save_in_db !== null) {
                $sql_parts[] = $update_profile_pic_sql_part;
                $params[] = $profile_pic_path_to_save_in_db;
                $types .= "s";
            }

            // If no fields are actually changing based on form input, set a message
            if (empty($sql_parts)) {
                $_SESSION['profile_update_success'] = "No changes detected for username, email, or password. Profile picture was also not changed.";
            } else {
                $sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
                $params[] = $teacher_id;
                $types .= "i";

                $stmt = $conn->prepare($sql);

                if ($stmt) {
                    $stmt->bind_param($types, ...$params);

                    if ($stmt->execute()) {
                        $success_messages[] = "Profile details (username/email/password) updated successfully!";

                        // Delete old profile picture only if a new one was successfully saved
                        if ($profile_pic_path_to_save_in_db && $old_profile_pic_path &&
                            file_exists($old_profile_pic_path) &&
                            $old_profile_pic_path !== $profile_pic_path_to_save_in_db &&
                            strpos($old_profile_pic_path, 'placehold.co') === false // Don't delete placeholder images
                        ) {
                            @unlink($old_profile_pic_path);
                        }

                        // Update session username if it changed
                        if (isset($_SESSION['username']) && $_SESSION['username'] !== $new_username) {
                            $_SESSION['username'] = $new_username;
                        }

                        $_SESSION['profile_update_success'] = implode(" ", $success_messages);
                    } else {
                        $errors[] = "Database error during update: " . $stmt->error;
                        // Clean up potentially uploaded file if DB update failed
                        if ($profile_pic_path_to_save_in_db && file_exists($profile_pic_path_to_save_in_db)) {
                            unlink($profile_pic_path_to_save_in_db);
                        }
                        $_SESSION['profile_update_errors'] = array_merge(($_SESSION['profile_update_errors'] ?? []), $errors);
                    }
                    $stmt->close();
                } else {
                    $errors[] = "Database error preparing update statement: " . $conn->error;
                    // Clean up potentially uploaded file if DB prepare failed
                    if ($profile_pic_path_to_save_in_db && file_exists($profile_pic_path_to_save_in_db)) {
                        unlink($profile_pic_path_to_save_in_db);
                    }
                    $_SESSION['profile_update_errors'] = array_merge(($_SESSION['profile_update_errors'] ?? []), $errors);
                }
            }
        }
    } // End of valid CSRF token block
}


// Re-fetch current data after potential update for display
// This ensures the displayed profile details are always current,
// regardless of whether an update just happened or not.
$sql_current_profile = "SELECT username, email, profile_pic_path FROM users WHERE id = ?";
$stmt_current_profile = $conn->prepare($sql_current_profile);
if ($stmt_current_profile) {
    $stmt_current_profile->bind_param("i", $teacher_id);
    if ($stmt_current_profile->execute()) {
        $result_current_profile = $stmt_current_profile->get_result();
        if ($row_current_profile = $result_current_profile->fetch_assoc()) {
            $current_username = htmlspecialchars($row_current_profile['username']);
            $current_email = htmlspecialchars($row_current_profile['email']);
            if (!empty($row_current_profile['profile_pic_path']) && file_exists($row_current_profile['profile_pic_path'])) {
                $current_profile_pic_path = htmlspecialchars($row_current_profile['profile_pic_path']);
            } else {
                // Fallback to placeholder if path is empty or file doesn't exist
                $current_profile_pic_path = 'https://placehold.co/120x120/BFDBFE/1E40AF?text=' . strtoupper(substr($current_username, 0, 1));
            }
        }
    }
    $stmt_current_profile->close();
}

// The $show_password_error_popup is handled by teacher_dashboard.php which passes it in.
// No need to fetch from session or unset here.
?>

<div class="max-w-3xl mx-auto">
    <div class="card">
        <div class="card-header">
            <h3 class="text-lg font-semibold text-gray-800">My Profile</h3>
        </div>
        <div class="card-body">
            <div id="profileView">
                <div class="flex flex-col items-center mb-6">
                    <img id="profileImage" src="<?php echo $current_profile_pic_path; ?>" alt="Profile Picture" class="w-32 h-32 rounded-full object-cover border-4 border-blue-400 shadow-md">
                    <h4 class="text-xl font-bold text-gray-900 mt-4"><?php echo $current_username; ?></h4>
                    <p class="text-gray-600"><?php echo $current_email; ?></p>
                </div>

                <div class="space-y-4">
                    <div>
                        <p class="text-sm font-medium text-gray-700">Username</p>
                        <p class="text-gray-900 font-semibold text-lg"><?php echo $current_username; ?></p>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-700">Email</p>
                        <p class="text-gray-900 font-semibold text-lg"><?php echo $current_email; ?></p>
                    </div>
                </div>
                <div class="mt-8 flex justify-end">
                    <button id="editProfileButton" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg shadow-md transition duration-150 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                        <i class="fas fa-edit mr-2"></i>Edit Profile
                    </button>
                </div>
            </div>

            <div id="profileEdit" class="hidden">
                <form id="profileEditForm" method="POST" action="teacher_dashboard.php?page=profile" enctype="multipart/form-data">
                    <input type="hidden" name="update_profile_action" value="1">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                    <div class="flex flex-col items-center mb-6">
                        <label for="profileImageUpload" class="cursor-pointer">
                            <img id="profileImagePreview" src="<?php echo $current_profile_pic_path; ?>" alt="Profile Picture" class="w-32 h-32 rounded-full object-cover border-4 border-blue-400 shadow-md transition-shadow duration-200 hover:shadow-lg">
                            <div class="mt-2 text-blue-600 hover:text-blue-700 text-sm font-medium text-center">Change Photo</div>
                            <input type="file" id="profileImageUpload" name="profile_pic" accept="image/*" class="hidden">
                        </label>
                    </div>

                    <div class="mb-5">
                        <label for="editName" class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                        <input type="text" id="editName" name="username" value="<?php echo $current_username; ?>"
                               class="w-full bg-white border border-gray-300 text-gray-900 px-3 py-2.5 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm" required>
                    </div>

                    <div class="mb-5">
                        <label for="editEmail" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" id="editEmail" name="email" value="<?php echo $current_email; ?>"
                               class="w-full bg-white border border-gray-300 text-gray-900 px-3 py-2.5 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm" required>
                    </div>

                    <h4 class="text-md font-semibold text-gray-800 mt-8 mb-4">Change Password (Optional)</h4>

                    <div class="mb-5">
                        <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">Current Password</label>
                        <div class="relative">
                            <input type="password" id="current_password" name="current_password"
                                   class="w-full bg-white border border-gray-300 text-gray-900 px-3 py-2.5 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm pr-10">
                            <button type="button" id="toggleCurrentPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-600 focus:outline-none">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p id="currentPasswordError" class="text-red-500 text-xs mt-1 hidden">Please enter your current password.</p>
                    </div>

                    <div class="mb-5">
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password <span class="text-gray-500 text-xs">(Min 6 characters)</span></label>
                        <div class="relative">
                            <input type="password" id="new_password" name="new_password"
                                   class="w-full bg-white border border-gray-300 text-gray-900 px-3 py-2.5 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm pr-10">
                            <button type="button" id="toggleNewPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-600 focus:outline-none">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p id="newPasswordError" class="text-red-500 text-xs mt-1 hidden">Password must be at least 6 characters long.</p>
                    </div>

                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <div class="relative">
                            <input type="password" id="confirm_password" name="confirm_password"
                                   class="w-full bg-white border border-gray-300 text-gray-900 px-3 py-2.5 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm pr-10">
                            <button type="button" id="toggleConfirmPassword" class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-600 focus:outline-none">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                        <p id="confirmPasswordError" class="text-red-500 text-xs mt-1 hidden">New passwords do not match.</p>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" id="cancelEditButton" class="px-5 py-2.5 bg-gray-200 hover:bg-gray-300 text-gray-700 font-semibold rounded-lg shadow-sm transition duration-150 focus:outline-none focus:ring-2 focus:ring-gray-400 focus:ring-opacity-50">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2.5 bg-green-600 hover:bg-green-700 text-white font-semibold rounded-lg shadow-md transition duration-150 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-opacity-50">
                            <i class="fas fa-save mr-2"></i>Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<div id="passwordErrorPopup" class="fixed inset-0 bg-red-600 bg-opacity-75 flex items-center justify-center z-50 transition-opacity duration-300 ease-out <?php echo $show_password_error_popup ? '' : 'opacity-0 pointer-events-none'; ?>">
    <div class="bg-white p-6 rounded-lg shadow-xl max-w-sm w-full transform transition-transform duration-300 ease-out <?php echo $show_password_error_popup ? 'scale-100' : 'scale-90'; ?>">
        <div class="text-center">
            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-red-100 mb-4">
                <svg class="h-6 w-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <h3 class="text-lg leading-6 font-medium text-gray-900">Password Error</h3>
            <div class="mt-2">
                <p class="text-sm text-gray-500">
                    The current password you entered is incorrect. Please try again.
                </p>
            </div>
        </div>
        <div class="mt-5 sm:mt-6">
            <button type="button" id="closePasswordErrorPopup" class="inline-flex justify-center w-full rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:text-sm">
                Got it!
            </button>
        </div>
    </div>
</div>


<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileImageUpload = document.getElementById('profileImageUpload');
    const profileImagePreview = document.getElementById('profileImagePreview');

    if (profileImageUpload && profileImagePreview) {
        profileImageUpload.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profileImagePreview.src = e.target.result;
                    const mainProfileImage = document.getElementById('profileImage');
                    if(mainProfileImage) mainProfileImage.src = e.target.result;

                    // Update topbar avatar if it uses a different ID/class
                    const topbarAvatar = document.querySelector('header img[alt="user photo"]'); // Targeting top bar avatar by alt
                    if(topbarAvatar) topbarAvatar.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // Password Toggle Functionality
    function setupPasswordToggle(inputId, toggleBtnId) {
        const passwordInput = document.getElementById(inputId);
        const toggleButton = document.getElementById(toggleBtnId);

        if (passwordInput && toggleButton) {
            toggleButton.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);

                this.querySelector('i').classList.toggle('fa-eye');
                this.querySelector('i').classList.toggle('fa-eye-slash');
            });
        }
    }

    setupPasswordToggle('current_password', 'toggleCurrentPassword');
    setupPasswordToggle('new_password', 'toggleNewPassword');
    setupPasswordToggle('confirm_password', 'toggleConfirmPassword');

    // Client-side Password Validation
    const currentPasswordInput = document.getElementById('current_password');
    const newPasswordInput = document.getElementById('new_password');
    const confirmPasswordInput = document.getElementById('confirm_password');

    const currentPasswordError = document.getElementById('currentPasswordError');
    const newPasswordError = document.getElementById('newPasswordError');
    const confirmPasswordError = document.getElementById('confirmPasswordError');
    const profileEditForm = document.getElementById('profileEditForm');

    function validatePasswords() {
        let isValid = true;
        const currentPassword = currentPasswordInput.value;
        const newPassword = newPasswordInput.value;
        const confirmPassword = confirmPasswordInput.value;

        // Reset errors
        currentPasswordError.classList.add('hidden');
        newPasswordError.classList.add('hidden');
        confirmPasswordError.classList.add('hidden');

        // Determine if the user is attempting to change password
        const isChangingPassword = currentPassword.length > 0 || newPassword.length > 0 || confirmPassword.length > 0;

        if (isChangingPassword) {
            // Requirement 1: Current Password must be entered
            if (currentPassword.length === 0) {
                currentPasswordError.textContent = "Please enter your current password.";
                currentPasswordError.classList.remove('hidden');
                isValid = false;
            }

            // Requirement 2: New password must be entered and meet length if current password or confirm password is provided
            // Only validate if current password field is filled OR new/confirm are filled.
            if (currentPassword.length > 0 || newPassword.length > 0 || confirmPassword.length > 0) {
                if (newPassword.length === 0) {
                    newPasswordError.textContent = "New password cannot be empty.";
                    newPasswordError.classList.remove('hidden');
                    isValid = false;
                } else if (newPassword.length < 6) { // Minimum password length: 6 characters
                    newPasswordError.textContent = "New password must be at least 6 characters long.";
                    newPasswordError.classList.remove('hidden');
                    isValid = false;
                }
            }

            // Requirement 3: New password and confirm password must match
            if (newPassword.length > 0 && newPassword !== confirmPassword) {
                confirmPasswordError.textContent = "New passwords do not match.";
                confirmPasswordError.classList.remove('hidden');
                isValid = false;
            }
        }
        return isValid;
    }

    // Add event listeners for real-time validation feedback
    currentPasswordInput.addEventListener('input', validatePasswords);
    newPasswordInput.addEventListener('input', validatePasswords);
    confirmPasswordInput.addEventListener('input', validatePasswords);

    // Prevent form submission if client-side validation fails
    profileEditForm.addEventListener('submit', function(event) {
        if (!validatePasswords()) {
            event.preventDefault(); // Stop form submission
            alert("Please correct the password errors before saving.");
        }
    });

    // Popup message functionality
    const passwordErrorPopup = document.getElementById('passwordErrorPopup');
    const closePasswordErrorPopup = document.getElementById('closePasswordErrorPopup');

    <?php if ($show_password_error_popup): ?>
        // Show popup when the page loads if PHP flag is set
        passwordErrorPopup.classList.remove('opacity-0', 'pointer-events-none');
        passwordErrorPopup.querySelector('div').classList.remove('scale-90');
        passwordErrorPopup.querySelector('div').classList.add('scale-100');
    <?php endif; ?>

    if (closePasswordErrorPopup) {
        closePasswordErrorPopup.addEventListener('click', function() {
            passwordErrorPopup.classList.add('opacity-0', 'pointer-events-none');
            passwordErrorPopup.querySelector('div').classList.remove('scale-100');
            passwordErrorPopup.querySelector('div').classList.add('scale-90');
        });
    }
});
</script>
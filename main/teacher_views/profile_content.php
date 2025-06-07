<?php
// This file is included by teacher_dashboard.php when $page is 'profile'.
// Expected variables from teacher_dashboard.php:
// $conn, $teacher_id, $teacher_username, $teacher_email, $profile_page_pic_url, $csrf_token
// $profile_update_success, $profile_update_errors (for messages)

// Fetch latest profile details if not already passed or if page is directly loaded
// (Redundant if main dashboard fetches, but good for direct access)
$current_username = $teacher_username;
$current_email = $teacher_email;
$current_profile_pic_path = $profile_page_pic_url; // Already handled by dashboard.php

// Handle form submission for profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile_action'])) {
    // CSRF Token Check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['profile_update_errors'][] = "Invalid CSRF token. Please try again.";
        header("Location: teacher_dashboard.php?page=profile");
        exit();
    }

    $new_username = htmlspecialchars(trim($_POST['username']));
    $new_email = htmlspecialchars(trim($_POST['email']));
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    $errors = [];

    // Basic validation
    if (empty($new_username)) {
        $errors[] = "Username cannot be empty.";
    }
    if (!filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    $sql_update_user = "UPDATE users SET username = ?, email = ? WHERE id = ?";
    $params = [$new_username, $new_email, $teacher_id];
    $types = "ssi";

    // Password update logic
    if (!empty($new_password)) {
        if ($new_password !== $confirm_password) {
            $errors[] = "New password and confirm password do not match.";
        } elseif (strlen($new_password) < 6) { // Minimum password length
            $errors[] = "Password must be at least 6 characters long.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $sql_update_user = "UPDATE users SET username = ?, email = ?, password = ? WHERE id = ?";
            $params = [$new_username, $new_email, $hashed_password, $teacher_id];
            $types = "sssi";
        }
    }

    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
        $file_name = $_FILES['profile_pic']['name'];
        $file_tmp = $_FILES['profile_pic']['tmp_name'];
        $file_size = $_FILES['profile_pic']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpeg', 'jpg', 'png', 'gif'];
        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = "Only JPG, JPEG, PNG & GIF files are allowed for profile picture.";
        }
        if ($file_size > 2097152) { // 2MB
            $errors[] = "Profile picture size must be less than 2MB.";
        }

        if (empty($errors)) {
            $upload_dir = 'uploads/profile_pics/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            $new_file_name = uniqid('profile_', true) . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;

            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old profile picture if it exists and is not the default
                $old_profile_pic_sql = "SELECT profile_pic_path FROM users WHERE id = ?";
                $stmt_old_pic = $conn->prepare($old_profile_pic_sql);
                if($stmt_old_pic){
                    $stmt_old_pic->bind_param("i", $teacher_id);
                    $stmt_old_pic->execute();
                    $old_pic_result = $stmt_old_pic->get_result();
                    if($old_pic_row = $old_pic_result->fetch_assoc()){
                        if(!empty($old_pic_row['profile_pic_path']) && file_exists($old_pic_row['profile_pic_path']) && strpos($old_pic_row['profile_pic_path'], 'placehold.co') === false){
                            unlink($old_pic_row['profile_pic_path']); // Delete the file
                        }
                    }
                    $stmt_old_pic->close();
                }

                // Update database with new picture path
                $sql_update_user_pic = "UPDATE users SET profile_pic_path = ? WHERE id = ?";
                $stmt_update_pic = $conn->prepare($sql_update_user_pic);
                if($stmt_update_pic){
                    $stmt_update_pic->bind_param("si", $upload_path, $teacher_id);
                    $stmt_update_pic->execute();
                    $stmt_update_pic->close();
                    $_SESSION['profile_update_success'] = "Profile picture updated successfully!";
                } else {
                     $errors[] = "Failed to update profile picture path in database.";
                }
            } else {
                $errors[] = "Failed to upload profile picture.";
            }
        }
    }


    if (empty($errors)) {
        $stmt_update = $conn->prepare($sql_update_user);
        if ($stmt_update) {
            $stmt_update->bind_param($types, ...$params);
            if ($stmt_update->execute()) {
                $_SESSION['profile_update_success'] = "Profile updated successfully!";
                // Update session username if it changed
                $_SESSION['username'] = $new_username;
            } else {
                $errors[] = "Failed to update profile: " . $stmt_update->error;
            }
            $stmt_update->close();
        } else {
            $errors[] = "Failed to prepare update statement.";
        }
    }

    if (!empty($errors)) {
        $_SESSION['profile_update_errors'] = $errors;
    }

    // Redirect to clear POST data and show messages
    header("Location: teacher_dashboard.php?page=profile");
    exit();
}

// Re-fetch current data after potential update for display
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
                 $current_profile_pic_path = 'https://placehold.co/120x120/BFDBFE/1E40AF?text=' . strtoupper(substr($current_username, 0, 1));
            }
        }
    }
    $stmt_current_profile->close();
}
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

                    <div class="mb-5">
                        <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">New Password (leave blank if not changing)</label>
                        <input type="password" id="new_password" name="new_password"
                               class="w-full bg-white border border-gray-300 text-gray-900 px-3 py-2.5 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                    </div>

                    <div class="mb-6">
                        <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               class="w-full bg-white border border-gray-300 text-gray-900 px-3 py-2.5 rounded-lg focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profileImageUpload = document.getElementById('profileImageUpload');
    const profileImagePreview = document.getElementById('profileImagePreview'); // Used for the preview within the edit form

    if (profileImageUpload && profileImagePreview) {
        profileImageUpload.addEventListener('change', function(event) {
            const file = event.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    profileImagePreview.src = e.target.result;
                    // Also update the main profile image if it's visible or for consistency
                    const mainProfileImage = document.getElementById('profileImage');
                    if(mainProfileImage) mainProfileImage.src = e.target.result;

                    // Update sidebar and topbar avatars live
                    const sidebarAvatar = document.getElementById('sidebarAvatar');
                    const topbarAvatar = document.querySelector('header img[alt="User Avatar"]');
                    if(sidebarAvatar) sidebarAvatar.src = e.target.result;
                    if(topbarAvatar) topbarAvatar.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }
        });
    }

    // The editProfileButton, cancelEditButton, profileView, profileEdit
    // logic is already handled in teacher_dashboard.php's main script.
    // Ensure that script is correctly executed and its elements are found.
});
</script>
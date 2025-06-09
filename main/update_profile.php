<?php
session_start();
include 'db_connection.php'; // Ensure this path is correct

// Redirect if not logged in or not a POST request from the form
if (!isset($_SESSION['user_id']) || $_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['update_profile_action'])) {
    header("Location: index.php");
    exit();
}

// Ensure CSRF token is valid
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $_SESSION['profile_update_errors'] = ["Security error: Invalid CSRF token. Please try again."];
    header("Location: teacher_dashboard.php?page=profile"); // Redirect to profile page
    exit();
}

$teacher_id = $_SESSION['user_id']; // Always use the session ID for the current user

$name = trim($_POST['name']);
$email = trim($_POST['email']);
$current_password_input = $_POST['current_password'] ?? ''; // New field for current password
$new_password = $_POST['new_password'] ?? '';
$confirm_new_password = $_POST['confirm_new_password'] ?? ''; // Renamed for clarity, ensure form sends this

$redirect_url = "teacher_dashboard.php?page=profile"; // Always redirect to profile page

$errors = [];
$success_messages = [];
$show_password_error_popup = false; // Flag for incorrect current password popup

// --- Input Validations ---
if (empty($name)) {
    $errors[] = "Username is required.";
}
if (empty($email)) {
    $errors[] = "Email is required.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

// Fetch current user data, especially the plaintext password and profile picture path
$sql_fetch_user = "SELECT password, profile_pic_path FROM users WHERE id = ?";
$stmt_fetch_user = $conn->prepare($sql_fetch_user);
$stored_plaintext_password = ''; // This will store the plaintext password from the DB
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
    $_SESSION['profile_update_errors'] = $errors;
    header("Location: " . $redirect_url);
    exit();
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

    // 2. Validate New Password (only if current password is correct or not provided)
    // Avoid redundant checks if current password is definitively wrong
    if ($show_password_error_popup && count($errors) == 1) { // Only the current password error
        // Don't add more password errors, let the popup handle it.
    } else {
        if (empty($new_password)) {
            // If current password was provided but new password is empty, it's an error
            if (!empty($current_password_input)) {
                $errors[] = "New password cannot be empty if you're attempting to change it.";
            }
        } elseif (strlen($new_password) < 6) { // Password length validation
            $errors[] = "New password must be at least 6 characters long.";
        } elseif ($new_password !== $confirm_new_password) {
            $errors[] = "New password and confirm new password do not match.";
        } else {
            // All new password validations passed, assign plaintext new password
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
        // Attempt to create directory with recursive option and appropriate permissions
        if (!mkdir($upload_dir, 0755, true)) {
            $errors[] = "Failed to create upload directory. Check server permissions.";
        }
    }

    if (empty($errors)) { // Proceed only if directory creation was successful or already exists
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

// If any errors occurred during validation or file upload, store them and redirect
if (!empty($errors)) {
    $_SESSION['profile_update_errors'] = $errors;
    $_SESSION['show_password_error_popup'] = $show_password_error_popup; // Pass popup flag
    // If a new pic was uploaded but then an error occurred, delete the temp file
    if ($profile_pic_path_to_save_in_db && file_exists($profile_pic_path_to_save_in_db)) {
        unlink($profile_pic_path_to_save_in_db);
    }
    header("Location: " . $redirect_url);
    exit();
}

// --- Build SQL Update Statement ---
$sql_parts = [];
$params = [];
$types = "";

$sql_parts[] = "username = ?";
$params[] = $name;
$types .= "s";

$sql_parts[] = "email = ?";
$params[] = $email;
$types .= "s";

if (!empty($update_password_sql_part) && $password_to_save) {
    $sql_parts[] = $update_password_sql_part;
    $params[] = $password_to_save; // ⚠️ BINDING PLAIN TEXT PASSWORD
    $types .= "s";
}

if (!empty($update_profile_pic_sql_part) && $profile_pic_path_to_save_in_db) {
    $sql_parts[] = $update_profile_pic_sql_part;
    $params[] = $profile_pic_path_to_save_in_db;
    $types .= "s";
}

// If no fields are actually changing, just report success (or no changes)
if (empty($sql_parts)) {
    $_SESSION['profile_update_success'] = "No changes detected for username, email, or password. Profile picture was also not changed.";
    header("Location: " . $redirect_url);
    exit();
}

$sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
$params[] = $teacher_id;
$types .= "i"; // Add integer type for the teacher_id

$stmt = $conn->prepare($sql);

if ($stmt) {
    // Dynamically bind parameters based on their types
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $success_messages[] = "Profile details (username/email/password) updated successfully!";

        // Delete old profile picture only if a new one was successfully saved
        if ($profile_pic_path_to_save_in_db && $old_profile_pic_path &&
            file_exists($old_profile_pic_path) &&
            $old_profile_pic_path !== $profile_pic_path_to_save_in_db && // Ensure it's not the same file
            strpos($old_profile_pic_path, 'placehold.co') === false // Don't delete placeholder images
        ) {
            @unlink($old_profile_pic_path); // Use @ to suppress errors if file is locked/permissions issue
        }

        // Update session username if it changed
        if (isset($_SESSION['username']) && $_SESSION['username'] !== $name) {
            $_SESSION['username'] = $name;
        }

        $_SESSION['profile_update_success'] = implode(" ", $success_messages);
        header("Location: " . $redirect_url);
        exit();
    } else {
        $errors[] = "Database error during update: " . $stmt->error;
        // If update failed, and a new pic was uploaded, delete it
        if ($profile_pic_path_to_save_in_db && file_exists($profile_pic_path_to_save_in_db)) {
            unlink($profile_pic_path_to_save_in_db);
        }
        $_SESSION['profile_update_errors'] = $errors;
        header("Location: " . $redirect_url);
        exit();
    }
    $stmt->close();
} else {
    $errors[] = "Database error preparing update statement: " . $conn->error;
    // If prepare failed, and a new pic was uploaded, delete it
    if ($profile_pic_path_to_save_in_db && file_exists($profile_pic_path_to_save_in_db)) {
        unlink($profile_pic_path_to_save_in_db);
    }
    $_SESSION['profile_update_errors'] = $errors;
    header("Location: " . $redirect_url);
    exit();
}

$conn->close();
?>
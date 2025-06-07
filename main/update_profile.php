<?php
session_start();
include 'db_connection.php'; // Ensure this path is correct

if (!isset($_SESSION['user_id']) || $_SERVER["REQUEST_METHOD"] !== "POST" || !isset($_POST['update_profile_action'])) {
    header("Location: index.php");
    exit();
}

$teacher_id = $_SESSION['user_id'];
if (!isset($_POST['teacher_id']) || $_POST['teacher_id'] != $_SESSION['user_id']) {
    $_SESSION['profile_update_errors'] = ["User ID mismatch. Action aborted."];
    header("Location: teacher_dashboard.php?page=dashboard&status=error_auth");
    exit();
}

$name = trim($_POST['name']);
$email = trim($_POST['email']);
$password = $_POST['password'];
$confirm_password = $_POST['confirm_password'];

$source_page = isset($_POST['source_page']) ? $_POST['source_page'] : 'dashboard';
$redirect_url = "teacher_dashboard.php?page=" . urlencode($source_page);

$errors = [];

// --- Input Validations ---
if (empty($name)) {
    $errors[] = "Name is required.";
}
if (empty($email)) {
    $errors[] = "Email is required.";
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = "Invalid email format.";
}

// --- Plain-text password (INSECURE) ---
$update_password_sql_part = "";
$password_param = null;
if (!empty($password)) {
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "New passwords do not match.";
    } else {
        $password_param = $password; // ⚠️ PLAIN TEXT
        $update_password_sql_part = "password = ?";
    }
}

// --- Profile Picture Upload Handling ---
$profile_pic_path_to_save_in_db = null;
$update_profile_pic_sql_part = "";
$old_profile_pic_path = null;

if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
    $sql_old_pic = "SELECT profile_pic_path FROM users WHERE id = ?";
    $stmt_old_pic = $conn->prepare($sql_old_pic);
    if ($stmt_old_pic) {
        $stmt_old_pic->bind_param("i", $teacher_id);
        $stmt_old_pic->execute();
        $result_old_pic = $stmt_old_pic->get_result();
        if ($row_old_pic = $result_old_pic->fetch_assoc()) {
            $old_profile_pic_path = $row_old_pic['profile_pic_path'];
        }
        $stmt_old_pic->close();
    }

    $upload_dir = "uploads/profile_pics/";
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            $errors[] = "Failed to create upload directory.";
        }
    }

    if (empty($errors)) {
        $tmp_name = $_FILES['profile_pic']['tmp_name'];
        $original_name = basename($_FILES['profile_pic']['name']);
        $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024;

        if (!in_array($file_extension, $allowed_extensions)) {
            $errors[] = "Invalid file type.";
        } elseif ($_FILES['profile_pic']['size'] > $max_file_size) {
            $errors[] = "File is too large.";
        } else {
            $new_filename = "user_" . $teacher_id . "_" . uniqid('', true) . "." . $file_extension;
            $destination_path = $upload_dir . $new_filename;

            if (move_uploaded_file($tmp_name, $destination_path)) {
                $profile_pic_path_to_save_in_db = $destination_path;
                $update_profile_pic_sql_part = "profile_pic_path = ?";
            } else {
                $errors[] = "Failed to upload file.";
            }
        }
    }
}

if (!empty($errors)) {
    $_SESSION['profile_update_errors'] = $errors;
    if ($profile_pic_path_to_save_in_db && file_exists($profile_pic_path_to_save_in_db)) {
        unlink($profile_pic_path_to_save_in_db);
    }
    header("Location: " . $redirect_url . "&status=error_validation");
    exit();
}

// --- Build SQL Update ---
$sql_parts = [];
$params = [];
$types = "";

$sql_parts[] = "username = ?";
$params[] = $name;
$types .= "s";

$sql_parts[] = "email = ?";
$params[] = $email;
$types .= "s";

if (!empty($update_password_sql_part) && $password_param) {
    $sql_parts[] = $update_password_sql_part;
    $params[] = $password_param;
    $types .= "s";
}

if (!empty($update_profile_pic_sql_part) && $profile_pic_path_to_save_in_db) {
    $sql_parts[] = $update_profile_pic_sql_part;
    $params[] = $profile_pic_path_to_save_in_db;
    $types .= "s";
}

if (empty($sql_parts)) {
    $_SESSION['profile_update_success'] = "No changes detected.";
    header("Location: " . $redirect_url . "&status=no_changes");
    exit();
}

$sql = "UPDATE users SET " . implode(", ", $sql_parts) . " WHERE id = ?";
$params[] = $teacher_id;
$types .= "i";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $_SESSION['profile_update_success'] = "Profile updated successfully!";

        if ($profile_pic_path_to_save_in_db && $old_profile_pic_path && file_exists($old_profile_pic_path)) {
            if ($old_profile_pic_path !== $profile_pic_path_to_save_in_db) {
                unlink($old_profile_pic_path);
            }
        }

        if (isset($_SESSION['username']) && $_SESSION['username'] !== $name) {
            $_SESSION['username'] = $name;
        }

        header("Location: " . $redirect_url . "&status=success_profile");
        exit();
    } else {
        $_SESSION['profile_update_errors'] = ["Database error: " . $stmt->error];
        if ($profile_pic_path_to_save_in_db && file_exists($profile_pic_path_to_save_in_db)) {
            unlink($profile_pic_path_to_save_in_db);
        }
        header("Location: " . $redirect_url . "&status=error_db");
        exit();
    }
    $stmt->close();
} else {
    $_SESSION['profile_update_errors'] = ["Database error: " . $conn->error];
    if ($profile_pic_path_to_save_in_db && file_exists($profile_pic_path_to_save_in_db)) {
        unlink($profile_pic_path_to_save_in_db);
    }
    header("Location: " . $redirect_url . "&status=error_prepare");
    exit();
}

$conn->close();
?>

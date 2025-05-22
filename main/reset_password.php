<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'db_connection.php'; // Ensure this file correctly establishes $conn

$message = null;
$error = null;
$show_form = true;

// Step 1: Get and verify token
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (empty($token)) { // Changed to empty for a slightly broader check
    die('Invalid or missing token.');
}

$stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? AND expires_at > NOW()");
if (!$stmt) {
    die("Database query preparation error: " . $conn->error);
}
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $stmt->close();
    die("Invalid or expired token. Please request a new password reset link.");
}

$row = $result->fetch_assoc();
$email = $row['email'];
$stmt->close();

// Step 2: Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Username field and logic removed
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($password) || empty($confirm_password)) { // Changed to empty and updated error message
        $error = "New Password and Confirm New Password are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        // Username check (Step 3) removed as it's no longer needed.
        // The $email from the token is sufficient to identify the user.

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Step 4: Update password using email
        $stmt_update = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        if (!$stmt_update) {
            $error = "Database query preparation error for update: " . $conn->error;
        } else {
            $stmt_update->bind_param("ss", $hashed_password, $email);

            if ($stmt_update->execute()) {
                // Step 5: Delete token
                $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE token=?");
                if ($stmt_delete) {
                    $stmt_delete->bind_param("s", $token);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                } // Silently continue if token deletion fails, password reset is more critical

                $show_form = false;
                $message = "✅ Your password has been successfully reset. <a href='index.php'>Click here to login</a>.";
            } else {
                $error = "Something went wrong while updating the password. Please try again. Error: " . $stmt_update->error;
            }
            $stmt_update->close();
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .container {
            background-color: #fff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="container" style="max-width:440px; margin-top:50px;">
    <h3 class="text-center mb-4">Reset Your Password</h3>
    <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if ($show_form): ?>
    <form method="post" action="" autocomplete="off"> 
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        
        <!-- Username field removed -->
        
        <div class="form-group">
            <label for="password">New Password</label>
            <input id="password" name="password" type="password" class="form-control" required>
            <small class="form-text text-muted">At least 8 characters</small>
        </div>
        <div class="form-group">
            <label for="confirm_password">Confirm New Password</label>
            <input id="confirm_password" name="confirm_password" type="password" class="form-control" required>
        </div>
        <button class="btn btn-success btn-block" type="submit">Reset Password</button>
    </form>
    <?php endif; ?>
</div>

<script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
</body>
</html>
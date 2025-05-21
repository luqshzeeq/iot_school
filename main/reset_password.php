<?php
session_start();
include 'db_connection.php';

$message = null;
$error = null;
$show_form = true;

// 1. Validate token
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (!$token) { die('Invalid link.'); }

// Check if token exists and not expired
$stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? AND expires_at > NOW()");
$stmt->bind_param("s", $token);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows === 0) {
    die("Invalid or expired token.");
}
$row = $res->fetch_assoc();
$email = $row['email'];

// 2. If POST, validate input and reset password
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (!$username || !$password || !$confirm_password) {
        $error = "All fields are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        // Check if username and email match
        $stmt2 = $conn->prepare("SELECT id FROM users WHERE username=? AND email=?");
        $stmt2->bind_param("ss", $username, $email);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        if ($res2->num_rows !== 1) {
            $error = "Username and email do not match.";
        } else {
            $user = $res2->fetch_assoc();
            $user_id = $user['id'];
            // Update password (hashed)
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt3 = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt3->bind_param("si", $hashed, $user_id);
            if ($stmt3->execute()) {
                // Delete reset token
                $stmt4 = $conn->prepare("DELETE FROM password_resets WHERE token=?");
                $stmt4->bind_param("s", $token);
                $stmt4->execute();
                $show_form = false;
                $message = "Password reset successful! <a href='index.php'>Login here</a>.";
            } else {
                $error = "Error updating password.";
            }
        }
    }
}
?>

<!-- Password Reset Form -->
<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body>
    <div class="container" style="max-width:400px; margin-top:50px;">
        <h3>Reset Your Password</h3>
        <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
        <?php if ($show_form): ?>
        <form method="post">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
            <div class="form-group">
                <label>Username</label>
                <input name="username" class="form-control" required>
            </div>
            <div class="form-group">
                <label>New Password</label>
                <input name="password" type="password" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Confirm New Password</label>
                <input name="confirm_password" type="password" class="form-control" required>
            </div>
            <button class="btn btn-success btn-block" type="submit">Reset Password</button>
        </form>
        <?php endif; ?>
    </div>
</body>
</html>

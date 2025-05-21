<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');  // set to your timezone
include 'db_connection.php'; // Your database connection


require __DIR__ . '/../vendor/autoload.php'; // Load PHPMailer via Composer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare("SELECT username FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save token to DB
            $stmt2 = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt2->bind_param("sss", $email, $token, $expires_at);
            $stmt2->execute();

            $reset_link = "http://localhost/iot_school/main/reset_password.php?token=$token";

            // Send email
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'luqman.haziq3601@gmail.com';          // ✅ your Gmail
                $mail->Password   = 'rywd zahf wlvu lxln';             // ✅ Gmail App Password only
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('luqman.haziq3601@gmail.com', 'Language Monitoring System');
                $mail->addAddress($email, $user['username']);

                $mail->isHTML(true);
                $mail->Subject = "Password Reset Request";
                $mail->Body = "Hello " . htmlspecialchars($user['username']) . ",<br><br>
                    Click the link below to reset your password:<br>
                    <a href='$reset_link'>$reset_link</a><br><br>
                    This link will expire in 1 hour.<br>
                    If you did not request a reset, just ignore this email.";

                $mail->send();
                $message = "A reset link has been sent to your email. Please check your inbox.";
            } catch (Exception $e) {
                $error = "Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $error = "If your email exists in our system, you will receive a reset link.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Forgot Password | Language Monitoring System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
</head>
<body>
<div class="container" style="max-width:440px; margin-top:60px;">
    <h3>Forgot Password?</h3>
    <?php if ($message): ?><div class="alert alert-success"><?= $message ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= $error ?></div><?php endif; ?>
    <?php if (!$message): ?>
    <form method="post" autocomplete="off">
        <div class="form-group">
            <label for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control" placeholder="Enter your email" required>
        </div>
        <button type="submit" class="btn btn-dark btn-block">Send Reset Link</button>
    </form>
    <?php endif; ?>
    <a href="index.php" class="btn btn-link mt-2">Back to Login</a>
</div>
</body>
</html>

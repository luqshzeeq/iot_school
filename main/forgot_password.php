<?php
session_start();
include 'db_connection.php'; // Your DB connection file
require __DIR__ . '/../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;

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
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

            // Save token to DB
            $stmt2 = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt2->bind_param("sss", $email, $token, $expires_at);
            $stmt2->execute();

            // Send email with reset link
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'your.smtp.host';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'your_email@example.com';
                $mail->Password   = 'your_password';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;
                $mail->setFrom('no-reply@yourdomain.com', 'Language Monitoring System');
                $mail->addAddress($email, $user['username']);

                $reset_link = "https://yourdomain.com/reset_password.php?token=$token";
                $mail->isHTML(true);
                $mail->Subject = "Password Reset Request";
                $mail->Body    = "Hello " . htmlspecialchars($user['username']) . ",<br><br>
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
    <style>
        body {
            background: #fff;
            font-family: 'Fira Mono', 'Consolas', monospace;
        }
        .reset-box {
            max-width: 440px;
            margin: 60px auto;
            background: #fff;
            border: 1px solid #b4efc5;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            padding: 40px 38px 30px 38px;
            text-align: center;
        }
        .reset-box .bolt {
            font-size: 2.7rem;
            color: #ffd600;
            margin-bottom: 10px;
            font-weight: bold;
            line-height: 1;
        }
        .reset-box h1 {
            font-size: 2rem;
            font-family: 'Fira Mono', 'Consolas', monospace;
            margin-bottom: 15px;
            margin-top: 0;
            font-weight: bold;
        }
        .reset-box label {
            display: block;
            text-align: left;
            margin-bottom: 7px;
            font-weight: 500;
            font-size: 1rem;
            letter-spacing: 0.01em;
        }
        .input-box {
            margin-bottom: 20px;
            position: relative;
            max-width: 100%;
        }
        .input-box input {
            width: 100%;
            box-sizing: border-box;
            padding: 13px 16px 13px 44px;
            font-size: 1.09rem;
            line-height: 1.5; /* Added for baseline alignment */
            border: 1px solid #e0e0e0;
            border-radius: 7px;
            outline: none;
            background: #fafcff;
            transition: border 0.2s;
        }
        .input-box input:focus {
            border: 1.5px solid #ffd600;
        }
        .input-box .icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(6px); /* Adjusted for baseline alignment */
            color: #9ca3af;
            font-size: 1.07rem;
            pointer-events: none;
        }
        .reset-btn {
            width: 100%;
            padding: 13px 0;
            background: #232323;
            color: #fff;
            font-weight: bold;
            font-size: 1.08rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            margin-bottom: 13px;
            transition: background 0.2s;
        }
        .reset-btn:hover {
            background: #393939;
        }
        .back-link {
            color: #2367f2;
            font-size: 1rem;
            display: inline-block;
            margin-top: 7px;
            text-decoration: none;
            transition: text-decoration 0.15s;
        }
        .back-link:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 17px;
            font-size: 0.97rem;
        }
        .alert-success {
            background: #dbffe8;
            color: #227849;
            border: 1px solid #b4efc5;
        }
        .alert-danger {
            background: #fff5e6;
            color: #d47c2c;
            border: 1px solid #ffd899;
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
</head>
<body>
    <div class="reset-box">
        <div class="bolt"><i class="fas fa-bolt"></i></div>
        <h1>Forgot Password?</h1>
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <?php if (!$message): ?>
        <form method="post" autocomplete="off">
            <div class="input-box">
                <label for="email">Email Address</label>
                <span class="icon"><i class="fas fa-envelope"></i></span>
                <input type="email" id="email" name="email" placeholder="Enter your email" required>
            </div>
            <button type="submit" class="reset-btn">Reset Password</button>
        </form>
        <?php endif; ?>
        <a href="index.php" class="back-link">Back to Login</a>
    </div>
</body>
</html>

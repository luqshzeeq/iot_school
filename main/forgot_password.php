<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'db_connection.php'; // Ensure this file exists and connects to your database

require __DIR__ . '/../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = null;
$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Database connection check (optional, but good practice)
        if ($conn->connect_error) {
            $error = "Database connection failed: " . $conn->connect_error;
        } else {
            $stmt = $conn->prepare("SELECT username FROM users WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $stmt2 = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
                    if ($stmt2) {
                        $stmt2->bind_param("sss", $email, $token, $expires_at);
                        $stmt2->execute();

                        // Ensure your domain and path are correct
                        $reset_link = "http://localhost/iot_school/main/reset_password.php?token=$token";

                        $mail = new PHPMailer(true);
                        try {
                            //Server settings
                            $mail->isSMTP();
                            $mail->Host       = 'smtp.gmail.com';
                            $mail->SMTPAuth   = true;
                            $mail->Username   = 'luqman.haziq3601@gmail.com'; // Replace with your Gmail address
                            $mail->Password   = 'wcfj xdyi fbnx msrx'; // Replace with your Gmail App Password
                            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                            $mail->Port       = 587;

                            //Recipients
                            $mail->setFrom('luqman.haziq3601@gmail.com', 'Language Monitoring System');
                            $mail->addAddress($email, htmlspecialchars($user['username']));

                            //Content
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
                        $stmt2->close();
                    } else {
                        $error = "Failed to prepare statement for password_resets.";
                    }
                } else {
                    // Generic message to prevent email enumeration
                    $error = "If your email exists in our system, you will receive a reset link.";
                }
                $stmt->close();
            } else {
                 $error = "Failed to prepare statement for users.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password | Language Monitoring System</title>
    <link rel="stylesheet" href="https://unpkg.com/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        html, body {
            height: 100%; /* Make html and body take full height */
            margin: 0; /* Remove default margin */
        }
        body {
            display: flex; /* Use flexbox to allow vertical centering */
            flex-direction: column; /* Stack children vertically */
        }
        .full-height-section {
            flex-grow: 1; /* Allow section to grow and take available space */
            display: flex; /* Use flexbox for centering content within the section */
            align-items: center; /* Vertically center content */
            justify-content: center; /* Horizontally center content */
            width: 100%;
        }
        /* Optional: if you want the card to not be overly stretched on very large screens */
        .card-max-width {
            max-width: 1200px; /* Adjust as needed */
        }

        /* Ensure images within the card scale correctly */
        .object-fit-cover {
            object-fit: cover;
        }
    </style>
</head>
<body>
<section class="bg-light p-3 p-md-4 p-xl-5 full-height-section">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-12 col-xxl-11 card-max-width"> 
        <div class="card border-light-subtle shadow-sm">
          <div class="row g-0">
            <div class="col-12 col-md-6">
              <img class="img-fluid rounded-start w-100 h-100 object-fit-cover" loading="lazy" src="img/forgotbg.png" alt="Decorative image for password reset">
            </div>
            <div class="col-12 col-md-6 d-flex align-items-center justify-content-center"> 
              <div class="col-12 col-lg-11 col-xl-10">
                <div class="card-body p-3 p-md-4 p-xl-5">
                  <div class="row">
                    <div class="col-12">
                      <div class="mb-5">
                        <div class="text-center mb-4">
                          <a href="#!">
                            <img src="img/unimap-logo.png" alt="UniMAP Logo" width="175" height="auto"> 
                          </a>
                        </div>
                        <h2 class="h4 text-center">Password Reset</h2>
                        <h3 class="fs-6 fw-normal text-secondary text-alignleft m-0">Provide the email address associated with your account to recover your password.</h3>
                      </div>
                    </div>
                  </div>

                  <?php if ($message): ?>
                    <div class="alert alert-success text-center" role="alert"><?= htmlspecialchars($message) ?></div>
                  <?php endif; ?>
                  <?php if ($error): ?>
                    <div class="alert alert-danger text-center" role="alert"><?= htmlspecialchars($error) ?></div>
                  <?php endif; ?>

                  <?php if (!$message): /* Only show form if no success message has been displayed */ ?>
                  <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                    <div class="row gy-3 overflow-hidden">
                      <div class="col-12">
                        <div class="form-floating mb-3">
                          <input type="email" class="form-control" name="email" id="email" placeholder="name@example.com" required>
                          <label for="email" class="form-label">Email</label>
                        </div>
                      </div>
                      <div class="col-12">
                        <div class="d-grid">
                          <button class="btn btn-dark btn-lg" type="submit">Reset Password</button>
                        </div>
                      </div>
                    </div>
                  </form>
                  <?php endif; ?>

                  <div class="row">
                    <div class="col-12">
                      <div class="d-flex gap-2 gap-md-4 flex-column flex-md-row justify-content-md-center mt-5">
                        <a href="index.php" class="link-secondary text-decoration-none">Login</a>
                        <a href="register.php" class="link-secondary text-decoration-none">Register</a>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<script src="https://unpkg.com/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
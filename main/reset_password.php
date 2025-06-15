<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'db_connection.php';

$message = null;
$error = null;
$show_form = true;
$success = false;
$user_id_for_log = null;

// Get token from GET or POST
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (empty($token)) {
    die('Invalid or missing token.');
}

// Verify token
$stmt_token_verify = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ? AND expires_at > NOW()");
if (!$stmt_token_verify) {
    die("Database query preparation error: " . $conn->error);
}
$stmt_token_verify->bind_param("s", $token);
$stmt_token_verify->execute();
$result_token_verify = $stmt_token_verify->get_result();

if ($result_token_verify->num_rows === 0) {
    $stmt_token_verify->close();
    die("Invalid or expired token. Please request a new password reset link.");
}
$row_token = $result_token_verify->fetch_assoc();
$email = $row_token['email'];
$stmt_token_verify->close();

// Get user_id for logging
$stmt_get_user = $conn->prepare("SELECT id FROM users WHERE email = ?");
if ($stmt_get_user) {
    $stmt_get_user->bind_param("s", $email);
    $stmt_get_user->execute();
    $result_user = $stmt_get_user->get_result();
    if ($result_user->num_rows === 1) {
        $user_row = $result_user->fetch_assoc();
        $user_id_for_log = $user_row['id'];
    }
    $stmt_get_user->close();
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $posted_token = $_POST['token'] ?? '';
    if(empty($posted_token) || $posted_token !== $token) {
        die("Token mismatch or missing from submission.");
    }
    
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = "New Password and Confirm New Password are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        $stmt_update = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        if (!$stmt_update) {
            $error = "Database query preparation error for update: " . $conn->error;
        } else {
            $stmt_update->bind_param("ss", $password, $email);
            if ($stmt_update->execute()) {
                // Log the reset
                if ($user_id_for_log) {
                    $reset_method_log = 'user_initiated_via_link';
                    $ip_address_log = $_SERVER['REMOTE_ADDR'] ?? null;
                    $stmt_log = $conn->prepare("INSERT INTO password_reset_logs (user_id, reset_method, admin_id_who_reset, ip_address, reset_at) VALUES (?, ?, NULL, ?, NOW())");
                    if ($stmt_log) {
                        $stmt_log->bind_param("iss", $user_id_for_log, $reset_method_log, $ip_address_log);
                        $stmt_log->execute();
                        $stmt_log->close();
                    }
                }
                
                // Delete token
                $stmt_delete = $conn->prepare("DELETE FROM password_resets WHERE token=?");
                if ($stmt_delete) {
                    $stmt_delete->bind_param("s", $token);
                    $stmt_delete->execute();
                    $stmt_delete->close();
                }
                
                $show_form = false;
                $success = true;
            } else {
                $error = "Something went wrong while updating the password. Please try again.";
            }
            $stmt_update->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - UMMAP</title>
    
    <!-- Bootstrap CSS -->
    <link href="../../assets/plugins/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <style>
        html, body {
            height: 100%;
            margin: 0;
            padding: 0;
        }
        .bg-image {
            background: url('../../assets/images/brand/resetbg2.png') no-repeat center center;
            background-size: cover;
        }
        .form-container {
            max-width: 500px;
            width: 100%;
        }
        .logo {
            max-height: 80px;
            width: auto;
        }
    </style>
    
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container-fluid h-100 p-0">
        <div class="row g-0 h-100">
            <!-- Background Image Column (hidden on small screens) -->
            <div class="col-lg-6 d-none d-lg-block bg-image h-100"></div>
            
            <!-- Form Column -->
            <div class="col-lg-6 d-flex align-items-center justify-content-center p-4">
                <div class="form-container">
                    <div class="text-center mb-4">
                        <img src="../../assets/images/brand/unimaplogo2.png" class="logo mb-4" alt="UMMAP Logo">
                        <h2 class="mb-3">Reset Password</h2>
                        <?php if ($show_form): ?>
                            <p class="text-muted">Enter your new password below</p>
                        <?php endif; ?>
                    </div>

                    <?php if ($error && $show_form): ?>
                        <div class="alert alert-danger text-center"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <?php if ($show_form): ?>
                        <form class="needs-validation" method="post" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>?token=<?= htmlspecialchars($token) ?>" novalidate>
                            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                            
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control form-control-lg" id="password" name="password" required>
                                <div class="form-text">At least 8 characters</div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="confirm_password" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control form-control-lg" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100 py-2" id="submitBtn">
                                Reset Password
                            </button>
                        </form>
                    <?php endif; ?>

                    <div class="text-center mt-4">
                        <p class="mb-0">Remembered your password? <a href="index.php">Login</a></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery, Bootstrap JS -->
    <script src="../../assets/plugins/jquery/jquery.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form submission handling
        const form = document.querySelector('form.needs-validation');
        if (form) {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('#submitBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processing...';
                }
            });
        }
    });

    <?php if ($success): ?>
    Swal.fire({
        icon: 'success',
        title: 'Password Reset!',
        text: 'Your password has been successfully reset.',
        confirmButtonText: 'Go to Login',
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "index.php";
        }
    });
    <?php elseif ($error && !$show_form): ?>
    Swal.fire({
        icon: 'error',
        title: 'Error Occurred',
        text: '<?= addslashes(htmlspecialchars($error)) ?>',
        confirmButtonText: 'Try Again',
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = "forgot_password.php";
        }
    });
    <?php endif; ?>
    </script>
</body>
</html>
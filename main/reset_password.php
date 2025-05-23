<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'db_connection.php'; // Your DB connection file with $conn

$message = null;
$error = null;
$show_form = true;
$success = false;

// Get token from GET or POST
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (empty($token)) {
    die('Invalid or missing token.');
}

// Verify token is valid and not expired
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

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($password) || empty($confirm_password)) {
        $error = "New Password and Confirm New Password are required.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } else {
        // Store password as plain text (NOT RECOMMENDED)
        $stmt_update = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        if (!$stmt_update) {
            $error = "Database query preparation error for update: " . $conn->error;
        } else {
            $stmt_update->bind_param("ss", $password, $email);
            if ($stmt_update->execute()) {
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
<html lang="en" dir="ltr">
<head>

    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
    <meta content="DayOne - Multipurpose Admin & Dashboard Template" name="description">
    <meta content="Spruko Technologies Private Limited" name="author">
    <meta name="keywords" content="admin dashboard, admin panel template, html admin template, dashboard html template, bootstrap 4 dashboard, template admin bootstrap 4, simple admin panel template, simple dashboard html template, bootstrap admin panel, task dashboard, job dashboard, bootstrap admin panel, dashboards html, panel in html, bootstrap 4 dashboard"/>

    <title>Reset Password</title>

    <link rel="icon" href="../../assets/images/brand/unimapicon.png" type="image/x-icon"/>

    <link href="../../assets/plugins/bootstrap/css/bootstrap.css" rel="stylesheet" />
    <link href="../../assets/css/style.css" rel="stylesheet" />
    <link href="../../assets/css/dark.css" rel="stylesheet" />
    <link href="../../assets/css/skin-modes.css" rel="stylesheet" />
    <link href="../../assets/css/animated.css" rel="stylesheet" />
    <link href="../../assets/css/icons.css" rel="stylesheet" />
    <link href="../../assets/plugins/select2/select2.min.css" rel="stylesheet" />
    <link href="../../assets/plugins/p-scrollbar/p-scrollbar.css" rel="stylesheet" />

    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet" />

</head>

<body>

    <div class="page relative error-page3">
        <div class="row no-gutters">
            <div class="col-md-6 h-100vh">
                <div class="cover-image h-100vh" style="background-image: url('/img/resetbg2.png'); background-size: cover; background-position: center;">
                    <div class="container">
                        <div class="customlogin-imgcontent">
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 bg-white h-100vh">
                <div class="container">
                    <div class="customlogin-content">
                        <div class="pt-4 pb-2">
                            <a class="header-brand" href="index.php">
                                <img src="../../assets/images/brand/unimaplogo.png" class="header-brand-img custom-logo" alt="Unimaplogo" style="max-width: 150px; height: auto;">
                            </a>
                        </div>
                        <div class="p-4 pt-6">
                            <h1 class="mb-2">Reset Password</h1>
                            <p class="text-muted">Enter your new password below</p>
                        </div>

                        <?php if ($error): ?>
                            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                        <?php endif; ?>

                        <?php if ($show_form): ?>
                            <form class="card-body pt-3" id="reset" name="reset" method="post" action="" autocomplete="off">
                                <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
                                <div class="form-group">
                                    <label class="form-label" for="password">New Password</label>
                                    <input class="form-control" id="password" name="password" type="password" placeholder="New password" required>
                                    <small class="form-text text-muted">At least 8 characters</small>
                                </div>
                                <div class="form-group">
                                    <label class="form-label" for="confirm_password">Confirm Password</label>
                                    <input class="form-control" id="confirm_password" name="confirm_password" type="password" placeholder="Confirm new password" required>
                                </div>
                                <div class="submit">
                                    <button class="btn btn-primary btn-block" type="submit">Reset Password</button>
                                </div>
                            </form>
                        <?php endif; ?>

                        <div class="text-center mt-4">
                            <p class="text-dark mb-0">Remembered your password? <a class="text-primary ml-1" href="index.php">Login</a></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="../../assets/plugins/jquery/jquery.min.js"></script>
    <script src="../../assets/plugins/bootstrap/popper.min.js"></script>
    <script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
    <script src="../../assets/plugins/select2/select2.full.min.js"></script>
    <script src="../../assets/plugins/p-scrollbar/p-scrollbar.js"></script>
    <script src="../../assets/js/custom.js"></script>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <?php if ($success): ?>
    <script>
        Swal.fire({
            icon: 'success',
            title: 'Password Reset!',
            text: 'Your password has been successfully reset.',
            confirmButtonText: 'Go to Login',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = "index.php";
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>

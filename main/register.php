<?php
include 'db_connection.php';
session_start();

$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm  = mysqli_real_escape_string($conn, $_POST['confirm']);

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Username or email already registered.";
        } else {
            // Store password as plaintext (matching index.php login)
            $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, 'teacher')");
            $stmt->bind_param("sss", $username, $email, $password);

            if ($stmt->execute()) {
                header("Location: index.php?register=success");
                exit();
            } else {
                $error = "Registration failed. Please try again.";
            }
            $stmt->close();
        }
        $check->close();
    }

    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <!-- Meta data -->
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0" />
    <meta content="DayOne - Multipurpose Admin & Dashboard Template" name="description" />
    <meta content="Spruko Technologies Private Limited" name="author" />
    <meta name="keywords" content="admin dashboard, admin panel template, html admin template, dashboard html template, bootstrap 4 dashboard, template admin bootstrap 4, simple admin panel template, simple dashboard html template, bootstrap admin panel, task dashboard, job dashboard, bootstrap admin panel, dashboards html, panel in html, bootstrap 4 dashboard" />

    <title>Register | Language Monitoring System</title>

    <!-- Favicon -->
    <link rel="icon" href="../../assets/images/brand/unimapicon.png" type="unimapicon" />

    <!-- Bootstrap CSS -->
    <link href="../../assets/plugins/bootstrap/css/bootstrap.css" rel="stylesheet" />

    <!-- Bootstrap Icons CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />

    <!-- Style CSS -->
    <link href="../../assets/css/style.css" rel="stylesheet" />
    <link href="../../assets/css/dark.css" rel="stylesheet" />
    <link href="../../assets/css/skin-modes.css" rel="stylesheet" />

    <!-- Animate CSS -->
    <link href="../../assets/css/animated.css" rel="stylesheet" />

    <!-- Icons CSS -->
    <link href="../../assets/css/icons.css" rel="stylesheet" />

    <!-- Select2 CSS -->
    <link href="../../assets/plugins/select2/select2.min.css" rel="stylesheet" />

    <!-- P-scroll bar CSS -->
    <link href="../../assets/plugins/p-scrollbar/p-scrollbar.css" rel="stylesheet" />

    <style>
        html, body {
            width: 100vw;
            height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #f1f5fb;
        }
        .page { display: flex; flex-direction: column; height: 100%; width: 100%; }
        .row.no-gutters { display: flex; flex: 1 1 auto; margin: 0; height: 100%; width: 100%; }
        .col-xl-6 { display: flex; flex-direction: column; flex: 1 1 50%; height: 100%; width: 50%; }
        .col-xl-6.bg-white { background-color: #fff; justify-content: center; }
        .customlogin-content { flex: 1; display: flex; flex-direction: column; justify-content: center; }
        .custom-logo { max-width: 250px; height: auto; display: block; margin: 0 auto 1rem auto; }
        .left-image-container {
            flex: 1; position: relative; display: flex; justify-content: center; align-items: center;
            background-color: #6c7383; padding: 0; overflow: hidden; flex-direction: column;
            text-align: center; color: white; padding: 2rem;
        }
        .left-image-container::before {
            content: ""; position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to bottom right, rgba(30, 39, 50, 0.7), rgba(44, 62, 80, 0.7));
            z-index: 1; pointer-events: none;
        }
        .left-image-container img {
            position: absolute; top: 0; left: 0; right: 0; bottom: 0;
            width: 100%; height: 100%; object-fit: cover; z-index: 0; opacity: 0.2;
            animation: zoomFadeIn 2s ease forwards;
        }
        @keyframes zoomFadeIn { to { opacity: 1; transform: scale(1); } }
        .welcome-message {
            position: relative; z-index: 2; max-width: 400px; margin: auto;
            animation: fadeSlideScaleIn 2.5s ease forwards; opacity: 0;
        }
        @keyframes fadeSlideScaleIn {
            0% { opacity: 0; transform: translateY(30px) scale(0.95); }
            100% { opacity: 1; transform: translateY(0) scale(1); }
        }
        .input-group-text {
            cursor: pointer; display: flex; align-items: center; padding: 0 0.75rem;
            background-color: #f8f9fa; border-left: 1px solid #ced4da; height: 38px;
        }
        .input-group-text i { font-size: 1.25rem; line-height: 1; color: #6c757d; }
    </style>
</head>
<body>
<div class="page relative error-page3">
    <div class="row no-gutters">
        <!-- Left side with image and welcome message -->
        <div class="col-xl-6 h-100vh">
            <div class="left-image-container">
                <img src="../../img/loginbg.jpg" alt="Register Image" />
                <div class="welcome-message">
                    <h1>Welcome to Smart Language Learning Hub</h1>
                    <p>IoT-Based Smart Language Monitoring System for Primary Schools</p>
                </div>
            </div>
        </div>
        <!-- Right side registration form -->
        <div class="col-xl-6 bg-white h-100vh">
            <div class="container">
                <div class="customlogin-content">
                    <div class="pt-4 pb-2">
                        <a class="header-brand" href="index.php">
                            <img src="../../assets/images/brand/unimaplogo2.png" class="header-brand-img custom-logo" alt="unimap logo" />
                        </a>
                    </div>
                    <div class="p-4 pt-6">
                        <h1 class="mb-2">Register</h1>
                        <p class="text-muted">Create your teacher account</p>
                    </div>
                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger mx-4">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>
                    <form method="POST" class="card-body pt-3" id="register" name="register" novalidate>
                        <div class="form-group">
                            <label class="form-label" for="username">Username</label>
                            <input id="username" type="text" class="form-control" name="username" required autofocus />
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="email">Email Address</label>
                            <input id="email" type="email" class="form-control" name="email" required />
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group">
                                <input id="password" type="password" class="form-control" name="password" minlength="8" required />
                                <span class="input-group-text" id="togglePassword" style="cursor: pointer;">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="confirm">Confirm Password</label>
                            <div class="input-group">
                                <input id="confirm" type="password" class="form-control" name="confirm" minlength="8" required />
                                <span class="input-group-text" id="toggleConfirm" style="cursor: pointer;">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="form-group m-0">
                            <button type="submit" class="btn btn-primary btn-block">Register</button>
                        </div>
                        <div class="text-center mt-3">
                            Already have an account? <a href="index.php">Login here</a>
                        </div>
                    </form>
                    <div class="card-body border-top-0 pb-6 pt-2">
                        <!-- Optional footer or social icons -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Jquery js-->
<script src="../../assets/plugins/jquery/jquery.min.js"></script>
<!-- Bootstrap4 js-->
<script src="../../assets/plugins/bootstrap/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<!-- Select2 js -->
<script src="../../assets/plugins/select2/select2.full.min.js"></script>
<!-- P-scroll js-->
<script src="../../assets/plugins/p-scrollbar/p-scrollbar.js"></script>
<!-- Custom js-->
<script src="../../assets/js/custom.js"></script>
<script>
    // Show/hide password for password field
    const togglePassword = document.querySelector('#togglePassword i');
    const passwordInput = document.querySelector('#password');
    togglePassword.addEventListener('click', function () {
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });

    // Show/hide password for confirm field
    const toggleConfirm = document.querySelector('#toggleConfirm i');
    const confirmInput = document.querySelector('#confirm');
    toggleConfirm.addEventListener('click', function () {
        const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
        confirmInput.setAttribute('type', type);
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });
</script>
</body>
</html>

<?php
session_start();

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'teacher') {
        header("Location: teacher_dashboard.php");
        exit();
    } elseif ($_SESSION['role'] == 'admin') {
        header("Location: admin_dashboard.php");
        exit();
    }
}

include 'db_connection.php';
$error = null;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['user_identifier']) || empty($_POST['password'])) {
        $error = "Please enter both username/email and password.";
    } else {
        $user_identifier = trim($_POST['user_identifier']);
        $password_attempt = $_POST['password'];

        if (strlen($password_attempt) < 8) {
            $error = "Password must be at least 8 characters long.";
        } else {
            $stmt = $conn->prepare("SELECT id, username, email, password, role FROM users WHERE username = ? OR email = ?");
            if ($stmt) {
                $stmt->bind_param("ss", $user_identifier, $user_identifier);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows == 1) {
                    $user = $result->fetch_assoc();

                    // Plaintext password check (temporary)
                    if ($password_attempt === $user['password']) {
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        session_regenerate_id(true);

                        if (isset($_POST['remember']) && $_POST['remember'] == '1') {
                            setcookie('rememberme', session_id(), time() + (86400 * 30), "/");
                        }

                        if ($user['role'] == 'teacher') {
                            header("Location: teacher_dashboard.php");
                            exit();
                        } elseif ($user['role'] == 'admin') {
                            header("Location: admin_dashboard.php");
                            exit();
                        }
                    } else {
                        $error = "Invalid username/email or password.";
                    }
                } else {
                    $error = "Invalid username/email or password.";
                }
                $stmt->close();
            } else {
                $error = "Login system error. Please try again later.";
            }
        }
        $conn->close();
    }
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

    <title>Login | Language Monitoring System</title>

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
        /* Full page and flex layout */
        html, body {
            width: 100vw;
            height: 100vh;
            margin: 0;
            padding: 0;
            overflow: hidden;
            background-color: #f1f5fb;
        }

        .page {
            display: flex;
            flex-direction: column;
            height: 100%;
            width: 100%;
        }

        .row.no-gutters {
            display: flex;
            flex: 1 1 auto;
            margin: 0;
            height: 100%;
            width: 100%;
        }

        .col-xl-6 {
            display: flex;
            flex-direction: column;
            flex: 1 1 50%;
            height: 100%;
            width: 50%;
        }

        .col-xl-6.bg-white {
            background-color: #fff;
            justify-content: center;
        }

        .customlogin-content {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .custom-logo {
            max-width: 250px;
            height: auto;
            display: block;
            margin: 0 auto 1rem auto;
        }

        /* Center and fit image on left panel */
        .left-image-container {
            flex: 1;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #6c7383;
            padding: 0;
            overflow: hidden;
            flex-direction: column;
            text-align: center;
            color: white;
            padding: 2rem;
        }

        .left-image-container::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: linear-gradient(to bottom right, rgba(30, 39, 50, 0.7), rgba(44, 62, 80, 0.7));
            z-index: 1;
            pointer-events: none;
        }

        .left-image-container img {
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            z-index: 0;
            opacity: 0.2;
            animation: zoomFadeIn 2s ease forwards;
        }

        @keyframes zoomFadeIn {
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Animated welcome message */
        .welcome-message {
            position: relative;
            z-index: 2;
            max-width: 400px;
            margin: auto;
            animation: fadeSlideScaleIn 2.5s ease forwards;
            opacity: 0;
        }

        @keyframes fadeSlideScaleIn {
            0% {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        /* Animation for error alert */
        @keyframes fadeInOut {
          0% {opacity: 0; transform: translateY(-20px);}
          10% {opacity: 1; transform: translateY(0);}
          90% {opacity: 1; transform: translateY(0);}
          100% {opacity: 0; transform: translateY(-20px);}
        }

        .alert-animated {
          animation: fadeInOut 4s ease forwards;
        }

        /* Style for show/hide password */
        .input-group {
            position: relative;
        }

        .input-group-text {
            cursor: pointer;
            display: flex;
            align-items: center;
            padding: 0 0.75rem;
            background-color: #f8f9fa;
            border-left: 1px solid #ced4da;
            height: 38px;
        }

        .input-group-text i {
            font-size: 1.25rem;
            line-height: 1;
            color: #6c757d;
        }
    </style>
</head>
<body>

<div class="page relative error-page3">
    <div class="row no-gutters">
        <!-- Left side with image and welcome message -->
        <div class="col-xl-6 h-100vh">
            <div class="left-image-container">
                <img src="../../img/loginbg2.jpg" alt="Login Image" />
                <div class="welcome-message">
                    <h1>Welcome to Smart Language Learning System</h1>
                    <p>Smart Language Monitoring System for Primary Schools</p>
                </div>
            </div>
        </div>

        <!-- Right side login form -->
        <div class="col-xl-6 bg-white h-100vh">
            <div class="container">
                <div class="customlogin-content">
                    <div class="pt-4 pb-2">
                        <a class="header-brand" href="index.php">
                            <img src="../../assets/images/brand/unimaplogo2.png" class="header-brand-img custom-logo" alt="unimap logo" />
                        </a>
                    </div>

                    <div class="p-4 pt-6">
                        <h1 class="mb-2">Login</h1>
                        <p class="text-muted">Sign In to your account</p>
                    </div>

                    <?php if (isset($error)): ?>
                        <div class="alert alert-danger mx-4 <?php echo ($error === 'Password must be at least 8 characters long.') ? 'alert-animated' : ''; ?>">
                            <?php echo htmlspecialchars($error); ?>
                        </div>
                    <?php endif; ?>

                    <form method="POST" class="card-body pt-3" id="login" name="login" novalidate>
                        <div class="form-group">
                            <label class="form-label" for="user_identifier">Username or Email</label>
                            <input
                                class="form-control"
                                id="user_identifier"
                                name="user_identifier"
                                placeholder="Email or Username"
                                type="text"
                                value="<?php echo isset($_POST['user_identifier']) ? htmlspecialchars($_POST['user_identifier']) : ''; ?>"
                                required
                                autofocus
                            />
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="password">Password</label>
                            <div class="input-group">
                                <input
                                    class="form-control"
                                    id="password"
                                    name="password"
                                    placeholder="Password"
                                    type="password"
                                    required
                                    minlength="8"
                                />
                                <span class="input-group-text" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="custom-control custom-checkbox">
                                <input
                                    type="checkbox"
                                    class="custom-control-input"
                                    name="remember"
                                    id="remember"
                                    value="1"
                                />
                                <span class="custom-control-label">Remember me</span>
                            </label>
                        </div>
                        <div class="submit">
                            <button type="submit" class="btn btn-primary btn-block">Login</button>
                        </div>
                        <div class="text-center mt-3">
                            <p class="mb-2"><a href="forgot_password.php">Forgot Password</a></p>
                            <p class="text-dark mb-0">
                                Don't have account?<a class="text-primary ml-1" href="register.php"> Register</a>
                            </p>
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
    // Show/hide password toggle
    const togglePassword = document.querySelector('#togglePassword i');
    const password = document.querySelector('#password');

    togglePassword.addEventListener('click', function () {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);

        // Toggle icon class
        this.classList.toggle('bi-eye');
        this.classList.toggle('bi-eye-slash');
    });

    // Auto hide animated alert after 4 seconds
    document.addEventListener('DOMContentLoaded', () => {
      const alert = document.querySelector('.alert-animated');
      if(alert) {
        setTimeout(() => {
          alert.style.display = 'none';
        }, 4000);
      }
    });
</script>

</body>
</html>
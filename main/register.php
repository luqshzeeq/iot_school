<?php
include 'db_connection.php';
session_start();

$error = null;
$registration_success = false; // Flag to indicate successful registration
$success_message = "Registration Completed Successfully"; // Default success message

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
        // Use prepared statements for checking existence
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        if ($check) {
            $check->bind_param("ss", $username, $email);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                $error = "Username or email already registered.";
            } else {
                // IMPORTANT: In a real application, you should hash passwords (e.g., using password_hash())
                // and verify them with password_verify(). Plaintext password storage is highly insecure.
                $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, created_at) VALUES (?, ?, ?, 'teacher', NOW())"); // Added created_at
                if ($stmt) {
                    $stmt->bind_param("sss", $username, $email, $password);

                    if ($stmt->execute()) {
                        $registration_success = true; // Set success flag
                        // No redirect here, let JS handle the modal
                    } else {
                        $error = "Registration failed. Please try again: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $error = "Error preparing registration statement: " . $conn->error;
                }
            }
            $check->close();
        } else {
            $error = "Error preparing user check statement: " . $conn->error;
        }
    }
    $conn->close(); // Close connection after all DB ops for POST request
}
?>

<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0" />
    <meta content="DayOne - Multipurpose Admin & Dashboard Template" name="description" />
    <meta content="Spruko Technologies Private Limited" name="author" />
    <meta name="keywords" content="admin dashboard, admin panel template, html admin template, dashboard html template, bootstrap 4 dashboard, template admin bootstrap 4, simple admin panel template, simple dashboard html template, bootstrap admin panel, task dashboard, job dashboard, bootstrap admin panel, dashboards html, panel in html, bootstrap 4 dashboard" />

    <title>Register | Language Monitoring System</title>

    <link rel="icon" href="../../assets/images/brand/unimapicon.png" type="unimapicon" />

    <link href="../../assets/plugins/bootstrap/css/bootstrap.css" rel="stylesheet" />

    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Ensure Font Awesome 6 is loaded if you use far fa-check-circle -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />

    <link href="../../assets/css/style.css" rel="stylesheet" />
    <link href="../../assets/css/dark.css" rel="stylesheet" />
    <link href="../../assets/css/skin-modes.css" rel="stylesheet" />

    <link href="../../assets/css/animated.css" rel="stylesheet" />

    <link href="../../assets/css/icons.css" rel="stylesheet" />

    <link href="../../assets/plugins/select2/select2.min.css" rel="stylesheet" />

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

        /* NEW: Styles for the eye icon outside the input group (Option 1) */
        .password-toggle-icon {
            cursor: pointer;
            position: absolute; /* Position it absolutely within the relative container */
            right: 10px; /* Adjust as needed for spacing from the right */
            top: 50%; /* Center vertically */
            transform: translateY(-50%); /* Adjust for perfect vertical centering */
            display: flex;
            align-items: center;
            color: #6c757d; /* Icon color */
            z-index: 10; /* Ensure it's above the input */
            padding: 0 0.75rem; /* Padding for visual space around the icon */
            height: 38px; /* Match input height for alignment */
        }
        .password-toggle-icon i {
            font-size: 1.25rem;
            line-height: 1;
        }
        
        /* Styles for the custom success modal (from other admin pages, adapted) */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease, visibility 0.3s ease;
        }
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .modal-box {
            background-color: white;
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            text-align: center;
            max-width: 400px;
            width: 90%;
            transform: translateY(-20px);
            opacity: 0;
            transition: transform 0.3s ease, opacity 0.3s ease;
        }
        .modal-overlay.active .modal-box {
            transform: translateY(0);
            opacity: 1;
        }
        .modal-box .icon-wrapper {
            background-color: #d1fae5; /* Light green background */
            color: #10b981; /* Darker green icon */
            border-radius: 9999px; /* Full rounded */
            width: 56px; /* h-14 */
            height: 56px; /* w-14 */
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem; /* mx-auto mb-6 */
        }
        .modal-box .icon-wrapper i {
            font-size: 2rem; /* text-4xl */
        }
        /* Specific button style for the success modal */
        .success-modal-button {
            background-color: #6a5acd; /* Purple from the image */
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: background-color 0.2s;
            border: none;
            cursor: pointer;
        }
        .success-modal-button:hover {
            background-color: #5d4ea5; /* Darker purple on hover */
        }
    </style>
</head>
<body>
<div class="page relative error-page3">
    <div class="row no-gutters">
        <div class="col-xl-6 h-100vh">
            <div class="left-image-container">
                <img src="../../img/loginbg.jpg" alt="Register Image" />
                <div class="welcome-message">
                    <h1>Welcome to Smart Language Learning System</h1>
                    <p>IoT-Based Smart Language Monitoring System for Primary Schools</p>
                </div>
            </div>
        </div>
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
                            <div style="position: relative;">
                                <input id="password" type="password" class="form-control" name="password" minlength="8" required />
                                <span class="password-toggle-icon" id="togglePassword">
                                    <i class="bi bi-eye"></i>
                                </span>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="confirm">Confirm Password</label>
                            <div style="position: relative;">
                                <input id="confirm" type="password" class="form-control" name="confirm" minlength="8" required />
                                <span class="password-toggle-icon" id="toggleConfirm">
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
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Registration Success Modal HTML -->
<div id="registrationSuccessModal" class="modal-overlay">
    <div class="modal-box">
        <div class="icon-wrapper">
            <i class="fas fa-check-circle"></i>
        </div>
        <h3 class="text-xl font-bold text-gray-900 mb-2">Registered Successfully</h3>
        <p class="text-sm text-gray-500 mb-6" id="registrationModalMessage">
            Welcome to Smart Language Learning System
        </p>
        <button id="backToLoginButton" class="w-full success-modal-button">
            Back to Login
        </button>
    </div>
</div>

<script src="../../assets/plugins/jquery/jquery.min.js"></script>
<script src="../../assets/plugins/bootstrap/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/select2/select2.full.min.js"></script>
<script src="../../assets/plugins/p-scrollbar/p-scrollbar.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
    // Show/hide password for password field
    const togglePassword = document.querySelector('#togglePassword i');
    const passwordInput = document.querySelector('#password');
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });
    }

    // Show/hide password for confirm field
    const toggleConfirm = document.querySelector('#toggleConfirm i');
    const confirmInput = document.querySelector('#confirm');
    if (toggleConfirm && confirmInput) {
        toggleConfirm.addEventListener('click', function () {
            const type = confirmInput.getAttribute('type') === 'password' ? 'text' : 'password';
            confirmInput.setAttribute('type', type);
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });
    }

    // --- Registration Success Modal Logic ---
    const registrationSuccessModal = document.getElementById('registrationSuccessModal');
    const backToLoginButton = document.getElementById('backToLoginButton');
    const registrationModalMessage = document.getElementById('registrationModalMessage');

    // PHP variable to determine if registration was successful
    const registrationSuccess = <?php echo json_encode($registration_success); ?>;
    

    if (registrationSuccess) {
        // Set the custom message (or use default if dynamic from PHP is not needed)
        registrationModalMessage.textContent = registrationCustomMessage; 
        registrationSuccessModal.classList.add('active'); // Show the modal
    }

    // Handle "Back to Login" button click
    if (backToLoginButton) {
        backToLoginButton.addEventListener('click', () => {
            registrationSuccessModal.classList.remove('active'); // Hide modal
            window.location.href = 'index.php'; // Redirect to login page
        });
    }

    // Close modal if overlay is clicked
    if (registrationSuccessModal) {
        registrationSuccessModal.addEventListener('click', (e) => {
            if (e.target === registrationSuccessModal) { // Check if the click was directly on the overlay
                registrationSuccessModal.classList.remove('active');
                window.location.href = 'index.php'; // Redirect to login page
            }
        });
    }
</script>
</body>
</html>

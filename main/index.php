<?php
session_start();  // Start the session for user login

// If the user is already logged in, redirect to the correct dashboard
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] == 'teacher') {
        header("Location: teacher_dashboard.php"); // Redirect to teacher dashboard
        exit();
    } elseif ($_SESSION['role'] == 'admin') {
        header("Location: admin_dashboard.php"); // Redirect to admin dashboard
        exit();
    }
}

include 'db_connection.php';  // Include database connection

$error = null;  // Initialize error variable

// Handle form submission for login
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (empty($_POST['username']) || empty($_POST['password'])) {
        // If username or password is empty, show an error
        $error = "Please enter both username and password.";
    } else {
        $username = trim($_POST['username']);  // Remove extra spaces
        $password_attempt = $_POST['password'];  // The password entered by the user

        // Query to check if user exists in the database
        $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
        if ($stmt) {
            $stmt->bind_param("s", $username);
            $stmt->execute();
            $result = $stmt->get_result();

            // Check if user is found
            if ($result->num_rows == 1) {
                $user = $result->fetch_assoc();

                // Verify the password (use password_verify for hashed passwords, plain-text will fail)
                if ($password_attempt == $user['password']) {  // Change this line if using hashed passwords
                    // Password is correct, set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];  // Store user role

                    // Regenerate session ID for security (prevents session fixation)
                    session_regenerate_id(true);

                    // Debugging: Check session variables
                    // var_dump($_SESSION);  // Remove this after debugging

                    // Redirect based on the user's role
                    if ($user['role'] == 'teacher') {
                        header("Location: teacher_dashboard.php");  // Redirect to teacher dashboard
                        exit();
                    } elseif ($user['role'] == 'admin') {
                        header("Location: admin_dashboard.php");  // Redirect to admin dashboard
                        exit();
                    }
                } else {
                    // Incorrect password
                    $error = "Invalid username or password.";
                }
            } else {
                // Username not found
                $error = "Invalid username or password."; // Keep this error generic
            }
            $stmt->close();  // Close the prepared statement
        } else {
            // Error preparing the SQL statement
            $error = "Login system error. Please try again later.";
        }
        $conn->close();  // Close the connection
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="author" content="Group 30 - UniMAP">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login | Language Monitoring System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" type="text/css" href="css/my-login.css">
</head>
<body class="my-login-page">
    <section class="h-100">
        <div class="container h-100">
            <div class="row justify-content-md-center align-items-center h-100">
                <div class="card-wrapper">

                    <!-- Centered Logo and Title -->
                    <div class="text-center mb-2">
                        <img src="img/unimap-logo.png" alt="UniMAP Logo" class="img-fluid logo-img">
                        <h4 class="project-title">Language Monitoring System</h4>
                        <p class="subtitle">Developed by Group 30, UniMAP</p>
                    </div>

                    <!-- Login Card -->
                    <div class="card fat">
                        <div class="card-body">
                            <h4 class="card-title">Login</h4>

                            <!-- Display error message if there's an error -->
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger">
                                    <?php echo htmlspecialchars($error); // Sanitize error output ?>
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="my-login-validation" novalidate="">
                                <div class="form-group">
                                    <label for="username">Username</label>
                                    <input id="username" type="text" class="form-control" name="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required autofocus>
                                    <div class="invalid-feedback">
                                        Username is required
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="password">Password</label>
                                    <input id="password" type="password" class="form-control" name="password" required data-eye>
                                    <div class="invalid-feedback">
                                        Password is required
                                    </div>
                                </div>

                                <div class="form-group">
                                    <a href="forgot_password.php" class="forgot-password-link">Forgot Password?</a>
                                </div>

                                <div class="form-group">
                                    <div class="custom-checkbox custom-control">
                                        <input type="checkbox" name="remember" id="remember" class="custom-control-input">
                                        <label for="remember" class="custom-control-label">Remember Me</label>
                                    </div>
                                </div>

                                <div class="form-group m-0">
                                    <button type="submit" class="btn btn-primary btn-block">Login</button>
                                </div>

                                <div class="mt-4 text-center">
                                    Don't have an account? <a href="register.php">Sign up as Teacher</a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="footer mt-3">
                        &copy; <?php echo date("Y"); ?> IoT-Based Language Monitoring System | Developed by Group 30, UniMAP
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.7/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <script src="js/my-login.js"></script>
</body>
</html>

<?php
include 'db_connection.php';
session_start();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $email    = mysqli_real_escape_string($conn, $_POST['email']);
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $confirm  = mysqli_real_escape_string($conn, $_POST['confirm']);

    if ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $error = "Username or email already registered.";
        } else {
            // No password hashing used here
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="author" content="Group 30 - UniMAP">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Registration | Language Monitoring System</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <link rel="stylesheet" href="css/my-login.css">
</head>
<body class="my-login-page">
<section class="h-100">
    <div class="container h-100">
        <div class="row justify-content-md-center align-items-center h-100">
            <div class="card-wrapper">
                <div class="text-center mb-2">
                    <img src="img/teacher-logo2.png" alt="Teacher Logo" class="img-fluid logo-img">
                    <h4 class="project-title">Register as Teacher</h4>
                    <p class="subtitle">IoT-Based Language Monitoring System</p>
                </div>

                <div class="card fat">
                    <div class="card-body">
                        <h4 class="card-title">Create an Account</h4>

                        <!-- 
                        Display error message if $error is set 
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>
                        -->

                        <form method="POST" novalidate>
                            <div class="form-group">
                                <label for="username">Username</label>
                                <input id="username" type="text" class="form-control" name="username" required autofocus>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input id="email" type="email" class="form-control" name="email" required>
                            </div>

                            <div class="form-group">
                                <label for="password">Password</label>
                                <input id="password" type="password" class="form-control" name="password" required>
                            </div>

                            <div class="form-group">
                                <label for="confirm">Confirm Password</label>
                                <input id="confirm" type="password" class="form-control" name="confirm" required>
                            </div>

                            <div class="form-group m-0">
                                <button type="submit" class="btn btn-primary btn-block">Register</button>
                            </div>

                            <div class="mt-4 text-center">
                                Already have an account? <a href="index.php">Login here</a>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="footer mt-3 text-center">
                    &copy; 2025 Language Monitoring System | Group 30, UniMAP
                </div>
            </div>
        </div>
    </div>
</section>
</body>
</html>




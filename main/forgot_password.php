<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');
include 'db_connection.php'; // Ensure this file exists and connects to your database

// Ensure the path to vendor/autoload.php is correct for your project structure
require __DIR__ . '/../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = null;
$error = null;
$email_sent_for_js = null; // To pass email to JS for success popup
$is_resend_ajax = isset($_POST['resend']) && $_POST['resend'] == "1";

// Function to send reset email (remains the same as previous version)
function send_reset_email($conn, $email) {
    // It's good practice to check if the email exists first.
    $stmt = $conn->prepare("SELECT username FROM users WHERE email = ?");
    if (!$stmt) {
        // Log detailed error: $conn->error
        return "Error preparing statement to check user: Database error.";
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32)); // Good secure token generation
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expiry

        $stmt_delete_old = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        if($stmt_delete_old){
            $stmt_delete_old->bind_param("s", $email);
            $stmt_delete_old->execute();
            $stmt_delete_old->close();
        }


        $stmt2 = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
        if (!$stmt2) {
            $stmt->close();
            return "Error preparing statement for password reset: Database error.";
        }
        $stmt2->bind_param("sss", $email, $token, $expires_at);
        
        if ($stmt2->execute()) {
            $baseUrl = "http://localhost"; 
            $reset_link = $baseUrl . "/reset_password.php?token=" . $token;

            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; 
                $mail->SMTPAuth   = true;
                $mail->Username   = 'luqman.haziq3601@gmail.com'; 
                $mail->Password   = 'wcfj xdyi fbnx msrx';       
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                $mail->setFrom('luqman.haziq3601@gmail.com', 'Language Monitoring System'); 
                $mail->addAddress($email, htmlspecialchars($user['username'])); 

                $mail->isHTML(true);
                $mail->Subject = "Password Reset Request";
                $mail->Body    = "Hello " . htmlspecialchars($user['username']) . ",<br><br>"
                               . "You requested a password reset for your account on Language Monitoring System.<br>"
                               . "Click the link below to reset your password:<br>"
                               . "<a href='" . $reset_link . "'>" . $reset_link . "</a><br><br>"
                               . "This link will expire in 1 hour.<br><br>"
                               . "If you did not request this password reset, please ignore this email. Your password will remain unchanged.<br><br>"
                               . "Thank you,<br>The Language Monitoring System Team";
                $mail->AltBody = "Hello " . htmlspecialchars($user['username']) . ",\n\n"
                               . "You requested a password reset for your account on Language Monitoring System.\n"
                               . "Copy and paste the following link into your browser to reset your password:\n"
                               . $reset_link . "\n\n"
                               . "This link will expire in 1 hour.\n\n"
                               . "If you did not request this password reset, please ignore this email. Your password will remain unchanged.\n\n"
                               . "Thank you,\nThe Language Monitoring System Team";

                $mail->send();
                $stmt2->close();
                $stmt->close();
                return true;
            } catch (Exception $e) {
                $stmt2->close();
                $stmt->close();
                return "Message could not be sent. Mailer Error. Please try again later."; 
            }
        } else {
            $stmt2->close();
            $stmt->close();
            return "Failed to store password reset request. Please try again.";
        }
    } else {
        $stmt->close();
        return "If your email address exists in our system, you will receive a password reset link shortly. Please check your inbox (and spam folder).";
    }
}

// Handle POST request (remains the same as previous version)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else if ($conn->connect_error) {
        $error = "Database connection failed. Please try again later.";
    } else {
        $send_result = send_reset_email($conn, $email);

        if ($send_result === true) {
            $message = "A reset link has been sent to your email. Please check your inbox (and spam folder).";
            $email_sent_for_js = $email; 
            if ($is_resend_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'email' => $email, 'msg' => $message]);
                exit;
            }
        } else {
            $error = $send_result;
            if ($is_resend_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'msg' => $error]);
                exit;
            }
        }
    }
    if ($is_resend_ajax && $error) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'msg' => $error]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name='viewport' content='width=device-width, initial-scale=1.0, user-scalable=0'>
    <title>Forgot Password | Language Monitoring System</title>
    <link rel="icon" href="../../assets/images/brand/unimapicon.png" type="image/x-icon"/>
    <link href="../../assets/plugins/bootstrap/css/bootstrap.css" rel="stylesheet" />
    <link href="../../assets/css/style.css" rel="stylesheet" />
    <link href="../../assets/css/dark.css" rel="stylesheet" />
    <link href="../../assets/css/skin-modes.css" rel="stylesheet" />
    <link href="../../assets/css/animated.css" rel="stylesheet" />
    <link href="../../assets/css/icons.css" rel="stylesheet" />
    <link href="../../assets/plugins/select2/select2.min.css" rel="stylesheet" />
    <link href="../../assets/plugins/p-scrollbar/p-scrollbar.css" rel="stylesheet" />
    <style>
        .login-bg1 {
            background: url('../../assets/images/brand/forgotpassbg.png') no-repeat center center;
            background-size: cover;
            min-height: 100vh;
            position: relative;
            display: flex; 
            align-items: center; /* Vertically center .page-single */
        }
        .login-bg1::before { /* Overlay, if needed */
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(34,34,59,0.05); /* Slightly reduced opacity */
            z-index: 1;
        }
        .page-single { 
            position: relative;
            z-index: 2;
            width: 100%; 
            padding-left: 10vw;  /* <<< KEY: Offset from left screen edge */
            padding-right: 2vw; /* Some space on the right */
            box-sizing: border-box;
        }
        /* Container and Row are standard Bootstrap */
        
        /* Card styling */
        .card.custom-card-style { /* Using a more specific class for this layout's card */
            padding: 2.5rem 2rem !important; 
            border-radius: 18px;
            box-shadow: 0 6px 24px rgba(0,0,0,0.15); /* Slightly enhanced shadow */
            font-size: 1.1rem; 
        }
        .header-brand-img.custom-logo {
            max-width: 180px; /* Adjusted logo size */
            height: auto;
            margin-bottom: 1rem; 
        }

        /* Column that wraps the card - adjust its width */
        .form-column-wrapper {
            /* Default to Bootstrap's column behavior, but we'll specify widths */
        }

        @media (min-width: 1200px) { /* XL screens */
            .form-column-wrapper {
                flex: 0 0 30%;    /* Adjust percentage as needed */
                max-width: 30%;   /* e.g., for a narrower card */
                /* max-width: 420px; /* Or a fixed max width */
            }
            .page-single { padding-left: 12vw; }
        }
        @media (min-width: 992px) and (max-width: 1199px) { /* LG screens */
            .form-column-wrapper {
                flex: 0 0 35%; 
                max-width: 35%;
                 /* max-width: 400px; */
            }
             .page-single { padding-left: 10vw; }
        }
        @media (min-width: 768px) and (max-width: 991px) { /* MD screens */
            .form-column-wrapper {
                flex: 0 0 50%; /* Card takes more width */
                max-width: 50%;
                 /* max-width: 450px; */
            }
            .page-single { padding-left: 8vw; }
        }
        @media (max-width: 767px) { /* SM screens and smaller */
            .page-single { 
                padding-left: 5vw; 
                padding-right: 5vw;
            }
            .form-column-wrapper { /* Form column takes full width of padded area */
                flex: 0 0 100%; 
                max-width: 100%;
            }
            .card.custom-card-style {
                padding: 2rem 1.5rem !important; 
            }
            .header-brand-img.custom-logo { max-width: 160px; }
            .h3 { font-size: 1.5rem; } /* Adjust heading on small screens */
        }

        /* Success Popup (styles remain largely the same) */
        .success-popup {
            position: fixed; top: 50%; left: 50%;
            transform: translate(-50%, -50%); min-width: 330px; max-width: 90%; 
            background: #fff; border-radius: 20px; box-shadow: 0 8px 32px rgba(0,0,0,0.2);
            z-index: 2000; display: flex; flex-direction: column; align-items: center;
            padding: 0; border-top: 7px solid #4CAF50; /* Green accent for success */
            animation: popIn 0.5s cubic-bezier(.68,-0.55,.27,1.55); text-align: center;
        }
        @keyframes popIn {
            0% { transform: translate(-50%, -50%) scale(0.8); opacity: 0; }
            100% { transform: translate(-50%, -50%) scale(1); opacity: 1; }
        }
        .success-popup-inner { padding: 30px 20px 25px 20px; width: 100%; }
        .success-popup-icon { margin-bottom: 15px; }
        .success-popup-icon svg { fill: #4CAF50; } /* Green icon */
        .success-popup-title { font-size: 1.7rem; font-weight: 600; color: #333; margin-bottom: 10px; }
        .success-popup-message { color: #555; font-size: 1rem; line-height: 1.5; margin-bottom: 5px;}
        @media (max-width: 500px) {
            .success-popup { min-width: 270px; } 
            .success-popup-inner { padding: 20px 15px 15px 15px; }
            .success-popup-title { font-size: 1.4rem; }
            .success-popup-message { font-size: 0.9rem; }
        }

        .alert-message-area { margin-top: 15px; margin-bottom:15px; width: 100%;}
        .alert-danger { margin-bottom: 15px; font-size: 0.9rem; }
        .form-label { font-size: 0.9rem; }
        .form-control { font-size: 0.95rem; }
        .btn-primary { font-size: 0.95rem; padding: 0.65rem 1rem; }
        .small, small { font-size: 0.85rem; }
    </style>
</head>
<body>
<div class="page login-bg1">
    <div id="success-popup" class="success-popup" style="display: none;">
        <div class="success-popup-inner">
            <div class="success-popup-icon">
                <svg xmlns="http://www.w3.org/2000/svg" width="50" height="50" viewBox="0 0 24 24"><path d="M0 0h24v24H0z" fill="none"/><path d="M9 16.17L4.83 12l-1.42 1.41L9 18.99 21 7l-1.41-1.41z"/></svg>
            </div>
            <div class="success-popup-title">EMAIL SENT!</div>
            <div class="success-popup-message">
                A reset link has been sent to<br>
                <span id="success-popup-email" style="font-weight:bold; color: #333;"></span>.<br>
                Please check your inbox (and spam folder).
            </div>
        </div>
    </div>

    <div class="page-single">
        <div class="container-fluid"> <div class="row justify-content-start"> <div class="col-xl-4 col-lg-5 col-md-7 col-sm-10 col-12 form-column-wrapper"> 
                    <div class="card custom-card-style"> <div class="text-center mb-3"> 
                            <a class="header-brand" href="index.php">
                                <img src="../../assets/images/brand/unimaplogo2.png" class="header-brand-img custom-logo" alt="UniMAP Logo">
                            </a>
                        </div>
                        <div class="text-center"> 
                            <h1 class="mb-2 h3">Forgot Password</h1> 
                            <p class="text-muted small">Enter the email address registered on your account.</p> 
                        </div>

                        <div class="alert-message-area">
                            <?php if ($error && !$is_resend_ajax): ?>
                                <div class="alert alert-danger text-center" role="alert" id="php-error-message"><?= htmlspecialchars($error) ?></div>
                            <?php endif; ?>
                            <div class="alert alert-danger text-center" role="alert" id="ajax-error-message" style="display:none;"></div>
                        </div>

                        <?php if (!$message || $error): ?>
                        <form class="card-body pt-2 pb-0" id="forgot" name="forgot" action="<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" autocomplete="off">
                            <div class="form-group">
                                <label class="form-label">E-Mail</label>
                                <input class="form-control form-control-sm" name="email" id="email-input" placeholder="Enter your email" type="email" required value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>">
                            </div>
                            <div class="submit mt-3"> 
                                <button class="btn btn-primary btn-block" type="submit" id="submit-btn">Send Reset Link</button>
                            </div>
                            <div class="text-center mt-3">
                                <p class="text-dark mb-0 small">Remembered your password? <a class="text-primary ml-1" href="index.php">Login</a></p>
                            </div>
                        </form>
                        <?php endif; ?>

                        <?php if ($message && !$error): ?>
                        <div class="text-center mt-3" id="resend-area" style="display: block;"> 
                             <p class="text-dark mb-0 small"> 
                                Didn’t receive the email?
                                <button class="btn btn-link btn-sm p-0" id="resend-btn" type="button">Resend Email</button>
                                <span id="resend-spinner" style="display:none;">
                                    <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                                    Sending...
                                </span>
                            </p>
                            <input type="hidden" id="resent-email-holder" value="<?= htmlspecialchars($email_sent_for_js ?? '') ?>">
                        </div>
                        <?php endif; ?>
                    </div></div></div></div></div></div><script src="../../assets/plugins/jquery/jquery.min.js"></script>
<script src="../../assets/plugins/bootstrap/popper.min.js"></script>
<script src="../../assets/plugins/bootstrap/js/bootstrap.min.js"></script>
<script src="../../assets/plugins/select2/select2.full.min.js"></script>
<script src="../../assets/plugins/p-scrollbar/p-scrollbar.js"></script>
<script src="../../assets/js/custom.js"></script>
<script>
// Utility to manage error messages
const phpErrorDiv = document.getElementById('php-error-message');
const ajaxErrorDiv = document.getElementById('ajax-error-message');

function clearMessages() {
    if (phpErrorDiv) phpErrorDiv.style.display = 'none';
    if (ajaxErrorDiv) ajaxErrorDiv.style.display = 'none';
    const successPopup = document.getElementById('success-popup');
    if(successPopup) successPopup.style.display = 'none';
}

function showAjaxError(message) {
    clearMessages();
    if (ajaxErrorDiv) {
        ajaxErrorDiv.textContent = message;
        ajaxErrorDiv.style.display = 'block';
    }
}

function showSuccessPopup(email) {
    clearMessages(); 
    const popup = document.getElementById('success-popup');
    const emailSpan = document.getElementById('success-popup-email');
    if (emailSpan) emailSpan.textContent = email;
    
    if (popup) {
        popup.style.display = 'flex';
        
        const form = document.getElementById('forgot');
        const resendArea = document.getElementById('resend-area');
        const resentEmailHolder = document.getElementById('resent-email-holder');

        if(form) form.style.display = 'none'; 
        if(resendArea) {
            resendArea.style.display = 'block'; 
            if(resentEmailHolder && email) resentEmailHolder.value = email; 
        }

        const timerId = setTimeout(() => { if(popup.style.display === 'flex') popup.style.display = 'none'; }, 7000);
        popup.onclick = function() { 
            popup.style.display = 'none'; 
            clearTimeout(timerId); 
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const initialMessage = <?= json_encode($message) ?>;
    const initialError = <?= json_encode($error) ?>;
    const initialEmailSent = <?= json_encode($email_sent_for_js) ?>;
    const form = document.getElementById('forgot');
    const resendArea = document.getElementById('resend-area');

    if (initialMessage && initialEmailSent && !initialError) {
        showSuccessPopup(initialEmailSent);
        if(form) form.style.display = 'none';
        if(resendArea) resendArea.style.display = 'block';
    } else {
        if(form) form.style.display = 'block';
        if(resendArea) resendArea.style.display = 'none';
    }
    
    const resendBtn = document.getElementById('resend-btn');
    const resendSpinner = document.getElementById('resend-spinner');
    const resentEmailInput = document.getElementById('resent-email-holder'); 

    if (resendBtn && resentEmailInput) {
        resendBtn.addEventListener('click', function() {
            const emailToResend = resentEmailInput.value;
            if (!emailToResend) {
                showAjaxError("Email address not found for resending. Please refresh and try submitting the form again.");
                return;
            }
            clearMessages(); 
            if(resendSpinner) resendSpinner.style.display = 'inline-block'; 
            resendBtn.disabled = true;
            resendBtn.style.opacity = '0.5';

            fetch('<?= htmlspecialchars($_SERVER["PHP_SELF"]); ?>', { 
                method: 'POST',
                headers: { 
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest' 
                },
                body: 'email=' + encodeURIComponent(emailToResend) + '&resend=1'
            })
            .then(response => {
                if (!response.ok) { throw new Error('Network response was not ok: ' + response.statusText); }
                return response.json();
            })
            .then(data => {
                if (data.success && data.email) { showSuccessPopup(data.email);  } 
                else if (data.msg) { showAjaxError(data.msg); } 
                else { showAjaxError('An unexpected error occurred while resending. Please try again.');}
            })
            .catch(error => {
                console.error('Resend Fetch Error:', error);
                showAjaxError('Failed to resend email. Please check your connection or try again later.');
            })
            .finally(() => {
                 if(resendSpinner) resendSpinner.style.display = 'none'; 
                 resendBtn.disabled = false;
                 resendBtn.style.opacity = '1';
            });
        });
    }

    if(form) {
        form.addEventListener('submit', function() {
            const submitBtn = document.getElementById('submit-btn');
            if(submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Sending...';
            }
            clearMessages();
        });
    }
});
</script>
</body>
</html>

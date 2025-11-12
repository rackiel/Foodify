<?php
session_start();
include 'config/db.php';
include 'phpmailer_setting.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$message_type = '';

// Handle forgot password form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Check if email exists in database
    $stmt = $conn->prepare("SELECT user_id, full_name, email FROM user_accounts WHERE email = ? AND is_verified = 1");
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Generate reset token
        $reset_token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
        
        // Delete any existing reset tokens for this user
        $delete_stmt = $conn->prepare("DELETE FROM password_reset_tokens WHERE user_id = ?");
        $delete_stmt->bind_param('i', $user['user_id']);
        $delete_stmt->execute();
        $delete_stmt->close();
        
        // Insert new reset token
        $insert_stmt = $conn->prepare("INSERT INTO password_reset_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
        $insert_stmt->bind_param('iss', $user['user_id'], $reset_token, $expires_at);
        
        if ($insert_stmt->execute()) {
            // Send reset email
            $reset_link = "http://localhost/foodify/reset-password.php?token=" . $reset_token;
            $subject = "Reset Your Foodify Password";
            $message_body = '<div style="font-family: Arial, sans-serif; background: #f9f9f9; padding: 32px; border-radius: 12px; max-width: 480px; margin: 0 auto;">
                <div style="text-align:center; margin-bottom: 24px;">
                    <img src="http://localhost/foodify/uploads/images/foodify_logo.png" alt="Foodify Logo" style="height: 80px;">
                </div>
                <h2 style="color: #43e97b; text-align:center;">Password Reset Request</h2>
                <p style="font-size: 1.1em; color: #333; text-align:center;">Hello ' . htmlspecialchars($user['full_name']) . ',</p>
                <p style="color: #333; text-align:center;">We received a request to reset your password. Click the button below to reset it:</p>
                <div style="text-align:center; margin: 32px 0;">
                    <a href="' . $reset_link . '" style="background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%); color: #fff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 1.1em; display: inline-block;">Reset My Password</a>
                </div>
                <p style="color: #888; font-size: 0.95em; text-align:center;">This link will expire in 1 hour for security reasons.</p>
                <p style="color: #888; font-size: 0.95em; text-align:center;">If you did not request this password reset, please ignore this email.</p>
                <div style="text-align:center; margin-top: 24px; color: #aaa; font-size: 0.9em;">&copy; ' . date('Y') . ' Foodify</div>
            </div>';

            $mail = new PHPMailer(true);
            try {
                include 'server_mail.php';
                $mail->addAddress($email, $user['full_name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body = $message_body;
                
                $mail->send();
                $message = "Password reset instructions have been sent to your email address.";
                $message_type = "success";
            } catch (Exception $e) {
                $message = "Failed to send reset email. Please try again later.";
                $message_type = "error";
            }
        } else {
            $message = "Failed to generate reset token. Please try again.";
            $message_type = "error";
        }
        $insert_stmt->close();
    } else {
        $message = "No account found with that email address or account is not verified.";
        $message_type = "error";
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Foodify</title>
    <link href="bootstrap/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="bootstrap/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="bootstrap/assets/css/style.css" rel="stylesheet">
    <link rel="icon" href="uploads/images/foodify_icon.png">
    <link rel="apple-touch-icon" href="uploads/images/foodify_icon.png">
    <style>
        body {
            min-height: 100vh;
            background: linear-gradient(270deg, #43e97b, #38f9d7, #43e97b, #b4ec51, #43e97b);
            background-size: 200% 200%;
            animation: gradientBG 8s ease-in-out infinite;
        }
        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }
        .auth-container {
            max-width: 420px;
            margin: 60px auto;
            background: #fff;
            border-radius: 18px;
            box-shadow: 0 8px 32px rgba(67, 233, 123, 0.15);
            overflow: hidden;
            animation: fadeIn 1s;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .auth-logo {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 32px 0 12px 0;
        }
        .auth-logo img {
            height: 180px;
        }
        .form-content {
            padding: 32px 24px 24px 24px;
        }
        .btn-primary {
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            border: none;
        }
        .btn-primary:hover {
            background: #43e97b;
        }
        .back-to-login {
            text-align: center;
            margin-top: 20px;
        }
        .back-to-login a {
            color: #43e97b;
            text-decoration: none;
            font-weight: 500;
        }
        .back-to-login a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-logo">
            <img src="uploads/images/slogan.png" alt="Foodify Logo">
        </div>
        <div class="form-content">
            <h4 class="text-center mb-4" style="color: #43e97b;">Forgot Password?</h4>
            <p class="text-center text-muted mb-4">Enter your email address and we'll send you instructions to reset your password.</p>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <form action="#" method="post" autocomplete="off">
                <div class="mb-4">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control" id="email" name="email" required 
                           placeholder="Enter your registered email address">
                </div>
                <button type="submit" class="btn btn-primary w-100 mb-3">Send Reset Instructions</button>
            </form>
            
            <div class="back-to-login">
                <a href="index.php">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>
    
    <script src="bootstrap/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>

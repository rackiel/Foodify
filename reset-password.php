<?php
session_start();
include 'config/db.php';

// Set timezone to Philippines
date_default_timezone_set('Asia/Manila');

$message = '';
$message_type = '';
$valid_token = false;
$user_id = null;

// Check if token is provided and valid
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];

    // Check if token exists and is not expired
    $current_time = date('Y-m-d H:i:s');
    $stmt = $conn->prepare("SELECT prt.user_id, prt.expires_at, ua.full_name, ua.email 
                           FROM password_reset_tokens prt 
                           JOIN user_accounts ua ON prt.user_id = ua.user_id 
                           WHERE prt.token = ? AND prt.used = 0 AND prt.expires_at > ?");
    $stmt->bind_param('ss', $token, $current_time);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $token_data = $result->fetch_assoc();
        $valid_token = true;
        $user_id = $token_data['user_id'];
    } else {
        $message = "Invalid or expired reset token. Please request a new password reset.";
        $message_type = "error";
    }
    $stmt->close();
} else {
    $message = "No reset token provided.";
    $message_type = "error";
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_password'], $_POST['confirm_password']) && $valid_token) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate passwords
    if ($new_password !== $confirm_password) {
        $message = "Passwords do not match.";
        $message_type = "error";
    } elseif (strlen($new_password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "error";
    } else {
        // Update password using secure hashing
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $update_stmt = $conn->prepare("UPDATE user_accounts SET password_hash = ? WHERE user_id = ?");
        $update_stmt->bind_param('si', $password_hash, $user_id);

        if ($update_stmt->execute()) {
            // Mark token as used
            $mark_used_stmt = $conn->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $mark_used_stmt->bind_param('s', $token);
            $mark_used_stmt->execute();
            $mark_used_stmt->close();

            $message = "Password has been reset successfully. You can now login with your new password.";
            $message_type = "success";
            $valid_token = false; // Hide the form after successful reset
        } else {
            $message = "Failed to update password. Please try again.";
            $message_type = "error";
        }
        $update_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Foodify</title>
    <link href="bootstrap/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <link href="bootstrap/assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
    <link href="bootstrap/assets/css/style.css" rel="stylesheet">
    <link href="assets/css/password-toggle.css" rel="stylesheet">
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
            0% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }

            100% {
                background-position: 0% 50%;
            }
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
            from {
                opacity: 0;
                transform: translateY(40px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
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

        .password-requirements {
            font-size: 0.9em;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-logo">
            <img src="uploads/images/slogan.png" alt="Foodify Logo">
        </div>
        <div class="form-content">
            <h4 class="text-center mb-4" style="color: #43e97b;">Reset Your Password</h4>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($valid_token): ?>
                <p class="text-center text-muted mb-4">Please enter your new password below.</p>

                <form action="#" method="post" autocomplete="off" id="resetForm">
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-control" id="new_password" name="new_password" required
                                placeholder="Enter your new password" minlength="6">
                        </div>
                        <div class="password-requirements">Password must be at least 6 characters long.</div>
                    </div>
                    <div class="mb-4">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="password-input-wrapper">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                                placeholder="Confirm your new password" minlength="6">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mb-3">Reset Password</button>
                </form>
            <?php else: ?>
                <div class="text-center">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                    <p class="mt-3 text-muted">This password reset link is invalid or has expired.</p>
                    <a href="forgot-password.php" class="btn btn-primary">Request New Reset Link</a>
                </div>
            <?php endif; ?>

            <div class="back-to-login">
                <a href="index.php">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script src="bootstrap/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation validation
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please try again.');
                return false;
            }
        });

        // Real-time password matching
        document.getElementById('confirm_password')?.addEventListener('input', function() {
            const password = document.getElementById('new_password').value;
            const confirmPassword = this.value;

            if (confirmPassword && password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });
    </script>
    <script src="assets/js/password-toggle.js"></script>
</body>

</html>
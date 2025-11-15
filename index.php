<?php
session_start();

// Check if user is already logged in and redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    // User is already logged in, redirect based on role
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin/index.php');
        exit();
    } elseif ($_SESSION['role'] === 'resident') {
        header('Location: residents/index.php');
        exit();
    } elseif ($_SESSION['role'] === 'team officer') {
        header('Location: teamofficer/index.php');
        exit();
    }
}

include 'config/db.php';
include 'phpmailer_setting.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login_username'], $_POST['login_password'])) {
    $login = trim($_POST['login_username']);
    $password_input = $_POST['login_password'];

    // Prepare statement to prevent SQL injection
    $stmt = $conn->prepare("SELECT * FROM user_accounts WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param('ss', $login, $login);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $stored_hash = $user['password_hash'];

        // Check password - support both new secure hashes and legacy MD5
        $password_valid = false;

        // First, try modern password_verify (for new secure hashes)
        if (password_verify($password_input, $stored_hash)) {
            $password_valid = true;

            // If this was an old MD5 hash that we migrated, update it to proper hash
            if (strlen($stored_hash) == 32 && ctype_xdigit($stored_hash)) {
                // This is an old MD5 hash, re-hash it properly
                $new_hash = password_hash($password_input, PASSWORD_DEFAULT);
                $update_stmt = $conn->prepare("UPDATE user_accounts SET password_hash = ? WHERE user_id = ?");
                $update_stmt->bind_param('si', $new_hash, $user['user_id']);
                $update_stmt->execute();
                $update_stmt->close();
            }
        }
        // Fallback: check legacy MD5 hash (for accounts not yet migrated)
        elseif (strlen($stored_hash) == 32 && md5($password_input) === $stored_hash) {
            $password_valid = true;

            // Migrate this MD5 hash to secure hash
            $new_hash = password_hash($password_input, PASSWORD_DEFAULT);
            $update_stmt = $conn->prepare("UPDATE user_accounts SET password_hash = ? WHERE user_id = ?");
            $update_stmt->bind_param('si', $new_hash, $user['user_id']);
            $update_stmt->execute();
            $update_stmt->close();
        }

        if ($password_valid) {
            // Check email verification and admin approval
            if ($user['is_verified'] == 1 && $user['is_approved'] == 1) {
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['user_type'] = $user['role']; // Add user_type for team officer compatibility
                $_SESSION['full_name'] = $user['full_name'] ?? '';
                $_SESSION['phone_number'] = $user['phone_number'] ?? '';
                $_SESSION['address'] = $user['address'] ?? '';
                // Store just the filename, not the full path
                $_SESSION['profile_img'] = !empty($user['profile_img']) ? $user['profile_img'] : 'no_image.png';

                // Redirect based on role
                if ($user['role'] === 'admin') {
                    header('Location: admin/index.php');
                    exit();
                } elseif ($user['role'] === 'resident') {
                    header('Location: residents/index.php');
                    exit();
                } elseif ($user['role'] === 'team officer') {
                    header('Location: teamofficer/index.php');
                    exit();
                } else {
                    $login_error = 'Unknown user role.';
                }
            } else {
                $login_pending_modal = true;
            }
        } else {
            $login_error = 'Invalid password.';
        }
    } else {
        $login_error = 'User not found.';
    }
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
}

// Handle registration form submission
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['full_name'], $_POST['email'], $_POST['username'], $_POST['password']) &&
    isset($_FILES['id_document'])
) {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $phone_number = $_POST['phone_number'] ?? '';
    $address = $_POST['address'] ?? '';
    $role = $_POST['role'] ?? 'resident';

    // Duplicate entry validation
    $dup_stmt = $conn->prepare("SELECT * FROM user_accounts WHERE full_name = ? OR username = ? OR email = ? LIMIT 1");
    $dup_stmt->bind_param('sss', $full_name, $username, $email);
    $dup_stmt->execute();
    $dup_result = $dup_stmt->get_result();
    if ($dup_result && $dup_result->num_rows > 0) {
        $dup_user = $dup_result->fetch_assoc();
        if ($dup_user['full_name'] === $full_name) {
            $register_error = 'The name has already created an account. Are you sure this is your first time creating an account?';
        } elseif ($dup_user['username'] === $username) {
            $register_error = 'Username already in use. Please try another.';
        } elseif ($dup_user['email'] === $email) {
            $register_error = 'Email already used.';
        }
    } else {
        // Handle file upload for ID/certification
        $id_document = '';
        if ($_FILES['id_document']['error'] === UPLOAD_ERR_OK) {
            $ext = pathinfo($_FILES['id_document']['name'], PATHINFO_EXTENSION);
            $id_document = 'uploads/id_documents/' . uniqid('id_') . '.' . $ext;
            if (!is_dir('uploads/id_documents')) {
                mkdir('uploads/id_documents', 0777, true);
            }
            move_uploaded_file($_FILES['id_document']['tmp_name'], $id_document);
        }

        // Password encryption using secure hashing
        $password_hash = password_hash($password, PASSWORD_DEFAULT);

        // Email verification
        $verification_token = bin2hex(random_bytes(16));
        $is_verified = 0;
        $is_approved = 0;

        // Insert user
        $stmt = $conn->prepare("INSERT INTO user_accounts (full_name, email, username, password_hash, phone_number, address, role, id_document, is_verified, verification_token, is_approved, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
        $stmt->bind_param('ssssssssssi', $full_name, $email, $username, $password_hash, $phone_number, $address, $role, $id_document, $is_verified, $verification_token, $is_approved);
        if ($stmt->execute()) {
            // Send verification email using PHPMailer
            $verify_link = "http://localhost/foodify/verify.php?token=$verification_token";
            $subject = "Verify your Foodify account";
            $message = '<div style="font-family: Arial, sans-serif; background: #f9f9f9; padding: 32px; border-radius: 12px; max-width: 480px; margin: 0 auto;">
            <div style="text-align:center; margin-bottom: 24px;">
                <img src="http://localhost/foodify/uploads/images/foodify_logo.png" alt="Foodify Logo" style="height: 80px;">
            </div>
            <h2 style="color: #43e97b; text-align:center;">Welcome to Foodify, ' . htmlspecialchars($full_name) . '!</h2>
            <p style="font-size: 1.1em; color: #333; text-align:center;">Thank you for registering. Please verify your account to continue.</p>
            <div style="text-align:center; margin: 32px 0;">
                <a href="' . $verify_link . '" style="background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%); color: #fff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 1.1em; display: inline-block;">Verify My Account</a>
            </div>
            <p style="color: #888; font-size: 0.95em; text-align:center;">If you did not request this, you can ignore this email.</p>
            <div style="text-align:center; margin-top: 24px; color: #aaa; font-size: 0.9em;">&copy; ' . date('Y') . ' Foodify</div>
        </div>';

            $mail = new PHPMailer(true);
            try {
                include 'server_mail.php';
                $mail->addAddress($email, $full_name);

                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $message;

                $mail->send();
                $register_success = "Registration successful! To continue your registration process, an email was sent to your account. Please check your email and verify your account. Thank you.";
            } catch (Exception $e) {
                $register_error = "Registration successful, but email could not be sent. Mailer Error: {$mail->ErrorInfo}";
            }
        } else {
            $register_error = "Registration failed: " . $stmt->error;
        }
    }
    if (isset($stmt) && $stmt) {
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Foodify - Login & Register</title>
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
            height: 230px;
        }

        .tab-slider {
            display: flex;
            border-bottom: 2px solid #e0f7ef;
        }

        .tab-slider button {
            flex: 1;
            background: none;
            border: none;
            padding: 16px 0;
            font-size: 1.1rem;
            font-weight: 600;
            color: #43e97b;
            transition: background 0.2s, color 0.2s;
        }

        .tab-slider button.active {
            color: #fff;
            background: #43e97b;
            border-bottom: 2px solid #43e97b;
        }

        .tab-content {
            padding: 32px 24px 24px 24px;
            animation: slideIn 0.5s;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(40px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .form-check-label {
            font-size: 0.95rem;
        }

        .forgot-link {
            float: right;
            font-size: 0.95rem;
        }

        .btn-primary {
            background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
            border: none;
        }

        .btn-primary:hover {
            background: #43e97b;
        }

        .form-control:hover {
            cursor: pointer;
        }
    </style>
</head>

<body>
    <div class="auth-container">
        <div class="auth-logo">
            <img src="uploads/images/slogan.png" alt="Foodify Logo">
        </div>
        <div class="tab-slider" id="tabSlider">
            <button class="active" onclick="showTab('login')" id="loginTabBtn">Login</button>
            <button onclick="showTab('register')" id="registerTabBtn">Create Account</button>
        </div>
        <div class="tab-content" id="loginTab">
            <form action="#" method="post" autocomplete="off">
                <div class="mb-3">
                    <label for="login-username" class="form-label">Username or Email</label>
                    <input type="text" class="form-control" id="login-username" name="login_username" required>
                </div>
                <div class="mb-3">
                    <label for="login-password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="login-password" name="login_password" required>
                </div>
                <div class="mb-3 d-flex justify-content-between align-items-center">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="keepLogin" name="keep_login">
                        <label class="form-check-label" for="keepLogin">Keep me login</label>
                    </div>
                    <a href="forgot-password.php" class="forgot-link">Forgot password?</a>
                </div>
                <button type="submit" class="btn btn-primary w-100">Login</button>
            </form>
        </div>
        <div class="tab-content" id="registerTab" style="display:none;">
            <form action="#" method="post" autocomplete="off" enctype="multipart/form-data">
                <div class="mb-3">
                    <label for="full_name" class="form-label">Full Name</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label for="register-email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="register-email" name="email" maxlength="100" required>
                </div>
                <div class="mb-3">
                    <label for="register-username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="register-username" name="username" maxlength="50" required>
                </div>
                <div class="mb-3">
                    <label for="register-password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="register-password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="phone_number" class="form-label">Phone Number</label>
                    <input type="text" class="form-control" id="phone_number" name="phone_number" maxlength="20">
                </div>
                <div class="mb-3">
                    <label for="address" class="form-label">Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-geo-alt-fill"></i></span>
                        <select class="form-control" id="address" name="address" required>
                            <option value="">Select your address</option>
                            <option value="Purok 1 - Tibanga, Iligan City">Purok 1 - Tibanga, Iligan City</option>
                            <option value="Purok 2 - Tibanga, Iligan City">Purok 2 - Tibanga, Iligan City</option>
                            <option value="Purok 3 - Tibanga, Iligan City">Purok 3 - Tibanga, Iligan City</option>
                            <option value="Purok 4 - Tibanga, Iligan City">Purok 4 - Tibanga, Iligan City</option>
                            <option value="Purok 5 - Tibanga, Iligan City">Purok 5 - Tibanga, Iligan City</option>
                            <option value="Purok 6 - Tibanga, Iligan City">Purok 6 - Tibanga, Iligan City</option>
                            <option value="Purok 7 - Tibanga, Iligan City">Purok 7 - Tibanga, Iligan City</option>
                            <option value="Purok 8 - Tibanga, Iligan City">Purok 8 - Tibanga, Iligan City</option>
                            <option value="Purok 9 - Tibanga, Iligan City">Purok 9 - Tibanga, Iligan City</option>
                            <option value="Purok 10 - Tibanga, Iligan City">Purok 10 - Tibanga, Iligan City</option>
                            <option value="Purok 11A - Tibanga, Iligan City">Purok 11A - Tibanga, Iligan City</option>
                            <option value="Purok 11B - Tibanga, Iligan City">Purok 11B - Tibanga, Iligan City</option>
                            <option value="Purok 12 - Tibanga, Iligan City">Purok 12 - Tibanga, Iligan City</option>
                            <option value="Purok 13 - Tibanga, Iligan City">Purok 13 - Tibanga, Iligan City</option>
                            <option value="Purok 14 - Tibanga, Iligan City">Purok 14 - Tibanga, Iligan City</option>
                            <option value="Purok 15 - Tibanga, Iligan City">Purok 15 - Tibanga, Iligan City</option>
                            <option value="Purok 16 - Tibanga, Iligan City">Purok 16 - Tibanga, Iligan City</option>
                            <option value="Purok 17 - Tibanga, Iligan City">Purok 17 - Tibanga, Iligan City</option>
                        </select>
                    </div>
                </div>
                <div class="mb-3">
                    <label for="id_document" class="form-label">Valid ID or Certification of Residency</label>
                    <input type="file" class="form-control" id="id_document" name="id_document" required>
                </div>
                <button type="submit" class="btn btn-primary w-100">Create Account</button>
            </form>
            <?php if (isset($register_success)): ?>
                <div class="alert alert-success"><?php echo $register_success; ?></div>
            <?php elseif (isset($register_error)): ?>
                <div class="alert alert-danger"><?php echo $register_error; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php if (isset($register_success)): ?>
        <div class="modal fade" id="registerSuccessModal" tabindex="-1" aria-labelledby="registerSuccessModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="registerSuccessModalLabel">Registration Successful!</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <?php echo $register_success; ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-success" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var registerModal = new bootstrap.Modal(document.getElementById('registerSuccessModal'));
                registerModal.show();
            });
        </script>
    <?php endif; ?>
    <?php if (isset($login_pending_modal) && $login_pending_modal): ?>
        <div class="modal fade" id="loginPendingModal" tabindex="-1" aria-labelledby="loginPendingModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header bg-warning text-dark">
                        <h5 class="modal-title" id="loginPendingModalLabel">Verification/Approval Pending</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        The verification or approval process is not yet complete. Please wait patiently. Thank you.
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-warning" data-bs-dismiss="modal">OK</button>
                    </div>
                </div>
            </div>
        </div>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var pendingModal = new bootstrap.Modal(document.getElementById('loginPendingModal'));
                pendingModal.show();
            });
        </script>
    <?php endif; ?>
    <script src="bootstrap/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script>
        function showTab(tab) {
            document.getElementById('loginTab').style.display = tab === 'login' ? '' : 'none';
            document.getElementById('registerTab').style.display = tab === 'register' ? '' : 'none';
            document.getElementById('loginTabBtn').classList.toggle('active', tab === 'login');
            document.getElementById('registerTabBtn').classList.toggle('active', tab === 'register');
            // Save the selected tab to localStorage
            localStorage.setItem('foodifyAuthTab', tab);
        }
        document.addEventListener('DOMContentLoaded', function() {
            var lastTab = localStorage.getItem('foodifyAuthTab') || 'login';
            showTab(lastTab);
        });
    </script>
</body>

</html>
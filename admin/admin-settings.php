<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

include '../config/db.php';

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update Profile Information
    if (isset($_POST['update_profile'])) {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        
        try {
            // Check if email already exists for another user
            $check_stmt = $conn->prepare("SELECT user_id FROM user_accounts WHERE email = ? AND user_id != ?");
            $check_stmt->bind_param('si', $email, $user_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $error_message = "Email address is already in use by another account!";
            } else {
                $stmt = $conn->prepare("UPDATE user_accounts SET full_name = ?, email = ?, phone_number = ?, address = ? WHERE user_id = ?");
                $stmt->bind_param('ssssi', $full_name, $email, $phone, $address, $user_id);
                
                if ($stmt->execute()) {
                    $success_message = "Profile updated successfully!";
                }
                $stmt->close();
            }
            $check_stmt->close();
        } catch (Exception $e) {
            $error_message = "Error updating profile: " . $e->getMessage();
        }
    }
    
    // Update Password
    if (isset($_POST['update_password'])) {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        if ($new_password !== $confirm_password) {
            $error_message = "New passwords do not match!";
        } elseif (strlen($new_password) < 6) {
            $error_message = "Password must be at least 6 characters long!";
        } else {
            try {
                // Verify current password
                $stmt = $conn->prepare("SELECT password_hash FROM user_accounts WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
                
                if (password_verify($current_password, $user['password_hash'])) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    $stmt = $conn->prepare("UPDATE user_accounts SET password_hash = ? WHERE user_id = ?");
                    $stmt->bind_param('si', $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_message = "Password updated successfully!";
                    }
                } else {
                    $error_message = "Current password is incorrect!";
                }
                $stmt->close();
            } catch (Exception $e) {
                $error_message = "Error updating password: " . $e->getMessage();
            }
        }
    }
    
    // Update Profile Picture
    if (isset($_POST['update_picture']) && isset($_FILES['profile_picture'])) {
        $file = $_FILES['profile_picture'];
        
        if ($file['error'] === 0) {
            $allowed = ['jpg', 'jpeg', 'png', 'gif'];
            $filename = $file['name'];
            $file_ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $file_size = $file['size'];
            
            if (!in_array($file_ext, $allowed)) {
                $error_message = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
            } elseif ($file_size > 2097152) { // 2MB
                $error_message = "File size too large. Maximum size is 2MB.";
            } else {
                $new_filename = uniqid('profile_', true) . '.' . $file_ext;
                $upload_path = '../uploads/profile_picture/' . $new_filename;
                
                if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                    try {
                        // Get old image to delete
                        $stmt = $conn->prepare("SELECT profile_img FROM user_accounts WHERE user_id = ?");
                        $stmt->bind_param('i', $user_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $old_data = $result->fetch_assoc();
                        
                        // Update database
                        $stmt = $conn->prepare("UPDATE user_accounts SET profile_img = ? WHERE user_id = ?");
                        $stmt->bind_param('si', $new_filename, $user_id);
                        
                        if ($stmt->execute()) {
                            // Delete old image if exists and not default
                            if (!empty($old_data['profile_img']) && 
                                $old_data['profile_img'] !== 'no_image.png' &&
                                file_exists('../uploads/profile_picture/' . $old_data['profile_img'])) {
                                unlink('../uploads/profile_picture/' . $old_data['profile_img']);
                            }
                            $success_message = "Profile picture updated successfully!";
                        }
                        $stmt->close();
                    } catch (Exception $e) {
                        $error_message = "Error updating profile picture: " . $e->getMessage();
                    }
                } else {
                    $error_message = "Failed to upload file. Please try again.";
                }
            }
        }
    }
}

// Fetch current user data
try {
    $stmt = $conn->prepare("SELECT * FROM user_accounts WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
} catch (Exception $e) {
    $error_message = "Error fetching user data: " . $e->getMessage();
}

include 'header.php';
?>

<body>
<?php include 'topbar.php'; ?>
<?php include 'sidebar.php'; ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-gear"></i> Administrator Settings</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </nav>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> <?= $success_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <section class="section">
        <div class="row">
            <!-- Sidebar Navigation -->
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <a href="#profile" class="list-group-item list-group-item-action active" data-bs-toggle="pill">
                                <i class="bi bi-person"></i> Profile Information
                            </a>
                            <a href="#security" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                <i class="bi bi-shield-lock"></i> Security
                            </a>
                            <a href="#account" class="list-group-item list-group-item-action" data-bs-toggle="pill">
                                <i class="bi bi-person-gear"></i> Account Details
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Quick Links -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-3 text-muted">Quick Links</h6>
                        <div class="d-grid gap-2">
                            <a href="users-profile.php" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-person-circle"></i> View My Profile
                            </a>
                            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                            <a href="users.php" class="btn btn-sm btn-outline-info">
                                <i class="bi bi-people"></i> Manage Users
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Content -->
            <div class="col-lg-9">
                <div class="tab-content">
                    
                    <!-- Profile Information -->
                    <div class="tab-pane fade show active" id="profile">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person"></i> Profile Information</h5>
                            </div>
                            <div class="card-body">
                                <!-- Profile Picture -->
                                <div class="row mb-4">
                                    <div class="col-12">
                                        <h6 class="mb-3">Profile Picture</h6>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= !empty($user_data['profile_img']) ? '../uploads/profile_picture/' . $user_data['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                 class="rounded-circle me-3" width="100" height="100" alt="Profile" id="profilePreview" style="object-fit: cover; border: 3px solid #e9ecef;">
                                            <div>
                                                <form method="POST" enctype="multipart/form-data" id="pictureForm">
                                                    <input type="file" name="profile_picture" id="profilePictureInput" class="form-control mb-2" accept="image/*" onchange="previewImage(this)">
                                                    <button type="submit" name="update_picture" class="btn btn-primary btn-sm">
                                                        <i class="bi bi-upload"></i> Upload New Picture
                                                    </button>
                                                    <small class="form-text text-muted d-block mt-2">
                                                        JPG, JPEG, PNG or GIF (Max 2MB)
                                                    </small>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <hr>

                                <!-- Profile Form -->
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" name="full_name" 
                                                   value="<?= htmlspecialchars($user_data['full_name']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" name="email" 
                                                   value="<?= htmlspecialchars($user_data['email']) ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Phone Number</label>
                                            <input type="text" class="form-control" name="phone" 
                                                   value="<?= htmlspecialchars($user_data['phone_number'] ?? '') ?>"
                                                   placeholder="+63 XXX XXX XXXX">
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Role</label>
                                            <input type="text" class="form-control" 
                                                   value="<?= ucfirst($user_data['role'] ?? 'Administrator') ?>" disabled>
                                        </div>
                                        <div class="col-12 mb-3">
                                            <label class="form-label">Address</label>
                                            <textarea class="form-control" name="address" rows="3" 
                                                      placeholder="Enter your complete address"><?= htmlspecialchars($user_data['address'] ?? '') ?></textarea>
                                        </div>
                                        <div class="col-12">
                                            <button type="submit" name="update_profile" class="btn btn-primary">
                                                <i class="bi bi-check-circle"></i> Save Changes
                                            </button>
                                            <a href="index.php" class="btn btn-outline-secondary">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <!-- Security -->
                    <div class="tab-pane fade" id="security">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-shield-lock"></i> Security Settings</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="mb-3">Change Password</h6>
                                <form method="POST">
                                    <div class="row">
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label">Current Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="current_password" 
                                                       id="currentPassword" required>
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="togglePassword('currentPassword')">
                                                    <i class="bi bi-eye" id="currentPasswordIcon"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">New Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="new_password" 
                                                       id="newPassword" minlength="6" required>
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="togglePassword('newPassword')">
                                                    <i class="bi bi-eye" id="newPasswordIcon"></i>
                                                </button>
                                            </div>
                                            <small class="form-text text-muted">Minimum 6 characters</small>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                            <div class="input-group">
                                                <input type="password" class="form-control" name="confirm_password" 
                                                       id="confirmPassword" minlength="6" required>
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="togglePassword('confirmPassword')">
                                                    <i class="bi bi-eye" id="confirmPasswordIcon"></i>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="col-12 mb-3">
                                            <div class="alert alert-warning">
                                                <i class="bi bi-shield-exclamation"></i>
                                                <strong>Security Tips:</strong>
                                                <ul class="mb-0 mt-2">
                                                    <li>Use a strong password with letters, numbers, and symbols</li>
                                                    <li>Never share your administrator password</li>
                                                    <li>Change your password regularly (every 3-6 months)</li>
                                                    <li>Use a unique password for this account</li>
                                                    <li>Enable two-factor authentication when available</li>
                                                </ul>
                                            </div>
                                        </div>
                                        
                                        <div class="col-12">
                                            <button type="submit" name="update_password" class="btn btn-primary">
                                                <i class="bi bi-key"></i> Update Password
                                            </button>
                                        </div>
                                    </div>
                                </form>

                                <hr class="my-4">

                                <h6 class="mb-3">Account Activity</h6>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <tbody>
                                            <tr>
                                                <td width="40%"><strong>Account Created:</strong></td>
                                                <td><?= date('F j, Y g:i A', strtotime($user_data['created_at'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Last Updated:</strong></td>
                                                <td><?= date('F j, Y g:i A', strtotime($user_data['updated_at'] ?? $user_data['created_at'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Current Session:</strong></td>
                                                <td><?= date('F j, Y g:i A') ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Account Status:</strong></td>
                                                <td>
                                                    <span class="badge bg-<?= $user_data['status'] === 'approved' ? 'success' : 'warning' ?>">
                                                        <?= ucfirst($user_data['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Account Details -->
                    <div class="tab-pane fade" id="account">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="bi bi-person-gear"></i> Account Details</h5>
                            </div>
                            <div class="card-body">
                                <h6 class="mb-3">Account Information</h6>
                                <div class="table-responsive mb-4">
                                    <table class="table table-bordered">
                                        <tbody>
                                            <tr>
                                                <td width="30%"><strong>User ID:</strong></td>
                                                <td>#<?= $user_data['user_id'] ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Username:</strong></td>
                                                <td><?= htmlspecialchars($user_data['username']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Full Name:</strong></td>
                                                <td><?= htmlspecialchars($user_data['full_name']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Email:</strong></td>
                                                <td><?= htmlspecialchars($user_data['email']) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Phone:</strong></td>
                                                <td><?= htmlspecialchars($user_data['phone_number'] ?? 'Not provided') ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Address:</strong></td>
                                                <td><?= htmlspecialchars($user_data['address'] ?? 'Not provided') ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Account Type:</strong></td>
                                                <td>
                                                    <span class="badge bg-danger">
                                                        <i class="bi bi-shield-check"></i> <?= ucfirst($user_data['role'] ?? 'Administrator') ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Status:</strong></td>
                                                <td>
                                                    <span class="badge bg-<?= $user_data['status'] === 'approved' ? 'success' : 'warning' ?>">
                                                        <?= ucfirst($user_data['status']) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td><strong>Member Since:</strong></td>
                                                <td><?= date('F j, Y', strtotime($user_data['created_at'])) ?></td>
                                            </tr>
                                            <tr>
                                                <td><strong>Last Updated:</strong></td>
                                                <td><?= date('F j, Y g:i A', strtotime($user_data['updated_at'] ?? $user_data['created_at'])) ?></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Administrator Privileges:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Full access to all platform features and settings</li>
                                        <li>Ability to manage all user accounts</li>
                                        <li>Approve or reject food donations</li>
                                        <li>Moderate community content and reports</li>
                                        <li>Access to analytics and reporting tools</li>
                                        <li>System configuration and maintenance</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </section>
</main>

<style>
.list-group-item {
    border-left: 3px solid transparent;
    transition: all 0.3s;
}

.list-group-item:hover {
    border-left-color: #0d6efd;
    background-color: #f8f9fa;
}

.list-group-item.active {
    border-left-color: #0d6efd;
    background-color: #e7f1ff;
    color: #0d6efd;
}

.alert ul {
    padding-left: 20px;
}

.input-group .btn-outline-secondary {
    border-left: 0;
}
</style>

<script>
// Preview profile picture before upload
function previewImage(input) {
    if (input.files && input.files[0]) {
        // Validate file size
        if (input.files[0].size > 2097152) {
            alert('File size too large. Maximum size is 2MB.');
            input.value = '';
            return;
        }
        
        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(input.files[0].type)) {
            alert('Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.');
            input.value = '';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePreview').src = e.target.result;
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Password visibility toggle
function togglePassword(inputId) {
    const input = document.getElementById(inputId);
    const icon = document.getElementById(inputId + 'Icon');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye');
        icon.classList.add('bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-slash');
        icon.classList.add('bi-eye');
    }
}

// Password confirmation validation
document.addEventListener('DOMContentLoaded', function() {
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    
    if (confirmPassword) {
        confirmPassword.addEventListener('input', function() {
            if (newPassword.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
                confirmPassword.classList.add('is-invalid');
            } else {
                confirmPassword.setCustomValidity('');
                confirmPassword.classList.remove('is-invalid');
                if (confirmPassword.value) {
                    confirmPassword.classList.add('is-valid');
                }
            }
        });
        
        newPassword.addEventListener('input', function() {
            if (confirmPassword.value) {
                confirmPassword.dispatchEvent(new Event('input'));
            }
        });
    }
    
    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-info):not(.alert-warning)');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>


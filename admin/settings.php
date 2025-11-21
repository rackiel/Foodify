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
            $stmt = $conn->prepare("UPDATE user_accounts SET full_name = ?, email = ?, phone_number = ?, address = ? WHERE user_id = ?");
            $stmt->bind_param('ssssi', $full_name, $email, $phone, $address, $user_id);

            if ($stmt->execute()) {
                $success_message = "Profile updated successfully!";
            }
            $stmt->close();
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

            if (in_array($file_ext, $allowed)) {
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
                            // Delete old image if exists
                            if (!empty($old_data['profile_img']) && file_exists('../uploads/profile_picture/' . $old_data['profile_img'])) {
                                unlink('../uploads/profile_picture/' . $old_data['profile_img']);
                            }
                            $success_message = "Profile picture updated successfully!";
                        }
                        $stmt->close();
                    } catch (Exception $e) {
                        $error_message = "Error updating profile picture: " . $e->getMessage();
                    }
                }
            } else {
                $error_message = "Invalid file type. Only JPG, JPEG, PNG & GIF files are allowed.";
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
            <h1><i class="bi bi-gear"></i> Settings</h1>
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
                                    <i class="bi bi-person-gear"></i> Account Management
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
                                                    class="rounded-circle me-3" width="100" height="100" alt="Profile" id="profilePreview" style="object-fit: cover;">
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
                                                    value="<?= htmlspecialchars($user_data['phone_number'] ?? '') ?>">
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">User Type</label>
                                                <input type="text" class="form-control"
                                                    value="<?= ucfirst($user_data['role'] ?? 'Administrator') ?>" disabled>
                                            </div>
                                            <div class="col-12 mb-3">
                                                <label class="form-label">Address</label>
                                                <textarea class="form-control" name="address" rows="3"><?= htmlspecialchars($user_data['address'] ?? '') ?></textarea>
                                            </div>
                                            <div class="col-12">
                                                <button type="submit" name="update_profile" class="btn btn-primary">
                                                    <i class="bi bi-check-circle"></i> Save Changes
                                                </button>
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
                                                <div class="password-input-wrapper">
                                                    <input type="password" class="form-control" name="current_password" required>
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">New Password <span class="text-danger">*</span></label>
                                                <div class="password-input-wrapper">
                                                    <input type="password" class="form-control" name="new_password"
                                                        id="newPassword" minlength="6" required>
                                                </div>
                                                <small class="form-text text-muted">Minimum 6 characters</small>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label">Confirm New Password <span class="text-danger">*</span></label>
                                                <div class="password-input-wrapper">
                                                    <input type="password" class="form-control" name="confirm_password"
                                                        id="confirmPassword" minlength="6" required>
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
                                                    <td><strong>Account Created:</strong></td>
                                                    <td><?= date('F j, Y g:i A', strtotime($user_data['created_at'])) ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Last Login:</strong></td>
                                                    <td><?= date('F j, Y g:i A') ?> (Current Session)</td>
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

                        <!-- Account Management -->
                        <div class="tab-pane fade" id="account">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-person-gear"></i> Account Management</h5>
                                </div>
                                <div class="card-body">
                                    <h6 class="mb-3">Account Information</h6>
                                    <div class="table-responsive mb-4">
                                        <table class="table">
                                            <tbody>
                                                <tr>
                                                    <td><strong>User ID:</strong></td>
                                                    <td>#<?= $user_data['user_id'] ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Username:</strong></td>
                                                    <td><?= htmlspecialchars($user_data['username']) ?></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Account Type:</strong></td>
                                                    <td><span class="badge bg-danger"><?= ucfirst($user_data['role'] ?? 'Administrator') ?></span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Status:</strong></td>
                                                    <td><span class="badge bg-<?= $user_data['status'] === 'approved' ? 'success' : 'warning' ?>"><?= ucfirst($user_data['status']) ?></span></td>
                                                </tr>
                                                <tr>
                                                    <td><strong>Member Since:</strong></td>
                                                    <td><?= date('F j, Y', strtotime($user_data['created_at'])) ?></td>
                                                </tr>
                                            </tbody>
                                        </table>
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

        #profilePreview {
            border: 3px solid #e9ecef;
        }
    </style>

    <script>
        // Preview profile picture before upload
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
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
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                });
            }

            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
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
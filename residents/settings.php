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

// ==========================================
// Fetch User Data Directly from Database
// ==========================================
$user_data = null;
try {
    $stmt = $conn->prepare("SELECT * FROM user_accounts WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $user_data = $result->fetch_assoc();
    }
    $stmt->close();
} catch (Exception $e) {
    $error_message = "Error fetching user data: " . $e->getMessage();
}

// Check if user data exists
if (!$user_data) {
    die("Error: Unable to load user profile. Please try logging in again.");
}

// ==========================================
// FUNCTION: Fetch User Profile Data
// ==========================================
function getUserProfile($conn, $user_id)
{
    try {
        $stmt = $conn->prepare("SELECT * FROM user_accounts WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $stmt->close();
        return $user_data;
    } catch (Exception $e) {
        return null;
    }
}

// ==========================================
// FUNCTION: Update Profile Information
// ==========================================
function updateProfileInfo($conn, $user_id, $full_name, $username, $email, $phone_number, $address)
{
    try {
        // Check if username already exists for another user
        $stmt = $conn->prepare("SELECT user_id FROM user_accounts WHERE username = ? AND user_id != ?");
        $stmt->bind_param('si', $username, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Username already taken by another user.'];
        }
        $stmt->close();

        // Check if email already exists for another user
        $stmt = $conn->prepare("SELECT user_id FROM user_accounts WHERE email = ? AND user_id != ?");
        $stmt->bind_param('si', $email, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $stmt->close();
            return ['success' => false, 'message' => 'Email already registered to another account.'];
        }
        $stmt->close();

        // Update profile information
        $stmt = $conn->prepare("UPDATE user_accounts SET full_name = ?, username = ?, email = ?, phone_number = ?, address = ? WHERE user_id = ?");
        $stmt->bind_param('sssssi', $full_name, $username, $email, $phone_number, $address, $user_id);

        if ($stmt->execute()) {
            // Update session variables
            $_SESSION['full_name'] = $full_name;
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;

            $stmt->close();
            return ['success' => true, 'message' => 'Profile updated successfully!'];
        } else {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to update profile.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==========================================
// FUNCTION: Update Profile Picture
// ==========================================
function updateProfilePicture($conn, $user_id, $file)
{
    try {
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        $max_file_size = 5 * 1024 * 1024; // 5MB

        // Validate file
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['success' => false, 'message' => 'File upload error.'];
        }

        if ($file['size'] > $max_file_size) {
            return ['success' => false, 'message' => 'File size exceeds 5MB limit.'];
        }

        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_extensions)) {
            return ['success' => false, 'message' => 'Only JPG, JPEG, PNG, and GIF files are allowed.'];
        }

        // Generate unique filename
        $new_filename = 'profile_' . $user_id . '_' . uniqid() . '.' . $file_extension;
        $upload_path = '../uploads/profile_picture/' . $new_filename;

        // Get old profile image to delete
        $stmt = $conn->prepare("SELECT profile_img FROM user_accounts WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $old_data = $result->fetch_assoc();
        $old_image = $old_data['profile_img'] ?? null;
        $stmt->close();

        // Upload new file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            // Update database
            $stmt = $conn->prepare("UPDATE user_accounts SET profile_img = ? WHERE user_id = ?");
            $stmt->bind_param('si', $new_filename, $user_id);

            if ($stmt->execute()) {
                // Delete old image if exists and not default
                if ($old_image && $old_image !== 'no_image.png' && file_exists('../uploads/profile_picture/' . $old_image)) {
                    unlink('../uploads/profile_picture/' . $old_image);
                }

                $stmt->close();
                return ['success' => true, 'message' => 'Profile picture updated successfully!'];
            } else {
                // Delete uploaded file if database update fails
                unlink($upload_path);
                $stmt->close();
                return ['success' => false, 'message' => 'Failed to update database.'];
            }
        } else {
            return ['success' => false, 'message' => 'Failed to upload file.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==========================================
// FUNCTION: Change Password
// ==========================================
function changePassword($conn, $user_id, $current_password, $new_password, $confirm_password)
{
    try {
        // Validate new password
        if (strlen($new_password) < 6) {
            return ['success' => false, 'message' => 'New password must be at least 6 characters long.'];
        }

        if ($new_password !== $confirm_password) {
            return ['success' => false, 'message' => 'New passwords do not match.'];
        }

        // Get current password hash
        $stmt = $conn->prepare("SELECT password FROM user_accounts WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        // Verify current password
        if (!password_verify($current_password, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect.'];
        }

        // Hash new password
        $new_password_hash = password_hash($new_password, PASSWORD_DEFAULT);

        // Update password
        $stmt = $conn->prepare("UPDATE user_accounts SET password = ? WHERE user_id = ?");
        $stmt->bind_param('si', $new_password_hash, $user_id);

        if ($stmt->execute()) {
            $stmt->close();
            return ['success' => true, 'message' => 'Password changed successfully!'];
        } else {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to change password.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==========================================
// FUNCTION: Delete Profile Picture
// ==========================================
function deleteProfilePicture($conn, $user_id)
{
    try {
        // Get current profile image
        $stmt = $conn->prepare("SELECT profile_img FROM user_accounts WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user_data = $result->fetch_assoc();
        $old_image = $user_data['profile_img'] ?? null;
        $stmt->close();

        // Reset to default
        $stmt = $conn->prepare("UPDATE user_accounts SET profile_img = NULL WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);

        if ($stmt->execute()) {
            // Delete old image file if exists
            if ($old_image && $old_image !== 'no_image.png' && file_exists('../uploads/profile_picture/' . $old_image)) {
                unlink('../uploads/profile_picture/' . $old_image);
            }

            $stmt->close();
            return ['success' => true, 'message' => 'Profile picture removed successfully!'];
        } else {
            $stmt->close();
            return ['success' => false, 'message' => 'Failed to remove profile picture.'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// ==========================================
// Fetch Account Statistics Directly from Database
// ==========================================
$account_stats = [
    'total_donations' => 0,
    'total_requests' => 0,
    'total_recipes_saved' => 0,
    'total_meal_plans' => 0,
    'member_since' => $user_data['created_at'] ?? date('Y-m-d H:i:s'),
    'last_activity' => ''
];

try {
    // Get donations count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM food_donations WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $account_stats['total_donations'] = $data['count'] ?? 0;
    }
    $stmt->close();
} catch (Exception $e) {
    // Keep default value
}

try {
    // Get requests count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM food_requests WHERE requester_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $account_stats['total_requests'] = $data['count'] ?? 0;
    }
    $stmt->close();
} catch (Exception $e) {
    // Keep default value
}

try {
    // Get saved recipes count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM recipe_tip_saves WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $account_stats['total_recipes_saved'] = $data['count'] ?? 0;
    }
    $stmt->close();
} catch (Exception $e) {
    // Keep default value
}

try {
    // Get meal plans count
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM meal_plans WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $account_stats['total_meal_plans'] = $data['count'] ?? 0;
    }
    $stmt->close();
} catch (Exception $e) {
    // Keep default value
}

// ==========================================
// FUNCTION: Get Account Statistics
// ==========================================
function getAccountStatistics($conn, $user_id)
{
    try {
        $stats = [
            'total_donations' => 0,
            'total_requests' => 0,
            'total_recipes_saved' => 0,
            'total_meal_plans' => 0,
            'member_since' => '',
            'last_activity' => ''
        ];

        // Get donations count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM food_donations WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_donations'] = $result->fetch_assoc()['count'];
        $stmt->close();

        // Get requests count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM food_requests WHERE requester_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_requests'] = $result->fetch_assoc()['count'];
        $stmt->close();

        // Get saved recipes count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM recipe_tip_saves WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_recipes_saved'] = $result->fetch_assoc()['count'];
        $stmt->close();

        // Get meal plans count
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM meal_plans WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $stats['total_meal_plans'] = $result->fetch_assoc()['count'];
        $stmt->close();

        // Get member since date
        $stmt = $conn->prepare("SELECT created_at FROM user_accounts WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stats['member_since'] = $user['created_at'];
        $stmt->close();

        return $stats;
    } catch (Exception $e) {
        return null;
    }
}

// ==========================================
// Handle POST Requests
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Update Profile Information
    if (isset($_POST['action']) && $_POST['action'] === 'update_profile') {
        $full_name = trim($_POST['full_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone_number = trim($_POST['phone_number'] ?? '');
        $address = trim($_POST['address'] ?? '');

        // Validate required fields
        if (empty($full_name) || empty($username) || empty($email)) {
            $error_message = 'Full name, username, and email are required.';
        } else if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Invalid email format.';
        } else {
            $result = updateProfileInfo($conn, $user_id, $full_name, $username, $email, $phone_number, $address);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
    }

    // Update Profile Picture
    if (isset($_POST['action']) && $_POST['action'] === 'update_picture' && isset($_FILES['profile_picture'])) {
        $result = updateProfilePicture($conn, $user_id, $_FILES['profile_picture']);
        if ($result['success']) {
            $success_message = $result['message'];
        } else {
            $error_message = $result['message'];
        }
    }

    // Delete Profile Picture
    if (isset($_POST['action']) && $_POST['action'] === 'delete_picture') {
        $result = deleteProfilePicture($conn, $user_id);
        if ($result['success']) {
            $success_message = $result['message'];
        } else {
            $error_message = $result['message'];
        }
    }

    // Change Password
    if (isset($_POST['action']) && $_POST['action'] === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error_message = 'All password fields are required.';
        } else {
            $result = changePassword($conn, $user_id, $current_password, $new_password, $confirm_password);
            if ($result['success']) {
                $success_message = $result['message'];
            } else {
                $error_message = $result['message'];
            }
        }
    }

    // Refresh user data after POST operations to show updated information
    try {
        $stmt = $conn->prepare("SELECT * FROM user_accounts WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $user_data = $result->fetch_assoc();
        }
        $stmt->close();
    } catch (Exception $e) {
        // Use existing user_data
    }
}

include 'header.php';
?>

<body>
    <?php include 'topbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1><i class="bi bi-gear"></i> Profile Settings</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Settings</li>
                </ol>
            </nav>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle"></i> <?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <section class="section profile">
            <div class="row">

                <!-- Left Sidebar - Profile Picture & Stats -->
                <div class="col-xl-4">

                    <!-- Profile Picture Card -->
                    <div class="card mb-4">
                        <div class="card-body profile-card pt-4 d-flex flex-column align-items-center">
                            <img src="<?= !empty($user_data['profile_img']) ? '../uploads/profile_picture/' . htmlspecialchars($user_data['profile_img']) : '../uploads/profile_picture/no_image.png' ?>?v=<?= time() ?>"
                                alt="Profile" class="rounded-circle profile-image" id="profileImagePreview">
                            <h2 class="mt-3"><?= htmlspecialchars($user_data['full_name'] ?? 'User') ?></h2>
                            <h3><?= ucfirst($user_data['role'] ?? 'Resident') ?></h3>
                            <div class="mt-3 d-flex gap-2">
                                <button class="btn btn-primary btn-sm" onclick="document.getElementById('profilePictureInput').click()">
                                    <i class="bi bi-upload"></i> Upload New
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteProfilePicture()">
                                    <i class="bi bi-trash"></i> Remove
                                </button>
                            </div>
                            <form id="profilePictureForm" method="POST" enctype="multipart/form-data" style="display: none;">
                                <input type="hidden" name="action" value="update_picture">
                                <input type="file" name="profile_picture" id="profilePictureInput" accept="image/*" onchange="previewAndSubmitImage(this)">
                            </form>
                        </div>
                    </div>

                    <!-- Account Statistics -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><i class="bi bi-graph-up"></i> Account Statistics</h5>

                            <div class="stat-item">
                                <i class="bi bi-basket text-success"></i>
                                <div class="stat-content">
                                    <span class="stat-label">Food Donations</span>
                                    <span class="stat-value"><?= number_format($account_stats['total_donations'] ?? 0) ?></span>
                                </div>
                            </div>

                            <div class="stat-item">
                                <i class="bi bi-inbox text-primary"></i>
                                <div class="stat-content">
                                    <span class="stat-label">Food Requests</span>
                                    <span class="stat-value"><?= number_format($account_stats['total_requests'] ?? 0) ?></span>
                                </div>
                            </div>

                            <div class="stat-item">
                                <i class="bi bi-bookmark text-warning"></i>
                                <div class="stat-content">
                                    <span class="stat-label">Saved Recipes</span>
                                    <span class="stat-value"><?= number_format($account_stats['total_recipes_saved'] ?? 0) ?></span>
                                </div>
                            </div>

                            <div class="stat-item">
                                <i class="bi bi-calendar3 text-info"></i>
                                <div class="stat-content">
                                    <span class="stat-label">Meal Plans</span>
                                    <span class="stat-value"><?= number_format($account_stats['total_meal_plans'] ?? 0) ?></span>
                                </div>
                            </div>

                            <div class="stat-item border-0 pb-0">
                                <i class="bi bi-clock-history text-secondary"></i>
                                <div class="stat-content">
                                    <span class="stat-label">Member Since</span>
                                    <span class="stat-value"><?= isset($account_stats['member_since']) ? date('M d, Y', strtotime($account_stats['member_since'])) : 'N/A' ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Content - Settings Forms -->
                <div class="col-xl-8">

                    <div class="card">
                        <div class="card-body pt-3">

                            <!-- Tabs -->
                            <ul class="nav nav-tabs nav-tabs-bordered" role="tablist">
                                <li class="nav-item">
                                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-overview">
                                        <i class="bi bi-person"></i> Overview
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-edit">
                                        <i class="bi bi-pencil"></i> Edit Profile
                                    </button>
                                </li>
                                <li class="nav-item">
                                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#profile-change-password">
                                        <i class="bi bi-lock"></i> Change Password
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content pt-4">

                                <!-- Overview Tab -->
                                <div class="tab-pane fade show active profile-overview" id="profile-overview">
                                    <h5 class="card-title">Profile Details</h5>
                                    <?php
                                    // Fetch fresh profile data directly from database (same pattern as topbar.php)
                                    $profile_full_name = 'N/A';
                                    $profile_username = 'N/A';
                                    $profile_email = 'N/A';
                                    $profile_phone = '';
                                    $profile_address = '';
                                    $profile_status = 'pending';
                                    $profile_user_id = 'N/A';
                                    $profile_created_at = '';

                                    try {
                                        $stmt = $conn->prepare("SELECT full_name, username, email, phone_number, address, status, user_id, created_at FROM user_accounts WHERE user_id = ?");
                                        $stmt->bind_param('i', $user_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result && $result->num_rows > 0) {
                                            $profile_data = $result->fetch_assoc();
                                            $profile_full_name = $profile_data['full_name'] ?? 'N/A';
                                            $profile_username = $profile_data['username'] ?? 'N/A';
                                            $profile_email = $profile_data['email'] ?? 'N/A';
                                            $profile_phone = $profile_data['phone_number'] ?? '';
                                            $profile_address = $profile_data['address'] ?? '';
                                            $profile_status = $profile_data['status'] ?? 'pending';
                                            $profile_user_id = $profile_data['user_id'] ?? 'N/A';
                                            $profile_created_at = $profile_data['created_at'] ?? '';
                                        }
                                        $stmt->close();
                                    } catch (Exception $e) {
                                        // Keep default values on error
                                    }
                                    ?>

                                    <div class="row mb-3">
                                        <div class="col-lg-3 col-md-4 label"><strong>Full Name</strong></div>
                                        <div class="col-lg-9 col-md-8"><?= htmlspecialchars($profile_full_name) ?></div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-lg-3 col-md-4 label"><strong>Username</strong></div>
                                        <div class="col-lg-9 col-md-8"><?= htmlspecialchars($profile_username) ?></div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-lg-3 col-md-4 label"><strong>Email</strong></div>
                                        <div class="col-lg-9 col-md-8"><?= htmlspecialchars($profile_email) ?></div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-lg-3 col-md-4 label"><strong>Phone Number</strong></div>
                                        <div class="col-lg-9 col-md-8">
                                            <?php if (!empty($profile_phone)): ?>
                                                <?= htmlspecialchars($profile_phone) ?>
                                            <?php else: ?>
                                                <em class="text-muted">Not provided</em>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-lg-3 col-md-4 label"><strong>Address</strong></div>
                                        <div class="col-lg-9 col-md-8">
                                            <?php if (!empty($profile_address)): ?>
                                                <?= htmlspecialchars($profile_address) ?>
                                            <?php else: ?>
                                                <em class="text-muted">Not provided</em>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-lg-3 col-md-4 label"><strong>Account Status</strong></div>
                                        <div class="col-lg-9 col-md-8">
                                            <span class="badge bg-<?= $profile_status === 'approved' ? 'success' : 'warning' ?>">
                                                <?= ucfirst($profile_status) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-lg-3 col-md-4 label"><strong>User ID</strong></div>
                                        <div class="col-lg-9 col-md-8">#<?= $profile_user_id ?></div>
                                    </div>

                                    <div class="row mb-3">
                                        <div class="col-lg-3 col-md-4 label"><strong>Account Created</strong></div>
                                        <div class="col-lg-9 col-md-8">
                                            <?php if (!empty($profile_created_at)): ?>
                                                <?= date('F j, Y \a\t g:i A', strtotime($profile_created_at)) ?>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Edit Profile Tab -->
                                <div class="tab-pane fade profile-edit pt-3" id="profile-edit">
                                    <?php
                                    // Fetch fresh edit data directly from database (same pattern as topbar.php)
                                    $edit_full_name = '';
                                    $edit_username = '';
                                    $edit_email = '';
                                    $edit_phone = '';
                                    $edit_address = '';

                                    try {
                                        $stmt = $conn->prepare("SELECT full_name, username, email, phone_number, address FROM user_accounts WHERE user_id = ?");
                                        $stmt->bind_param('i', $user_id);
                                        $stmt->execute();
                                        $result = $stmt->get_result();
                                        if ($result && $result->num_rows > 0) {
                                            $edit_data = $result->fetch_assoc();
                                            $edit_full_name = $edit_data['full_name'] ?? '';
                                            $edit_username = $edit_data['username'] ?? '';
                                            $edit_email = $edit_data['email'] ?? '';
                                            $edit_phone = $edit_data['phone_number'] ?? '';
                                            $edit_address = $edit_data['address'] ?? '';
                                        }
                                        $stmt->close();
                                    } catch (Exception $e) {
                                        // Keep default values on error
                                    }
                                    ?>
                                    <form method="POST" action="">
                                        <input type="hidden" name="action" value="update_profile">

                                        <div class="row mb-3">
                                            <label for="fullName" class="col-md-4 col-lg-3 col-form-label">Full Name <span class="text-danger">*</span></label>
                                            <div class="col-md-8 col-lg-9">
                                                <input type="text" name="full_name" class="form-control" id="fullName"
                                                    value="<?= htmlspecialchars($edit_full_name) ?>" required>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label for="username" class="col-md-4 col-lg-3 col-form-label">Username <span class="text-danger">*</span></label>
                                            <div class="col-md-8 col-lg-9">
                                                <input type="text" name="username" class="form-control" id="username"
                                                    value="<?= htmlspecialchars($edit_username) ?>" required>
                                                <small class="form-text text-muted">Username must be unique</small>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label for="email" class="col-md-4 col-lg-3 col-form-label">Email <span class="text-danger">*</span></label>
                                            <div class="col-md-8 col-lg-9">
                                                <input type="email" name="email" class="form-control" id="email"
                                                    value="<?= htmlspecialchars($edit_email) ?>" required>
                                                <small class="form-text text-muted">Email must be unique</small>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label for="phone" class="col-md-4 col-lg-3 col-form-label">Phone Number</label>
                                            <div class="col-md-8 col-lg-9">
                                                <input type="text" name="phone_number" class="form-control" id="phone"
                                                    value="<?= htmlspecialchars($edit_phone) ?>"
                                                    placeholder="+63 912 345 6789">
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label for="address" class="col-md-4 col-lg-3 col-form-label">Address</label>
                                            <div class="col-md-8 col-lg-9">
                                                <textarea name="address" class="form-control" id="address" rows="3"
                                                    placeholder="Enter your complete address"><?= htmlspecialchars($edit_address) ?></textarea>
                                            </div>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" class="btn btn-primary px-4">
                                                <i class="bi bi-save"></i> Save Changes
                                            </button>
                                            <button type="reset" class="btn btn-secondary px-4">
                                                <i class="bi bi-x-circle"></i> Reset
                                            </button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Change Password Tab -->
                                <div class="tab-pane fade pt-3" id="profile-change-password">
                                    <form method="POST" action="" onsubmit="return validatePasswordForm()">
                                        <input type="hidden" name="action" value="change_password">

                                        <div class="row mb-3">
                                            <label for="currentPassword" class="col-md-4 col-lg-3 col-form-label">Current Password <span class="text-danger">*</span></label>
                                            <div class="col-md-8 col-lg-9">
                                                <div class="password-input-wrapper">
                                                    <input type="password" name="current_password" class="form-control" id="currentPassword" required>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label for="newPassword" class="col-md-4 col-lg-3 col-form-label">New Password <span class="text-danger">*</span></label>
                                            <div class="col-md-8 col-lg-9">
                                                <div class="password-input-wrapper">
                                                    <input type="password" name="new_password" class="form-control" id="newPassword"
                                                        minlength="6" required>
                                                </div>
                                                <small class="form-text text-muted">Minimum 6 characters</small>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <label for="confirmPassword" class="col-md-4 col-lg-3 col-form-label">Confirm New Password <span class="text-danger">*</span></label>
                                            <div class="col-md-8 col-lg-9">
                                                <div class="password-input-wrapper">
                                                    <input type="password" name="confirm_password" class="form-control" id="confirmPassword"
                                                        minlength="6" required>
                                                </div>
                                                <div id="passwordMatchError" class="text-danger mt-1" style="display: none;">
                                                    Passwords do not match
                                                </div>
                                            </div>
                                        </div>

                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle"></i>
                                            <strong>Password Requirements:</strong>
                                            <ul class="mb-0 mt-2">
                                                <li>Minimum 6 characters long</li>
                                                <li>Mix of letters and numbers recommended</li>
                                                <li>Avoid common passwords</li>
                                            </ul>
                                        </div>

                                        <div class="text-center">
                                            <button type="submit" class="btn btn-primary px-4">
                                                <i class="bi bi-shield-check"></i> Change Password
                                            </button>
                                        </div>
                                    </form>
                                </div>

                            </div><!-- End Tabs -->

                        </div>
                    </div>

                </div>
            </div>
        </section>

    </main>

    <style>
        .profile-image {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 5px solid #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.15);
        }

        .profile-card h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .profile-card h3 {
            font-size: 1rem;
            color: #6c757d;
        }

        .stat-item {
            display: flex;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }

        .stat-item i {
            font-size: 2rem;
            margin-right: 15px;
            width: 40px;
        }

        .stat-content {
            flex: 1;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .stat-value {
            font-weight: 600;
            color: #343a40;
            font-size: 1.1rem;
        }

        .nav-tabs-bordered .nav-link {
            color: #6c757d;
            border: 0;
            border-bottom: 3px solid transparent;
        }

        .nav-tabs-bordered .nav-link:hover {
            color: #0d6efd;
        }

        .nav-tabs-bordered .nav-link.active {
            color: #0d6efd;
            border-bottom-color: #0d6efd;
        }

        .label {
            font-weight: 600;
            color: #495057;
        }

        .card {
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.08);
            border: none;
            border-radius: 10px;
        }

        .card-title {
            font-weight: 600;
            color: #343a40;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
    </style>

    <script>
        // Preview and submit profile picture
        function previewAndSubmitImage(input) {
            if (input.files && input.files[0]) {
                const file = input.files[0];

                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    input.value = '';
                    return;
                }

                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPG, JPEG, PNG, and GIF files are allowed');
                    input.value = '';
                    return;
                }

                // Preview image
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profileImagePreview').src = e.target.result;
                };
                reader.readAsDataURL(file);

                // Submit form
                document.getElementById('profilePictureForm').submit();
            }
        }

        // Delete profile picture
        function deleteProfilePicture() {
            if (confirm('Are you sure you want to remove your profile picture?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'action';
                input.value = 'delete_picture';

                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Validate password form
        function validatePasswordForm() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            const errorDiv = document.getElementById('passwordMatchError');

            if (newPassword !== confirmPassword) {
                errorDiv.style.display = 'block';
                document.getElementById('confirmPassword').classList.add('is-invalid');
                return false;
            }

            errorDiv.style.display = 'none';
            document.getElementById('confirmPassword').classList.remove('is-invalid');
            return true;
        }

        // Real-time password match validation
        document.getElementById('confirmPassword')?.addEventListener('input', function() {
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = this.value;
            const errorDiv = document.getElementById('passwordMatchError');

            if (confirmPassword && newPassword !== confirmPassword) {
                errorDiv.style.display = 'block';
                this.classList.add('is-invalid');
            } else {
                errorDiv.style.display = 'none';
                this.classList.remove('is-invalid');
            }
        });

        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false];

    try {
        // Get Residents by Purok
        if ($action === 'get_residents') {
            $purok = trim($_POST['purok'] ?? '');
            $stmt = $conn->prepare("
                SELECT user_id, full_name, username, email, phone_number, profile_img, status, address, created_at
                FROM user_accounts 
                WHERE address = ? AND role = 'resident' AND status != 'inactive'
                ORDER BY created_at DESC
            ");
            $stmt->bind_param('s', $purok);
            $stmt->execute();
            $result = $stmt->get_result();
            $residents = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $response['success'] = true;
            $response['residents'] = $residents;
        }

        // Update Resident Status
        elseif ($action === 'update_resident_status') {
            $user_id = intval($_POST['user_id']);
            $status = $_POST['status'];
            $purok = trim($_POST['purok'] ?? '');

            $stmt = $conn->prepare("UPDATE user_accounts SET status = ? WHERE user_id = ? AND address = ? AND role = 'resident'");
            $stmt->bind_param('sis', $status, $user_id, $purok);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Resident status updated successfully!';
            }
            $stmt->close();
        }

        // Add New User (Team Officer)
        elseif ($action === 'add_user') {
            $full_name = trim($_POST['full_name']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $password = $_POST['password'];
            $role = $_POST['role'];
            $phone = trim($_POST['phone_number'] ?? '');
            $address = trim($_POST['address'] ?? '');

            // Check if username or email already exists
            $check_stmt = $conn->prepare("SELECT user_id FROM user_accounts WHERE username = ? OR email = ?");
            $check_stmt->bind_param('ss', $username, $email);
            $check_stmt->execute();

            if ($check_stmt->get_result()->num_rows > 0) {
                $response['message'] = 'Username or email already exists!';
            } else {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                $stmt = $conn->prepare("
                    INSERT INTO user_accounts (full_name, username, email, password_hash, role, phone_number, address, status, is_approved, is_verified)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active', 1, 1)
                ");
                $stmt->bind_param('sssssss', $full_name, $username, $email, $hashed_password, $role, $phone, $address);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'User added successfully!';
                }
                $stmt->close();
            }
            $check_stmt->close();
        }

        // Edit User
        elseif ($action === 'edit_user') {
            $user_id = intval($_POST['user_id']);
            $full_name = trim($_POST['full_name']);
            $username = trim($_POST['username']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $phone = trim($_POST['phone_number'] ?? '');
            $address = trim($_POST['address'] ?? '');

            $stmt = $conn->prepare("
                UPDATE user_accounts 
                SET full_name = ?, username = ?, email = ?, role = ?, phone_number = ?, address = ?
                WHERE user_id = ?
            ");
            $stmt->bind_param('ssssssi', $full_name, $username, $email, $role, $phone, $address, $user_id);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'User updated successfully!';
            }
            $stmt->close();
        }

        // Archive User
        elseif ($action === 'archive_user') {
            $user_id = intval($_POST['user_id']);

            $stmt = $conn->prepare("UPDATE user_accounts SET status = 'inactive' WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'User archived successfully!';
            }
            $stmt->close();
        }

        // Delete User
        elseif ($action === 'delete_user') {
            $user_id = intval($_POST['user_id']);

            // Check if it's the current admin
            if ($user_id === $_SESSION['user_id']) {
                $response['message'] = 'Cannot delete your own account!';
            } else {
                $stmt = $conn->prepare("DELETE FROM user_accounts WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'User deleted successfully!';
                }
                $stmt->close();
            }
        }

        // Update Status
        elseif ($action === 'update_status') {
            $user_id = intval($_POST['user_id']);
            $status = $_POST['status'];

            $stmt = $conn->prepare("UPDATE user_accounts SET status = ? WHERE user_id = ?");
            $stmt->bind_param('si', $status, $user_id);

            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Status updated successfully!';
            }
            $stmt->close();
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// Get statistics
try {
    $stats = [];

    // Get current user's address (purok)
    $current_user_stmt = $conn->prepare("SELECT address, role FROM user_accounts WHERE user_id = ?");
    $current_user_stmt->bind_param('i', $_SESSION['user_id']);
    $current_user_stmt->execute();
    $current_user_result = $current_user_stmt->get_result();
    $current_user = $current_user_result->fetch_assoc();
    $current_user_stmt->close();

    $current_user_address = $current_user['address'] ?? '';
    $is_team_officer = ($current_user['role'] === 'team officer');

    // Count by role
    $result = $conn->query("
        SELECT 
            COUNT(CASE WHEN status != 'inactive' THEN 1 END) as total,
            COUNT(CASE WHEN role = 'admin' AND status != 'inactive' THEN 1 END) as admins,
            COUNT(CASE WHEN role = 'resident' AND status != 'inactive' THEN 1 END) as residents,
            COUNT(CASE WHEN role = 'team officer' AND status != 'inactive' THEN 1 END) as officers,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as active,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'inactive' THEN 1 END) as archived
        FROM user_accounts
    ");
    $stats = $result->fetch_assoc();

    // Get filter
    $role_filter = isset($_GET['role']) ? $_GET['role'] : '';
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';

    // Build query - exclude admin role by default, or show when admin filter is clicked
    $where_conditions = [];
    $params = [];
    $types = '';

    // Exclude admins unless specifically filtering for admins
    if ($role_filter !== 'admin') {
        $where_conditions[] = "role != 'admin'";
    }

    // Exclude archived users unless specifically filtering for archived
    if ($status_filter !== 'inactive') {
        $where_conditions[] = "status != 'inactive'";
    }

    // If team officer, filter residents by their purok
    if ($is_team_officer && !$role_filter) {
        $where_conditions[] = "(role = 'team officer' OR address = ?)";
        $params[] = $current_user_address;
        $types .= 's';
    } elseif ($is_team_officer && $role_filter === 'resident') {
        $where_conditions[] = "address = ?";
        $params[] = $current_user_address;
        $types .= 's';
    }

    if ($status_filter && $status_filter !== 'all' && $status_filter !== '') {
        $where_conditions[] = "status = ?";
        $params[] = $status_filter;
        $types .= 's';
    }

    if ($role_filter && in_array($role_filter, ['admin', 'resident', 'team officer'])) {
        $where_conditions[] = "role = ?";
        $params[] = $role_filter;
        $types .= 's';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    if (!empty($params)) {
        $stmt = $conn->prepare("SELECT * FROM user_accounts $where_clause ORDER BY created_at DESC");
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();
        $users = $result->fetch_all(MYSQLI_ASSOC);
    } else {
        $result = $conn->query("SELECT * FROM user_accounts $where_clause ORDER BY created_at DESC");
        $users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $users = [];
    $stats = ['total' => 0, 'admins' => 0, 'residents' => 0, 'officers' => 0, 'active' => 0, 'pending' => 0, 'archived' => 0];
}

include 'header.php';
?>

<body>
    <?php include 'topbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1><i class="bi bi-people"></i> User Management</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Users</li>
                </ol>
            </nav>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <section class="section">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Total Users</h6>
                                    <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                                </div>
                                <div class="stat-icon bg-primary">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Residents</h6>
                                    <h3 class="mb-0"><?= number_format($stats['residents']) ?></h3>
                                </div>
                                <div class="stat-icon bg-info">
                                    <i class="bi bi-person"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Team Officers</h6>
                                    <h3 class="mb-0"><?= number_format($stats['officers']) ?></h3>
                                </div>
                                <div class="stat-icon bg-success">
                                    <i class="bi bi-person-badge"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Administrators</h6>
                                    <h3 class="mb-0"><?= number_format($stats['admins']) ?></h3>
                                </div>
                                <div class="stat-icon bg-danger">
                                    <i class="bi bi-shield-check"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="card-title mb-0">Users List</h5>
                        <button class="btn btn-success" onclick="showAddModal()">
                            <i class="bi bi-person-plus"></i> Add Team Officer
                        </button>
                    </div>

                    <!-- Filters -->
                    <div class="mb-3">
                        <div class="btn-group me-2" role="group">
                            <a href="users.php" class="btn btn-sm btn-outline-primary <?= $status_filter === '' ? 'active' : '' ?>">
                                All Users (<?= $stats['total'] ?>)
                            </a>
                            <a href="users.php?status=approved" class="btn btn-sm btn-outline-success <?= $status_filter === 'approved' ? 'active' : '' ?>">
                                Active (<?= $stats['active'] ?>)
                            </a>
                            <a href="users.php?status=pending" class="btn btn-sm btn-outline-warning <?= $status_filter === 'pending' ? 'active' : '' ?>">
                                Pending (<?= $stats['pending'] ?>)
                            </a>
                            <a href="users.php?status=inactive" class="btn btn-sm btn-outline-secondary <?= $status_filter === 'inactive' ? 'active' : '' ?>">
                                Archived (<?= $stats['archived'] ?>)
                            </a>
                        </div>

                        <div class="btn-group" role="group">
                            <a href="users.php?role=admin" class="btn btn-sm btn-outline-danger <?= $role_filter === 'admin' ? 'active' : '' ?>">
                                <i class="bi bi-shield-check"></i> Admins (<?= $stats['admins'] ?>)
                            </a>
                            <a href="users.php?role=team officer" class="btn btn-sm btn-outline-success <?= $role_filter === 'team officer' ? 'active' : '' ?>">
                                <i class="bi bi-person-badge"></i> Officers (<?= $stats['officers'] ?>)
                            </a>
                            <a href="users.php?role=resident" class="btn btn-sm btn-outline-info <?= $role_filter === 'resident' ? 'active' : '' ?>">
                                <i class="bi bi-person"></i> Residents (<?= $stats['residents'] ?>)
                            </a>
                        </div>
                    </div>

                    <!-- Search -->
                    <div class="mb-3">
                        <input type="text" class="form-control" id="searchInput" placeholder="Search users by name, email, or username..." onkeyup="searchUsers()">
                    </div>

                    <div class="table-responsive">
                        <table class="table table-hover" id="usersTable">
                            <thead class="table-light">
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($users)): ?>
                                    <?php foreach ($users as $user): ?>
                                        <tr class="user-row">
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>"
                                                        class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                    <div>
                                                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                                        <br><small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td><?= htmlspecialchars($user['phone_number'] ?? 'N/A') ?></td>
                                            <td><?= htmlspecialchars($user['address'] ?? 'N/A') ?></td>
                                            <td>
                                                <span class="badge bg-<?=
                                                                        $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'team officer' ? 'success' : 'primary')
                                                                        ?>"><?= ucfirst($user['role']) ?></span>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm"
                                                    onchange="updateStatus(<?= $user['user_id'] ?>, this.value)">
                                                    <option value="approved" <?= $user['status'] === 'approved' ? 'selected' : '' ?>>Approved</option>
                                                    <option value="pending" <?= $user['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                    <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                                </select>
                                            </td>
                                            <td>
                                                <small><?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="users-profile.php?id=<?= $user['user_id'] ?>"
                                                        class="btn btn-sm btn-info" title="View Profile">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if ($role_filter === 'team officer' && $user['role'] === 'team officer'): ?>
                                                        <button class="btn btn-sm btn-success"
                                                            onclick="toggleResidents(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['address']) ?>', '<?= htmlspecialchars($user['full_name']) ?>')"
                                                            title="View Residents">
                                                            <i class="bi bi-people"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php if ($user['status'] === 'inactive'): ?>
                                                        <!-- Archived User Actions -->
                                                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                                            <button class="btn btn-sm btn-success unarchive-btn" onclick="unarchiveUser(<?= $user['user_id'] ?>)" title="Unarchive User">
                                                                <i class="bi bi-arrow-up-circle"></i>
                                                            </button>
                                                            <button class="btn btn-sm btn-danger delete-btn" onclick="deleteUser(<?= $user['user_id'] ?>)" title="Delete User">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <!-- Active User Actions -->
                                                        <button class="btn btn-sm btn-primary"
                                                            onclick='editUser(<?= json_encode($user, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'
                                                            title="Edit User">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <?php if ($user['user_id'] !== $_SESSION['user_id']): ?>
                                                            <button class="btn btn-sm btn-warning"
                                                                onclick="archiveUser(<?= $user['user_id'] ?>)"
                                                                title="Archive User">
                                                                <i class="bi bi-archive"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center text-muted py-4">No users found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Add Team Officer Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-person-plus"></i> Add New Team Officer</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        <strong>Team Officer Account:</strong> This will create a new team officer account with full access to manage donations, announcements, and community features.
                    </div>
                    <form id="addUserForm">
                        <input type="hidden" name="role" value="team officer">

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="full_name"
                                    placeholder="Enter full name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="username"
                                    placeholder="Choose a unique username" required>
                                <small class="form-text text-muted">Used for login</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" name="email"
                                    placeholder="email@example.com" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="addPassword" name="password"
                                        minlength="6" placeholder="Minimum 6 characters" required>
                                    <button class="btn btn-outline-secondary" type="button"
                                        onclick="togglePasswordAdd()">
                                        <i class="bi bi-eye" id="addPasswordIcon"></i>
                                    </button>
                                </div>
                                <small class="form-text text-muted">Officer will use this to login</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" name="phone_number"
                                    placeholder="+63 XXX XXX XXXX">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Officer Role Badge</label>
                                <input type="text" class="form-control" value="Team Officer" disabled>
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Address <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-geo-alt-fill"></i></span>
                                    <select class="form-control" name="address" required>
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
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="bi bi-x-circle"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-check-circle"></i> Create Team Officer Account
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editUserForm">
                        <input type="hidden" id="edit_user_id" name="user_id">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Username <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="edit_username" name="username" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="edit_email" name="email" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="edit_role" name="role" required>
                                    <option value="admin">Administrator</option>
                                    <option value="team officer">Team Officer</option>
                                    <option value="resident">Resident</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="edit_phone_number" name="phone_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Address</label>
                                <select class="form-select" id="edit_address" name="address">
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
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Residents Modal -->
    <div class="modal fade" id="residentsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-people"></i> <span id="modalLeaderName"></span> - Residents</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <input type="text" class="form-control" id="residentsSearchInput" placeholder="Search residents by name, email, or username..." onkeyup="searchResidentsModal()">
                    </div>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Resident</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Registered</th>
                                </tr>
                            </thead>
                            <tbody id="residentsBody">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <div class="spinner-border spinner-border-sm" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div> Loading residents...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .stat-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .stat-card .card-body {
            padding: 20px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
    </style>

    <script>
        // Show Add User Modal
        function showAddModal() {
            document.getElementById('addUserForm').reset();
            new bootstrap.Modal(document.getElementById('addUserModal')).show();
        }

        // Toggle password visibility for add form
        function togglePasswordAdd() {
            const input = document.getElementById('addPassword');
            const icon = document.getElementById('addPasswordIcon');

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

        // Toggle Residents
        function toggleResidents(leaderId, purok, leaderName) {
            document.getElementById('modalLeaderName').textContent = leaderName + ' (' + purok + ')';
            loadResidents(purok);
            new bootstrap.Modal(document.getElementById('residentsModal')).show();
        }

        // Load Residents via AJAX
        function loadResidents(purok) {
            const formData = new FormData();
            formData.append('action', 'get_residents');
            formData.append('purok', purok);

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderResidents(data.residents);
                    } else {
                        showNotification('Failed to load residents', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Error loading residents', 'error');
                });
        }

        // Render Residents Table
        function renderResidents(residents) {
            const tbody = document.getElementById('residentsBody');

            if (residents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-4">No residents found in this purok</td></tr>';
                return;
            }

            tbody.innerHTML = residents.map(resident => `
                <tr class="resident-row-modal">
                    <td>
                        <div class="d-flex align-items-center">
                            <img src="${!resident.profile_img ? '../uploads/profile_picture/no_image.png' : '../uploads/profile_picture/' + resident.profile_img}"
                                class="rounded-circle me-2" width="32" height="32" alt="Profile">
                            <div>
                                <strong>${escapeHtml(resident.full_name)}</strong>
                                <br><small class="text-muted">@${escapeHtml(resident.username)}</small>
                            </div>
                        </div>
                    </td>
                    <td>${escapeHtml(resident.email)}</td>
                    <td>${escapeHtml(resident.phone_number || 'N/A')}</td>
                    <td>
                        <small>${new Date(resident.created_at).toLocaleDateString('en-US', {year: 'numeric', month: 'short', day: 'numeric'})}</small>
                    </td>
                </tr>
            `).join('');
        }

        // Update Resident Status
        function updateResidentStatus(userId, status, purok) {
            const formData = new FormData();
            formData.append('action', 'update_resident_status');
            formData.append('user_id', userId);
            formData.append('status', status);
            formData.append('purok', purok);

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message || 'Failed to update status', 'error');
                    }
                });
        }

        // Search Residents in Modal
        function searchResidentsModal() {
            const searchTerm = document.getElementById('residentsSearchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.resident-row-modal');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }

        // Escape HTML
        function escapeHtml(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }

        // Add User Form Submit
        document.getElementById('addUserForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'add_user');

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showNotification(data.message || 'Failed to add user', 'error');
                    }
                });
        });

        // Edit User
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.user_id;
            document.getElementById('edit_full_name').value = user.full_name;
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_phone_number').value = user.phone_number || '';
            document.getElementById('edit_address').value = user.address || '';

            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }

        // Edit User Form Submit
        document.getElementById('editUserForm').addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            formData.append('action', 'edit_user');

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showNotification(data.message || 'Failed to update user', 'error');
                    }
                });
        });

        // Update Status
        function updateStatus(userId, status) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('user_id', userId);
            formData.append('status', status);

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                    } else {
                        showNotification(data.message || 'Failed to update status', 'error');
                        setTimeout(() => window.location.reload(), 1000);
                    }
                });
        }

        // Archive User
        function archiveUser(userId) {
            if (!confirm('Are you sure you want to archive this user?')) return;

            const formData = new FormData();
            formData.append('action', 'archive_user');
            formData.append('user_id', userId);

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        
                        // If residents modal is open, reload the residents list
                        const residentsModal = document.getElementById('residentsModal');
                        if (residentsModal && bootstrap.Modal.getInstance(residentsModal)) {
                            const leaderNameSpan = document.getElementById('modalLeaderName');
                            const leaderText = leaderNameSpan.textContent;
                            const purok = leaderText.substring(leaderText.lastIndexOf('(') + 1, leaderText.lastIndexOf(')'));
                            if (purok) {
                                loadResidents(purok);
                            }
                        } else {
                            // Otherwise reload the page
                            setTimeout(() => window.location.reload(), 1500);
                        }
                    } else {
                        showNotification(data.message || 'Failed to archive user', 'error');
                    }
                });
        }

        // Unarchive User
        function unarchiveUser(userId) {
            if (!confirm('Are you sure you want to unarchive this user?')) return;

            const formData = new FormData();
            formData.append('action', 'archive_user');
            formData.append('user_id', userId);
            
            // Change to unarchive by updating status to approved
            const unarchiveData = new FormData();
            unarchiveData.append('action', 'update_status');
            unarchiveData.append('user_id', userId);
            unarchiveData.append('status', 'approved');

            fetch('users.php', {
                    method: 'POST',
                    body: unarchiveData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('User unarchived successfully!', 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showNotification(data.message || 'Failed to unarchive user', 'error');
                    }
                });
        }

        // Delete User
        function deleteUser(userId) {
            if (!confirm('Are you sure you want to delete this user? This action cannot be undone!')) return;

            const formData = new FormData();
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);

            fetch('users.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1500);
                    } else {
                        showNotification(data.message || 'Failed to delete user', 'error');
                    }
                });
        }

        // Search Users
        function searchUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.user-row');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }

        // Notification System
        function showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' :
                type === 'error' ? 'alert-danger' :
                type === 'warning' ? 'alert-warning' : 'alert-info';

            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            notification.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;

            document.body.appendChild(notification);

            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
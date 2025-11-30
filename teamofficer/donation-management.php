<?php
include '../config/db.php';
include 'email_notifications.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is team officer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'team officer') {
    header('Location: ../index.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $donation_id = intval($_POST['donation_id'] ?? 0);
    
    // Actions that require donation_id
    $actions_requiring_donation_id = ['approve', 'reject', 'get_details', 'delete', 'assign_donation'];
    
    if (in_array($action, $actions_requiring_donation_id) && $donation_id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid donation ID.']);
        exit;
    }
    
    try {
        switch ($action) {
            case 'approve':
                // First get donation and donor details for email
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email 
                    FROM food_donations fd 
                    JOIN user_accounts ua ON fd.user_id = ua.user_id 
                    WHERE fd.id = ?
                ");
                $stmt->bind_param('i', $donation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $donation = $result->fetch_assoc();
                $stmt->close();
                
                // Update approval status
                $stmt = $conn->prepare("UPDATE food_donations SET approval_status = 'approved', approved_at = NOW(), approved_by = ? WHERE id = ?");
                $stmt->bind_param('ii', $_SESSION['user_id'], $donation_id);
                
                if ($stmt->execute()) {
                    // Send approval email notification
                    $emailNotifier = new DonationEmailNotifications();
                    $emailSent = $emailNotifier->sendDonationApproved($donation, $donation['email'], $donation['full_name']);
                    
                    $message = 'Donation approved successfully!';
                    if ($emailSent) {
                        $message .= ' Email notification sent to donor.';
                    } else {
                        $message .= ' Note: Email notification could not be sent.';
                    }
                    
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to approve donation.']);
                }
                $stmt->close();
                break;
                
            case 'reject':
                $rejection_reason = trim($_POST['rejection_reason']);
                if (empty($rejection_reason)) {
                    echo json_encode(['success' => false, 'message' => 'Rejection reason is required.']);
                    exit;
                }
                
                // First get donation and donor details for email
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email 
                    FROM food_donations fd 
                    JOIN user_accounts ua ON fd.user_id = ua.user_id 
                    WHERE fd.id = ?
                ");
                $stmt->bind_param('i', $donation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $donation = $result->fetch_assoc();
                $stmt->close();
                
                // Update rejection status
                $stmt = $conn->prepare("UPDATE food_donations SET approval_status = 'rejected', approved_at = NOW(), approved_by = ?, rejection_reason = ? WHERE id = ?");
                $stmt->bind_param('isi', $_SESSION['user_id'], $rejection_reason, $donation_id);
                
                if ($stmt->execute()) {
                    // Send rejection email notification
                    $emailNotifier = new DonationEmailNotifications();
                    $emailSent = $emailNotifier->sendDonationRejected($donation, $donation['email'], $donation['full_name'], $rejection_reason);
                    
                    $message = 'Donation rejected successfully!';
                    if ($emailSent) {
                        $message .= ' Email notification sent to donor.';
                    } else {
                        $message .= ' Note: Email notification could not be sent.';
                    }
                    
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to reject donation.']);
                }
                $stmt->close();
                break;
                
            case 'get_details':
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email, ua.phone_number,
                           admin.full_name as approved_by_name,
                           assigned_ua.full_name as assigned_to_name,
                           assigned_ua.email as assigned_to_email
                    FROM food_donations fd 
                    JOIN user_accounts ua ON fd.user_id = ua.user_id 
                    LEFT JOIN user_accounts admin ON fd.approved_by = admin.user_id
                    LEFT JOIN user_accounts assigned_ua ON fd.assigned_to_user_id = assigned_ua.user_id
                    WHERE fd.id = ?
                ");
                $stmt->bind_param('i', $donation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $donation = $result->fetch_assoc();
                
                if ($donation) {
                    echo json_encode(['success' => true, 'donation' => $donation]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Donation not found.']);
                }
                $stmt->close();
                break;
                
            case 'get_residents':
                // Get team officer's address to filter residents
                $stmt = $conn->prepare("SELECT address FROM user_accounts WHERE user_id = ?");
                $stmt->bind_param('i', $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $officer = $result->fetch_assoc();
                $stmt->close();
                
                $team_officer_address = trim($officer['address'] ?? '');
                
                if (empty($team_officer_address)) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'Team officer address not set. Please update your address in Settings first.',
                        'residents' => []
                    ]);
                    exit;
                }
                
                // Get donation_id if provided to exclude the donor from the list
                $exclude_user_id = null;
                $donation_id_for_exclusion = intval($_POST['donation_id'] ?? 0);
                if ($donation_id_for_exclusion > 0) {
                    $stmt = $conn->prepare("SELECT user_id FROM food_donations WHERE id = ?");
                    $stmt->bind_param('i', $donation_id_for_exclusion);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $donation_data = $result->fetch_assoc();
                    $stmt->close();
                    if ($donation_data) {
                        $exclude_user_id = $donation_data['user_id'];
                    }
                }
                
                // Get residents from the same address/purok (include all statuses, but prioritize approved)
                // Exclude the donor if donation_id is provided
                // First try to get approved residents
                if ($exclude_user_id) {
                    $stmt = $conn->prepare("
                        SELECT user_id, full_name, email, phone_number, address, status
                        FROM user_accounts 
                        WHERE role = 'resident' 
                        AND LOWER(TRIM(address)) = LOWER(TRIM(?))
                        AND status = 'approved'
                        AND user_id != ?
                        ORDER BY full_name ASC
                    ");
                    $stmt->bind_param('si', $team_officer_address, $exclude_user_id);
                } else {
                    $stmt = $conn->prepare("
                        SELECT user_id, full_name, email, phone_number, address, status
                        FROM user_accounts 
                        WHERE role = 'resident' 
                        AND LOWER(TRIM(address)) = LOWER(TRIM(?))
                        AND status = 'approved'
                        ORDER BY full_name ASC
                    ");
                    $stmt->bind_param('s', $team_officer_address);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                $residents = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                // If no approved residents found, get all residents (including pending) for better visibility
                if (empty($residents)) {
                    if ($exclude_user_id) {
                        $stmt = $conn->prepare("
                            SELECT user_id, full_name, email, phone_number, address, status
                            FROM user_accounts 
                            WHERE role = 'resident' 
                            AND LOWER(TRIM(address)) = LOWER(TRIM(?))
                            AND user_id != ?
                            ORDER BY 
                                CASE status 
                                    WHEN 'approved' THEN 1 
                                    WHEN 'pending' THEN 2 
                                    ELSE 3 
                                END,
                                full_name ASC
                        ");
                        $stmt->bind_param('si', $team_officer_address, $exclude_user_id);
                    } else {
                        $stmt = $conn->prepare("
                            SELECT user_id, full_name, email, phone_number, address, status
                            FROM user_accounts 
                            WHERE role = 'resident' 
                            AND LOWER(TRIM(address)) = LOWER(TRIM(?))
                            ORDER BY 
                                CASE status 
                                    WHEN 'approved' THEN 1 
                                    WHEN 'pending' THEN 2 
                                    ELSE 3 
                                END,
                                full_name ASC
                        ");
                        $stmt->bind_param('s', $team_officer_address);
                    }
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $residents = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                }
                
                // Always return success: true, but include a message if no residents found
                $message = '';
                if (empty($residents)) {
                    $message = "No residents found in your area (Address: {$team_officer_address}). Please ensure residents have the same address set in their profiles and are approved.";
                }
                
                echo json_encode([
                    'success' => true, 
                    'residents' => $residents,
                    'message' => $message
                ]);
                break;
                
            case 'assign_donation':
                $assigned_to_user_id = intval($_POST['assigned_to_user_id'] ?? 0);
                $assignment_notes = trim($_POST['assignment_notes'] ?? '');
                
                if ($assigned_to_user_id <= 0) {
                    echo json_encode(['success' => false, 'message' => 'Please select a resident to assign this donation to.']);
                    exit;
                }
                
                // Verify the donation exists and is approved
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email 
                    FROM food_donations fd 
                    JOIN user_accounts ua ON fd.user_id = ua.user_id 
                    WHERE fd.id = ? AND fd.approval_status = 'approved'
                ");
                $stmt->bind_param('i', $donation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $donation = $result->fetch_assoc();
                $stmt->close();
                
                if (!$donation) {
                    echo json_encode(['success' => false, 'message' => 'Donation not found or not approved.']);
                    exit;
                }
                
                // Check if trying to assign donation to the donor themselves
                if ($donation['user_id'] == $assigned_to_user_id) {
                    echo json_encode(['success' => false, 'message' => 'Cannot assign donation to the donor. A donor cannot receive their own donation.']);
                    exit;
                }
                
                // Verify the assigned user is a resident
                $stmt = $conn->prepare("SELECT user_id, full_name, email, phone_number, role FROM user_accounts WHERE user_id = ? AND role = 'resident'");
                $stmt->bind_param('i', $assigned_to_user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $assigned_user = $result->fetch_assoc();
                $stmt->close();
                
                if (!$assigned_user) {
                    echo json_encode(['success' => false, 'message' => 'Invalid resident selected.']);
                    exit;
                }
                
                // Update donation with assigned user
                $stmt = $conn->prepare("
                    UPDATE food_donations 
                    SET assigned_to_user_id = ?, assigned_at = NOW(), assigned_by = ?, assignment_notes = ?, status = 'reserved'
                    WHERE id = ?
                ");
                $stmt->bind_param('iisi', $assigned_to_user_id, $_SESSION['user_id'], $assignment_notes, $donation_id);
                
                if ($stmt->execute()) {
                    // Check if reservation already exists
                    $stmt2 = $conn->prepare("SELECT id FROM food_donation_reservations WHERE donation_id = ? AND requester_id = ?");
                    $stmt2->bind_param('ii', $donation_id, $assigned_to_user_id);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    $existing_reservation = $result2->fetch_assoc();
                    $stmt2->close();
                    
                    $contact_info = $assigned_user['email'] . (!empty($assigned_user['phone_number']) ? ' / ' . $assigned_user['phone_number'] : '');
                    $message = 'Assigned by team officer' . ($assignment_notes ? ': ' . $assignment_notes : '');
                    
                    if ($existing_reservation) {
                        // Update existing reservation
                        $stmt3 = $conn->prepare("
                            UPDATE food_donation_reservations 
                            SET message = ?, contact_info = ?, status = 'approved', reserved_at = NOW()
                            WHERE id = ?
                        ");
                        $stmt3->bind_param('ssi', $message, $contact_info, $existing_reservation['id']);
                        $stmt3->execute();
                        $stmt3->close();
                    } else {
                        // Create new reservation record for tracking
                        $stmt3 = $conn->prepare("
                            INSERT INTO food_donation_reservations (donation_id, requester_id, message, contact_info, status, reserved_at)
                            VALUES (?, ?, ?, ?, 'approved', NOW())
                        ");
                        $stmt3->bind_param('iiss', $donation_id, $assigned_to_user_id, $message, $contact_info);
                        $stmt3->execute();
                        $stmt3->close();
                    }
                    
                    // Send email notification to assigned resident
                    $emailNotifier = new DonationEmailNotifications();
                    $emailSent = $emailNotifier->sendDonationAssigned($donation, $assigned_user['email'], $assigned_user['full_name'], $assignment_notes);
                    
                    $message = 'Donation assigned successfully to ' . $assigned_user['full_name'] . '!';
                    if ($emailSent) {
                        $message .= ' Email notification sent to resident.';
                    } else {
                        $message .= ' Note: Email notification could not be sent.';
                    }
                    
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to assign donation.']);
                }
                $stmt->close();
                break;
                
            case 'delete':
                // First get donation and donor details for email
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email 
                    FROM food_donations fd 
                    JOIN user_accounts ua ON fd.user_id = ua.user_id 
                    WHERE fd.id = ?
                ");
                $stmt->bind_param('i', $donation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $donation = $result->fetch_assoc();
                $stmt->close();
                
                // Delete associated images first
                if ($donation['images']) {
                    $images = json_decode($donation['images'], true);
                    foreach ($images as $image) {
                        $file_path = '../' . $image;
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                }
                
                // Delete from database
                $stmt = $conn->prepare("DELETE FROM food_donations WHERE id = ?");
                $stmt->bind_param('i', $donation_id);
                
                if ($stmt->execute()) {
                    // Send deletion email notification
                    $emailNotifier = new DonationEmailNotifications();
                    $emailSent = $emailNotifier->sendDonationDeleted($donation, $donation['email'], $donation['full_name'], 'Removed by team officer');
                    
                    $message = 'Donation deleted successfully!';
                    if ($emailSent) {
                        $message .= ' Email notification sent to donor.';
                    } else {
                        $message .= ' Note: Email notification could not be sent.';
                    }
                    
                    echo json_encode(['success' => true, 'message' => $message]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete donation.']);
                }
                $stmt->close();
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$food_type = isset($_GET['food_type']) ? $_GET['food_type'] : '';
$expired_filter = isset($_GET['expired']) ? $_GET['expired'] : '';

// Build query conditions
$where_conditions = [];
$params = [];
$param_types = '';

if (!empty($status_filter)) {
    $where_conditions[] = "fd.approval_status = ?";
    $params[] = $status_filter;
    $param_types .= 's';
}

if (!empty($search)) {
    $where_conditions[] = "(fd.title LIKE ? OR fd.description LIKE ? OR ua.full_name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $param_types .= 'sss';
}

if (!empty($food_type)) {
    $where_conditions[] = "fd.food_type = ?";
    $params[] = $food_type;
    $param_types .= 's';
}

if ($expired_filter === 'yes') {
    $where_conditions[] = "fd.expiration_date IS NOT NULL AND fd.expiration_date < CURDATE()";
} elseif ($expired_filter === 'no') {
    $where_conditions[] = "(fd.expiration_date IS NULL OR fd.expiration_date >= CURDATE())";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get donations
$query = "
    SELECT fd.*, ua.full_name, ua.email,
           admin.full_name as approved_by_name,
           assigned_ua.full_name as assigned_to_name,
           assigned_ua.email as assigned_to_email,
           CASE 
               WHEN fd.expiration_date IS NOT NULL AND fd.expiration_date < CURDATE() THEN 1 
               ELSE 0 
           END as is_expired
    FROM food_donations fd 
    JOIN user_accounts ua ON fd.user_id = ua.user_id 
    LEFT JOIN user_accounts admin ON fd.approved_by = admin.user_id
    LEFT JOIN user_accounts assigned_ua ON fd.assigned_to_user_id = assigned_ua.user_id
    $where_clause
    ORDER BY is_expired DESC, fd.created_at DESC
";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($param_types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$donations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-basket"></i> Food Donation Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Food Donation Management</li>
                <li class="breadcrumb-item active">All Donations</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Filters -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       value="<?php echo htmlspecialchars($search); ?>" 
                                       placeholder="Search donations...">
                            </div>
                            <div class="col-md-2">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="food_type" class="form-label">Food Type</label>
                                <select class="form-select" id="food_type" name="food_type">
                                    <option value="">All Types</option>
                                    <option value="cooked" <?php echo $food_type === 'cooked' ? 'selected' : ''; ?>>Cooked Food</option>
                                    <option value="raw" <?php echo $food_type === 'raw' ? 'selected' : ''; ?>>Raw Ingredients</option>
                                    <option value="packaged" <?php echo $food_type === 'packaged' ? 'selected' : ''; ?>>Packaged Food</option>
                                    <option value="beverages" <?php echo $food_type === 'beverages' ? 'selected' : ''; ?>>Beverages</option>
                                    <option value="other" <?php echo $food_type === 'other' ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label for="expired" class="form-label">Expiration</label>
                                <select class="form-select" id="expired" name="expired">
                                    <option value="">All</option>
                                    <option value="yes" <?php echo $expired_filter === 'yes' ? 'selected' : ''; ?>>Expired</option>
                                    <option value="no" <?php echo $expired_filter === 'no' ? 'selected' : ''; ?>>Not Expired</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Filter
                                    </button>
                                    <a href="donation-management.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Donations List -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul"></i> All Food Donations
                        </h5>
                        <span class="badge bg-primary"><?php echo count($donations); ?> donations</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($donations)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-basket text-muted" style="font-size: 3rem;"></i>
                                <h4 class="mt-3">No Donations Found</h4>
                                <p class="text-muted">No donations match your current filters.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Donation Details</th>
                                            <th>Donor</th>
                                            <th>Status</th>
                                            <th>Posted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($donations as $donation): 
                                            $is_expired = isset($donation['is_expired']) && $donation['is_expired'] == 1;
                                            $row_class = $is_expired ? 'table-secondary opacity-75' : '';
                                        ?>
                                            <tr class="<?php echo $row_class; ?>">
                                                <td>
                                                    <div class="d-flex">
                                                        <?php if ($donation['images']): 
                                                            $images = json_decode($donation['images'], true);
                                                            if (!empty($images)): ?>
                                                                <img src="../<?php echo htmlspecialchars($images[0]); ?>" 
                                                                     class="rounded me-3" 
                                                                     style="width: 60px; height: 60px; object-fit: cover;"
                                                                     alt="Food image">
                                                            <?php endif;
                                                        endif; ?>
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <?php echo htmlspecialchars($donation['title']); ?>
                                                                <?php if ($is_expired): ?>
                                                                    <span class="badge bg-danger ms-2">
                                                                        <i class="bi bi-exclamation-triangle"></i> Expired
                                                                    </span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <p class="text-muted mb-1 small">
                                                                <strong>Type:</strong> <?php echo ucfirst($donation['food_type']); ?><br>
                                                                <strong>Quantity:</strong> <?php echo htmlspecialchars($donation['quantity']); ?><br>
                                                                <?php if ($donation['expiration_date']): ?>
                                                                    <strong>Expires:</strong> 
                                                                    <span class="<?php echo $is_expired ? 'text-danger fw-bold' : ''; ?>">
                                                                        <?php echo date('M d, Y', strtotime($donation['expiration_date'])); ?>
                                                                        <?php if ($is_expired): ?>
                                                                            <i class="bi bi-x-circle"></i>
                                                                        <?php endif; ?>
                                                                    </span>
                                                                <?php endif; ?>
                                                            </p>
                                                            <p class="mb-0 small"><?php echo htmlspecialchars(substr($donation['description'], 0, 100)); ?><?php echo strlen($donation['description']) > 100 ? '...' : ''; ?></p>
                                                        </div>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($donation['full_name']); ?></strong><br>
                                                        <small class="text-muted"><?php echo htmlspecialchars($donation['email']); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $status_class = '';
                                                    $status_icon = '';
                                                    $status_text = ucfirst($donation['approval_status']);
                                                    
                                                    switch ($donation['approval_status']) {
                                                        case 'approved':
                                                            $status_class = 'bg-success';
                                                            $status_icon = 'bi-check-circle';
                                                            break;
                                                        case 'pending':
                                                            $status_class = 'bg-warning';
                                                            $status_icon = 'bi-clock';
                                                            break;
                                                        case 'rejected':
                                                            $status_class = 'bg-danger';
                                                            $status_icon = 'bi-x-circle';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <i class="bi <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                                    </span>
                                                    <?php if ($donation['approval_status'] === 'rejected' && $donation['rejection_reason']): ?>
                                                        <br><small class="text-muted">Reason: <?php echo htmlspecialchars($donation['rejection_reason']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if ($donation['approved_by_name']): ?>
                                                        <br><small class="text-muted">By: <?php echo htmlspecialchars($donation['approved_by_name']); ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($donation['assigned_to_name'])): ?>
                                                        <br><small class="text-success">
                                                            <i class="bi bi-person-check"></i> Assigned to: <?php echo htmlspecialchars($donation['assigned_to_name']); ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($donation['created_at'])); ?><br>
                                                        <?php echo date('h:i A', strtotime($donation['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if ($donation['approval_status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-success btn-sm" 
                                                                    onclick="approveDonation(<?php echo $donation['id']; ?>)">
                                                                <i class="bi bi-check"></i>
                                                            </button>
                                                            <button type="button" class="btn btn-danger btn-sm" 
                                                                    onclick="rejectDonation(<?php echo $donation['id']; ?>)">
                                                                <i class="bi bi-x"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($donation['approval_status'] === 'approved' && empty($donation['assigned_to_name'])): ?>
                                                            <button type="button" class="btn btn-primary btn-sm" 
                                                                    onclick="assignDonation(<?php echo $donation['id']; ?>)">
                                                                <i class="bi bi-person-plus"></i> Assign
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-info btn-sm" 
                                                                onclick="viewDetails(<?php echo $donation['id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm" 
                                                                onclick="deleteDonation(<?php echo $donation['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Rejection Modal -->
<div class="modal fade" id="rejectionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Reject Food Donation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="rejectionForm">
                    <input type="hidden" id="reject_donation_id">
                    <div class="mb-3">
                        <label for="rejection_reason" class="form-label">Reason for Rejection *</label>
                        <textarea class="form-control" id="rejection_reason" rows="4" required 
                                  placeholder="Please provide a reason for rejecting this food donation..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" onclick="confirmRejection()">Reject Donation</button>
            </div>
        </div>
    </div>
</div>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Donation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="donationDetails">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Assign Donation Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person-plus"></i> Assign Donation to Resident
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="assignForm">
                    <input type="hidden" id="assign_donation_id">
                    
                    <div class="mb-3">
                        <label for="assigned_to_user_id" class="form-label">Select Resident *</label>
                        <select class="form-select" id="assigned_to_user_id" name="assigned_to_user_id" required>
                            <option value="">Loading residents...</option>
                        </select>
                        <small class="text-muted">Only residents from your area are shown.</small>
                    </div>
                    
                    <div class="mb-3">
                        <label for="assignment_notes" class="form-label">Assignment Notes (Optional)</label>
                        <textarea class="form-control" id="assignment_notes" name="assignment_notes" rows="3" 
                                  placeholder="Add any notes about this assignment..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmAssignment()">
                    <span class="loading-spinner spinner-border spinner-border-sm me-2"></span>
                    Assign Donation
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function approveDonation(donationId) {
    if (confirm('Are you sure you want to approve this donation?')) {
        const formData = new FormData();
        formData.append('action', 'approve');
        formData.append('donation_id', donationId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                location.reload();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while approving the donation.', 'error');
        });
    }
}

function rejectDonation(donationId) {
    document.getElementById('reject_donation_id').value = donationId;
    document.getElementById('rejection_reason').value = '';
    new bootstrap.Modal(document.getElementById('rejectionModal')).show();
}

function confirmRejection() {
    const donationId = document.getElementById('reject_donation_id').value;
    const rejectionReason = document.getElementById('rejection_reason').value;
    
    if (!rejectionReason.trim()) {
        showNotification('Please provide a reason for rejection.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reject');
    formData.append('donation_id', donationId);
    formData.append('rejection_reason', rejectionReason);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('rejectionModal')).hide();
            location.reload();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while rejecting the donation.', 'error');
    });
}

function deleteDonation(donationId) {
    if (confirm('Are you sure you want to delete this donation? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('donation_id', donationId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                location.reload();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while deleting the donation.', 'error');
        });
    }
}

function viewDetails(donationId) {
    // Show loading state
    document.getElementById('donationDetails').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading donation details...</p>
        </div>
    `;
    
    // Show modal first
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
    
    // Fetch donation details
    const formData = new FormData();
    formData.append('action', 'get_details');
    formData.append('donation_id', donationId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayDonationDetails(data.donation);
        } else {
            showNotification(data.message, 'error');
            document.getElementById('donationDetails').innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Error Loading Details</h5>
                    <p class="text-muted">${data.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        showNotification('An error occurred while loading donation details.', 'error');
        document.getElementById('donationDetails').innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Error Loading Details</h5>
                <p class="text-muted">An error occurred while loading the donation details.</p>
            </div>
        `;
    });
}

function displayDonationDetails(donation) {
    const images = donation.images ? JSON.parse(donation.images) : [];
    const imagesHtml = images.length > 0 ? 
        images.map(img => `<img src="../${img}" class="img-fluid rounded mb-2" style="max-height: 200px; object-fit: cover;" alt="Food image">`).join('') :
        '<p class="text-muted">No images available</p>';
    
    const statusClass = donation.approval_status === 'approved' ? 'success' : 
                       donation.approval_status === 'pending' ? 'warning' : 'danger';
    const statusIcon = donation.approval_status === 'approved' ? 'check-circle' : 
                      donation.approval_status === 'pending' ? 'clock' : 'x-circle';
    
    document.getElementById('donationDetails').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary border-bottom pb-2 mb-3">Basic Information</h6>
                <p><strong>Title:</strong> ${donation.title}</p>
                <p><strong>Description:</strong> ${donation.description}</p>
                <p><strong>Food Type:</strong> ${donation.food_type.charAt(0).toUpperCase() + donation.food_type.slice(1)}</p>
                <p><strong>Quantity:</strong> ${donation.quantity}</p>
                ${donation.expiration_date ? `<p><strong>Expiration Date:</strong> ${new Date(donation.expiration_date).toLocaleDateString()}</p>` : ''}
            </div>
            <div class="col-md-6">
                <h6 class="text-primary border-bottom pb-2 mb-3">Location & Contact</h6>
                <p><strong>Address:</strong> ${donation.location_address}</p>
                ${donation.location_lat && donation.location_lng ? `<p><strong>Coordinates:</strong> ${donation.location_lat}, ${donation.location_lng}</p>` : ''}
                <p><strong>Contact Method:</strong> ${donation.contact_method.charAt(0).toUpperCase() + donation.contact_method.slice(1)}</p>
                <p><strong>Contact Info:</strong> ${donation.contact_info}</p>
                ${donation.pickup_time_start && donation.pickup_time_end ? 
                    `<p><strong>Available:</strong> ${donation.pickup_time_start} - ${donation.pickup_time_end}</p>` : ''}
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="text-primary border-bottom pb-2 mb-3">Additional Information</h6>
                ${donation.dietary_info ? `<p><strong>Dietary Info:</strong> ${donation.dietary_info}</p>` : ''}
                ${donation.allergens ? `<p><strong>Allergens:</strong> ${donation.allergens}</p>` : ''}
                ${donation.storage_instructions ? `<p><strong>Storage Instructions:</strong> ${donation.storage_instructions}</p>` : ''}
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6 class="text-primary border-bottom pb-2 mb-3">Images</h6>
                <div class="d-flex flex-wrap gap-2">
                    ${imagesHtml}
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-md-6">
                <h6 class="text-primary border-bottom pb-2 mb-3">Donor Information</h6>
                <p><strong>Name:</strong> ${donation.full_name}</p>
                <p><strong>Email:</strong> ${donation.email}</p>
                ${donation.phone_number ? `<p><strong>Phone:</strong> ${donation.phone_number}</p>` : ''}
            </div>
            <div class="col-md-6">
                <h6 class="text-primary border-bottom pb-2 mb-3">Status Information</h6>
                <p><strong>Status:</strong> <span class="badge bg-${statusClass}"><i class="bi bi-${statusIcon}"></i> ${donation.approval_status.charAt(0).toUpperCase() + donation.approval_status.slice(1)}</span></p>
                <p><strong>Posted:</strong> ${new Date(donation.created_at).toLocaleString()}</p>
                <p><strong>Views:</strong> ${donation.views_count}</p>
                ${donation.approved_by_name ? `<p><strong>Approved by:</strong> ${donation.approved_by_name}</p>` : ''}
                ${donation.rejection_reason ? `<p><strong>Rejection Reason:</strong> ${donation.rejection_reason}</p>` : ''}
                ${donation.assigned_to_name ? `<p><strong>Assigned to:</strong> <span class="text-success"><i class="bi bi-person-check"></i> ${donation.assigned_to_name}</span></p>` : ''}
                ${donation.assigned_to_email ? `<p><strong>Assigned Email:</strong> ${donation.assigned_to_email}</p>` : ''}
            </div>
        </div>
    `;
}

function assignDonation(donationId) {
    document.getElementById('assign_donation_id').value = donationId;
    document.getElementById('assigned_to_user_id').innerHTML = '<option value="">Loading residents...</option>';
    document.getElementById('assignment_notes').value = '';
    
    // Load residents (exclude the donor by passing donation_id)
    const formData = new FormData();
    formData.append('action', 'get_residents');
    formData.append('donation_id', donationId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.residents && data.residents.length > 0) {
            const select = document.getElementById('assigned_to_user_id');
            select.innerHTML = '<option value="">Select a resident...</option>';
            data.residents.forEach(resident => {
                const option = document.createElement('option');
                option.value = resident.user_id;
                const statusBadge = resident.status === 'approved' ? '' : ` [${resident.status}]`;
                option.textContent = `${resident.full_name} (${resident.email})${statusBadge}`;
                select.appendChild(option);
            });
            // Clear any previous error messages
            if (data.message) {
                // Don't show warning if we have residents
            }
        } else {
            document.getElementById('assigned_to_user_id').innerHTML = 
                '<option value="">No residents available</option>';
            if (data.message) {
                showNotification(data.message, 'warning');
            } else {
                showNotification('No residents found in your area. Please check that residents have the same address set in their profiles.', 'warning');
            }
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while loading residents.', 'error');
        document.getElementById('assigned_to_user_id').innerHTML = 
            '<option value="">Error loading residents</option>';
    });
    
    new bootstrap.Modal(document.getElementById('assignModal')).show();
}

function confirmAssignment() {
    const donationId = document.getElementById('assign_donation_id').value;
    const assignedToUserId = document.getElementById('assigned_to_user_id').value;
    const assignmentNotes = document.getElementById('assignment_notes').value;
    
    if (!assignedToUserId) {
        showNotification('Please select a resident to assign this donation to.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'assign_donation');
    formData.append('donation_id', donationId);
    formData.append('assigned_to_user_id', assignedToUserId);
    formData.append('assignment_notes', assignmentNotes);
    
    const submitBtn = document.querySelector('#assignModal .btn-primary');
    const spinner = submitBtn.querySelector('.loading-spinner');
    
    submitBtn.disabled = true;
    spinner.classList.add('show');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('assignModal')).hide();
            location.reload();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while assigning the donation.', 'error');
    })
    .finally(() => {
        submitBtn.disabled = false;
        spinner.classList.remove('show');
    });
}

function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}
</script>

<?php include 'footer.php'; ?>

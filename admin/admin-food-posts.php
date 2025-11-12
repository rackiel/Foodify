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
        if ($action === 'approve_donation') {
            $donation_id = intval($_POST['donation_id']);
            
            // Check if approval_status column exists
            $check_col = $conn->query("SHOW COLUMNS FROM food_donations LIKE 'approval_status'");
            if ($check_col && $check_col->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE food_donations SET approval_status = 'approved' WHERE id = ?");
                $stmt->bind_param('i', $donation_id);
            } else {
                $stmt = $conn->prepare("UPDATE food_donations SET status = 'available' WHERE id = ?");
                $stmt->bind_param('i', $donation_id);
            }
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Donation approved successfully!';
            }
            $stmt->close();
            
        } elseif ($action === 'reject_donation') {
            $donation_id = intval($_POST['donation_id']);
            $reason = trim($_POST['reason'] ?? '');
            
            $check_col = $conn->query("SHOW COLUMNS FROM food_donations LIKE 'approval_status'");
            if ($check_col && $check_col->num_rows > 0) {
                $stmt = $conn->prepare("UPDATE food_donations SET approval_status = 'rejected' WHERE id = ?");
            } else {
                $stmt = $conn->prepare("UPDATE food_donations SET status = 'expired' WHERE id = ?");
            }
            $stmt->bind_param('i', $donation_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Donation rejected successfully!';
            }
            $stmt->close();
            
        } elseif ($action === 'delete_donation') {
            $donation_id = intval($_POST['donation_id']);
            
            $stmt = $conn->prepare("DELETE FROM food_donations WHERE id = ?");
            $stmt->bind_param('i', $donation_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Donation deleted successfully!';
            }
            $stmt->close();
            
        } elseif ($action === 'update_status') {
            $donation_id = intval($_POST['donation_id']);
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE food_donations SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $donation_id);
            
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
$stats = [
    'total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0,
    'available' => 0, 'claimed' => 0, 'expired' => 0, 'today' => 0
];

try {
    // Overall stats
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'available' THEN 1 END) as available,
            COUNT(CASE WHEN status = 'claimed' THEN 1 END) as claimed,
            COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today
        FROM food_donations
    ");
    if ($result) {
        $data = $result->fetch_assoc();
        $stats['total'] = $data['total'];
        $stats['available'] = $data['available'];
        $stats['claimed'] = $data['claimed'];
        $stats['expired'] = $data['expired'];
        $stats['today'] = $data['today'];
    }
    
    // Check for approval_status column
    $check_col = $conn->query("SHOW COLUMNS FROM food_donations LIKE 'approval_status'");
    if ($check_col && $check_col->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                COUNT(CASE WHEN approval_status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN approval_status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN approval_status = 'rejected' THEN 1 END) as rejected
            FROM food_donations
        ");
        if ($result) {
            $approval_data = $result->fetch_assoc();
            $stats['pending'] = $approval_data['pending'];
            $stats['approved'] = $approval_data['approved'];
            $stats['rejected'] = $approval_data['rejected'];
        }
    }
    
    // Get filter
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Get all donations
    if ($status_filter && $status_filter !== 'all') {
        $stmt = $conn->prepare("
            SELECT fd.*, ua.full_name, ua.profile_img, ua.email
            FROM food_donations fd
            JOIN user_accounts ua ON fd.user_id = ua.user_id
            WHERE fd.status = ?
            ORDER BY fd.created_at DESC
        ");
        $stmt->bind_param('s', $status_filter);
        $stmt->execute();
        $donations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query("
            SELECT fd.*, ua.full_name, ua.profile_img, ua.email
            FROM food_donations fd
            JOIN user_accounts ua ON fd.user_id = ua.user_id
            ORDER BY fd.created_at DESC
        ");
        $donations = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $donations = [];
}

include 'header.php';
?>

<body>
<?php include 'topbar.php'; ?>
<?php include 'sidebar.php'; ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-basket"></i> Food Donation Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Food Posts</li>
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
                                <h6 class="text-muted mb-2">Total Donations</h6>
                                <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                            </div>
                            <div class="stat-icon bg-primary">
                                <i class="bi bi-basket"></i>
                            </div>
                        </div>
                        <small class="text-muted"><?= $stats['today'] ?> today</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Available</h6>
                                <h3 class="mb-0"><?= number_format($stats['available']) ?></h3>
                            </div>
                            <div class="stat-icon bg-success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                        </div>
                        <small class="text-muted">Active donations</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Claimed</h6>
                                <h3 class="mb-0"><?= number_format($stats['claimed']) ?></h3>
                            </div>
                            <div class="stat-icon bg-info">
                                <i class="bi bi-bag-check"></i>
                            </div>
                        </div>
                        <small class="text-muted">Successfully shared</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Pending Approval</h6>
                                <h3 class="mb-0"><?= number_format($stats['pending']) ?></h3>
                            </div>
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-clock"></i>
                            </div>
                        </div>
                        <small class="text-muted">Need review</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Donations Table -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Food Donations</h5>
                    <button class="btn btn-success" onclick="exportDonations()">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>
                </div>

                <!-- Filters -->
                <div class="mb-3">
                    <div class="btn-group me-2" role="group">
                        <a href="admin-food-posts.php" class="btn btn-sm btn-outline-primary <?= $status_filter === '' ? 'active' : '' ?>">
                            All (<?= $stats['total'] ?>)
                        </a>
                        <a href="admin-food-posts.php?status=available" class="btn btn-sm btn-outline-success <?= $status_filter === 'available' ? 'active' : '' ?>">
                            Available (<?= $stats['available'] ?>)
                        </a>
                        <a href="admin-food-posts.php?status=claimed" class="btn btn-sm btn-outline-info <?= $status_filter === 'claimed' ? 'active' : '' ?>">
                            Claimed (<?= $stats['claimed'] ?>)
                        </a>
                        <a href="admin-food-posts.php?status=expired" class="btn btn-sm btn-outline-secondary <?= $status_filter === 'expired' ? 'active' : '' ?>">
                            Expired (<?= $stats['expired'] ?>)
                        </a>
                        <?php if ($stats['pending'] > 0): ?>
                            <a href="admin-food-posts.php?status=pending" class="btn btn-sm btn-outline-warning <?= $status_filter === 'pending' ? 'active' : '' ?>">
                                Pending (<?= $stats['pending'] ?>)
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Search -->
                <div class="mb-3">
                    <input type="text" class="form-control" id="searchInput" 
                           placeholder="Search donations by title, donor, or food type..." 
                           onkeyup="searchDonations()">
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Donation</th>
                                <th>Donor</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Views</th>
                                <th>Posted</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($donations)): ?>
                                <?php foreach ($donations as $donation): ?>
                                    <tr class="donation-row">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php 
                                                $has_image = false;
                                                if (!empty($donation['images'])): 
                                                    $images = json_decode($donation['images'], true);
                                                    if (!empty($images) && is_array($images)): 
                                                        $has_image = true;
                                                ?>
                                                    <img src="../<?= htmlspecialchars($images[0]) ?>" 
                                                         class="rounded me-2" width="50" height="50" 
                                                         style="object-fit: cover;" alt="Food">
                                                <?php 
                                                    endif;
                                                endif;
                                                if (!$has_image): 
                                                ?>
                                                    <div class="bg-secondary rounded me-2 d-flex align-items-center justify-content-center" 
                                                         style="width: 50px; height: 50px;">
                                                        <i class="bi bi-image text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($donation['title']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($donation['description'] ? substr($donation['description'], 0, 40) . '...' : 'No description') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <img src="<?= !empty($donation['profile_img']) ? '../uploads/profile_picture/' . $donation['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                     class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                <div>
                                                    <strong><?= htmlspecialchars($donation['full_name']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars($donation['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= ucfirst($donation['food_type']) ?></span>
                                        </td>
                                        <td>
                                            <select class="form-select form-select-sm" 
                                                    onchange="updateStatus(<?= $donation['id'] ?>, this.value)">
                                                <option value="available" <?= $donation['status'] === 'available' ? 'selected' : '' ?>>Available</option>
                                                <option value="reserved" <?= $donation['status'] === 'reserved' ? 'selected' : '' ?>>Reserved</option>
                                                <option value="claimed" <?= $donation['status'] === 'claimed' ? 'selected' : '' ?>>Claimed</option>
                                                <option value="expired" <?= $donation['status'] === 'expired' ? 'selected' : '' ?>>Expired</option>
                                            </select>
                                        </td>
                                        <td><?= number_format($donation['views_count'] ?? 0) ?></td>
                                        <td>
                                            <small><?= date('M j, Y', strtotime($donation['created_at'])) ?></small>
                                            <br><small class="text-muted"><?= date('g:i A', strtotime($donation['created_at'])) ?></small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-info" 
                                                        onclick='viewDonation(<?= json_encode($donation, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <?php if (isset($donation['approval_status']) && $donation['approval_status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" 
                                                            onclick="approveDonation(<?= $donation['id'] ?>)" 
                                                            title="Approve">
                                                        <i class="bi bi-check-circle"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" 
                                                            onclick="rejectDonation(<?= $donation['id'] ?>)" 
                                                            title="Reject">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="deleteDonation(<?= $donation['id'] ?>)" 
                                                        title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No donations found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- View Donation Modal -->
<div class="modal fade" id="viewDonationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-basket"></i> Donation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="donationDetailsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<style>
.stat-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stat-card .card-body {
    padding: 24px 20px 20px 20px;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.stat-icon {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    color: white;
}

.donation-images {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.donation-images img {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
    cursor: pointer;
}

.donation-images img:hover {
    opacity: 0.8;
}
</style>

<script>
// View Donation Details
function viewDonation(donation) {
    let images = '';
    if (donation.images) {
        try {
            const imageArray = JSON.parse(donation.images);
            if (imageArray && imageArray.length > 0) {
                images = '<div class="donation-images mb-3">';
                imageArray.forEach(img => {
                    images += `<img src="../${img}" alt="Food" onclick="window.open('../${img}', '_blank')">`;
                });
                images += '</div>';
            }
        } catch (e) {}
    }
    
    let attachments = '';
    if (donation.attachments) {
        try {
            const attachArray = JSON.parse(donation.attachments);
            if (attachArray && attachArray.length > 0) {
                attachments = '<div class="mb-3"><strong>Attachments:</strong><ul class="mt-2">';
                attachArray.forEach(file => {
                    const fileName = file.split('/').pop();
                    attachments += `<li><a href="../${file}" target="_blank">${fileName}</a></li>`;
                });
                attachments += '</ul></div>';
            }
        } catch (e) {}
    }
    
    const content = `
        <div class="row">
            <div class="col-12 mb-3">
                ${images}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Donation ID:</strong> #${donation.id}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Posted:</strong> ${new Date(donation.created_at).toLocaleString()}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Food Type:</strong> <span class="badge bg-info">${donation.food_type}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Status:</strong> <span class="badge bg-${
                    donation.status === 'available' ? 'success' :
                    (donation.status === 'claimed' ? 'info' : 'secondary')
                }">${donation.status}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Views:</strong> ${donation.views_count || 0}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Expiry Date:</strong> ${donation.expiry_date ? new Date(donation.expiry_date).toLocaleDateString() : 'Not specified'}
            </div>
            <div class="col-12 mb-3">
                <strong>Donor:</strong><br>
                <div class="d-flex align-items-center mt-2">
                    <img src="${donation.profile_img ? '../uploads/profile_picture/' + donation.profile_img : '../uploads/profile_picture/no_image.png'}" 
                         class="rounded-circle me-2" width="40" height="40">
                    <div>
                        <div><strong>${donation.full_name}</strong></div>
                        <small class="text-muted">${donation.email}</small>
                    </div>
                </div>
            </div>
            <div class="col-12 mb-3">
                <strong>Title:</strong>
                <div class="alert alert-light mt-2">${donation.title}</div>
            </div>
            <div class="col-12 mb-3">
                <strong>Description:</strong>
                <div class="alert alert-light mt-2">${donation.description || 'No description provided'}</div>
            </div>
            ${donation.location_address ? `
            <div class="col-12 mb-3">
                <strong>Pickup Location:</strong>
                <div class="alert alert-light mt-2">
                    <i class="bi bi-geo-alt"></i> ${donation.location_address}
                </div>
            </div>
            ` : ''}
            ${attachments}
            <div class="col-12">
                <hr>
                <div class="d-grid gap-2">
                    ${donation.approval_status === 'pending' ? `
                    <button class="btn btn-success" onclick="approveDonation(${donation.id})">
                        <i class="bi bi-check-circle"></i> Approve Donation
                    </button>
                    <button class="btn btn-warning" onclick="rejectDonation(${donation.id})">
                        <i class="bi bi-x-circle"></i> Reject Donation
                    </button>
                    ` : ''}
                    <button class="btn btn-outline-danger" onclick="deleteDonation(${donation.id})">
                        <i class="bi bi-trash"></i> Delete Donation
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('donationDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('viewDonationModal')).show();
}

// Approve Donation
function approveDonation(donationId) {
    if (!confirm('Approve this food donation?')) return;
    
    const formData = new FormData();
    formData.append('action', 'approve_donation');
    formData.append('donation_id', donationId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to approve', 'error');
        }
    });
}

// Reject Donation
function rejectDonation(donationId) {
    const reason = prompt('Enter rejection reason (optional):');
    if (reason === null) return; // User cancelled
    
    const formData = new FormData();
    formData.append('action', 'reject_donation');
    formData.append('donation_id', donationId);
    formData.append('reason', reason || '');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to reject', 'error');
        }
    });
}

// Delete Donation
function deleteDonation(donationId) {
    if (!confirm('Are you sure you want to delete this donation? This action cannot be undone!')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_donation');
    formData.append('donation_id', donationId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to delete', 'error');
        }
    });
}

// Update Status
function updateStatus(donationId, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('donation_id', donationId);
    formData.append('status', status);
    
    fetch(window.location.href, {
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

// Search Donations
function searchDonations() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.donation-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// Export Donations
function exportDonations() {
    const data = <?= json_encode($donations) ?>;
    
    if (data.length === 0) {
        showNotification('No donations to export', 'warning');
        return;
    }
    
    const headers = ['ID', 'Title', 'Donor', 'Email', 'Food Type', 'Status', 'Views', 'Posted'];
    let csv = headers.join(',') + '\n';
    
    data.forEach(row => {
        const values = [
            row.id,
            `"${row.title}"`,
            `"${row.full_name}"`,
            `"${row.email}"`,
            row.food_type,
            row.status,
            row.views_count || 0,
            row.created_at
        ];
        csv += values.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'food_donations_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Donations exported successfully!', 'success');
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


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
        if ($action === 'restore_donation') {
            $donation_id = intval($_POST['donation_id']);
            
            $stmt = $conn->prepare("UPDATE food_donations SET status = 'available' WHERE id = ?");
            $stmt->bind_param('i', $donation_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Donation restored successfully!';
            }
            $stmt->close();
            
        } elseif ($action === 'delete_donation') {
            $donation_id = intval($_POST['donation_id']);
            
            $stmt = $conn->prepare("DELETE FROM food_donations WHERE id = ?");
            $stmt->bind_param('i', $donation_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Donation permanently deleted!';
            }
            $stmt->close();
            
        } elseif ($action === 'bulk_delete') {
            $days = intval($_POST['days']);
            
            $stmt = $conn->prepare("
                DELETE FROM food_donations 
                WHERE status = 'expired' 
                AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
            ");
            $stmt->bind_param('i', $days);
            
            if ($stmt->execute()) {
                $affected = $stmt->affected_rows;
                $response['success'] = true;
                $response['message'] = "Deleted $affected expired donations older than $days days";
            }
            $stmt->close();
            
        } elseif ($action === 'auto_expire') {
            // Check if expiry_date column exists
            $check_col = $conn->query("SHOW COLUMNS FROM food_donations LIKE 'expiry_date'");
            if ($check_col && $check_col->num_rows > 0) {
                // Auto-expire donations based on expiry_date
                $stmt = $conn->query("
                    UPDATE food_donations 
                    SET status = 'expired' 
                    WHERE expiry_date < NOW() 
                    AND status = 'available'
                ");
                
                if ($stmt) {
                    $affected = $conn->affected_rows;
                    $response['success'] = true;
                    $response['message'] = "Auto-expired $affected donations";
                }
            } else {
                $response['message'] = 'Expiry date column does not exist in the database';
            }
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Get statistics
$stats = [
    'expired_total' => 0,
    'expired_today' => 0,
    'expired_week' => 0,
    'expired_month' => 0,
    'expiring_soon' => 0
];

try {
    // Check if expiry_date column exists
    $has_expiry_date = false;
    $check_col = $conn->query("SHOW COLUMNS FROM food_donations LIKE 'expiry_date'");
    if ($check_col && $check_col->num_rows > 0) {
        $has_expiry_date = true;
    }
    
    // Expired donations stats
    $result = $conn->query("
        SELECT 
            COUNT(*) as expired_total,
            COUNT(CASE WHEN DATE(updated_at) = CURDATE() THEN 1 END) as expired_today,
            COUNT(CASE WHEN DATE(updated_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as expired_week,
            COUNT(CASE WHEN DATE(updated_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as expired_month
        FROM food_donations
        WHERE status = 'expired'
    ");
    if ($result) {
        $stats = array_merge($stats, $result->fetch_assoc());
    }
    
    // Expiring soon (next 24 hours) - only if expiry_date column exists
    if ($has_expiry_date) {
        $result = $conn->query("
            SELECT COUNT(*) as expiring_soon
            FROM food_donations
            WHERE status = 'available'
            AND expiry_date IS NOT NULL
            AND expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
        ");
        if ($result) {
            $expiring_data = $result->fetch_assoc();
            $stats['expiring_soon'] = $expiring_data['expiring_soon'];
        }
    }
    
    // Get expired donations
    $result = $conn->query("
        SELECT fd.*, ua.full_name, ua.profile_img, ua.email
        FROM food_donations fd
        JOIN user_accounts ua ON fd.user_id = ua.user_id
        WHERE fd.status = 'expired'
        ORDER BY fd.updated_at DESC
    ");
    $expired_donations = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    
    // Get expiring soon donations - only if expiry_date column exists
    if ($has_expiry_date) {
        $result = $conn->query("
            SELECT fd.*, ua.full_name, ua.profile_img, ua.email,
                   TIMESTAMPDIFF(HOUR, NOW(), fd.expiry_date) as hours_remaining
            FROM food_donations fd
            JOIN user_accounts ua ON fd.user_id = ua.user_id
            WHERE fd.status = 'available'
            AND fd.expiry_date IS NOT NULL
            AND fd.expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)
            ORDER BY fd.expiry_date ASC
        ");
        $expiring_soon = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $expiring_soon = [];
    }
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $expired_donations = [];
    $expiring_soon = [];
}

include 'header.php';
?>

<body>
<?php include 'topbar.php'; ?>
<?php include 'sidebar.php'; ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-exclamation-triangle"></i> Expired & Expiring Donations</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Expired Posts</li>
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
                                <h6 class="text-muted mb-2">Total Expired</h6>
                                <h3 class="mb-0"><?= number_format($stats['expired_total']) ?></h3>
                            </div>
                            <div class="stat-icon bg-danger">
                                <i class="bi bi-x-circle"></i>
                            </div>
                        </div>
                        <small class="text-muted"><?= $stats['expired_today'] ?> today</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">This Week</h6>
                                <h3 class="mb-0"><?= number_format($stats['expired_week']) ?></h3>
                            </div>
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-calendar-week"></i>
                            </div>
                        </div>
                        <small class="text-muted">Last 7 days</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">This Month</h6>
                                <h3 class="mb-0"><?= number_format($stats['expired_month']) ?></h3>
                            </div>
                            <div class="stat-icon bg-info">
                                <i class="bi bi-calendar-month"></i>
                            </div>
                        </div>
                        <small class="text-muted">Last 30 days</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Expiring Soon</h6>
                                <h3 class="mb-0 text-warning"><?= number_format($stats['expiring_soon']) ?></h3>
                            </div>
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-clock-history"></i>
                            </div>
                        </div>
                        <small class="text-muted">Next 24 hours</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Expiring Soon Alert -->
        <?php if ($stats['expiring_soon'] > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Warning!</strong> <?= $stats['expiring_soon'] ?> donations will expire in the next 24 hours.
            <a href="#expiringSoonSection" class="alert-link">View them below</a>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <!-- Expiring Soon Section -->
        <?php if (!empty($expiring_soon)): ?>
        <div class="card mb-4" id="expiringSoonSection">
            <div class="card-header bg-warning">
                <h5 class="mb-0 text-white"><i class="bi bi-clock-history"></i> Expiring Soon (Next 24 Hours)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Donation</th>
                                <th>Donor</th>
                                <th>Food Type</th>
                                <th>Expires In</th>
                                <th>Posted</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiring_soon as $donation): ?>
                                <tr class="table-warning">
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
                                                     class="rounded me-2" width="40" height="40" 
                                                     style="object-fit: cover;" alt="Food">
                                            <?php 
                                                endif;
                                            endif;
                                            if (!$has_image): 
                                            ?>
                                                <div class="bg-secondary rounded me-2 d-flex align-items-center justify-content-center" 
                                                     style="width: 40px; height: 40px;">
                                                    <i class="bi bi-image text-white"></i>
                                                </div>
                                            <?php endif; ?>
                                            <strong><?= htmlspecialchars($donation['title']) ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <img src="<?= !empty($donation['profile_img']) ? '../uploads/profile_picture/' . $donation['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                 class="rounded-circle me-2" width="28" height="28" alt="Profile">
                                            <?= htmlspecialchars($donation['full_name']) ?>
                                        </div>
                                    </td>
                                    <td><span class="badge bg-info"><?= ucfirst($donation['food_type']) ?></span></td>
                                    <td>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-clock"></i> <?= $donation['hours_remaining'] ?> hours
                                        </span>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($donation['created_at'])) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-info" 
                                                onclick='viewDonation(<?= json_encode($donation, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                            <i class="bi bi-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Expired Donations -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Expired Donations</h5>
                    <div class="btn-group">
                        <button class="btn btn-danger" onclick="showBulkDeleteModal()">
                            <i class="bi bi-trash"></i> Bulk Delete
                        </button>
                        <button class="btn btn-warning" onclick="autoExpire()">
                            <i class="bi bi-clock"></i> Auto-Expire
                        </button>
                        <button class="btn btn-success" onclick="exportExpired()">
                            <i class="bi bi-file-earmark-excel"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Search -->
                <div class="mb-3">
                    <input type="text" class="form-control" id="searchInput" 
                           placeholder="Search expired donations..." 
                           onkeyup="searchDonations()">
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Donation</th>
                                <th>Donor</th>
                                <th>Food Type</th>
                                <th>Views</th>
                                <th>Posted</th>
                                <th>Expired</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($expired_donations)): ?>
                                <?php foreach ($expired_donations as $donation): ?>
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
                                        <td><span class="badge bg-info"><?= ucfirst($donation['food_type']) ?></span></td>
                                        <td><?= number_format($donation['views_count'] ?? 0) ?></td>
                                        <td>
                                            <small><?= date('M j, Y', strtotime($donation['created_at'])) ?></small>
                                        </td>
                                        <td>
                                            <small class="text-danger">
                                                <?php 
                                                $days_ago = floor((time() - strtotime($donation['updated_at'])) / 86400);
                                                echo $days_ago . ' day' . ($days_ago != 1 ? 's' : '') . ' ago';
                                                ?>
                                            </small>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-info" 
                                                        onclick='viewDonation(<?= json_encode($donation, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="restoreDonation(<?= $donation['id'] ?>)" 
                                                        title="Restore">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="deleteDonation(<?= $donation['id'] ?>)" 
                                                        title="Delete Permanently">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                        <p class="mt-2">No expired donations - Great job!</p>
                                    </td>
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

<!-- Bulk Delete Modal -->
<div class="modal fade" id="bulkDeleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-trash"></i> Bulk Delete Expired Donations</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Warning:</strong> This action will permanently delete expired donations and cannot be undone!
                </div>
                <form id="bulkDeleteForm">
                    <div class="mb-3">
                        <label class="form-label">Delete donations older than:</label>
                        <select class="form-select" name="days" required>
                            <option value="7">7 days</option>
                            <option value="14">14 days</option>
                            <option value="30" selected>30 days</option>
                            <option value="60">60 days</option>
                            <option value="90">90 days</option>
                        </select>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Old Donations
                        </button>
                    </div>
                </form>
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
    
    const content = `
        <div class="row">
            <div class="col-12 mb-3">
                ${images}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Donation ID:</strong> #${donation.id}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Status:</strong> <span class="badge bg-danger">Expired</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Posted:</strong> ${new Date(donation.created_at).toLocaleString()}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Expired:</strong> ${new Date(donation.updated_at).toLocaleString()}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Food Type:</strong> <span class="badge bg-info">${donation.food_type}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Views:</strong> ${donation.views_count || 0}
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
            <div class="col-12">
                <hr>
                <div class="d-grid gap-2">
                    <button class="btn btn-success" onclick="restoreDonation(${donation.id})">
                        <i class="bi bi-arrow-counterclockwise"></i> Restore Donation
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteDonation(${donation.id})">
                        <i class="bi bi-trash"></i> Delete Permanently
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('donationDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('viewDonationModal')).show();
}

// Restore Donation
function restoreDonation(donationId) {
    if (!confirm('Restore this donation to available status?')) return;
    
    const formData = new FormData();
    formData.append('action', 'restore_donation');
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
            showNotification(data.message || 'Failed to restore', 'error');
        }
    });
}

// Delete Donation
function deleteDonation(donationId) {
    if (!confirm('Permanently delete this donation? This action cannot be undone!')) return;
    
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

// Show Bulk Delete Modal
function showBulkDeleteModal() {
    new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
}

// Bulk Delete Form Submit
document.getElementById('bulkDeleteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'bulk_delete');
    
    const days = formData.get('days');
    
    if (!confirm(`Delete all expired donations older than ${days} days? This cannot be undone!`)) return;
    
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
});

// Auto-Expire Donations
function autoExpire() {
    if (!confirm('Automatically expire all donations that have passed their expiry date?')) return;
    
    const formData = new FormData();
    formData.append('action', 'auto_expire');
    
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
            showNotification(data.message || 'Failed to auto-expire', 'error');
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

// Export Expired Donations
function exportExpired() {
    const data = <?= json_encode($expired_donations) ?>;
    
    if (data.length === 0) {
        showNotification('No expired donations to export', 'warning');
        return;
    }
    
    const headers = ['ID', 'Title', 'Donor', 'Email', 'Food Type', 'Views', 'Posted', 'Expired'];
    let csv = headers.join(',') + '\n';
    
    data.forEach(row => {
        const values = [
            row.id,
            `"${row.title}"`,
            `"${row.full_name}"`,
            `"${row.email}"`,
            row.food_type,
            row.views_count || 0,
            row.created_at,
            row.updated_at
        ];
        csv += values.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'expired_donations_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Expired donations exported successfully!', 'success');
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


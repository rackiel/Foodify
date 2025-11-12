<?php
include '../config/db.php';

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
    $donation_id = intval($_POST['donation_id']);
    
    try {
        switch ($action) {
            case 'delete':
                // Delete associated images first
                $stmt = $conn->prepare("SELECT images FROM food_donations WHERE id = ?");
                $stmt->bind_param('i', $donation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $donation = $result->fetch_assoc();
                
                if ($donation['images']) {
                    $images = json_decode($donation['images'], true);
                    foreach ($images as $image) {
                        $file_path = '../' . $image;
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                }
                
                $stmt = $conn->prepare("DELETE FROM food_donations WHERE id = ?");
                $stmt->bind_param('i', $donation_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Donation deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete donation.']);
                }
                $stmt->close();
                break;
                
            case 'extend':
                $new_expiry = $_POST['new_expiry'];
                $stmt = $conn->prepare("UPDATE food_donations SET expiration_date = ? WHERE id = ?");
                $stmt->bind_param('si', $new_expiry, $donation_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Donation expiry extended successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to extend donation.']);
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

// Get expired and flagged donations
$stmt = $conn->prepare("
    SELECT fd.*, ua.full_name, ua.email,
           admin.full_name as approved_by_name
    FROM food_donations fd 
    JOIN user_accounts ua ON fd.user_id = ua.user_id 
    LEFT JOIN user_accounts admin ON fd.approved_by = admin.user_id
    WHERE (fd.expiration_date < CURDATE() OR fd.status = 'expired' OR fd.status = 'cancelled')
    ORDER BY fd.expiration_date ASC, fd.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$expired_donations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-exclamation-triangle"></i> Expired & Flagged Donations</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Food Donation Management</li>
                <li class="breadcrumb-item active">Expired/Flagged</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history"></i> Expired & Flagged Donations
                        </h5>
                        <span class="badge bg-danger"><?php echo count($expired_donations); ?> items</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($expired_donations)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                                <h4 class="mt-3">No Expired Donations</h4>
                                <p class="text-muted">All donations are current and active.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Donation Details</th>
                                            <th>Donor</th>
                                            <th>Status</th>
                                            <th>Expired</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($expired_donations as $donation): ?>
                                            <?php
                                            $is_expired = $donation['expiration_date'] && strtotime($donation['expiration_date']) < time();
                                            $status_class = $is_expired ? 'bg-danger' : 'bg-warning';
                                            $status_text = $is_expired ? 'Expired' : 'Flagged';
                                            $status_icon = $is_expired ? 'bi-exclamation-triangle' : 'bi-flag';
                                            ?>
                                            <tr class="<?php echo $is_expired ? 'table-danger' : 'table-warning'; ?>">
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
                                                            <h6 class="mb-1"><?php echo htmlspecialchars($donation['title']); ?></h6>
                                                            <p class="text-muted mb-1 small">
                                                                <strong>Type:</strong> <?php echo ucfirst($donation['food_type']); ?><br>
                                                                <strong>Quantity:</strong> <?php echo htmlspecialchars($donation['quantity']); ?><br>
                                                                <?php if ($donation['expiration_date']): ?>
                                                                    <strong>Expired:</strong> <?php echo date('M d, Y', strtotime($donation['expiration_date'])); ?>
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
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <i class="bi <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                                    </span>
                                                    <?php if ($donation['approved_by_name']): ?>
                                                        <br><small class="text-muted">Approved by: <?php echo htmlspecialchars($donation['approved_by_name']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php if ($donation['expiration_date']): ?>
                                                            <?php echo date('M d, Y', strtotime($donation['expiration_date'])); ?><br>
                                                            <?php 
                                                            $days_ago = floor((time() - strtotime($donation['expiration_date'])) / (60 * 60 * 24));
                                                            echo $days_ago . ' day' . ($days_ago != 1 ? 's' : '') . ' ago';
                                                            ?>
                                                        <?php else: ?>
                                                            No expiry date
                                                        <?php endif; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <?php if ($donation['expiration_date']): ?>
                                                            <button type="button" class="btn btn-warning btn-sm" 
                                                                    onclick="extendDonation(<?php echo $donation['id']; ?>, '<?php echo $donation['expiration_date']; ?>')">
                                                                <i class="bi bi-clock-history"></i> Extend
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

<!-- Extend Modal -->
<div class="modal fade" id="extendModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Extend Donation Expiry</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="extendForm">
                    <input type="hidden" id="extend_donation_id">
                    <div class="mb-3">
                        <label for="new_expiry" class="form-label">New Expiry Date *</label>
                        <input type="date" class="form-control" id="new_expiry" required>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" onclick="confirmExtend()">Extend Donation</button>
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

<script>
function extendDonation(donationId, currentExpiry) {
    document.getElementById('extend_donation_id').value = donationId;
    document.getElementById('new_expiry').value = currentExpiry;
    new bootstrap.Modal(document.getElementById('extendModal')).show();
}

function confirmExtend() {
    const donationId = document.getElementById('extend_donation_id').value;
    const newExpiry = document.getElementById('new_expiry').value;
    
    if (!newExpiry) {
        showNotification('Please select a new expiry date.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'extend');
    formData.append('donation_id', donationId);
    formData.append('new_expiry', newExpiry);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('extendModal')).hide();
            location.reload();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while extending the donation.', 'error');
    });
}

function deleteDonation(donationId) {
    if (confirm('Are you sure you want to delete this expired donation? This action cannot be undone.')) {
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
    document.getElementById('donationDetails').innerHTML = `
        <div class="text-center">
            <i class="bi bi-info-circle text-primary" style="font-size: 3rem;"></i>
            <h5 class="mt-3">Donation Details</h5>
            <p class="text-muted">Detailed view for donation ID: ${donationId}</p>
            <p><em>This feature can be enhanced to show complete donation details, images, and donor information.</em></p>
        </div>
    `;
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
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

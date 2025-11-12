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

// Handle AJAX requests for approval actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $donation_id = intval($_POST['donation_id']);
    
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
                    
                    $message = 'Food donation approved successfully!';
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
                
            case 'get_details':
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email, ua.phone_number,
                           admin.full_name as approved_by_name
                    FROM food_donations fd 
                    JOIN user_accounts ua ON fd.user_id = ua.user_id 
                    LEFT JOIN user_accounts admin ON fd.approved_by = admin.user_id
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
                    
                    $message = 'Food donation rejected successfully!';
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
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get pending food donations
$stmt = $conn->prepare("
    SELECT fd.*, ua.full_name, ua.email 
    FROM food_donations fd 
    JOIN user_accounts ua ON fd.user_id = ua.user_id 
    WHERE fd.approval_status = 'pending' 
    ORDER BY fd.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$pending_donations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-check-circle"></i> Donation Approvals</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Food Donation Management</li>
                <li class="breadcrumb-item active">Pending Approvals</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history"></i> Pending Food Donations
                        </h5>
                        <span class="badge bg-warning"><?php echo count($pending_donations); ?> pending</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($pending_donations)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                                <h4 class="mt-3">No Pending Approvals</h4>
                                <p class="text-muted">All food donations have been reviewed.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Donation Details</th>
                                            <th>Donor</th>
                                            <th>Location</th>
                                            <th>Posted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pending_donations as $donation): ?>
                                            <tr>
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
                                                                    <strong>Expires:</strong> <?php echo date('M d, Y', strtotime($donation['expiration_date'])); ?>
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
                                                    <small>
                                                        <?php echo htmlspecialchars(substr($donation['location_address'], 0, 50)); ?><?php echo strlen($donation['location_address']) > 50 ? '...' : ''; ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($donation['created_at'])); ?><br>
                                                        <?php echo date('h:i A', strtotime($donation['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-success btn-sm" 
                                                                onclick="approveDonation(<?php echo $donation['id']; ?>)">
                                                            <i class="bi bi-check"></i> Approve
                                                        </button>
                                                        <button type="button" class="btn btn-danger btn-sm" 
                                                                onclick="rejectDonation(<?php echo $donation['id']; ?>)">
                                                            <i class="bi bi-x"></i> Reject
                                                        </button>
                                                        <button type="button" class="btn btn-info btn-sm" 
                                                                onclick="viewDetails(<?php echo $donation['id']; ?>)">
                                                            <i class="bi bi-eye"></i> View
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
                <h5 class="modal-title">Food Donation Details</h5>
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
function approveDonation(donationId) {
    if (confirm('Are you sure you want to approve this food donation?')) {
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
            </div>
        </div>
    `;
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

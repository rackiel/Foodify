<?php
include '../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $donation_id = intval($_POST['donation_id']);
    
    // Verify ownership
    $stmt = $conn->prepare("SELECT user_id FROM food_donations WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $donation_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Donation not found or access denied.']);
        exit;
    }
    $stmt->close();
    
    try {
        switch ($action) {
            case 'delete':
                // Only allow deletion of pending donations
                $stmt = $conn->prepare("SELECT approval_status FROM food_donations WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $donation_id, $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $donation = $result->fetch_assoc();
                
                if ($donation['approval_status'] !== 'pending') {
                    echo json_encode(['success' => false, 'message' => 'Only pending donations can be deleted.']);
                    exit;
                }
                
                // Delete the donation
                $stmt = $conn->prepare("DELETE FROM food_donations WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $donation_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Donation deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete donation.']);
                }
                $stmt->close();
                break;
                
            case 'get_details':
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email,
                           admin.full_name as approved_by_name
                    FROM food_donations fd 
                    JOIN user_accounts ua ON fd.user_id = ua.user_id 
                    LEFT JOIN user_accounts admin ON fd.approved_by = admin.user_id
                    WHERE fd.id = ? AND fd.user_id = ?
                ");
                $stmt->bind_param('ii', $donation_id, $_SESSION['user_id']);
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
                
            case 'get_requests':
                $stmt = $conn->prepare("
                    SELECT fdr.*, ua.full_name as requester_name, ua.email as requester_email
                    FROM food_donation_reservations fdr
                    JOIN user_accounts ua ON fdr.requester_id = ua.user_id
                    WHERE fdr.donation_id = ?
                    ORDER BY fdr.reserved_at DESC
                ");
                $stmt->bind_param('i', $donation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $requests = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                echo json_encode(['success' => true, 'requests' => $requests]);
                break;
                
            case 'update_request_status':
                $request_id = intval($_POST['request_id']);
                $new_status = $_POST['status'];
                
                // Validate status
                $valid_statuses = ['approved', 'rejected', 'completed'];
                if (!in_array($new_status, $valid_statuses)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid status.']);
                    exit;
                }
                
                // Check if request belongs to user's donation
                $stmt = $conn->prepare("
                    SELECT fdr.id, fd.user_id 
                    FROM food_donation_reservations fdr
                    JOIN food_donations fd ON fdr.donation_id = fd.id
                    WHERE fdr.id = ? AND fd.user_id = ?
                ");
                $stmt->bind_param('ii', $request_id, $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                $stmt->close();
                
                if (!$request) {
                    echo json_encode(['success' => false, 'message' => 'Request not found.']);
                    exit;
                }
                
                // Update request status
                $stmt = $conn->prepare("
                    UPDATE food_donation_reservations 
                    SET status = ?, responded_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->bind_param('si', $new_status, $request_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Request status updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update request status.']);
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

// Get user's donations
$stmt = $conn->prepare("
    SELECT fd.*, ua.full_name, ua.email,
           admin.full_name as approved_by_name
    FROM food_donations fd 
    JOIN user_accounts ua ON fd.user_id = ua.user_id 
    LEFT JOIN user_accounts admin ON fd.approved_by = admin.user_id
    WHERE fd.user_id = ?
    ORDER BY fd.created_at DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$donations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get statistics
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_donations,
        SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved_donations,
        SUM(CASE WHEN approval_status = 'pending' THEN 1 ELSE 0 END) as pending_donations,
        SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected_donations,
        SUM(views_count) as total_views
    FROM food_donations 
    WHERE user_id = ?
");
$stats_stmt->bind_param('i', $_SESSION['user_id']);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-clock-history"></i> My Donation History</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Food Sharing</li>
                <li class="breadcrumb-item active">My Donation History</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Donations</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-basket"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $stats['total_donations']; ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Approved</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $stats['approved_donations']; ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Pending</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $stats['pending_donations']; ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Views</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                                <i class="bi bi-eye"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $stats['total_views']; ?></h6>
                            </div>
                        </div>
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
                            <i class="bi bi-list-ul"></i> My Food Donations
                        </h5>
                        <a href="post_excess_food.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Post New Donation
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($donations)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-basket text-muted" style="font-size: 3rem;"></i>
                                <h4 class="mt-3">No Donations Found</h4>
                                <p class="text-muted">You haven't posted any food donations yet.</p>
                                <a href="post_excess_food.php" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Post Your First Donation
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Donation Details</th>
                                            <th>Status</th>
                                            <th>Views</th>
                                            <th>Posted</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($donations as $donation): ?>
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
                                                </td>
                                                <td>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-eye"></i> <?php echo $donation['views_count']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <?php echo date('M d, Y', strtotime($donation['created_at'])); ?><br>
                                                        <?php echo date('h:i A', strtotime($donation['created_at'])); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <button type="button" class="btn btn-info btn-sm" 
                                                                onclick="viewDonationDetails(<?php echo $donation['id']; ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <?php if ($donation['approval_status'] === 'approved'): ?>
                                                            <button type="button" class="btn btn-primary btn-sm" 
                                                                    onclick="viewRequests(<?php echo $donation['id']; ?>)">
                                                                <i class="bi bi-hand-thumbs-up"></i>
                                                            </button>
                                                        <?php endif; ?>
                                                        <?php if ($donation['approval_status'] === 'pending'): ?>
                                                            <button type="button" class="btn btn-danger btn-sm" 
                                                                    onclick="deleteDonation(<?php echo $donation['id']; ?>)">
                                                                <i class="bi bi-trash"></i>
                                                            </button>
                                                        <?php endif; ?>
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

<!-- Donation Details Modal -->
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

<!-- Requests Modal -->
<div class="modal fade" id="requestsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Food Requests</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="requestsContent">
                <!-- Requests will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.info-card .card-icon {
    width: 4rem;
    height: 4rem;
    font-size: 2rem;
    background-color: #e3f2fd;
    color: #1976d2;
}

.info-card .card-icon.bg-success {
    background-color: #d4edda;
    color: #155724;
}

.info-card .card-icon.bg-warning {
    background-color: #fff3cd;
    color: #856404;
}

.info-card .card-icon.bg-info {
    background-color: #d1ecf1;
    color: #0c5460;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #495057;
}

.badge {
    font-size: 0.75rem;
}
</style>

<script>
function viewDonationDetails(donationId) {
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
        }
    })
    .catch(error => {
        showNotification('An error occurred while loading donation details.', 'error');
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
                <h6>Basic Information</h6>
                <p><strong>Title:</strong> ${donation.title}</p>
                <p><strong>Description:</strong> ${donation.description}</p>
                <p><strong>Food Type:</strong> ${donation.food_type.charAt(0).toUpperCase() + donation.food_type.slice(1)}</p>
                <p><strong>Quantity:</strong> ${donation.quantity}</p>
                ${donation.expiration_date ? `<p><strong>Expiration Date:</strong> ${new Date(donation.expiration_date).toLocaleDateString()}</p>` : ''}
            </div>
            <div class="col-md-6">
                <h6>Location & Contact</h6>
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
                <h6>Additional Information</h6>
                ${donation.dietary_info ? `<p><strong>Dietary Info:</strong> ${donation.dietary_info}</p>` : ''}
                ${donation.allergens ? `<p><strong>Allergens:</strong> ${donation.allergens}</p>` : ''}
                ${donation.storage_instructions ? `<p><strong>Storage Instructions:</strong> ${donation.storage_instructions}</p>` : ''}
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6>Images</h6>
                <div class="d-flex flex-wrap gap-2">
                    ${imagesHtml}
                </div>
            </div>
        </div>
        
        <div class="row mt-3">
            <div class="col-12">
                <h6>Status Information</h6>
                <p><strong>Status:</strong> <span class="badge bg-${statusClass}"><i class="bi bi-${statusIcon}"></i> ${donation.approval_status.charAt(0).toUpperCase() + donation.approval_status.slice(1)}</span></p>
                <p><strong>Posted:</strong> ${new Date(donation.created_at).toLocaleString()}</p>
                <p><strong>Views:</strong> ${donation.views_count}</p>
                ${donation.approved_by_name ? `<p><strong>Approved by:</strong> ${donation.approved_by_name}</p>` : ''}
                ${donation.rejection_reason ? `<p><strong>Rejection Reason:</strong> ${donation.rejection_reason}</p>` : ''}
            </div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('detailsModal')).show();
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

function viewRequests(donationId) {
    // Show loading state
    document.getElementById('requestsContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading requests...</p>
        </div>
    `;
    
    // Show modal first
    new bootstrap.Modal(document.getElementById('requestsModal')).show();
    
    // Fetch requests
    const formData = new FormData();
    formData.append('action', 'get_requests');
    formData.append('donation_id', donationId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayRequests(data.requests);
        } else {
            showNotification(data.message, 'error');
            document.getElementById('requestsContent').innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Error Loading Requests</h5>
                    <p class="text-muted">${data.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        showNotification('An error occurred while loading requests.', 'error');
        document.getElementById('requestsContent').innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Error Loading Requests</h5>
                <p class="text-muted">An error occurred while loading the requests.</p>
            </div>
        `;
    });
}

function displayRequests(requests) {
    if (requests.length === 0) {
        document.getElementById('requestsContent').innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-hand-thumbs-up text-muted" style="font-size: 3rem;"></i>
                <h5 class="mt-3">No Requests Yet</h5>
                <p class="text-muted">No one has requested this food donation yet.</p>
            </div>
        `;
        return;
    }
    
    let requestsHtml = '<div class="row">';
    
    requests.forEach(request => {
        const statusClass = request.status === 'pending' ? 'warning' : 
                           request.status === 'approved' ? 'success' : 
                           request.status === 'rejected' ? 'danger' : 
                           request.status === 'completed' ? 'info' : 'secondary';
        const statusIcon = request.status === 'pending' ? 'clock' : 
                          request.status === 'approved' ? 'check-circle' : 
                          request.status === 'rejected' ? 'x-circle' : 
                          request.status === 'completed' ? 'check2-all' : 'x-circle';
        
        requestsHtml += `
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="card-title">${request.requester_name}</h6>
                            <span class="badge bg-${statusClass}">
                                <i class="bi bi-${statusIcon}"></i> ${request.status.charAt(0).toUpperCase() + request.status.slice(1)}
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Message:</strong>
                            <p class="text-muted small">${request.message}</p>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Contact Info:</strong>
                            <p class="text-muted small">${request.contact_info}</p>
                        </div>
                        
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="bi bi-calendar"></i> 
                                Requested: ${new Date(request.reserved_at).toLocaleString()}
                            </small>
                        </div>
                        
                        ${request.status === 'pending' ? `
                            <div class="d-flex gap-2">
                                <button class="btn btn-success btn-sm" onclick="updateRequestStatus(${request.id}, 'approved')">
                                    <i class="bi bi-check"></i> Approve
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="updateRequestStatus(${request.id}, 'rejected')">
                                    <i class="bi bi-x"></i> Reject
                                </button>
                            </div>
                        ` : request.status === 'approved' ? `
                            <div class="d-flex gap-2">
                                <button class="btn btn-info btn-sm" onclick="updateRequestStatus(${request.id}, 'completed')">
                                    <i class="bi bi-check2-all"></i> Mark Complete
                                </button>
                            </div>
                        ` : ''}
                    </div>
                </div>
            </div>
        `;
    });
    
    requestsHtml += '</div>';
    document.getElementById('requestsContent').innerHTML = requestsHtml;
}

function updateRequestStatus(requestId, newStatus) {
    if (confirm(`Are you sure you want to ${newStatus} this request?`)) {
        const formData = new FormData();
        formData.append('action', 'update_request_status');
        formData.append('request_id', requestId);
        formData.append('status', newStatus);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                // Refresh the requests modal
                const donationId = document.querySelector('#requestsModal .btn-primary').onclick.toString().match(/\d+/)[0];
                viewRequests(donationId);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while updating the request status.', 'error');
        });
    }
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

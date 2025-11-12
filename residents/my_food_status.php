<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Check if user has permission to access this page
if ($_SESSION['role'] !== 'resident' && $_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'team officer') {
    header('Location: ../index.php');
    exit();
}

include '../config/db.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $donation_id = intval($_POST['donation_id'] ?? 0);
    $request_id = intval($_POST['request_id'] ?? 0);
    
    switch ($action) {
        case 'get_donations':
            // Get user's food donations with request counts
            $stmt = $conn->prepare("
                SELECT fd.*, 
                       COUNT(fdr.id) as total_requests,
                       COUNT(CASE WHEN fdr.status = 'pending' THEN 1 END) as pending_requests,
                       COUNT(CASE WHEN fdr.status = 'approved' THEN 1 END) as approved_requests,
                       COUNT(CASE WHEN fdr.status = 'rejected' THEN 1 END) as rejected_requests,
                       COUNT(CASE WHEN fdr.status = 'completed' THEN 1 END) as completed_requests
                FROM food_donations fd
                LEFT JOIN food_donation_reservations fdr ON fd.id = fdr.donation_id
                WHERE fd.user_id = ?
                GROUP BY fd.id
                ORDER BY fd.created_at DESC
            ");
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $donations = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
            
            echo json_encode(['success' => true, 'donations' => $donations]);
            break;
            
        case 'get_requests':
            // Get requests for a specific donation
            $stmt = $conn->prepare("
                SELECT fdr.*, ua.full_name as requester_name, ua.email as requester_email, ua.phone_number as requester_phone
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
            // Update request status (approve/reject/complete/cancel)
            $new_status = $_POST['status'] ?? '';
            $admin_notes = trim($_POST['admin_notes'] ?? '');
            
            // Check if user has permission to update status
            if ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'team officer') {
                echo json_encode(['success' => false, 'message' => 'You do not have permission to update request status.']);
                exit;
            }
            
            // Validate status
            $valid_statuses = ['approved', 'rejected', 'completed', 'cancelled'];
            if (!in_array($new_status, $valid_statuses)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status.']);
                exit;
            }
            
            // Get request details for email notification
            $stmt = $conn->prepare("
                SELECT fdr.*, fd.title as donation_title, ua_requester.full_name as requester_name, 
                       ua_requester.email as requester_email, ua_donor.full_name as donor_name, 
                       ua_donor.email as donor_email
                FROM food_donation_reservations fdr
                JOIN food_donations fd ON fdr.donation_id = fd.id
                JOIN user_accounts ua_requester ON fdr.requester_id = ua_requester.user_id
                JOIN user_accounts ua_donor ON fd.user_id = ua_donor.user_id
                WHERE fdr.id = ?
            ");
            $stmt->bind_param('i', $request_id);
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
                SET status = ?, admin_notes = ?, updated_at = NOW(), updated_by = ?
                WHERE id = ?
            ");
            $stmt->bind_param('ssii', $new_status, $admin_notes, $_SESSION['user_id'], $request_id);
            
            if ($stmt->execute()) {
                // Send email notification
                include '../teamofficer/email_notifications.php';
                $emailNotifier = new DonationEmailNotifications();
                
                $emailSent = false;
                if ($new_status === 'approved') {
                    $emailSent = $emailNotifier->sendRequestApproved($request, $request['requester_email'], $request['requester_name']);
                } elseif ($new_status === 'rejected') {
                    $emailSent = $emailNotifier->sendRequestRejected($request, $request['requester_email'], $request['requester_name'], $admin_notes);
                } elseif ($new_status === 'completed') {
                    $emailSent = $emailNotifier->sendRequestCompleted($request, $request['requester_email'], $request['requester_name']);
                }
                
                $message = 'Request status updated successfully!';
                if ($emailSent) {
                    $message .= ' Email notification sent to requester.';
                } else {
                    $message .= ' Note: Email notification could not be sent.';
                }
                
                echo json_encode(['success' => true, 'message' => $message]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update request status.']);
            }
            $stmt->close();
            break;
            
        case 'get_donation_stats':
            // Get overall statistics for user's donations
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(fd.id) as total_donations,
                    COUNT(CASE WHEN fd.approval_status = 'approved' THEN 1 END) as approved_donations,
                    COUNT(CASE WHEN fd.approval_status = 'pending' THEN 1 END) as pending_donations,
                    COUNT(CASE WHEN fd.approval_status = 'rejected' THEN 1 END) as rejected_donations,
                    COALESCE(SUM(fd.views_count), 0) as total_views
                FROM food_donations fd
                WHERE fd.user_id = ?
            ");
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            // Get request statistics
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(fdr.id) as total_requests,
                    COUNT(CASE WHEN fdr.status = 'pending' THEN 1 END) as pending_requests,
                    COUNT(CASE WHEN fdr.status = 'approved' THEN 1 END) as approved_requests,
                    COUNT(CASE WHEN fdr.status = 'rejected' THEN 1 END) as rejected_requests,
                    COUNT(CASE WHEN fdr.status = 'completed' THEN 1 END) as completed_requests
                FROM food_donation_reservations fdr
                JOIN food_donations fd ON fdr.donation_id = fd.id
                WHERE fd.user_id = ?
            ");
            $stmt->bind_param('i', $_SESSION['user_id']);
            $stmt->execute();
            $result = $stmt->get_result();
            $request_stats = $result->fetch_assoc();
            $stmt->close();
            
            $combined_stats = array_merge($stats, $request_stats);
            echo json_encode(['success' => true, 'stats' => $combined_stats]);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }
    exit;
}

// Get initial data
$stmt = $conn->prepare("
    SELECT 
        COUNT(fd.id) as total_donations,
        COUNT(CASE WHEN fd.approval_status = 'approved' THEN 1 END) as approved_donations,
        COUNT(CASE WHEN fd.approval_status = 'pending' THEN 1 END) as pending_donations,
        COUNT(CASE WHEN fd.approval_status = 'rejected' THEN 1 END) as rejected_donations,
        COALESCE(SUM(fd.views_count), 0) as total_views
    FROM food_donations fd
    WHERE fd.user_id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();

// Get request statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(fdr.id) as total_requests,
        COUNT(CASE WHEN fdr.status = 'pending' THEN 1 END) as pending_requests,
        COUNT(CASE WHEN fdr.status = 'approved' THEN 1 END) as approved_requests,
        COUNT(CASE WHEN fdr.status = 'rejected' THEN 1 END) as rejected_requests,
        COUNT(CASE WHEN fdr.status = 'completed' THEN 1 END) as completed_requests
    FROM food_donation_reservations fdr
    JOIN food_donations fd ON fdr.donation_id = fd.id
    WHERE fd.user_id = ?
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$request_stats = $result->fetch_assoc();
$stmt->close();

$combined_stats = array_merge($stats, $request_stats);
?>

<?php include 'header.php'; ?>
<?php include 'topbar.php'; ?>
<?php include 'sidebar.php'; ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>My Food Status</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">My Food Status</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->
    
    <div class="container-fluid">
        <div class="row">
            <div class="col-12">
    
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4rem 0.8rem;
        }
        
        .stats-card {
            transition: transform 0.2s ease-in-out;
        }
        
        .stats-card:hover {
            transform: translateY(-2px);
        }
        
        .donation-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .donation-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .request-item {
            border-left: 3px solid #28a745;
            background: #f8f9fa;
        }
        
        .request-item.pending {
            border-left-color: #ffc107;
        }
        
        .request-item.rejected {
            border-left-color: #dc3545;
        }
        
        .request-item.completed {
            border-left-color: #28a745;
        }
        
        .request-item.cancelled {
            border-left-color: #6c757d;
        }
        
        .action-buttons .btn {
            margin: 0 2px;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white;
        }
        
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
        }
        
        .loading-spinner {
            display: none;
        }
        
        .loading-spinner.show {
            display: inline-block;
        }
    </style>
    
    <section class="section dashboard">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h2 class="h4"><i class="bi bi-graph-up"></i> Food Donation Statistics</h2>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <button type="button" class="btn btn-outline-primary" onclick="refreshData()">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                </div>
            </div>
        </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Donations</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-donations"><?php echo $combined_stats['total_donations']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-basket text-primary fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Total Requests</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-requests"><?php echo $combined_stats['total_requests']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-hand-thumbs-up text-success fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending Requests</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="pending-requests"><?php echo $combined_stats['pending_requests']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-clock text-warning fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Total Views</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-views"><?php echo $combined_stats['total_views']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-eye text-info fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Donations List -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-basket"></i> My Food Donations
                        </h6>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadDonations()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="donations-container">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-2">Loading donations...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </section>
</main><!-- End #main -->

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-people"></i> Food Requests
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="requests-container">
                        <div class="text-center">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2">Loading requests...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-gear"></i> Update Request Status
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="statusForm">
                        <input type="hidden" id="request_id" name="request_id">
                        <input type="hidden" id="donation_id" name="donation_id">
                        
                        <div class="mb-3">
                            <label for="status" class="form-label">Status</label>
                            <select class="form-select" id="status" name="status" required>
                                <option value="">Select Status</option>
                                <option value="approved">Approve</option>
                                <option value="rejected">Reject</option>
                                <option value="completed">Mark as Completed</option>
                                <option value="cancelled">Cancel</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="admin_notes" class="form-label">Admin Notes (Optional)</label>
                            <textarea class="form-control" id="admin_notes" name="admin_notes" rows="3" 
                                      placeholder="Add any notes about this decision..."></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="updateRequestStatus()">
                        <span class="loading-spinner spinner-border spinner-border-sm me-2"></span>
                        Update Status
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Container -->
    <div id="notification-container" class="notification"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentDonationId = null;
        let currentRequestId = null;

        // Load donations on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadDonations();
        });

        // Load donations
        function loadDonations() {
            const container = document.getElementById('donations-container');
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading donations...</p>
                </div>
            `;

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_donations'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayDonations(data.donations);
                } else {
                    showNotification('Failed to load donations: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while loading donations.', 'error');
            });
        }

        // Display donations
        function displayDonations(donations) {
            const container = document.getElementById('donations-container');
            
            if (donations.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-5">
                        <i class="bi bi-basket text-muted" style="font-size: 3rem;"></i>
                        <h5 class="text-muted mt-3">No donations found</h5>
                        <p class="text-muted">You haven't posted any food donations yet.</p>
                        <a href="post_excess_food.php" class="btn btn-primary">
                            <i class="bi bi-plus"></i> Post Your First Donation
                        </a>
                    </div>
                `;
                return;
            }

            container.innerHTML = donations.map(donation => `
                <div class="card donation-card mb-3">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h5 class="card-title">${donation.title}</h5>
                                <p class="card-text">${donation.description}</p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="bi bi-tag"></i> ${donation.food_type}<br>
                                            <i class="bi bi-box"></i> ${donation.quantity}<br>
                                            <i class="bi bi-calendar"></i> ${new Date(donation.created_at).toLocaleDateString()}
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="bi bi-eye"></i> ${donation.views_count} views<br>
                                            <i class="bi bi-geo-alt"></i> ${donation.location_address}
                                        </small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-end">
                                    <span class="badge status-badge bg-${getApprovalStatusColor(donation.approval_status)}">
                                        ${donation.approval_status.toUpperCase()}
                                    </span>
                                    <div class="mt-2">
                                        <h6 class="text-primary">Request Statistics</h6>
                                        <div class="row text-center">
                                            <div class="col-3">
                                                <small class="text-muted">Total</small><br>
                                                <strong>${donation.total_requests}</strong>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-warning">Pending</small><br>
                                                <strong>${donation.pending_requests}</strong>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-success">Approved</small><br>
                                                <strong>${donation.approved_requests}</strong>
                                            </div>
                                            <div class="col-3">
                                                <small class="text-info">Completed</small><br>
                                                <strong>${donation.completed_requests}</strong>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <button class="btn btn-outline-primary btn-sm" onclick="viewRequests(${donation.id})">
                                            <i class="bi bi-people"></i> View Requests (${donation.total_requests})
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Get approval status color
        function getApprovalStatusColor(status) {
            switch(status) {
                case 'approved': return 'success';
                case 'pending': return 'warning';
                case 'rejected': return 'danger';
                default: return 'secondary';
            }
        }

        // View requests for a donation
        function viewRequests(donationId) {
            currentDonationId = donationId;
            const modal = new bootstrap.Modal(document.getElementById('requestsModal'));
            modal.show();
            
            loadRequests(donationId);
        }

        // Load requests for a donation
        function loadRequests(donationId) {
            const container = document.getElementById('requests-container');
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading requests...</p>
                </div>
            `;

            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_requests&donation_id=${donationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayRequests(data.requests);
                } else {
                    showNotification('Failed to load requests: ' + data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while loading requests.', 'error');
            });
        }

        // Display requests
        function displayRequests(requests) {
            const container = document.getElementById('requests-container');
            
            if (requests.length === 0) {
                container.innerHTML = `
                    <div class="text-center py-4">
                        <i class="bi bi-people text-muted" style="font-size: 2rem;"></i>
                        <h6 class="text-muted mt-2">No requests yet</h6>
                        <p class="text-muted">No one has requested this food donation yet.</p>
                    </div>
                `;
                return;
            }

            container.innerHTML = requests.map(request => `
                <div class="card request-item mb-3 ${request.status}">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <h6 class="card-title">
                                    <i class="bi bi-person"></i> ${request.requester_name}
                                </h6>
                                <p class="card-text">${request.message}</p>
                                <div class="row">
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="bi bi-envelope"></i> ${request.requester_email}<br>
                                            <i class="bi bi-telephone"></i> ${request.requester_phone || 'Not provided'}
                                        </small>
                                    </div>
                                    <div class="col-md-6">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> ${new Date(request.reserved_at).toLocaleDateString()}<br>
                                            <i class="bi bi-clock"></i> ${new Date(request.reserved_at).toLocaleTimeString()}
                                        </small>
                                    </div>
                                </div>
                                ${request.admin_notes ? `
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <strong>Admin Notes:</strong> ${request.admin_notes}
                                        </small>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="col-md-4">
                                <div class="text-end">
                                    <span class="badge status-badge bg-${getRequestStatusColor(request.status)}">
                                        ${request.status.toUpperCase()}
                                    </span>
                                    <div class="mt-2">
                                        <small class="text-muted">Contact Info:</small><br>
                                        <strong>${request.contact_info}</strong>
                                    </div>
                                    ${canUpdateStatus() ? `
                                        <div class="mt-3">
                                            <button class="btn btn-outline-primary btn-sm" onclick="openStatusModal(${request.id})">
                                                <i class="bi bi-gear"></i> Update Status
                                            </button>
                                        </div>
                                    ` : ''}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `).join('');
        }

        // Get request status color
        function getRequestStatusColor(status) {
            switch(status) {
                case 'pending': return 'warning';
                case 'approved': return 'success';
                case 'rejected': return 'danger';
                case 'completed': return 'info';
                case 'cancelled': return 'secondary';
                default: return 'secondary';
            }
        }

        // Check if user can update status
        function canUpdateStatus() {
            return '<?php echo $_SESSION['role']; ?>' === 'admin' || '<?php echo $_SESSION['role']; ?>' === 'team officer';
        }

        // Open status update modal
        function openStatusModal(requestId) {
            currentRequestId = requestId;
            document.getElementById('request_id').value = requestId;
            document.getElementById('donation_id').value = currentDonationId;
            document.getElementById('status').value = '';
            document.getElementById('admin_notes').value = '';
            
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }

        // Update request status
        function updateRequestStatus() {
            const form = document.getElementById('statusForm');
            const formData = new FormData(form);
            formData.append('action', 'update_request_status');
            
            const submitBtn = document.querySelector('#statusModal .btn-primary');
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
                    bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
                    loadRequests(currentDonationId);
                    loadDonations();
                    updateStats();
                } else {
                    showNotification(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showNotification('An error occurred while updating status.', 'error');
            })
            .finally(() => {
                submitBtn.disabled = false;
                spinner.classList.remove('show');
            });
        }

        // Update statistics
        function updateStats() {
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_donation_stats'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const stats = data.stats;
                    document.getElementById('total-donations').textContent = stats.total_donations;
                    document.getElementById('total-requests').textContent = stats.total_requests;
                    document.getElementById('pending-requests').textContent = stats.pending_requests;
                    document.getElementById('total-views').textContent = stats.total_views;
                }
            })
            .catch(error => {
                console.error('Error updating stats:', error);
            });
        }

        // Refresh all data
        function refreshData() {
            loadDonations();
            updateStats();
        }

        // Show notification
        function showNotification(message, type) {
            const container = document.getElementById('notification-container');
            const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : 'alert-info';
            
            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show`;
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            container.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    </script>

<?php include 'footer.php'; ?>

<?php
session_start();

// Check if user is logged in and is a team officer
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

if ($_SESSION['role'] !== 'team officer' && $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

include '../config/db.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $request_id = intval($_POST['request_id'] ?? 0);
    $donation_id = intval($_POST['donation_id'] ?? 0);
    
    switch ($action) {
        case 'get_requests':
            // Get all donation requests with filters
            $status_filter = $_POST['status'] ?? '';
            $search_term = $_POST['search'] ?? '';
            $date_from = $_POST['date_from'] ?? '';
            $date_to = $_POST['date_to'] ?? '';
            
            $where_conditions = [];
            $params = [];
            $param_types = '';
            
            if (!empty($status_filter)) {
                $where_conditions[] = "fdr.status = ?";
                $params[] = $status_filter;
                $param_types .= 's';
            }
            
            if (!empty($search_term)) {
                $where_conditions[] = "(fd.title LIKE ? OR ua_requester.full_name LIKE ? OR ua_donor.full_name LIKE ?)";
                $search_param = "%$search_term%";
                $params[] = $search_param;
                $params[] = $search_param;
                $params[] = $search_param;
                $param_types .= 'sss';
            }
            
            if (!empty($date_from)) {
                $where_conditions[] = "DATE(fdr.reserved_at) >= ?";
                $params[] = $date_from;
                $param_types .= 's';
            }
            
            if (!empty($date_to)) {
                $where_conditions[] = "DATE(fdr.reserved_at) <= ?";
                $params[] = $date_to;
                $param_types .= 's';
            }
            
            $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
            
            $query = "
                SELECT fdr.*, 
                       fd.title as donation_title,
                       fd.description as donation_description,
                       fd.food_type,
                       fd.quantity,
                       fd.location_address,
                       fd.expiration_date,
                       ua_requester.full_name as requester_name,
                       ua_requester.email as requester_email,
                       ua_requester.phone_number as requester_phone,
                       ua_donor.full_name as donor_name,
                       ua_donor.email as donor_email,
                       ua_donor.phone_number as donor_phone
                FROM food_donation_reservations fdr
                JOIN food_donations fd ON fdr.donation_id = fd.id
                JOIN user_accounts ua_requester ON fdr.requester_id = ua_requester.user_id
                JOIN user_accounts ua_donor ON fd.user_id = ua_donor.user_id
                $where_clause
                ORDER BY fdr.reserved_at DESC
            ";
            
            $stmt = $conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($param_types, ...$params);
            }
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
                include 'email_notifications.php';
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
            
        case 'get_statistics':
            // Get request statistics
            $stmt = $conn->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_requests,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_requests,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_requests,
                    COUNT(CASE WHEN DATE(reserved_at) = CURDATE() THEN 1 END) as today_requests,
                    COUNT(CASE WHEN DATE(reserved_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_requests
                FROM food_donation_reservations
            ");
            $stmt->execute();
            $result = $stmt->get_result();
            $stats = $result->fetch_assoc();
            $stmt->close();
            
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'bulk_action':
            // Handle bulk actions (approve/reject multiple requests)
            $bulk_action = $_POST['bulk_action'] ?? '';
            $request_ids = $_POST['request_ids'] ?? [];
            $admin_notes = trim($_POST['admin_notes'] ?? '');
            
            if (empty($request_ids) || !is_array($request_ids)) {
                echo json_encode(['success' => false, 'message' => 'No requests selected.']);
                exit;
            }
            
            $valid_actions = ['approve', 'reject', 'complete', 'cancel'];
            if (!in_array($bulk_action, $valid_actions)) {
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
                exit;
            }
            
            $status_map = [
                'approve' => 'approved',
                'reject' => 'rejected',
                'complete' => 'completed',
                'cancel' => 'cancelled'
            ];
            
            $new_status = $status_map[$bulk_action];
            $placeholders = str_repeat('?,', count($request_ids) - 1) . '?';
            
            $stmt = $conn->prepare("
                UPDATE food_donation_reservations 
                SET status = ?, admin_notes = ?, updated_at = NOW(), updated_by = ?
                WHERE id IN ($placeholders)
            ");
            
            $params = [$new_status, $admin_notes, $_SESSION['user_id'], ...$request_ids];
            $param_types = 'ssi' . str_repeat('i', count($request_ids));
            $stmt->bind_param($param_types, ...$params);
            
            if ($stmt->execute()) {
                $affected_rows = $stmt->affected_rows;
                echo json_encode(['success' => true, 'message' => "Successfully updated $affected_rows request(s)."]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update requests.']);
            }
            $stmt->close();
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action.']);
            break;
    }
    exit;
}

// Get initial statistics
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total_requests,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_requests,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_requests,
        COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
        COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_requests,
        COUNT(CASE WHEN DATE(reserved_at) = CURDATE() THEN 1 END) as today_requests,
        COUNT(CASE WHEN DATE(reserved_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as week_requests
    FROM food_donation_reservations
");
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();
$stmt->close();
?>

<?php include 'header.php'; ?>
<?php include 'topbar.php'; ?>
<?php include 'sidebar.php'; ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Donation Requests Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Donation Requests</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->
    
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
        
        .request-card {
            border-left: 4px solid #007bff;
            transition: all 0.3s ease;
        }
        
        .request-card:hover {
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .request-card.pending {
            border-left-color: #ffc107;
        }
        
        .request-card.approved {
            border-left-color: #28a745;
        }
        
        .request-card.rejected {
            border-left-color: #dc3545;
        }
        
        .request-card.completed {
            border-left-color: #17a2b8;
        }
        
        .request-card.cancelled {
            border-left-color: #6c757d;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .bulk-actions {
            background: #e3f2fd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            display: none;
        }
        
        .bulk-actions.show {
            display: block;
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
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card stats-card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">Total Requests</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="total-requests"><?php echo $stats['total_requests']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-list-ul text-primary fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">Pending</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="pending-requests"><?php echo $stats['pending_requests']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-clock text-warning fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">Approved</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="approved-requests"><?php echo $stats['approved_requests']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-check-circle text-success fa-2x text-gray-300"></i>
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
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">Today's Requests</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800" id="today-requests"><?php echo $stats['today_requests']; ?></div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-calendar-day text-info fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters Section -->
                <div class="filter-section">
                    <h5 class="mb-3"><i class="bi bi-funnel"></i> Filters & Search</h5>
                    <form id="filterForm">
                        <div class="row">
                            <div class="col-md-3">
                                <label for="status_filter" class="form-label">Status</label>
                                <select class="form-select" id="status_filter" name="status">
                                    <option value="">All Status</option>
                                    <option value="pending">Pending</option>
                                    <option value="approved">Approved</option>
                                    <option value="rejected">Rejected</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="search_term" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search_term" name="search" placeholder="Search by title or name...">
                            </div>
                            <div class="col-md-2">
                                <label for="date_from" class="form-label">From Date</label>
                                <input type="date" class="form-control" id="date_from" name="date_from">
                            </div>
                            <div class="col-md-2">
                                <label for="date_to" class="form-label">To Date</label>
                                <input type="date" class="form-control" id="date_to" name="date_to">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="button" class="btn btn-primary" onclick="applyFilters()">
                                        <i class="bi bi-search"></i> Filter
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Bulk Actions -->
                <div class="bulk-actions" id="bulkActions">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <span id="selectedCount">0</span> request(s) selected
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group">
                                <button class="btn btn-success btn-sm" onclick="bulkAction('approve')">
                                    <i class="bi bi-check"></i> Approve
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="bulkAction('reject')">
                                    <i class="bi bi-x"></i> Reject
                                </button>
                                <button class="btn btn-info btn-sm" onclick="bulkAction('complete')">
                                    <i class="bi bi-check-circle"></i> Complete
                                </button>
                                <button class="btn btn-secondary btn-sm" onclick="bulkAction('cancel')">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Requests List -->
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="bi bi-list-ul"></i> Donation Requests
                        </h6>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadRequests()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
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
    </section>
</main><!-- End #main -->

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

<!-- Bulk Action Modal -->
<div class="modal fade" id="bulkActionModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-gear"></i> Bulk Action
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="bulkActionForm">
                    <input type="hidden" id="bulk_action" name="bulk_action">
                    <input type="hidden" id="request_ids" name="request_ids">
                    
                    <div class="mb-3">
                        <label for="bulk_admin_notes" class="form-label">Admin Notes (Optional)</label>
                        <textarea class="form-control" id="bulk_admin_notes" name="admin_notes" rows="3" 
                                  placeholder="Add any notes about this decision..."></textarea>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i>
                        This action will be applied to <span id="bulkCount">0</span> selected request(s).
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="confirmBulkAction()">
                    <span class="loading-spinner spinner-border spinner-border-sm me-2"></span>
                    Confirm Action
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Notification Container -->
<div id="notification-container" class="notification"></div>

<script>
    let currentRequestId = null;
    let selectedRequests = new Set();

    // Load requests on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadRequests();
    });

    // Load requests
    function loadRequests() {
        const container = document.getElementById('requests-container');
        container.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2">Loading requests...</p>
            </div>
        `;

        const formData = new FormData(document.getElementById('filterForm'));
        formData.append('action', 'get_requests');

        fetch(window.location.href, {
            method: 'POST',
            body: formData
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
                <div class="text-center py-5">
                    <i class="bi bi-list-ul text-muted" style="font-size: 3rem;"></i>
                    <h5 class="text-muted mt-3">No requests found</h5>
                    <p class="text-muted">No donation requests match your current filters.</p>
                </div>
            `;
            return;
        }

        container.innerHTML = requests.map(request => `
            <div class="card request-card mb-3 ${request.status}">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-1">
                            <div class="form-check">
                                <input class="form-check-input request-checkbox" type="checkbox" 
                                       value="${request.id}" onchange="toggleSelection(${request.id})">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <h5 class="card-title">${request.donation_title}</h5>
                            <p class="card-text">${request.donation_description}</p>
                            <div class="row">
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <strong>Requester:</strong> ${request.requester_name}<br>
                                        <strong>Email:</strong> ${request.requester_email}<br>
                                        <strong>Phone:</strong> ${request.requester_phone || 'Not provided'}<br>
                                        <strong>Requested:</strong> ${new Date(request.reserved_at).toLocaleDateString()}
                                    </small>
                                </div>
                                <div class="col-md-6">
                                    <small class="text-muted">
                                        <strong>Donor:</strong> ${request.donor_name}<br>
                                        <strong>Food Type:</strong> ${request.food_type}<br>
                                        <strong>Quantity:</strong> ${request.quantity}<br>
                                        <strong>Location:</strong> ${request.location_address}
                                    </small>
                                </div>
                            </div>
                            ${request.message ? `
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <strong>Message:</strong> ${request.message}
                                    </small>
                                </div>
                            ` : ''}
                            ${request.admin_notes ? `
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <strong>Admin Notes:</strong> ${request.admin_notes}
                                    </small>
                                </div>
                            ` : ''}
                        </div>
                        <div class="col-md-3">
                            <div class="text-end">
                                <span class="badge status-badge bg-${getRequestStatusColor(request.status)}">
                                    ${request.status.toUpperCase()}
                                </span>
                                <div class="mt-2">
                                    <small class="text-muted">Contact Info:</small><br>
                                    <strong>${request.contact_info}</strong>
                                </div>
                                <div class="mt-3">
                                    <button class="btn btn-outline-primary btn-sm" onclick="openStatusModal(${request.id})">
                                        <i class="bi bi-gear"></i> Update Status
                                    </button>
                                </div>
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

    // Toggle selection
    function toggleSelection(requestId) {
        if (selectedRequests.has(requestId)) {
            selectedRequests.delete(requestId);
        } else {
            selectedRequests.add(requestId);
        }
        updateBulkActions();
    }

    // Update bulk actions
    function updateBulkActions() {
        const count = selectedRequests.size;
        const bulkActions = document.getElementById('bulkActions');
        const selectedCount = document.getElementById('selectedCount');
        
        selectedCount.textContent = count;
        
        if (count > 0) {
            bulkActions.classList.add('show');
        } else {
            bulkActions.classList.remove('show');
        }
    }

    // Apply filters
    function applyFilters() {
        loadRequests();
    }

    // Open status update modal
    function openStatusModal(requestId) {
        currentRequestId = requestId;
        document.getElementById('request_id').value = requestId;
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
                loadRequests();
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

    // Bulk action
    function bulkAction(action) {
        if (selectedRequests.size === 0) {
            showNotification('Please select at least one request.', 'warning');
            return;
        }
        
        document.getElementById('bulk_action').value = action;
        document.getElementById('request_ids').value = Array.from(selectedRequests).join(',');
        document.getElementById('bulkCount').textContent = selectedRequests.size;
        document.getElementById('bulk_admin_notes').value = '';
        
        const modal = new bootstrap.Modal(document.getElementById('bulkActionModal'));
        modal.show();
    }

    // Confirm bulk action
    function confirmBulkAction() {
        const form = document.getElementById('bulkActionForm');
        const formData = new FormData(form);
        formData.append('action', 'bulk_action');
        
        const submitBtn = document.querySelector('#bulkActionModal .btn-primary');
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
                bootstrap.Modal.getInstance(document.getElementById('bulkActionModal')).hide();
                selectedRequests.clear();
                updateBulkActions();
                loadRequests();
                updateStats();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showNotification('An error occurred while processing bulk action.', 'error');
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
            body: 'action=get_statistics'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const stats = data.stats;
                document.getElementById('total-requests').textContent = stats.total_requests;
                document.getElementById('pending-requests').textContent = stats.pending_requests;
                document.getElementById('approved-requests').textContent = stats.approved_requests;
                document.getElementById('today-requests').textContent = stats.today_requests;
            }
        })
        .catch(error => {
            console.error('Error updating stats:', error);
        });
    }

    // Show notification
    function showNotification(message, type) {
        const container = document.getElementById('notification-container');
        const alertClass = type === 'success' ? 'alert-success' : type === 'error' ? 'alert-danger' : type === 'warning' ? 'alert-warning' : 'alert-info';
        
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

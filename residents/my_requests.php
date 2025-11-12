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
    $request_id = intval($_POST['request_id']);
    $user_id = $_SESSION['user_id'];
    
    try {
        switch ($action) {
            case 'cancel':
                // Check if request belongs to user
                $stmt = $conn->prepare("
                    SELECT id, status FROM food_donation_reservations 
                    WHERE id = ? AND requester_id = ?
                ");
                $stmt->bind_param('ii', $request_id, $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                $stmt->close();
                
                if (!$request) {
                    echo json_encode(['success' => false, 'message' => 'Request not found.']);
                    exit;
                }
                
                if ($request['status'] !== 'pending') {
                    echo json_encode(['success' => false, 'message' => 'Only pending requests can be cancelled.']);
                    exit;
                }
                
                // Cancel the request
                $stmt = $conn->prepare("
                    UPDATE food_donation_reservations 
                    SET status = 'cancelled' 
                    WHERE id = ? AND requester_id = ?
                ");
                $stmt->bind_param('ii', $request_id, $user_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Request cancelled successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to cancel request.']);
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

// Get user's requests
$stmt = $conn->prepare("
    SELECT fdr.*, fd.title, fd.description, fd.food_type, fd.quantity, 
           fd.location_address, fd.pickup_time_start, fd.pickup_time_end,
           fd.contact_method, fd.contact_info as donor_contact,
           donor.full_name as donor_name, donor.email as donor_email
    FROM food_donation_reservations fdr
    JOIN food_donations fd ON fdr.donation_id = fd.id
    JOIN user_accounts donor ON fd.user_id = donor.user_id
    WHERE fdr.requester_id = ?
    ORDER BY fdr.reserved_at DESC
");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$requests = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-hand-thumbs-up"></i> My Food Requests</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Food Sharing</li>
                <li class="breadcrumb-item active">My Requests</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-list-ul"></i> Your Food Requests
                        </h5>
                        <span class="badge bg-primary"><?php echo count($requests); ?> requests</span>
                    </div>
                    <div class="card-body">
                        <?php if (empty($requests)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-hand-thumbs-up text-muted" style="font-size: 3rem;"></i>
                                <h4 class="mt-3">No Requests Yet</h4>
                                <p class="text-muted">You haven't made any food requests yet.</p>
                                <a href="browse_donations.php" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Browse Available Food
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($requests as $request): ?>
                                    <div class="col-lg-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-3">
                                                    <h6 class="card-title"><?php echo htmlspecialchars($request['title']); ?></h6>
                                                    <?php
                                                    $status_class = '';
                                                    $status_icon = '';
                                                    $status_text = ucfirst($request['status']);
                                                    
                                                    switch ($request['status']) {
                                                        case 'pending':
                                                            $status_class = 'bg-warning';
                                                            $status_icon = 'bi-clock';
                                                            break;
                                                        case 'approved':
                                                            $status_class = 'bg-success';
                                                            $status_icon = 'bi-check-circle';
                                                            break;
                                                        case 'rejected':
                                                            $status_class = 'bg-danger';
                                                            $status_icon = 'bi-x-circle';
                                                            break;
                                                        case 'completed':
                                                            $status_class = 'bg-info';
                                                            $status_icon = 'bi-check2-all';
                                                            break;
                                                        case 'cancelled':
                                                            $status_class = 'bg-secondary';
                                                            $status_icon = 'bi-x-circle';
                                                            break;
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $status_class; ?>">
                                                        <i class="bi <?php echo $status_icon; ?>"></i> <?php echo $status_text; ?>
                                                    </span>
                                                </div>
                                                
                                                <p class="card-text text-muted small mb-3">
                                                    <i class="bi bi-person"></i> <strong>Donor:</strong> <?php echo htmlspecialchars($request['donor_name']); ?><br>
                                                    <i class="bi bi-tag"></i> <strong>Type:</strong> <?php echo ucfirst($request['food_type']); ?><br>
                                                    <i class="bi bi-box"></i> <strong>Quantity:</strong> <?php echo htmlspecialchars($request['quantity']); ?><br>
                                                    <i class="bi bi-geo-alt"></i> <strong>Location:</strong> <?php echo htmlspecialchars(substr($request['location_address'], 0, 40)); ?><?php echo strlen($request['location_address']) > 40 ? '...' : ''; ?>
                                                </p>
                                                
                                                <div class="mb-3">
                                                    <strong>Your Message:</strong>
                                                    <p class="text-muted small"><?php echo htmlspecialchars($request['message']); ?></p>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <strong>Your Contact:</strong>
                                                    <p class="text-muted small"><?php echo htmlspecialchars($request['contact_info']); ?></p>
                                                </div>
                                                
                                                <?php if ($request['status'] === 'approved'): ?>
                                                    <div class="alert alert-success alert-sm mb-3">
                                                        <i class="bi bi-check-circle"></i> 
                                                        <strong>Approved!</strong> Contact the donor to arrange pickup.
                                                        <br><strong>Donor Contact:</strong> <?php echo htmlspecialchars($request['donor_contact']); ?>
                                                    </div>
                                                <?php elseif ($request['status'] === 'rejected'): ?>
                                                    <div class="alert alert-danger alert-sm mb-3">
                                                        <i class="bi bi-x-circle"></i> 
                                                        <strong>Request Rejected</strong> - This donation is no longer available.
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        <i class="bi bi-calendar"></i> 
                                                        Requested: <?php echo date('M d, Y h:i A', strtotime($request['reserved_at'])); ?>
                                                    </small>
                                                    
                                                    <?php if ($request['status'] === 'pending'): ?>
                                                        <button class="btn btn-outline-danger btn-sm" 
                                                                onclick="cancelRequest(<?php echo $request['id']; ?>)">
                                                            <i class="bi bi-x"></i> Cancel
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
function cancelRequest(requestId) {
    if (confirm('Are you sure you want to cancel this request?')) {
        const formData = new FormData();
        formData.append('action', 'cancel');
        formData.append('request_id', requestId);
        
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
            showNotification('An error occurred while cancelling the request.', 'error');
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

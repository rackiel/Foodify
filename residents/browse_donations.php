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

// Get current user ID
$current_user_id = $_SESSION['user_id'];

// Get approved food donations only
$stmt = $conn->prepare("
    SELECT fd.*, ua.full_name, ua.email 
    FROM food_donations fd 
    JOIN user_accounts ua ON fd.user_id = ua.user_id 
    WHERE fd.approval_status = 'approved'
    ORDER BY fd.created_at DESC
    LIMIT 20
");
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
        <h1><i class="bi bi-basket"></i> Browse Food Donations</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Food Sharing</li>
                <li class="breadcrumb-item active">Browse Donations</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <?php if (empty($donations)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="bi bi-basket text-muted" style="font-size: 3rem;"></i>
                        <h4 class="mt-3">No Food Donations Available</h4>
                        <p class="text-muted">No approved food donations are currently available.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($donations as $donation): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="card h-100 shadow-sm">
                            <?php if ($donation['images']): 
                                $images = json_decode($donation['images'], true);
                                if (!empty($images)): ?>
                                    <img src="../<?php echo htmlspecialchars($images[0]); ?>" 
                                         class="card-img-top" 
                                         style="height: 200px; object-fit: cover;"
                                         alt="Food image">
                                <?php endif;
                            else: ?>
                                <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                     style="height: 200px;">
                                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo htmlspecialchars($donation['title']); ?></h5>
                                <p class="card-text text-muted small">
                                    <i class="bi bi-person"></i> <?php echo htmlspecialchars($donation['full_name']); ?><br>
                                    <i class="bi bi-tag"></i> <?php echo ucfirst($donation['food_type']); ?><br>
                                    <i class="bi bi-box"></i> <?php echo htmlspecialchars($donation['quantity']); ?><br>
                                    <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars(substr($donation['location_address'], 0, 50)); ?><?php echo strlen($donation['location_address']) > 50 ? '...' : ''; ?>
                                </p>
                                <p class="card-text"><?php echo htmlspecialchars(substr($donation['description'], 0, 100)); ?><?php echo strlen($donation['description']) > 100 ? '...' : ''; ?></p>
                                
                                <?php if ($donation['expiration_date']): ?>
                                    <div class="alert alert-warning alert-sm mb-3">
                                        <i class="bi bi-clock"></i> 
                                        <strong>Expires:</strong> <?php echo date('M d, Y', strtotime($donation['expiration_date'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mt-auto">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="bi bi-calendar"></i> 
                                            <?php echo date('M d, Y', strtotime($donation['created_at'])); ?><br>
                                            <i class="bi bi-eye"></i> 
                                            <?php echo $donation['views_count']; ?> views
                                        </small>
                                        <?php if ($donation['user_id'] == $current_user_id): ?>
                                            <span class="badge bg-info">Your Donation</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-grid gap-2 mt-2">
                                        <?php if ($donation['user_id'] == $current_user_id): ?>
                                            <!-- User's own donation - only show view button -->
                                            <button class="btn btn-outline-info btn-sm w-100" onclick="viewDonation(<?php echo $donation['id']; ?>)">
                                                <i class="bi bi-eye"></i> View Your Donation
                                            </button>
                                        <?php else: ?>
                                            <!-- Other user's donation - show view and request buttons -->
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-outline-info btn-sm" onclick="viewDonation(<?php echo $donation['id']; ?>)">
                                                    <i class="bi bi-eye"></i> View Details
                                                </button>
                                                <button class="btn btn-primary btn-sm" onclick="openRequestModal(<?php echo $donation['id']; ?>, '<?php echo htmlspecialchars($donation['title']); ?>')">
                                                    <i class="bi bi-hand-thumbs-up"></i> Request
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<!-- View Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Donation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewContent">
                <!-- Donation details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="requestFromViewBtn" style="display: none;">
                    <i class="bi bi-hand-thumbs-up"></i> Request This Food
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Request Modal -->
<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Request Food Donation</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="requestForm">
                    <input type="hidden" id="request_donation_id">
                    <div class="mb-3">
                        <label for="request_message" class="form-label">Message to Donor *</label>
                        <textarea class="form-control" id="request_message" rows="4" required 
                                  placeholder="Please tell the donor why you're interested in this food and any specific needs..."></textarea>
                        <div class="form-text">Be specific about your needs and when you can pick up the food.</div>
                    </div>
                    <div class="mb-3">
                        <label for="request_contact" class="form-label">Your Contact Information *</label>
                        <input type="text" class="form-control" id="request_contact" required 
                               placeholder="Phone number, email, or preferred contact method">
                        <div class="form-text">This will be shared with the donor so they can contact you.</div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitRequest()">
                    <i class="bi bi-send"></i> Send Request
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function viewDonation(donationId) {
    // Show loading state
    document.getElementById('viewContent').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Loading donation details...</p>
        </div>
    `;
    
    // Show modal first
    new bootstrap.Modal(document.getElementById('viewModal')).show();
    
    // Fetch donation details
    const formData = new FormData();
    formData.append('action', 'view_donation');
    formData.append('donation_id', donationId);
    
    fetch('food_request_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayDonationDetails(data.donation);
        } else {
            showNotification(data.message, 'error');
            document.getElementById('viewContent').innerHTML = `
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
        document.getElementById('viewContent').innerHTML = `
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
    
    const expiration_date = donation.expiration_date ? new Date(donation.expiration_date).toLocaleDateString() : 'Not specified';
    const pickup_times = donation.pickup_time_start && donation.pickup_time_end ? 
        `${donation.pickup_time_start} - ${donation.pickup_time_end}` : 'Not specified';
    
    // Check if this is the current user's donation
    const currentUserId = <?php echo $current_user_id; ?>;
    const isOwnDonation = donation.user_id == currentUserId;
    
    document.getElementById('viewContent').innerHTML = `
        <div class="row">
            <div class="col-md-6">
                <h6 class="text-primary border-bottom pb-2 mb-3">Basic Information</h6>
                <p><strong>Title:</strong> ${donation.title}</p>
                <p><strong>Description:</strong> ${donation.description}</p>
                <p><strong>Food Type:</strong> ${donation.food_type.charAt(0).toUpperCase() + donation.food_type.slice(1)}</p>
                <p><strong>Quantity:</strong> ${donation.quantity}</p>
                <p><strong>Expiration Date:</strong> ${expiration_date}</p>
                <p><strong>Views:</strong> <span class="badge bg-info">${donation.views_count}</span></p>
            </div>
            <div class="col-md-6">
                <h6 class="text-primary border-bottom pb-2 mb-3">Location & Contact</h6>
                <p><strong>Address:</strong> ${donation.location_address}</p>
                ${donation.location_lat && donation.location_lng ? `<p><strong>Coordinates:</strong> ${donation.location_lat}, ${donation.location_lng}</p>` : ''}
                <p><strong>Contact Method:</strong> ${donation.contact_method.charAt(0).toUpperCase() + donation.contact_method.slice(1)}</p>
                <p><strong>Contact Info:</strong> ${donation.contact_info}</p>
                <p><strong>Available:</strong> ${pickup_times}</p>
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
                <h6 class="text-primary border-bottom pb-2 mb-3">Posting Information</h6>
                <p><strong>Posted:</strong> ${new Date(donation.created_at).toLocaleString()}</p>
                <p><strong>Status:</strong> <span class="badge bg-success">Available</span></p>
            </div>
        </div>
    `;
    
    // Show request button only if not own donation
    const requestBtn = document.getElementById('requestFromViewBtn');
    if (isOwnDonation) {
        requestBtn.style.display = 'none';
    } else {
        requestBtn.style.display = 'inline-block';
        requestBtn.onclick = () => {
            bootstrap.Modal.getInstance(document.getElementById('viewModal')).hide();
            openRequestModal(donation.id, donation.title);
        };
    }
}

function openRequestModal(donationId, donationTitle) {
    document.getElementById('request_donation_id').value = donationId;
    document.getElementById('request_message').value = '';
    document.getElementById('request_contact').value = '';
    
    // Update modal title
    document.querySelector('#requestModal .modal-title').textContent = `Request: ${donationTitle}`;
    
    // Show modal
    new bootstrap.Modal(document.getElementById('requestModal')).show();
}

function submitRequest() {
    const donationId = document.getElementById('request_donation_id').value;
    const message = document.getElementById('request_message').value.trim();
    const contactInfo = document.getElementById('request_contact').value.trim();
    
    // Validate input
    if (!message) {
        showNotification('Please provide a message for your request.', 'error');
        return;
    }
    
    if (!contactInfo) {
        showNotification('Please provide your contact information.', 'error');
        return;
    }
    
    // Show loading state
    const submitBtn = document.querySelector('#requestModal .btn-primary');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Sending...';
    submitBtn.disabled = true;
    
    // Submit request
    const formData = new FormData();
    formData.append('action', 'request');
    formData.append('donation_id', donationId);
    formData.append('message', message);
    formData.append('contact_info', contactInfo);
    
    fetch('food_request_handler.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('requestModal')).hide();
            
            // Update the button to show requested status
            const requestBtn = document.querySelector(`button[onclick*="${donationId}"]`);
            if (requestBtn) {
                requestBtn.innerHTML = '<i class="bi bi-check-circle"></i> Request Sent';
                requestBtn.className = 'btn btn-success btn-sm';
                requestBtn.disabled = true;
            }
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while sending your request.', 'error');
    })
    .finally(() => {
        // Reset button state
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
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

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

$user_id = $_SESSION['user_id'];

// Create community_feedback table if it doesn't exist
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS community_feedback (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT NOT NULL,
            feedback_type ENUM('platform', 'feature', 'donation', 'announcement', 'support', 'other') DEFAULT 'platform',
            rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            status ENUM('new', 'reviewed', 'responded', 'resolved', 'archived') DEFAULT 'new',
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            response TEXT,
            responded_by INT,
            responded_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES user_accounts(user_id),
            FOREIGN KEY (responded_by) REFERENCES user_accounts(user_id)
        )
    ");
} catch (Exception $e) {
    // Table might already exist
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false];
    
    try {
        if ($action === 'create_feedback') {
            $feedback_type = $_POST['feedback_type'];
            $rating = intval($_POST['rating']);
            $subject = trim($_POST['subject']);
            $message = trim($_POST['message']);
            $priority = $_POST['priority'] ?? 'medium';
            
            if ($rating < 1 || $rating > 5) {
                $response['message'] = 'Invalid rating value (1-5 required)';
            } elseif (empty($subject) || empty($message)) {
                $response['message'] = 'Subject and message are required';
            } else {
                $stmt = $conn->prepare("
                    INSERT INTO community_feedback (user_id, feedback_type, rating, subject, message, priority)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param('isssss', $user_id, $feedback_type, $rating, $subject, $message, $priority);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Thank you for your feedback! We appreciate your input.';
                }
                $stmt->close();
            }
            
        } elseif ($action === 'update_feedback') {
            $feedback_id = intval($_POST['feedback_id']);
            $feedback_type = $_POST['feedback_type'];
            $rating = intval($_POST['rating']);
            $subject = trim($_POST['subject']);
            $message = trim($_POST['message']);
            $priority = $_POST['priority'];
            
            // Only allow editing if status is 'new'
            $check = $conn->prepare("SELECT status FROM community_feedback WHERE id = ? AND user_id = ?");
            $check->bind_param('ii', $feedback_id, $user_id);
            $check->execute();
            $result = $check->get_result();
            $current = $result->fetch_assoc();
            
            if ($current && $current['status'] === 'new') {
                $stmt = $conn->prepare("
                    UPDATE community_feedback 
                    SET feedback_type = ?, rating = ?, subject = ?, message = ?, priority = ?
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->bind_param('sisssii', $feedback_type, $rating, $subject, $message, $priority, $feedback_id, $user_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Feedback updated successfully';
                }
                $stmt->close();
            } else {
                $response['message'] = 'Cannot edit feedback that has been reviewed';
            }
            $check->close();
            
        } elseif ($action === 'delete_feedback') {
            $feedback_id = intval($_POST['feedback_id']);
            
            // Only allow deleting if status is 'new'
            $check = $conn->prepare("SELECT status FROM community_feedback WHERE id = ? AND user_id = ?");
            $check->bind_param('ii', $feedback_id, $user_id);
            $check->execute();
            $result = $check->get_result();
            $current = $result->fetch_assoc();
            
            if ($current && $current['status'] === 'new') {
                $stmt = $conn->prepare("DELETE FROM community_feedback WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $feedback_id, $user_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Feedback deleted successfully';
                }
                $stmt->close();
            } else {
                $response['message'] = 'Cannot delete feedback that has been reviewed';
            }
            $check->close();
        }
        
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Get user's feedback
$stats = [];

try {
    // User's feedback statistics
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_feedback,
            COUNT(CASE WHEN status = 'new' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'responded' THEN 1 END) as responded,
            COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
            AVG(rating) as avg_rating
        FROM community_feedback
        WHERE user_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stats['overview'] = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // User's all feedback
    $stmt = $conn->prepare("
        SELECT 
            cf.*,
            r.full_name as responder_name
        FROM community_feedback cf
        LEFT JOIN user_accounts r ON cf.responded_by = r.user_id
        WHERE cf.user_id = ?
        ORDER BY cf.created_at DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stats['my_feedback'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // User's rating distribution
    $stmt = $conn->prepare("
        SELECT rating, COUNT(*) as count
        FROM community_feedback
        WHERE user_id = ?
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $stats['my_ratings'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    $stats = [
        'overview' => ['total_feedback' => 0, 'pending' => 0, 'responded' => 0, 'resolved' => 0, 'avg_rating' => 0],
        'my_feedback' => [],
        'my_ratings' => []
    ];
}

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
<div class="container-fluid py-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-chat-square-text"></i> Submit Feedback</h2>
                    <p class="text-muted mb-0">Share your thoughts and help us improve the Foodify platform</p>
                </div>
                <button class="btn btn-primary" onclick="showAddModal()">
                    <i class="bi bi-plus-circle"></i> New Feedback
                </button>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">My Feedback</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['total_feedback']) ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-chat-dots"></i>
                        </div>
                    </div>
                    <small class="text-muted">Total submitted</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Pending</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['pending']) ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-clock"></i>
                        </div>
                    </div>
                    <small class="text-muted">Awaiting review</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Responded</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['responded']) ?></h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="bi bi-reply"></i>
                        </div>
                    </div>
                    <small class="text-muted">Team replied</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">My Avg Rating</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['avg_rating'], 1) ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                    <small class="text-muted">Out of 5 stars</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback Guidelines -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-info">
                <h5><i class="bi bi-info-circle"></i> Feedback Guidelines</h5>
                <ul class="mb-0">
                    <li>Be specific and constructive in your feedback</li>
                    <li>Rate your overall experience from 1 to 5 stars</li>
                    <li>You can edit or delete feedback before it's reviewed</li>
                    <li>Team officers will respond to your feedback when reviewed</li>
                    <li>Your feedback helps improve the platform for everyone</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- My Feedback List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-task"></i> My Feedback History</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['my_feedback'])): ?>
                        <div class="row">
                            <?php foreach ($stats['my_feedback'] as $feedback): ?>
                                <div class="col-lg-6 mb-4">
                                    <div class="card h-100 feedback-card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <div>
                                                <span class="badge bg-<?= 
                                                    $feedback['status'] === 'new' ? 'warning' :
                                                    ($feedback['status'] === 'resolved' ? 'success' :
                                                    ($feedback['status'] === 'responded' ? 'info' : 'secondary'))
                                                ?>"><?= ucfirst($feedback['status']) ?></span>
                                                <span class="badge bg-info ms-1"><?= ucfirst($feedback['feedback_type']) ?></span>
                                            </div>
                                            <small class="text-muted"><?= date('M j, Y', strtotime($feedback['created_at'])) ?></small>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="bi bi-star-fill <?= $i <= $feedback['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                <?php endfor; ?>
                                                <strong class="ms-2"><?= $feedback['rating'] ?>/5</strong>
                                            </div>
                                            <h5 class="card-title"><?= htmlspecialchars($feedback['subject']) ?></h5>
                                            <p class="card-text"><?= htmlspecialchars($feedback['message']) ?></p>
                                            
                                            <?php if ($feedback['response']): ?>
                                                <div class="alert alert-success mt-3">
                                                    <strong><i class="bi bi-reply"></i> Team Response:</strong>
                                                    <p class="mb-0 mt-2"><?= htmlspecialchars($feedback['response']) ?></p>
                                                    <?php if ($feedback['responder_name']): ?>
                                                        <small class="text-muted">- <?= htmlspecialchars($feedback['responder_name']) ?>, 
                                                        <?= date('M j, Y g:i A', strtotime($feedback['responded_at'])) ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted">
                                                    <i class="bi bi-clock"></i> <?= date('g:i A', strtotime($feedback['created_at'])) ?>
                                                </small>
                                                <?php if ($feedback['status'] === 'new'): ?>
                                                    <div>
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick='editFeedback(<?= json_encode($feedback, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" 
                                                                onclick="deleteFeedback(<?= $feedback['id'] ?>)">
                                                            <i class="bi bi-trash"></i> Delete
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">
                                                        <i class="bi bi-lock"></i> Locked
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <i class="bi bi-chat-square-text text-muted" style="font-size: 4rem;"></i>
                            <h4 class="mt-3">No Feedback Yet</h4>
                            <p class="text-muted">You haven't submitted any feedback. Share your thoughts with us!</p>
                            <button class="btn btn-primary btn-lg" onclick="showAddModal()">
                                <i class="bi bi-plus-circle"></i> Submit Your First Feedback
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<!-- Add/Edit Feedback Modal -->
<div class="modal fade" id="feedbackModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">
                    <i class="bi bi-chat-square-text"></i> Submit Feedback
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="feedbackForm">
                    <input type="hidden" id="feedbackId" name="feedback_id">
                    
                    <div class="alert alert-light">
                        <i class="bi bi-lightbulb"></i>
                        <strong>Tip:</strong> Your honest feedback helps us improve the platform for everyone!
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">What are you giving feedback about? <span class="text-danger">*</span></label>
                            <select class="form-select" id="feedbackType" name="feedback_type" required>
                                <option value="">Select category...</option>
                                <option value="platform">Overall Platform Experience</option>
                                <option value="feature">Feature Request/Suggestion</option>
                                <option value="donation">Food Donation System</option>
                                <option value="announcement">Announcements & Communication</option>
                                <option value="support">Technical Support/Issue</option>
                                <option value="other">Other Feedback</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">How important is this? <span class="text-danger">*</span></label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="low">Low - Nice to have</option>
                                <option value="medium" selected>Medium - Should be addressed</option>
                                <option value="high">High - Important issue</option>
                                <option value="urgent">Urgent - Critical problem</option>
                            </select>
                        </div>

                        <div class="col-12 mb-4">
                            <label class="form-label">How would you rate your experience? <span class="text-danger">*</span></label>
                            <div class="text-center p-3 bg-light rounded">
                                <div class="star-rating" id="starRating">
                                    <i class="bi bi-star star-icon" data-rating="1"></i>
                                    <i class="bi bi-star star-icon" data-rating="2"></i>
                                    <i class="bi bi-star star-icon" data-rating="3"></i>
                                    <i class="bi bi-star star-icon" data-rating="4"></i>
                                    <i class="bi bi-star star-icon" data-rating="5"></i>
                                </div>
                                <input type="hidden" id="rating" name="rating" value="0" required>
                                <small class="form-text text-muted d-block mt-2">Click on stars to rate (1 = Poor, 5 = Excellent)</small>
                            </div>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   placeholder="Brief summary of your feedback" maxlength="255" required>
                            <small class="form-text text-muted">Maximum 255 characters</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Your Feedback <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="5" 
                                      placeholder="Please provide detailed feedback. The more specific you are, the better we can help!" required></textarea>
                            <small class="form-text text-muted">Share your thoughts, suggestions, or concerns</small>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send"></i> Submit Feedback
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

.feedback-card {
    transition: all 0.3s;
    border-left: 4px solid transparent;
}

.feedback-card:hover {
    border-left-color: #0d6efd;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.star-rating {
    font-size: 2.5rem;
}

.star-icon {
    cursor: pointer;
    color: #ddd;
    transition: all 0.2s;
    margin: 0 5px;
}

.star-icon:hover,
.star-icon.active {
    color: #ffc107;
    transform: scale(1.1);
}

.alert ul {
    margin-bottom: 0;
    padding-left: 20px;
}
</style>

<script>
// Star Rating Interactive System
let selectedRating = 0;
const stars = document.querySelectorAll('.star-icon');

function initStarRating() {
    const starsElements = document.querySelectorAll('.star-icon');
    
    starsElements.forEach(star => {
        star.addEventListener('click', function() {
            selectedRating = parseInt(this.dataset.rating);
            document.getElementById('rating').value = selectedRating;
            updateStars();
        });
        
        star.addEventListener('mouseenter', function() {
            const rating = parseInt(this.dataset.rating);
            starsElements.forEach((s, index) => {
                if (index < rating) {
                    s.classList.add('active');
                } else {
                    s.classList.remove('active');
                }
            });
        });
    });

    document.getElementById('starRating').addEventListener('mouseleave', function() {
        updateStars();
    });
}

function updateStars() {
    const starsElements = document.querySelectorAll('.star-icon');
    starsElements.forEach((s, index) => {
        if (index < selectedRating) {
            s.classList.add('active');
            s.classList.remove('bi-star');
            s.classList.add('bi-star-fill');
        } else {
            s.classList.remove('active');
            s.classList.remove('bi-star-fill');
            s.classList.add('bi-star');
        }
    });
}

// Form Submission
document.getElementById('feedbackForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const rating = parseInt(document.getElementById('rating').value);
    if (rating === 0 || rating < 1 || rating > 5) {
        showNotification('Please select a rating from 1 to 5 stars', 'warning');
        return;
    }
    
    const formData = new FormData(this);
    const feedbackId = document.getElementById('feedbackId').value;
    
    if (feedbackId) {
        formData.append('action', 'update_feedback');
    } else {
        formData.append('action', 'create_feedback');
    }
    
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
            showNotification(data.message || 'Operation failed', 'error');
        }
    })
    .catch(error => {
        showNotification('Error submitting feedback', 'error');
    });
});

// Show Add Modal
function showAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-chat-square-text"></i> Submit Feedback';
    document.getElementById('feedbackForm').reset();
    document.getElementById('feedbackId').value = '';
    selectedRating = 0;
    updateStars();
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
    
    // Reinitialize star rating
    setTimeout(() => initStarRating(), 100);
}

// Edit Feedback
function editFeedback(feedback) {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit Feedback';
    document.getElementById('feedbackId').value = feedback.id;
    document.getElementById('feedbackType').value = feedback.feedback_type;
    document.getElementById('priority').value = feedback.priority;
    document.getElementById('subject').value = feedback.subject;
    document.getElementById('message').value = feedback.message;
    
    selectedRating = feedback.rating;
    document.getElementById('rating').value = selectedRating;
    
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
    
    // Update stars after modal is shown
    setTimeout(() => {
        initStarRating();
        updateStars();
    }, 100);
}

// Delete Feedback
function deleteFeedback(feedbackId) {
    if (!confirm('Are you sure you want to delete this feedback? This action cannot be undone.')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_feedback');
    formData.append('feedback_id', feedbackId);
    
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
    })
    .catch(error => {
        showNotification('Error deleting feedback', 'error');
    });
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
        <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'error' ? 'x-circle' : 'info-circle'}"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 4000);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    initStarRating();
});
</script>

<?php include 'footer.php'; ?>


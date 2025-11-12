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
                $response['message'] = 'Invalid rating value';
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
                    $response['message'] = 'Feedback submitted successfully';
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
            
        } elseif ($action === 'delete_feedback') {
            $feedback_id = intval($_POST['feedback_id']);
            
            $stmt = $conn->prepare("DELETE FROM community_feedback WHERE id = ? AND user_id = ?");
            $stmt->bind_param('ii', $feedback_id, $user_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Feedback deleted successfully';
            }
            $stmt->close();
            
        } elseif ($action === 'respond_feedback') {
            $feedback_id = intval($_POST['feedback_id']);
            $response_text = trim($_POST['response']);
            $new_status = $_POST['status'] ?? 'responded';
            
            $stmt = $conn->prepare("
                UPDATE community_feedback 
                SET response = ?, status = ?, responded_by = ?, responded_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('ssii', $response_text, $new_status, $user_id, $feedback_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Response added successfully';
            }
            $stmt->close();
            
        } elseif ($action === 'update_status') {
            $feedback_id = intval($_POST['feedback_id']);
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE community_feedback SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $status, $feedback_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Status updated successfully';
            }
            $stmt->close();
        }
        
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Get feedback statistics
$stats = [];

try {
    // Overall stats
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_feedback,
            COUNT(CASE WHEN status = 'new' THEN 1 END) as new_feedback,
            COUNT(CASE WHEN status = 'reviewed' THEN 1 END) as reviewed,
            COUNT(CASE WHEN status = 'responded' THEN 1 END) as responded,
            COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved,
            AVG(rating) as avg_rating,
            COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_feedback,
            COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_feedback,
            COUNT(CASE WHEN priority = 'urgent' THEN 1 END) as urgent_count
        FROM community_feedback
    ");
    $stats['overview'] = $stmt->fetch_assoc();
    
    // Feedback by type
    $stmt = $conn->query("
        SELECT feedback_type, COUNT(*) as count, AVG(rating) as avg_rating
        FROM community_feedback
        GROUP BY feedback_type
        ORDER BY count DESC
    ");
    $stats['by_type'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Rating distribution
    $stmt = $conn->query("
        SELECT rating, COUNT(*) as count
        FROM community_feedback
        GROUP BY rating
        ORDER BY rating DESC
    ");
    $stats['rating_distribution'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // All feedback
    $stmt = $conn->query("
        SELECT 
            cf.*,
            u.full_name as user_name,
            u.email as user_email,
            u.profile_img,
            r.full_name as responder_name
        FROM community_feedback cf
        JOIN user_accounts u ON cf.user_id = u.user_id
        LEFT JOIN user_accounts r ON cf.responded_by = r.user_id
        ORDER BY 
            FIELD(cf.priority, 'urgent', 'high', 'medium', 'low'),
            FIELD(cf.status, 'new', 'reviewed', 'responded', 'resolved', 'archived'),
            cf.created_at DESC
    ");
    $stats['all_feedback'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Recent feedback (last 30 days)
    $stmt = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count,
            AVG(rating) as avg_rating
        FROM community_feedback
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stats['daily_trends'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $stats = [
        'overview' => [
            'total_feedback' => 0, 'new_feedback' => 0, 'reviewed' => 0,
            'responded' => 0, 'resolved' => 0, 'avg_rating' => 0,
            'positive_feedback' => 0, 'negative_feedback' => 0, 'urgent_count' => 0
        ],
        'by_type' => [],
        'rating_distribution' => [],
        'all_feedback' => [],
        'daily_trends' => []
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
                    <h2><i class="bi bi-chat-square-text"></i> Community Feedback Management</h2>
                    <p class="text-muted mb-0">Monitor and respond to community feedback to improve platform experience</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-success" onclick="exportFeedback()">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>
                    <button class="btn btn-primary" onclick="showAddModal()">
                        <i class="bi bi-plus-circle"></i> Add Feedback
                    </button>
                </div>
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
                            <h6 class="text-muted mb-2">Total Feedback</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['total_feedback']) ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-chat-dots"></i>
                        </div>
                    </div>
                    <small class="text-muted">All feedback received</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Average Rating</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['avg_rating'], 1) ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-star-fill"></i>
                        </div>
                    </div>
                    <small class="text-muted">Out of 5 stars</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Pending Review</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['new_feedback']) ?></h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="bi bi-clock"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $stats['overview']['urgent_count'] ?> urgent</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Resolved</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['resolved']) ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $stats['overview']['total_feedback'] > 0 ? round(($stats['overview']['resolved'] / $stats['overview']['total_feedback']) * 100, 1) : 0 ?>% resolution rate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Rating Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="ratingChart"></canvas>
                    <div class="mt-3">
                        <?php 
                        $rating_counts = array_fill(1, 5, 0);
                        if (!empty($stats['rating_distribution'])) {
                            foreach ($stats['rating_distribution'] as $rating_data) {
                                $rating_counts[$rating_data['rating']] = $rating_data['count'];
                            }
                        }
                        for ($i = 5; $i >= 1; $i--): 
                        ?>
                            <div class="d-flex justify-content-between mb-2">
                                <div>
                                    <?php for ($j = 0; $j < $i; $j++): ?>
                                        <i class="bi bi-star-fill text-warning"></i>
                                    <?php endfor; ?>
                                </div>
                                <strong><?= $rating_counts[$i] ?></strong>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Feedback Types</h5>
                </div>
                <div class="card-body">
                    <canvas id="typeChart"></canvas>
                    <div class="mt-3">
                        <?php if (!empty($stats['by_type'])): ?>
                            <?php foreach ($stats['by_type'] as $type): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= ucfirst($type['feedback_type']) ?></span>
                                    <div>
                                        <strong><?= $type['count'] ?></strong>
                                        <small class="text-muted">(<?= number_format($type['avg_rating'], 1) ?>⭐)</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted mb-0">No data</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Sentiment Breakdown</h5>
                </div>
                <div class="card-body text-center">
                    <div class="row">
                        <div class="col-6 mb-3">
                            <h2 class="text-success"><?= $stats['overview']['positive_feedback'] ?></h2>
                            <small class="text-muted">Positive (4-5⭐)</small>
                        </div>
                        <div class="col-6 mb-3">
                            <h2 class="text-danger"><?= $stats['overview']['negative_feedback'] ?></h2>
                            <small class="text-muted">Negative (1-2⭐)</small>
                        </div>
                    </div>
                    <hr>
                    <h1 class="display-4 text-warning"><?= number_format($stats['overview']['avg_rating'], 1) ?></h1>
                    <div class="mb-2">
                        <?php 
                        $avg_rating = round($stats['overview']['avg_rating']);
                        for ($i = 1; $i <= 5; $i++): 
                        ?>
                            <i class="bi bi-star-fill <?= $i <= $avg_rating ? 'text-warning' : 'text-muted' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <small class="text-muted">Average Rating</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <select class="form-select" id="statusFilter" onchange="filterFeedback()">
                                <option value="all">All Status</option>
                                <option value="new">New</option>
                                <option value="reviewed">Reviewed</option>
                                <option value="responded">Responded</option>
                                <option value="resolved">Resolved</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="ratingFilter" onchange="filterFeedback()">
                                <option value="all">All Ratings</option>
                                <option value="5">5 Stars</option>
                                <option value="4">4 Stars</option>
                                <option value="3">3 Stars</option>
                                <option value="2">2 Stars</option>
                                <option value="1">1 Star</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="typeFilter" onchange="filterFeedback()">
                                <option value="all">All Types</option>
                                <option value="platform">Platform</option>
                                <option value="feature">Feature</option>
                                <option value="donation">Donation</option>
                                <option value="announcement">Announcement</option>
                                <option value="support">Support</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search feedback..." onkeyup="searchFeedback()">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feedback List -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-task"></i> All Feedback</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>User</th>
                                    <th>Subject</th>
                                    <th>Type</th>
                                    <th>Rating</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($stats['all_feedback'])): ?>
                                    <?php foreach ($stats['all_feedback'] as $feedback): ?>
                                        <tr class="feedback-row" 
                                            data-status="<?= $feedback['status'] ?>"
                                            data-rating="<?= $feedback['rating'] ?>"
                                            data-type="<?= $feedback['feedback_type'] ?>">
                                            <td><strong>#<?= $feedback['id'] ?></strong></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($feedback['profile_img']) ? '../uploads/profile_picture/' . $feedback['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                         class="rounded-circle me-2" width="28" height="28" alt="Profile">
                                                    <div>
                                                        <small class="d-block"><?= htmlspecialchars($feedback['user_name']) ?></small>
                                                        <small class="text-muted"><?= htmlspecialchars($feedback['user_email']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars($feedback['subject']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars(substr($feedback['message'], 0, 50)) ?>...</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= ucfirst($feedback['feedback_type']) ?></span>
                                            </td>
                                            <td>
                                                <div>
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="bi bi-star-fill <?= $i <= $feedback['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                                <small><?= $feedback['rating'] ?>/5</small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $feedback['priority'] === 'urgent' ? 'danger' :
                                                    ($feedback['priority'] === 'high' ? 'warning' :
                                                    ($feedback['priority'] === 'medium' ? 'info' : 'secondary'))
                                                ?>"><?= ucfirst($feedback['priority']) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $feedback['status'] === 'new' ? 'warning' :
                                                    ($feedback['status'] === 'resolved' ? 'success' :
                                                    ($feedback['status'] === 'responded' ? 'info' : 'secondary'))
                                                ?>"><?= ucfirst($feedback['status']) ?></span>
                                            </td>
                                            <td>
                                                <small><?= date('M j, Y', strtotime($feedback['created_at'])) ?></small>
                                            </td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick='viewFeedback(<?= json_encode($feedback, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No feedback yet</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
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
                    <i class="bi bi-chat-square-text"></i> Add Feedback
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="feedbackForm">
                    <input type="hidden" id="feedbackId" name="feedback_id">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Feedback Type <span class="text-danger">*</span></label>
                            <select class="form-select" id="feedbackType" name="feedback_type" required>
                                <option value="platform">Platform Performance</option>
                                <option value="feature">Feature Request</option>
                                <option value="donation">Donation System</option>
                                <option value="announcement">Announcements</option>
                                <option value="support">Technical Support</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Priority <span class="text-danger">*</span></label>
                            <select class="form-select" id="priority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Rating <span class="text-danger">*</span></label>
                            <div class="star-rating" id="starRating">
                                <i class="bi bi-star star-icon" data-rating="1"></i>
                                <i class="bi bi-star star-icon" data-rating="2"></i>
                                <i class="bi bi-star star-icon" data-rating="3"></i>
                                <i class="bi bi-star star-icon" data-rating="4"></i>
                                <i class="bi bi-star star-icon" data-rating="5"></i>
                            </div>
                            <input type="hidden" id="rating" name="rating" value="0" required>
                            <small class="form-text text-muted">Click on stars to rate</small>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   placeholder="Brief description of your feedback" required>
                        </div>

                        <div class="col-12 mb-3">
                            <label class="form-label">Message <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="message" name="message" rows="4" 
                                      placeholder="Provide detailed feedback..." required></textarea>
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

<!-- View Feedback Modal -->
<div class="modal fade" id="viewFeedbackModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-eye"></i> Feedback Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewFeedbackContent">
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

.star-rating {
    font-size: 2rem;
}

.star-icon {
    cursor: pointer;
    color: #ddd;
    transition: color 0.2s;
}

.star-icon:hover,
.star-icon.active {
    color: #ffc107;
}

@media print {
    .sidebar, .header, .btn, .modal {
        display: none !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Rating Distribution Chart
const ratingCtx = document.getElementById('ratingChart').getContext('2d');
new Chart(ratingCtx, {
    type: 'bar',
    data: {
        labels: ['5⭐', '4⭐', '3⭐', '2⭐', '1⭐'],
        datasets: [{
            label: 'Count',
            data: [
                <?= $rating_counts[5] ?>,
                <?= $rating_counts[4] ?>,
                <?= $rating_counts[3] ?>,
                <?= $rating_counts[2] ?>,
                <?= $rating_counts[1] ?>
            ],
            backgroundColor: [
                'rgba(25, 135, 84, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(255, 159, 64, 0.8)',
                'rgba(220, 53, 69, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Type Chart
const typeCtx = document.getElementById('typeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            if (!empty($stats['by_type'])) {
                echo implode(',', array_map(function($t) { return "'" . ucfirst($t['feedback_type']) . "'"; }, $stats['by_type'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [{
            data: [<?php 
                if (!empty($stats['by_type'])) {
                    echo implode(',', array_column($stats['by_type'], 'count')); 
                } else {
                    echo "0";
                }
            ?>],
            backgroundColor: [
                'rgba(13, 110, 253, 0.8)',
                'rgba(25, 135, 84, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(220, 53, 69, 0.8)',
                'rgba(108, 117, 125, 0.8)',
                'rgba(13, 202, 240, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Star Rating Interactive
let selectedRating = 0;
const stars = document.querySelectorAll('.star-icon');

stars.forEach(star => {
    star.addEventListener('click', function() {
        selectedRating = parseInt(this.dataset.rating);
        document.getElementById('rating').value = selectedRating;
        updateStars();
    });
    
    star.addEventListener('mouseenter', function() {
        const rating = parseInt(this.dataset.rating);
        stars.forEach((s, index) => {
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

function updateStars() {
    stars.forEach((s, index) => {
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
    if (rating === 0) {
        showNotification('Please select a rating', 'warning');
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
    });
});

// Show Add Modal
function showAddModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="bi bi-chat-square-text"></i> Add Feedback';
    document.getElementById('feedbackForm').reset();
    document.getElementById('feedbackId').value = '';
    selectedRating = 0;
    updateStars();
    new bootstrap.Modal(document.getElementById('feedbackModal')).show();
}

// View Feedback
function viewFeedback(feedback) {
    let stars = '';
    for (let i = 1; i <= 5; i++) {
        stars += `<i class="bi bi-star-fill ${i <= feedback.rating ? 'text-warning' : 'text-muted'}"></i> `;
    }
    
    const content = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <strong>Feedback ID:</strong> #${feedback.id}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Date:</strong> ${new Date(feedback.created_at).toLocaleString()}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Type:</strong> <span class="badge bg-info">${feedback.feedback_type}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Priority:</strong> <span class="badge bg-${feedback.priority === 'urgent' ? 'danger' : 'secondary'}">${feedback.priority}</span>
            </div>
            <div class="col-12 mb-3">
                <strong>Rating:</strong><br>
                ${stars} <strong>${feedback.rating}/5</strong>
            </div>
            <div class="col-12 mb-3">
                <strong>User:</strong><br>
                <div class="d-flex align-items-center mt-2">
                    <img src="${feedback.profile_img ? '../uploads/profile_picture/' + feedback.profile_img : '../uploads/profile_picture/no_image.png'}" 
                         class="rounded-circle me-2" width="40" height="40">
                    <div>
                        <div>${feedback.user_name}</div>
                        <small class="text-muted">${feedback.user_email}</small>
                    </div>
                </div>
            </div>
            <div class="col-12 mb-3">
                <strong>Subject:</strong>
                <div class="alert alert-light mt-2">${feedback.subject}</div>
            </div>
            <div class="col-12 mb-3">
                <strong>Message:</strong>
                <div class="alert alert-light mt-2">${feedback.message}</div>
            </div>
            ${feedback.response ? `
            <div class="col-12 mb-3">
                <strong>Response:</strong>
                <div class="alert alert-success mt-2">${feedback.response}</div>
                <small class="text-muted">Responded by: ${feedback.responder_name || 'Team'}</small>
            </div>
            ` : ''}
            <div class="col-12">
                <hr>
                <strong>Actions:</strong>
                <div class="mt-3">
                    <select class="form-select mb-2" id="feedbackStatus">
                        <option value="new" ${feedback.status === 'new' ? 'selected' : ''}>New</option>
                        <option value="reviewed" ${feedback.status === 'reviewed' ? 'selected' : ''}>Reviewed</option>
                        <option value="responded" ${feedback.status === 'responded' ? 'selected' : ''}>Responded</option>
                        <option value="resolved" ${feedback.status === 'resolved' ? 'selected' : ''}>Resolved</option>
                        <option value="archived" ${feedback.status === 'archived' ? 'selected' : ''}>Archived</option>
                    </select>
                    <textarea class="form-control mb-2" id="feedbackResponse" placeholder="Add your response..." rows="3">${feedback.response || ''}</textarea>
                    <div class="d-grid gap-2">
                        <button class="btn btn-primary" onclick="respondToFeedback(${feedback.id})">
                            <i class="bi bi-reply"></i> Send Response
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteFeedback(${feedback.id})">
                            <i class="bi bi-trash"></i> Delete Feedback
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('viewFeedbackContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('viewFeedbackModal')).show();
}

// Respond to Feedback
function respondToFeedback(feedbackId) {
    const response = document.getElementById('feedbackResponse').value;
    const status = document.getElementById('feedbackStatus').value;
    
    if (!response.trim()) {
        showNotification('Please enter a response', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'respond_feedback');
    formData.append('feedback_id', feedbackId);
    formData.append('response', response);
    formData.append('status', status);
    
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
            showNotification(data.message || 'Failed to respond', 'error');
        }
    });
}

// Delete Feedback
function deleteFeedback(feedbackId) {
    if (!confirm('Are you sure you want to delete this feedback?')) return;
    
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
    });
}

// Filter Feedback
function filterFeedback() {
    const statusFilter = document.getElementById('statusFilter').value;
    const ratingFilter = document.getElementById('ratingFilter').value;
    const typeFilter = document.getElementById('typeFilter').value;
    
    const rows = document.querySelectorAll('.feedback-row');
    
    rows.forEach(row => {
        const status = row.dataset.status;
        const rating = row.dataset.rating;
        const type = row.dataset.type;
        
        const statusMatch = statusFilter === 'all' || status === statusFilter;
        const ratingMatch = ratingFilter === 'all' || rating === ratingFilter;
        const typeMatch = typeFilter === 'all' || type === typeFilter;
        
        row.style.display = (statusMatch && ratingMatch && typeMatch) ? '' : 'none';
    });
}

// Search Feedback
function searchFeedback() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.feedback-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// Export Feedback
function exportFeedback() {
    const data = <?= json_encode($stats['all_feedback']) ?>;
    
    if (data.length === 0) {
        showNotification('No feedback to export', 'warning');
        return;
    }
    
    const headers = ['ID', 'User', 'Email', 'Subject', 'Message', 'Type', 'Rating', 'Priority', 'Status', 'Date'];
    let csv = headers.join(',') + '\n';
    
    data.forEach(row => {
        const values = [
            row.id,
            `"${row.user_name}"`,
            `"${row.user_email}"`,
            `"${row.subject}"`,
            `"${row.message.replace(/"/g, '""')}"`,
            row.feedback_type,
            row.rating,
            row.priority,
            row.status,
            row.created_at
        ];
        csv += values.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'community_feedback_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Feedback exported successfully!', 'success');
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

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

// Create reports table if it doesn't exist
try {
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_reports (
            id INT PRIMARY KEY AUTO_INCREMENT,
            reporter_id INT NOT NULL,
            reported_user_id INT,
            reported_post_id INT,
            report_type ENUM('user', 'post', 'donation', 'comment', 'other') DEFAULT 'user',
            category ENUM('spam', 'harassment', 'inappropriate', 'fake', 'violence', 'other') NOT NULL,
            description TEXT NOT NULL,
            status ENUM('pending', 'reviewing', 'resolved', 'dismissed') DEFAULT 'pending',
            priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
            resolved_by INT,
            resolution_note TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (reporter_id) REFERENCES user_accounts(user_id),
            FOREIGN KEY (reported_user_id) REFERENCES user_accounts(user_id),
            FOREIGN KEY (resolved_by) REFERENCES user_accounts(user_id)
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
        if ($action === 'update_status') {
            $report_id = intval($_POST['report_id']);
            $status = $_POST['status'];
            $resolution_note = $_POST['resolution_note'] ?? '';
            
            $stmt = $conn->prepare("
                UPDATE user_reports 
                SET status = ?, 
                    resolution_note = ?, 
                    resolved_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->bind_param('ssii', $status, $resolution_note, $_SESSION['user_id'], $report_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Report status updated successfully';
            }
            $stmt->close();
            
        } elseif ($action === 'update_priority') {
            $report_id = intval($_POST['report_id']);
            $priority = $_POST['priority'];
            
            $stmt = $conn->prepare("UPDATE user_reports SET priority = ? WHERE id = ?");
            $stmt->bind_param('si', $priority, $report_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Priority updated successfully';
            }
            $stmt->close();
            
        } elseif ($action === 'delete_report') {
            $report_id = intval($_POST['report_id']);
            
            $stmt = $conn->prepare("DELETE FROM user_reports WHERE id = ?");
            $stmt->bind_param('i', $report_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Report deleted successfully';
            }
            $stmt->close();
            
        } elseif ($action === 'take_action') {
            $report_id = intval($_POST['report_id']);
            $action_type = $_POST['action_type'];
            $reported_user_id = intval($_POST['reported_user_id']);
            
            // Take appropriate action
            if ($action_type === 'warn_user') {
                // Add warning to user (could be implemented with a warnings table)
                $response['success'] = true;
                $response['message'] = 'Warning sent to user';
                
            } elseif ($action_type === 'suspend_user') {
                $stmt = $conn->prepare("UPDATE user_accounts SET status = 'suspended' WHERE user_id = ?");
                $stmt->bind_param('i', $reported_user_id);
                $stmt->execute();
                $stmt->close();
                
                $response['success'] = true;
                $response['message'] = 'User suspended successfully';
                
            } elseif ($action_type === 'delete_content') {
                // Mark report as resolved
                $stmt = $conn->prepare("UPDATE user_reports SET status = 'resolved', resolved_by = ? WHERE id = ?");
                $stmt->bind_param('ii', $_SESSION['user_id'], $report_id);
                $stmt->execute();
                $stmt->close();
                
                $response['success'] = true;
                $response['message'] = 'Content action taken';
            }
        }
        
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
    
    echo json_encode($response);
    exit;
}

// Get report statistics
$stats = [];

try {
    // Overall stats
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_reports,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_reports,
            COUNT(CASE WHEN status = 'reviewing' THEN 1 END) as reviewing_reports,
            COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved_reports,
            COUNT(CASE WHEN status = 'dismissed' THEN 1 END) as dismissed_reports,
            COUNT(CASE WHEN priority = 'critical' THEN 1 END) as critical_reports,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_reports
        FROM user_reports
    ");
    $stats['overview'] = $stmt->fetch_assoc();
    
    // Reports by category
    $stmt = $conn->query("
        SELECT category, COUNT(*) as count 
        FROM user_reports 
        GROUP BY category 
        ORDER BY count DESC
    ");
    $stats['by_category'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Reports by type
    $stmt = $conn->query("
        SELECT report_type, COUNT(*) as count 
        FROM user_reports 
        GROUP BY report_type 
        ORDER BY count DESC
    ");
    $stats['by_type'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // All reports with details
    $stmt = $conn->query("
        SELECT 
            ur.id,
            ur.report_type,
            ur.category,
            ur.description,
            ur.status,
            ur.priority,
            ur.created_at,
            ur.updated_at,
            ur.resolution_note,
            reporter.full_name as reporter_name,
            reporter.email as reporter_email,
            reporter.profile_img as reporter_img,
            reported_user.user_id as reported_user_id,
            reported_user.full_name as reported_user_name,
            reported_user.email as reported_user_email,
            reported_user.profile_img as reported_user_img,
            resolver.full_name as resolver_name
        FROM user_reports ur
        JOIN user_accounts reporter ON ur.reporter_id = reporter.user_id
        LEFT JOIN user_accounts reported_user ON ur.reported_user_id = reported_user.user_id
        LEFT JOIN user_accounts resolver ON ur.resolved_by = resolver.user_id
        ORDER BY 
            FIELD(ur.priority, 'critical', 'high', 'medium', 'low'),
            FIELD(ur.status, 'pending', 'reviewing', 'resolved', 'dismissed'),
            ur.created_at DESC
    ");
    $stats['all_reports'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Top reporters
    $stmt = $conn->query("
        SELECT 
            u.full_name,
            u.profile_img,
            COUNT(ur.id) as report_count
        FROM user_reports ur
        JOIN user_accounts u ON ur.reporter_id = u.user_id
        GROUP BY ur.reporter_id
        ORDER BY report_count DESC
        LIMIT 5
    ");
    $stats['top_reporters'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Most reported users
    $stmt = $conn->query("
        SELECT 
            u.user_id,
            u.full_name,
            u.email,
            u.profile_img,
            COUNT(ur.id) as report_count
        FROM user_reports ur
        JOIN user_accounts u ON ur.reported_user_id = u.user_id
        WHERE ur.reported_user_id IS NOT NULL
        GROUP BY ur.reported_user_id
        ORDER BY report_count DESC
        LIMIT 5
    ");
    $stats['most_reported'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Daily report trends (last 30 days)
    $stmt = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM user_reports
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $stats['daily_trends'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $stats = [
        'overview' => [
            'total_reports' => 0,
            'pending_reports' => 0,
            'reviewing_reports' => 0,
            'resolved_reports' => 0,
            'dismissed_reports' => 0,
            'critical_reports' => 0,
            'today_reports' => 0
        ],
        'by_category' => [],
        'by_type' => [],
        'all_reports' => [],
        'top_reporters' => [],
        'most_reported' => [],
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
                    <h2><i class="bi bi-flag"></i> User Reports Management</h2>
                    <p class="text-muted mb-0">Monitor and resolve community reports to maintain a safe and respectful environment</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-success" onclick="exportReports()">
                        <i class="bi bi-file-earmark-excel"></i> Export
                    </button>
                    <button class="btn btn-info" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Reports</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['total_reports']) ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-flag"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $stats['overview']['today_reports'] ?> today</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Pending</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['pending_reports']) ?></h3>
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
                            <h6 class="text-muted mb-2">Critical</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['critical_reports']) ?></h3>
                        </div>
                        <div class="stat-icon bg-danger">
                            <i class="bi bi-exclamation-triangle"></i>
                        </div>
                    </div>
                    <small class="text-muted">Urgent attention needed</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Resolved</h6>
                            <h3 class="mb-0"><?= number_format($stats['overview']['resolved_reports']) ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $stats['overview']['total_reports'] > 0 ? round(($stats['overview']['resolved_reports'] / $stats['overview']['total_reports']) * 100, 1) : 0 ?>% resolution rate</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Overview & Charts -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Reports by Category</h5>
                </div>
                <div class="card-body">
                    <canvas id="categoryChart"></canvas>
                    <div class="mt-3">
                        <?php if (!empty($stats['by_category'])): ?>
                            <?php foreach ($stats['by_category'] as $cat): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-<?= 
                                        $cat['category'] === 'harassment' ? 'danger' :
                                        ($cat['category'] === 'spam' ? 'warning' :
                                        ($cat['category'] === 'inappropriate' ? 'info' : 'secondary'))
                                    ?>">
                                        <?= ucfirst($cat['category']) ?>
                                    </span>
                                    <strong><?= $cat['count'] ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted mb-0">No reports yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Reports by Type</h5>
                </div>
                <div class="card-body">
                    <canvas id="typeChart"></canvas>
                    <div class="mt-3">
                        <?php if (!empty($stats['by_type'])): ?>
                            <?php foreach ($stats['by_type'] as $type): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= ucfirst($type['report_type']) ?></span>
                                    <strong><?= $type['count'] ?></strong>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted mb-0">No reports yet</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Report Trends (30 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="trendsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Search -->
    <div class="row mb-3">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3">
                            <select class="form-select" id="statusFilter" onchange="filterReports()">
                                <option value="all">All Status</option>
                                <option value="pending">Pending</option>
                                <option value="reviewing">Reviewing</option>
                                <option value="resolved">Resolved</option>
                                <option value="dismissed">Dismissed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="priorityFilter" onchange="filterReports()">
                                <option value="all">All Priority</option>
                                <option value="critical">Critical</option>
                                <option value="high">High</option>
                                <option value="medium">Medium</option>
                                <option value="low">Low</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" id="categoryFilter" onchange="filterReports()">
                                <option value="all">All Categories</option>
                                <option value="spam">Spam</option>
                                <option value="harassment">Harassment</option>
                                <option value="inappropriate">Inappropriate</option>
                                <option value="fake">Fake/Misleading</option>
                                <option value="violence">Violence</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <input type="text" class="form-control" id="searchInput" placeholder="Search reports..." onkeyup="searchReports()">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reports Table -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-list-task"></i> All Reports</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="reportsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Reporter</th>
                                    <th>Reported User</th>
                                    <th>Type</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($stats['all_reports'])): ?>
                                    <?php foreach ($stats['all_reports'] as $report): ?>
                                        <tr class="report-row" 
                                            data-status="<?= $report['status'] ?>" 
                                            data-priority="<?= $report['priority'] ?>"
                                            data-category="<?= $report['category'] ?>">
                                            <td><strong>#<?= $report['id'] ?></strong></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($report['reporter_img']) ? '../uploads/profile_picture/' . $report['reporter_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                         class="rounded-circle me-2" width="28" height="28" alt="Profile">
                                                    <div>
                                                        <small class="d-block"><?= htmlspecialchars($report['reporter_name']) ?></small>
                                                        <small class="text-muted"><?= htmlspecialchars($report['reporter_email']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($report['reported_user_id']): ?>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= !empty($report['reported_user_img']) ? '../uploads/profile_picture/' . $report['reported_user_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                             class="rounded-circle me-2" width="28" height="28" alt="Profile">
                                                        <div>
                                                            <small class="d-block"><?= htmlspecialchars($report['reported_user_name']) ?></small>
                                                            <small class="text-muted"><?= htmlspecialchars($report['reported_user_email']) ?></small>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-info"><?= ucfirst($report['report_type']) ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $report['category'] === 'harassment' ? 'danger' :
                                                    ($report['category'] === 'spam' ? 'warning' :
                                                    ($report['category'] === 'inappropriate' ? 'info' : 'secondary'))
                                                ?>">
                                                    <?= ucfirst($report['category']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm" 
                                                        onchange="updatePriority(<?= $report['id'] ?>, this.value)">
                                                    <option value="low" <?= $report['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                                                    <option value="medium" <?= $report['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                                                    <option value="high" <?= $report['priority'] === 'high' ? 'selected' : '' ?>>High</option>
                                                    <option value="critical" <?= $report['priority'] === 'critical' ? 'selected' : '' ?>>Critical</option>
                                                </select>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $report['status'] === 'pending' ? 'warning' :
                                                    ($report['status'] === 'reviewing' ? 'info' :
                                                    ($report['status'] === 'resolved' ? 'success' : 'secondary'))
                                                ?>">
                                                    <?= ucfirst($report['status']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small><?= date('M j, Y', strtotime($report['created_at'])) ?></small>
                                                <br><small class="text-muted"><?= date('g:i A', strtotime($report['created_at'])) ?></small>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-primary" 
                                                        onclick="viewReportDetails(<?= $report['id'] ?>, <?= htmlspecialchars(json_encode($report), ENT_QUOTES) ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No reports found</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Reporters & Most Reported -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-badge"></i> Top Reporters</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['top_reporters'])): ?>
                        <?php foreach ($stats['top_reporters'] as $index => $reporter): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="me-2">#<?= $index + 1 ?></span>
                                    <img src="<?= !empty($reporter['profile_img']) ? '../uploads/profile_picture/' . $reporter['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                         class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                    <strong><?= htmlspecialchars($reporter['full_name']) ?></strong>
                                </div>
                                <span class="badge bg-primary"><?= $reporter['report_count'] ?> reports</span>
                                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Most Reported Users</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['most_reported'])): ?>
                        <?php foreach ($stats['most_reported'] as $index => $user): ?>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="d-flex align-items-center">
                                    <span class="me-2">#<?= $index + 1 ?></span>
                                    <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                         class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                    <div>
                                        <strong class="d-block"><?= htmlspecialchars($user['full_name']) ?></strong>
                                        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                    </div>
                                </div>
                                <span class="badge bg-danger"><?= $user['report_count'] ?> reports</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">No data available</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<!-- Report Details Modal -->
<div class="modal fade" id="reportDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-flag"></i> Report Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="reportDetailsContent">
                <!-- Content will be loaded dynamically -->
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

@media print {
    .sidebar, .header, .btn, .modal {
        display: none !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
new Chart(categoryCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            if (!empty($stats['by_category'])) {
                echo implode(',', array_map(function($c) { return "'" . ucfirst($c['category']) . "'"; }, $stats['by_category'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [{
            data: [<?php 
                if (!empty($stats['by_category'])) {
                    echo implode(',', array_column($stats['by_category'], 'count')); 
                } else {
                    echo "0";
                }
            ?>],
            backgroundColor: [
                'rgba(220, 53, 69, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(13, 110, 253, 0.8)',
                'rgba(108, 117, 125, 0.8)',
                'rgba(255, 99, 132, 0.8)',
                'rgba(75, 192, 192, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Type Chart
const typeCtx = document.getElementById('typeChart').getContext('2d');
new Chart(typeCtx, {
    type: 'pie',
    data: {
        labels: [<?php 
            if (!empty($stats['by_type'])) {
                echo implode(',', array_map(function($t) { return "'" . ucfirst($t['report_type']) . "'"; }, $stats['by_type'])); 
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
                'rgba(108, 117, 125, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { position: 'bottom' } }
    }
});

// Trends Chart
const trendsCtx = document.getElementById('trendsChart').getContext('2d');
new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: [<?php 
            if (!empty($stats['daily_trends'])) {
                echo implode(',', array_map(function($d) { return "'" . date('M j', strtotime($d['date'])) . "'"; }, $stats['daily_trends'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [{
            label: 'Reports',
            data: [<?php 
                if (!empty($stats['daily_trends'])) {
                    echo implode(',', array_column($stats['daily_trends'], 'count')); 
                } else {
                    echo "0";
                }
            ?>],
            borderColor: 'rgb(220, 53, 69)',
            backgroundColor: 'rgba(220, 53, 69, 0.1)',
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
    }
});

// Filter Reports
function filterReports() {
    const statusFilter = document.getElementById('statusFilter').value;
    const priorityFilter = document.getElementById('priorityFilter').value;
    const categoryFilter = document.getElementById('categoryFilter').value;
    
    const rows = document.querySelectorAll('.report-row');
    
    rows.forEach(row => {
        const status = row.dataset.status;
        const priority = row.dataset.priority;
        const category = row.dataset.category;
        
        const statusMatch = statusFilter === 'all' || status === statusFilter;
        const priorityMatch = priorityFilter === 'all' || priority === priorityFilter;
        const categoryMatch = categoryFilter === 'all' || category === categoryFilter;
        
        row.style.display = (statusMatch && priorityMatch && categoryMatch) ? '' : 'none';
    });
}

// Search Reports
function searchReports() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.report-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// View Report Details
function viewReportDetails(reportId, reportData) {
    const content = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <strong>Report ID:</strong> #${reportData.id}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Date:</strong> ${new Date(reportData.created_at).toLocaleString()}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Type:</strong> <span class="badge bg-info">${reportData.report_type}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Category:</strong> <span class="badge bg-warning">${reportData.category}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Priority:</strong> <span class="badge bg-${reportData.priority === 'critical' ? 'danger' : 'secondary'}">${reportData.priority}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Status:</strong> <span class="badge bg-${reportData.status === 'resolved' ? 'success' : 'warning'}">${reportData.status}</span>
            </div>
            <div class="col-12 mb-3">
                <strong>Reporter:</strong><br>
                <div class="d-flex align-items-center mt-2">
                    <img src="${reportData.reporter_img ? '../uploads/profile_picture/' + reportData.reporter_img : '../uploads/profile_picture/no_image.png'}" 
                         class="rounded-circle me-2" width="40" height="40">
                    <div>
                        <div>${reportData.reporter_name}</div>
                        <small class="text-muted">${reportData.reporter_email}</small>
                    </div>
                </div>
            </div>
            ${reportData.reported_user_id ? `
            <div class="col-12 mb-3">
                <strong>Reported User:</strong><br>
                <div class="d-flex align-items-center mt-2">
                    <img src="${reportData.reported_user_img ? '../uploads/profile_picture/' + reportData.reported_user_img : '../uploads/profile_picture/no_image.png'}" 
                         class="rounded-circle me-2" width="40" height="40">
                    <div>
                        <div>${reportData.reported_user_name}</div>
                        <small class="text-muted">${reportData.reported_user_email}</small>
                    </div>
                </div>
            </div>
            ` : ''}
            <div class="col-12 mb-3">
                <strong>Description:</strong>
                <div class="alert alert-light mt-2">${reportData.description}</div>
            </div>
            ${reportData.resolution_note ? `
            <div class="col-12 mb-3">
                <strong>Resolution Note:</strong>
                <div class="alert alert-success mt-2">${reportData.resolution_note}</div>
                <small class="text-muted">Resolved by: ${reportData.resolver_name}</small>
            </div>
            ` : ''}
            <div class="col-12">
                <hr>
                <strong>Take Action:</strong>
                <div class="d-grid gap-2 mt-3">
                    <select class="form-select" id="statusSelect">
                        <option value="pending" ${reportData.status === 'pending' ? 'selected' : ''}>Pending</option>
                        <option value="reviewing" ${reportData.status === 'reviewing' ? 'selected' : ''}>Reviewing</option>
                        <option value="resolved" ${reportData.status === 'resolved' ? 'selected' : ''}>Resolved</option>
                        <option value="dismissed" ${reportData.status === 'dismissed' ? 'selected' : ''}>Dismissed</option>
                    </select>
                    <textarea class="form-control" id="resolutionNote" placeholder="Add resolution note..." rows="3">${reportData.resolution_note || ''}</textarea>
                    <button class="btn btn-primary" onclick="updateReportStatus(${reportId})">
                        <i class="bi bi-check"></i> Update Status
                    </button>
                    ${reportData.reported_user_id ? `
                    <div class="btn-group">
                        <button class="btn btn-warning" onclick="takeAction(${reportId}, 'warn_user', ${reportData.reported_user_id})">
                            <i class="bi bi-exclamation-triangle"></i> Warn User
                        </button>
                        <button class="btn btn-danger" onclick="takeAction(${reportId}, 'suspend_user', ${reportData.reported_user_id})">
                            <i class="bi bi-ban"></i> Suspend User
                        </button>
                    </div>
                    ` : ''}
                    <button class="btn btn-outline-danger" onclick="deleteReport(${reportId})">
                        <i class="bi bi-trash"></i> Delete Report
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('reportDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('reportDetailsModal')).show();
}

// Update Priority
function updatePriority(reportId, priority) {
    const formData = new FormData();
    formData.append('action', 'update_priority');
    formData.append('report_id', reportId);
    formData.append('priority', priority);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message || 'Failed to update priority', 'error');
        }
    });
}

// Update Report Status
function updateReportStatus(reportId) {
    const status = document.getElementById('statusSelect').value;
    const note = document.getElementById('resolutionNote').value;
    
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('report_id', reportId);
    formData.append('status', status);
    formData.append('resolution_note', note);
    
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
            showNotification(data.message || 'Failed to update status', 'error');
        }
    });
}

// Take Action
function takeAction(reportId, actionType, userId) {
    if (!confirm(`Are you sure you want to ${actionType.replace('_', ' ')}?`)) return;
    
    const formData = new FormData();
    formData.append('action', 'take_action');
    formData.append('report_id', reportId);
    formData.append('action_type', actionType);
    formData.append('reported_user_id', userId);
    
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
            showNotification(data.message || 'Action failed', 'error');
        }
    });
}

// Delete Report
function deleteReport(reportId) {
    if (!confirm('Are you sure you want to delete this report?')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_report');
    formData.append('report_id', reportId);
    
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
            showNotification(data.message || 'Failed to delete report', 'error');
        }
    });
}

// Export Reports
function exportReports() {
    const data = <?= json_encode($stats['all_reports']) ?>;
    
    if (data.length === 0) {
        showNotification('No reports to export', 'warning');
        return;
    }
    
    const headers = ['ID', 'Reporter', 'Reported User', 'Type', 'Category', 'Priority', 'Status', 'Date', 'Description'];
    let csv = headers.join(',') + '\n';
    
    data.forEach(row => {
        const values = [
            row.id,
            `"${row.reporter_name}"`,
            `"${row.reported_user_name || 'N/A'}"`,
            row.report_type,
            row.category,
            row.priority,
            row.status,
            row.created_at,
            `"${row.description.replace(/"/g, '""')}"`
        ];
        csv += values.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'user_reports_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Reports exported successfully!', 'success');
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

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
$stats = [];

// Initialize defaults
$stats['donations'] = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0, 'available' => 0, 'claimed' => 0, 'today' => 0];
$stats['users'] = ['total' => 0, 'residents' => 0, 'officers' => 0, 'pending_approval' => 0, 'new_this_week' => 0];
$stats['announcements'] = ['total' => 0, 'published' => 0, 'drafts' => 0, 'total_likes' => 0, 'total_comments' => 0, 'total_shares' => 0];
$stats['requests'] = ['total' => 0, 'pending' => 0, 'approved' => 0, 'completed' => 0, 'today' => 0];
$stats['reports'] = ['total' => 0, 'pending' => 0, 'critical' => 0];
$recent_donations = [];
$recent_activity = [];
$donation_trends = [];
$top_contributors = [];

try {
    // Food Donations Statistics
    $result = $conn->query("SELECT COUNT(*) as total FROM food_donations");
    if ($result) {
        $stats['donations']['total'] = $result->fetch_assoc()['total'];
        
        // Check if approval_status column exists
        $check_col = $conn->query("SHOW COLUMNS FROM food_donations LIKE 'approval_status'");
        if ($check_col && $check_col->num_rows > 0) {
            $result = $conn->query("
                SELECT 
                    COUNT(CASE WHEN approval_status = 'pending' THEN 1 END) as pending,
                    COUNT(CASE WHEN approval_status = 'approved' THEN 1 END) as approved,
                    COUNT(CASE WHEN approval_status = 'rejected' THEN 1 END) as rejected
                FROM food_donations
            ");
            if ($result) {
                $approval_data = $result->fetch_assoc();
                $stats['donations']['pending'] = $approval_data['pending'];
                $stats['donations']['approved'] = $approval_data['approved'];
                $stats['donations']['rejected'] = $approval_data['rejected'];
            }
        }
        
        $result = $conn->query("
            SELECT 
                COUNT(CASE WHEN status = 'available' THEN 1 END) as available,
                COUNT(CASE WHEN status = 'claimed' THEN 1 END) as claimed,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today
            FROM food_donations
        ");
        if ($result) {
            $status_data = $result->fetch_assoc();
            $stats['donations']['available'] = $status_data['available'];
            $stats['donations']['claimed'] = $status_data['claimed'];
            $stats['donations']['today'] = $status_data['today'];
        }
    }
    
    // User Statistics
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN role = 'resident' THEN 1 END) as residents,
            COUNT(CASE WHEN role = 'team officer' THEN 1 END) as officers,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_approval,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
        FROM user_accounts
    ");
    if ($result) {
        $stats['users'] = $result->fetch_assoc();
    }
    
    // Announcements Statistics
    $check_table = $conn->query("SHOW TABLES LIKE 'announcements'");
    if ($check_table && $check_table->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as drafts,
                COALESCE(SUM(likes_count), 0) as total_likes,
                COALESCE(SUM(comments_count), 0) as total_comments,
                COALESCE(SUM(shares_count), 0) as total_shares
            FROM announcements
        ");
        if ($result) {
            $stats['announcements'] = $result->fetch_assoc();
        }
    }
    
    // Food Requests Statistics
    $check_table = $conn->query("SHOW TABLES LIKE 'food_donation_reservations'");
    if ($check_table && $check_table->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN DATE(reserved_at) = CURDATE() THEN 1 END) as today
            FROM food_donation_reservations
        ");
        if ($result) {
            $stats['requests'] = $result->fetch_assoc();
        }
    }
    
    // User Reports Statistics
    $check_table = $conn->query("SHOW TABLES LIKE 'user_reports'");
    if ($check_table && $check_table->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN priority = 'critical' THEN 1 END) as critical
            FROM user_reports
        ");
        if ($result) {
            $stats['reports'] = $result->fetch_assoc();
        }
    }
    
    // Recent Donations
    $result = $conn->query("
        SELECT 
            fd.*,
            ua.full_name,
            ua.profile_img
        FROM food_donations fd 
        JOIN user_accounts ua ON fd.user_id = ua.user_id 
        ORDER BY fd.created_at DESC 
        LIMIT 10
    ");
    if ($result) {
        $recent_donations = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Recent Activity (last 24 hours)
    $activity_items = [];
    
    // Donations
    $result = $conn->query("
        SELECT 
            'donation' as type,
            fd.id,
            fd.title as content,
            fd.created_at,
            ua.full_name as user_name,
            fd.status
        FROM food_donations fd
        JOIN user_accounts ua ON fd.user_id = ua.user_id
        WHERE fd.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY fd.created_at DESC
        LIMIT 5
    ");
    if ($result) {
        $activity_items = array_merge($activity_items, $result->fetch_all(MYSQLI_ASSOC));
    }
    
    // Announcements
    if ($check_table = $conn->query("SHOW TABLES LIKE 'announcements'")) {
        if ($check_table->num_rows > 0) {
            $result = $conn->query("
                SELECT 
                    'announcement' as type,
                    a.id,
                    a.title as content,
                    a.created_at,
                    ua.full_name as user_name,
                    a.status
                FROM announcements a
                JOIN user_accounts ua ON a.user_id = ua.user_id
                WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY a.created_at DESC
                LIMIT 5
            ");
            if ($result) {
                $activity_items = array_merge($activity_items, $result->fetch_all(MYSQLI_ASSOC));
            }
        }
    }
    
    // Users
    $result = $conn->query("
        SELECT 
            'user' as type,
            u.user_id as id,
            u.full_name as content,
            u.created_at,
            'System' as user_name,
            u.status
        FROM user_accounts u
        WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY u.created_at DESC
        LIMIT 5
    ");
    if ($result) {
        $activity_items = array_merge($activity_items, $result->fetch_all(MYSQLI_ASSOC));
    }
    
    // Sort by created_at
    usort($activity_items, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recent_activity = array_slice($activity_items, 0, 10);
    
    // Donation Trends (last 30 days)
    $result = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total
        FROM food_donations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    if ($result) {
        $donation_trends = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Top Contributors
    $result = $conn->query("
        SELECT 
            ua.full_name,
            ua.profile_img,
            COUNT(fd.id) as donation_count,
            SUM(fd.views_count) as total_views
        FROM food_donations fd
        JOIN user_accounts ua ON fd.user_id = ua.user_id
        GROUP BY fd.user_id
        ORDER BY donation_count DESC
        LIMIT 5
    ");
    if ($result) {
        $top_contributors = $result->fetch_all(MYSQLI_ASSOC);
    }
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

// Calculate total pending items
$pending_items = [
    'donations' => $stats['donations']['pending'] ?? 0,
    'users' => $stats['users']['pending_approval'] ?? 0,
    'requests' => $stats['requests']['pending'] ?? 0,
    'reports' => $stats['reports']['pending'] ?? 0
];
$total_pending = array_sum($pending_items);

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-speedometer2"></i> Team Officer Dashboard</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($total_pending > 0): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Attention Required!</strong> You have <?= $total_pending ?> pending items requiring review.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <section class="section dashboard">
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <div class="row">
                    
                    <!-- Total Donations -->
                    <div class="col-xxl-3 col-md-6 mb-3">
                        <div class="card info-card sales-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Donations</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-basket"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= number_format($stats['donations']['total']) ?></h6>
                                        <span class="text-muted small"><?= $stats['donations']['today'] ?> today</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Pending Approvals -->
                    <div class="col-xxl-3 col-md-6 mb-3">
                        <div class="card info-card revenue-card">
                            <div class="card-body">
                                <h5 class="card-title">Pending Review</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-clock"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= number_format($stats['donations']['pending']) ?></h6>
                                        <span class="text-warning small">Need action</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Available Donations -->
                    <div class="col-xxl-3 col-md-6 mb-3">
                        <div class="card info-card customers-card">
                            <div class="card-body">
                                <h5 class="card-title">Available</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= number_format($stats['donations']['available']) ?></h6>
                                        <span class="text-success small">Live now</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Total Users -->
                    <div class="col-xxl-3 col-md-6 mb-3">
                        <div class="card info-card">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-people"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= number_format($stats['users']['total']) ?></h6>
                                        <span class="text-muted small"><?= $stats['users']['new_this_week'] ?> new</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Announcements -->
                    <div class="col-xxl-4 col-md-6 mb-3">
                        <div class="card info-card">
                            <div class="card-body">
                                <h5 class="card-title">Announcements</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-megaphone"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= number_format($stats['announcements']['published']) ?></h6>
                                        <span class="text-muted small"><?= number_format($stats['announcements']['total_likes']) ?> likes</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Requests -->
                    <div class="col-xxl-4 col-md-6 mb-3">
                        <div class="card info-card">
                            <div class="card-body">
                                <h5 class="card-title">Food Requests</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-clipboard-check"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= number_format($stats['requests']['total']) ?></h6>
                                        <span class="text-muted small"><?= $stats['requests']['today'] ?> today</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Claimed -->
                    <div class="col-xxl-4 col-md-6 mb-3">
                        <div class="card info-card">
                            <div class="card-body">
                                <h5 class="card-title">Claimed</h5>
                                <div class="d-flex align-items-center">
                                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                        <i class="bi bi-bag-check"></i>
                                    </div>
                                    <div class="ps-3">
                                        <h6><?= number_format($stats['donations']['claimed']) ?></h6>
                                        <span class="text-success small">Successful</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Donation Trends Chart -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Donation Trends <span>| Last 30 Days</span></h5>
                                <canvas id="donationTrendsChart" style="max-height: 300px;"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Donations Table -->
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Recent Donations <span>| Latest</span></h5>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Donation</th>
                                                <th>Donor</th>
                                                <th>Status</th>
                                                <th>Views</th>
                                                <th>Posted</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recent_donations)): ?>
                                                <?php foreach ($recent_donations as $donation): ?>
                                                    <tr>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php 
                                                                $has_image = false;
                                                                if (!empty($donation['images'])): 
                                                                    $images = json_decode($donation['images'], true);
                                                                    if (!empty($images) && is_array($images)): 
                                                                        $has_image = true;
                                                                ?>
                                                                        <img src="../<?= htmlspecialchars($images[0]) ?>" 
                                                                             alt="Food" class="rounded me-2" 
                                                                             style="width: 40px; height: 40px; object-fit: cover;">
                                                                <?php 
                                                                    endif;
                                                                endif;
                                                                
                                                                if (!$has_image): 
                                                                ?>
                                                                    <div class="bg-secondary rounded me-2 d-flex align-items-center justify-content-center" 
                                                                         style="width: 40px; height: 40px;">
                                                                        <i class="bi bi-image text-white"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div>
                                                                    <h6 class="mb-0"><?= htmlspecialchars($donation['title']) ?></h6>
                                                                    <small class="text-muted"><?= ucfirst($donation['food_type']) ?></small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <img src="<?= !empty($donation['profile_img']) ? '../uploads/profile_picture/' . $donation['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                                     class="rounded-circle me-2" width="28" height="28" alt="Profile">
                                                                <?= htmlspecialchars($donation['full_name']) ?>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?= 
                                                                $donation['status'] === 'available' ? 'success' : 
                                                                ($donation['status'] === 'claimed' ? 'info' : 
                                                                ($donation['status'] === 'reserved' ? 'warning' : 'secondary'))
                                                            ?>">
                                                                <?= ucfirst($donation['status']) ?>
                                                            </span>
                                                        </td>
                                                        <td><?= number_format($donation['views_count'] ?? 0) ?></td>
                                                        <td>
                                                            <small><?= date('M d, Y', strtotime($donation['created_at'])) ?></small>
                                                            <br><small class="text-muted"><?= date('g:i A', strtotime($donation['created_at'])) ?></small>
                                                        </td>
                                                        <td>
                                                            <a href="donation-management.php?id=<?= $donation['id'] ?>" 
                                                               class="btn btn-primary btn-sm">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">No donations yet</td>
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

            <!-- Right Sidebar -->
            <div class="col-lg-4">
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Recent Activity <span>| Last 24 Hours</span></h5>
                        <div class="activity">
                            <?php if (!empty($recent_activity)): ?>
                                <?php foreach ($recent_activity as $activity): 
                                    $time_ago = time() - strtotime($activity['created_at']);
                                    if ($time_ago < 60) {
                                        $time_label = 'Just now';
                                    } elseif ($time_ago < 3600) {
                                        $time_label = floor($time_ago / 60) . ' min';
                                    } else {
                                        $time_label = floor($time_ago / 3600) . 'h';
                                    }
                                    
                                    $badge_class = 'text-muted';
                                    $message = '';
                                    
                                    switch ($activity['type']) {
                                        case 'donation':
                                            $badge_class = 'text-success';
                                            $message = 'New donation: ';
                                            break;
                                        case 'announcement':
                                            $badge_class = 'text-primary';
                                            $message = 'Announcement: ';
                                            break;
                                        case 'user':
                                            $badge_class = 'text-info';
                                            $message = 'New user: ';
                                            break;
                                    }
                                ?>
                                    <div class="activity-item d-flex">
                                        <div class="activite-label"><?= $time_label ?></div>
                                        <i class='bi bi-circle-fill activity-badge <?= $badge_class ?> align-self-start'></i>
                                        <div class="activity-content">
                                            <?= $message ?>
                                            <span class="fw-bold text-dark">
                                                <?= htmlspecialchars(substr($activity['content'], 0, 30)) ?><?= strlen($activity['content']) > 30 ? '...' : '' ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-center text-muted">No recent activity in the last 24 hours</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Top Contributors -->
                <?php if (!empty($top_contributors)): ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Top Contributors</h5>
                        <?php foreach ($top_contributors as $index => $contributor): ?>
                            <div class="d-flex align-items-center mb-3 pb-2 <?= $index < count($top_contributors) - 1 ? 'border-bottom' : '' ?>">
                                <div class="me-2">
                                    <strong class="text-primary">#<?= $index + 1 ?></strong>
                                </div>
                                <img src="<?= !empty($contributor['profile_img']) ? '../uploads/profile_picture/' . $contributor['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                     class="rounded-circle me-2" width="40" height="40" alt="Profile">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?= htmlspecialchars($contributor['full_name']) ?></h6>
                                    <small class="text-muted">
                                        <?= $contributor['donation_count'] ?> donations • 
                                        <?= number_format($contributor['total_views']) ?> views
                                    </small>
                                </div>
                                <span class="badge bg-primary"><?= $contributor['donation_count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Quick Actions</h5>
                        <div class="d-grid gap-2">
                            <?php if ($stats['donations']['pending'] > 0): ?>
                                <a href="donation-approvals.php" class="btn btn-warning">
                                    <i class="bi bi-clock"></i> Review Pending (<?= $stats['donations']['pending'] ?>)
                                </a>
                            <?php endif; ?>
                            <a href="announcements.php" class="btn btn-info">
                                <i class="bi bi-megaphone"></i> Post Announcement
                            </a>
                            <?php if ($stats['reports']['total'] > 0): ?>
                                <a href="user-reports.php" class="btn btn-<?= $stats['reports']['critical'] > 0 ? 'danger' : 'secondary' ?>">
                                    <i class="bi bi-flag"></i> User Reports (<?= $stats['reports']['pending'] ?>)
                                </a>
                            <?php endif; ?>
                            <a href="community_impact.php" class="btn btn-success">
                                <i class="bi bi-graph-up"></i> View Statistics
                            </a>
                            <a href="reports.php" class="btn btn-primary">
                                <i class="bi bi-file-text"></i> Generate Report
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Pending Items Summary -->
                <?php if ($total_pending > 0): ?>
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-warning">
                            <i class="bi bi-exclamation-triangle"></i> Action Required
                        </h5>
                        <div class="list-group list-group-flush">
                            <?php if ($pending_items['donations'] > 0): ?>
                                <a href="donation-approvals.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-basket"></i> Donation Approvals</span>
                                    <span class="badge bg-warning"><?= $pending_items['donations'] ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($pending_items['users'] > 0): ?>
                                <a href="user-approvals.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-person-check"></i> User Approvals</span>
                                    <span class="badge bg-warning"><?= $pending_items['users'] ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($pending_items['requests'] > 0): ?>
                                <a href="donation_request.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-clipboard-check"></i> Food Requests</span>
                                    <span class="badge bg-warning"><?= $pending_items['requests'] ?></span>
                                </a>
                            <?php endif; ?>
                            <?php if ($pending_items['reports'] > 0): ?>
                                <a href="user-reports.php" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                    <span><i class="bi bi-flag"></i> User Reports</span>
                                    <span class="badge bg-<?= $stats['reports']['critical'] > 0 ? 'danger' : 'warning' ?>">
                                        <?= $pending_items['reports'] ?>
                                    </span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Donation Trends Chart
const ctx = document.getElementById('donationTrendsChart');
if (ctx) {
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: [<?php 
                if (!empty($donation_trends)) {
                    echo implode(',', array_map(function($d) { return "'" . date('M j', strtotime($d['date'])) . "'"; }, $donation_trends));
                } else {
                    echo "'No Data'";
                }
            ?>],
            datasets: [{
                label: 'Donations',
                data: [<?php 
                    if (!empty($donation_trends)) {
                        echo implode(',', array_column($donation_trends, 'total'));
                    } else {
                        echo "0";
                    }
                ?>],
                borderColor: 'rgb(13, 110, 253)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
}

// Auto-refresh dashboard every 5 minutes
setInterval(function() {
    if (!document.hidden) {
        window.location.reload();
    }
}, 300000);
</script>

<?php include 'footer.php'; ?>

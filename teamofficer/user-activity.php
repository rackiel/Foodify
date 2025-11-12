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

// Get user activity data
$activity = [];

try {
    // Overall user statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN role = 'resident' THEN 1 END) as residents,
            COUNT(CASE WHEN role = 'team officer' THEN 1 END) as officers,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as active_users,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_users,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 END) as new_users_month
        FROM user_accounts
    ");
    $activity['overview'] = $stmt->fetch_assoc();
    
    // Active users (with recent activity)
    $stmt = $conn->query("
        SELECT 
            u.user_id,
            u.full_name,
            u.email,
            u.profile_img,
            u.role as user_type,
            u.status,
            u.created_at,
            (
                SELECT COUNT(*) FROM announcements WHERE user_id = u.user_id
            ) as announcements_count,
            (
                SELECT COUNT(*) FROM food_donations WHERE user_id = u.user_id
            ) as donations_count,
            (
                SELECT COUNT(*) FROM announcement_likes WHERE user_id = u.user_id
            ) as likes_given,
            (
                SELECT COUNT(*) FROM announcement_comments WHERE user_id = u.user_id
            ) as comments_made,
            (
                SELECT MAX(created_at) FROM (
                    SELECT created_at FROM announcements WHERE user_id = u.user_id
                    UNION ALL
                    SELECT created_at FROM food_donations WHERE user_id = u.user_id
                    UNION ALL
                    SELECT created_at FROM announcement_likes WHERE user_id = u.user_id
                    UNION ALL
                    SELECT created_at FROM announcement_comments WHERE user_id = u.user_id
                ) as activities
            ) as last_activity
        FROM user_accounts u
        WHERE u.status = 'approved'
        ORDER BY last_activity DESC
        LIMIT 20
    ");
    $activity['active_users'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // User registration trends (last 12 months)
    $stmt = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as registrations,
            COUNT(CASE WHEN role = 'resident' THEN 1 END) as residents,
            COUNT(CASE WHEN role = 'team officer' THEN 1 END) as officers
        FROM user_accounts
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $activity['registration_trends'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // User engagement levels
    $stmt = $conn->query("
        SELECT 
            u.user_id,
            u.full_name,
            u.profile_img,
            u.role as user_type,
            COUNT(DISTINCT a.id) as announcement_posts,
            COUNT(DISTINCT fd.id) as food_posts,
            COUNT(DISTINCT al.id) as likes_given,
            COUNT(DISTINCT ac.id) as comments_made,
            COUNT(DISTINCT ash.id) as shares_made,
            (COUNT(DISTINCT a.id) + COUNT(DISTINCT fd.id) + COUNT(DISTINCT al.id) + 
             COUNT(DISTINCT ac.id) + COUNT(DISTINCT ash.id)) as total_activity_score
        FROM user_accounts u
        LEFT JOIN announcements a ON u.user_id = a.user_id
        LEFT JOIN food_donations fd ON u.user_id = fd.user_id
        LEFT JOIN announcement_likes al ON u.user_id = al.user_id
        LEFT JOIN announcement_comments ac ON u.user_id = ac.user_id
        LEFT JOIN announcement_shares ash ON u.user_id = ash.user_id
        WHERE u.status = 'approved'
        GROUP BY u.user_id
        HAVING total_activity_score > 0
        ORDER BY total_activity_score DESC
        LIMIT 10
    ");
    $activity['top_engaged'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Inactive users (no activity in 30 days)
    $stmt = $conn->query("
        SELECT 
            u.user_id,
            u.full_name,
            u.email,
            u.profile_img,
            u.role as user_type,
            u.created_at,
            DATEDIFF(NOW(), u.created_at) as days_since_registration
        FROM user_accounts u
        WHERE u.status = 'approved'
        AND u.user_id NOT IN (
            SELECT DISTINCT user_id FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION
            SELECT DISTINCT user_id FROM food_donations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION
            SELECT DISTINCT user_id FROM announcement_likes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION
            SELECT DISTINCT user_id FROM announcement_comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        )
        ORDER BY u.created_at DESC
        LIMIT 15
    ");
    $activity['inactive_users'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Daily active users (last 30 days)
    $stmt = $conn->query("
        SELECT 
            DATE(activity_date) as date,
            COUNT(DISTINCT user_id) as active_users
        FROM (
            SELECT user_id, created_at as activity_date FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION ALL
            SELECT user_id, created_at as activity_date FROM food_donations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION ALL
            SELECT user_id, created_at as activity_date FROM announcement_likes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            UNION ALL
            SELECT user_id, created_at as activity_date FROM announcement_comments WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ) as all_activities
        GROUP BY date
        ORDER BY date
    ");
    $activity['daily_active'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // User type distribution and activity
    $stmt = $conn->query("
        SELECT 
            role as user_type,
            COUNT(*) as count,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
        FROM user_accounts
        GROUP BY role
    ");
    $activity['user_types'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Recent user registrations
    $stmt = $conn->query("
        SELECT 
            user_id,
            full_name,
            email,
            profile_img,
            role as user_type,
            status,
            created_at
        FROM user_accounts
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $activity['recent_registrations'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // User contribution stats
    $stmt = $conn->query("
        SELECT 
            'Announcements' as type,
            COUNT(*) as total,
            COUNT(DISTINCT user_id) as unique_contributors
        FROM announcements
        UNION ALL
        SELECT 
            'Donations' as type,
            COUNT(*) as total,
            COUNT(DISTINCT user_id) as unique_contributors
        FROM food_donations
        UNION ALL
        SELECT 
            'Comments' as type,
            COUNT(*) as total,
            COUNT(DISTINCT user_id) as unique_contributors
        FROM announcement_comments
    ");
    $activity['contributions'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Session activity (simulated with last activity timestamp)
    $stmt = $conn->query("
        SELECT 
            COUNT(DISTINCT user_id) as users_today
        FROM (
            SELECT user_id FROM announcements WHERE DATE(created_at) = CURDATE()
            UNION
            SELECT user_id FROM food_donations WHERE DATE(created_at) = CURDATE()
            UNION
            SELECT user_id FROM announcement_likes WHERE DATE(created_at) = CURDATE()
            UNION
            SELECT user_id FROM announcement_comments WHERE DATE(created_at) = CURDATE()
        ) as today_activities
    ");
    $today = $stmt->fetch_assoc();
    $activity['today_active'] = $today['users_today'];
    
    // User retention rate
    $stmt = $conn->query("
        SELECT 
            COUNT(DISTINCT user_id) as returning_users
        FROM (
            SELECT user_id FROM announcements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION
            SELECT user_id FROM food_donations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            UNION
            SELECT user_id FROM announcement_likes WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) as recent_activities
    ");
    $retention = $stmt->fetch_assoc();
    $activity['weekly_active'] = $retention['returning_users'];
    
} catch (Exception $e) {
    $error_message = "Error fetching user activity: " . $e->getMessage();
}

// Ensure all required arrays exist with defaults
$defaults = [
    'overview' => [
        'total_users' => 0, 'residents' => 0, 'officers' => 0,
        'active_users' => 0, 'pending_users' => 0, 'new_users_month' => 0
    ],
    'active_users' => [], 'registration_trends' => [], 'top_engaged' => [],
    'inactive_users' => [], 'daily_active' => [], 'user_types' => [],
    'recent_registrations' => [], 'contributions' => [],
    'today_active' => 0, 'weekly_active' => 0
];

foreach ($defaults as $key => $default_value) {
    if (!isset($activity[$key])) {
        $activity[$key] = $default_value;
    }
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
                    <h2><i class="bi bi-activity"></i> User Activity Monitoring</h2>
                    <p class="text-muted mb-0">Track user engagement, behavior patterns, and platform usage in real-time</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-success" onclick="exportUserActivity()">
                        <i class="bi bi-file-earmark-excel"></i> Export Data
                    </button>
                    <button class="btn btn-info" onclick="refreshActivity()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row mb-4">
        <!-- Total Users -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Users</h6>
                            <h3 class="mb-0"><?= number_format($activity['overview']['total_users']) ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $activity['overview']['residents'] ?> residents, <?= $activity['overview']['officers'] ?> officers</small>
                </div>
            </div>
        </div>

        <!-- Active Users -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Active Today</h6>
                            <h3 class="mb-0"><?= number_format($activity['today_active']) ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="bi bi-person-check"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $activity['overview']['active_users'] ?> total approved users</small>
                </div>
            </div>
        </div>

        <!-- Weekly Active -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Active This Week</h6>
                            <h3 class="mb-0"><?= number_format($activity['weekly_active']) ?></h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="bi bi-graph-up"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $activity['overview']['active_users'] > 0 ? round(($activity['weekly_active'] / $activity['overview']['active_users']) * 100, 1) : 0 ?>% retention rate</small>
                </div>
            </div>
        </div>

        <!-- New Users -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">New This Month</h6>
                            <h3 class="mb-0"><?= number_format($activity['overview']['new_users_month']) ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-person-plus"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $activity['overview']['pending_users'] ?> pending approval</small>
                </div>
            </div>
        </div>
    </div>

    <!-- User Distribution & Registration Trends -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> User Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="userTypeChart"></canvas>
                    <div class="mt-3">
                        <?php if (!empty($activity['user_types'])): ?>
                            <?php foreach ($activity['user_types'] as $type): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-<?= $type['user_type'] === 'resident' ? 'primary' : 'success' ?>">
                                        <?= ucfirst($type['user_type']) ?>
                                    </span>
                                    <div>
                                        <strong><?= $type['count'] ?></strong>
                                        <small class="text-muted">(<?= $type['approved'] ?> active)</small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-center text-muted mb-0">No data available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Registration Trends (12 Months)</h5>
                </div>
                <div class="card-body">
                    <canvas id="registrationTrendsChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Daily Active Users -->
    <div class="row mb-4">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar3"></i> Daily Active Users (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="dailyActiveChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Engaged Users & Recently Active -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Most Engaged Users</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>User</th>
                                    <th class="text-center">Posts</th>
                                    <th class="text-center">Engagement</th>
                                    <th class="text-center">Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($activity['top_engaged'])): ?>
                                    <?php foreach ($activity['top_engaged'] as $index => $user): ?>
                                        <tr>
                                            <td>
                                                <?php if ($index < 3): ?>
                                                    <i class="bi bi-trophy-fill <?= 
                                                        $index == 0 ? 'text-warning' : 
                                                        ($index == 1 ? 'text-secondary' : '') 
                                                    ?>" style="<?= $index == 2 ? 'color: #CD7F32;' : '' ?>"></i>
                                                <?php else: ?>
                                                    #<?= $index + 1 ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                         class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                    <div>
                                                        <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                                        <br><small class="text-muted"><?= ucfirst($user['user_type']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center"><?= $user['announcement_posts'] + $user['food_posts'] ?></td>
                                            <td class="text-center">
                                                <small><?= $user['likes_given'] ?>❤️ <?= $user['comments_made'] ?>💬 <?= $user['shares_made'] ?>📤</small>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?= $user['total_activity_score'] ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No engaged users yet</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recently Active Users</h5>
                </div>
                <div class="card-body" style="max-height: 450px; overflow-y: auto;">
                    <?php if (!empty($activity['active_users'])): ?>
                        <?php foreach ($activity['active_users'] as $user): ?>
                            <div class="user-item mb-3">
                                <div class="d-flex align-items-start">
                                    <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                         class="rounded-circle me-3" width="40" height="40" alt="Profile">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-0"><?= htmlspecialchars($user['full_name']) ?></h6>
                                        <small class="text-muted"><?= htmlspecialchars($user['email']) ?></small>
                                        <div class="mt-1">
                                            <span class="badge bg-<?= $user['user_type'] === 'resident' ? 'primary' : 'success' ?> me-1">
                                                <?= ucfirst($user['user_type']) ?>
                                            </span>
                                            <?php if ($user['last_activity']): ?>
                                                <small class="text-muted">
                                                    Active <?= date('M j, g:i A', strtotime($user['last_activity'])) ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <?= $user['announcements_count'] ?> announcements • 
                                                <?= $user['donations_count'] ?> donations • 
                                                <?= $user['comments_made'] ?> comments
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">No active users</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Contribution Stats & Inactive Users -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Contribution Stats</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($activity['contributions'])): ?>
                        <?php foreach ($activity['contributions'] as $contrib): ?>
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= $contrib['type'] ?></span>
                                    <strong><?= number_format($contrib['total']) ?></strong>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar bg-<?= 
                                        $contrib['type'] === 'Announcements' ? 'info' : 
                                        ($contrib['type'] === 'Donations' ? 'warning' : 'success')
                                    ?>" style="width: <?= ($contrib['total'] > 0) ? min(($contrib['total'] / 100) * 100, 100) : 0 ?>%"></div>
                                </div>
                                <small class="text-muted"><?= $contrib['unique_contributors'] ?> unique contributors</small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">No contributions yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Inactive Users (No Activity in 30 Days)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th class="text-center">Days Since Registration</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($activity['inactive_users'])): ?>
                                    <?php foreach ($activity['inactive_users'] as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                         class="rounded-circle me-2" width="28" height="28" alt="Profile">
                                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                                </div>
                                            </td>
                                            <td><small><?= htmlspecialchars($user['email']) ?></small></td>
                                            <td>
                                                <span class="badge bg-<?= $user['user_type'] === 'resident' ? 'primary' : 'success' ?>">
                                                    <?= ucfirst($user['user_type']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-warning"><?= $user['days_since_registration'] ?> days</span>
                                            </td>
                                            <td class="text-center">
                                                <button class="btn btn-sm btn-outline-primary" onclick="sendReminder(<?= $user['user_id'] ?>)">
                                                    <i class="bi bi-envelope"></i> Remind
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No inactive users</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Registrations -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-person-plus"></i> Recent Registrations</h5>
                    </div>
                    <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th>Type</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($activity['recent_registrations'])): ?>
                                    <?php foreach ($activity['recent_registrations'] as $user): ?>
                                        <tr>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                         class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                        </div>
                                            </td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $user['user_type'] === 'resident' ? 'primary' : 'success' ?>">
                                                    <?= ucfirst($user['user_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger') ?>">
                                                    <?= ucfirst($user['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y g:i A', strtotime($user['created_at'])) ?></td>
                                            <td class="text-center">
                                                <?php if ($user['status'] === 'pending'): ?>
                                                    <button class="btn btn-sm btn-success" onclick="approveUser(<?= $user['user_id'] ?>)">
                                                        <i class="bi bi-check-circle"></i> Approve
                                                    </button>
                                                <?php else: ?>
                                                    <a href="../admin/users-profile.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-outline-info">
                                                        <i class="bi bi-eye"></i> View
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No recent registrations</td>
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

.user-item {
    padding-bottom: 12px;
    border-bottom: 1px solid #e9ecef;
}

.user-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

@media print {
    .sidebar, .header, .btn, .card-header button {
        display: none !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// User Type Distribution Chart
const userTypeCtx = document.getElementById('userTypeChart').getContext('2d');
new Chart(userTypeCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            if (!empty($activity['user_types'])) {
                echo implode(',', array_map(function($t) { return "'" . ucfirst($t['user_type']) . "'"; }, $activity['user_types'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [{
            data: [<?php 
                if (!empty($activity['user_types'])) {
                    echo implode(',', array_column($activity['user_types'], 'count')); 
                } else {
                    echo "0";
                }
            ?>],
            backgroundColor: [
                'rgba(13, 110, 253, 0.8)',
                'rgba(25, 135, 84, 0.8)',
                'rgba(255, 193, 7, 0.8)'
            ]
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Registration Trends Chart
const registrationCtx = document.getElementById('registrationTrendsChart').getContext('2d');
new Chart(registrationCtx, {
    type: 'line',
    data: {
        labels: [<?php 
            if (!empty($activity['registration_trends'])) {
                echo implode(',', array_map(function($t) { return "'" . date('M Y', strtotime($t['month'] . '-01')) . "'"; }, $activity['registration_trends'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [
            {
                label: 'Total Registrations',
                data: [<?php 
                    if (!empty($activity['registration_trends'])) {
                        echo implode(',', array_column($activity['registration_trends'], 'registrations')); 
                    } else {
                        echo "0";
                    }
                ?>],
                borderColor: 'rgb(13, 110, 253)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4
            },
            {
                label: 'Residents',
                data: [<?php 
                    if (!empty($activity['registration_trends'])) {
                        echo implode(',', array_column($activity['registration_trends'], 'residents')); 
                    } else {
                        echo "0";
                    }
                ?>],
                borderColor: 'rgb(25, 135, 84)',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                tension: 0.4
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
            legend: {
                position: 'top'
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});

// Daily Active Users Chart
const dailyActiveCtx = document.getElementById('dailyActiveChart').getContext('2d');
new Chart(dailyActiveCtx, {
    type: 'bar',
    data: {
        labels: [<?php 
            if (!empty($activity['daily_active'])) {
                echo implode(',', array_map(function($d) { return "'" . date('M j', strtotime($d['date'])) . "'"; }, $activity['daily_active'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [{
            label: 'Active Users',
            data: [<?php 
                if (!empty($activity['daily_active'])) {
                    echo implode(',', array_column($activity['daily_active'], 'active_users')); 
                } else {
                    echo "0";
                }
            ?>],
            backgroundColor: 'rgba(13, 110, 253, 0.8)',
            borderColor: 'rgb(13, 110, 253)',
            borderWidth: 1
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

// Enhanced Functions
function exportUserActivity() {
    const data = [
        ['Metric', 'Value'],
        ['Total Users', <?= $activity['overview']['total_users'] ?>],
        ['Residents', <?= $activity['overview']['residents'] ?>],
        ['Team Officers', <?= $activity['overview']['officers'] ?>],
        ['Active Users', <?= $activity['overview']['active_users'] ?>],
        ['Pending Users', <?= $activity['overview']['pending_users'] ?>],
        ['New Users This Month', <?= $activity['overview']['new_users_month'] ?>],
        ['Active Today', <?= $activity['today_active'] ?>],
        ['Active This Week', <?= $activity['weekly_active'] ?>],
        ['Retention Rate (%)', <?= $activity['overview']['active_users'] > 0 ? round(($activity['weekly_active'] / $activity['overview']['active_users']) * 100, 2) : 0 ?>]
    ];
    
    let csv = data.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'user_activity_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('User activity data exported successfully!', 'success');
}

function refreshActivity() {
    showNotification('Refreshing activity data...', 'info');
    setTimeout(() => {
        window.location.reload();
    }, 1000);
}

function sendReminder(userId) {
    if (confirm('Send a reminder email to this inactive user?')) {
        const formData = new FormData();
        formData.append('action', 'send_reminder');
        formData.append('user_id', userId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'Reminder sent successfully!', 'success');
            } else {
                showNotification(data.message || 'Failed to send reminder', 'error');
            }
        })
        .catch(error => {
            showNotification('Error sending reminder', 'error');
        });
    }
}

function approveUser(userId) {
    if (confirm('Approve this user registration?')) {
        const formData = new FormData();
        formData.append('action', 'approve_user');
        formData.append('user_id', userId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message || 'User approved successfully!', 'success');
                setTimeout(() => window.location.reload(), 1500);
            } else {
                showNotification(data.message || 'Failed to approve user', 'error');
            }
        })
        .catch(error => {
            showNotification('Error approving user', 'error');
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
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 3000);
}

// Auto-refresh every 10 minutes
setInterval(() => {
    if (!document.hidden) {
        refreshActivity();
    }
}, 600000); // 10 minutes
</script>

<?php include 'footer.php'; ?>

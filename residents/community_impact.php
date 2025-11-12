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

// Get overall statistics
$stats = [];

try {
    // Announcements Statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_announcements,
            SUM(likes_count) as total_likes,
            SUM(comments_count) as total_comments,
            SUM(shares_count) as total_shares
        FROM announcements 
        WHERE status = 'published'
    ");
    $stats['announcements'] = $stmt->fetch_assoc();
    
    // Total saves
    $stmt = $conn->query("
        SELECT COUNT(*) as total_saves 
        FROM announcement_saves 
        WHERE post_type = 'announcement'
    ");
    $saves = $stmt->fetch_assoc();
    $stats['announcements']['total_saves'] = $saves['total_saves'];
    
    // Food Donations Statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_donations,
            COUNT(CASE WHEN status = 'available' THEN 1 END) as available_donations,
            COUNT(CASE WHEN status = 'reserved' THEN 1 END) as reserved_donations,
            COUNT(CASE WHEN status = 'claimed' THEN 1 END) as claimed_donations,
            SUM(views_count) as total_views
        FROM food_donations
    ");
    $stats['food_donations'] = $stmt->fetch_assoc();
    
    // Food Reservations Statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_requests,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_requests,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests
        FROM food_donation_reservations
    ");
    $stats['reservations'] = $stmt->fetch_assoc();
    
    // Active Users Count
    $stmt = $conn->query("
        SELECT COUNT(DISTINCT user_id) as active_users
        FROM (
            SELECT user_id FROM announcements WHERE status = 'published'
            UNION
            SELECT user_id FROM food_donations
            UNION
            SELECT user_id FROM announcement_likes
            UNION
            SELECT user_id FROM announcement_comments
        ) as active_users_query
    ");
    $active = $stmt->fetch_assoc();
    $stats['active_users'] = $active['active_users'];
    
    // Food Feedback/Ratings
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_feedback,
            AVG(rating) as avg_rating
        FROM food_donation_feedback
    ");
    $stats['feedback'] = $stmt->fetch_assoc();
    
    // Top Engaged Announcements
    $stmt = $conn->query("
        SELECT 
            a.id,
            a.title,
            a.type,
            a.likes_count,
            a.comments_count,
            a.shares_count,
            (a.likes_count + a.comments_count + a.shares_count) as total_engagement,
            u.full_name as author
        FROM announcements a
        LEFT JOIN user_accounts u ON a.user_id = u.user_id
        WHERE a.status = 'published'
        ORDER BY total_engagement DESC
        LIMIT 5
    ");
    $stats['top_announcements'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Most Active Food Donors
    $stmt = $conn->query("
        SELECT 
            u.full_name,
            u.profile_img,
            COUNT(fd.id) as donations_count,
            SUM(fd.views_count) as total_views
        FROM food_donations fd
        JOIN user_accounts u ON fd.user_id = u.user_id
        GROUP BY fd.user_id
        ORDER BY donations_count DESC
        LIMIT 5
    ");
    $stats['top_donors'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Recent Community Activity
    $stmt = $conn->query("
        SELECT 
            'announcement' as type,
            a.id,
            a.title as content,
            a.created_at,
            u.full_name as user_name,
            u.profile_img
        FROM announcements a
        JOIN user_accounts u ON a.user_id = u.user_id
        WHERE a.status = 'published'
        UNION ALL
        SELECT 
            'donation' as type,
            fd.id,
            fd.title as content,
            fd.created_at,
            u.full_name as user_name,
            u.profile_img
        FROM food_donations fd
        JOIN user_accounts u ON fd.user_id = u.user_id
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stats['recent_activity'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Announcement Types Breakdown
    $stmt = $conn->query("
        SELECT 
            type,
            COUNT(*) as count
        FROM announcements
        WHERE status = 'published'
        GROUP BY type
    ");
    $stats['announcement_types'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Food Types Breakdown
    $stmt = $conn->query("
        SELECT 
            food_type,
            COUNT(*) as count
        FROM food_donations
        GROUP BY food_type
    ");
    $stats['food_types'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Monthly Trend (Last 6 months)
    $stmt = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM announcements
        WHERE status = 'published' AND created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $stats['announcement_trend'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    $stmt = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count
        FROM food_donations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $stats['donation_trend'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error fetching statistics: " . $e->getMessage();
}

// Ensure all required arrays exist with defaults
if (!isset($stats['announcements'])) {
    $stats['announcements'] = [
        'total_announcements' => 0,
        'total_likes' => 0,
        'total_comments' => 0,
        'total_shares' => 0,
        'total_saves' => 0
    ];
}

if (!isset($stats['food_donations'])) {
    $stats['food_donations'] = [
        'total_donations' => 0,
        'available_donations' => 0,
        'reserved_donations' => 0,
        'claimed_donations' => 0,
        'total_views' => 0
    ];
}

if (!isset($stats['reservations'])) {
    $stats['reservations'] = [
        'total_requests' => 0,
        'pending_requests' => 0,
        'approved_requests' => 0,
        'completed_requests' => 0
    ];
}

if (!isset($stats['feedback'])) {
    $stats['feedback'] = [
        'total_feedback' => 0,
        'avg_rating' => 0
    ];
}

if (!isset($stats['active_users'])) {
    $stats['active_users'] = 0;
}

if (!isset($stats['top_announcements'])) {
    $stats['top_announcements'] = [];
}

if (!isset($stats['top_donors'])) {
    $stats['top_donors'] = [];
}

if (!isset($stats['recent_activity'])) {
    $stats['recent_activity'] = [];
}

if (!isset($stats['announcement_types'])) {
    $stats['announcement_types'] = [];
}

if (!isset($stats['food_types'])) {
    $stats['food_types'] = [];
}

if (!isset($stats['announcement_trend'])) {
    $stats['announcement_trend'] = [];
}

if (!isset($stats['donation_trend'])) {
    $stats['donation_trend'] = [];
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
                    <h2><i class="bi bi-graph-up-arrow"></i> Community Impact & Statistics</h2>
                    <p class="text-muted mb-0">Real-time analytics of community engagement and food sharing activities</p>
                </div>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print Report
                </button>
            </div>
        </div>
    </div>

    <!-- Key Metrics Cards -->
    <div class="row mb-4">
        <!-- Total Announcements -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Announcements</h6>
                            <h3 class="mb-0"><?= number_format($stats['announcements']['total_announcements'] ?? 0) ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-megaphone"></i>
                        </div>
                    </div>
                    <small class="text-muted">Published posts</small>
                </div>
            </div>
        </div>

        <!-- Total Engagement -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Engagement</h6>
                            <h3 class="mb-0"><?= number_format(
                                ($stats['announcements']['total_likes'] ?? 0) + 
                                ($stats['announcements']['total_comments'] ?? 0) + 
                                ($stats['announcements']['total_shares'] ?? 0)
                            ) ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="bi bi-heart-fill"></i>
                        </div>
                    </div>
                    <small class="text-muted">Likes, comments & shares</small>
                </div>
            </div>
        </div>

        <!-- Food Donations -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Food Donations</h6>
                            <h3 class="mb-0"><?= number_format($stats['food_donations']['total_donations'] ?? 0) ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-basket"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $stats['food_donations']['available_donations'] ?? 0 ?> currently available</small>
                </div>
            </div>
        </div>

        <!-- Active Users -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Active Users</h6>
                            <h3 class="mb-0"><?= number_format($stats['active_users'] ?? 0) ?></h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                    <small class="text-muted">Contributing members</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Detailed Engagement Metrics -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Engagement Breakdown</h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3 mb-3">
                            <div class="engagement-stat">
                                <i class="bi bi-heart-fill text-danger"></i>
                                <h4><?= number_format($stats['announcements']['total_likes'] ?? 0) ?></h4>
                                <p class="text-muted mb-0">Likes</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="engagement-stat">
                                <i class="bi bi-chat-fill text-primary"></i>
                                <h4><?= number_format($stats['announcements']['total_comments'] ?? 0) ?></h4>
                                <p class="text-muted mb-0">Comments</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="engagement-stat">
                                <i class="bi bi-share-fill text-success"></i>
                                <h4><?= number_format($stats['announcements']['total_shares'] ?? 0) ?></h4>
                                <p class="text-muted mb-0">Shares</p>
                            </div>
                        </div>
                        <div class="col-md-3 mb-3">
                            <div class="engagement-stat">
                                <i class="bi bi-bookmark-fill text-warning"></i>
                                <h4><?= number_format($stats['announcements']['total_saves'] ?? 0) ?></h4>
                                <p class="text-muted mb-0">Saves</p>
                            </div>
                        </div>
                    </div>
                    <hr>
                    <canvas id="engagementChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Announcement Types</h5>
                </div>
                <div class="card-body">
                    <canvas id="announcementTypesChart"></canvas>
                    <div class="mt-3">
                        <?php if (!empty($stats['announcement_types'])): ?>
                            <?php foreach ($stats['announcement_types'] as $type): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span class="badge bg-<?= 
                                        $type['type'] === 'announcement' ? 'info' : 
                                        ($type['type'] === 'reminder' ? 'primary' : 
                                        ($type['type'] === 'guideline' ? 'warning' : 'danger'))
                                    ?>">
                                        <?= ucfirst($type['type']) ?>
                                    </span>
                                    <strong><?= $type['count'] ?></strong>
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

    <!-- Food Sharing Statistics -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-basket"></i> Food Donation Status</h5>
                </div>
                <div class="card-body">
                    <canvas id="donationStatusChart"></canvas>
                    <div class="mt-3">
                        <div class="row text-center">
                            <div class="col-4">
                                <div class="status-stat">
                                    <h5 class="text-success"><?= $stats['food_donations']['available_donations'] ?? 0 ?></h5>
                                    <small>Available</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="status-stat">
                                    <h5 class="text-warning"><?= $stats['food_donations']['reserved_donations'] ?? 0 ?></h5>
                                    <small>Reserved</small>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="status-stat">
                                    <h5 class="text-info"><?= $stats['food_donations']['claimed_donations'] ?? 0 ?></h5>
                                    <small>Claimed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> Food Types Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="foodTypesChart"></canvas>
                    <div class="mt-3">
                        <?php if (!empty($stats['food_types'])): ?>
                            <?php foreach ($stats['food_types'] as $food): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= ucfirst($food['food_type']) ?></span>
                                    <strong><?= $food['count'] ?></strong>
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

    <!-- Request Statistics -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clipboard-check"></i> Food Requests</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Total Requests</span>
                            <h4 class="mb-0"><?= $stats['reservations']['total_requests'] ?? 0 ?></h4>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Pending</span>
                            <span class="badge bg-warning"><?= $stats['reservations']['pending_requests'] ?? 0 ?></span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-warning" style="width: <?= 
                                ($stats['reservations']['total_requests'] > 0) 
                                ? ($stats['reservations']['pending_requests'] / $stats['reservations']['total_requests'] * 100) 
                                : 0 
                            ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Completed</span>
                            <span class="badge bg-success"><?= $stats['reservations']['completed_requests'] ?? 0 ?></span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-success" style="width: <?= 
                                ($stats['reservations']['total_requests'] > 0) 
                                ? ($stats['reservations']['completed_requests'] / $stats['reservations']['total_requests'] * 100) 
                                : 0 
                            ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-star-fill"></i> Community Ratings</h5>
                </div>
                <div class="card-body text-center">
                    <h1 class="display-3 mb-2"><?= number_format($stats['feedback']['avg_rating'] ?? 0, 1) ?></h1>
                    <div class="mb-2">
                        <?php 
                        $rating = round($stats['feedback']['avg_rating'] ?? 0);
                        for ($i = 1; $i <= 5; $i++): 
                        ?>
                            <i class="bi bi-star-fill <?= $i <= $rating ? 'text-warning' : 'text-muted' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted mb-0">Based on <?= number_format($stats['feedback']['total_feedback'] ?? 0) ?> reviews</p>
                    <hr>
                    <small class="text-muted">Average community satisfaction rating</small>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-eye"></i> Donation Views</h5>
                </div>
                <div class="card-body text-center">
                    <h1 class="display-4 mb-2"><?= number_format($stats['food_donations']['total_views'] ?? 0) ?></h1>
                    <p class="text-muted mb-0">Total views on donations</p>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <h5><?= number_format(
                                ($stats['food_donations']['total_donations'] > 0) 
                                ? ($stats['food_donations']['total_views'] / $stats['food_donations']['total_donations']) 
                                : 0, 
                                1
                            ) ?></h5>
                            <small class="text-muted">Avg per post</small>
                        </div>
                        <div class="col-6">
                            <h5><?= $stats['food_donations']['total_donations'] ?? 0 ?></h5>
                            <small class="text-muted">Total posts</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Content & Contributors -->
    <div class="row mb-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Most Engaged Announcements</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Title</th>
                                    <th>Type</th>
                                    <th class="text-center">❤️</th>
                                    <th class="text-center">💬</th>
                                    <th class="text-center">📤</th>
                                    <th class="text-center">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($stats['top_announcements'])): ?>
                                    <?php foreach ($stats['top_announcements'] as $index => $post): ?>
                                        <tr>
                                            <td>
                                                <?php if ($index == 0): ?>
                                                    <i class="bi bi-trophy-fill text-warning"></i>
                                                <?php elseif ($index == 1): ?>
                                                    <i class="bi bi-trophy-fill text-secondary"></i>
                                                <?php elseif ($index == 2): ?>
                                                    <i class="bi bi-trophy-fill" style="color: #CD7F32;"></i>
                                                <?php else: ?>
                                                    #<?= $index + 1 ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <strong><?= htmlspecialchars(substr($post['title'], 0, 40)) ?><?= strlen($post['title']) > 40 ? '...' : '' ?></strong>
                                                <br><small class="text-muted">by <?= htmlspecialchars($post['author']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $post['type'] === 'announcement' ? 'info' : 
                                                    ($post['type'] === 'reminder' ? 'primary' : 
                                                    ($post['type'] === 'guideline' ? 'warning' : 'danger'))
                                                ?>">
                                                    <?= ucfirst($post['type']) ?>
                                                </span>
                                            </td>
                                            <td class="text-center"><?= $post['likes_count'] ?></td>
                                            <td class="text-center"><?= $post['comments_count'] ?></td>
                                            <td class="text-center"><?= $post['shares_count'] ?></td>
                                            <td class="text-center"><strong><?= $post['total_engagement'] ?></strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No announcements yet</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-award"></i> Top Food Donors</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($stats['top_donors'])): ?>
                        <?php foreach ($stats['top_donors'] as $index => $donor): ?>
                            <div class="d-flex align-items-center mb-3 pb-3 <?= $index < count($stats['top_donors']) - 1 ? 'border-bottom' : '' ?>">
                                <div class="me-3">
                                    <?php if ($index < 3): ?>
                                        <i class="bi bi-trophy-fill <?= 
                                            $index == 0 ? 'text-warning' : 
                                            ($index == 1 ? 'text-secondary' : '') 
                                        ?>" style="<?= $index == 2 ? 'color: #CD7F32;' : '' ?>"></i>
                                    <?php else: ?>
                                        <span class="text-muted">#<?= $index + 1 ?></span>
                                    <?php endif; ?>
                                </div>
                                <img src="<?= !empty($donor['profile_img']) ? '../uploads/profile_picture/' . $donor['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                     class="rounded-circle me-3" width="50" height="50" alt="Profile">
                                <div class="flex-grow-1">
                                    <h6 class="mb-0"><?= htmlspecialchars($donor['full_name']) ?></h6>
                                    <small class="text-muted">
                                        <?= $donor['donations_count'] ?> donations • <?= number_format($donor['total_views']) ?> views
                                    </small>
                                </div>
                                <span class="badge bg-primary"><?= $donor['donations_count'] ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">No donors yet</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Timeline & Trends -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> 6-Month Trend</h5>
                </div>
                <div class="card-body">
                    <canvas id="trendChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (!empty($stats['recent_activity'])): ?>
                        <?php foreach ($stats['recent_activity'] as $activity): ?>
                            <div class="activity-item mb-3">
                                <div class="d-flex align-items-start">
                                    <img src="<?= !empty($activity['profile_img']) ? '../uploads/profile_picture/' . $activity['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                         class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                    <div class="flex-grow-1">
                                        <p class="mb-0">
                                            <strong><?= htmlspecialchars($activity['user_name']) ?></strong> posted a new 
                                            <span class="badge bg-<?= $activity['type'] === 'announcement' ? 'info' : 'warning' ?>">
                                                <?= $activity['type'] ?>
                                            </span>
                                        </p>
                                        <p class="mb-0 text-muted small"><?= htmlspecialchars(substr($activity['content'], 0, 50)) ?>...</p>
                                        <small class="text-muted"><?= date('M j, g:i A', strtotime($activity['created_at'])) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">No recent activity</p>
                    <?php endif; ?>
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

.engagement-stat {
    padding: 15px;
    border-radius: 8px;
    background: #f8f9fa;
}

.engagement-stat i {
    font-size: 2rem;
    margin-bottom: 10px;
}

.status-stat {
    padding: 10px;
}

.activity-item {
    padding-bottom: 12px;
    border-bottom: 1px solid #e9ecef;
}

.activity-item:last-child {
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
// Engagement Chart
const engagementCtx = document.getElementById('engagementChart').getContext('2d');
new Chart(engagementCtx, {
    type: 'bar',
    data: {
        labels: ['Likes', 'Comments', 'Shares', 'Saves'],
        datasets: [{
            label: 'Engagement Metrics',
            data: [
                <?= $stats['announcements']['total_likes'] ?? 0 ?>,
                <?= $stats['announcements']['total_comments'] ?? 0 ?>,
                <?= $stats['announcements']['total_shares'] ?? 0 ?>,
                <?= $stats['announcements']['total_saves'] ?? 0 ?>
            ],
            backgroundColor: [
                'rgba(220, 53, 69, 0.8)',
                'rgba(13, 110, 253, 0.8)',
                'rgba(25, 135, 84, 0.8)',
                'rgba(255, 193, 7, 0.8)'
            ],
            borderColor: [
                'rgb(220, 53, 69)',
                'rgb(13, 110, 253)',
                'rgb(25, 135, 84)',
                'rgb(255, 193, 7)'
            ],
            borderWidth: 2
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
                beginAtZero: true
            }
        }
    }
});

// Announcement Types Chart
const announcementTypesCtx = document.getElementById('announcementTypesChart').getContext('2d');
new Chart(announcementTypesCtx, {
    type: 'doughnut',
    data: {
        labels: [<?php 
            if (!empty($stats['announcement_types'])) {
                echo implode(',', array_map(function($t) { return "'" . ucfirst($t['type']) . "'"; }, $stats['announcement_types'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [{
            data: [<?php 
                if (!empty($stats['announcement_types'])) {
                    echo implode(',', array_column($stats['announcement_types'], 'count')); 
                } else {
                    echo "0";
                }
            ?>],
            backgroundColor: [
                'rgba(13, 110, 253, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(25, 135, 84, 0.8)',
                'rgba(220, 53, 69, 0.8)'
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

// Donation Status Chart
const donationStatusCtx = document.getElementById('donationStatusChart').getContext('2d');
new Chart(donationStatusCtx, {
    type: 'pie',
    data: {
        labels: ['Available', 'Reserved', 'Claimed'],
        datasets: [{
            data: [
                <?= $stats['food_donations']['available_donations'] ?? 0 ?>,
                <?= $stats['food_donations']['reserved_donations'] ?? 0 ?>,
                <?= $stats['food_donations']['claimed_donations'] ?? 0 ?>
            ],
            backgroundColor: [
                'rgba(25, 135, 84, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(13, 110, 253, 0.8)'
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

// Food Types Chart
const foodTypesCtx = document.getElementById('foodTypesChart').getContext('2d');
new Chart(foodTypesCtx, {
    type: 'polarArea',
    data: {
        labels: [<?php 
            if (!empty($stats['food_types'])) {
                echo implode(',', array_map(function($f) { return "'" . ucfirst($f['food_type']) . "'"; }, $stats['food_types'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [{
            data: [<?php 
                if (!empty($stats['food_types'])) {
                    echo implode(',', array_column($stats['food_types'], 'count')); 
                } else {
                    echo "0";
                }
            ?>],
            backgroundColor: [
                'rgba(255, 99, 132, 0.8)',
                'rgba(54, 162, 235, 0.8)',
                'rgba(255, 206, 86, 0.8)',
                'rgba(75, 192, 192, 0.8)',
                'rgba(153, 102, 255, 0.8)'
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

// Trend Chart
const trendCtx = document.getElementById('trendChart').getContext('2d');
new Chart(trendCtx, {
    type: 'line',
    data: {
        labels: [<?php 
            $months = [];
            if (!empty($stats['announcement_trend']) || !empty($stats['donation_trend'])) {
                $months = array_unique(array_merge(
                    !empty($stats['announcement_trend']) ? array_column($stats['announcement_trend'], 'month') : [],
                    !empty($stats['donation_trend']) ? array_column($stats['donation_trend'], 'month') : []
                ));
                sort($months);
                echo implode(',', array_map(function($m) { return "'" . date('M Y', strtotime($m . '-01')) . "'"; }, $months));
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [
            {
                label: 'Announcements',
                data: [<?php 
                    if (!empty($months)) {
                        foreach ($months as $month) {
                            $found = false;
                            if (!empty($stats['announcement_trend'])) {
                                foreach ($stats['announcement_trend'] as $trend) {
                                    if ($trend['month'] == $month) {
                                        echo $trend['count'] . ',';
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                            if (!$found) echo '0,';
                        }
                    } else {
                        echo '0';
                    }
                ?>],
                borderColor: 'rgb(13, 110, 253)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4
            },
            {
                label: 'Food Donations',
                data: [<?php 
                    if (!empty($months)) {
                        foreach ($months as $month) {
                            $found = false;
                            if (!empty($stats['donation_trend'])) {
                                foreach ($stats['donation_trend'] as $trend) {
                                    if ($trend['month'] == $month) {
                                        echo $trend['count'] . ',';
                                        $found = true;
                                        break;
                                    }
                                }
                            }
                            if (!$found) echo '0,';
                        }
                    } else {
                        echo '0';
                    }
                ?>],
                borderColor: 'rgb(255, 193, 7)',
                backgroundColor: 'rgba(255, 193, 7, 0.1)',
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
</script>

<?php include 'footer.php'; ?>

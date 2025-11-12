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

// Get donation analytics data
$analytics = [];

try {
    // Overall donation statistics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_donations,
            COUNT(CASE WHEN status = 'available' THEN 1 END) as available_donations,
            COUNT(CASE WHEN status = 'reserved' THEN 1 END) as reserved_donations,
            COUNT(CASE WHEN status = 'claimed' THEN 1 END) as claimed_donations,
            COUNT(CASE WHEN status = 'expired' THEN 1 END) as expired_donations,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_donations,
            SUM(views_count) as total_views,
            AVG(views_count) as avg_views_per_donation
        FROM food_donations
    ");
    $analytics['overview'] = $stmt->fetch_assoc();
    
    // Food type distribution
    $stmt = $conn->query("
        SELECT 
            food_type,
            COUNT(*) as count,
            ROUND((COUNT(*) * 100.0 / (SELECT COUNT(*) FROM food_donations)), 2) as percentage
        FROM food_donations
        GROUP BY food_type
        ORDER BY count DESC
    ");
    $analytics['food_types'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Top donors
    $stmt = $conn->query("
        SELECT 
            u.full_name,
            u.email,
            u.profile_img,
            COUNT(fd.id) as donations_count,
            SUM(fd.views_count) as total_views,
            COUNT(CASE WHEN fd.status = 'claimed' THEN 1 END) as successful_donations,
            ROUND((COUNT(CASE WHEN fd.status = 'claimed' THEN 1 END) * 100.0 / COUNT(fd.id)), 2) as success_rate
        FROM food_donations fd
        JOIN user_accounts u ON fd.user_id = u.user_id
        GROUP BY fd.user_id
        ORDER BY donations_count DESC
        LIMIT 10
    ");
    $analytics['top_donors'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Monthly donation trends (last 12 months)
    $stmt = $conn->query("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as donations_count,
            COUNT(CASE WHEN status = 'claimed' THEN 1 END) as claimed_count,
            SUM(views_count) as total_views
        FROM food_donations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY month
        ORDER BY month
    ");
    $analytics['monthly_trends'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Daily donation patterns (last 30 days)
    $stmt = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as donations_count,
            COUNT(CASE WHEN status = 'claimed' THEN 1 END) as claimed_count
        FROM food_donations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY date
        ORDER BY date
    ");
    $analytics['daily_patterns'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Request analytics
    $stmt = $conn->query("
        SELECT 
            COUNT(*) as total_requests,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_requests,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_requests,
            COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_requests,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_requests,
            AVG(TIMESTAMPDIFF(HOUR, reserved_at, responded_at)) as avg_response_time_hours
        FROM food_donation_reservations
    ");
    $analytics['requests'] = $stmt->fetch_assoc();
    
    // Location analytics
    $stmt = $conn->query("
        SELECT 
            SUBSTRING_INDEX(location_address, ',', -1) as area,
            COUNT(*) as donations_count
        FROM food_donations
        WHERE location_address IS NOT NULL AND location_address != ''
        GROUP BY area
        ORDER BY donations_count DESC
        LIMIT 10
    ");
    $analytics['locations'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Peak hours analysis
    $stmt = $conn->query("
        SELECT 
            HOUR(created_at) as hour,
            COUNT(*) as donations_count
        FROM food_donations
        GROUP BY hour
        ORDER BY hour
    ");
    $analytics['peak_hours'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Donation lifecycle analysis
    $stmt = $conn->query("
        SELECT 
            AVG(TIMESTAMPDIFF(HOUR, created_at, 
                CASE 
                    WHEN status = 'claimed' THEN updated_at
                    WHEN status = 'expired' THEN updated_at
                    ELSE NULL
                END
            )) as avg_lifetime_hours,
            COUNT(CASE WHEN status = 'claimed' THEN 1 END) * 100.0 / COUNT(*) as claim_rate_percentage
        FROM food_donations
        WHERE status IN ('claimed', 'expired')
    ");
    $lifecycle = $stmt->fetch_assoc();
    $analytics['lifecycle'] = $lifecycle;
    
    // Recent high-engagement donations
    $stmt = $conn->query("
        SELECT 
            fd.id,
            fd.title,
            fd.food_type,
            fd.status,
            fd.views_count,
            fd.created_at,
            u.full_name as donor_name
        FROM food_donations fd
        JOIN user_accounts u ON fd.user_id = u.user_id
        WHERE fd.views_count > (SELECT AVG(views_count) FROM food_donations)
        ORDER BY fd.views_count DESC
        LIMIT 10
    ");
    $analytics['high_engagement'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Feedback analytics
    $stmt = $conn->query("
        SELECT 
            AVG(rating) as avg_rating,
            COUNT(*) as total_feedback,
            COUNT(CASE WHEN rating >= 4 THEN 1 END) as positive_feedback,
            COUNT(CASE WHEN rating <= 2 THEN 1 END) as negative_feedback
        FROM food_donation_feedback
    ");
    $analytics['feedback'] = $stmt->fetch_assoc();
    
} catch (Exception $e) {
    $error_message = "Error fetching analytics: " . $e->getMessage();
}

// Ensure all required arrays exist with defaults
$defaults = [
    'overview' => [
        'total_donations' => 0, 'available_donations' => 0, 'reserved_donations' => 0,
        'claimed_donations' => 0, 'expired_donations' => 0, 'cancelled_donations' => 0,
        'total_views' => 0, 'avg_views_per_donation' => 0
    ],
    'food_types' => [], 'top_donors' => [], 'monthly_trends' => [], 'daily_patterns' => [],
    'requests' => [
        'total_requests' => 0, 'pending_requests' => 0, 'approved_requests' => 0,
        'completed_requests' => 0, 'rejected_requests' => 0, 'cancelled_requests' => 0,
        'avg_response_time_hours' => 0
    ],
    'locations' => [], 'peak_hours' => [], 'lifecycle' => ['avg_lifetime_hours' => 0, 'claim_rate_percentage' => 0],
    'high_engagement' => [], 'feedback' => ['avg_rating' => 0, 'total_feedback' => 0, 'positive_feedback' => 0, 'negative_feedback' => 0]
];

foreach ($defaults as $key => $default_value) {
    if (!isset($analytics[$key])) {
        $analytics[$key] = $default_value;
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
                    <h2><i class="bi bi-bar-chart"></i> Donation Analytics Dashboard</h2>
                    <p class="text-muted mb-0">Comprehensive analysis of food donation patterns, donor behavior, and platform performance</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-success" onclick="exportDonationData()">
                        <i class="bi bi-file-earmark-excel"></i> Export Data
                    </button>
                    <button class="btn btn-info" onclick="refreshAnalytics()">
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
        <!-- Total Donations -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Donations</h6>
                            <h3 class="mb-0"><?= number_format($analytics['overview']['total_donations']) ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-basket"></i>
                        </div>
                    </div>
                    <small class="text-muted">All time donations</small>
                </div>
            </div>
        </div>

        <!-- Claimed Donations -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Successfully Claimed</h6>
                            <h3 class="mb-0"><?= number_format($analytics['overview']['claimed_donations']) ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="bi bi-check-circle"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= $analytics['overview']['total_donations'] > 0 ? round(($analytics['overview']['claimed_donations'] / $analytics['overview']['total_donations']) * 100, 1) : 0 ?>% success rate</small>
                </div>
            </div>
        </div>

        <!-- Total Views -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Views</h6>
                            <h3 class="mb-0"><?= number_format($analytics['overview']['total_views']) ?></h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="bi bi-eye"></i>
                        </div>
                    </div>
                    <small class="text-muted"><?= round($analytics['overview']['avg_views_per_donation'], 1) ?> avg per donation</small>
                </div>
            </div>
        </div>

        <!-- Active Donors -->
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Active Donors</h6>
                            <h3 class="mb-0"><?= count($analytics['top_donors']) ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                    <small class="text-muted">Top contributors</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Status Overview -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Donation Status Distribution</h5>
                </div>
                <div class="card-body">
                    <canvas id="statusChart"></canvas>
                    <div class="mt-3">
                        <div class="row text-center">
                            <div class="col-2">
                                <div class="status-stat">
                                    <h5 class="text-success"><?= $analytics['overview']['claimed_donations'] ?></h5>
                                    <small>Claimed</small>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="status-stat">
                                    <h5 class="text-primary"><?= $analytics['overview']['available_donations'] ?></h5>
                                    <small>Available</small>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="status-stat">
                                    <h5 class="text-warning"><?= $analytics['overview']['reserved_donations'] ?></h5>
                                    <small>Reserved</small>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="status-stat">
                                    <h5 class="text-danger"><?= $analytics['overview']['expired_donations'] ?></h5>
                                    <small>Expired</small>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="status-stat">
                                    <h5 class="text-secondary"><?= $analytics['overview']['cancelled_donations'] ?></h5>
                                    <small>Cancelled</small>
                                </div>
                            </div>
                            <div class="col-2">
                                <div class="status-stat">
                                    <h5 class="text-info"><?= $analytics['overview']['total_views'] ?></h5>
                                    <small>Total Views</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-box-seam"></i> Food Types</h5>
                </div>
                <div class="card-body">
                    <canvas id="foodTypesChart"></canvas>
                    <div class="mt-3">
                        <?php if (!empty($analytics['food_types'])): ?>
                            <?php foreach ($analytics['food_types'] as $food): ?>
                                <div class="d-flex justify-content-between mb-2">
                                    <span><?= ucfirst($food['food_type']) ?></span>
                                    <div>
                                        <strong><?= $food['count'] ?></strong>
                                        <small class="text-muted">(<?= $food['percentage'] ?>%)</small>
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
    </div>

    <!-- Monthly Trends -->
    <div class="row mb-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> 12-Month Donation Trends</h5>
                </div>
                <div class="card-body">
                    <canvas id="trendsChart" height="80"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Donors & High Engagement -->
    <div class="row mb-4">
        <div class="col-lg-7">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-trophy"></i> Top Donors</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Donor</th>
                                    <th class="text-center">Donations</th>
                                    <th class="text-center">Success Rate</th>
                                    <th class="text-center">Total Views</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($analytics['top_donors'])): ?>
                                    <?php foreach ($analytics['top_donors'] as $index => $donor): ?>
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
                                                    <img src="<?= !empty($donor['profile_img']) ? '../uploads/profile_picture/' . $donor['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                         class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                    <div>
                                                        <strong><?= htmlspecialchars($donor['full_name']) ?></strong>
                                                        <br><small class="text-muted"><?= htmlspecialchars($donor['email']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="text-center"><?= $donor['donations_count'] ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-<?= $donor['success_rate'] >= 70 ? 'success' : ($donor['success_rate'] >= 50 ? 'warning' : 'danger') ?>">
                                                    <?= $donor['success_rate'] ?>%
                                                </span>
                                            </td>
                                            <td class="text-center"><?= number_format($donor['total_views']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No donors yet</td>
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
                    <h5 class="mb-0"><i class="bi bi-fire"></i> High Engagement Donations</h5>
                </div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <?php if (!empty($analytics['high_engagement'])): ?>
                        <?php foreach ($analytics['high_engagement'] as $donation): ?>
                            <div class="engagement-item mb-3">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?= htmlspecialchars(substr($donation['title'], 0, 30)) ?><?= strlen($donation['title']) > 30 ? '...' : '' ?></h6>
                                        <small class="text-muted">by <?= htmlspecialchars($donation['donor_name']) ?></small>
                                        <br><span class="badge bg-<?= 
                                            $donation['status'] === 'claimed' ? 'success' : 
                                            ($donation['status'] === 'available' ? 'primary' : 
                                            ($donation['status'] === 'reserved' ? 'warning' : 'secondary'))
                                        ?>"><?= ucfirst($donation['status']) ?></span>
                                    </div>
                                    <div class="text-end">
                                        <strong class="text-primary"><?= $donation['views_count'] ?></strong>
                                        <br><small class="text-muted">views</small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">No high engagement donations</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Performance Metrics -->
    <div class="row mb-4">
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock"></i> Request Processing</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Total Requests</span>
                            <h4 class="mb-0"><?= $analytics['requests']['total_requests'] ?></h4>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-primary" style="width: 100%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Completed</span>
                            <span class="badge bg-success"><?= $analytics['requests']['completed_requests'] ?></span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-success" style="width: <?= 
                                ($analytics['requests']['total_requests'] > 0) 
                                ? ($analytics['requests']['completed_requests'] / $analytics['requests']['total_requests'] * 100) 
                                : 0 
                            ?>%"></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span>Pending</span>
                            <span class="badge bg-warning"><?= $analytics['requests']['pending_requests'] ?></span>
                        </div>
                        <div class="progress" style="height: 5px;">
                            <div class="progress-bar bg-warning" style="width: <?= 
                                ($analytics['requests']['total_requests'] > 0) 
                                ? ($analytics['requests']['pending_requests'] / $analytics['requests']['total_requests'] * 100) 
                                : 0 
                            ?>%"></div>
                        </div>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="bi bi-clock-history"></i> 
                        Avg Response Time: <?= round($analytics['requests']['avg_response_time_hours'], 1) ?> hours
                    </small>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-star"></i> Community Feedback</h5>
                </div>
                <div class="card-body text-center">
                    <h1 class="display-3 mb-2"><?= number_format($analytics['feedback']['avg_rating'], 1) ?></h1>
                    <div class="mb-2">
                        <?php 
                        $rating = round($analytics['feedback']['avg_rating']);
                        for ($i = 1; $i <= 5; $i++): 
                        ?>
                            <i class="bi bi-star-fill <?= $i <= $rating ? 'text-warning' : 'text-muted' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <p class="text-muted mb-0">Based on <?= number_format($analytics['feedback']['total_feedback']) ?> reviews</p>
                    <hr>
                    <div class="row">
                        <div class="col-6">
                            <h5 class="text-success"><?= $analytics['feedback']['positive_feedback'] ?></h5>
                            <small class="text-muted">Positive (4-5⭐)</small>
                        </div>
                        <div class="col-6">
                            <h5 class="text-danger"><?= $analytics['feedback']['negative_feedback'] ?></h5>
                            <small class="text-muted">Negative (1-2⭐)</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-graph-up"></i> Platform Performance</h5>
                </div>
                <div class="card-body">
                    <div class="performance-metric mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Claim Rate</span>
                            <strong><?= round($analytics['lifecycle']['claim_rate_percentage'], 1) ?>%</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?= $analytics['lifecycle']['claim_rate_percentage'] ?>%"></div>
                        </div>
                    </div>
                    <div class="performance-metric mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Avg Lifetime</span>
                            <strong><?= round($analytics['lifecycle']['avg_lifetime_hours'], 1) ?>h</strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-info" style="width: <?= min(($analytics['lifecycle']['avg_lifetime_hours'] / 168) * 100, 100) ?>%"></div>
                        </div>
                    </div>
                    <div class="performance-metric mb-3">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Engagement</span>
                            <strong><?= round($analytics['overview']['avg_views_per_donation'], 1) ?></strong>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-warning" style="width: <?= min(($analytics['overview']['avg_views_per_donation'] / 20) * 100, 100) ?>%"></div>
                        </div>
                    </div>
                    <hr>
                    <small class="text-muted">
                        <i class="bi bi-info-circle"></i> 
                        Higher metrics indicate better platform performance
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Peak Hours & Locations -->
    <div class="row mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock"></i> Peak Donation Hours</h5>
                </div>
                <div class="card-body">
                    <canvas id="peakHoursChart"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Top Locations</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Location</th>
                                    <th class="text-center">Donations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($analytics['locations'])): ?>
                                    <?php foreach ($analytics['locations'] as $location): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($location['area']) ?></td>
                                            <td class="text-center">
                                                <span class="badge bg-primary"><?= $location['donations_count'] ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="2" class="text-center text-muted">No location data available</td>
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

.status-stat {
    padding: 10px;
}

.engagement-item {
    padding-bottom: 12px;
    border-bottom: 1px solid #e9ecef;
}

.engagement-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.performance-metric {
    padding: 8px 0;
}

@media print {
    .sidebar, .header, .btn, .card-header button {
        display: none !important;
    }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Status Distribution Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: ['Claimed', 'Available', 'Reserved', 'Expired', 'Cancelled'],
        datasets: [{
            data: [
                <?= $analytics['overview']['claimed_donations'] ?>,
                <?= $analytics['overview']['available_donations'] ?>,
                <?= $analytics['overview']['reserved_donations'] ?>,
                <?= $analytics['overview']['expired_donations'] ?>,
                <?= $analytics['overview']['cancelled_donations'] ?>
            ],
            backgroundColor: [
                'rgba(25, 135, 84, 0.8)',
                'rgba(13, 110, 253, 0.8)',
                'rgba(255, 193, 7, 0.8)',
                'rgba(220, 53, 69, 0.8)',
                'rgba(108, 117, 125, 0.8)'
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
            if (!empty($analytics['food_types'])) {
                echo implode(',', array_map(function($f) { return "'" . ucfirst($f['food_type']) . "'"; }, $analytics['food_types'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [{
            data: [<?php 
                if (!empty($analytics['food_types'])) {
                    echo implode(',', array_column($analytics['food_types'], 'count')); 
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

// Monthly Trends Chart
const trendsCtx = document.getElementById('trendsChart').getContext('2d');
new Chart(trendsCtx, {
    type: 'line',
    data: {
        labels: [<?php 
            if (!empty($analytics['monthly_trends'])) {
                echo implode(',', array_map(function($t) { return "'" . date('M Y', strtotime($t['month'] . '-01')) . "'"; }, $analytics['monthly_trends'])); 
            } else {
                echo "'No Data'";
            }
        ?>],
        datasets: [
            {
                label: 'Total Donations',
                data: [<?php 
                    if (!empty($analytics['monthly_trends'])) {
                        echo implode(',', array_column($analytics['monthly_trends'], 'donations_count')); 
                    } else {
                        echo "0";
                    }
                ?>],
                borderColor: 'rgb(13, 110, 253)',
                backgroundColor: 'rgba(13, 110, 253, 0.1)',
                tension: 0.4
            },
            {
                label: 'Claimed Donations',
                data: [<?php 
                    if (!empty($analytics['monthly_trends'])) {
                        echo implode(',', array_column($analytics['monthly_trends'], 'claimed_count')); 
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

// Peak Hours Chart
const peakHoursCtx = document.getElementById('peakHoursChart').getContext('2d');
new Chart(peakHoursCtx, {
    type: 'bar',
    data: {
        labels: [<?php 
            $hours = [];
            for ($i = 0; $i < 24; $i++) {
                $hours[$i] = 0;
            }
            if (!empty($analytics['peak_hours'])) {
                foreach ($analytics['peak_hours'] as $hour_data) {
                    $hours[$hour_data['hour']] = $hour_data['donations_count'];
                }
            }
            echo implode(',', array_map(function($h, $i) { return "'" . $i . ":00'"; }, $hours, array_keys($hours)));
        ?>],
        datasets: [{
            label: 'Donations per Hour',
            data: [<?= implode(',', $hours) ?>],
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
                beginAtZero: true
            }
        }
    }
});

// Enhanced Functions
function exportDonationData() {
    const data = [
        ['Metric', 'Value'],
        ['Total Donations', <?= $analytics['overview']['total_donations'] ?>],
        ['Claimed Donations', <?= $analytics['overview']['claimed_donations'] ?>],
        ['Available Donations', <?= $analytics['overview']['available_donations'] ?>],
        ['Reserved Donations', <?= $analytics['overview']['reserved_donations'] ?>],
        ['Expired Donations', <?= $analytics['overview']['expired_donations'] ?>],
        ['Cancelled Donations', <?= $analytics['overview']['cancelled_donations'] ?>],
        ['Total Views', <?= $analytics['overview']['total_views'] ?>],
        ['Average Views per Donation', <?= round($analytics['overview']['avg_views_per_donation'], 2) ?>],
        ['Total Requests', <?= $analytics['requests']['total_requests'] ?>],
        ['Completed Requests', <?= $analytics['requests']['completed_requests'] ?>],
        ['Pending Requests', <?= $analytics['requests']['pending_requests'] ?>],
        ['Average Response Time (hours)', <?= round($analytics['requests']['avg_response_time_hours'], 2) ?>],
        ['Average Rating', <?= round($analytics['feedback']['avg_rating'], 2) ?>],
        ['Total Feedback', <?= $analytics['feedback']['total_feedback'] ?>],
        ['Claim Rate (%)', <?= round($analytics['lifecycle']['claim_rate_percentage'], 2) ?>],
        ['Average Lifetime (hours)', <?= round($analytics['lifecycle']['avg_lifetime_hours'], 2) ?>]
    ];
    
    let csv = data.map(row => row.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'donation_analytics_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Donation data exported successfully!', 'success');
}

function refreshAnalytics() {
    showNotification('Refreshing analytics data...', 'info');
    setTimeout(() => {
        window.location.reload();
    }, 1000);
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
        refreshAnalytics();
    }
}, 600000); // 10 minutes
</script>

<?php include 'footer.php'; ?>

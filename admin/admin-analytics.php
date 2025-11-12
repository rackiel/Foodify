<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

include '../config/db.php';

// Get date range from filters
$period = isset($_GET['period']) ? $_GET['period'] : '30days';
$end_date = date('Y-m-d');

switch ($period) {
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        $period_label = 'Last 7 Days';
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        $period_label = 'Last 90 Days';
        break;
    case 'year':
        $start_date = date('Y-m-d', strtotime('-1 year'));
        $period_label = 'Last Year';
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
        $period_label = 'Last 30 Days';
}

// Initialize statistics
$stats = [
    'total_users' => 0,
    'active_users' => 0,
    'total_donations' => 0,
    'total_posts' => 0,
    'total_challenges' => 0,
    'total_engagement' => 0,
    'growth_rate' => 0
];

$chart_data = [
    'user_growth' => [],
    'donation_trends' => [],
    'engagement_trends' => [],
    'user_types' => [],
    'donation_status' => [],
    'post_types' => [],
    'top_contributors' => []
];

try {
    // Total Users
    $result = $conn->query("SELECT COUNT(*) as count FROM user_accounts WHERE role != 'admin'");
    if ($result) {
        $stats['total_users'] = $result->fetch_assoc()['count'];
    }

    // Active Users (logged in last 30 days)
    $result = $conn->query("
        SELECT COUNT(*) as count 
        FROM user_accounts 
        WHERE role != 'admin' 
        AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    if ($result) {
        $stats['active_users'] = $result->fetch_assoc()['count'];
    }

    // Total Donations
    $result = $conn->query("SELECT COUNT(*) as count FROM food_donations");
    if ($result) {
        $stats['total_donations'] = $result->fetch_assoc()['count'];
    }

    // Total Posts
    $check = $conn->query("SHOW TABLES LIKE 'recipes_tips'");
    if ($check && $check->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM recipes_tips");
        if ($result) {
            $stats['total_posts'] = $result->fetch_assoc()['count'];
        }

        // Total Engagement
        $result = $conn->query("
            SELECT SUM(likes_count) + SUM(comments_count) + SUM(shares_count) as engagement
            FROM recipes_tips
        ");
        if ($result) {
            $row = $result->fetch_assoc();
            $stats['total_engagement'] = $row['engagement'] ?? 0;
        }
    }

    // Total Challenges
    $check = $conn->query("SHOW TABLES LIKE 'challenges'");
    if ($check && $check->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM challenges");
        if ($result) {
            $stats['total_challenges'] = $result->fetch_assoc()['count'];
        }
    }

    // User Growth Chart Data
    $result = $conn->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM user_accounts
        WHERE created_at BETWEEN '$start_date' AND '$end_date'
        AND role != 'admin'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $chart_data['user_growth'][] = $row;
        }
    }

    // Donation Trends Chart Data
    $result = $conn->query("
        SELECT DATE(created_at) as date, COUNT(*) as count
        FROM food_donations
        WHERE created_at BETWEEN '$start_date' AND '$end_date'
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $chart_data['donation_trends'][] = $row;
        }
    }

    // User Types Distribution
    $result = $conn->query("
        SELECT role, COUNT(*) as count
        FROM user_accounts
        WHERE role != 'admin'
        GROUP BY role
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $chart_data['user_types'][] = $row;
        }
    }

    // Donation Status Distribution
    $result = $conn->query("
        SELECT status, COUNT(*) as count
        FROM food_donations
        GROUP BY status
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $chart_data['donation_status'][] = $row;
        }
    }

    // Post Types Distribution
    $check = $conn->query("SHOW TABLES LIKE 'recipes_tips'");
    if ($check && $check->num_rows > 0) {
        $result = $conn->query("
            SELECT post_type, COUNT(*) as count
            FROM recipes_tips
            GROUP BY post_type
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $chart_data['post_types'][] = $row;
            }
        }
    }

    // Top Contributors
    $check = $conn->query("SHOW TABLES LIKE 'recipes_tips'");
    if ($check && $check->num_rows > 0) {
        $result = $conn->query("
            SELECT ua.full_name, COUNT(*) as post_count, SUM(rt.likes_count) as total_likes
            FROM user_accounts ua
            JOIN recipes_tips rt ON ua.user_id = rt.user_id
            WHERE ua.role = 'resident'
            GROUP BY ua.user_id
            ORDER BY post_count DESC, total_likes DESC
            LIMIT 5
        ");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $chart_data['top_contributors'][] = $row;
            }
        }
    }

    // Calculate growth rate
    $prev_start = date('Y-m-d', strtotime($start_date . ' -30 days'));
    $result = $conn->query("
        SELECT 
            COUNT(CASE WHEN created_at BETWEEN '$start_date' AND '$end_date' THEN 1 END) as current_period,
            COUNT(CASE WHEN created_at BETWEEN '$prev_start' AND '$start_date' THEN 1 END) as prev_period
        FROM user_accounts
        WHERE role != 'admin'
    ");
    if ($result) {
        $growth = $result->fetch_assoc();
        if ($growth['prev_period'] > 0) {
            $stats['growth_rate'] = round((($growth['current_period'] - $growth['prev_period']) / $growth['prev_period']) * 100, 1);
        }
    }
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}

include 'header.php';
?>

<body>
    <?php include 'topbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1><i class="bi bi-graph-up"></i> Analytics Dashboard</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item">Analytics & Reports</li>
                    <li class="breadcrumb-item active">Analytics Dashboard</li>
                </ol>
            </nav>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <section class="section dashboard">
            <!-- Period Filter -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body py-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Time Period: <strong><?= $period_label ?></strong></h6>
                                <div class="btn-group">
                                    <a href="?period=7days" class="btn btn-sm btn-outline-primary <?= $period === '7days' ? 'active' : '' ?>">7 Days</a>
                                    <a href="?period=30days" class="btn btn-sm btn-outline-primary <?= $period === '30days' ? 'active' : '' ?>">30 Days</a>
                                    <a href="?period=90days" class="btn btn-sm btn-outline-primary <?= $period === '90days' ? 'active' : '' ?>">90 Days</a>
                                    <a href="?period=year" class="btn btn-sm btn-outline-primary <?= $period === 'year' ? 'active' : '' ?>">1 Year</a>
                                </div>
                            </div>
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
                                    <h6 class="text-muted mb-2">Total Users</h6>
                                    <h3 class="mb-0"><?= number_format($stats['total_users']) ?></h3>
                                </div>
                                <div class="stat-icon bg-primary">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <small class="text-<?= $stats['growth_rate'] >= 0 ? 'success' : 'danger' ?>">
                                <i class="bi bi-arrow-<?= $stats['growth_rate'] >= 0 ? 'up' : 'down' ?>"></i>
                                <?= abs($stats['growth_rate']) ?>% growth
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Active Users</h6>
                                    <h3 class="mb-0"><?= number_format($stats['active_users']) ?></h3>
                                </div>
                                <div class="stat-icon bg-success">
                                    <i class="bi bi-person-check"></i>
                                </div>
                            </div>
                            <small class="text-muted">
                                <?= $stats['total_users'] > 0 ? round(($stats['active_users'] / $stats['total_users']) * 100, 1) : 0 ?>% of total
                            </small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Total Donations</h6>
                                    <h3 class="mb-0"><?= number_format($stats['total_donations']) ?></h3>
                                </div>
                                <div class="stat-icon bg-warning">
                                    <i class="bi bi-basket"></i>
                                </div>
                            </div>
                            <small class="text-muted">Food sharing posts</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Total Engagement</h6>
                                    <h3 class="mb-0"><?= number_format($stats['total_engagement']) ?></h3>
                                </div>
                                <div class="stat-icon bg-info">
                                    <i class="bi bi-heart"></i>
                                </div>
                            </div>
                            <small class="text-muted">Likes + Comments + Shares</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 1 -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-graph-up"></i> User Growth Trend
                            </h5>
                            <canvas id="userGrowthChart" height="80"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-pie-chart"></i> User Distribution
                            </h5>
                            <div class="chart-container">
                                <canvas id="userTypesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 2 -->
            <div class="row mb-4">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-basket"></i> Donation Trends
                            </h5>
                            <canvas id="donationTrendsChart" height="100"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-diagram-3"></i> Donation Status
                            </h5>
                            <canvas id="donationStatusChart" height="100"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row 3 -->
            <div class="row mb-4">
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-star"></i> Top Contributors
                            </h5>
                            <canvas id="topContributorsChart" height="80"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-file-post"></i> Content Types
                            </h5>
                            <div class="chart-container">
                                <canvas id="postTypesChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Additional Stats -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-clipboard-data"></i> Key Performance Indicators
                            </h5>
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <div class="kpi-card">
                                        <h6>Avg Posts per User</h6>
                                        <h3><?= $stats['total_users'] > 0 ? round($stats['total_posts'] / $stats['total_users'], 2) : 0 ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="kpi-card">
                                        <h6>Avg Donations per User</h6>
                                        <h3><?= $stats['total_users'] > 0 ? round($stats['total_donations'] / $stats['total_users'], 2) : 0 ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="kpi-card">
                                        <h6>Engagement Rate</h6>
                                        <h3><?= $stats['total_posts'] > 0 ? round($stats['total_engagement'] / $stats['total_posts'], 1) : 0 ?></h3>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="kpi-card">
                                        <h6>Active User Rate</h6>
                                        <h3><?= $stats['total_users'] > 0 ? round(($stats['active_users'] / $stats['total_users']) * 100, 1) : 0 ?>%</h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <style>
        .stat-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .stat-card .card-body {
            padding: 24px 20px 20px 20px;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
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

        .kpi-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
        }

        .kpi-card h6 {
            color: #6c757d;
            font-size: 0.875rem;
            margin-bottom: 10px;
        }

        .kpi-card h3 {
            color: #0d6efd;
            margin: 0;
        }

        /* Constrain smaller side charts so the dashboard doesn't vertically expand
           when Chart.js places legends or resizes. */
        .chart-container {
            height: 220px;
            max-height: 320px;
            position: relative;
        }

        /* Make canvas fill the chart container and let Chart.js manage sizing */
        .chart-container canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // User Growth Chart
        const userGrowthData = <?= json_encode($chart_data['user_growth']) ?>;
        const userGrowthCtx = document.getElementById('userGrowthChart');
        new Chart(userGrowthCtx, {
            type: 'line',
            data: {
                labels: userGrowthData.map(d => d.date),
                datasets: [{
                    label: 'New Users',
                    data: userGrowthData.map(d => d.count),
                    borderColor: '#0d6efd',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
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

        // User Types Chart
        const userTypesData = <?= json_encode($chart_data['user_types']) ?>;
        const userTypesCtx = document.getElementById('userTypesChart');
        new Chart(userTypesCtx, {
            type: 'doughnut',
            data: {
                labels: userTypesData.map(d => d.role.charAt(0).toUpperCase() + d.role.slice(1)),
                datasets: [{
                    data: userTypesData.map(d => d.count),
                    backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Donation Trends Chart
        const donationTrendsData = <?= json_encode($chart_data['donation_trends']) ?>;
        const donationTrendsCtx = document.getElementById('donationTrendsChart');
        new Chart(donationTrendsCtx, {
            type: 'bar',
            data: {
                labels: donationTrendsData.map(d => d.date),
                datasets: [{
                    label: 'Donations',
                    data: donationTrendsData.map(d => d.count),
                    backgroundColor: '#ffc107',
                    borderColor: '#ffc107',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
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

        // Donation Status Chart
        const donationStatusData = <?= json_encode($chart_data['donation_status']) ?>;
        const donationStatusCtx = document.getElementById('donationStatusChart');
        new Chart(donationStatusCtx, {
            type: 'pie',
            data: {
                labels: donationStatusData.map(d => d.status.charAt(0).toUpperCase() + d.status.slice(1)),
                datasets: [{
                    data: donationStatusData.map(d => d.count),
                    backgroundColor: ['#198754', '#0dcaf0', '#ffc107', '#dc3545', '#6c757d']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Top Contributors Chart
        const topContributorsData = <?= json_encode($chart_data['top_contributors']) ?>;
        const topContributorsCtx = document.getElementById('topContributorsChart');
        new Chart(topContributorsCtx, {
            type: 'bar',
            data: {
                labels: topContributorsData.map(d => d.full_name),
                datasets: [{
                    label: 'Posts',
                    data: topContributorsData.map(d => d.post_count),
                    backgroundColor: '#0d6efd',
                    yAxisID: 'y'
                }, {
                    label: 'Likes',
                    data: topContributorsData.map(d => d.total_likes),
                    backgroundColor: '#dc3545',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                interaction: {
                    mode: 'index',
                    intersect: false
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Posts'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        },
                        title: {
                            display: true,
                            text: 'Likes'
                        }
                    }
                }
            }
        });

        // Post Types Chart
        const postTypesData = <?= json_encode($chart_data['post_types']) ?>;
        const postTypesCtx = document.getElementById('postTypesChart');
        new Chart(postTypesCtx, {
            type: 'polarArea',
            data: {
                labels: postTypesData.map(d => d.post_type.charAt(0).toUpperCase() + d.post_type.slice(1)),
                datasets: [{
                    data: postTypesData.map(d => d.count),
                    backgroundColor: ['#198754', '#ffc107', '#0dcaf0']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
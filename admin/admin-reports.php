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

// Handle Export Requests
if (isset($_GET['export']) && isset($_GET['report_type'])) {
    $report_type = $_GET['report_type'];
    $start_date = $_GET['start_date'] ?? date('Y-m-01');
    $end_date = $_GET['end_date'] ?? date('Y-m-d');
    $format = $_GET['format'] ?? 'csv';
    
    try {
        $data = [];
        $filename = '';
        $headers = [];
        
        switch ($report_type) {
            case 'users':
                $filename = 'users_report_' . date('Y-m-d');
                $headers = ['ID', 'Name', 'Email', 'Role', 'Status', 'Registration Date', 'Last Login'];
                
                $stmt = $conn->prepare("
                    SELECT user_id, full_name, email, role, status, 
                           DATE_FORMAT(created_at, '%Y-%m-%d') as reg_date,
                           DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i') as last_activity
                    FROM user_accounts
                    WHERE created_at BETWEEN ? AND ?
                    ORDER BY created_at DESC
                ");
                $stmt->bind_param('ss', $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                break;
                
            case 'donations':
                $filename = 'donations_report_' . date('Y-m-d');
                $headers = ['ID', 'Title', 'Donor', 'Food Type', 'Status', 'Views', 'Created Date'];
                
                $stmt = $conn->prepare("
                    SELECT fd.id, fd.title, ua.full_name as donor, fd.food_type, 
                           fd.status, fd.views_count,
                           DATE_FORMAT(fd.created_at, '%Y-%m-%d %H:%i') as created
                    FROM food_donations fd
                    JOIN user_accounts ua ON fd.user_id = ua.user_id
                    WHERE fd.created_at BETWEEN ? AND ?
                    ORDER BY fd.created_at DESC
                ");
                $stmt->bind_param('ss', $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                break;
                
            case 'newsfeeds':
                $filename = 'newsfeeds_report_' . date('Y-m-d');
                $headers = ['ID', 'Type', 'Title', 'Author', 'Likes', 'Comments', 'Shares', 'Views', 'Created Date'];
                
                $check = $conn->query("SHOW TABLES LIKE 'recipes_tips'");
                if ($check && $check->num_rows > 0) {
                    $stmt = $conn->prepare("
                        SELECT rt.id, rt.post_type, rt.title, ua.full_name as author,
                               rt.likes_count, rt.comments_count, rt.shares_count, rt.views_count,
                               DATE_FORMAT(rt.created_at, '%Y-%m-%d %H:%i') as created
                        FROM recipes_tips rt
                        JOIN user_accounts ua ON rt.user_id = ua.user_id
                        WHERE rt.created_at BETWEEN ? AND ?
                        ORDER BY rt.created_at DESC
                    ");
                    $stmt->bind_param('ss', $start_date, $end_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                }
                break;
                
            case 'challenges':
                $filename = 'challenges_report_' . date('Y-m-d');
                $headers = ['ID', 'Title', 'Type', 'Category', 'Participants', 'Completed', 'Points', 'Status', 'Start Date', 'End Date'];
                
                $check = $conn->query("SHOW TABLES LIKE 'challenges'");
                if ($check && $check->num_rows > 0) {
                    $stmt = $conn->prepare("
                        SELECT c.challenge_id, c.title, c.challenge_type, c.category,
                               COUNT(DISTINCT cp.participant_id) as participants,
                               COUNT(CASE WHEN cp.completed = TRUE THEN 1 END) as completed,
                               c.points, c.status,
                               DATE_FORMAT(c.start_date, '%Y-%m-%d') as start_dt,
                               DATE_FORMAT(c.end_date, '%Y-%m-%d') as end_dt
                        FROM challenges c
                        LEFT JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id
                        WHERE c.created_at BETWEEN ? AND ?
                        GROUP BY c.challenge_id
                        ORDER BY c.created_at DESC
                    ");
                    $stmt->bind_param('ss', $start_date, $end_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_all(MYSQLI_ASSOC);
                    $stmt->close();
                }
                break;
                
            case 'engagement':
                $filename = 'engagement_report_' . date('Y-m-d');
                $headers = ['Date', 'New Users', 'New Donations', 'New Posts', 'Total Engagement'];
                
                // Daily aggregated engagement data
                $stmt = $conn->prepare("
                    SELECT 
                        DATE(created_at) as date,
                        COUNT(*) as new_users,
                        0 as new_donations,
                        0 as new_posts,
                        0 as engagement
                    FROM user_accounts
                    WHERE created_at BETWEEN ? AND ?
                    GROUP BY DATE(created_at)
                    ORDER BY date DESC
                ");
                $stmt->bind_param('ss', $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $data = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                break;
        }
        
        // Generate CSV
        if ($format === 'csv') {
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
            
            $output = fopen('php://output', 'w');
            fputcsv($output, $headers);
            
            foreach ($data as $row) {
                fputcsv($output, array_values($row));
            }
            
            fclose($output);
            exit();
        }
        
        // Generate JSON
        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . $filename . '.json"');
            
            echo json_encode([
                'report_type' => $report_type,
                'start_date' => $start_date,
                'end_date' => $end_date,
                'generated_at' => date('Y-m-d H:i:s'),
                'total_records' => count($data),
                'data' => $data
            ], JSON_PRETTY_PRINT);
            exit();
        }
        
    } catch (Exception $e) {
        die('Error generating report: ' . $e->getMessage());
    }
}

// Get report statistics
$stats = [
    'total_users' => 0,
    'total_donations' => 0,
    'total_posts' => 0,
    'total_challenges' => 0
];

try {
    // Users count
    $result = $conn->query("SELECT COUNT(*) as count FROM user_accounts WHERE role = 'resident'");
    if ($result) {
        $stats['total_users'] = $result->fetch_assoc()['count'];
    }
    
    // Donations count
    $result = $conn->query("SELECT COUNT(*) as count FROM food_donations");
    if ($result) {
        $stats['total_donations'] = $result->fetch_assoc()['count'];
    }
    
    // Posts count
    $check = $conn->query("SHOW TABLES LIKE 'recipes_tips'");
    if ($check && $check->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM recipes_tips");
        if ($result) {
            $stats['total_posts'] = $result->fetch_assoc()['count'];
        }
    }
    
    // Challenges count
    $check = $conn->query("SHOW TABLES LIKE 'challenges'");
    if ($check && $check->num_rows > 0) {
        $result = $conn->query("SELECT COUNT(*) as count FROM challenges");
        if ($result) {
            $stats['total_challenges'] = $result->fetch_assoc()['count'];
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
        <h1><i class="bi bi-file-earmark-text"></i> Export Reports</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Analytics & Reports</li>
                <li class="breadcrumb-item active">Export Reports</li>
            </ol>
        </nav>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <section class="section">
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
                        <small class="text-muted">Registered residents</small>
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
                            <div class="stat-icon bg-success">
                                <i class="bi bi-basket"></i>
                            </div>
                        </div>
                        <small class="text-muted">Food donations posted</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Posts</h6>
                                <h3 class="mb-0"><?= number_format($stats['total_posts']) ?></h3>
                            </div>
                            <div class="stat-icon bg-info">
                                <i class="bi bi-file-post"></i>
                            </div>
                        </div>
                        <small class="text-muted">Recipes & tips shared</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Challenges</h6>
                                <h3 class="mb-0"><?= number_format($stats['total_challenges']) ?></h3>
                            </div>
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-trophy"></i>
                            </div>
                        </div>
                        <small class="text-muted">Community challenges</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Report Generator -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Generate Report</h5>
                        
                        <form id="reportForm" method="get">
                            <input type="hidden" name="export" value="1">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Report Type *</label>
                                    <select class="form-select" name="report_type" id="reportType" required>
                                        <option value="">Select Report Type</option>
                                        <option value="users">Users Report</option>
                                        <option value="donations">Food Donations Report</option>
                                        <option value="newsfeeds">Newsfeeds Report</option>
                                        <option value="challenges">Challenges Report</option>
                                        <option value="engagement">Engagement Report</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Export Format *</label>
                                    <select class="form-select" name="format" required>
                                        <option value="csv">CSV (Excel Compatible)</option>
                                        <option value="json">JSON</option>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" 
                                           value="<?= date('Y-m-01') ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" name="end_date" 
                                           value="<?= date('Y-m-d') ?>" required>
                                </div>
                            </div>
                            
                            <div class="alert alert-info" id="reportDescription">
                                <i class="bi bi-info-circle"></i> Select a report type to see description
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-download"></i> Generate & Download Report
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Reports -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h5 class="card-title">Quick Reports</h5>
                        <p class="text-muted">Generate common reports with one click</p>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="quick-report-card">
                                    <h6><i class="bi bi-calendar-week"></i> This Week</h6>
                                    <p class="small text-muted">Data from the last 7 days</p>
                                    <div class="btn-group-sm">
                                        <button class="btn btn-sm btn-outline-primary" onclick="quickReport('users', 'week')">Users</button>
                                        <button class="btn btn-sm btn-outline-success" onclick="quickReport('donations', 'week')">Donations</button>
                                        <button class="btn btn-sm btn-outline-info" onclick="quickReport('newsfeeds', 'week')">Posts</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="quick-report-card">
                                    <h6><i class="bi bi-calendar-month"></i> This Month</h6>
                                    <p class="small text-muted">Data from current month</p>
                                    <div class="btn-group-sm">
                                        <button class="btn btn-sm btn-outline-primary" onclick="quickReport('users', 'month')">Users</button>
                                        <button class="btn btn-sm btn-outline-success" onclick="quickReport('donations', 'month')">Donations</button>
                                        <button class="btn btn-sm btn-outline-info" onclick="quickReport('newsfeeds', 'month')">Posts</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="quick-report-card">
                                    <h6><i class="bi bi-calendar3"></i> Last 30 Days</h6>
                                    <p class="small text-muted">Data from past 30 days</p>
                                    <div class="btn-group-sm">
                                        <button class="btn btn-sm btn-outline-primary" onclick="quickReport('users', '30days')">Users</button>
                                        <button class="btn btn-sm btn-outline-success" onclick="quickReport('donations', '30days')">Donations</button>
                                        <button class="btn btn-sm btn-outline-warning" onclick="quickReport('challenges', '30days')">Challenges</button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="quick-report-card">
                                    <h6><i class="bi bi-clock-history"></i> All Time</h6>
                                    <p class="small text-muted">Complete historical data</p>
                                    <div class="btn-group-sm">
                                        <button class="btn btn-sm btn-outline-primary" onclick="quickReport('users', 'all')">Users</button>
                                        <button class="btn btn-sm btn-outline-success" onclick="quickReport('donations', 'all')">Donations</button>
                                        <button class="btn btn-sm btn-outline-info" onclick="quickReport('engagement', 'all')">Engagement</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Report Types Info -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-info-circle"></i> Report Types</h5>
                        
                        <div class="report-type-info mb-3">
                            <strong>Users Report</strong>
                            <p class="small text-muted mb-0">
                                All registered users with their details, registration dates, and status.
                            </p>
                        </div>
                        
                        <div class="report-type-info mb-3">
                            <strong>Food Donations Report</strong>
                            <p class="small text-muted mb-0">
                                All food donation posts with donor info, status, and engagement metrics.
                            </p>
                        </div>
                        
                        <div class="report-type-info mb-3">
                            <strong>Newsfeeds Report</strong>
                            <p class="small text-muted mb-0">
                                Recipes and tips with author details and engagement statistics.
                            </p>
                        </div>
                        
                        <div class="report-type-info mb-3">
                            <strong>Challenges Report</strong>
                            <p class="small text-muted mb-0">
                                All challenges with participation and completion statistics.
                            </p>
                        </div>
                        
                        <div class="report-type-info">
                            <strong>Engagement Report</strong>
                            <p class="small text-muted mb-0">
                                Daily aggregated data showing platform activity and growth trends.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Export Formats -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-file-earmark"></i> Export Formats</h5>
                        
                        <div class="format-info mb-3">
                            <strong>CSV Format</strong>
                            <p class="small text-muted mb-0">
                                Compatible with Excel, Google Sheets, and other spreadsheet applications.
                            </p>
                        </div>
                        
                        <div class="format-info">
                            <strong>JSON Format</strong>
                            <p class="small text-muted mb-0">
                                Structured data format for developers and data analysis tools.
                            </p>
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

.quick-report-card {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    height: 100%;
}

.quick-report-card h6 {
    color: #495057;
    margin-bottom: 5px;
}

.report-type-info, .format-info {
    padding-bottom: 10px;
    border-bottom: 1px solid #dee2e6;
}

.report-type-info:last-child, .format-info:last-child {
    border-bottom: none;
    padding-bottom: 0;
}
</style>

<script>
// Report descriptions
const reportDescriptions = {
    'users': 'Exports all user accounts with registration details, roles, status, and activity information.',
    'donations': 'Exports all food donation posts including donor information, food types, status, and view counts.',
    'newsfeeds': 'Exports recipes and tips posts with author details, engagement metrics (likes, comments, shares), and views.',
    'challenges': 'Exports all challenges with participation statistics, completion rates, and reward points.',
    'engagement': 'Exports daily aggregated data showing new users, donations, posts, and total engagement over time.'
};

// Update description when report type changes
document.getElementById('reportType').addEventListener('change', function() {
    const desc = reportDescriptions[this.value];
    const descBox = document.getElementById('reportDescription');
    
    if (desc) {
        descBox.innerHTML = '<i class="bi bi-info-circle"></i> ' + desc;
        descBox.className = 'alert alert-info';
    } else {
        descBox.innerHTML = '<i class="bi bi-info-circle"></i> Select a report type to see description';
        descBox.className = 'alert alert-info';
    }
});

// Quick report function
function quickReport(type, period) {
    let startDate, endDate = new Date().toISOString().split('T')[0];
    const today = new Date();
    
    switch(period) {
        case 'week':
            startDate = new Date(today.setDate(today.getDate() - 7)).toISOString().split('T')[0];
            break;
        case 'month':
            startDate = new Date(today.getFullYear(), today.getMonth(), 1).toISOString().split('T')[0];
            break;
        case '30days':
            startDate = new Date(today.setDate(today.getDate() - 30)).toISOString().split('T')[0];
            break;
        case 'all':
            startDate = '2020-01-01';
            endDate = new Date().toISOString().split('T')[0];
            break;
    }
    
    window.location.href = `admin-reports.php?export=1&report_type=${type}&format=csv&start_date=${startDate}&end_date=${endDate}`;
}

// Form submission
document.getElementById('reportForm').addEventListener('submit', function(e) {
    const reportType = document.getElementById('reportType').value;
    if (!reportType) {
        e.preventDefault();
        alert('Please select a report type');
    }
});
</script>

<?php include 'footer.php'; ?>
</body>
</html>


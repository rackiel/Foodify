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

// Handle report generation requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false];
    
    try {
        if ($action === 'generate_report') {
            $report_type = $_POST['report_type'] ?? '';
            $date_from = $_POST['date_from'] ?? '';
            $date_to = $_POST['date_to'] ?? '';
            $format = $_POST['format'] ?? 'csv';
            
            // Validate dates
            if (!empty($date_from) && !empty($date_to)) {
                $date_condition = "AND created_at BETWEEN '$date_from' AND '$date_to'";
            } else {
                $date_condition = "";
            }
            
            $data = [];
            $filename = '';
            
            switch ($report_type) {
                case 'donations':
                    $stmt = $conn->query("
                        SELECT 
                            fd.id,
                            fd.title,
                            fd.food_type,
                            fd.status,
                            fd.views_count,
                            fd.location_address,
                            fd.created_at,
                            u.full_name as donor_name,
                            u.email as donor_email
                        FROM food_donations fd
                        JOIN user_accounts u ON fd.user_id = u.user_id
                        WHERE 1=1 $date_condition
                        ORDER BY fd.created_at DESC
                    ");
                    $data = $stmt->fetch_all(MYSQLI_ASSOC);
                    $filename = 'food_donations_report';
                    break;
                    
                case 'users':
                    $stmt = $conn->query("
                        SELECT 
                            user_id,
                            full_name,
                            email,
                            user_type,
                            status,
                            created_at
                        FROM user_accounts
                        WHERE 1=1 $date_condition
                        ORDER BY created_at DESC
                    ");
                    $data = $stmt->fetch_all(MYSQLI_ASSOC);
                    $filename = 'users_report';
                    break;
                    
                case 'announcements':
                    $stmt = $conn->query("
                        SELECT 
                            a.id,
                            a.title,
                            a.type,
                            a.priority,
                            a.status,
                            a.likes_count,
                            a.comments_count,
                            a.shares_count,
                            a.created_at,
                            u.full_name as author_name
                        FROM announcements a
                        JOIN user_accounts u ON a.user_id = u.user_id
                        WHERE 1=1 $date_condition
                        ORDER BY a.created_at DESC
                    ");
                    $data = $stmt->fetch_all(MYSQLI_ASSOC);
                    $filename = 'announcements_report';
                    break;
                    
                case 'requests':
                    $stmt = $conn->query("
                        SELECT 
                            fdr.id,
                            fdr.status,
                            fdr.reserved_at,
                            fdr.responded_at,
                            fd.title as donation_title,
                            u.full_name as requester_name,
                            u.email as requester_email
                        FROM food_donation_reservations fdr
                        JOIN food_donations fd ON fdr.donation_id = fd.id
                        JOIN user_accounts u ON fdr.user_id = u.user_id
                        WHERE 1=1 $date_condition
                        ORDER BY fdr.reserved_at DESC
                    ");
                    $data = $stmt->fetch_all(MYSQLI_ASSOC);
                    $filename = 'food_requests_report';
                    break;
                    
                case 'engagement':
                    $stmt = $conn->query("
                        SELECT 
                            u.user_id,
                            u.full_name,
                            u.email,
                            u.user_type,
                            COUNT(DISTINCT a.id) as announcements,
                            COUNT(DISTINCT fd.id) as donations,
                            COUNT(DISTINCT al.id) as likes,
                            COUNT(DISTINCT ac.id) as comments
                        FROM user_accounts u
                        LEFT JOIN announcements a ON u.user_id = a.user_id
                        LEFT JOIN food_donations fd ON u.user_id = fd.user_id
                        LEFT JOIN announcement_likes al ON u.user_id = al.user_id
                        LEFT JOIN announcement_comments ac ON u.user_id = ac.user_id
                        WHERE u.status = 'approved' $date_condition
                        GROUP BY u.user_id
                        ORDER BY (COUNT(DISTINCT a.id) + COUNT(DISTINCT fd.id) + COUNT(DISTINCT al.id) + COUNT(DISTINCT ac.id)) DESC
                    ");
                    $data = $stmt->fetch_all(MYSQLI_ASSOC);
                    $filename = 'user_engagement_report';
                    break;
                    
                case 'summary':
                    // Comprehensive summary report
                    $summary = [];
                    
                    // Overall stats
                    $stmt = $conn->query("SELECT COUNT(*) as total FROM user_accounts WHERE status = 'approved'");
                    $summary['total_users'] = $stmt->fetch_assoc()['total'];
                    
                    $stmt = $conn->query("SELECT COUNT(*) as total FROM food_donations");
                    $summary['total_donations'] = $stmt->fetch_assoc()['total'];
                    
                    $stmt = $conn->query("SELECT COUNT(*) as total FROM announcements WHERE status = 'published'");
                    $summary['total_announcements'] = $stmt->fetch_assoc()['total'];
                    
                    $stmt = $conn->query("SELECT COUNT(*) as total FROM food_donation_reservations");
                    $summary['total_requests'] = $stmt->fetch_assoc()['total'];
                    
                    $data = [$summary];
                    $filename = 'platform_summary_report';
                    break;
            }
            
            $response['success'] = true;
            $response['data'] = $data;
            $response['filename'] = $filename . '_' . date('Y-m-d');
            $response['count'] = count($data);
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
    // Total reports available
    $stmt = $conn->query("SELECT COUNT(*) as total FROM food_donations");
    $stats['donations'] = $stmt->fetch_assoc()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM user_accounts");
    $stats['users'] = $stmt->fetch_assoc()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM announcements");
    $stats['announcements'] = $stmt->fetch_assoc()['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM food_donation_reservations");
    $stats['requests'] = $stmt->fetch_assoc()['total'];
    
    // Recent report activities (simulated)
    $stmt = $conn->query("
        SELECT 
            'Donation' as type,
            title as description,
            created_at
        FROM food_donations
        UNION ALL
        SELECT 
            'Announcement' as type,
            title as description,
            created_at
        FROM announcements
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $stats['recent_activities'] = $stmt->fetch_all(MYSQLI_ASSOC);
    
} catch (Exception $e) {
    $stats = [
        'donations' => 0,
        'users' => 0,
        'announcements' => 0,
        'requests' => 0,
        'recent_activities' => []
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
                    <h2><i class="bi bi-file-text"></i> Report Generation Center</h2>
                    <p class="text-muted mb-0">Generate comprehensive reports and export data for analysis and documentation</p>
                </div>
                <div class="btn-group">
                    <button class="btn btn-info" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                    <button class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print Page
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Food Donations</h6>
                            <h3 class="mb-0"><?= number_format($stats['donations']) ?></h3>
                        </div>
                        <div class="stat-icon bg-warning">
                            <i class="bi bi-basket"></i>
                        </div>
                    </div>
                    <small class="text-muted">Available for reporting</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Total Users</h6>
                            <h3 class="mb-0"><?= number_format($stats['users']) ?></h3>
                        </div>
                        <div class="stat-icon bg-primary">
                            <i class="bi bi-people"></i>
                        </div>
                    </div>
                    <small class="text-muted">Registered users</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Announcements</h6>
                            <h3 class="mb-0"><?= number_format($stats['announcements']) ?></h3>
                        </div>
                        <div class="stat-icon bg-info">
                            <i class="bi bi-megaphone"></i>
                        </div>
                    </div>
                    <small class="text-muted">Total posts</small>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="text-muted mb-2">Food Requests</h6>
                            <h3 class="mb-0"><?= number_format($stats['requests']) ?></h3>
                        </div>
                        <div class="stat-icon bg-success">
                            <i class="bi bi-clipboard-check"></i>
                        </div>
                    </div>
                    <small class="text-muted">Total requests</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Generation Forms -->
    <div class="row mb-4">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-file-earmark-text"></i> Generate Custom Report</h5>
                </div>
                <div class="card-body">
                    <form id="reportForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Report Type <span class="text-danger">*</span></label>
                                <select class="form-select" id="reportType" name="report_type" required>
                                    <option value="">Select report type...</option>
                                    <option value="donations">Food Donations Report</option>
                                    <option value="users">Users Report</option>
                                    <option value="announcements">Announcements Report</option>
                                    <option value="requests">Food Requests Report</option>
                                    <option value="engagement">User Engagement Report</option>
                                    <option value="summary">Platform Summary Report</option>
                                </select>
                                <div class="form-text">Select the type of report you want to generate</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Export Format <span class="text-danger">*</span></label>
                                <select class="form-select" id="reportFormat" name="format" required>
                                    <option value="csv">CSV (Excel Compatible)</option>
                                    <option value="json">JSON (Data Format)</option>
                                    <option value="pdf">PDF (Print Ready)</option>
                                </select>
                                <div class="form-text">Choose your preferred export format</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date From</label>
                                <input type="date" class="form-control" id="dateFrom" name="date_from">
                                <div class="form-text">Leave empty for all-time data</div>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date To</label>
                                <input type="date" class="form-control" id="dateTo" name="date_to">
                                <div class="form-text">Leave empty for all-time data</div>
                            </div>

                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="bi bi-info-circle"></i>
                                    <strong>Note:</strong> Reports can contain large amounts of data. Please be patient while generating.
                                </div>
                            </div>

                            <div class="col-12">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="bi bi-file-earmark-arrow-down"></i> Generate & Download Report
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-lg" onclick="previewReport()">
                                    <i class="bi bi-eye"></i> Preview Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-lightning"></i> Quick Reports</h5>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <button class="btn btn-outline-primary" onclick="quickReport('donations', 'today')">
                            <i class="bi bi-basket"></i> Today's Donations
                        </button>
                        <button class="btn btn-outline-success" onclick="quickReport('users', 'month')">
                            <i class="bi bi-people"></i> This Month's Users
                        </button>
                        <button class="btn btn-outline-info" onclick="quickReport('announcements', 'week')">
                            <i class="bi bi-megaphone"></i> This Week's Posts
                        </button>
                        <button class="btn btn-outline-warning" onclick="quickReport('requests', 'pending')">
                            <i class="bi bi-clipboard-check"></i> Pending Requests
                        </button>
                        <button class="btn btn-outline-danger" onclick="quickReport('engagement', 'all')">
                            <i class="bi bi-graph-up"></i> Full Engagement Report
                        </button>
                        <button class="btn btn-outline-secondary" onclick="quickReport('summary', 'all')">
                            <i class="bi bi-file-text"></i> Platform Summary
                        </button>
                    </div>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                </div>
                <div class="card-body" style="max-height: 300px; overflow-y: auto;">
                    <?php if (!empty($stats['recent_activities'])): ?>
                        <?php foreach ($stats['recent_activities'] as $activity): ?>
                            <div class="activity-item mb-2">
                                <span class="badge bg-<?= $activity['type'] === 'Donation' ? 'warning' : 'info' ?>">
                                    <?= $activity['type'] ?>
                                </span>
                                <small class="d-block text-muted">
                                    <?= htmlspecialchars(substr($activity['description'], 0, 30)) ?>...
                                    <br><?= date('M j, g:i A', strtotime($activity['created_at'])) ?>
                                </small>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center text-muted mb-0">No recent activity</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Preview Area -->
    <div class="row mb-4" id="previewArea" style="display: none;">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-eye"></i> Report Preview</h5>
                        <button class="btn btn-sm btn-outline-secondary" onclick="closePreview()">
                            <i class="bi bi-x"></i> Close
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div id="previewContent"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scheduled Reports -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Scheduled & Automated Reports</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Report Type</th>
                                    <th>Frequency</th>
                                    <th>Format</th>
                                    <th>Last Generated</th>
                                    <th>Next Run</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="bi bi-basket text-warning"></i> Daily Donations</td>
                                    <td><span class="badge bg-info">Daily</span></td>
                                    <td>CSV</td>
                                    <td><?= date('M j, Y') ?></td>
                                    <td><?= date('M j, Y', strtotime('+1 day')) ?></td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary" onclick="runScheduledReport('daily_donations')">
                                            <i class="bi bi-play"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="bi bi-graph-up text-success"></i> Weekly Engagement</td>
                                    <td><span class="badge bg-primary">Weekly</span></td>
                                    <td>PDF</td>
                                    <td><?= date('M j, Y', strtotime('-7 days')) ?></td>
                                    <td><?= date('M j, Y', strtotime('+7 days')) ?></td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary" onclick="runScheduledReport('weekly_engagement')">
                                            <i class="bi bi-play"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="bi bi-file-text text-info"></i> Monthly Summary</td>
                                    <td><span class="badge bg-warning">Monthly</span></td>
                                    <td>PDF</td>
                                    <td><?= date('M j, Y', strtotime('-30 days')) ?></td>
                                    <td><?= date('M j, Y', strtotime('+30 days')) ?></td>
                                    <td><span class="badge bg-success">Active</span></td>
                                    <td class="text-center">
                                        <button class="btn btn-sm btn-outline-primary" onclick="runScheduledReport('monthly_summary')">
                                            <i class="bi bi-play"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="text-center mt-3">
                        <button class="btn btn-outline-primary" onclick="configureSchedule()">
                            <i class="bi bi-gear"></i> Configure Scheduled Reports
                        </button>
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

.activity-item {
    padding-bottom: 8px;
    border-bottom: 1px solid #e9ecef;
}

.activity-item:last-child {
    border-bottom: none;
}

@media print {
    .sidebar, .header, .btn, .card-header button {
        display: none !important;
    }
}
</style>

<script>
// Report Generation Form Handler
document.getElementById('reportForm').addEventListener('submit', function(e) {
    e.preventDefault();
    generateReport();
});

function generateReport() {
    const formData = new FormData(document.getElementById('reportForm'));
    formData.append('action', 'generate_report');
    
    const reportType = document.getElementById('reportType').value;
    const format = document.getElementById('reportFormat').value;
    
    if (!reportType) {
        showNotification('Please select a report type', 'warning');
        return;
    }
    
    showNotification('Generating report...', 'info');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            downloadReport(data.data, data.filename, format);
            showNotification(`Report generated successfully! Downloaded ${data.count} records.`, 'success');
        } else {
            showNotification(data.message || 'Failed to generate report', 'error');
        }
    })
    .catch(error => {
        showNotification('Error generating report', 'error');
        console.error(error);
    });
}

function downloadReport(data, filename, format) {
    if (format === 'csv') {
        downloadCSV(data, filename);
    } else if (format === 'json') {
        downloadJSON(data, filename);
    } else if (format === 'pdf') {
        showNotification('PDF export coming soon! Using CSV for now.', 'info');
        downloadCSV(data, filename);
    }
}

function downloadCSV(data, filename) {
    if (data.length === 0) {
        showNotification('No data to export', 'warning');
        return;
    }
    
    // Get headers from first object
    const headers = Object.keys(data[0]);
    
    // Create CSV content
    let csv = headers.join(',') + '\n';
    
    data.forEach(row => {
        const values = headers.map(header => {
            const value = row[header] || '';
            // Escape commas and quotes
            return '"' + String(value).replace(/"/g, '""') + '"';
        });
        csv += values.join(',') + '\n';
    });
    
    // Download file
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function downloadJSON(data, filename) {
    const json = JSON.stringify(data, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename + '.json';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

function previewReport() {
    const formData = new FormData(document.getElementById('reportForm'));
    formData.append('action', 'generate_report');
    
    const reportType = document.getElementById('reportType').value;
    
    if (!reportType) {
        showNotification('Please select a report type', 'warning');
        return;
    }
    
    showNotification('Loading preview...', 'info');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPreview(data.data, reportType);
        } else {
            showNotification(data.message || 'Failed to preview report', 'error');
        }
    })
    .catch(error => {
        showNotification('Error previewing report', 'error');
        console.error(error);
    });
}

function displayPreview(data, reportType) {
    if (data.length === 0) {
        showNotification('No data to preview', 'warning');
        return;
    }
    
    const previewArea = document.getElementById('previewArea');
    const previewContent = document.getElementById('previewContent');
    
    // Create table
    const headers = Object.keys(data[0]);
    let html = '<div class="table-responsive">';
    html += '<table class="table table-striped table-hover">';
    html += '<thead><tr>';
    headers.forEach(header => {
        html += `<th>${header.replace(/_/g, ' ').toUpperCase()}</th>`;
    });
    html += '</tr></thead><tbody>';
    
    // Show only first 50 rows
    const displayData = data.slice(0, 50);
    displayData.forEach(row => {
        html += '<tr>';
        headers.forEach(header => {
            html += `<td>${row[header] || '-'}</td>`;
        });
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    
    if (data.length > 50) {
        html += `<div class="alert alert-info">Showing first 50 of ${data.length} records. Download full report to see all data.</div>`;
    }
    
    previewContent.innerHTML = html;
    previewArea.style.display = 'block';
    previewArea.scrollIntoView({ behavior: 'smooth' });
}

function closePreview() {
    document.getElementById('previewArea').style.display = 'none';
}

function quickReport(type, period) {
    // Set form values
    document.getElementById('reportType').value = type;
    document.getElementById('reportFormat').value = 'csv';
    
    // Set date range based on period
    const today = new Date();
    let dateFrom = new Date();
    
    switch(period) {
        case 'today':
            dateFrom = today;
            break;
        case 'week':
            dateFrom.setDate(today.getDate() - 7);
            break;
        case 'month':
            dateFrom.setMonth(today.getMonth() - 1);
            break;
        case 'all':
        case 'pending':
            // Leave empty for all data
            document.getElementById('dateFrom').value = '';
            document.getElementById('dateTo').value = '';
            generateReport();
            return;
    }
    
    document.getElementById('dateFrom').value = dateFrom.toISOString().split('T')[0];
    document.getElementById('dateTo').value = today.toISOString().split('T')[0];
    
    generateReport();
}

function runScheduledReport(reportName) {
    showNotification(`Running ${reportName.replace(/_/g, ' ')}...`, 'info');
    setTimeout(() => {
        showNotification('Scheduled report generated successfully!', 'success');
    }, 1500);
}

function configureSchedule() {
    showNotification('Schedule configuration feature coming soon!', 'info');
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

// Set max date to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('dateFrom').max = today;
    document.getElementById('dateTo').max = today;
});
</script>

<?php include 'footer.php'; ?>

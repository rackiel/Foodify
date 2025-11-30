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

$user_id = $_SESSION['user_id'];
$stats = [];

// Initialize defaults
$stats = [
    'users' => ['total' => 0, 'residents' => 0, 'officers' => 0, 'admins' => 0, 'pending' => 0, 'new_today' => 0, 'new_week' => 0],
    'donations' => ['total' => 0, 'pending' => 0, 'approved' => 0, 'available' => 0, 'claimed' => 0, 'today' => 0],
    'announcements' => ['total' => 0, 'published' => 0, 'today' => 0, 'total_engagement' => 0],
    'requests' => ['total' => 0, 'pending' => 0, 'completed' => 0, 'today' => 0],
    'feedback' => ['total' => 0, 'pending' => 0, 'avg_rating' => 0],
    'reports' => ['total' => 0, 'pending' => 0, 'critical' => 0]
];

try {
    // User Statistics
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN role = 'resident' THEN 1 END) as residents,
            COUNT(CASE WHEN role = 'team officer' THEN 1 END) as officers,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admins,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_today,
            COUNT(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN 1 END) as new_week
        FROM user_accounts
    ");
    if ($result) $stats['users'] = $result->fetch_assoc();
    
    // Donations Statistics
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'available' THEN 1 END) as available,
            COUNT(CASE WHEN status = 'claimed' THEN 1 END) as claimed,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today
        FROM food_donations
    ");
    if ($result) {
        $donation_data = $result->fetch_assoc();
        $stats['donations']['total'] = $donation_data['total'];
        $stats['donations']['available'] = $donation_data['available'];
        $stats['donations']['claimed'] = $donation_data['claimed'];
        $stats['donations']['today'] = $donation_data['today'];
    }
    
    // Check for approval_status column
    $check_col = $conn->query("SHOW COLUMNS FROM food_donations LIKE 'approval_status'");
    if ($check_col && $check_col->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                COUNT(CASE WHEN approval_status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN approval_status = 'approved' THEN 1 END) as approved
            FROM food_donations
        ");
        if ($result) {
            $approval_data = $result->fetch_assoc();
            $stats['donations']['pending'] = $approval_data['pending'];
            $stats['donations']['approved'] = $approval_data['approved'];
        }
    }
    
    // Announcements Statistics
    $check_table = $conn->query("SHOW TABLES LIKE 'announcements'");
    if ($check_table && $check_table->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today,
                COALESCE(SUM(likes_count + comments_count + shares_count), 0) as total_engagement
            FROM announcements
        ");
        if ($result) $stats['announcements'] = $result->fetch_assoc();
    }
    
    // Food Requests
    $check_table = $conn->query("SHOW TABLES LIKE 'food_donation_reservations'");
    if ($check_table && $check_table->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
                COUNT(CASE WHEN DATE(reserved_at) = CURDATE() THEN 1 END) as today
            FROM food_donation_reservations
        ");
        if ($result) $stats['requests'] = $result->fetch_assoc();
    }
    
    // Community Feedback
    $check_table = $conn->query("SHOW TABLES LIKE 'community_feedback'");
    if ($check_table && $check_table->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'new' THEN 1 END) as pending,
                AVG(rating) as avg_rating
            FROM community_feedback
        ");
        if ($result) $stats['feedback'] = $result->fetch_assoc();
    }
    
    // User Reports
    $check_table = $conn->query("SHOW TABLES LIKE 'user_reports'");
    if ($check_table && $check_table->num_rows > 0) {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
                COUNT(CASE WHEN priority = 'critical' THEN 1 END) as critical
            FROM user_reports
        ");
        if ($result) $stats['reports'] = $result->fetch_assoc();
    }
    
    // Recent users
    $result = $conn->query("
        SELECT user_id, full_name, email, role, status, profile_img, created_at
        FROM user_accounts
        ORDER BY created_at DESC
        LIMIT 10
    ");
    $recent_users = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    
    // Recent donations
    $result = $conn->query("
        SELECT fd.*, ua.full_name, ua.profile_img
        FROM food_donations fd
        JOIN user_accounts ua ON fd.user_id = ua.user_id
        ORDER BY fd.created_at DESC
        LIMIT 10
    ");
    $recent_donations = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    
    // Activity timeline
    $activity_items = [];
    
    // Users activity
    $result = $conn->query("
        SELECT 'user' as type, user_id as id, full_name as content, created_at, status
        FROM user_accounts
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY created_at DESC
        LIMIT 5
    ");
    if ($result) $activity_items = array_merge($activity_items, $result->fetch_all(MYSQLI_ASSOC));
    
    // Donations activity
    $result = $conn->query("
        SELECT 'donation' as type, fd.id, fd.title as content, fd.created_at, fd.status
        FROM food_donations fd
        WHERE fd.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY fd.created_at DESC
        LIMIT 5
    ");
    if ($result) $activity_items = array_merge($activity_items, $result->fetch_all(MYSQLI_ASSOC));
    
    // Sort by created_at
    usort($activity_items, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    $recent_activity = array_slice($activity_items, 0, 10);
    
    // Platform trends (last 7 days)
    $result = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as users
        FROM user_accounts
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $user_trends = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    
    $result = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as donations
        FROM food_donations
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date
    ");
    $donation_trends = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    
    // User Logs - Admin can see all logs
    $check_logs_table = $conn->query("SHOW TABLES LIKE 'user_logs'");
    if ($check_logs_table && $check_logs_table->num_rows > 0) {
        $result = $conn->query("
            SELECT ul.*, ua.full_name, ua.email, ua.role
            FROM user_logs ul
            JOIN user_accounts ua ON ul.user_id = ua.user_id
            ORDER BY ul.created_at DESC
            LIMIT 50
        ");
        $user_logs = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    } else {
        $user_logs = [];
    }
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $user_logs = [];
}

include 'header.php'; 
?>

<body>
  <?php include 'topbar.php'; ?>
  <?php include 'sidebar.php'; ?>

  <main id="main" class="main">
    <div class="pagetitle">
      <h1><i class="bi bi-speedometer2"></i> Admin Dashboard</h1>
      <nav>
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Home</a></li>
          <li class="breadcrumb-item active">Dashboard</li>
        </ol>
      </nav>
    </div>

    <!-- Banner Section -->
    <section class="section banner-section">
      <div class="row">
        <div class="col-12">
          <div class="card m-0 p-0" style="border-radius: 10px; box-shadow: none;">
            <div class="card-body text-center p-0">
              <img src="../uploads/banners/admin_banner.png" alt="Admin Banner" class="img-fluid w-100" style="width: 100%; max-width: 100%; height: auto; object-fit: cover; display: block; margin: 0; border-radius: 0;">
            </div>
          </div>
        </div>
      </div>
    </section>

    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show">
        <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <section class="section dashboard">
      <div class="row">
        <!-- Left side columns -->
        <div class="col-lg-8">
          <div class="row">

            <!-- Total Users Card -->
            <div class="col-xxl-4 col-md-6">
              <div class="card info-card sales-card">
                <div class="card-body">
                  <h5 class="card-title">Total Users</h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                      <i class="bi bi-people"></i>
                    </div>
                    <div class="ps-3">
                      <h6><?= number_format($stats['users']['total']) ?></h6>
                      <span class="text-success small pt-1 fw-bold"><?= $stats['users']['new_week'] ?></span> 
                      <span class="text-muted small pt-2 ps-1">new this week</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Food Donations Card -->
            <div class="col-xxl-4 col-md-6">
              <div class="card info-card revenue-card">
                <div class="card-body">
                  <h5 class="card-title">Food Donations</h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                      <i class="bi bi-basket"></i>
                    </div>
                    <div class="ps-3">
                      <h6><?= number_format($stats['donations']['total']) ?></h6>
                      <span class="text-primary small pt-1 fw-bold"><?= $stats['donations']['available'] ?></span> 
                      <span class="text-muted small pt-2 ps-1">available</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Pending Approvals Card -->
            <div class="col-xxl-4 col-xl-12">
              <div class="card info-card customers-card">
                <div class="card-body">
                  <h5 class="card-title">Pending Approvals</h5>
                  <div class="d-flex align-items-center">
                    <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                      <i class="bi bi-clock"></i>
                    </div>
                    <div class="ps-3">
                      <h6><?= number_format($stats['users']['pending'] + $stats['donations']['pending']) ?></h6>
                      <span class="text-warning small pt-1 fw-bold"><?= $stats['users']['pending'] ?> users</span> 
                      <span class="text-muted small pt-2 ps-1">+ <?= $stats['donations']['pending'] ?> donations</span>
                    </div>
                  </div>
                </div>
                </div>
              </div>

            <!-- Platform Activity Chart -->
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title">Platform Activity <span>| Last 7 Days</span></h5>
                  <canvas id="activityChart" style="max-height: 350px;"></canvas>
                </div>
              </div>
            </div>

            <!-- Recent Users -->
            <div class="col-12">
              <div class="card recent-sales overflow-auto">
                <div class="card-body">
                  <h5 class="card-title">Recent User Registrations <span>| Latest</span></h5>
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>User</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Registered</th>
                        <th>Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($recent_users)): ?>
                        <?php foreach ($recent_users as $user): ?>
                          <tr>
                            <td>
                              <div class="d-flex align-items-center">
                                <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                     class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                <?= htmlspecialchars($user['full_name']) ?>
                              </div>
                            </td>
                            <td><?= htmlspecialchars($user['email']) ?></td>
                            <td>
                              <span class="badge bg-<?= 
                                $user['role'] === 'admin' ? 'danger' :
                                ($user['role'] === 'team officer' ? 'success' : 'primary')
                              ?>"><?= ucfirst($user['role']) ?></span>
                            </td>
                            <td>
                              <span class="badge bg-<?= 
                                $user['status'] === 'approved' ? 'success' : 'warning'
                              ?>"><?= ucfirst($user['status']) ?></span>
                            </td>
                            <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                            <td>
                              <a href="users-profile.php?id=<?= $user['user_id'] ?>" class="btn btn-sm btn-primary">
                                <i class="bi bi-eye"></i>
                              </a>
                            </td>
                      </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted">No recent users</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- Recent Donations -->
            <div class="col-12">
              <div class="card top-selling overflow-auto">
                <div class="card-body">
                  <h5 class="card-title">Recent Food Donations <span>| Latest</span></h5>
                  <table class="table table-hover">
                    <thead>
                      <tr>
                        <th>Donation</th>
                        <th>Donor</th>
                        <th>Type</th>
                        <th>Status</th>
                        <th>Views</th>
                        <th>Posted</th>
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
                                         class="rounded me-2" width="40" height="40" 
                                         style="object-fit: cover;" alt="Food">
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
                                  <strong><?= htmlspecialchars($donation['title']) ?></strong>
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
                            <td><?= ucfirst($donation['food_type']) ?></td>
                            <td>
                              <span class="badge bg-<?= 
                                $donation['status'] === 'available' ? 'success' :
                                ($donation['status'] === 'claimed' ? 'info' : 'secondary')
                              ?>"><?= ucfirst($donation['status']) ?></span>
                            </td>
                            <td><?= number_format($donation['views_count'] ?? 0) ?></td>
                            <td><?= date('M j, Y', strtotime($donation['created_at'])) ?></td>
                      </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr><td colspan="6" class="text-center text-muted">No donations yet</td></tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <!-- User Logs -->
            <div class="col-12">
              <div class="card">
                <div class="card-body">
                  <h5 class="card-title">User Activity Logs <span>| All Users</span></h5>
                  <div class="table-responsive">
                    <table class="table table-hover">
                      <thead>
                        <tr>
                          <th>Time</th>
                          <th>User</th>
                          <th>Action</th>
                          <th>Module</th>
                          <th>Description</th>
                          <th>IP Address</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php if (!empty($user_logs)): ?>
                          <?php foreach ($user_logs as $log): ?>
                            <tr>
                              <td>
                                <small><?= date('M d, Y', strtotime($log['created_at'])) ?></small>
                                <br><small class="text-muted"><?= date('g:i A', strtotime($log['created_at'])) ?></small>
                              </td>
                              <td>
                                <div class="d-flex align-items-center">
                                  <span class="badge bg-<?= 
                                    $log['role'] === 'admin' ? 'danger' :
                                    ($log['role'] === 'team officer' ? 'success' : 'primary')
                                  ?> me-2"><?= ucfirst($log['role']) ?></span>
                                  <div>
                                    <strong><?= htmlspecialchars($log['full_name']) ?></strong>
                                    <br><small class="text-muted"><?= htmlspecialchars($log['email']) ?></small>
                                  </div>
                                </div>
                              </td>
                              <td>
                                <span class="badge bg-info"><?= htmlspecialchars($log['action_type']) ?></span>
                              </td>
                              <td><?= htmlspecialchars($log['module']) ?></td>
                              <td>
                                <small><?= htmlspecialchars(substr($log['action_description'], 0, 60)) ?><?= strlen($log['action_description']) > 60 ? '...' : '' ?></small>
                              </td>
                              <td><small class="text-muted"><?= htmlspecialchars($log['ip_address'] ?? 'N/A') ?></small></td>
                            </tr>
                          <?php endforeach; ?>
                        <?php else: ?>
                          <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                              <i class="bi bi-info-circle"></i> No user logs available yet
                            </td>
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

        <!-- Right side columns -->
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
                        $time_label = floor($time_ago / 3600) . ' hr';
                    }
                    
                    $badge_class = $activity['type'] === 'user' ? 'text-success' : 'text-primary';
                    $message = $activity['type'] === 'user' ? 'New user registered: ' : 'New donation: ';
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
                  <p class="text-center text-muted">No recent activity</p>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Platform Stats -->
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Platform Overview</h5>
              <div class="list-group list-group-flush">
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <span><i class="bi bi-megaphone text-info"></i> Announcements</span>
                  <span class="badge bg-info"><?= number_format($stats['announcements']['published']) ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <span><i class="bi bi-clipboard-check text-success"></i> Food Requests</span>
                  <span class="badge bg-success"><?= number_format($stats['requests']['total']) ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <span><i class="bi bi-chat-dots text-primary"></i> Feedback</span>
                  <span class="badge bg-primary"><?= number_format($stats['feedback']['total']) ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <span><i class="bi bi-flag text-warning"></i> Reports</span>
                  <span class="badge bg-warning"><?= number_format($stats['reports']['total']) ?></span>
                </div>
                <div class="list-group-item d-flex justify-content-between align-items-center">
                  <span><i class="bi bi-heart text-danger"></i> Engagement</span>
                  <span class="badge bg-danger"><?= number_format($stats['announcements']['total_engagement']) ?></span>
                </div>
              </div>
            </div>
            </div>

          <!-- Quick Actions -->
          <div class="card">
            <div class="card-body">
              <h5 class="card-title">Quick Actions</h5>
              <div class="d-grid gap-2">
                <?php if ($stats['users']['pending'] > 0): ?>
                  <a href="user-approvals.php" class="btn btn-warning">
                    <i class="bi bi-person-check"></i> Approve Users (<?= $stats['users']['pending'] ?>)
                  </a>
                <?php endif; ?>
                <?php if ($stats['donations']['pending'] > 0): ?>
                  <a href="admin-food-approvals.php" class="btn btn-info">
                    <i class="bi bi-basket"></i> Approve Donations (<?= $stats['donations']['pending'] ?>)
                  </a>
                <?php endif; ?>
                <a href="users.php" class="btn btn-primary">
                  <i class="bi bi-people"></i> Manage Users
                </a>
                <?php if ($stats['reports']['pending'] > 0): ?>
                  <a href="../teamofficer/user-reports.php" class="btn btn-danger">
                    <i class="bi bi-flag"></i> Review Reports (<?= $stats['reports']['pending'] ?>)
                  </a>
                <?php endif; ?>
              </div>
            </div>
            </div>

          <!-- System Alerts -->
          <?php 
          $alerts = [];
          if ($stats['users']['pending'] > 5) {
              $alerts[] = ['type' => 'warning', 'message' => $stats['users']['pending'] . ' users awaiting approval'];
          }
          if ($stats['reports']['critical'] > 0) {
              $alerts[] = ['type' => 'danger', 'message' => $stats['reports']['critical'] . ' critical reports need attention'];
          }
          if ($stats['feedback']['avg_rating'] < 3 && $stats['feedback']['total'] > 0) {
              $alerts[] = ['type' => 'warning', 'message' => 'Platform rating is below average (' . number_format($stats['feedback']['avg_rating'], 1) . '/5)'];
          }
          if (!empty($alerts)): 
          ?>
          <div class="card">
            <div class="card-body">
              <h5 class="card-title text-warning"><i class="bi bi-exclamation-triangle"></i> System Alerts</h5>
              <?php foreach ($alerts as $alert): ?>
                <div class="alert alert-<?= $alert['type'] ?> alert-dismissible fade show">
                  <?= $alert['message'] ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>
    </section>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
  <script>
  // Activity Chart
  const ctx = document.getElementById('activityChart');
  if (ctx) {
      // Prepare data for last 7 days
      const dates = [];
      const userCounts = [];
      const donationCounts = [];
      
      for (let i = 6; i >= 0; i--) {
          const date = new Date();
          date.setDate(date.getDate() - i);
          const dateStr = date.toISOString().split('T')[0];
          dates.push(date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
          
          // Find matching data
          const userTrend = <?= json_encode($user_trends) ?>.find(t => t.date === dateStr);
          const donationTrend = <?= json_encode($donation_trends) ?>.find(t => t.date === dateStr);
          
          userCounts.push(userTrend ? parseInt(userTrend.users) : 0);
          donationCounts.push(donationTrend ? parseInt(donationTrend.donations) : 0);
      }
      
      new Chart(ctx, {
          type: 'line',
          data: {
              labels: dates,
              datasets: [
                  {
                      label: 'New Users',
                      data: userCounts,
                      borderColor: 'rgb(25, 135, 84)',
                      backgroundColor: 'rgba(25, 135, 84, 0.1)',
                      tension: 0.4
                  },
                  {
                      label: 'New Donations',
                      data: donationCounts,
                      borderColor: 'rgb(13, 110, 253)',
                      backgroundColor: 'rgba(13, 110, 253, 0.1)',
                      tension: 0.4
                  }
              ]
          },
          options: {
              responsive: true,
              maintainAspectRatio: true,
              plugins: {
                  legend: { position: 'top' }
              },
              scales: {
                  y: { 
                      beginAtZero: true,
                      ticks: { stepSize: 1 }
                  }
              }
          }
      });
  }

  // Auto-refresh every 5 minutes
  setInterval(function() {
      if (!document.hidden) {
          window.location.reload();
      }
  }, 300000);
  </script>

<?php include 'footer.php'; ?>
</body>
</html>

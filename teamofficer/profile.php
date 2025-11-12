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

// Get user data
try {
    $stmt = $conn->prepare("
        SELECT * FROM user_accounts WHERE user_id = ?
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $user_data = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get activity statistics
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM announcements WHERE user_id = ? AND status = 'published') as announcements_count,
            (SELECT COUNT(*) FROM food_donations WHERE user_id = ?) as donations_count,
            (SELECT COUNT(*) FROM announcement_likes WHERE user_id = ?) as likes_given,
            (SELECT COUNT(*) FROM announcement_comments WHERE user_id = ?) as comments_made,
            (SELECT COUNT(*) FROM announcement_shares WHERE user_id = ?) as shares_made
    ");
    $stmt->bind_param('iiiii', $user_id, $user_id, $user_id, $user_id, $user_id);
    $stmt->execute();
    $activity_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    // Get recent announcements
    $stmt = $conn->prepare("
        SELECT id, title, type, priority, status, likes_count, comments_count, created_at
        FROM announcements 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $recent_announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get recent food donations
    $stmt = $conn->prepare("
        SELECT id, title, food_type, status, views_count, created_at
        FROM food_donations 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $recent_donations = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    // Get recent comments
    $stmt = $conn->prepare("
        SELECT 
            ac.comment,
            ac.created_at,
            a.title as announcement_title,
            a.id as announcement_id
        FROM announcement_comments ac
        JOIN announcements a ON ac.post_id = a.id
        WHERE ac.user_id = ? AND ac.post_type = 'announcement'
        ORDER BY ac.created_at DESC
        LIMIT 5
    ");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $recent_comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
} catch (Exception $e) {
    $error_message = "Error fetching profile data: " . $e->getMessage();
}

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
<div class="container-fluid py-4">
    <!-- Profile Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card profile-header-card">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3 text-center">
                            <img src="<?= !empty($user_data['profile_img']) ? '../uploads/profile_picture/' . $user_data['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                 class="rounded-circle profile-image" alt="Profile">
                            <div class="mt-3">
                                <a href="settings.php" class="btn btn-sm btn-outline-primary">
                                    <i class="bi bi-pencil"></i> Edit Profile
                                </a>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <h2 class="mb-2"><?= htmlspecialchars($user_data['full_name']) ?></h2>
                            <p class="text-muted mb-2">
                                <i class="bi bi-person-badge"></i> <?= ucfirst($user_data['role'] ?? 'Team Officer') ?>
                            </p>
                            <p class="mb-2">
                                <i class="bi bi-envelope"></i> <?= htmlspecialchars($user_data['email']) ?>
                            </p>
                            <?php if (!empty($user_data['phone_number'])): ?>
                                <p class="mb-2">
                                    <i class="bi bi-phone"></i> <?= htmlspecialchars($user_data['phone_number']) ?>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($user_data['address'])): ?>
                                <p class="mb-2">
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($user_data['address']) ?>
                                </p>
                            <?php endif; ?>
                            <p class="mb-0">
                                <i class="bi bi-calendar"></i> Member since <?= date('F j, Y', strtotime($user_data['created_at'])) ?>
                            </p>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center">
                                <span class="badge bg-<?= $user_data['status'] === 'approved' ? 'success' : 'warning' ?> badge-lg">
                                    <i class="bi bi-check-circle"></i> <?= ucfirst($user_data['status']) ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Activity Statistics -->
    <div class="row mb-4">
        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-megaphone stat-icon text-primary"></i>
                    <h3 class="mb-0"><?= number_format($activity_stats['announcements_count']) ?></h3>
                    <small class="text-muted">Announcements</small>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-basket stat-icon text-warning"></i>
                    <h3 class="mb-0"><?= number_format($activity_stats['donations_count']) ?></h3>
                    <small class="text-muted">Donations</small>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-heart stat-icon text-danger"></i>
                    <h3 class="mb-0"><?= number_format($activity_stats['likes_given']) ?></h3>
                    <small class="text-muted">Likes Given</small>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-chat stat-icon text-success"></i>
                    <h3 class="mb-0"><?= number_format($activity_stats['comments_made']) ?></h3>
                    <small class="text-muted">Comments</small>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-share stat-icon text-info"></i>
                    <h3 class="mb-0"><?= number_format($activity_stats['shares_made']) ?></h3>
                    <small class="text-muted">Shares</small>
                </div>
            </div>
        </div>

        <div class="col-xl-2 col-md-4 col-6 mb-3">
            <div class="card stat-card">
                <div class="card-body text-center">
                    <i class="bi bi-graph-up stat-icon text-secondary"></i>
                    <h3 class="mb-0"><?= number_format(
                        $activity_stats['announcements_count'] + 
                        $activity_stats['donations_count'] + 
                        $activity_stats['likes_given'] + 
                        $activity_stats['comments_made'] + 
                        $activity_stats['shares_made']
                    ) ?></h3>
                    <small class="text-muted">Total Activity</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Content Tabs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-bs-toggle="tab" href="#announcements">
                                <i class="bi bi-megaphone"></i> Announcements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#donations">
                                <i class="bi bi-basket"></i> Food Donations
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#comments">
                                <i class="bi bi-chat"></i> Comments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-bs-toggle="tab" href="#about">
                                <i class="bi bi-info-circle"></i> About
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">
                        
                        <!-- Announcements Tab -->
                        <div class="tab-pane fade show active" id="announcements">
                            <h5 class="mb-3">Recent Announcements</h5>
                            <?php if (!empty($recent_announcements)): ?>
                                <div class="list-group">
                                    <?php foreach ($recent_announcements as $announcement): ?>
                                        <a href="announcements.php" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?= htmlspecialchars($announcement['title']) ?></h6>
                                                <small><?= date('M j, Y', strtotime($announcement['created_at'])) ?></small>
                                            </div>
                                            <div class="mb-1">
                                                <span class="badge bg-<?= 
                                                    $announcement['type'] === 'announcement' ? 'info' : 
                                                    ($announcement['type'] === 'reminder' ? 'primary' : 
                                                    ($announcement['type'] === 'guideline' ? 'warning' : 'danger'))
                                                ?>"><?= ucfirst($announcement['type']) ?></span>
                                                <span class="badge bg-<?= 
                                                    $announcement['priority'] === 'critical' ? 'danger' : 
                                                    ($announcement['priority'] === 'high' ? 'warning' : 'secondary')
                                                ?>"><?= ucfirst($announcement['priority']) ?></span>
                                                <span class="badge bg-<?= 
                                                    $announcement['status'] === 'published' ? 'success' : 'warning'
                                                ?>"><?= ucfirst($announcement['status']) ?></span>
                                            </div>
                                            <small>
                                                <i class="bi bi-heart"></i> <?= $announcement['likes_count'] ?> likes • 
                                                <i class="bi bi-chat"></i> <?= $announcement['comments_count'] ?> comments
                                            </small>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="announcements.php" class="btn btn-outline-primary">
                                        View All Announcements
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-megaphone text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3">No announcements yet</p>
                                    <a href="announcements.php" class="btn btn-primary">
                                        Create Your First Announcement
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Donations Tab -->
                        <div class="tab-pane fade" id="donations">
                            <h5 class="mb-3">Recent Food Donations</h5>
                            <?php if (!empty($recent_donations)): ?>
                                <div class="list-group">
                                    <?php foreach ($recent_donations as $donation): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?= htmlspecialchars($donation['title']) ?></h6>
                                                <small><?= date('M j, Y', strtotime($donation['created_at'])) ?></small>
                                            </div>
                                            <div class="mb-1">
                                                <span class="badge bg-info"><?= ucfirst($donation['food_type']) ?></span>
                                                <span class="badge bg-<?= 
                                                    $donation['status'] === 'available' ? 'success' : 
                                                    ($donation['status'] === 'reserved' ? 'warning' : 
                                                    ($donation['status'] === 'claimed' ? 'info' : 'secondary'))
                                                ?>"><?= ucfirst($donation['status']) ?></span>
                                            </div>
                                            <small>
                                                <i class="bi bi-eye"></i> <?= $donation['views_count'] ?> views
                                            </small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-basket text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3">No food donations yet</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Comments Tab -->
                        <div class="tab-pane fade" id="comments">
                            <h5 class="mb-3">Recent Comments</h5>
                            <?php if (!empty($recent_comments)): ?>
                                <div class="list-group">
                                    <?php foreach ($recent_comments as $comment): ?>
                                        <a href="announcements.php" class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <small class="text-muted">On: <?= htmlspecialchars($comment['announcement_title']) ?></small>
                                                <small><?= date('M j, Y', strtotime($comment['created_at'])) ?></small>
                                            </div>
                                            <p class="mb-0 mt-2"><?= htmlspecialchars($comment['comment']) ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-chat text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-3">No comments yet</p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- About Tab -->
                        <div class="tab-pane fade" id="about">
                            <h5 class="mb-3">Account Information</h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-3 text-muted">Personal Details</h6>
                                            <table class="table table-sm">
                                                <tbody>
                                                    <tr>
                                                        <td><strong>Full Name:</strong></td>
                                                        <td><?= htmlspecialchars($user_data['full_name']) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Username:</strong></td>
                                                        <td><?= htmlspecialchars($user_data['username']) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Email:</strong></td>
                                                        <td><?= htmlspecialchars($user_data['email']) ?></td>
                                                    </tr>
                                                    <?php if (!empty($user_data['phone_number'])): ?>
                                                    <tr>
                                                        <td><strong>Phone:</strong></td>
                                                        <td><?= htmlspecialchars($user_data['phone_number']) ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                    <?php if (!empty($user_data['address'])): ?>
                                                    <tr>
                                                        <td><strong>Address:</strong></td>
                                                        <td><?= htmlspecialchars($user_data['address']) ?></td>
                                                    </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card mb-3">
                                        <div class="card-body">
                                            <h6 class="card-subtitle mb-3 text-muted">Account Details</h6>
                                            <table class="table table-sm">
                                                <tbody>
                                                    <tr>
                                                        <td><strong>User ID:</strong></td>
                                                        <td>#<?= $user_data['user_id'] ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Account Type:</strong></td>
                                                        <td><span class="badge bg-success"><?= ucfirst($user_data['role'] ?? 'Team Officer') ?></span></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Status:</strong></td>
                                                        <td><span class="badge bg-<?= $user_data['status'] === 'approved' ? 'success' : 'warning' ?>"><?= ucfirst($user_data['status']) ?></span></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Member Since:</strong></td>
                                                        <td><?= date('F j, Y', strtotime($user_data['created_at'])) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td><strong>Last Updated:</strong></td>
                                                        <td><?= date('F j, Y', strtotime($user_data['updated_at'] ?? $user_data['created_at'])) ?></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-subtitle mb-3 text-muted">Activity Summary</h6>
                                    <div class="row text-center">
                                        <div class="col-md-3 mb-3">
                                            <div class="activity-summary">
                                                <h4 class="text-primary"><?= $activity_stats['announcements_count'] ?></h4>
                                                <p class="mb-0">Announcements Posted</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="activity-summary">
                                                <h4 class="text-warning"><?= $activity_stats['donations_count'] ?></h4>
                                                <p class="mb-0">Food Donations</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="activity-summary">
                                                <h4 class="text-success"><?= $activity_stats['comments_made'] ?></h4>
                                                <p class="mb-0">Comments Made</p>
                                            </div>
                                        </div>
                                        <div class="col-md-3 mb-3">
                                            <div class="activity-summary">
                                                <h4 class="text-info"><?= $activity_stats['shares_made'] ?></h4>
                                                <p class="mb-0">Posts Shared</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-3">
                                <a href="settings.php" class="btn btn-primary">
                                    <i class="bi bi-gear"></i> Edit Profile Settings
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<style>
.profile-header-card {
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.profile-image {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border: 5px solid #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.badge-lg {
    padding: 10px 20px;
    font-size: 1rem;
}

.stat-card {
    border: none;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.stat-icon {
    font-size: 2rem;
    margin-bottom: 10px;
}

.list-group-item {
    border-left: 3px solid transparent;
    transition: all 0.3s;
}

.list-group-item:hover {
    border-left-color: #0d6efd;
    background-color: #f8f9fa;
}

.activity-summary {
    padding: 15px;
    border-radius: 8px;
    background: #f8f9fa;
}

.nav-tabs .nav-link {
    color: #6c757d;
}

.nav-tabs .nav-link.active {
    color: #0d6efd;
    font-weight: 500;
}

@media print {
    .sidebar, .header, .btn {
        display: none !important;
    }
}
</style>

<script>
// Auto-scroll to hash on page load
document.addEventListener('DOMContentLoaded', function() {
    if (window.location.hash) {
        const tab = document.querySelector(`a[href="${window.location.hash}"]`);
        if (tab) {
            const bsTab = new bootstrap.Tab(tab);
            bsTab.show();
        }
    }
});

// Update URL when tab changes
document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown.bs.tab', function (event) {
        const hash = event.target.getAttribute('href');
        history.pushState(null, null, hash);
    });
});
</script>

<?php include 'footer.php'; ?>


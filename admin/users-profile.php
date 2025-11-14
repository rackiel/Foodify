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

// Use GET parameter if provided, otherwise use session user
$user_id = isset($_GET['id']) ? intval($_GET['id']) : $_SESSION['user_id'];
$user = null;
$activity_stats = [];
$recent_activity = [];

if ($user_id > 0) {
    try {
        // Get user data
        $stmt = $conn->prepare("SELECT * FROM user_accounts WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $user = $result->fetch_assoc();
        }
        $stmt->close();

        if ($user) {
            // Get activity statistics
            $stmt = $conn->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM announcements WHERE user_id = ?) as announcements_count,
                    (SELECT COUNT(*) FROM food_donations WHERE user_id = ?) as donations_count,
                    (SELECT COUNT(*) FROM announcement_likes WHERE user_id = ?) as likes_given,
                    (SELECT COUNT(*) FROM announcement_comments WHERE user_id = ?) as comments_made,
                    (SELECT COUNT(*) FROM announcement_shares WHERE user_id = ?) as shares_made,
                    (SELECT COUNT(*) FROM announcement_saves WHERE user_id = ?) as saves_made
            ");
            $stmt->bind_param('iiiiii', $user_id, $user_id, $user_id, $user_id, $user_id, $user_id);
            $stmt->execute();
            $activity_stats = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            // Get recent announcements
            $stmt = $conn->prepare("
                SELECT id, title, type, status, likes_count, comments_count, created_at
                FROM announcements
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $recent_announcements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Get recent donations
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
                SELECT ac.comment, ac.created_at, a.title as post_title
                FROM announcement_comments ac
                LEFT JOIN announcements a ON ac.post_id = a.id
                WHERE ac.user_id = ?
                ORDER BY ac.created_at DESC
                LIMIT 5
            ");
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $recent_comments = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // Get community feedback
            $check_table = $conn->query("SHOW TABLES LIKE 'community_feedback'");
            if ($check_table && $check_table->num_rows > 0) {
                $stmt = $conn->prepare("
                    SELECT id, subject, rating, status, created_at
                    FROM community_feedback
                    WHERE user_id = ?
                    ORDER BY created_at DESC
                    LIMIT 3
                ");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $user_feedback = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
            } else {
                $user_feedback = [];
            }
        }
    } catch (Exception $e) {
        $error_message = "Error fetching user data: " . $e->getMessage();
    }
}

include 'header.php';
?>

<body>
    <?php include 'topbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1><i class="bi bi-person-circle"></i> User Profile</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item"><a href="users.php">Users</a></li>
                    <li class="breadcrumb-item active">Profile</li>
                </ol>
            </nav>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <section class="section profile">
            <?php if ($user): ?>
                <div class="row">
                    <!-- Profile Header -->
                    <div class="col-12 mb-4">
                        <div class="card">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-3 text-center">
                                        <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>"
                                            alt="Profile" class="rounded-circle"
                                            style="width: 150px; height: 150px; object-fit: cover; border: 5px solid #f8f9fa; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                                        <?php if ($user_id === $_SESSION['user_id']): ?>
                                            <div class="mt-3">
                                                <a href="settings.php" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-pencil"></i> Edit Profile
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="col-md-6">
                                        <h2 class="mb-2"><?= htmlspecialchars($user['full_name']) ?></h2>
                                        <p class="text-muted mb-2">
                                            <i class="bi bi-at"></i> <?= htmlspecialchars($user['username']) ?>
                                        </p>
                                        <p class="mb-2">
                                            <i class="bi bi-envelope"></i> <?= htmlspecialchars($user['email']) ?>
                                        </p>
                                        <?php if (!empty($user['phone_number'])): ?>
                                            <p class="mb-2">
                                                <i class="bi bi-phone"></i> <?= htmlspecialchars($user['phone_number']) ?>
                                            </p>
                                        <?php endif; ?>
                                        <p class="mb-0">
                                            <i class="bi bi-calendar"></i> Member since <?= date('F j, Y', strtotime($user['created_at'])) ?>
                                        </p>
                                    </div>
                                    <div class="col-md-3 text-center">
                                        <span class="badge bg-<?=
                                                                $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'team officer' ? 'success' : 'primary')
                                                                ?> badge-lg mb-2" style="padding: 10px 20px; font-size: 1rem;">
                                            <?= ucfirst($user['role']) ?>
                                        </span>
                                        <br>
                                        <span class="badge bg-<?=
                                                                $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger')
                                                                ?> badge-lg" style="padding: 10px 20px; font-size: 1rem;">
                                            <?= ucfirst($user['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Activity Statistics -->
                    <div class="col-lg-12 mb-4">
                        <div class="row">
                            <div class="col-xl-2 col-md-4 col-6 mb-3">
                                <div class="card stat-card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-megaphone stat-icon text-primary"></i>
                                        <h3 class="mb-0"><?= number_format($activity_stats['announcements_count'] ?? 0) ?></h3>
                                        <small class="text-muted">Announcements</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-3">
                                <div class="card stat-card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-basket stat-icon text-warning"></i>
                                        <h3 class="mb-0"><?= number_format($activity_stats['donations_count'] ?? 0) ?></h3>
                                        <small class="text-muted">Donations</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-3">
                                <div class="card stat-card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-heart stat-icon text-danger"></i>
                                        <h3 class="mb-0"><?= number_format($activity_stats['likes_given'] ?? 0) ?></h3>
                                        <small class="text-muted">Likes</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-3">
                                <div class="card stat-card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-chat stat-icon text-success"></i>
                                        <h3 class="mb-0"><?= number_format($activity_stats['comments_made'] ?? 0) ?></h3>
                                        <small class="text-muted">Comments</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-3">
                                <div class="card stat-card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-share stat-icon text-info"></i>
                                        <h3 class="mb-0"><?= number_format($activity_stats['shares_made'] ?? 0) ?></h3>
                                        <small class="text-muted">Shares</small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-xl-2 col-md-4 col-6 mb-3">
                                <div class="card stat-card text-center">
                                    <div class="card-body">
                                        <i class="bi bi-bookmark stat-icon text-secondary"></i>
                                        <h3 class="mb-0"><?= number_format($activity_stats['saves_made'] ?? 0) ?></h3>
                                        <small class="text-muted">Saves</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tabbed Content -->
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-header">
                                <ul class="nav nav-tabs card-header-tabs" role="tablist">
                                    <li class="nav-item">
                                        <a class="nav-link active" data-bs-toggle="tab" href="#details">
                                            <i class="bi bi-info-circle"></i> Details
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#announcements">
                                            <i class="bi bi-megaphone"></i> Announcements
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#donations">
                                            <i class="bi bi-basket"></i> Donations
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#comments">
                                            <i class="bi bi-chat"></i> Comments
                                        </a>
                                    </li>
                                    <li class="nav-item">
                                        <a class="nav-link" data-bs-toggle="tab" href="#feedback">
                                            <i class="bi bi-star"></i> Feedback
                                        </a>
                                    </li>
                                </ul>
                            </div>
                            <div class="card-body">
                                <div class="tab-content">

                                    <!-- Details Tab -->
                                    <div class="tab-pane fade show active" id="details">
                                        <h5 class="mb-3">Account Information</h5>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <th width="40%">User ID:</th>
                                                        <td><strong>#<?= $user['user_id'] ?></strong></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Full Name:</th>
                                                        <td><?= htmlspecialchars($user['full_name']) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Username:</th>
                                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Email:</th>
                                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Phone:</th>
                                                        <td><?= htmlspecialchars($user['phone_number'] ?? 'N/A') ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                            <div class="col-md-6">
                                                <table class="table table-borderless">
                                                    <tr>
                                                        <th width="40%">Role:</th>
                                                        <td>
                                                            <span class="badge bg-<?=
                                                                                    $user['role'] === 'admin' ? 'danger' : ($user['role'] === 'team officer' ? 'success' : 'primary')
                                                                                    ?>"><?= ucfirst($user['role']) ?></span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Status:</th>
                                                        <td>
                                                            <span class="badge bg-<?=
                                                                                    $user['status'] === 'approved' ? 'success' : ($user['status'] === 'pending' ? 'warning' : 'danger')
                                                                                    ?>"><?= ucfirst($user['status']) ?></span>
                                                        </td>
                                                    </tr>
                                                    <tr>
                                                        <th>Registered:</th>
                                                        <td><?= date('F j, Y g:i A', strtotime($user['created_at'])) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Last Updated:</th>
                                                        <td><?= date('F j, Y g:i A', strtotime($user['updated_at'] ?? $user['created_at'])) ?></td>
                                                    </tr>
                                                    <tr>
                                                        <th>Address:</th>
                                                        <td><?= htmlspecialchars($user['address'] ?? 'N/A') ?></td>
                                                    </tr>
                                                </table>
                                            </div>
                                        </div>

                                        <hr>

                                        <h5 class="mb-3">Activity Summary</h5>
                                        <div class="row text-center">
                                            <div class="col-md-3 mb-3">
                                                <div class="p-3 bg-light rounded">
                                                    <h4 class="text-primary"><?= $activity_stats['announcements_count'] ?? 0 ?></h4>
                                                    <p class="mb-0 text-muted">Announcements</p>
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <div class="p-3 bg-light rounded">
                                                    <h4 class="text-warning"><?= $activity_stats['donations_count'] ?? 0 ?></h4>
                                                    <p class="mb-0 text-muted">Donations</p>
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <div class="p-3 bg-light rounded">
                                                    <h4 class="text-success"><?= $activity_stats['comments_made'] ?? 0 ?></h4>
                                                    <p class="mb-0 text-muted">Comments</p>
                                                </div>
                                            </div>
                                            <div class="col-md-3 mb-3">
                                                <div class="p-3 bg-light rounded">
                                                    <h4 class="text-info"><?=
                                                                            ($activity_stats['likes_given'] ?? 0) +
                                                                                ($activity_stats['shares_made'] ?? 0) +
                                                                                ($activity_stats['saves_made'] ?? 0)
                                                                            ?></h4>
                                                    <p class="mb-0 text-muted">Interactions</p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-end mt-3">
                                            <a href="users.php" class="btn btn-outline-secondary">
                                                <i class="bi bi-arrow-left"></i> Back to Users
                                            </a>
                                        </div>
                                    </div>

                                    <!-- Announcements Tab -->
                                    <div class="tab-pane fade" id="announcements">
                                        <h5 class="mb-3">Recent Announcements</h5>
                                        <?php if (!empty($recent_announcements)): ?>
                                            <div class="list-group">
                                                <?php foreach ($recent_announcements as $announcement): ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <h6 class="mb-1"><?= htmlspecialchars($announcement['title']) ?></h6>
                                                            <small><?= date('M j, Y', strtotime($announcement['created_at'])) ?></small>
                                                        </div>
                                                        <div class="mt-2">
                                                            <span class="badge bg-info"><?= ucfirst($announcement['type']) ?></span>
                                                            <span class="badge bg-<?= $announcement['status'] === 'published' ? 'success' : 'secondary' ?>">
                                                                <?= ucfirst($announcement['status']) ?>
                                                            </span>
                                                        </div>
                                                        <small class="mt-2 d-block">
                                                            <i class="bi bi-heart"></i> <?= $announcement['likes_count'] ?> likes •
                                                            <i class="bi bi-chat"></i> <?= $announcement['comments_count'] ?> comments
                                                        </small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4 text-muted">
                                                <i class="bi bi-megaphone" style="font-size: 3rem;"></i>
                                                <p class="mt-2">No announcements yet</p>
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
                                                        <div class="mt-2">
                                                            <span class="badge bg-info"><?= ucfirst($donation['food_type']) ?></span>
                                                            <span class="badge bg-<?=
                                                                                    $donation['status'] === 'available' ? 'success' : ($donation['status'] === 'claimed' ? 'info' : 'secondary')
                                                                                    ?>"><?= ucfirst($donation['status']) ?></span>
                                                        </div>
                                                        <small class="mt-2 d-block">
                                                            <i class="bi bi-eye"></i> <?= $donation['views_count'] ?> views
                                                        </small>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4 text-muted">
                                                <i class="bi bi-basket" style="font-size: 3rem;"></i>
                                                <p class="mt-2">No food donations yet</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Comments Tab -->
                                    <div class="tab-pane fade" id="comments">
                                        <h5 class="mb-3">Recent Comments</h5>
                                        <?php if (!empty($recent_comments)): ?>
                                            <div class="list-group">
                                                <?php foreach ($recent_comments as $comment): ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <small class="text-muted">
                                                                On: <?= htmlspecialchars($comment['post_title'] ?? 'Post') ?>
                                                            </small>
                                                            <small><?= date('M j, Y', strtotime($comment['created_at'])) ?></small>
                                                        </div>
                                                        <p class="mb-0 mt-2"><?= htmlspecialchars($comment['comment']) ?></p>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4 text-muted">
                                                <i class="bi bi-chat" style="font-size: 3rem;"></i>
                                                <p class="mt-2">No comments yet</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Feedback Tab -->
                                    <div class="tab-pane fade" id="feedback">
                                        <h5 class="mb-3">Community Feedback</h5>
                                        <?php if (!empty($user_feedback)): ?>
                                            <div class="list-group">
                                                <?php foreach ($user_feedback as $feedback): ?>
                                                    <div class="list-group-item">
                                                        <div class="d-flex w-100 justify-content-between">
                                                            <h6 class="mb-1"><?= htmlspecialchars($feedback['subject']) ?></h6>
                                                            <small><?= date('M j, Y', strtotime($feedback['created_at'])) ?></small>
                                                        </div>
                                                        <div class="mt-2 mb-2">
                                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                <i class="bi bi-star-fill <?= $i <= $feedback['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                            <?php endfor; ?>
                                                            <strong class="ms-2"><?= $feedback['rating'] ?>/5</strong>
                                                        </div>
                                                        <span class="badge bg-<?=
                                                                                $feedback['status'] === 'resolved' ? 'success' : ($feedback['status'] === 'responded' ? 'info' : 'warning')
                                                                                ?>"><?= ucfirst($feedback['status']) ?></span>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                            <?php if (count($user_feedback) >= 3): ?>
                                                <div class="text-center mt-3">
                                                    <a href="../teamofficer/community-feedback.php" class="btn btn-outline-primary btn-sm">
                                                        View All Feedback
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <div class="text-center py-4 text-muted">
                                                <i class="bi bi-star" style="font-size: 3rem;"></i>
                                                <p class="mt-2">No feedback submitted yet</p>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <!-- User Not Found -->
                <div class="row">
                    <div class="col-lg-8 mx-auto">
                        <div class="card">
                            <div class="card-body text-center py-5">
                                <i class="bi bi-person-x text-muted" style="font-size: 4rem;"></i>
                                <h4 class="mt-3">User Not Found</h4>
                                <p class="text-muted">The requested user profile does not exist or has been removed.</p>
                                <a href="users.php" class="btn btn-primary">
                                    <i class="bi bi-arrow-left"></i> Back to Users
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <style>
        .stat-card {
            border: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
        }

        .stat-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .nav-tabs .nav-link {
            color: #6c757d;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            font-weight: 500;
        }

        .list-group-item {
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }

        .list-group-item:hover {
            border-left-color: #0d6efd;
            background-color: #f8f9fa;
        }
    </style>

    <?php include 'footer.php'; ?>
</body>

</html>
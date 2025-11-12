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

// Mark that we're including from parent to avoid redundant session/auth checks
define('INCLUDED_FROM_PARENT', true);
include 'update_challenge_progress.php';

// Auto-update user's challenge progress on page load
updateChallengeProgress($conn, $_SESSION['user_id']);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false];
    
    try {
        if ($action === 'join_challenge') {
            $challenge_id = intval($_POST['challenge_id']);
            $user_id = $_SESSION['user_id'];
            
            // Check if already joined
            $stmt = $conn->prepare("
                SELECT participant_id FROM challenge_participants 
                WHERE challenge_id = ? AND user_id = ?
            ");
            $stmt->bind_param('ii', $challenge_id, $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $response['message'] = 'You have already joined this challenge!';
            } else {
                $stmt->close();
                $stmt = $conn->prepare("
                    INSERT INTO challenge_participants (challenge_id, user_id, progress, completed)
                    VALUES (?, ?, 0, FALSE)
                ");
                $stmt->bind_param('ii', $challenge_id, $user_id);
                
                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Successfully joined the challenge!';
                }
            }
            $stmt->close();
            
        } elseif ($action === 'leave_challenge') {
            $challenge_id = intval($_POST['challenge_id']);
            $user_id = $_SESSION['user_id'];
            
            $stmt = $conn->prepare("
                DELETE FROM challenge_participants 
                WHERE challenge_id = ? AND user_id = ? AND completed = FALSE
            ");
            $stmt->bind_param('ii', $challenge_id, $user_id);
            
            if ($stmt->execute()) {
                if ($stmt->affected_rows > 0) {
                    $response['success'] = true;
                    $response['message'] = 'Left the challenge successfully!';
                } else {
                    $response['message'] = 'Cannot leave completed challenges!';
                }
            }
            $stmt->close();
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Get user's statistics
$user_stats = [
    'total_joined' => 0,
    'completed' => 0,
    'in_progress' => 0,
    'total_points' => 0
];

try {
    $user_id = $_SESSION['user_id'];
    
    $result = $conn->query("
        SELECT 
            COUNT(*) as total_joined,
            COUNT(CASE WHEN completed = TRUE THEN 1 END) as completed,
            COUNT(CASE WHEN completed = FALSE THEN 1 END) as in_progress,
            SUM(points_earned) as total_points
        FROM challenge_participants
        WHERE user_id = $user_id
    ");
    
    if ($result && $row = $result->fetch_assoc()) {
        $user_stats = [
            'total_joined' => $row['total_joined'] ?? 0,
            'completed' => $row['completed'] ?? 0,
            'in_progress' => $row['in_progress'] ?? 0,
            'total_points' => $row['total_points'] ?? 0
        ];
    }
    
    // Get active challenges with user participation status
    $active_challenges = [];
    $result = $conn->query("
        SELECT c.*,
               cp.participant_id,
               cp.progress,
               cp.completed as user_completed,
               cp.joined_at,
               COUNT(DISTINCT cp_all.participant_id) as total_participants
        FROM challenges c
        LEFT JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id AND cp.user_id = $user_id
        LEFT JOIN challenge_participants cp_all ON c.challenge_id = cp_all.challenge_id
        WHERE c.status = 'active'
        AND c.end_date >= CURDATE()
        GROUP BY c.challenge_id
        ORDER BY c.start_date DESC, c.created_at DESC
    ");
    
    if ($result) {
        $active_challenges = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get user's joined challenges (active and in progress)
    $my_challenges = [];
    $result = $conn->query("
        SELECT c.*, cp.progress, cp.completed, cp.joined_at, cp.points_earned
        FROM challenges c
        JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id
        WHERE cp.user_id = $user_id
        AND c.status = 'active'
        ORDER BY cp.completed ASC, c.end_date ASC
    ");
    
    if ($result) {
        $my_challenges = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get completed challenges
    $completed_challenges = [];
    $result = $conn->query("
        SELECT c.*, cp.progress, cp.completed_at, cp.points_earned
        FROM challenges c
        JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id
        WHERE cp.user_id = $user_id
        AND cp.completed = TRUE
        ORDER BY cp.completed_at DESC
        LIMIT 10
    ");
    
    if ($result) {
        $completed_challenges = $result->fetch_all(MYSQLI_ASSOC);
    }
    
    // Get leaderboard
    $leaderboard = [];
    $result = $conn->query("
        SELECT ua.user_id, ua.full_name, ua.profile_img,
               COUNT(CASE WHEN cp.completed = TRUE THEN 1 END) as completed_count,
               SUM(cp.points_earned) as total_points
        FROM user_accounts ua
        JOIN challenge_participants cp ON ua.user_id = cp.user_id
        WHERE ua.role = 'resident'
        GROUP BY ua.user_id
        ORDER BY total_points DESC, completed_count DESC
        LIMIT 10
    ");
    
    if ($result) {
        $leaderboard = $result->fetch_all(MYSQLI_ASSOC);
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
        <h1><i class="bi bi-trophy"></i> Challenges & Events</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Challenges</li>
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
        <!-- User Statistics -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Points</h6>
                                <h3 class="mb-0 text-primary"><?= number_format($user_stats['total_points']) ?></h3>
                            </div>
                            <div class="stat-icon bg-primary">
                                <i class="bi bi-star-fill"></i>
                            </div>
                        </div>
                        <small class="text-muted">Earned from challenges</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Challenges Joined</h6>
                                <h3 class="mb-0"><?= number_format($user_stats['total_joined']) ?></h3>
                            </div>
                            <div class="stat-icon bg-info">
                                <i class="bi bi-trophy"></i>
                            </div>
                        </div>
                        <small class="text-muted">All time</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Completed</h6>
                                <h3 class="mb-0 text-success"><?= number_format($user_stats['completed']) ?></h3>
                            </div>
                            <div class="stat-icon bg-success">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                        </div>
                        <small class="text-muted">Successfully finished</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">In Progress</h6>
                                <h3 class="mb-0 text-warning"><?= number_format($user_stats['in_progress']) ?></h3>
                            </div>
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-hourglass-split"></i>
                            </div>
                        </div>
                        <small class="text-muted">Active now</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="nav nav-tabs mb-3" id="challengeTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="available-tab" data-bs-toggle="tab" 
                        data-bs-target="#available" type="button" role="tab">
                    <i class="bi bi-star"></i> Available Challenges
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="my-challenges-tab" data-bs-toggle="tab" 
                        data-bs-target="#my-challenges" type="button" role="tab">
                    <i class="bi bi-trophy"></i> My Challenges (<?= $user_stats['in_progress'] ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="completed-tab" data-bs-toggle="tab" 
                        data-bs-target="#completed" type="button" role="tab">
                    <i class="bi bi-check-circle"></i> Completed (<?= $user_stats['completed'] ?>)
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="leaderboard-tab" data-bs-toggle="tab" 
                        data-bs-target="#leaderboard" type="button" role="tab">
                    <i class="bi bi-bar-chart"></i> Leaderboard
                </button>
            </li>
        </ul>

        <!-- Tab Content -->
        <div class="tab-content" id="challengeTabContent">
            <!-- Available Challenges Tab -->
            <div class="tab-pane fade show active" id="available" role="tabpanel">
                <div class="row">
                    <?php if (!empty($active_challenges)): ?>
                        <?php foreach ($active_challenges as $challenge): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card challenge-card h-100">
                                    <?php if ($challenge['banner_image']): ?>
                                        <img src="../<?= htmlspecialchars($challenge['banner_image']) ?>" 
                                             class="card-img-top" alt="Challenge" style="height: 200px; object-fit: cover;">
                                    <?php else: ?>
                                        <div class="card-img-top bg-gradient-primary d-flex align-items-center justify-content-center" 
                                             style="height: 200px;">
                                            <i class="bi bi-trophy text-white" style="font-size: 4rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h5 class="card-title mb-0"><?= htmlspecialchars($challenge['title']) ?></h5>
                                            <span class="badge bg-primary"><?= $challenge['points'] ?> pts</span>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <span class="badge bg-info me-1"><?= ucfirst($challenge['challenge_type']) ?></span>
                                            <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $challenge['category'])) ?></span>
                                        </div>
                                        
                                        <p class="card-text text-muted"><?= htmlspecialchars($challenge['description']) ?></p>
                                        
                                        <div class="mb-3">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> 
                                                <?= date('M j', strtotime($challenge['start_date'])) ?> - 
                                                <?= date('M j, Y', strtotime($challenge['end_date'])) ?>
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="bi bi-people"></i> <?= $challenge['total_participants'] ?> participants
                                            </small>
                                            <br>
                                            <small class="text-muted">
                                                <i class="bi bi-target"></i> Goal: <?= $challenge['target_value'] ?> 
                                                <?= $challenge['category'] === 'donation' ? 'donations' : 'actions' ?>
                                            </small>
                                        </div>
                                        
                                        <?php if ($challenge['prize_description']): ?>
                                            <div class="alert alert-warning py-2 mb-3">
                                                <small><i class="bi bi-gift"></i> <?= htmlspecialchars($challenge['prize_description']) ?></small>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if ($challenge['participant_id']): ?>
                                            <?php if ($challenge['user_completed']): ?>
                                                <button class="btn btn-success w-100" disabled>
                                                    <i class="bi bi-check-circle"></i> Completed!
                                                </button>
                                            <?php else: ?>
                                                <div class="mb-2">
                                                    <div class="progress mb-2">
                                                        <div class="progress-bar" role="progressbar" 
                                                             style="width: <?= ($challenge['progress'] / $challenge['target_value']) * 100 ?>%">
                                                            <?= $challenge['progress'] ?>/<?= $challenge['target_value'] ?>
                                                        </div>
                                                    </div>
                                                    <small class="text-muted">
                                                        Joined: <?= date('M j, Y', strtotime($challenge['joined_at'])) ?>
                                                    </small>
                                                </div>
                                                <button class="btn btn-outline-danger btn-sm w-100" 
                                                        onclick="leaveChallenge(<?= $challenge['challenge_id'] ?>)">
                                                    <i class="bi bi-x-circle"></i> Leave Challenge
                                                </button>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <button class="btn btn-primary w-100" 
                                                    onclick="joinChallenge(<?= $challenge['challenge_id'] ?>)">
                                                <i class="bi bi-plus-circle"></i> Join Challenge
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="bi bi-info-circle" style="font-size: 3rem;"></i>
                                <p class="mt-3 mb-0">No active challenges available at the moment. Check back soon!</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- My Challenges Tab -->
            <div class="tab-pane fade" id="my-challenges" role="tabpanel">
                <div class="row">
                    <?php if (!empty($my_challenges)): ?>
                        <?php foreach ($my_challenges as $challenge): ?>
                            <?php if (!$challenge['completed']): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card challenge-card-compact">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h5 class="mb-1"><?= htmlspecialchars($challenge['title']) ?></h5>
                                                <div class="mb-2">
                                                    <span class="badge bg-info me-1"><?= ucfirst($challenge['challenge_type']) ?></span>
                                                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $challenge['category'])) ?></span>
                                                    <span class="badge bg-primary"><?= $challenge['points'] ?> pts</span>
                                                </div>
                                            </div>
                                            <?php if ($challenge['banner_image']): ?>
                                                <img src="../<?= htmlspecialchars($challenge['banner_image']) ?>" 
                                                     class="rounded ms-3" width="80" height="80" 
                                                     style="object-fit: cover;" alt="Challenge">
                                            <?php endif; ?>
                                        </div>
                                        
                                        <p class="text-muted mb-3"><?= htmlspecialchars(substr($challenge['description'], 0, 100)) ?>...</p>
                                        
                                        <div class="mb-3">
                                            <div class="d-flex justify-content-between mb-1">
                                                <small class="text-muted">Progress</small>
                                                <small class="text-muted">
                                                    <?= $challenge['progress'] ?>/<?= $challenge['target_value'] ?>
                                                    (<?= round(($challenge['progress'] / $challenge['target_value']) * 100) ?>%)
                                                </small>
                                            </div>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" role="progressbar" 
                                                     style="width: <?= ($challenge['progress'] / $challenge['target_value']) * 100 ?>%">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="d-flex justify-content-between align-items-center">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar"></i> Ends: <?= date('M j, Y', strtotime($challenge['end_date'])) ?>
                                            </small>
                                            <button class="btn btn-outline-danger btn-sm" 
                                                    onclick="leaveChallenge(<?= $challenge['challenge_id'] ?>)">
                                                <i class="bi bi-x-circle"></i> Leave
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="bi bi-trophy" style="font-size: 3rem;"></i>
                                <p class="mt-3 mb-0">You haven't joined any challenges yet. Check out available challenges!</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Completed Tab -->
            <div class="tab-pane fade" id="completed" role="tabpanel">
                <div class="row">
                    <?php if (!empty($completed_challenges)): ?>
                        <?php foreach ($completed_challenges as $challenge): ?>
                            <div class="col-lg-6 mb-4">
                                <div class="card challenge-card-completed">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h5 class="mb-1">
                                                    <i class="bi bi-check-circle-fill text-success"></i>
                                                    <?= htmlspecialchars($challenge['title']) ?>
                                                </h5>
                                                <div class="mb-2">
                                                    <span class="badge bg-info me-1"><?= ucfirst($challenge['challenge_type']) ?></span>
                                                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $challenge['category'])) ?></span>
                                                    <span class="badge bg-success"><?= $challenge['points_earned'] ?> pts earned</span>
                                                </div>
                                            </div>
                                            <?php if ($challenge['banner_image']): ?>
                                                <img src="../<?= htmlspecialchars($challenge['banner_image']) ?>" 
                                                     class="rounded ms-3" width="80" height="80" 
                                                     style="object-fit: cover; opacity: 0.7;" alt="Challenge">
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="alert alert-success py-2 mb-2">
                                            <i class="bi bi-trophy"></i> 
                                            <strong>Completed!</strong> on <?= date('M j, Y', strtotime($challenge['completed_at'])) ?>
                                        </div>
                                        
                                        <small class="text-muted">
                                            <i class="bi bi-target"></i> Achieved: <?= $challenge['progress'] ?>/<?= $challenge['target_value'] ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-info text-center">
                                <i class="bi bi-check-circle" style="font-size: 3rem;"></i>
                                <p class="mt-3 mb-0">No completed challenges yet. Join a challenge and complete it to earn points!</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Leaderboard Tab -->
            <div class="tab-pane fade" id="leaderboard" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-bar-chart"></i> Top Challenge Champions
                        </h5>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Rank</th>
                                        <th>User</th>
                                        <th>Completed</th>
                                        <th>Total Points</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($leaderboard)): ?>
                                        <?php 
                                        $rank = 1;
                                        foreach ($leaderboard as $user): 
                                            $isCurrentUser = $user['user_id'] == $_SESSION['user_id'];
                                        ?>
                                            <tr class="<?= $isCurrentUser ? 'table-primary' : '' ?>">
                                                <td>
                                                    <?php if ($rank <= 3): ?>
                                                        <span class="badge bg-warning">
                                                            <?php if ($rank == 1): ?>
                                                                <i class="bi bi-trophy-fill"></i> #<?= $rank ?>
                                                            <?php elseif ($rank == 2): ?>
                                                                <i class="bi bi-award-fill"></i> #<?= $rank ?>
                                                            <?php else: ?>
                                                                <i class="bi bi-award"></i> #<?= $rank ?>
                                                            <?php endif; ?>
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">#<?= $rank ?></span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                             class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                        <strong>
                                                            <?= htmlspecialchars($user['full_name']) ?>
                                                            <?= $isCurrentUser ? '<span class="badge bg-primary ms-1">You</span>' : '' ?>
                                                        </strong>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-success"><?= $user['completed_count'] ?> challenges</span>
                                                </td>
                                                <td>
                                                    <strong class="text-primary"><?= number_format($user['total_points']) ?></strong> pts
                                                </td>
                                            </tr>
                                        <?php 
                                            $rank++;
                                        endforeach; 
                                        ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                No leaderboard data yet. Be the first to complete a challenge!
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

.challenge-card {
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.challenge-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.challenge-card-compact {
    border-left: 4px solid #0d6efd;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.challenge-card-completed {
    border-left: 4px solid #198754;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    opacity: 0.9;
}

.bg-gradient-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.nav-tabs .nav-link {
    color: #6c757d;
}

.nav-tabs .nav-link.active {
    color: #0d6efd;
    font-weight: 600;
}
</style>

<script>
// Join Challenge
function joinChallenge(challengeId) {
    if (!confirm('Join this challenge?')) return;
    
    const formData = new FormData();
    formData.append('action', 'join_challenge');
    formData.append('challenge_id', challengeId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to join challenge', 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred', 'error');
    });
}

// Leave Challenge
function leaveChallenge(challengeId) {
    if (!confirm('Are you sure you want to leave this challenge? Your progress will be lost.')) return;
    
    const formData = new FormData();
    formData.append('action', 'leave_challenge');
    formData.append('challenge_id', challengeId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showNotification(data.message || 'Failed to leave challenge', 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred', 'error');
    });
}

// Notification System
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
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
</script>

<?php include 'footer.php'; ?>
</body>
</html>

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

// Get statistics
$stats = [
    'total_participants' => 0,
    'active_participants' => 0,
    'completed_challenges' => 0,
    'total_points_earned' => 0,
    'avg_completion_rate' => 0
];

try {
    // Total participants
    $result = $conn->query("
        SELECT COUNT(DISTINCT user_id) as total_participants
        FROM challenge_participants
    ");
    if ($result) {
        $data = $result->fetch_assoc();
        $stats['total_participants'] = $data['total_participants'];
    }

    // Active participants (not completed)
    $result = $conn->query("
        SELECT COUNT(DISTINCT user_id) as active_participants
        FROM challenge_participants
        WHERE completed = FALSE
    ");
    if ($result) {
        $data = $result->fetch_assoc();
        $stats['active_participants'] = $data['active_participants'];
    }

    // Completed challenges
    $result = $conn->query("
        SELECT COUNT(*) as completed_challenges
        FROM challenge_participants
        WHERE completed = TRUE
    ");
    if ($result) {
        $data = $result->fetch_assoc();
        $stats['completed_challenges'] = $data['completed_challenges'];
    }

    // Total points earned
    $result = $conn->query("
        SELECT SUM(points_earned) as total_points_earned
        FROM challenge_participants
    ");
    if ($result) {
        $data = $result->fetch_assoc();
        $stats['total_points_earned'] = $data['total_points_earned'] ?? 0;
    }

    // Average completion rate
    $result = $conn->query("
        SELECT 
            COUNT(CASE WHEN completed = TRUE THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as avg_completion_rate
        FROM challenge_participants
    ");
    if ($result) {
        $data = $result->fetch_assoc();
        $stats['avg_completion_rate'] = round($data['avg_completion_rate'] ?? 0, 1);
    }

    // Get detailed user progress
    $filter_challenge = isset($_GET['challenge']) ? intval($_GET['challenge']) : 0;
    $filter_status = isset($_GET['status']) ? $_GET['status'] : '';

    $query = "
        SELECT 
            ua.user_id,
            ua.full_name,
            ua.profile_img,
            ua.email,
            c.challenge_id,
            c.title as challenge_title,
            c.category,
            c.target_value,
            c.points as challenge_points,
            cp.progress,
            cp.completed,
            cp.points_earned,
            cp.joined_at,
            cp.completed_at,
            CASE 
                WHEN cp.completed = TRUE THEN 'Completed'
                WHEN cp.progress >= c.target_value THEN 'Pending Completion'
                ELSE 'In Progress'
            END as status
        FROM challenge_participants cp
        JOIN user_accounts ua ON cp.user_id = ua.user_id
        JOIN challenges c ON cp.challenge_id = c.challenge_id
        WHERE 1=1
    ";

    if ($filter_challenge > 0) {
        $query .= " AND c.challenge_id = $filter_challenge";
    }

    if ($filter_status === 'completed') {
        $query .= " AND cp.completed = TRUE";
    } elseif ($filter_status === 'in_progress') {
        $query .= " AND cp.completed = FALSE";
    }

    $query .= " ORDER BY cp.completed ASC, cp.progress DESC, cp.joined_at DESC";

    $result = $conn->query($query);
    $user_progress = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    // Get all challenges for filter dropdown
    $result = $conn->query("
        SELECT challenge_id, title, status
        FROM challenges
        ORDER BY created_at DESC
    ");
    $all_challenges = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    // Get top performers
    $result = $conn->query("
        SELECT 
            ua.user_id,
            ua.full_name,
            ua.profile_img,
            COUNT(CASE WHEN cp.completed = TRUE THEN 1 END) as completed_count,
            SUM(cp.points_earned) as total_points
        FROM user_accounts ua
        JOIN challenge_participants cp ON ua.user_id = cp.user_id
        WHERE ua.role = 'resident'
        GROUP BY ua.user_id
        ORDER BY total_points DESC, completed_count DESC
        LIMIT 10
    ");
    $top_performers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

    // Get challenge completion stats
    $result = $conn->query("
        SELECT 
            c.challenge_id,
            c.title,
            c.category,
            COUNT(cp.participant_id) as total_participants,
            COUNT(CASE WHEN cp.completed = TRUE THEN 1 END) as completed_count,
            AVG(cp.progress) as avg_progress
        FROM challenges c
        LEFT JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id
        WHERE c.status = 'active'
        GROUP BY c.challenge_id
        ORDER BY total_participants DESC
        LIMIT 5
    ");
    $challenge_stats = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
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
            <h1><i class="bi bi-bar-chart-line"></i> User Challenges Progress</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item">Community</li>
                    <li class="breadcrumb-item active">User Challenges Progress</li>
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
                                    <h6 class="text-muted mb-2">Total Participants</h6>
                                    <h3 class="mb-0"><?= number_format($stats['total_participants']) ?></h3>
                                </div>
                                <div class="stat-icon bg-primary">
                                    <i class="bi bi-people"></i>
                                </div>
                            </div>
                            <small class="text-muted">Unique users</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Active Participants</h6>
                                    <h3 class="mb-0 text-warning"><?= number_format($stats['active_participants']) ?></h3>
                                </div>
                                <div class="stat-icon bg-warning">
                                    <i class="bi bi-hourglass-split"></i>
                                </div>
                            </div>
                            <small class="text-muted">Currently participating</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Completed</h6>
                                    <h3 class="mb-0 text-success"><?= number_format($stats['completed_challenges']) ?></h3>
                                </div>
                                <div class="stat-icon bg-success">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                            </div>
                            <small class="text-muted">Total completions</small>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-muted mb-2">Total Points</h6>
                                    <h3 class="mb-0 text-info"><?= number_format($stats['total_points_earned']) ?></h3>
                                </div>
                                <div class="stat-icon bg-info">
                                    <i class="bi bi-star-fill"></i>
                                </div>
                            </div>
                            <small class="text-muted"><?= $stats['avg_completion_rate'] ?>% completion rate</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Main Progress Table -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title mb-0">User Progress</h5>
                                <button class="btn btn-success" onclick="exportProgress()">
                                    <i class="bi bi-file-earmark-excel"></i> Export
                                </button>
                            </div>

                            <!-- Filters -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <select class="form-select" id="challengeFilter" onchange="applyFilters()">
                                        <option value="">All Challenges</option>
                                        <?php foreach ($all_challenges as $ch): ?>
                                            <option value="<?= $ch['challenge_id'] ?>" <?= $filter_challenge == $ch['challenge_id'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($ch['title']) ?> (<?= ucfirst($ch['status']) ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <select class="form-select" id="statusFilter" onchange="applyFilters()">
                                        <option value="">All Status</option>
                                        <option value="in_progress" <?= $filter_status === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                                        <option value="completed" <?= $filter_status === 'completed' ? 'selected' : '' ?>>Completed</option>
                                    </select>
                                </div>
                            </div>

                            <!-- Search -->
                            <div class="mb-3">
                                <input type="text" class="form-control" id="searchInput"
                                    placeholder="Search by user name or email..."
                                    onkeyup="searchProgress()">
                            </div>

                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>User</th>
                                            <th>Challenge</th>
                                            <th>Progress</th>
                                            <th>Status</th>
                                            <th>Points</th>
                                            <th>Joined</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($user_progress)): ?>
                                            <?php foreach ($user_progress as $up): ?>
                                                <tr class="progress-row">
                                                    <td>
                                                        <div class="d-flex align-items-center">
                                                            <img src="<?= !empty($up['profile_img']) ? '../uploads/profile_picture/' . $up['profile_img'] : '../uploads/profile_picture/no_image.png' ?>"
                                                                class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                            <div>
                                                                <strong><?= htmlspecialchars($up['full_name']) ?></strong>
                                                                <br><small class="text-muted"><?= htmlspecialchars($up['email']) ?></small>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        <strong><?= htmlspecialchars($up['challenge_title']) ?></strong>
                                                        <br><small class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $up['category'])) ?></small>
                                                    </td>
                                                    <td>
                                                        <div class="progress" style="width: 100px;">
                                                            <div class="progress-bar <?= $up['completed'] ? 'bg-success' : 'bg-primary' ?>"
                                                                role="progressbar"
                                                                style="width: <?= min(($up['progress'] / $up['target_value']) * 100, 100) ?>%">
                                                                <?= $up['progress'] ?>/<?= $up['target_value'] ?>
                                                            </div>
                                                        </div>
                                                        <small class="text-muted"><?= round(min(($up['progress'] / $up['target_value']) * 100, 100)) ?>%</small>
                                                    </td>
                                                    <td>
                                                        <?php if ($up['completed']): ?>
                                                            <span class="badge bg-success">
                                                                <i class="bi bi-check-circle"></i> Completed
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="badge bg-warning">
                                                                <i class="bi bi-hourglass-split"></i> In Progress
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if ($up['completed']): ?>
                                                            <strong class="text-success"><?= $up['points_earned'] ?></strong> pts
                                                        <?php else: ?>
                                                            <span class="text-muted"><?= $up['challenge_points'] ?> pts</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <small><?= date('M j, Y', strtotime($up['joined_at'])) ?></small>
                                                        <?php if ($up['completed']): ?>
                                                            <br><small class="text-success">✓ <?= date('M j', strtotime($up['completed_at'])) ?></small>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="6" class="text-center text-muted py-4">
                                                    <i class="bi bi-info-circle" style="font-size: 3rem;"></i>
                                                    <p class="mt-2">No user progress data found</p>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Sidebar Stats -->
                <div class="col-lg-4">
                    <!-- Top Performers -->
                    <div class="card mb-3">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-trophy"></i> Top Performers
                            </h5>
                            <div class="list-group list-group-flush">
                                <?php if (!empty($top_performers)): ?>
                                    <?php
                                    $rank = 1;
                                    foreach ($top_performers as $user):
                                    ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex align-items-center">
                                                <span class="badge <?= $rank <= 3 ? 'bg-warning' : 'bg-secondary' ?> me-2">
                                                    <?php if ($rank == 1): ?>
                                                        <i class="bi bi-trophy-fill"></i>
                                                    <?php elseif ($rank == 2): ?>
                                                        <i class="bi bi-award-fill"></i>
                                                    <?php elseif ($rank == 3): ?>
                                                        <i class="bi bi-award"></i>
                                                    <?php endif; ?>
                                                    #<?= $rank ?>
                                                </span>
                                                <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>"
                                                    class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                <div class="flex-grow-1">
                                                    <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                                    <div class="small text-muted">
                                                        <?= $user['completed_count'] ?> completed • <?= number_format($user['total_points']) ?> pts
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php
                                        $rank++;
                                    endforeach;
                                    ?>
                                <?php else: ?>
                                    <p class="text-muted text-center py-3">No data yet</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Challenge Stats -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">
                                <i class="bi bi-graph-up"></i> Challenge Stats
                            </h5>
                            <?php if (!empty($challenge_stats)): ?>
                                <?php foreach ($challenge_stats as $cs): ?>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <strong class="small"><?= htmlspecialchars($cs['title']) ?></strong>
                                            <span class="badge bg-light text-dark"><?= $cs['total_participants'] ?> users</span>
                                        </div>
                                        <div class="progress mb-1">
                                            <div class="progress-bar bg-success"
                                                style="width: <?= $cs['total_participants'] > 0 ? ($cs['completed_count'] / $cs['total_participants']) * 100 : 0 ?>%">
                                            </div>
                                        </div>
                                        <small class="text-muted">
                                            <?= $cs['completed_count'] ?> completed (<?= $cs['total_participants'] > 0 ? round(($cs['completed_count'] / $cs['total_participants']) * 100) : 0 ?>%)
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">No active challenges</p>
                            <?php endif; ?>
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
            /* use inline-flex and prevent shrinking so icons stay vertically centered
       with the card content across different breakpoints */
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            flex-shrink: 0;
            align-self: center;
            margin-left: 8px;
        }
    </style>

    <script>
        // Apply Filters
        function applyFilters() {
            const challenge = document.getElementById('challengeFilter').value;
            const status = document.getElementById('statusFilter').value;

            let url = 'user-challenges-progress.php?';
            if (challenge) url += 'challenge=' + challenge + '&';
            if (status) url += 'status=' + status;

            window.location.href = url;
        }

        // Search Progress
        function searchProgress() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.progress-row');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        }

        // Export Progress
        function exportProgress() {
            const data = <?= json_encode($user_progress) ?>;

            if (data.length === 0) {
                alert('No data to export');
                return;
            }

            const headers = ['User', 'Email', 'Challenge', 'Category', 'Progress', 'Target', 'Percentage', 'Status', 'Points Earned', 'Joined Date', 'Completed Date'];
            let csv = headers.join(',') + '\n';

            data.forEach(row => {
                const percentage = Math.round((row.progress / row.target_value) * 100);
                const values = [
                    `"${row.full_name}"`,
                    `"${row.email}"`,
                    `"${row.challenge_title}"`,
                    row.category,
                    row.progress,
                    row.target_value,
                    percentage + '%',
                    row.completed ? 'Completed' : 'In Progress',
                    row.points_earned || 0,
                    row.joined_at,
                    row.completed_at || 'N/A'
                ];
                csv += values.join(',') + '\n';
            });

            const blob = new Blob([csv], {
                type: 'text/csv'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'user_challenges_progress_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
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

// Auto-create challenges table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS challenges (
    challenge_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    challenge_type ENUM('daily', 'weekly', 'monthly', 'special') DEFAULT 'weekly',
    category ENUM('donation', 'waste_reduction', 'recipe', 'community', 'sustainability') DEFAULT 'donation',
    points INT DEFAULT 10,
    target_value INT DEFAULT 1,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('draft', 'active', 'completed', 'cancelled') DEFAULT 'draft',
    banner_image VARCHAR(255),
    prize_description TEXT,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id) ON DELETE SET NULL
)";
$conn->query($create_table);

// Create challenge_participants table
$create_participants = "CREATE TABLE IF NOT EXISTS challenge_participants (
    participant_id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    user_id INT NOT NULL,
    progress INT DEFAULT 0,
    completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    points_earned INT DEFAULT 0,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (challenge_id) REFERENCES challenges(challenge_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_participation (challenge_id, user_id)
)";
$conn->query($create_participants);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false];
    
    try {
        if ($action === 'create_challenge') {
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $challenge_type = $_POST['challenge_type'];
            $category = $_POST['category'];
            $points = intval($_POST['points']);
            $target_value = intval($_POST['target_value']);
            $start_date = trim($_POST['start_date']);
            $end_date = trim($_POST['end_date']);
            $status = $_POST['status'];
            $prize_description = trim($_POST['prize_description'] ?? '');
            
            // Validate dates
            if (empty($start_date) || empty($end_date)) {
                $response['message'] = 'Start date and end date are required!';
                echo json_encode($response);
                exit();
            }
            
            // Validate date format (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                $response['message'] = 'Invalid date format!';
                echo json_encode($response);
                exit();
            }
            
            // Validate end date is after start date
            if (strtotime($end_date) <= strtotime($start_date)) {
                $response['message'] = 'End date must be after start date!';
                echo json_encode($response);
                exit();
            }
            
            // Handle image upload
            $banner_image = null;
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === 0) {
                $upload_dir = '../uploads/challenges/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_ext, $allowed)) {
                    $filename = 'challenge_' . time() . '_' . uniqid() . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $upload_dir . $filename)) {
                        $banner_image = 'uploads/challenges/' . $filename;
                    }
                }
            }
            
            $stmt = $conn->prepare("
                INSERT INTO challenges (title, description, challenge_type, category, points, 
                                      target_value, start_date, end_date, status, banner_image, 
                                      prize_description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->bind_param('sssssisssssi', $title, $description, $challenge_type, $category, 
                            $points, $target_value, $start_date, $end_date, $status, 
                            $banner_image, $prize_description, $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Challenge created successfully!';
            }
            $stmt->close();
            
        } elseif ($action === 'update_challenge') {
            $challenge_id = intval($_POST['challenge_id']);
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $challenge_type = $_POST['challenge_type'];
            $category = $_POST['category'];
            $points = intval($_POST['points']);
            $target_value = intval($_POST['target_value']);
            $start_date = trim($_POST['start_date']);
            $end_date = trim($_POST['end_date']);
            $status = $_POST['status'];
            $prize_description = trim($_POST['prize_description'] ?? '');
            
            // Validate dates
            if (empty($start_date) || empty($end_date)) {
                $response['message'] = 'Start date and end date are required!';
                echo json_encode($response);
                exit();
            }
            
            // Validate date format (YYYY-MM-DD)
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start_date) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end_date)) {
                $response['message'] = 'Invalid date format!';
                echo json_encode($response);
                exit();
            }
            
            // Validate end date is after start date
            if (strtotime($end_date) <= strtotime($start_date)) {
                $response['message'] = 'End date must be after start date!';
                echo json_encode($response);
                exit();
            }
            
            // Handle image upload
            $banner_image = $_POST['existing_image'] ?? null;
            if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === 0) {
                $upload_dir = '../uploads/challenges/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif'];
                if (in_array($file_ext, $allowed)) {
                    // Delete old image
                    if ($banner_image && file_exists('../' . $banner_image)) {
                        unlink('../' . $banner_image);
                    }
                    $filename = 'challenge_' . time() . '_' . uniqid() . '.' . $file_ext;
                    if (move_uploaded_file($_FILES['banner_image']['tmp_name'], $upload_dir . $filename)) {
                        $banner_image = 'uploads/challenges/' . $filename;
                    }
                }
            }
            
            $stmt = $conn->prepare("
                UPDATE challenges 
                SET title = ?, description = ?, challenge_type = ?, category = ?, points = ?,
                    target_value = ?, start_date = ?, end_date = ?, status = ?, 
                    banner_image = ?, prize_description = ?
                WHERE challenge_id = ?
            ");
            $stmt->bind_param('ssssississsi', $title, $description, $challenge_type, $category,
                            $points, $target_value, $start_date, $end_date, $status,
                            $banner_image, $prize_description, $challenge_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Challenge updated successfully!';
            }
            $stmt->close();
            
        } elseif ($action === 'delete_challenge') {
            $challenge_id = intval($_POST['challenge_id']);
            
            // Get and delete image
            $stmt = $conn->prepare("SELECT banner_image FROM challenges WHERE challenge_id = ?");
            $stmt->bind_param('i', $challenge_id);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                if ($row['banner_image'] && file_exists('../' . $row['banner_image'])) {
                    unlink('../' . $row['banner_image']);
                }
            }
            $stmt->close();
            
            $stmt = $conn->prepare("DELETE FROM challenges WHERE challenge_id = ?");
            $stmt->bind_param('i', $challenge_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Challenge deleted successfully!';
            }
            $stmt->close();
            
        } elseif ($action === 'update_status') {
            $challenge_id = intval($_POST['challenge_id']);
            $status = $_POST['status'];
            
            $stmt = $conn->prepare("UPDATE challenges SET status = ? WHERE challenge_id = ?");
            $stmt->bind_param('si', $status, $challenge_id);
            
            if ($stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Status updated successfully!';
            }
            $stmt->close();
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Get statistics
$stats = [
    'total' => 0, 'active' => 0, 'completed' => 0, 'draft' => 0,
    'total_participants' => 0, 'avg_completion' => 0
];

try {
    // Challenge stats
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed,
            COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft
        FROM challenges
    ");
    if ($result) {
        $stats = array_merge($stats, $result->fetch_assoc());
    }
    
    // Participant stats
    $result = $conn->query("SELECT COUNT(*) as total_participants FROM challenge_participants");
    if ($result) {
        $part_data = $result->fetch_assoc();
        $stats['total_participants'] = $part_data['total_participants'];
    }
    
    // Completion rate
    $result = $conn->query("
        SELECT 
            COUNT(CASE WHEN completed = TRUE THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0) as avg_completion
        FROM challenge_participants
    ");
    if ($result) {
        $comp_data = $result->fetch_assoc();
        $stats['avg_completion'] = round($comp_data['avg_completion'] ?? 0, 1);
    }
    
    // Get filter
    $status_filter = isset($_GET['status']) ? $_GET['status'] : '';
    
    // Get all challenges with participant count
    if ($status_filter && $status_filter !== 'all') {
        $stmt = $conn->prepare("
            SELECT c.*, ua.full_name as creator_name,
                   DATE_FORMAT(c.start_date, '%Y-%m-%d') as start_date,
                   DATE_FORMAT(c.end_date, '%Y-%m-%d') as end_date,
                   COUNT(DISTINCT cp.participant_id) as participant_count,
                   COUNT(CASE WHEN cp.completed = TRUE THEN 1 END) as completed_count
            FROM challenges c
            LEFT JOIN user_accounts ua ON c.created_by = ua.user_id
            LEFT JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id
            WHERE c.status = ?
            GROUP BY c.challenge_id
            ORDER BY c.created_at DESC
        ");
        $stmt->bind_param('s', $status_filter);
        $stmt->execute();
        $challenges = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } else {
        $result = $conn->query("
            SELECT c.*, ua.full_name as creator_name,
                   DATE_FORMAT(c.start_date, '%Y-%m-%d') as start_date,
                   DATE_FORMAT(c.end_date, '%Y-%m-%d') as end_date,
                   COUNT(DISTINCT cp.participant_id) as participant_count,
                   COUNT(CASE WHEN cp.completed = TRUE THEN 1 END) as completed_count
            FROM challenges c
            LEFT JOIN user_accounts ua ON c.created_by = ua.user_id
            LEFT JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id
            GROUP BY c.challenge_id
            ORDER BY c.created_at DESC
        ");
        $challenges = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
    }
    
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
    $challenges = [];
}

include 'header.php';
?>

<body>
<?php include 'topbar.php'; ?>
<?php include 'sidebar.php'; ?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-trophy"></i> Challenges & Events Management</h1>
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
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Challenges</h6>
                                <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                            </div>
                            <div class="stat-icon bg-primary">
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
                                <h6 class="text-muted mb-2">Active Challenges</h6>
                                <h3 class="mb-0"><?= number_format($stats['active']) ?></h3>
                            </div>
                            <div class="stat-icon bg-success">
                                <i class="bi bi-lightning"></i>
                            </div>
                        </div>
                        <small class="text-muted">Currently running</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Total Participants</h6>
                                <h3 class="mb-0"><?= number_format($stats['total_participants']) ?></h3>
                            </div>
                            <div class="stat-icon bg-info">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                        <small class="text-muted">Engaged users</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Completion Rate</h6>
                                <h3 class="mb-0"><?= $stats['avg_completion'] ?>%</h3>
                            </div>
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-graph-up"></i>
                            </div>
                        </div>
                        <small class="text-muted">Average</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Challenges Table -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Challenges</h5>
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="showCreateModal()">
                            <i class="bi bi-plus-circle"></i> Create Challenge
                        </button>
                        <button class="btn btn-success" onclick="exportChallenges()">
                            <i class="bi bi-file-earmark-excel"></i> Export
                        </button>
                    </div>
                </div>

                <!-- Filters -->
                <div class="mb-3">
                    <div class="btn-group me-2" role="group">
                        <a href="admin-challenges.php" class="btn btn-sm btn-outline-primary <?= $status_filter === '' ? 'active' : '' ?>">
                            All (<?= $stats['total'] ?>)
                        </a>
                        <a href="admin-challenges.php?status=active" class="btn btn-sm btn-outline-success <?= $status_filter === 'active' ? 'active' : '' ?>">
                            Active (<?= $stats['active'] ?>)
                        </a>
                        <a href="admin-challenges.php?status=draft" class="btn btn-sm btn-outline-secondary <?= $status_filter === 'draft' ? 'active' : '' ?>">
                            Draft (<?= $stats['draft'] ?>)
                        </a>
                        <a href="admin-challenges.php?status=completed" class="btn btn-sm btn-outline-info <?= $status_filter === 'completed' ? 'active' : '' ?>">
                            Completed (<?= $stats['completed'] ?>)
                        </a>
                    </div>
                </div>

                <!-- Search -->
                <div class="mb-3">
                    <input type="text" class="form-control" id="searchInput" 
                           placeholder="Search challenges by title, description, or category..." 
                           onkeyup="searchChallenges()">
                </div>

                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>Challenge</th>
                                <th>Type</th>
                                <th>Category</th>
                                <th>Duration</th>
                                <th>Participants</th>
                                <th>Points</th>
                                <th>Status</th>
                                <th class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($challenges)): ?>
                                <?php foreach ($challenges as $challenge): ?>
                                    <tr class="challenge-row">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <?php if ($challenge['banner_image']): ?>
                                                    <img src="../<?= htmlspecialchars($challenge['banner_image']) ?>" 
                                                         class="rounded me-2" width="50" height="50" 
                                                         style="object-fit: cover;" alt="Challenge">
                                                <?php else: ?>
                                                    <div class="bg-primary rounded me-2 d-flex align-items-center justify-content-center" 
                                                         style="width: 50px; height: 50px;">
                                                        <i class="bi bi-trophy text-white"></i>
                                                    </div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong><?= htmlspecialchars($challenge['title']) ?></strong>
                                                    <br><small class="text-muted"><?= htmlspecialchars(substr($challenge['description'], 0, 50) . '...') ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-info"><?= ucfirst($challenge['challenge_type']) ?></span></td>
                                        <td><span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $challenge['category'])) ?></span></td>
                                        <td>
                                            <small>
                                                <?= date('M j, Y', strtotime($challenge['start_date'])) ?> - <?= date('M j, Y', strtotime($challenge['end_date'])) ?>
                                                <br>
                                                <span class="text-muted">
                                                    (<?php 
                                                        $start = new DateTime($challenge['start_date']);
                                                        $end = new DateTime($challenge['end_date']);
                                                        $diff = $start->diff($end);
                                                        $days = $diff->days + 1; // Include both start and end days
                                                        echo $days . ' day' . ($days != 1 ? 's' : '');
                                                    ?>)
                                                </span>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?= $challenge['participant_count'] ?> joined
                                                <?php if ($challenge['participant_count'] > 0): ?>
                                                    <br><small>(<?= $challenge['completed_count'] ?> completed)</small>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                        <td><strong><?= number_format($challenge['points']) ?></strong> pts</td>
                                        <td>
                                            <select class="form-select form-select-sm" 
                                                    onchange="updateStatus(<?= $challenge['challenge_id'] ?>, this.value)">
                                                <option value="draft" <?= $challenge['status'] === 'draft' ? 'selected' : '' ?>>Draft</option>
                                                <option value="active" <?= $challenge['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                <option value="completed" <?= $challenge['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                <option value="cancelled" <?= $challenge['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                            </select>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group">
                                                <button class="btn btn-sm btn-info" 
                                                        onclick='viewChallenge(<?= json_encode($challenge, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-warning" 
                                                        onclick='editChallenge(<?= json_encode($challenge, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)' 
                                                        title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-success" 
                                                        onclick="viewParticipants(<?= $challenge['challenge_id'] ?>)" 
                                                        title="View Participants">
                                                    <i class="bi bi-people"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" 
                                                        onclick="deleteChallenge(<?= $challenge['challenge_id'] ?>)" 
                                                        title="Delete">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">
                                        <i class="bi bi-trophy" style="font-size: 3rem;"></i>
                                        <p class="mt-2">No challenges found. Create your first challenge!</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Create/Edit Challenge Modal -->
<div class="modal fade" id="challengeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="challengeModalTitle">
                    <i class="bi bi-trophy"></i> Create Challenge
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="challengeForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="challenge_id" id="challenge_id">
                    <input type="hidden" name="existing_image" id="existing_image">
                    
                    <div class="row">
                        <div class="col-12 mb-3">
                            <label class="form-label">Challenge Title *</label>
                            <input type="text" class="form-control" name="title" id="title" required>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" name="description" id="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Challenge Type *</label>
                            <select class="form-select" name="challenge_type" id="challenge_type" required>
                                <option value="daily">Daily</option>
                                <option value="weekly" selected>Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="special">Special Event</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Category *</label>
                            <select class="form-select" name="category" id="category" required>
                                <option value="donation" selected>Food Donation</option>
                                <option value="waste_reduction">Waste Reduction</option>
                                <option value="recipe">Recipe Sharing</option>
                                <option value="community">Community</option>
                                <option value="sustainability">Sustainability</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Points Reward *</label>
                            <input type="number" class="form-control" name="points" id="points" min="1" value="10" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Target Value *</label>
                            <input type="number" class="form-control" name="target_value" id="target_value" min="1" value="1" required>
                            <small class="text-muted">Goal to complete challenge (e.g., 5 donations)</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" id="start_date" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date *</label>
                            <input type="date" class="form-control" name="end_date" id="end_date" required>
                            <small class="text-muted">Must be after start date</small>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status *</label>
                            <select class="form-select" name="status" id="status" required>
                                <option value="draft" selected>Draft</option>
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Banner Image</label>
                            <input type="file" class="form-control" name="banner_image" id="banner_image" accept="image/*">
                            <div id="imagePreview" class="mt-2"></div>
                        </div>
                        
                        <div class="col-12 mb-3">
                            <label class="form-label">Prize/Reward Description</label>
                            <textarea class="form-control" name="prize_description" id="prize_description" rows="2"></textarea>
                            <small class="text-muted">Optional: Describe any prizes or rewards</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="bi bi-save"></i> Save Challenge
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Challenge Modal -->
<div class="modal fade" id="viewChallengeModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-trophy"></i> Challenge Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="challengeDetailsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

<!-- View Participants Modal -->
<div class="modal fade" id="participantsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-people"></i> Challenge Participants</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="participantsContent">
                <!-- Content loaded dynamically -->
            </div>
        </div>
    </div>
</div>

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

#imagePreview img {
    max-width: 200px;
    max-height: 150px;
    border-radius: 8px;
}
</style>

<script>
// Show Create Modal
function showCreateModal() {
    document.getElementById('challengeModalTitle').innerHTML = '<i class="bi bi-trophy"></i> Create Challenge';
    document.getElementById('challengeForm').reset();
    document.getElementById('challenge_id').value = '';
    document.getElementById('imagePreview').innerHTML = '';
    document.getElementById('challengeForm').action = '';
    new bootstrap.Modal(document.getElementById('challengeModal')).show();
}

// Edit Challenge
function editChallenge(challenge) {
    document.getElementById('challengeModalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit Challenge';
    document.getElementById('challenge_id').value = challenge.challenge_id;
    document.getElementById('title').value = challenge.title;
    document.getElementById('description').value = challenge.description;
    document.getElementById('challenge_type').value = challenge.challenge_type;
    document.getElementById('category').value = challenge.category;
    document.getElementById('points').value = challenge.points;
    document.getElementById('target_value').value = challenge.target_value;
    
    // Format dates properly for HTML5 date input (YYYY-MM-DD)
    const formatDate = (dateString) => {
        if (!dateString) return '';
        
        // Handle MySQL zero date
        if (dateString === '0000-00-00' || dateString === '0000-00-00 00:00:00') {
            console.warn('Zero date detected:', dateString);
            return '';
        }
        
        // If already in YYYY-MM-DD format, validate it's not a zero date
        if (typeof dateString === 'string' && dateString.match(/^\d{4}-\d{2}-\d{2}$/)) {
            // Check if it's a valid date (not 0000-00-00)
            if (dateString.startsWith('0000-')) {
                console.warn('Invalid zero date:', dateString);
                return '';
            }
            return dateString;
        }
        
        // Extract date part if it includes time (YYYY-MM-DD HH:MM:SS)
        let datePart = dateString;
        if (typeof dateString === 'string' && dateString.includes(' ')) {
            datePart = dateString.split(' ')[0];
            // Check for zero date
            if (datePart === '0000-00-00' || datePart.startsWith('0000-')) {
                console.warn('Invalid zero date:', datePart);
                return '';
            }
            // If it's already in YYYY-MM-DD format after splitting, return it
            if (datePart.match(/^\d{4}-\d{2}-\d{2}$/)) {
                return datePart;
            }
        }
        
        // Try to parse and format
        const date = new Date(datePart + 'T00:00:00'); // Add time to avoid timezone issues
        if (isNaN(date.getTime()) || date.getFullYear() < 1900) {
            console.warn('Invalid date:', dateString, 'Parsed as:', date);
            return '';
        }
        
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        const formatted = `${year}-${month}-${day}`;
        
        // Double check the formatted date is valid
        if (formatted.startsWith('0000-') || year < 1900) {
            console.warn('Formatted date is invalid:', formatted, 'from:', dateString);
            return '';
        }
        
        return formatted;
    };
    
    const startDateFormatted = formatDate(challenge.start_date);
    const endDateFormatted = formatDate(challenge.end_date);
    
    // Set start date first
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    
    // Remove min constraint temporarily to allow setting the value
    endDateInput.removeAttribute('min');
    
    // Set start date
    startDateInput.value = startDateFormatted;
    
    // Set end date value FIRST (before applying min constraint)
    endDateInput.value = endDateFormatted;
    endDateInput.setCustomValidity(''); // Clear any validation errors
    
    // Now set minimum end date to start date + 1 day (after value is set)
    if (startDateFormatted && endDateFormatted) {
        const startDate = new Date(startDateFormatted);
        const existingEndDate = new Date(endDateFormatted);
        const minEndDate = new Date(startDate);
        minEndDate.setDate(minEndDate.getDate() + 1);
        
        // Only apply min if end date is valid (greater than start date)
        if (existingEndDate > startDate) {
            endDateInput.min = minEndDate.toISOString().split('T')[0];
        }
    }
    
    document.getElementById('status').value = challenge.status;
    document.getElementById('prize_description').value = challenge.prize_description || '';
    document.getElementById('existing_image').value = challenge.banner_image || '';
    
    if (challenge.banner_image) {
        document.getElementById('imagePreview').innerHTML = 
            `<img src="../${challenge.banner_image}" class="img-thumbnail">`;
    } else {
        document.getElementById('imagePreview').innerHTML = '';
    }
    
    new bootstrap.Modal(document.getElementById('challengeModal')).show();
}

// Challenge Form Submit
document.getElementById('challengeForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Validate end date is after start date
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(document.getElementById('end_date').value);
    
    if (endDate <= startDate) {
        showNotification('End date must be after start date!', 'error');
        return;
    }
    
    const formData = new FormData(this);
    const challengeId = document.getElementById('challenge_id').value;
    formData.append('action', challengeId ? 'update_challenge' : 'create_challenge');
    
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
            showNotification(data.message || 'Failed to save challenge', 'error');
        }
    });
});

// Add date validation on change
document.getElementById('start_date').addEventListener('change', function() {
    const startDate = new Date(this.value);
    const endDateInput = document.getElementById('end_date');
    if (endDateInput.value) {
        const endDate = new Date(endDateInput.value);
        if (endDate <= startDate) {
            endDateInput.setCustomValidity('End date must be after start date');
        } else {
            endDateInput.setCustomValidity('');
        }
    }
    // Set minimum end date to start date + 1 day
    const minEndDate = new Date(startDate);
    minEndDate.setDate(minEndDate.getDate() + 1);
    endDateInput.min = minEndDate.toISOString().split('T')[0];
});

document.getElementById('end_date').addEventListener('change', function() {
    const endDate = new Date(this.value);
    const startDateInput = document.getElementById('start_date');
    if (startDateInput.value) {
        const startDate = new Date(startDateInput.value);
        if (endDate <= startDate) {
            this.setCustomValidity('End date must be after start date');
        } else {
            this.setCustomValidity('');
        }
    }
});

// Helper function to format date for display
function formatDisplayDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    if (isNaN(date.getTime())) return dateString;
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Helper function to calculate duration
function calculateDuration(startDate, endDate) {
    if (!startDate || !endDate) return 'N/A';
    const start = new Date(startDate);
    const end = new Date(endDate);
    if (isNaN(start.getTime()) || isNaN(end.getTime())) return 'Invalid dates';
    
    const diffTime = Math.abs(end - start);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1; // +1 to include both start and end days
    
    if (diffDays === 1) {
        return '1 day';
    } else if (diffDays < 30) {
        return `${diffDays} days`;
    } else if (diffDays < 365) {
        const months = Math.floor(diffDays / 30);
        const days = diffDays % 30;
        return months > 0 ? `${months} month${months > 1 ? 's' : ''}${days > 0 ? ` ${days} day${days > 1 ? 's' : ''}` : ''}` : `${diffDays} days`;
    } else {
        const years = Math.floor(diffDays / 365);
        const months = Math.floor((diffDays % 365) / 30);
        return `${years} year${years > 1 ? 's' : ''}${months > 0 ? ` ${months} month${months > 1 ? 's' : ''}` : ''}`;
    }
}

// View Challenge Details
function viewChallenge(challenge) {
    let banner = '';
    if (challenge.banner_image) {
        banner = `<img src="../${challenge.banner_image}" class="img-fluid rounded mb-3" style="max-height: 300px;">`;
    }
    
    const statusColors = {
        'draft': 'secondary',
        'active': 'success',
        'completed': 'info',
        'cancelled': 'danger'
    };
    
    const content = `
        <div class="row">
            ${banner ? `<div class="col-12 mb-3">${banner}</div>` : ''}
            <div class="col-md-6 mb-3">
                <strong>Challenge ID:</strong> #${challenge.challenge_id}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Status:</strong> <span class="badge bg-${statusColors[challenge.status] || 'secondary'}">${challenge.status.toUpperCase()}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Type:</strong> <span class="badge bg-info">${challenge.challenge_type}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Category:</strong> <span class="badge bg-secondary">${challenge.category.replace('_', ' ')}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Points Reward:</strong> ${challenge.points} pts
            </div>
            <div class="col-md-6 mb-3">
                <strong>Target Value:</strong> ${challenge.target_value}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Start Date:</strong> ${formatDisplayDate(challenge.start_date)}
            </div>
            <div class="col-md-6 mb-3">
                <strong>End Date:</strong> ${formatDisplayDate(challenge.end_date)}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Duration:</strong> ${calculateDuration(challenge.start_date, challenge.end_date)}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Participants:</strong> ${challenge.participant_count} joined
            </div>
            <div class="col-md-6 mb-3">
                <strong>Completed:</strong> ${challenge.completed_count} users
            </div>
            <div class="col-12 mb-3">
                <strong>Title:</strong>
                <div class="alert alert-light mt-2">${challenge.title}</div>
            </div>
            <div class="col-12 mb-3">
                <strong>Description:</strong>
                <div class="alert alert-light mt-2">${challenge.description}</div>
            </div>
            ${challenge.prize_description ? `
            <div class="col-12 mb-3">
                <strong>Prize/Reward:</strong>
                <div class="alert alert-warning mt-2">
                    <i class="bi bi-gift"></i> ${challenge.prize_description}
                </div>
            </div>
            ` : ''}
            <div class="col-12 mb-3">
                <strong>Created By:</strong> ${challenge.creator_name || 'N/A'}
            </div>
            <div class="col-12">
                <hr>
                <div class="d-grid gap-2">
                    <button class="btn btn-success" onclick="viewParticipants(${challenge.challenge_id})">
                        <i class="bi bi-people"></i> View Participants
                    </button>
                    <button class="btn btn-warning" onclick='editChallenge(${JSON.stringify(challenge)})'>
                        <i class="bi bi-pencil"></i> Edit Challenge
                    </button>
                    <button class="btn btn-outline-danger" onclick="deleteChallenge(${challenge.challenge_id})">
                        <i class="bi bi-trash"></i> Delete Challenge
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('challengeDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('viewChallengeModal')).show();
}

// View Participants
function viewParticipants(challengeId) {
    fetch(`get_participants.php?challenge_id=${challengeId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let content = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th>User</th><th>Progress</th><th>Status</th><th>Joined</th></tr></thead><tbody>';
                
                if (data.participants.length > 0) {
                    data.participants.forEach(p => {
                        const progressPercent = (p.progress / p.target_value) * 100;
                        content += `
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <img src="${p.profile_img ? '../uploads/profile_picture/' + p.profile_img : '../uploads/profile_picture/no_image.png'}" 
                                             class="rounded-circle me-2" width="32" height="32">
                                        <strong>${p.full_name}</strong>
                                    </div>
                                </td>
                                <td>
                                    <div class="progress" style="width: 150px;">
                                        <div class="progress-bar" role="progressbar" style="width: ${progressPercent}%" 
                                             aria-valuenow="${p.progress}" aria-valuemin="0" aria-valuemax="${p.target_value}">
                                            ${p.progress}/${p.target_value}
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    ${p.completed ? '<span class="badge bg-success">Completed</span>' : '<span class="badge bg-warning">In Progress</span>'}
                                </td>
                                <td>${new Date(p.joined_at).toLocaleDateString()}</td>
                            </tr>
                        `;
                    });
                } else {
                    content += '<tr><td colspan="4" class="text-center text-muted">No participants yet</td></tr>';
                }
                
                content += '</tbody></table></div>';
                document.getElementById('participantsContent').innerHTML = content;
                new bootstrap.Modal(document.getElementById('participantsModal')).show();
            }
        });
}

// Delete Challenge
function deleteChallenge(challengeId) {
    if (!confirm('Delete this challenge? All participant data will be lost!')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_challenge');
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
            showNotification(data.message || 'Failed to delete', 'error');
        }
    });
}

// Update Status
function updateStatus(challengeId, status) {
    const formData = new FormData();
    formData.append('action', 'update_status');
    formData.append('challenge_id', challengeId);
    formData.append('status', status);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message || 'Failed to update', 'error');
        }
    });
}

// Search Challenges
function searchChallenges() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.challenge-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// Export Challenges
function exportChallenges() {
    const data = <?= json_encode($challenges) ?>;
    
    if (data.length === 0) {
        showNotification('No challenges to export', 'warning');
        return;
    }
    
    const headers = ['ID', 'Title', 'Type', 'Category', 'Points', 'Target', 'Start Date', 'End Date', 'Participants', 'Status'];
    let csv = headers.join(',') + '\n';
    
    data.forEach(row => {
        const values = [
            row.challenge_id,
            `"${row.title}"`,
            row.challenge_type,
            row.category,
            row.points,
            row.target_value,
            row.start_date,
            row.end_date,
            row.participant_count,
            row.status
        ];
        csv += values.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'challenges_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Challenges exported successfully!', 'success');
}

// Image Preview
document.getElementById('banner_image').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').innerHTML = 
                `<img src="${e.target.result}" class="img-thumbnail">`;
        };
        reader.readAsDataURL(file);
    }
});

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


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

// Create announcements table if it doesn't exist
$create_table = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('announcement', 'guideline', 'reminder') DEFAULT 'announcement',
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at TIMESTAMP NULL,
    approval_status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    FOREIGN KEY (created_by) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at)
)";

$conn->query($create_table);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'create':
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                $type = $_POST['type'];
                $priority = $_POST['priority'];
                $status = $_POST['status'];
                
                if (empty($title) || empty($content)) {
                    echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
                    exit;
                }
                
                $stmt = $conn->prepare("INSERT INTO announcements (title, content, type, priority, status, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('sssssi', $title, $content, $type, $priority, $status, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Announcement created successfully!', 'id' => $conn->insert_id]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to create announcement.']);
                }
                $stmt->close();
                break;
                
            case 'update':
                $id = intval($_POST['id']);
                $title = trim($_POST['title']);
                $content = trim($_POST['content']);
                $type = $_POST['type'];
                $priority = $_POST['priority'];
                $status = $_POST['status'];
                
                if (empty($title) || empty($content)) {
                    echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
                    exit;
                }
                
                $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, type = ?, priority = ?, status = ?, updated_at = NOW() WHERE id = ? AND created_by = ?");
                $stmt->bind_param('sssssii', $title, $content, $type, $priority, $status, $id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Announcement updated successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to update announcement.']);
                }
                $stmt->close();
                break;
                
            case 'delete':
                $id = intval($_POST['id']);
                
                $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND created_by = ?");
                $stmt->bind_param('ii', $id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete announcement.']);
                }
                $stmt->close();
                break;
                
            case 'publish':
                $id = intval($_POST['id']);
                
                $stmt = $conn->prepare("UPDATE announcements SET status = 'published', published_at = NOW() WHERE id = ? AND created_by = ?");
                $stmt->bind_param('ii', $id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Announcement published successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to publish announcement.']);
                }
                $stmt->close();
                break;
                
            case 'approve_donation':
                $id = intval($_POST['id']);
                
                $stmt = $conn->prepare("UPDATE food_donations SET approval_status = 'approved', status = 'available', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Food donation approved successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to approve donation.']);
                }
                $stmt->close();
                break;
                
            case 'reject_donation':
                $id = intval($_POST['id']);
                $reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';
                
                $stmt = $conn->prepare("UPDATE food_donations SET approval_status = 'rejected', status = 'cancelled', updated_at = NOW() WHERE id = ?");
                $stmt->bind_param('i', $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Food donation rejected.', 'reason' => $reason]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to reject donation.']);
                }
                $stmt->close();
                break;
                
            case 'get_donation_details':
                $id = intval($_POST['id']);
                
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email, ua.phone_number, ua.address
                    FROM food_donations fd
                    JOIN user_accounts ua ON fd.user_id = ua.user_id
                    WHERE fd.id = ?
                ");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($row = $result->fetch_assoc()) {
                    echo json_encode(['success' => true, 'data' => $row]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Donation not found.']);
                }
                $stmt->close();
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// Get filter parameters
$post_type = isset($_GET['post_type']) ? $_GET['post_type'] : 'all'; // 'all', 'announcements', 'food_donations'
$type_filter = isset($_GET['type']) ? $_GET['type'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Get all posts (announcements + food donations)
$all_posts = [];

// Fetch announcements
if ($post_type === 'all' || $post_type === 'announcements') {
    $where_conditions = [];
    $params = [];
    $param_types = '';

    if (!empty($type_filter)) {
        $where_conditions[] = "a.type = ?";
        $params[] = $type_filter;
        $param_types .= 's';
    }

    if (!empty($status_filter)) {
        $where_conditions[] = "a.status = ?";
        $params[] = $status_filter;
        $param_types .= 's';
    }
    
    if (!empty($search_query)) {
        $where_conditions[] = "(a.title LIKE ? OR a.content LIKE ?)";
        $search_param = "%{$search_query}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= 'ss';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    $query = "
        SELECT 
            a.*,
            ua.full_name as created_by_name,
            ua.email as created_by_email,
            'announcement' as post_type
        FROM announcements a 
        JOIN user_accounts ua ON a.created_by = ua.user_id
        $where_clause
        ORDER BY a.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $announcements_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $all_posts = array_merge($all_posts, $announcements_data);
}

// Fetch food donations
if ($post_type === 'all' || $post_type === 'food_donations') {
    $where_conditions = ["fd.approval_status = 'pending'"];
    $params = [];
    $param_types = '';
    
    if (!empty($search_query)) {
        $where_conditions[] = "(fd.title LIKE ? OR fd.description LIKE ?)";
        $search_param = "%{$search_query}%";
        $params[] = $search_param;
        $params[] = $search_param;
        $param_types .= 'ss';
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

    $query = "
        SELECT 
            fd.*,
            ua.full_name as created_by_name,
            ua.email as created_by_email,
            ua.phone_number as created_by_phone,
            'food_donation' as post_type,
            fd.description as content
        FROM food_donations fd
        JOIN user_accounts ua ON fd.user_id = ua.user_id
        $where_clause
        ORDER BY fd.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($param_types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $food_donations_data = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    
    $all_posts = array_merge($all_posts, $food_donations_data);
}

// Sort all posts by created_at DESC
usort($all_posts, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Get statistics
$stats_query = "
    SELECT 
        (SELECT COUNT(*) FROM announcements WHERE status = 'published') as total_announcements,
        (SELECT COUNT(*) FROM food_donations WHERE approval_status = 'pending') as pending_donations,
        (SELECT COUNT(*) FROM food_donations WHERE approval_status = 'approved') as approved_donations,
        (SELECT COUNT(*) FROM food_donations WHERE approval_status = 'rejected') as rejected_donations
";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-megaphone"></i> Community Content & Posts Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Content Management</li>
                <li class="breadcrumb-item active">Posts & Announcements</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Announcements</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center">
                                <i class="bi bi-megaphone"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $stats['total_announcements']; ?></h6>
                                <span class="text-muted small pt-2 ps-1">Published</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Pending Donations</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-warning">
                                <i class="bi bi-clock"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $stats['pending_donations']; ?></h6>
                                <span class="text-muted small pt-2 ps-1">Awaiting Review</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Approved Donations</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-success">
                                <i class="bi bi-check-circle"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo $stats['approved_donations']; ?></h6>
                                <span class="text-muted small pt-2 ps-1">Approved</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="card info-card">
                    <div class="card-body">
                        <h5 class="card-title">Total Posts</h5>
                        <div class="d-flex align-items-center">
                            <div class="card-icon rounded-circle d-flex align-items-center justify-content-center bg-info">
                                <i class="bi bi-collection"></i>
                            </div>
                            <div class="ps-3">
                                <h6><?php echo count($all_posts); ?></h6>
                                <span class="text-muted small pt-2 ps-1">All Posts</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters and Create Button -->
        <div class="row mb-4">
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="post_type" class="form-label">Post Type</label>
                                <select class="form-select" id="post_type" name="post_type" onchange="updateFilters()">
                                    <option value="all" <?php echo $post_type === 'all' ? 'selected' : ''; ?>>All Posts</option>
                                    <option value="announcements" <?php echo $post_type === 'announcements' ? 'selected' : ''; ?>>Announcements Only</option>
                                    <option value="food_donations" <?php echo $post_type === 'food_donations' ? 'selected' : ''; ?>>Food Donations Only</option>
                                </select>
                            </div>
                            <div class="col-md-3" id="type_filter_div">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="">All Types</option>
                                    <option value="announcement" <?php echo $type_filter === 'announcement' ? 'selected' : ''; ?>>Announcement</option>
                                    <option value="guideline" <?php echo $type_filter === 'guideline' ? 'selected' : ''; ?>>Guideline</option>
                                    <option value="reminder" <?php echo $type_filter === 'reminder' ? 'selected' : ''; ?>>Reminder</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search_query); ?>" placeholder="Search by title...">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Filter
                                    </button>
                                    <a href="announcements.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-clockwise"></i> Clear
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-3">
                <div class="card">
                    <div class="card-body text-center">
                        <button type="button" class="btn btn-success btn-lg" onclick="createAnnouncement()">
                            <i class="bi bi-plus-circle"></i> Create Announcement
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- All Posts List -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-collection"></i> All Community Posts
                        </h5>
                        <div>
                            <button class="btn btn-sm btn-outline-primary me-2" onclick="location.reload()">
                                <i class="bi bi-arrow-clockwise"></i> Refresh
                            </button>
                            <span class="badge bg-primary"><?php echo count($all_posts); ?> posts</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($all_posts)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                <h4 class="mt-3">No Posts Found</h4>
                                <p class="text-muted">No posts match your current filters.</p>
                                <button type="button" class="btn btn-primary" onclick="createAnnouncement()">
                                    <i class="bi bi-plus-circle"></i> Create Your First Announcement
                                </button>
                            </div>
                        <?php else: ?>
                            <!-- Card Grid View -->
                            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                                <?php foreach ($all_posts as $post): ?>
                                    <div class="col">
                                        <div class="card h-100 post-card">
                                            <?php if ($post['post_type'] === 'food_donation'): ?>
                                                <!-- Food Donation Post -->
                                                <div class="card-header bg-primary text-white">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span><i class="bi bi-basket"></i> Food Donation</span>
                                                        <span class="badge bg-warning">Pending Review</span>
                                                    </div>
                                                </div>
                                                <?php 
                                                $images = json_decode($post['images'], true);
                                                if (!empty($images) && is_array($images)): 
                                                ?>
                                                    <img src="../<?php echo htmlspecialchars($images[0]); ?>" class="card-img-top post-image" alt="Food Image" onerror="this.src='https://via.placeholder.com/400x200?text=No+Image'">
                                                <?php else: ?>
                                                    <img src="https://via.placeholder.com/400x200?text=Food+Donation" class="card-img-top post-image" alt="Placeholder">
                                                <?php endif; ?>
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h5>
                                                    <p class="card-text text-muted small">
                                                        <?php echo htmlspecialchars(substr($post['description'], 0, 120)); ?><?php echo strlen($post['description']) > 120 ? '...' : ''; ?>
                                                    </p>
                                                    <div class="mb-2">
                                                        <span class="badge bg-info"><i class="bi bi-tag"></i> <?php echo ucfirst($post['food_type']); ?></span>
                                                        <span class="badge bg-secondary"><i class="bi bi-box"></i> <?php echo htmlspecialchars($post['quantity']); ?></span>
                                                    </div>
                                                    <hr>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($post['created_by_name']); ?>
                                                            </small>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="bi bi-clock"></i> <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($post['location_address'])): ?>
                                                        <div class="mt-2">
                                                            <small class="text-muted">
                                                                <i class="bi bi-geo-alt"></i> <?php echo htmlspecialchars(substr($post['location_address'], 0, 50)); ?>...
                                                            </small>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="btn-group w-100" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="viewFoodDonation(<?php echo $post['id']; ?>)">
                                                            <i class="bi bi-eye"></i> View
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-success" onclick="approveFoodDonation(<?php echo $post['id']; ?>)">
                                                            <i class="bi bi-check"></i> Approve
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="rejectFoodDonation(<?php echo $post['id']; ?>)">
                                                            <i class="bi bi-x"></i> Reject
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <!-- Announcement Post -->
                                                <div class="card-header <?php 
                                                    echo $post['type'] === 'announcement' ? 'bg-info' : 
                                                         ($post['type'] === 'guideline' ? 'bg-warning' : 'bg-primary'); 
                                                ?> text-white">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span>
                                                            <i class="bi <?php 
                                                                echo $post['type'] === 'announcement' ? 'bi-megaphone' : 
                                                                     ($post['type'] === 'guideline' ? 'bi-book' : 'bi-bell'); 
                                                            ?>"></i> <?php echo ucfirst($post['type']); ?>
                                                        </span>
                                                        <span class="badge <?php 
                                                            echo $post['status'] === 'published' ? 'bg-success' : 
                                                                 ($post['status'] === 'draft' ? 'bg-warning' : 'bg-secondary'); 
                                                        ?>">
                                                            <?php echo ucfirst($post['status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="card-body">
                                                    <h5 class="card-title"><?php echo htmlspecialchars($post['title']); ?></h5>
                                                    <p class="card-text text-muted">
                                                        <?php echo htmlspecialchars(substr($post['content'], 0, 150)); ?><?php echo strlen($post['content']) > 150 ? '...' : ''; ?>
                                                    </p>
                                                    <div class="mb-2">
                                                        <span class="badge <?php 
                                                            echo $post['priority'] === 'high' ? 'bg-danger' : 
                                                                 ($post['priority'] === 'medium' ? 'bg-warning' : 'bg-success'); 
                                                        ?>">
                                                            <?php echo ucfirst($post['priority']); ?> Priority
                                                        </span>
                                                    </div>
                                                    <hr>
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($post['created_by_name']); ?>
                                                            </small>
                                                        </div>
                                                        <div>
                                                            <small class="text-muted">
                                                                <i class="bi bi-clock"></i> <?php echo date('M d, Y', strtotime($post['created_at'])); ?>
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="card-footer">
                                                    <div class="btn-group w-100" role="group">
                                                        <button type="button" class="btn btn-sm btn-outline-info" onclick="viewAnnouncement(<?php echo $post['id']; ?>)">
                                                            <i class="bi bi-eye"></i> View
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="editAnnouncement(<?php echo $post['id']; ?>)">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </button>
                                                        <?php if ($post['status'] === 'draft'): ?>
                                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="publishAnnouncement(<?php echo $post['id']; ?>)">
                                                                <i class="bi bi-check"></i> Publish
                                                            </button>
                                                        <?php endif; ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteAnnouncement(<?php echo $post['id']; ?>)">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Create/Edit Modal -->
<div class="modal fade" id="announcementModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Create New Announcement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="announcementForm">
                    <input type="hidden" id="announcement_id">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title *</label>
                        <input type="text" class="form-control" id="title" required>
                    </div>
                    <div class="mb-3">
                        <label for="content" class="form-label">Content *</label>
                        <textarea class="form-control" id="content" rows="6" required></textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <label for="type" class="form-label">Type *</label>
                            <select class="form-select" id="type" required>
                                <option value="announcement">Announcement</option>
                                <option value="guideline">Guideline</option>
                                <option value="reminder">Reminder</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="priority" class="form-label">Priority *</label>
                            <select class="form-select" id="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" required>
                                <option value="draft">Draft</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAnnouncement()">Save Announcement</button>
            </div>
        </div>
    </div>
</div>

<!-- View Announcement Modal -->
<div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Announcement Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="announcementDetails">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- View Food Donation Modal -->
<div class="modal fade" id="viewFoodDonationModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="bi bi-basket"></i> Food Donation Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="foodDonationDetails">
                <!-- Details will be loaded here dynamically -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-danger" id="rejectBtn" onclick="rejectFromModal()">
                    <i class="bi bi-x-circle"></i> Reject
                </button>
                <button type="button" class="btn btn-success" id="approveBtn" onclick="approveFromModal()">
                    <i class="bi bi-check-circle"></i> Approve
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.info-card .card-icon {
    width: 64px;
    height: 64px;
    background: #0d6efd;
}

.post-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.post-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.post-image {
    height: 200px;
    object-fit: cover;
}

.card-footer {
    background-color: #f8f9fa;
}
</style>

<script>
// Global variable for current donation ID
let currentDonationId = null;

function createAnnouncement() {
    document.getElementById('modalTitle').textContent = 'Create New Announcement';
    document.getElementById('announcementForm').reset();
    document.getElementById('announcement_id').value = '';
    new bootstrap.Modal(document.getElementById('announcementModal')).show();
}

function editAnnouncement(id) {
    // This would typically load the announcement data via AJAX
    document.getElementById('modalTitle').textContent = 'Edit Announcement';
    document.getElementById('announcement_id').value = id;
    new bootstrap.Modal(document.getElementById('announcementModal')).show();
}

function viewAnnouncement(id) {
    // This would typically load the announcement data via AJAX
    document.getElementById('announcementDetails').innerHTML = `
        <div class="text-center">
            <i class="bi bi-info-circle text-primary" style="font-size: 3rem;"></i>
            <h5 class="mt-3">Announcement Details</h5>
            <p class="text-muted">Detailed view for announcement ID: ${id}</p>
            <p><em>This feature can be enhanced to show complete announcement details.</em></p>
        </div>
    `;
    new bootstrap.Modal(document.getElementById('viewModal')).show();
}

// Food Donation Functions
function viewFoodDonation(id) {
    currentDonationId = id;
    const formData = new FormData();
    formData.append('action', 'get_donation_details');
    formData.append('id', id);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayFoodDonationDetails(data.data);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while loading donation details.', 'error');
    });
}

function displayFoodDonationDetails(donation) {
    const images = donation.images ? JSON.parse(donation.images) : [];
    let imagesHTML = '';
    
    if (images.length > 0) {
        imagesHTML = '<div class="row mb-3">';
        images.forEach(img => {
            imagesHTML += `
                <div class="col-md-4 mb-2">
                    <img src="../${img}" class="img-fluid rounded" alt="Food Image" onerror="this.src='https://via.placeholder.com/400x300?text=No+Image'">
                </div>
            `;
        });
        imagesHTML += '</div>';
    }
    
    document.getElementById('foodDonationDetails').innerHTML = `
        ${imagesHTML}
        
        <div class="row">
            <div class="col-md-6">
                <h5 class="text-primary"><i class="bi bi-info-circle"></i> Basic Information</h5>
                <table class="table table-sm">
                    <tr>
                        <th width="40%">Title:</th>
                        <td>${donation.title}</td>
                    </tr>
                    <tr>
                        <th>Food Type:</th>
                        <td><span class="badge bg-info">${donation.food_type}</span></td>
                    </tr>
                    <tr>
                        <th>Quantity:</th>
                        <td><span class="badge bg-secondary">${donation.quantity}</span></td>
                    </tr>
                    <tr>
                        <th>Expiration Date:</th>
                        <td>${donation.expiration_date || 'Not specified'}</td>
                    </tr>
                    <tr>
                        <th>Status:</th>
                        <td><span class="badge bg-warning">${donation.approval_status}</span></td>
                    </tr>
                </table>
                
                <h5 class="text-primary mt-4"><i class="bi bi-person-circle"></i> Donor Information</h5>
                <table class="table table-sm">
                    <tr>
                        <th width="40%">Name:</th>
                        <td>${donation.full_name}</td>
                    </tr>
                    <tr>
                        <th>Email:</th>
                        <td><a href="mailto:${donation.email}">${donation.email}</a></td>
                    </tr>
                    <tr>
                        <th>Phone:</th>
                        <td>${donation.phone_number || 'Not provided'}</td>
                    </tr>
                    <tr>
                        <th>Contact Method:</th>
                        <td><span class="badge bg-info">${donation.contact_method}</span></td>
                    </tr>
                    <tr>
                        <th>Contact Info:</th>
                        <td>${donation.contact_info}</td>
                    </tr>
                </table>
            </div>
            
            <div class="col-md-6">
                <h5 class="text-primary"><i class="bi bi-card-text"></i> Description</h5>
                <p class="text-muted">${donation.description}</p>
                
                <h5 class="text-primary mt-4"><i class="bi bi-geo-alt"></i> Location & Pickup</h5>
                <p class="text-muted"><strong>Address:</strong><br>${donation.location_address}</p>
                ${donation.pickup_time_start ? `<p><strong>Pickup Time:</strong> ${donation.pickup_time_start} - ${donation.pickup_time_end || 'Not specified'}</p>` : ''}
                
                ${donation.dietary_info ? `
                <h5 class="text-primary mt-4"><i class="bi bi-heart"></i> Dietary Information</h5>
                <p class="text-muted">${donation.dietary_info}</p>
                ` : ''}
                
                ${donation.allergens ? `
                <div class="alert alert-warning">
                    <strong><i class="bi bi-exclamation-triangle"></i> Allergens:</strong> ${donation.allergens}
                </div>
                ` : ''}
                
                ${donation.storage_instructions ? `
                <h5 class="text-primary mt-4"><i class="bi bi-box-seam"></i> Storage Instructions</h5>
                <p class="text-muted">${donation.storage_instructions}</p>
                ` : ''}
                
                <p class="text-muted small mt-4">
                    <strong>Posted:</strong> ${new Date(donation.created_at).toLocaleString()}<br>
                    <strong>Last Updated:</strong> ${new Date(donation.updated_at).toLocaleString()}
                </p>
            </div>
        </div>
    `;
    
    new bootstrap.Modal(document.getElementById('viewFoodDonationModal')).show();
}

function approveFoodDonation(id) {
    if (confirm('Are you sure you want to approve this food donation? It will be made available to the community.')) {
        const formData = new FormData();
        formData.append('action', 'approve_donation');
        formData.append('id', id);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while approving the donation.', 'error');
        });
    }
}

function rejectFoodDonation(id) {
    if (confirm('Are you sure you want to reject this food donation?')) {
        const formData = new FormData();
        formData.append('action', 'reject_donation');
        formData.append('id', id);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                setTimeout(() => location.reload(), 1500);
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while rejecting the donation.', 'error');
        });
    }
}

function approveFromModal() {
    if (currentDonationId) {
        bootstrap.Modal.getInstance(document.getElementById('viewFoodDonationModal')).hide();
        approveFoodDonation(currentDonationId);
    }
}

function rejectFromModal() {
    if (currentDonationId) {
        bootstrap.Modal.getInstance(document.getElementById('viewFoodDonationModal')).hide();
        rejectFoodDonation(currentDonationId);
    }
}

function updateFilters() {
    const postType = document.getElementById('post_type').value;
    const typeDiv = document.getElementById('type_filter_div');
    
    if (postType === 'food_donations') {
        typeDiv.style.display = 'none';
    } else {
        typeDiv.style.display = 'block';
    }
}

function saveAnnouncement() {
    const id = document.getElementById('announcement_id').value;
    const title = document.getElementById('title').value;
    const content = document.getElementById('content').value;
    const type = document.getElementById('type').value;
    const priority = document.getElementById('priority').value;
    const status = document.getElementById('status').value;
    
    if (!title.trim() || !content.trim()) {
        showNotification('Please fill in all required fields.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', id ? 'update' : 'create');
    if (id) formData.append('id', id);
    formData.append('title', title);
    formData.append('content', content);
    formData.append('type', type);
    formData.append('priority', priority);
    formData.append('status', status);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('announcementModal')).hide();
            location.reload();
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while saving the announcement.', 'error');
    });
}

function publishAnnouncement(id) {
    if (confirm('Are you sure you want to publish this announcement?')) {
        const formData = new FormData();
        formData.append('action', 'publish');
        formData.append('id', id);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                location.reload();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while publishing the announcement.', 'error');
        });
    }
}

function deleteAnnouncement(id) {
    if (confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete');
        formData.append('id', id);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showNotification(data.message, 'success');
                location.reload();
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while deleting the announcement.', 'error');
        });
    }
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
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}
</script>

<?php include 'footer.php'; ?>

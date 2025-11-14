<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

include '../config/db.php';

// Check if announcements table exists
$check_table = $conn->query("SHOW TABLES LIKE 'announcements'");
$table_exists = $check_table && $check_table->num_rows > 0;

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false];

    try {
        if (!$table_exists) {
            $response['message'] = 'Announcements table does not exist. Please run the setup script.';
            echo json_encode($response);
            exit();
        }

        switch ($action) {
            case 'create_announcement':
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $type = $_POST['type'] ?? 'announcement';
                $priority = $_POST['priority'] ?? 'medium';
                $status = $_POST['status'] ?? 'published';
                $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

                if (empty($title) || empty($content)) {
                    $response['message'] = 'Title and content are required.';
                    echo json_encode($response);
                    exit();
                }

                // Handle image uploads
                $uploaded_images = [];
                if (!empty($_FILES['images']['name'][0])) {
                    $upload_dir = '../uploads/announcements/images/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_extension = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

                            if (in_array($file_extension, $allowed_extensions)) {
                                $new_filename = uniqid('announcement_img_', true) . '.' . $file_extension;
                                $upload_path = $upload_dir . $new_filename;

                                if (move_uploaded_file($tmp_name, $upload_path)) {
                                    $uploaded_images[] = 'uploads/announcements/images/' . $new_filename;
                                }
                            }
                        }
                    }
                }

                // Handle file attachments
                $uploaded_attachments = [];
                if (!empty($_FILES['attachments']['name'][0])) {
                    $upload_dir = '../uploads/announcements/files/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_extension = strtolower(pathinfo($_FILES['attachments']['name'][$key], PATHINFO_EXTENSION));
                            $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];

                            if (in_array($file_extension, $allowed_extensions)) {
                                $original_filename = $_FILES['attachments']['name'][$key];
                                $new_filename = uniqid('announcement_file_', true) . '.' . $file_extension;
                                $upload_path = $upload_dir . $new_filename;

                                if (move_uploaded_file($tmp_name, $upload_path)) {
                                    $uploaded_attachments[] = [
                                        'path' => 'uploads/announcements/files/' . $new_filename,
                                        'original_name' => $original_filename,
                                        'size' => $_FILES['attachments']['size'][$key],
                                        'type' => $file_extension
                                    ];
                                }
                            }
                        }
                    }
                }

                $images_json = !empty($uploaded_images) ? json_encode($uploaded_images) : null;
                $attachments_json = !empty($uploaded_attachments) ? json_encode($uploaded_attachments) : null;

                $stmt = $conn->prepare("INSERT INTO announcements (user_id, title, content, type, priority, status, is_pinned, images, attachments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssssiss', $_SESSION['user_id'], $title, $content, $type, $priority, $status, $is_pinned, $images_json, $attachments_json);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Announcement created successfully!';
                    $response['announcement_id'] = $conn->insert_id;
                } else {
                    $response['message'] = 'Failed to create announcement: ' . $stmt->error;
                }
                $stmt->close();
                break;

            case 'update_announcement':
                $id = (int)$_POST['id'];
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $type = $_POST['type'] ?? 'announcement';
                $priority = $_POST['priority'] ?? 'medium';
                $status = $_POST['status'] ?? 'published';
                $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;

                if (empty($title) || empty($content)) {
                    $response['message'] = 'Title and content are required.';
                    echo json_encode($response);
                    exit();
                }

                // Get existing images and attachments
                $stmt = $conn->prepare("SELECT images, attachments FROM announcements WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing = $result->fetch_assoc();
                $stmt->close();

                $existing_images = $existing['images'] ? json_decode($existing['images'], true) : [];
                $existing_attachments = $existing['attachments'] ? json_decode($existing['attachments'], true) : [];

                // Handle removal of existing images
                if (isset($_POST['remove_images'])) {
                    $remove_images = json_decode($_POST['remove_images'], true);
                    if (is_array($remove_images)) {
                        foreach ($remove_images as $img_path) {
                            $file_path = '../' . $img_path;
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                            $existing_images = array_filter($existing_images, function ($img) use ($img_path) {
                                return $img !== $img_path;
                            });
                        }
                        $existing_images = array_values($existing_images);
                    }
                }

                // Handle removal of existing attachments
                if (isset($_POST['remove_attachments'])) {
                    $remove_attachments = json_decode($_POST['remove_attachments'], true);
                    if (is_array($remove_attachments)) {
                        foreach ($remove_attachments as $att_path) {
                            $file_path = '../' . $att_path;
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                            $existing_attachments = array_filter($existing_attachments, function ($att) use ($att_path) {
                                return $att['path'] !== $att_path;
                            });
                        }
                        $existing_attachments = array_values($existing_attachments);
                    }
                }

                // Handle new image uploads
                if (!empty($_FILES['images']['name'][0])) {
                    $upload_dir = '../uploads/announcements/images/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_extension = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

                            if (in_array($file_extension, $allowed_extensions)) {
                                $new_filename = uniqid('announcement_img_', true) . '.' . $file_extension;
                                $upload_path = $upload_dir . $new_filename;

                                if (move_uploaded_file($tmp_name, $upload_path)) {
                                    $existing_images[] = 'uploads/announcements/images/' . $new_filename;
                                }
                            }
                        }
                    }
                }

                // Handle new file attachments
                if (!empty($_FILES['attachments']['name'][0])) {
                    $upload_dir = '../uploads/announcements/files/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }

                    foreach ($_FILES['attachments']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['attachments']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_extension = strtolower(pathinfo($_FILES['attachments']['name'][$key], PATHINFO_EXTENSION));
                            $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];

                            if (in_array($file_extension, $allowed_extensions)) {
                                $original_filename = $_FILES['attachments']['name'][$key];
                                $new_filename = uniqid('announcement_file_', true) . '.' . $file_extension;
                                $upload_path = $upload_dir . $new_filename;

                                if (move_uploaded_file($tmp_name, $upload_path)) {
                                    $existing_attachments[] = [
                                        'path' => 'uploads/announcements/files/' . $new_filename,
                                        'original_name' => $original_filename,
                                        'size' => $_FILES['attachments']['size'][$key],
                                        'type' => $file_extension
                                    ];
                                }
                            }
                        }
                    }
                }

                $images_json = !empty($existing_images) ? json_encode($existing_images) : null;
                $attachments_json = !empty($existing_attachments) ? json_encode($existing_attachments) : null;

                $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, type = ?, priority = ?, status = ?, is_pinned = ?, images = ?, attachments = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param('sssssissi', $title, $content, $type, $priority, $status, $is_pinned, $images_json, $attachments_json, $id);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Announcement updated successfully!';
                } else {
                    $response['message'] = 'Failed to update announcement: ' . $stmt->error;
                }
                $stmt->close();
                break;

            case 'delete_announcement':
                $id = (int)$_POST['id'];

                // Get announcement to delete associated files
                $stmt = $conn->prepare("SELECT images, attachments FROM announcements WHERE id = ?");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();
                $announcement = $result->fetch_assoc();
                $stmt->close();

                // Delete associated images
                if (!empty($announcement['images'])) {
                    $images = json_decode($announcement['images'], true);
                    if (is_array($images)) {
                        foreach ($images as $img_path) {
                            $file_path = '../' . $img_path;
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                    }
                }

                // Delete associated attachments
                if (!empty($announcement['attachments'])) {
                    $attachments = json_decode($announcement['attachments'], true);
                    if (is_array($attachments)) {
                        foreach ($attachments as $att) {
                            $file_path = '../' . $att['path'];
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                    }
                }

                // Delete announcement
                $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
                $stmt->bind_param('i', $id);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Announcement deleted successfully!';
                } else {
                    $response['message'] = 'Failed to delete announcement: ' . $stmt->error;
                }
                $stmt->close();
                break;

            case 'toggle_pin':
                $id = (int)$_POST['id'];
                $is_pinned = (int)$_POST['is_pinned'];

                $stmt = $conn->prepare("UPDATE announcements SET is_pinned = ? WHERE id = ?");
                $stmt->bind_param('ii', $is_pinned, $id);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = $is_pinned ? 'Announcement pinned!' : 'Announcement unpinned!';
                } else {
                    $response['message'] = 'Failed to update pin status: ' . $stmt->error;
                }
                $stmt->close();
                break;

            case 'update_status':
                $id = (int)$_POST['id'];
                $status = $_POST['status'];

                $stmt = $conn->prepare("UPDATE announcements SET status = ?, updated_at = NOW() WHERE id = ?");
                $stmt->bind_param('si', $status, $id);

                if ($stmt->execute()) {
                    $response['success'] = true;
                    $response['message'] = 'Status updated successfully!';
                } else {
                    $response['message'] = 'Failed to update status: ' . $stmt->error;
                }
                $stmt->close();
                break;

            case 'get_announcement':
                $id = (int)$_POST['id'];

                $stmt = $conn->prepare("
                    SELECT a.*, ua.full_name, ua.email, ua.profile_img
                    FROM announcements a
                    JOIN user_accounts ua ON a.user_id = ua.user_id
                    WHERE a.id = ?
                ");
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($announcement = $result->fetch_assoc()) {
                    $response['success'] = true;
                    $response['announcement'] = $announcement;
                    $response['announcement']['images'] = $announcement['images'] ? json_decode($announcement['images'], true) : [];
                    $response['announcement']['attachments'] = $announcement['attachments'] ? json_decode($announcement['attachments'], true) : [];
                } else {
                    $response['message'] = 'Announcement not found.';
                }
                $stmt->close();
                break;
        }
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }

    echo json_encode($response);
    exit();
}

// Get statistics
$stats = [
    'total' => 0,
    'published' => 0,
    'draft' => 0,
    'archived' => 0,
    'pinned' => 0,
    'total_engagement' => 0,
    'today' => 0
];

if ($table_exists) {
    try {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN status = 'published' THEN 1 END) as published,
                COUNT(CASE WHEN status = 'draft' THEN 1 END) as draft,
                COUNT(CASE WHEN status = 'archived' THEN 1 END) as archived,
                COUNT(CASE WHEN is_pinned = 1 THEN 1 END) as pinned,
                COALESCE(SUM(likes_count + comments_count + shares_count), 0) as total_engagement,
                COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today
            FROM announcements
        ");

        if ($result) {
            $stats = $result->fetch_assoc();
        }
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$filter_type = isset($_GET['type']) ? $_GET['type'] : '';
$filter_priority = isset($_GET['priority']) ? $_GET['priority'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query
$announcements = [];
$total_announcements = 0;

if ($table_exists) {
    try {
        // Build WHERE clause
        $where_conditions = [];
        $params = [];
        $types = '';

        if ($filter_status) {
            $where_conditions[] = "a.status = ?";
            $params[] = $filter_status;
            $types .= 's';
        }

        if ($filter_type) {
            $where_conditions[] = "a.type = ?";
            $params[] = $filter_type;
            $types .= 's';
        }

        if ($filter_priority) {
            $where_conditions[] = "a.priority = ?";
            $params[] = $filter_priority;
            $types .= 's';
        }

        if ($search_query) {
            $where_conditions[] = "(a.title LIKE ? OR a.content LIKE ?)";
            $search_param = '%' . $search_query . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'ss';
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count
        $count_query = "SELECT COUNT(*) as total FROM announcements a $where_clause";
        if (!empty($params)) {
            $count_stmt = $conn->prepare($count_query);
            $count_stmt->bind_param($types, ...$params);
            $count_stmt->execute();
            $count_result = $count_stmt->get_result();
            $total_announcements = $count_result->fetch_assoc()['total'];
            $count_stmt->close();
        } else {
            $count_result = $conn->query($count_query);
            $total_announcements = $count_result->fetch_assoc()['total'];
        }

        // Get announcements
        $query = "
            SELECT a.*, ua.full_name, ua.email, ua.profile_img
            FROM announcements a
            JOIN user_accounts ua ON a.user_id = ua.user_id
            $where_clause
            ORDER BY a.is_pinned DESC, a.created_at DESC
            LIMIT ? OFFSET ?
        ";

        // Always add LIMIT and OFFSET parameters (required)
        $params[] = $per_page;
        $params[] = $offset;
        $types .= 'ii';

        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
        } else {
            throw new Exception("Query preparation failed: " . $conn->error);
        }

        while ($row = $result->fetch_assoc()) {
            $row['images'] = $row['images'] ? json_decode($row['images'], true) : [];
            $row['attachments'] = $row['attachments'] ? json_decode($row['attachments'], true) : [];
            $announcements[] = $row;
        }

        $stmt->close();
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Calculate pagination
$total_pages = ceil($total_announcements / $per_page);

include 'header.php';
?>

<body>
    <?php include 'topbar.php'; ?>
    <?php include 'sidebar.php'; ?>

    <main id="main" class="main">
        <div class="pagetitle">
            <h1><i class="bi bi-megaphone"></i> Announcements Management</h1>
            <nav>
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                    <li class="breadcrumb-item active">Announcements</li>
                </ol>
            </nav>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!$table_exists): ?>
            <div class="alert alert-warning">
                <i class="bi bi-exclamation-triangle"></i> Announcements table does not exist. Please run the setup script first.
                <a href="../teamofficer/setup_announcements_db.php" class="btn btn-sm btn-primary ms-2">Run Setup</a>
            </div>
        <?php else: ?>

            <section class="section">
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Announcements</h6>
                                        <h3 class="mb-0"><?= number_format($stats['total']) ?></h3>
                                    </div>
                                    <div class="stat-icon bg-primary">
                                        <i class="bi bi-megaphone"></i>
                                    </div>
                                </div>
                                <small class="text-muted">All announcements</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Published</h6>
                                        <h3 class="mb-0 text-success"><?= number_format($stats['published']) ?></h3>
                                    </div>
                                    <div class="stat-icon bg-success">
                                        <i class="bi bi-check-circle"></i>
                                    </div>
                                </div>
                                <small class="text-muted">Active announcements</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Pinned</h6>
                                        <h3 class="mb-0 text-warning"><?= number_format($stats['pinned']) ?></h3>
                                    </div>
                                    <div class="stat-icon bg-warning">
                                        <i class="bi bi-pin-angle"></i>
                                    </div>
                                </div>
                                <small class="text-muted">Pinned to top</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="text-muted mb-2">Total Engagement</h6>
                                        <h3 class="mb-0 text-info"><?= number_format($stats['total_engagement']) ?></h3>
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

                <div class="row">
                    <!-- Main Content -->
                    <div class="col-lg-12">
                        <div class="card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h5 class="card-title mb-0">All Announcements</h5>
                                    <div class="btn-group">
                                        <button class="btn btn-success" onclick="exportAnnouncements()">
                                            <i class="bi bi-file-earmark-excel"></i> Export
                                        </button>
                                        <button class="btn btn-primary" onclick="showCreateModal()">
                                            <i class="bi bi-plus-circle"></i> Create New
                                        </button>
                                        <button class="btn btn-secondary" onclick="refreshData()">
                                            <i class="bi bi-arrow-clockwise"></i> Refresh
                                        </button>
                                    </div>
                                </div>

                                <!-- Filters and Search -->
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <select class="form-select" id="filterStatus" onchange="applyFilters()">
                                            <option value="">All Status</option>
                                            <option value="published" <?= $filter_status === 'published' ? 'selected' : '' ?>>Published</option>
                                            <option value="draft" <?= $filter_status === 'draft' ? 'selected' : '' ?>>Draft</option>
                                            <option value="archived" <?= $filter_status === 'archived' ? 'selected' : '' ?>>Archived</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" id="filterType" onchange="applyFilters()">
                                            <option value="">All Types</option>
                                            <option value="announcement" <?= $filter_type === 'announcement' ? 'selected' : '' ?>>Announcement</option>
                                            <option value="guideline" <?= $filter_type === 'guideline' ? 'selected' : '' ?>>Guideline</option>
                                            <option value="reminder" <?= $filter_type === 'reminder' ? 'selected' : '' ?>>Reminder</option>
                                            <option value="alert" <?= $filter_type === 'alert' ? 'selected' : '' ?>>Alert</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <select class="form-select" id="filterPriority" onchange="applyFilters()">
                                            <option value="">All Priorities</option>
                                            <option value="low" <?= $filter_priority === 'low' ? 'selected' : '' ?>>Low</option>
                                            <option value="medium" <?= $filter_priority === 'medium' ? 'selected' : '' ?>>Medium</option>
                                            <option value="high" <?= $filter_priority === 'high' ? 'selected' : '' ?>>High</option>
                                            <option value="critical" <?= $filter_priority === 'critical' ? 'selected' : '' ?>>Critical</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <input type="text" class="form-control" id="searchInput"
                                            placeholder="Search announcements..."
                                            value="<?= htmlspecialchars($search_query) ?>"
                                            onkeyup="handleSearch(event)">
                                    </div>
                                </div>

                                <!-- Announcements List -->
                                <div class="announcements-container">
                                    <?php if (!empty($announcements)): ?>
                                        <?php foreach ($announcements as $announcement): ?>
                                            <div class="announcement-card mb-3 announcement-row"
                                                data-id="<?= $announcement['id'] ?>"
                                                data-status="<?= $announcement['status'] ?>"
                                                data-type="<?= $announcement['type'] ?>"
                                                data-priority="<?= $announcement['priority'] ?>">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <div class="d-flex align-items-start">
                                                            <?php if ($announcement['is_pinned']): ?>
                                                                <div class="me-2">
                                                                    <i class="bi bi-pin-angle-fill text-warning" style="font-size: 1.5rem;"></i>
                                                                </div>
                                                            <?php endif; ?>

                                                            <div class="flex-grow-1">
                                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                                    <div>
                                                                        <h5 class="mb-1">
                                                                            <?= htmlspecialchars($announcement['title']) ?>
                                                                            <?php if ($announcement['is_pinned']): ?>
                                                                                <span class="badge bg-warning">Pinned</span>
                                                                            <?php endif; ?>
                                                                        </h5>
                                                                        <div class="mb-2">
                                                                            <span class="badge bg-<?=
                                                                                                    $announcement['status'] === 'published' ? 'success' : ($announcement['status'] === 'draft' ? 'secondary' : 'dark')
                                                                                                    ?>">
                                                                                <?= ucfirst($announcement['status']) ?>
                                                                            </span>
                                                                            <span class="badge bg-<?=
                                                                                                    $announcement['type'] === 'announcement' ? 'primary' : ($announcement['type'] === 'guideline' ? 'info' : ($announcement['type'] === 'reminder' ? 'warning' : 'danger'))
                                                                                                    ?>">
                                                                                <?= ucfirst($announcement['type']) ?>
                                                                            </span>
                                                                            <span class="badge bg-<?=
                                                                                                    $announcement['priority'] === 'critical' ? 'danger' : ($announcement['priority'] === 'high' ? 'warning' : ($announcement['priority'] === 'medium' ? 'info' : 'secondary'))
                                                                                                    ?>">
                                                                                <?= ucfirst($announcement['priority']) ?> Priority
                                                                            </span>
                                                                        </div>
                                                                    </div>
                                                                    <div class="text-end">
                                                                        <small class="text-muted">
                                                                            <?= date('M j, Y g:i A', strtotime($announcement['created_at'])) ?>
                                                                        </small>
                                                                        <div class="mt-1">
                                                                            <small class="text-muted">By: <?= htmlspecialchars($announcement['full_name']) ?></small>
                                                                        </div>
                                                                    </div>
                                                                </div>

                                                                <p class="text-muted mb-2">
                                                                    <?= htmlspecialchars(substr($announcement['content'], 0, 200)) ?><?= strlen($announcement['content']) > 200 ? '...' : '' ?>
                                                                </p>

                                                                <?php if (!empty($announcement['images'])): ?>
                                                                    <div class="mb-2">
                                                                        <i class="bi bi-image"></i> <?= count($announcement['images']) ?> image(s)
                                                                    </div>
                                                                <?php endif; ?>

                                                                <?php if (!empty($announcement['attachments'])): ?>
                                                                    <div class="mb-2">
                                                                        <i class="bi bi-paperclip"></i> <?= count($announcement['attachments']) ?> attachment(s)
                                                                    </div>
                                                                <?php endif; ?>

                                                                <div class="engagement-stats mb-2">
                                                                    <span class="me-3" title="Likes">
                                                                        <i class="bi bi-heart-fill text-danger"></i> <?= number_format($announcement['likes_count']) ?>
                                                                    </span>
                                                                    <span class="me-3" title="Comments">
                                                                        <i class="bi bi-chat-fill text-primary"></i> <?= number_format($announcement['comments_count']) ?>
                                                                    </span>
                                                                    <span class="me-3" title="Shares">
                                                                        <i class="bi bi-share-fill text-success"></i> <?= number_format($announcement['shares_count']) ?>
                                                                    </span>
                                                                </div>

                                                                <div class="post-actions">
                                                                    <button class="btn btn-sm btn-outline-info me-1"
                                                                        onclick='viewAnnouncement(<?= $announcement['id'] ?>)'>
                                                                        <i class="bi bi-eye"></i> View
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-primary me-1"
                                                                        onclick='editAnnouncement(<?= $announcement['id'] ?>)'>
                                                                        <i class="bi bi-pencil"></i> Edit
                                                                    </button>
                                                                    <button class="btn btn-sm btn-outline-<?= $announcement['is_pinned'] ? 'warning' : 'secondary' ?> me-1"
                                                                        onclick="togglePin(<?= $announcement['id'] ?>, <?= $announcement['is_pinned'] ? 0 : 1 ?>)">
                                                                        <i class="bi bi-pin-angle"></i>
                                                                        <?= $announcement['is_pinned'] ? 'Unpin' : 'Pin' ?>
                                                                    </button>
                                                                    <div class="btn-group me-1">
                                                                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
                                                                            data-bs-toggle="dropdown">
                                                                            Status
                                                                        </button>
                                                                        <ul class="dropdown-menu">
                                                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $announcement['id'] ?>, 'published'); return false;">Published</a></li>
                                                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $announcement['id'] ?>, 'draft'); return false;">Draft</a></li>
                                                                            <li><a class="dropdown-item" href="#" onclick="updateStatus(<?= $announcement['id'] ?>, 'archived'); return false;">Archived</a></li>
                                                                        </ul>
                                                                    </div>
                                                                    <button class="btn btn-sm btn-outline-danger"
                                                                        onclick="deleteAnnouncement(<?= $announcement['id'] ?>)">
                                                                        <i class="bi bi-trash"></i> Delete
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="alert alert-info text-center">
                                            <i class="bi bi-info-circle" style="font-size: 3rem;"></i>
                                            <p class="mt-3 mb-0">No announcements found</p>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Pagination -->
                                <?php if ($total_pages > 1): ?>
                                    <nav aria-label="Page navigation">
                                        <ul class="pagination justify-content-center">
                                            <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $filter_status ?>&type=<?= $filter_type ?>&priority=<?= $filter_priority ?>&search=<?= urlencode($search_query) ?>">Previous</a>
                                            </li>
                                            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                                    <a class="page-link" href="?page=<?= $i ?>&status=<?= $filter_status ?>&type=<?= $filter_type ?>&priority=<?= $filter_priority ?>&search=<?= urlencode($search_query) ?>"><?= $i ?></a>
                                                </li>
                                            <?php endfor; ?>
                                            <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                                <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $filter_status ?>&type=<?= $filter_type ?>&priority=<?= $filter_priority ?>&search=<?= urlencode($search_query) ?>">Next</a>
                                            </li>
                                        </ul>
                                    </nav>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

        <?php endif; ?>
    </main>

    <!-- Create/Edit Announcement Modal -->
    <div class="modal fade" id="announcementModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle"><i class="bi bi-megaphone"></i> Create Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="announcementForm" enctype="multipart/form-data">
                        <input type="hidden" id="announcementId" name="id">
                        <input type="hidden" id="actionType" name="action" value="create_announcement">

                        <div class="mb-3">
                            <label for="title" class="form-label">Title *</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>

                        <div class="mb-3">
                            <label for="content" class="form-label">Content *</label>
                            <textarea class="form-control" id="content" name="content" rows="6" required></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="type" class="form-label">Type</label>
                                <select class="form-select" id="type" name="type">
                                    <option value="announcement">Announcement</option>
                                    <option value="guideline">Guideline</option>
                                    <option value="reminder">Reminder</option>
                                    <option value="alert">Alert</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="priority" class="form-label">Priority</label>
                                <select class="form-select" id="priority" name="priority">
                                    <option value="low">Low</option>
                                    <option value="medium" selected>Medium</option>
                                    <option value="high">High</option>
                                    <option value="critical">Critical</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="status" class="form-label">Status</label>
                                <select class="form-select" id="status" name="status">
                                    <option value="draft">Draft</option>
                                    <option value="published" selected>Published</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_pinned" name="is_pinned">
                                <label class="form-check-label" for="is_pinned">
                                    Pin to top
                                </label>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="images" class="form-label">Images</label>
                            <input type="file" class="form-control" id="images" name="images[]" multiple
                                accept="image/*">
                            <small class="text-muted">You can select multiple images</small>
                            <div id="imagePreview" class="mt-2"></div>
                            <div id="existingImages" class="mt-2"></div>
                        </div>

                        <div class="mb-3">
                            <label for="attachments" class="form-label">Attachments</label>
                            <input type="file" class="form-control" id="attachments" name="attachments[]" multiple
                                accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                            <small class="text-muted">PDF, DOC, XLS, PPT, TXT, ZIP, RAR files</small>
                            <div id="attachmentPreview" class="mt-2"></div>
                            <div id="existingAttachments" class="mt-2"></div>
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
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-eye"></i> Announcement Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="viewContent">
                    <!-- Content loaded dynamically -->
                </div>
            </div>
        </div>
    </div>

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
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .announcement-card {
            border: 1px solid #dee2e6;
            border-radius: 8px;
            transition: box-shadow 0.2s;
        }

        .announcement-card:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .engagement-stats {
            font-size: 0.9rem;
        }

        .post-actions {
            margin-top: 10px;
        }

        .image-preview-item {
            position: relative;
            display: inline-block;
            margin: 5px;
        }

        .image-preview-item img {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 4px;
        }

        .image-preview-item .remove-btn {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>

    <script>
        let announcementModal = null;
        let viewModal = null;
        let removedImages = [];
        let removedAttachments = [];

        document.addEventListener('DOMContentLoaded', function() {
            announcementModal = new bootstrap.Modal(document.getElementById('announcementModal'));
            viewModal = new bootstrap.Modal(document.getElementById('viewModal'));

            // Handle image preview
            const imagesInput = document.getElementById('images');
            if (imagesInput) {
                imagesInput.addEventListener('change', function(e) {
                    const preview = document.getElementById('imagePreview');
                    preview.innerHTML = '';

                    Array.from(e.target.files).forEach(file => {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const div = document.createElement('div');
                            div.className = 'image-preview-item';
                            div.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                            preview.appendChild(div);
                        };
                        reader.readAsDataURL(file);
                    });
                });
            }
        });

        // Apply Filters
        function applyFilters() {
            const status = document.getElementById('filterStatus').value;
            const type = document.getElementById('filterType').value;
            const priority = document.getElementById('filterPriority').value;
            const search = document.getElementById('searchInput').value;

            const params = new URLSearchParams();
            if (status) params.append('status', status);
            if (type) params.append('type', type);
            if (priority) params.append('priority', priority);
            if (search) params.append('search', search);

            window.location.href = 'admin-announcements.php?' + params.toString();
        }

        // Handle Search
        let searchTimeout;

        function handleSearch(event) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                if (event.key === 'Enter' || event.target.value.length === 0) {
                    applyFilters();
                }
            }, 500);
        }

        // Show Create Modal
        function showCreateModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="bi bi-megaphone"></i> Create Announcement';
            document.getElementById('actionType').value = 'create_announcement';
            document.getElementById('announcementForm').reset();
            document.getElementById('announcementId').value = '';
            document.getElementById('imagePreview').innerHTML = '';
            document.getElementById('existingImages').innerHTML = '';
            document.getElementById('attachmentPreview').innerHTML = '';
            document.getElementById('existingAttachments').innerHTML = '';
            removedImages = [];
            removedAttachments = [];
            announcementModal.show();
        }

        // View Announcement
        function viewAnnouncement(id) {
            const formData = new FormData();
            formData.append('action', 'get_announcement');
            formData.append('id', id);

            fetch('admin-announcements.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const ann = data.announcement;
                        let content = `
                <div class="row">
                    <div class="col-12 mb-3">
                        <div class="d-flex align-items-center">
                            <img src="${ann.profile_img ? '../uploads/profile_picture/' + ann.profile_img : '../uploads/profile_picture/no_image.png'}" 
                                 class="rounded-circle me-3" width="48" height="48">
                            <div>
                                <h6 class="mb-0">${ann.full_name}</h6>
                                <small class="text-muted">${ann.email}</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Status:</strong> <span class="badge bg-${ann.status === 'published' ? 'success' : (ann.status === 'draft' ? 'secondary' : 'dark')}">${ann.status.toUpperCase()}</span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Type:</strong> <span class="badge bg-primary">${ann.type.toUpperCase()}</span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Priority:</strong> <span class="badge bg-${ann.priority === 'critical' ? 'danger' : (ann.priority === 'high' ? 'warning' : (ann.priority === 'medium' ? 'info' : 'secondary'))}">${ann.priority.toUpperCase()}</span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Pinned:</strong> ${ann.is_pinned ? '<span class="badge bg-warning">YES</span>' : '<span class="badge bg-secondary">NO</span>'}
                    </div>
                    <div class="col-12 mb-3">
                        <strong>Title:</strong>
                        <div class="alert alert-light mt-2">${ann.title}</div>
                    </div>
                    <div class="col-12 mb-3">
                        <strong>Content:</strong>
                        <div class="alert alert-light mt-2">${ann.content.replace(/\n/g, '<br>')}</div>
                    </div>
            `;

                        if (ann.images && ann.images.length > 0) {
                            content += '<div class="col-12 mb-3"><strong>Images:</strong><div class="mt-2">';
                            ann.images.forEach(img => {
                                content += `<img src="../${img}" class="img-thumbnail me-2 mb-2" style="max-width: 200px; max-height: 200px;">`;
                            });
                            content += '</div></div>';
                        }

                        if (ann.attachments && ann.attachments.length > 0) {
                            content += '<div class="col-12 mb-3"><strong>Attachments:</strong><ul class="mt-2">';
                            ann.attachments.forEach(att => {
                                content += `<li><a href="../${att.path}" target="_blank">${att.original_name}</a> (${formatFileSize(att.size)})</li>`;
                            });
                            content += '</ul></div>';
                        }

                        content += `
                    <div class="col-12 mb-3">
                        <strong>Engagement:</strong><br>
                        <span class="me-3"><i class="bi bi-heart-fill text-danger"></i> ${ann.likes_count} Likes</span>
                        <span class="me-3"><i class="bi bi-chat-fill text-primary"></i> ${ann.comments_count} Comments</span>
                        <span class="me-3"><i class="bi bi-share-fill text-success"></i> ${ann.shares_count} Shares</span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Created:</strong> ${new Date(ann.created_at).toLocaleString()}
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Updated:</strong> ${new Date(ann.updated_at).toLocaleString()}
                    </div>
                </div>
            `;

                        document.getElementById('viewContent').innerHTML = content;
                        viewModal.show();
                    } else {
                        showNotification(data.message || 'Failed to load announcement', 'error');
                    }
                });
        }

        // Edit Announcement
        function editAnnouncement(id) {
            const formData = new FormData();
            formData.append('action', 'get_announcement');
            formData.append('id', id);

            fetch('admin-announcements.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const ann = data.announcement;
                        document.getElementById('modalTitle').innerHTML = '<i class="bi bi-pencil"></i> Edit Announcement';
                        document.getElementById('actionType').value = 'update_announcement';
                        document.getElementById('announcementId').value = ann.id;
                        document.getElementById('title').value = ann.title;
                        document.getElementById('content').value = ann.content;
                        document.getElementById('type').value = ann.type;
                        document.getElementById('priority').value = ann.priority;
                        document.getElementById('status').value = ann.status;
                        document.getElementById('is_pinned').checked = ann.is_pinned == 1;

                        // Display existing images
                        const existingImagesDiv = document.getElementById('existingImages');
                        existingImagesDiv.innerHTML = '';
                        removedImages = [];
                        if (ann.images && ann.images.length > 0) {
                            ann.images.forEach((img) => {
                                const div = document.createElement('div');
                                div.className = 'image-preview-item';
                                div.innerHTML = `
                        <img src="../${img}" alt="Existing">
                        <button type="button" class="remove-btn" onclick="removeExistingImage('${img}', this)">×</button>
                    `;
                                existingImagesDiv.appendChild(div);
                            });
                        }

                        // Display existing attachments
                        const existingAttachmentsDiv = document.getElementById('existingAttachments');
                        existingAttachmentsDiv.innerHTML = '';
                        removedAttachments = [];
                        if (ann.attachments && ann.attachments.length > 0) {
                            ann.attachments.forEach(att => {
                                const div = document.createElement('div');
                                div.className = 'mb-2';
                                div.innerHTML = `
                        <a href="../${att.path}" target="_blank">${att.original_name}</a>
                        <button type="button" class="btn btn-sm btn-danger ms-2" onclick="removeExistingAttachment('${att.path}', this)">Remove</button>
                    `;
                                existingAttachmentsDiv.appendChild(div);
                            });
                        }

                        announcementModal.show();
                    } else {
                        showNotification(data.message || 'Failed to load announcement', 'error');
                    }
                });
        }

        // Remove existing image
        function removeExistingImage(imgPath, button) {
            removedImages.push(imgPath);
            button.closest('.image-preview-item').remove();
        }

        // Remove existing attachment
        function removeExistingAttachment(attPath, button) {
            removedAttachments.push(attPath);
            button.closest('div').remove();
        }

        // Save Announcement
        function saveAnnouncement() {
            const form = document.getElementById('announcementForm');
            const formData = new FormData(form);

            const action = document.getElementById('actionType').value;
            formData.append('action', action);

            if (action === 'update_announcement') {
                if (removedImages.length > 0) {
                    formData.append('remove_images', JSON.stringify(removedImages));
                }
                if (removedAttachments.length > 0) {
                    formData.append('remove_attachments', JSON.stringify(removedAttachments));
                }
            }

            // Show loading indicator
            const saveBtn = document.querySelector('#announcementModal .btn-primary');
            const originalText = saveBtn.innerHTML;
            saveBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
            saveBtn.disabled = true;

            fetch('admin-announcements.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;

                    if (data.success) {
                        showNotification(data.message, 'success');
                        announcementModal.hide();
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showNotification(data.message || 'Failed to save announcement', 'error');
                    }
                })
                .catch(error => {
                    saveBtn.innerHTML = originalText;
                    saveBtn.disabled = false;
                    showNotification('Network error: ' + error.message, 'error');
                });
        }

        // Delete Announcement
        function deleteAnnouncement(id) {
            if (!confirm('Are you sure you want to delete this announcement? This action cannot be undone!')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'delete_announcement');
            formData.append('id', id);

            fetch('admin-announcements.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showNotification(data.message || 'Failed to delete announcement', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Network error: ' + error.message, 'error');
                });
        }

        // Toggle Pin
        function togglePin(id, isPinned) {
            const formData = new FormData();
            formData.append('action', 'toggle_pin');
            formData.append('id', id);
            formData.append('is_pinned', isPinned);

            fetch('admin-announcements.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showNotification(data.message || 'Failed to update pin status', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Network error: ' + error.message, 'error');
                });
        }

        // Update Status
        function updateStatus(id, status) {
            const formData = new FormData();
            formData.append('action', 'update_status');
            formData.append('id', id);
            formData.append('status', status);

            fetch('admin-announcements.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message, 'success');
                        setTimeout(() => window.location.reload(), 1000);
                    } else {
                        showNotification(data.message || 'Failed to update status', 'error');
                    }
                })
                .catch(error => {
                    showNotification('Network error: ' + error.message, 'error');
                });
        }

        // Export Announcements
        function exportAnnouncements() {
            const data = <?= json_encode($announcements) ?>;

            if (data.length === 0) {
                alert('No data to export');
                return;
            }

            const headers = ['ID', 'Title', 'Type', 'Priority', 'Status', 'Pinned', 'Author', 'Likes', 'Comments', 'Shares', 'Created Date'];
            let csv = headers.join(',') + '\n';

            data.forEach(row => {
                const values = [
                    row.id,
                    `"${row.title.replace(/"/g, '""')}"`,
                    row.type,
                    row.priority,
                    row.status,
                    row.is_pinned ? 'Yes' : 'No',
                    `"${row.full_name.replace(/"/g, '""')}"`,
                    row.likes_count,
                    row.comments_count,
                    row.shares_count,
                    row.created_at
                ];
                csv += values.join(',') + '\n';
            });

            const blob = new Blob([csv], {
                type: 'text/csv;charset=utf-8;'
            });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'announcements_' + new Date().toISOString().split('T')[0] + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            showNotification('Announcements exported successfully!', 'success');
        }

        // Refresh Data
        function refreshData() {
            window.location.reload();
        }

        // Format File Size
        function formatFileSize(bytes) {
            if (!bytes || bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
        }

        // Notification System
        function showNotification(message, type = 'info') {
            const alertClass = type === 'success' ? 'alert-success' :
                type === 'error' ? 'alert-danger' :
                type === 'warning' ? 'alert-warning' : 'alert-info';

            const iconClass = type === 'success' ? 'check-circle' :
                type === 'error' ? 'exclamation-triangle' :
                type === 'warning' ? 'exclamation-triangle' : 'info-circle';

            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification-toast');
            existingNotifications.forEach(n => n.remove());

            const notification = document.createElement('div');
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed notification-toast`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);';
            notification.innerHTML = `
        <i class="bi bi-${iconClass} me-2"></i>
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `;

            document.body.appendChild(notification);

            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.classList.remove('show');
                    setTimeout(() => notification.remove(), 300);
                }
            }, 5000);
        }

        // Handle modal cleanup on close
        document.addEventListener('DOMContentLoaded', function() {
            const modalElement = document.getElementById('announcementModal');
            if (modalElement) {
                modalElement.addEventListener('hidden.bs.modal', function() {
                    // Reset form and cleanup
                    document.getElementById('announcementForm').reset();
                    document.getElementById('imagePreview').innerHTML = '';
                    document.getElementById('existingImages').innerHTML = '';
                    document.getElementById('attachmentPreview').innerHTML = '';
                    document.getElementById('existingAttachments').innerHTML = '';
                    removedImages = [];
                    removedAttachments = [];
                });
            }
        });
    </script>

    <?php include 'footer.php'; ?>
</body>

</html>
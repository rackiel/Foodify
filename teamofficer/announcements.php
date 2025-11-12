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

// Simple table checks - if setup is needed, show message
$tables_needed = ['announcements', 'announcement_likes', 'announcement_comments', 'announcement_shares', 'announcement_saves'];
$tables_exist = true;
$missing_tables = [];

foreach ($tables_needed as $table) {
    $check = $conn->query("SHOW TABLES LIKE '$table'");
    if ($check->num_rows == 0) {
        $tables_exist = false;
        $missing_tables[] = $table;
    }
}

// If tables don't exist, show setup message
if (!$tables_exist) {
    echo "<!DOCTYPE html><html><head><title>Setup Required</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css' rel='stylesheet'>
    </head><body>
    <div class='container mt-5'>
        <div class='alert alert-warning'>
            <h4><i class='bi bi-exclamation-triangle'></i> Database Setup Required</h4>
            <p>The announcements system tables need to be set up. Please run the setup script first.</p>
            <p><strong>Missing tables:</strong> " . implode(', ', $missing_tables) . "</p>
            <a href='setup_announcements_db.php' class='btn btn-primary'>Run Database Setup</a>
        </div>
    </div></body></html>";
    exit;
}

// Create tables with simple CREATE IF NOT EXISTS (won't fail if exists)
$conn->query("CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('announcement', 'guideline', 'reminder', 'alert') DEFAULT 'announcement',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    is_pinned TINYINT(1) DEFAULT 0,
    images JSON,
    attachments JSON,
    likes_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS announcement_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    post_type ENUM('announcement', 'food_donation') NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (post_id, post_type, user_id),
    INDEX idx_post (post_id, post_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS announcement_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    post_type ENUM('announcement', 'food_donation') NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post (post_id, post_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS announcement_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    post_type ENUM('announcement', 'food_donation') NOT NULL,
    user_id INT NOT NULL,
    share_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post (post_id, post_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS announcement_saves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    post_type ENUM('announcement', 'food_donation') NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_save (post_id, post_type, user_id),
    INDEX idx_post (post_id, post_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Handle AJAX requests BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_announcement':
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $type = $_POST['type'] ?? 'announcement';
                $priority = $_POST['priority'] ?? 'medium';
                $status = $_POST['status'] ?? 'published';
                $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
                
                if (empty($title) || empty($content)) {
                    echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
                    exit;
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
                
                try {
                    $images_json = !empty($uploaded_images) ? json_encode($uploaded_images) : null;
                    $attachments_json = !empty($uploaded_attachments) ? json_encode($uploaded_attachments) : null;
                    
                    $stmt = $conn->prepare("INSERT INTO announcements (user_id, title, content, type, priority, status, is_pinned, images, attachments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param('isssssiss', $_SESSION['user_id'], $title, $content, $type, $priority, $status, $is_pinned, $images_json, $attachments_json);
                
                if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Announcement created successfully!', 'announcement_id' => $conn->insert_id]);
                } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to create announcement: ' . $stmt->error]);
                }
                $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'update_announcement':
                $id = (int)$_POST['id'];
                $title = trim($_POST['title'] ?? '');
                $content = trim($_POST['content'] ?? '');
                $type = $_POST['type'] ?? 'announcement';
                $priority = $_POST['priority'] ?? 'medium';
                $status = $_POST['status'] ?? 'published';
                $is_pinned = isset($_POST['is_pinned']) ? 1 : 0;
                
                if (empty($title) || empty($content)) {
                    echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
                    exit;
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
                
                try {
                    $images_json = !empty($existing_images) ? json_encode($existing_images) : null;
                    $attachments_json = !empty($existing_attachments) ? json_encode($existing_attachments) : null;
                    
                    $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ?, type = ?, priority = ?, status = ?, is_pinned = ?, images = ?, attachments = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->bind_param('sssssissi', $title, $content, $type, $priority, $status, $is_pinned, $images_json, $attachments_json, $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Announcement updated successfully!']);
                } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to update announcement: ' . $stmt->error]);
                }
                $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'delete_announcement':
                $id = (int)$_POST['id'];
                
                try {
                    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
                    $stmt->bind_param('i', $id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete announcement.']);
                }
                $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'toggle_like':
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'announcement';
                
                try {
                    $stmt = $conn->prepare("SELECT id FROM announcement_likes WHERE post_id = ? AND post_type = ? AND user_id = ?");
                    $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Unlike
                        $stmt->close();
                        $stmt = $conn->prepare("DELETE FROM announcement_likes WHERE post_id = ? AND post_type = ? AND user_id = ?");
                        $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                        $stmt->execute();
                        
                        // Update likes count
                        if ($post_type === 'announcement') {
                            $stmt->close();
                            $stmt = $conn->prepare("UPDATE announcements SET likes_count = likes_count - 1 WHERE id = ?");
                            $stmt->bind_param('i', $post_id);
                            $stmt->execute();
                        }
                        
                        echo json_encode(['success' => true, 'liked' => false, 'message' => 'Post unliked']);
                } else {
                        // Like
                        $stmt->close();
                        $stmt = $conn->prepare("INSERT INTO announcement_likes (post_id, post_type, user_id) VALUES (?, ?, ?)");
                        $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                        $stmt->execute();
                        
                        // Update likes count
                        if ($post_type === 'announcement') {
                            $stmt->close();
                            $stmt = $conn->prepare("UPDATE announcements SET likes_count = likes_count + 1 WHERE id = ?");
                            $stmt->bind_param('i', $post_id);
                            $stmt->execute();
                        }
                        
                        echo json_encode(['success' => true, 'liked' => true, 'message' => 'Post liked!']);
                }
                $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'add_comment':
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'announcement';
                $comment = trim($_POST['comment'] ?? '');
                
                if (empty($comment)) {
                    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
                    exit;
                }
                
                try {
                    $stmt = $conn->prepare("INSERT INTO announcement_comments (post_id, post_type, user_id, comment) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('isis', $post_id, $post_type, $_SESSION['user_id'], $comment);
                    $stmt->execute();
                    
                    // Update comments count
                    if ($post_type === 'announcement') {
                        $stmt->close();
                        $stmt = $conn->prepare("UPDATE announcements SET comments_count = comments_count + 1 WHERE id = ?");
                        $stmt->bind_param('i', $post_id);
                        $stmt->execute();
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Comment added successfully!']);
                    $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
                
            case 'get_comments':
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'announcement';
                $page = (int)($_POST['page'] ?? 1);
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                try {
                    // Get total count
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM announcement_comments WHERE post_id = ? AND post_type = ?");
                    $stmt->bind_param('is', $post_id, $post_type);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $total_count = $result->fetch_assoc()['total'];
                    $stmt->close();
                    
                    // Get comments with pagination
                    $stmt = $conn->prepare("
                        SELECT c.*, u.full_name, u.profile_img 
                        FROM announcement_comments c 
                        JOIN user_accounts u ON c.user_id = u.user_id 
                        WHERE c.post_id = ? AND c.post_type = ? 
                        ORDER BY c.created_at DESC
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->bind_param('isii', $post_id, $post_type, $limit, $offset);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $comments = [];
                    while ($comment = $result->fetch_assoc()) {
                        $comments[] = [
                            'id' => $comment['id'],
                            'comment' => $comment['comment'],
                            'user_name' => $comment['full_name'],
                            'profile_img' => $comment['profile_img'],
                            'created_at' => $comment['created_at'],
                            'is_own_comment' => $comment['user_id'] == $_SESSION['user_id']
                        ];
                    }
                    
                    $has_more = ($offset + $limit) < $total_count;
                    
                    echo json_encode([
                        'success' => true, 
                        'comments' => $comments,
                        'total_count' => $total_count,
                        'current_page' => $page,
                        'has_more' => $has_more
                    ]);
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'share_post':
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'announcement';
                $share_message = trim($_POST['share_message'] ?? '');
                
                try {
                    $stmt = $conn->prepare("INSERT INTO announcement_shares (post_id, post_type, user_id, share_message) VALUES (?, ?, ?, ?)");
                    $stmt->bind_param('isis', $post_id, $post_type, $_SESSION['user_id'], $share_message);
                    $stmt->execute();
                    
                    // Update shares count
                    if ($post_type === 'announcement') {
                        $stmt->close();
                        $stmt = $conn->prepare("UPDATE announcements SET shares_count = shares_count + 1 WHERE id = ?");
                        $stmt->bind_param('i', $post_id);
                        $stmt->execute();
                    }
                    
                    echo json_encode(['success' => true, 'message' => 'Post shared successfully!']);
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'save_post':
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'announcement';
                
                try {
                    $stmt = $conn->prepare("SELECT id FROM announcement_saves WHERE post_id = ? AND post_type = ? AND user_id = ?");
                    $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Remove from saved
                        $stmt->close();
                        $stmt = $conn->prepare("DELETE FROM announcement_saves WHERE post_id = ? AND post_type = ? AND user_id = ?");
                        $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                        $stmt->execute();
                        echo json_encode(['success' => true, 'saved' => false, 'message' => 'Post removed from saved']);
                    } else {
                        // Add to saved
                        $stmt->close();
                        $stmt = $conn->prepare("INSERT INTO announcement_saves (post_id, post_type, user_id) VALUES (?, ?, ?)");
                        $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                        $stmt->execute();
                        echo json_encode(['success' => true, 'saved' => true, 'message' => 'Post saved successfully!']);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'get_post_details':
                $id = (int)$_POST['id'];
                
                try {
                    $stmt = $conn->prepare("
                        SELECT a.*, u.full_name, u.email, u.profile_img
    FROM announcements a 
                        JOIN user_accounts u ON a.user_id = u.user_id
                        WHERE a.id = ?
                    ");
                    $stmt->bind_param('i', $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($row = $result->fetch_assoc()) {
                        echo json_encode(['success' => true, 'data' => $row]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Announcement not found.']);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'load_posts':
                $announcement_type = $_POST['filter_type'] ?? 'all';
                $page = (int)($_POST['page'] ?? 1);
                $limit = 50;
                $offset = ($page - 1) * $limit;
                
                $posts = [];
                
                try {
                    // Build WHERE clause based on filter
                    $where_clause = "a.status != 'archived'";
                    if ($announcement_type !== 'all') {
                        $where_clause .= " AND a.type = '" . $conn->real_escape_string($announcement_type) . "'";
                    }
                    
                    $stmt = $conn->prepare("
                        SELECT a.*, u.full_name as username, u.profile_img, 'announcement' as post_type,
                        CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END as is_liked,
                        CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as is_saved
                        FROM announcements a
                        LEFT JOIN user_accounts u ON a.user_id = u.user_id
                        LEFT JOIN announcement_likes l ON a.id = l.post_id AND l.post_type = 'announcement' AND l.user_id = ?
                        LEFT JOIN announcement_saves s ON a.id = s.post_id AND s.post_type = 'announcement' AND s.user_id = ?
                        WHERE $where_clause
                        ORDER BY a.is_pinned DESC, a.created_at DESC
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->bind_param('iiii', $_SESSION['user_id'], $_SESSION['user_id'], $limit, $offset);
$stmt->execute();
$result = $stmt->get_result();
                    
                    while ($post = $result->fetch_assoc()) {
                        $posts[] = $post;
                    }
$stmt->close();
                    
                    echo json_encode(['success' => true, 'data' => $posts, 'filter' => $announcement_type]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
        }
    }
}

// Get posts for initial display (announcements only)
$posts = [];
try {
    // Get all announcements (all types)
    $stmt = $conn->prepare("
        SELECT a.*, u.full_name as username, u.profile_img, 'announcement' as post_type
        FROM announcements a
        LEFT JOIN user_accounts u ON a.user_id = u.user_id
        WHERE a.status != 'archived'
        ORDER BY a.is_pinned DESC, a.created_at DESC
        LIMIT 50
    ");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($post = $result->fetch_assoc()) {
        // Check if user liked this post
        $like_check = $conn->prepare("SELECT id FROM announcement_likes WHERE post_id = ? AND post_type = 'announcement' AND user_id = ?");
        $like_check->bind_param('ii', $post['id'], $_SESSION['user_id']);
        $like_check->execute();
        $post['is_liked'] = $like_check->get_result()->num_rows > 0 ? 1 : 0;
        $like_check->close();
        
        // Check if user saved this post
        $save_check = $conn->prepare("SELECT id FROM announcement_saves WHERE post_id = ? AND post_type = 'announcement' AND user_id = ?");
        $save_check->bind_param('ii', $post['id'], $_SESSION['user_id']);
        $save_check->execute();
        $post['is_saved'] = $save_check->get_result()->num_rows > 0 ? 1 : 0;
        $save_check->close();
        
        // Check if user shared this post
        $share_check = $conn->prepare("SELECT id FROM announcement_shares WHERE post_id = ? AND post_type = 'announcement' AND user_id = ?");
        $share_check->bind_param('ii', $post['id'], $_SESSION['user_id']);
        $share_check->execute();
        $post['is_shared'] = $share_check->get_result()->num_rows > 0 ? 1 : 0;
        $share_check->close();
        
        $posts[] = $post;
    }
    $stmt->close();
    
} catch (Exception $e) {
    // Handle error silently
    $posts = [];
}

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
<div class="container py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-megaphone"></i> Announcements, Reminders & Guidelines</h2>
                    <p class="text-muted mb-0">Create and manage community announcements, important reminders, guidelines, and alerts</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                    <i class="bi bi-plus-circle"></i> Create Announcement
                </button>
            </div>
        </div>
    </div>

    <!-- View Toggle and Filter Tabs -->
        <div class="row mb-4">
        <div class="col-md-12">
            <div class="facebook-tabs">
                <div class="tab-item active" id="all-tab" data-filter="all">
                    <i class="bi bi-grid"></i>
                    <span>All</span>
                </div>
                <div class="tab-item" id="announcements-tab" data-filter="announcement">
                    <i class="bi bi-megaphone"></i>
                    <span>Announcements</span>
                </div>
                <div class="tab-item" id="reminders-tab" data-filter="reminder">
                    <i class="bi bi-bell"></i>
                    <span>Reminders</span>
                </div>
                <div class="tab-item" id="guidelines-tab" data-filter="guideline">
                    <i class="bi bi-book"></i>
                    <span>Guidelines</span>
                </div>
                <div class="tab-item" id="alerts-tab" data-filter="alert">
                    <i class="bi bi-exclamation-triangle"></i>
                    <span>Alerts</span>
                </div>
                <div class="tab-item" id="saved-tab" data-filter="saved">
                    <i class="bi bi-bookmark-fill"></i>
                    <span>Saved</span>
                </div>
                <div class="tab-item" id="shared-tab" data-filter="shared">
                    <i class="bi bi-share-fill"></i>
                    <span>Shared</span>
                </div>
                <div class="tab-item view-toggle" id="card-view-btn" onclick="switchView('card')">
                    <i class="bi bi-grid-3x3-gap"></i>
                </div>
                <div class="tab-item view-toggle" id="table-view-btn" onclick="switchView('table')">
                    <i class="bi bi-table"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Posts Container - Card View -->
    <div id="card-view-container">
        <div class="row">
            <div id="posts-container" class="col-12">
            <?php if (empty($posts)): ?>
                <div class="col-12">
                            <div class="text-center py-5">
                        <i class="bi bi-megaphone display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No posts yet</h4>
                        <p class="text-muted">Be the first to create an announcement!</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAnnouncementModal">
                            <i class="bi bi-plus-circle"></i> Create First Announcement
                                </button>
                    </div>
                            </div>
                        <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="col-lg-8 mx-auto mb-4 announcement-card" data-announcement-type="<?= $post['type'] ?>" data-saved="<?= $post['is_saved'] ?>" data-shared="<?= $post['is_shared'] ?>" data-post-id="<?= $post['id'] ?>">
                        <div class="card post-card" data-post-id="<?= $post['id'] ?>" data-post-type="announcement" data-type="<?= $post['type'] ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <img src="<?= !empty($post['profile_img']) ? '../uploads/profile_picture/' . $post['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                         class="rounded-circle me-2" width="40" height="40" alt="Profile">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($post['username']) ?></h6>
                                        <small class="text-muted"><?= date('M j, Y g:i A', strtotime($post['created_at'])) ?></small>
                                    </div>
                                </div>
                                <div>
                                    <span class="badge bg-<?= 
                                        $post['type'] === 'announcement' ? 'info' : 
                                        ($post['type'] === 'guideline' ? 'warning' : 
                                        ($post['type'] === 'reminder' ? 'primary' : 'danger')) 
                                    ?>">
                                        <i class="bi bi-<?= 
                                            $post['type'] === 'announcement' ? 'megaphone' : 
                                            ($post['type'] === 'guideline' ? 'book' : 
                                            ($post['type'] === 'reminder' ? 'bell' : 'exclamation-triangle')) 
                                        ?>"></i>
                                        <?= ucfirst($post['type']) ?>
                                    </span>
                                    <span class="badge bg-<?= 
                                        $post['priority'] === 'critical' ? 'danger' : 
                                        ($post['priority'] === 'high' ? 'warning' : 
                                        ($post['priority'] === 'medium' ? 'primary' : 'secondary')) 
                                    ?> ms-1">
                                        <?= ucfirst($post['priority']) ?>
                                    </span>
                                    <?php if ($post['is_pinned']): ?>
                                        <span class="badge bg-success ms-1"><i class="bi bi-pin-angle-fill"></i> Pinned</span>
                                    <?php endif; ?>
                                    <?php if ($post['is_shared']): ?>
                                        <span class="badge bg-info ms-1" title="You shared this post" data-bs-toggle="tooltip" data-bs-placement="top">
                                            <i class="bi bi-share-fill"></i>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                    <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($post['title']) ?></h5>
                                <p class="card-text"><?= nl2br(htmlspecialchars($post['content'] ?? $post['description'] ?? '')) ?></p>
                                
                                <?php if (!empty($post['images'])): ?>
                                    <?php 
                                    $images = json_decode($post['images'], true);
                                    if (!empty($images) && is_array($images)): 
                                    ?>
                                        <div class="announcement-images mt-3 mb-3">
                                            <div class="row g-2">
                                                <?php foreach ($images as $index => $image): ?>
                                                    <div class="col-md-<?= count($images) == 1 ? '12' : (count($images) == 2 ? '6' : '4') ?>">
                                                        <img src="../<?= htmlspecialchars($image) ?>" 
                                                             class="img-fluid rounded" 
                                                             alt="Announcement Image" 
                                                             style="max-height: 300px; width: 100%; object-fit: cover; cursor: pointer;"
                                                             onclick="openImageModal(this.src)">
                                                    </div>
                                                    <?php if ($index >= 5) break; // Limit to 6 images ?>
                                                <?php endforeach; ?>
                                                <?php if (count($images) > 6): ?>
                                                    <div class="col-12">
                                                        <small class="text-muted">+ <?= count($images) - 6 ?> more images</small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if (!empty($post['attachments'])): ?>
                                                    <?php
                                    $attachments = json_decode($post['attachments'], true);
                                    if (!empty($attachments) && is_array($attachments)): 
                                    ?>
                                        <div class="announcement-attachments mt-3 mb-3">
                                            <h6 class="mb-2"><i class="bi bi-paperclip"></i> Attachments:</h6>
                                            <div class="list-group">
                                                <?php foreach ($attachments as $attachment): ?>
                                                    <a href="../<?= htmlspecialchars($attachment['path']) ?>" 
                                                       class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                                       download="<?= htmlspecialchars($attachment['original_name']) ?>"
                                                       target="_blank">
                                                        <div>
                                                            <i class="bi bi-file-earmark-<?= 
                                                                $attachment['type'] === 'pdf' ? 'pdf' : 
                                                                (in_array($attachment['type'], ['doc', 'docx']) ? 'word' : 
                                                                (in_array($attachment['type'], ['xls', 'xlsx']) ? 'excel' : 
                                                                (in_array($attachment['type'], ['ppt', 'pptx']) ? 'ppt' : 
                                                                (in_array($attachment['type'], ['zip', 'rar']) ? 'zip' : 'text')))) 
                                                            ?>"></i>
                                                            <strong><?= htmlspecialchars($attachment['original_name']) ?></strong>
                                                            <small class="text-muted">(<?= round($attachment['size'] / 1024, 2) ?> KB)</small>
                                                        </div>
                                                        <i class="bi bi-download"></i>
                                                    </a>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php if ($post['post_type'] === 'food_donation'): ?>
                                    <div class="donation-details mt-3">
                                        <div class="row">
                            <div class="col-md-4">
                                                <small class="text-muted"><i class="bi bi-tag"></i> <?= ucfirst($post['food_type']) ?></small>
                            </div>
                            <div class="col-md-4">
                                                <small class="text-muted"><i class="bi bi-box"></i> <?= htmlspecialchars($post['quantity']) ?></small>
                            </div>
                                            <?php if ($post['expiration_date']): ?>
                            <div class="col-md-4">
                                                <small class="text-muted"><i class="bi bi-calendar"></i> Expires: <?= date('M j, Y', strtotime($post['expiration_date'])) ?></small>
                                </div>
                                            <?php endif; ?>
                            </div>
                                        <?php 
                                        $images = json_decode($post['images'], true);
                                        if (!empty($images) && is_array($images)): 
                                        ?>
                                            <div class="mt-3">
                                                <img src="../<?= htmlspecialchars($images[0]) ?>" class="img-fluid rounded" alt="Food Image" style="max-height: 300px; object-fit: cover;">
                    </div>
                                        <?php endif; ?>
                </div>
                                <?php endif; ?>
            </div>
                            
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-md-6">
                                                    <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-danger like-btn <?= $post['is_liked'] ? 'active' : '' ?>" 
                                                    data-post-id="<?= $post['id'] ?>" data-post-type="<?= $post['post_type'] ?>">
                                                <i class="bi bi-heart<?= $post['is_liked'] ? '-fill' : '' ?>"></i>
                                                <span class="likes-count"><?= $post['likes_count'] ?? 0 ?></span>
                                                        </button>
                                            <button type="button" class="btn btn-outline-primary comment-btn" 
                                                    data-post-id="<?= $post['id'] ?>" data-post-type="<?= $post['post_type'] ?>">
                                                <i class="bi bi-chat"></i>
                                                <span class="comments-count"><?= $post['comments_count'] ?? 0 ?></span>
                                                        </button>
                                            <button type="button" class="btn btn-outline-success share-btn" 
                                                    data-post-id="<?= $post['id'] ?>" data-post-type="<?= $post['post_type'] ?>">
                                                <i class="bi bi-share"></i>
                                                <span class="shares-count"><?= $post['shares_count'] ?? 0 ?></span>
                                                            </button>
                                            <button type="button" class="btn btn-outline-warning save-btn <?= $post['is_saved'] ? 'active' : '' ?>" 
                                                    data-post-id="<?= $post['id'] ?>" data-post-type="<?= $post['post_type'] ?>">
                                                <i class="bi bi-bookmark<?= $post['is_saved'] ? '-fill' : '' ?>"></i>
                        </button>
                    </div>
                </div>
                                    <div class="col-md-6 text-end">
                                        <button class="btn btn-sm btn-info" onclick="viewPost(<?= $post['id'] ?>, 'announcement')">
                                            <i class="bi bi-eye"></i> View
                                        </button>
                                        <button class="btn btn-sm btn-warning" onclick="editAnnouncement(<?= $post['id'] ?>)">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button class="btn btn-sm btn-danger" onclick="deleteAnnouncement(<?= $post['id'] ?>)">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
            </div>
        </div>

                                <!-- Comments Section -->
                                <div class="comments-section mt-3" id="comments-<?= $post['id'] ?>-<?= $post['post_type'] ?>" style="display: none;">
                                    <hr>
                                    <div class="comments-list"></div>
                                    <div class="comment-form mt-3">
                                        <div class="input-group">
                                            <input type="text" class="form-control comment-input" placeholder="Write a comment...">
                                            <button class="btn btn-primary submit-comment" type="button">
                                                <i class="bi bi-send"></i> Post
                                            </button>
            </div>
        </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Table View Container -->
    <div id="table-view-container" style="display: none;">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="row align-items-center mb-3">
                            <div class="col-md-6">
                                <h5 class="mb-0"><i class="bi bi-table"></i> Announcements Table</h5>
                    </div>
                            <div class="col-md-6 text-end">
                                <div class="btn-group btn-group-sm" role="group">
                                    <button type="button" class="btn btn-outline-primary filter-type-btn active" data-type="all">
                                        All
                                    </button>
                                    <button type="button" class="btn btn-outline-info filter-type-btn" data-type="announcement">
                                        Announcements
                                    </button>
                                    <button type="button" class="btn btn-outline-warning filter-type-btn" data-type="guideline">
                                        Guidelines
                                    </button>
                                    <button type="button" class="btn btn-outline-success filter-type-btn" data-type="reminder">
                                        Reminders
                                    </button>
                                    <button type="button" class="btn btn-outline-danger filter-type-btn" data-type="alert">
                                        Alerts
                                </button>
                            </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-12">
                                <div class="input-group">
                                    <span class="input-group-text">
                                        <i class="bi bi-search"></i>
                                    </span>
                                    <input type="text" class="form-control" id="tableSearchInput" 
                                           placeholder="Search by title, content, or author..." 
                                           onkeyup="searchTable()">
                                    <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                                        <i class="bi bi-x-circle"></i> Clear
                                    </button>
                                </div>
                                <small class="text-muted" id="searchResultsCount"></small>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                            <div class="table-responsive">
                            <table class="table table-hover table-striped mb-0" id="announcementsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th width="5%">
                                            <i class="bi bi-pin-angle"></i>
                                        </th>
                                        <th width="15%">Type</th>
                                        <th width="25%">Title</th>
                                        <th width="10%">Priority</th>
                                        <th width="10%">Status</th>
                                        <th width="10%">Attachments</th>
                                        <th width="10%">Created</th>
                                        <th width="10%">Engagement</th>
                                        <th width="10%">Actions</th>
                                        </tr>
                                    </thead>
                                <tbody id="announcementsTableBody">
                                    <?php foreach ($posts as $post): ?>
                                        <?php if ($post['post_type'] === 'announcement'): ?>
                                            <tr data-announcement-type="<?= $post['type'] ?>" data-post-id="<?= $post['id'] ?>" data-shared="<?= $post['is_shared'] ?>">
                                                <td class="text-center">
                                                    <?php if ($post['is_pinned']): ?>
                                                        <i class="bi bi-pin-angle-fill text-success" title="Pinned" data-bs-toggle="tooltip" data-bs-placement="top"></i>
                                                    <?php endif; ?>
                                                    <?php if ($post['is_shared']): ?>
                                                        <i class="bi bi-share-fill text-info" title="You shared this post" data-bs-toggle="tooltip" data-bs-placement="top"></i>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $post['type'] === 'announcement' ? 'info' : 
                                                        ($post['type'] === 'guideline' ? 'warning' : 
                                                        ($post['type'] === 'reminder' ? 'primary' : 'danger')) 
                                                    ?>">
                                                        <i class="bi bi-<?= 
                                                            $post['type'] === 'announcement' ? 'megaphone' : 
                                                            ($post['type'] === 'guideline' ? 'book' : 
                                                            ($post['type'] === 'reminder' ? 'bell' : 'exclamation-triangle')) 
                                                        ?>"></i>
                                                        <?= ucfirst($post['type']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <strong><?= htmlspecialchars($post['title']) ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars(substr($post['content'], 0, 80)) ?>
                                                        <?= strlen($post['content']) > 80 ? '...' : '' ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $post['priority'] === 'critical' ? 'danger' : 
                                                        ($post['priority'] === 'high' ? 'warning' : 
                                                        ($post['priority'] === 'medium' ? 'primary' : 'secondary')) 
                                                    ?>">
                                                        <?= ucfirst($post['priority']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?= 
                                                        $post['status'] === 'published' ? 'success' : 
                                                        ($post['status'] === 'draft' ? 'warning' : 'secondary') 
                                                    ?>">
                                                        <?= ucfirst($post['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $images = json_decode($post['images'], true);
                                                    $attachments = json_decode($post['attachments'], true);
                                                    $imageCount = !empty($images) ? count($images) : 0;
                                                    $fileCount = !empty($attachments) ? count($attachments) : 0;
                                                    ?>
                                                    <?php if ($imageCount > 0): ?>
                                                        <span class="badge bg-info me-1" title="Images">
                                                            <i class="bi bi-image"></i> <?= $imageCount ?>
                                                    </span>
                                                    <?php endif; ?>
                                                    <?php if ($fileCount > 0): ?>
                                                        <span class="badge bg-secondary" title="Files">
                                                            <i class="bi bi-paperclip"></i> <?= $fileCount ?>
                                                        </span>
                                                    <?php endif; ?>
                                                    <?php if ($imageCount == 0 && $fileCount == 0): ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <small>
                                                        <strong><?= htmlspecialchars($post['username']) ?></strong><br>
                                                        <span class="text-muted"><?= date('M j, Y', strtotime($post['created_at'])) ?></span><br>
                                                        <span class="text-muted"><?= date('g:i A', strtotime($post['created_at'])) ?></span>
                                                    </small>
                                                </td>
                                                <td>
                                                    <small>
                                                        <i class="bi bi-heart text-danger"></i> <?= $post['likes_count'] ?? 0 ?><br>
                                                        <i class="bi bi-chat text-primary"></i> <?= $post['comments_count'] ?? 0 ?><br>
                                                        <i class="bi bi-share text-success"></i> <?= $post['shares_count'] ?? 0 ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <div class="btn-group-vertical btn-group-sm" role="group">
                                                        <button class="btn btn-sm btn-outline-info" onclick="viewPost(<?= $post['id'] ?>, 'announcement')" title="View">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-warning" onclick="editAnnouncement(<?= $post['id'] ?>)" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-danger" onclick="deleteAnnouncement(<?= $post['id'] ?>)" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php if (empty(array_filter($posts, function($p) { return $p['post_type'] === 'announcement'; }))): ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-5">
                                                <i class="bi bi-inbox display-4 text-muted"></i>
                                                <h5 class="text-muted mt-3">No announcements found</h5>
                                                <p class="text-muted">Create your first announcement to get started!</p>
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
</div>
</main>

<!-- Create/Edit Announcement Modal -->
<div class="modal fade" id="createAnnouncementModal" tabindex="-1">
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
                                <option value="alert">Alert</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="priority" class="form-label">Priority *</label>
                            <select class="form-select" id="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label for="status" class="form-label">Status *</label>
                            <select class="form-select" id="status" required>
                                <option value="draft">Draft</option>
                                <option value="published" selected>Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="is_pinned">
                        <label class="form-check-label" for="is_pinned">
                            <i class="bi bi-pin-angle"></i> Pin this announcement to the top
                        </label>
                    </div>
                    
                    <!-- Image Upload Section -->
                    <div class="mb-3 mt-3">
                        <label for="images" class="form-label">
                            <i class="bi bi-image"></i> Upload Images (Optional)
                        </label>
                        <input type="file" class="form-control" id="images" name="images[]" multiple 
                               accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/svg+xml">
                        <div class="form-text">Upload images (JPG, PNG, GIF, WebP, SVG). Multiple files allowed.</div>
                        <div id="image-preview" class="mt-2 d-flex flex-wrap gap-2"></div>
                    </div>
                    
                    <!-- File Attachments Section -->
                    <div class="mb-3">
                        <label for="attachments" class="form-label">
                            <i class="bi bi-paperclip"></i> Attach Files (Optional)
                        </label>
                        <input type="file" class="form-control" id="attachments" name="attachments[]" multiple 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">
                        <div class="form-text">Attach documents (PDF, Word, Excel, PowerPoint, TXT, ZIP). Multiple files allowed.</div>
                        <div id="attachment-preview" class="mt-2"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveAnnouncement()">
                    <i class="bi bi-save"></i> Save Announcement
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Post Details Modal -->
<div class="modal fade" id="viewPostModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Post Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="postDetails">
                <!-- Details will be loaded here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- Image Viewer Modal -->
<div class="modal fade" id="imageViewerModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content bg-transparent border-0">
            <div class="modal-body p-0 position-relative">
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3 btn-close-white" 
                        data-bs-dismiss="modal" style="z-index: 1051;"></button>
                <img id="imageViewerImg" src="" class="img-fluid w-100" alt="Full Image">
            </div>
        </div>
    </div>
</div>

<style>
.facebook-tabs {
    display: flex;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 8px;
    gap: 4px;
    flex-wrap: wrap;
}

.tab-item {
    flex: 0 1 auto;
    min-width: 100px;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    border-radius: 6px;
    transition: all 0.2s;
    font-weight: 500;
    color: #65676b;
}

.tab-item:hover {
    background: #f0f2f5;
}

.tab-item.active {
    background: #e7f3ff;
    color: #0d6efd;
}

.tab-item i {
    font-size: 1.2rem;
}

.tab-item.view-toggle {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    margin-left: auto;
}

.tab-item.view-toggle:hover {
    background: #e9ecef;
}

.tab-item.view-toggle.active {
    background: #0d6efd;
    color: white;
    border-color: #0d6efd;
}

.post-card {
    transition: box-shadow 0.2s;
    border: 1px solid #e4e6eb;
}

.post-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.post-card .card-header {
    background: #fff;
    border-bottom: 1px solid #e4e6eb;
    padding: 12px 16px;
}

.post-card .card-body {
    padding: 16px;
}

.post-card .card-footer {
    background: #f7f8fa;
    border-top: 1px solid #e4e6eb;
    padding: 8px 16px;
}

.btn-group .btn {
    border-radius: 20px !important;
    margin: 0 4px;
}

.like-btn.active {
    color: #e74c3c;
    border-color: #e74c3c;
}

.save-btn.active {
    color: #f39c12;
    border-color: #f39c12;
}

.comments-section {
    background: #f7f8fa;
    padding: 16px;
    border-radius: 8px;
    margin-top: 16px;
}

.comment-item {
    background: #fff;
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 12px;
}

.announcement-images img {
    transition: transform 0.2s;
}

.announcement-images img:hover {
    transform: scale(1.02);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.announcement-attachments .list-group-item {
    border-left: 3px solid #0d6efd;
}

.announcement-attachments .list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: #0a58ca;
}

#image-preview img {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e4e6eb;
}

#attachment-preview .attachment-item {
    padding: 8px 12px;
    background: #f0f2f5;
    border-radius: 6px;
    margin-bottom: 8px;
}

#imageViewerModal .modal-content {
    background: rgba(0,0,0,0.9);
}

/* Table View Styles */
#announcementsTable {
    font-size: 0.9rem;
}

#announcementsTable thead th {
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.75rem;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #dee2e6;
    padding: 12px 8px;
}

#announcementsTable tbody tr {
    transition: all 0.2s;
}

#announcementsTable tbody tr:hover {
    background-color: #f8f9fa;
    transform: scale(1.01);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

#announcementsTable tbody td {
    vertical-align: middle;
    padding: 12px 8px;
}

#announcementsTable .btn-group-vertical {
    width: 100%;
}

#announcementsTable .btn-group-vertical .btn {
    margin-bottom: 2px;
}

.filter-type-btn {
    transition: all 0.2s;
}

.filter-type-btn.active {
    font-weight: 600;
    transform: scale(1.05);
}

.table-hover tbody tr[data-announcement-type]:hover {
    cursor: pointer;
}

/* Pinned row highlight */
#announcementsTable tbody tr:has(.bi-pin-angle-fill) {
    background-color: #fff3cd;
}

#announcementsTable tbody tr:has(.bi-pin-angle-fill):hover {
    background-color: #ffecb5;
}

/* Badge adjustments for table */
#announcementsTable .badge {
    padding: 4px 8px;
    font-size: 0.75rem;
}

/* View toggle buttons */
.btn-group .btn.active {
    background-color: #0d6efd;
    color: white;
}

/* Search box styling */
#tableSearchInput {
    border: 2px solid #e4e6eb;
    transition: all 0.3s;
}

#tableSearchInput:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}

#searchResultsCount {
    display: block;
    margin-top: 8px;
    font-style: italic;
    color: #0d6efd;
}

.input-group-text {
    background-color: #f8f9fa;
    border: 2px solid #e4e6eb;
    border-right: none;
}

#tableSearchInput:focus + .input-group-text {
    border-color: #0d6efd;
}

/* Highlight search matches */
.search-highlight {
    background-color: #fff3cd;
    padding: 2px 4px;
    border-radius: 3px;
    font-weight: 600;
}

/* Table header with search */
.card-header .input-group {
    max-width: 100%;
}

/* Shared icon badge styling */
.badge .bi-share-fill {
    font-size: 1rem;
}

.badge:has(.bi-share-fill):not(:has(span:not(.bi))) {
    padding: 6px 10px;
    cursor: help;
}

/* Table icons spacing */
#announcementsTable td.text-center i {
    margin: 0 3px;
    font-size: 1.1rem;
}
</style>

<script>
// Global variables
let currentPage = 1;
let currentFilter = 'all';

// Tab switching - Filter by announcement type
document.querySelectorAll('.tab-item:not(.view-toggle)').forEach(tab => {
    tab.addEventListener('click', function() {
        // Remove active class only from filter tabs (not view toggles)
        document.querySelectorAll('.tab-item:not(.view-toggle)').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentFilter = this.dataset.filter;
        
        // Filter cards in card view
        filterAnnouncementsByType(currentFilter);
        
        // Filter table in table view
        filterTableByType(currentFilter);
    });
});

// Filter announcements by type in card view
function filterAnnouncementsByType(type) {
    const cards = document.querySelectorAll('.announcement-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const cardType = card.dataset.announcementType;
        const isSaved = card.dataset.saved == '1';
        const isShared = card.dataset.shared == '1';
        
        let shouldShow = false;
        
        if (type === 'all') {
            shouldShow = true;
        } else if (type === 'saved') {
            shouldShow = isSaved;
        } else if (type === 'shared') {
            shouldShow = isShared;
        } else {
            shouldShow = cardType === type;
        }
        
        if (shouldShow) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Show empty message if no cards visible
    const emptyMessage = document.querySelector('#posts-container > div.col-12 > div.text-center');
    if (emptyMessage) {
        emptyMessage.parentElement.style.display = visibleCount === 0 ? 'block' : 'none';
    }
}

// Filter table by type
function filterTableByType(type) {
    const rows = document.querySelectorAll('#announcementsTableBody tr[data-announcement-type]');
    let visibleCount = 0;
    
    rows.forEach(row => {
        const rowType = row.dataset.announcementType;
        const postId = row.dataset.postId;
        const isShared = row.dataset.shared == '1';
        
        // Check if this post is saved by finding the corresponding card
        const card = document.querySelector(`.announcement-card[data-post-id="${postId}"]`);
        const isSaved = card ? card.dataset.saved == '1' : false;
        
        let shouldShow = false;
        
        if (type === 'all') {
            shouldShow = true;
        } else if (type === 'saved') {
            shouldShow = isSaved;
        } else if (type === 'shared') {
            shouldShow = isShared;
        } else {
            shouldShow = rowType === type;
        }
        
        if (shouldShow) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update filter buttons in table to match (only if not saved/shared filter)
    if (type !== 'saved' && type !== 'shared') {
        document.querySelectorAll('.filter-type-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.type === type) {
                btn.classList.add('active');
            }
        });
    } else {
        // When saved/shared is selected, set 'all' as active in table filters
        document.querySelectorAll('.filter-type-btn').forEach(btn => {
            btn.classList.remove('active');
            if (btn.dataset.type === 'all') {
                btn.classList.add('active');
            }
        });
    }
}

// Create/Edit Announcement Functions
function saveAnnouncement() {
    const id = document.getElementById('announcement_id').value;
    const title = document.getElementById('title').value;
    const content = document.getElementById('content').value;
    const type = document.getElementById('type').value;
    const priority = document.getElementById('priority').value;
    const status = document.getElementById('status').value;
    const is_pinned = document.getElementById('is_pinned').checked;
    
    if (!title.trim() || !content.trim()) {
        showNotification('Please fill in all required fields.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', id ? 'update_announcement' : 'create_announcement');
    if (id) formData.append('id', id);
    formData.append('title', title);
    formData.append('content', content);
    formData.append('type', type);
    formData.append('priority', priority);
    formData.append('status', status);
    if (is_pinned) formData.append('is_pinned', '1');
    
    // Append image files
    const imageFiles = document.getElementById('images').files;
    for (let i = 0; i < imageFiles.length; i++) {
        formData.append('images[]', imageFiles[i]);
    }
    
    // Append attachment files
    const attachmentFiles = document.getElementById('attachments').files;
    for (let i = 0; i < attachmentFiles.length; i++) {
        formData.append('attachments[]', attachmentFiles[i]);
    }
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('createAnnouncementModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while saving the announcement.', 'error');
    });
}

// Image preview function
document.getElementById('images').addEventListener('change', function(e) {
    const preview = document.getElementById('image-preview');
    preview.innerHTML = '';
    
    const files = Array.from(e.target.files);
    files.forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const img = document.createElement('img');
                img.src = e.target.result;
                img.className = 'img-thumbnail';
                img.title = file.name;
                preview.appendChild(img);
            };
            reader.readAsDataURL(file);
        }
    });
});

// Attachment preview function
document.getElementById('attachments').addEventListener('change', function(e) {
    const preview = document.getElementById('attachment-preview');
    preview.innerHTML = '';
    
    const files = Array.from(e.target.files);
    files.forEach((file, index) => {
        const div = document.createElement('div');
        div.className = 'attachment-item d-flex justify-content-between align-items-center';
        div.innerHTML = `
            <div>
                <i class="bi bi-paperclip"></i>
                <strong>${file.name}</strong>
                <small class="text-muted">(${(file.size / 1024).toFixed(2)} KB)</small>
            </div>
        `;
        preview.appendChild(div);
    });
});

// Image viewer function
function openImageModal(imageSrc) {
    document.getElementById('imageViewerImg').src = imageSrc;
    new bootstrap.Modal(document.getElementById('imageViewerModal')).show();
}

function editAnnouncement(id) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_post_details&id=${id}&post_type=announcement`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const post = data.data;
            document.getElementById('modalTitle').textContent = 'Edit Announcement';
            document.getElementById('announcement_id').value = post.id;
            document.getElementById('title').value = post.title;
            document.getElementById('content').value = post.content;
            document.getElementById('type').value = post.type;
            document.getElementById('priority').value = post.priority;
            document.getElementById('status').value = post.status;
            document.getElementById('is_pinned').checked = post.is_pinned == 1;
            new bootstrap.Modal(document.getElementById('createAnnouncementModal')).show();
        } else {
            showNotification(data.message, 'error');
        }
    });
}

function deleteAnnouncement(id) {
    if (confirm('Are you sure you want to delete this announcement? This action cannot be undone.')) {
        const formData = new FormData();
        formData.append('action', 'delete_announcement');
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
        });
    }
}


function viewPost(id, postType) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_post_details&id=${id}&post_type=${postType}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPostDetails(data.data, postType);
        } else {
            showNotification(data.message, 'error');
        }
    });
}

function displayPostDetails(post, postType) {
    const images = post.images ? JSON.parse(post.images) : [];
    const attachments = post.attachments ? JSON.parse(post.attachments) : [];
    
    let imagesHTML = '';
    if (images.length > 0) {
        imagesHTML = '<div class="row mb-3">';
        images.forEach(img => {
            imagesHTML += `
                <div class="col-md-4 mb-2">
                    <img src="../${img}" class="img-fluid rounded" alt="Image" onclick="openImageModal(this.src)" style="cursor: pointer;">
                </div>
            `;
        });
        imagesHTML += '</div>';
    }
    
    let attachmentsHTML = '';
    if (attachments.length > 0) {
        attachmentsHTML = `
            <div class="mt-3 mb-3">
                <h6><i class="bi bi-paperclip"></i> Attachments:</h6>
                <div class="list-group">
        `;
        attachments.forEach(file => {
            const fileIcon = file.type === 'pdf' ? 'file-earmark-pdf' : 
                           (file.type === 'doc' || file.type === 'docx') ? 'file-earmark-word' :
                           (file.type === 'xls' || file.type === 'xlsx') ? 'file-earmark-excel' :
                           (file.type === 'ppt' || file.type === 'pptx') ? 'file-earmark-ppt' :
                           (file.type === 'zip' || file.type === 'rar') ? 'file-earmark-zip' : 'file-earmark-text';
            
            attachmentsHTML += `
                <a href="../${file.path}" class="list-group-item list-group-item-action d-flex justify-content-between" 
                   download="${file.original_name}" target="_blank">
                    <div>
                        <i class="bi bi-${fileIcon}"></i>
                        <strong>${file.original_name}</strong>
                        <small class="text-muted">(${(file.size / 1024).toFixed(2)} KB)</small>
                    </div>
                    <i class="bi bi-download"></i>
                </a>
            `;
        });
        attachmentsHTML += '</div></div>';
    }
    
    const html = `
        <div class="row">
            <div class="col-12">
                ${imagesHTML}
                
                <h4 class="mb-3">${post.title}</h4>
                
                <div class="mb-3">
                    <span class="badge bg-${
                        post.type === 'announcement' ? 'info' : 
                        (post.type === 'guideline' ? 'warning' : 
                        (post.type === 'reminder' ? 'primary' : 'danger'))
                    }">
                        <i class="bi bi-${
                            post.type === 'announcement' ? 'megaphone' : 
                            (post.type === 'guideline' ? 'book' : 
                            (post.type === 'reminder' ? 'bell' : 'exclamation-triangle'))
                        }"></i>
                        ${post.type.charAt(0).toUpperCase() + post.type.slice(1)}
                    </span>
                    <span class="badge bg-${
                        post.priority === 'critical' ? 'danger' : 
                        (post.priority === 'high' ? 'warning' : 
                        (post.priority === 'medium' ? 'primary' : 'secondary'))
                    }">
                        ${post.priority.charAt(0).toUpperCase() + post.priority.slice(1)} Priority
                    </span>
                    <span class="badge bg-${post.status === 'published' ? 'success' : (post.status === 'draft' ? 'warning' : 'secondary')}">
                        ${post.status.charAt(0).toUpperCase() + post.status.slice(1)}
                    </span>
                    ${post.is_pinned == 1 ? '<span class="badge bg-success"><i class="bi bi-pin-angle-fill"></i> Pinned</span>' : ''}
                </div>
                
                <hr>
                
                <div class="mb-4" style="white-space: pre-wrap;">${post.content}</div>
                
                ${attachmentsHTML}
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong><i class="bi bi-person-circle"></i> Created by:</strong> ${post.full_name}</p>
                        <p class="mb-1"><strong><i class="bi bi-envelope"></i> Email:</strong> ${post.email}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong><i class="bi bi-calendar"></i> Created:</strong> ${new Date(post.created_at).toLocaleString()}</p>
                        <p class="mb-1"><strong><i class="bi bi-clock-history"></i> Last Updated:</strong> ${new Date(post.updated_at).toLocaleString()}</p>
                    </div>
                </div>
                
                <div class="mt-3 p-3 bg-light rounded">
                    <strong><i class="bi bi-graph-up"></i> Engagement:</strong>
                    <span class="ms-3"><i class="bi bi-heart-fill text-danger"></i> ${post.likes_count || 0} Likes</span>
                    <span class="ms-3"><i class="bi bi-chat-fill text-primary"></i> ${post.comments_count || 0} Comments</span>
                    <span class="ms-3"><i class="bi bi-share-fill text-success"></i> ${post.shares_count || 0} Shares</span>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('postDetails').innerHTML = html;
    new bootstrap.Modal(document.getElementById('viewPostModal')).show();
}

// Social Interaction Functions
document.addEventListener('click', function(e) {
    // Like button
    if (e.target.closest('.like-btn')) {
        const btn = e.target.closest('.like-btn');
        const postId = btn.dataset.postId;
        const postType = btn.dataset.postType;
        toggleLike(postId, postType, btn);
    }
    
    // Comment button
    if (e.target.closest('.comment-btn')) {
        const btn = e.target.closest('.comment-btn');
        const postId = btn.dataset.postId;
        const postType = btn.dataset.postType;
        toggleCommentsSection(postId, postType);
    }
    
    // Share button
    if (e.target.closest('.share-btn')) {
        const btn = e.target.closest('.share-btn');
        const postId = btn.dataset.postId;
        const postType = btn.dataset.postType;
        sharePost(postId, postType, btn);
    }
    
    // Save button
    if (e.target.closest('.save-btn')) {
        const btn = e.target.closest('.save-btn');
        const postId = btn.dataset.postId;
        const postType = btn.dataset.postType;
        toggleSave(postId, postType, btn);
    }
    
    // Submit comment
    if (e.target.closest('.submit-comment')) {
        const btn = e.target.closest('.submit-comment');
        const card = btn.closest('.post-card');
        const postId = card.dataset.postId;
        const postType = card.dataset.postType;
        const input = card.querySelector('.comment-input');
        submitComment(postId, postType, input.value, card);
    }
});

function toggleLike(postId, postType, btn) {
        const formData = new FormData();
    formData.append('action', 'toggle_like');
    formData.append('post_id', postId);
    formData.append('post_type', postType);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
            const icon = btn.querySelector('i');
            const count = btn.querySelector('.likes-count');
            if (data.liked) {
                btn.classList.add('active');
                icon.classList.remove('bi-heart');
                icon.classList.add('bi-heart-fill');
                count.textContent = parseInt(count.textContent) + 1;
            } else {
                btn.classList.remove('active');
                icon.classList.remove('bi-heart-fill');
                icon.classList.add('bi-heart');
                count.textContent = parseInt(count.textContent) - 1;
            }
        }
    });
}

function toggleCommentsSection(postId, postType) {
    const section = document.getElementById(`comments-${postId}-${postType}`);
    if (section.style.display === 'none') {
        section.style.display = 'block';
        loadComments(postId, postType);
    } else {
        section.style.display = 'none';
    }
}

function loadComments(postId, postType) {
    const formData = new FormData();
    formData.append('action', 'get_comments');
    formData.append('post_id', postId);
    formData.append('post_type', postType);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const section = document.getElementById(`comments-${postId}-${postType}`);
            const list = section.querySelector('.comments-list');
            list.innerHTML = '';
            
            data.comments.forEach(comment => {
                const commentHtml = `
                    <div class="comment-item">
                        <div class="d-flex">
                            <img src="${comment.profile_img ? '../uploads/profile_picture/' + comment.profile_img : '../uploads/profile_picture/no_image.png'}" 
                                 class="rounded-circle me-2" width="32" height="32" alt="Profile">
                            <div class="flex-grow-1">
                                <strong>${comment.user_name}</strong>
                                <p class="mb-1">${comment.comment}</p>
                                <small class="text-muted">${new Date(comment.created_at).toLocaleString()}</small>
                            </div>
                        </div>
                    </div>
                `;
                list.insertAdjacentHTML('beforeend', commentHtml);
            });
        }
    });
}

function submitComment(postId, postType, comment, card) {
    if (!comment.trim()) return;
    
    const formData = new FormData();
    formData.append('action', 'add_comment');
    formData.append('post_id', postId);
    formData.append('post_type', postType);
    formData.append('comment', comment);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            card.querySelector('.comment-input').value = '';
            const commentCount = card.querySelector('.comments-count');
            commentCount.textContent = parseInt(commentCount.textContent) + 1;
            loadComments(postId, postType);
            showNotification(data.message, 'success');
        }
    });
}

function sharePost(postId, postType, btn) {
    const message = prompt('Add a message to your share (optional):');
    if (message === null) return; // User cancelled
    
    const formData = new FormData();
    formData.append('action', 'share_post');
    formData.append('post_id', postId);
    formData.append('post_type', postType);
    formData.append('share_message', message || '');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const count = btn.querySelector('.shares-count');
            count.textContent = parseInt(count.textContent) + 1;
            
            // Mark card and row as shared
            const card = btn.closest('.announcement-card');
            if (card) {
                card.dataset.shared = '1';
                
                // Add shared badge to card header if not already there
                const cardHeader = card.querySelector('.card-header > div:last-child');
                if (cardHeader && !cardHeader.querySelector('.badge:has(.bi-share-fill)')) {
                    const sharedBadge = document.createElement('span');
                    sharedBadge.className = 'badge bg-info ms-1';
                    sharedBadge.title = 'You shared this post';
                    sharedBadge.setAttribute('data-bs-toggle', 'tooltip');
                    sharedBadge.setAttribute('data-bs-placement', 'top');
                    sharedBadge.innerHTML = '<i class="bi bi-share-fill"></i>';
                    cardHeader.appendChild(sharedBadge);
                    
                    // Initialize tooltip
                    new bootstrap.Tooltip(sharedBadge);
                }
            }
            
            // Update table row
            const row = document.querySelector(`#announcementsTableBody tr[data-post-id="${postId}"]`);
            if (row) {
                row.dataset.shared = '1';
                
                // Add shared icon to table row
                const iconCell = row.cells[0];
                if (!iconCell.querySelector('.bi-share-fill')) {
                    const sharedIcon = document.createElement('i');
                    sharedIcon.className = 'bi bi-share-fill text-info';
                    sharedIcon.title = 'You shared this post';
                    sharedIcon.setAttribute('data-bs-toggle', 'tooltip');
                    sharedIcon.setAttribute('data-bs-placement', 'top');
                    iconCell.appendChild(sharedIcon);
                    
                    // Initialize tooltip
                    new bootstrap.Tooltip(sharedIcon);
                }
            }
            
            showNotification(data.message, 'success');
        }
    });
}

function toggleSave(postId, postType, btn) {
    const formData = new FormData();
    formData.append('action', 'save_post');
    formData.append('post_id', postId);
    formData.append('post_type', postType);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('i');
            const card = btn.closest('.announcement-card');
            
            if (data.saved) {
                btn.classList.add('active');
                icon.classList.remove('bi-bookmark');
                icon.classList.add('bi-bookmark-fill');
                if (card) card.dataset.saved = '1';
            } else {
                btn.classList.remove('active');
                icon.classList.remove('bi-bookmark-fill');
                icon.classList.add('bi-bookmark');
                if (card) card.dataset.saved = '0';
            }
            showNotification(data.message, data.saved ? 'success' : 'info');
        }
    });
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
    }, 5000);
}

// View Switching Functions
function switchView(viewType) {
    const cardView = document.getElementById('card-view-container');
    const tableView = document.getElementById('table-view-container');
    const cardBtn = document.getElementById('card-view-btn');
    const tableBtn = document.getElementById('table-view-btn');
    
    // Prevent event bubbling
    event.stopPropagation();
    
    if (viewType === 'card') {
        cardView.style.display = 'block';
        tableView.style.display = 'none';
        cardBtn.classList.add('active');
        tableBtn.classList.remove('active');
        localStorage.setItem('announcementsView', 'card');
    } else {
        cardView.style.display = 'none';
        tableView.style.display = 'block';
        cardBtn.classList.remove('active');
        tableBtn.classList.add('active');
        localStorage.setItem('announcementsView', 'table');
    }
}

// Table search function
function searchTable() {
    const searchInput = document.getElementById('tableSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#announcementsTableBody tr[data-announcement-type]');
    let visibleCount = 0;
    
    rows.forEach(row => {
        // Get current filter type
        const activeFilter = document.querySelector('.filter-type-btn.active');
        const filterType = activeFilter ? activeFilter.dataset.type : 'all';
        const rowType = row.dataset.announcementType;
        
        // Check if row matches type filter
        const matchesTypeFilter = filterType === 'all' || rowType === filterType;
        
        if (!matchesTypeFilter) {
            row.style.display = 'none';
            return;
        }
        
        // Get searchable text from row
        const title = row.cells[2].textContent.toLowerCase();
        const content = row.cells[2].querySelector('small') ? row.cells[2].querySelector('small').textContent.toLowerCase() : '';
        const author = row.cells[6].textContent.toLowerCase();
        const type = row.cells[1].textContent.toLowerCase();
        
        // Check if search term matches
        const matchesSearch = searchInput === '' || 
                            title.includes(searchInput) || 
                            content.includes(searchInput) || 
                            author.includes(searchInput) ||
                            type.includes(searchInput);
        
        if (matchesSearch) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });
    
    // Update search results count
    const countElement = document.getElementById('searchResultsCount');
    if (searchInput !== '') {
        countElement.textContent = `Found ${visibleCount} result(s)`;
        countElement.style.display = 'block';
    } else {
        countElement.textContent = '';
        countElement.style.display = 'none';
    }
    
    // Check if any rows are visible
    const emptyRow = document.querySelector('#announcementsTableBody tr:not([data-announcement-type])');
    if (visibleCount === 0 && emptyRow) {
        emptyRow.style.display = '';
    } else if (emptyRow) {
        emptyRow.style.display = 'none';
    }
}

// Clear search function
function clearSearch() {
    document.getElementById('tableSearchInput').value = '';
    searchTable();
}

// Table Filter by Type (Updated to work with search)
document.addEventListener('click', function(e) {
    if (e.target.closest('.filter-type-btn')) {
        const btn = e.target.closest('.filter-type-btn');
        const filterType = btn.dataset.type;
        
        // Update active button
        document.querySelectorAll('.filter-type-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        // Re-run search to apply both filters
        searchTable();
    }
});

// Restore saved view preference and initialize tooltips
document.addEventListener('DOMContentLoaded', function() {
    const savedView = localStorage.getItem('announcementsView');
    if (savedView === 'table') {
        document.getElementById('card-view-btn').classList.remove('active');
        document.getElementById('table-view-btn').classList.add('active');
        document.getElementById('card-view-container').style.display = 'none';
        document.getElementById('table-view-container').style.display = 'block';
    } else {
        // Default: Card view active
        document.getElementById('card-view-btn').classList.add('active');
        document.getElementById('table-view-btn').classList.remove('active');
    }
    
    // Initialize all Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Reset modal on close
document.getElementById('createAnnouncementModal').addEventListener('hidden.bs.modal', function () {
    document.getElementById('modalTitle').textContent = 'Create New Announcement';
    document.getElementById('announcementForm').reset();
    document.getElementById('announcement_id').value = '';
    document.getElementById('image-preview').innerHTML = '';
    document.getElementById('attachment-preview').innerHTML = '';
});
</script>

<?php include 'footer.php'; ?>

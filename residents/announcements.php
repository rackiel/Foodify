<?php
include '../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Handle AJAX requests BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'toggle_like':
                $post_id = (int)$_POST['post_id'];
                
                try {
                    $stmt = $conn->prepare("SELECT id FROM announcement_likes WHERE post_id = ? AND post_type = 'announcement' AND user_id = ?");
                    $stmt->bind_param('ii', $post_id, $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Unlike
                        $stmt->close();
                        $stmt = $conn->prepare("DELETE FROM announcement_likes WHERE post_id = ? AND post_type = 'announcement' AND user_id = ?");
                        $stmt->bind_param('ii', $post_id, $_SESSION['user_id']);
                        $stmt->execute();
                        
                        // Update likes count
                        $stmt->close();
                        $stmt = $conn->prepare("UPDATE announcements SET likes_count = likes_count - 1 WHERE id = ?");
                        $stmt->bind_param('i', $post_id);
                        $stmt->execute();
                        
                        echo json_encode(['success' => true, 'liked' => false, 'message' => 'Post unliked']);
                    } else {
                        // Like
                        $stmt->close();
                        $stmt = $conn->prepare("INSERT INTO announcement_likes (post_id, post_type, user_id) VALUES (?, 'announcement', ?)");
                        $stmt->bind_param('ii', $post_id, $_SESSION['user_id']);
                        $stmt->execute();
                        
                        // Update likes count
                        $stmt->close();
                        $stmt = $conn->prepare("UPDATE announcements SET likes_count = likes_count + 1 WHERE id = ?");
                        $stmt->bind_param('i', $post_id);
                        $stmt->execute();
                        
                        echo json_encode(['success' => true, 'liked' => true, 'message' => 'Post liked!']);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'add_comment':
                $post_id = (int)$_POST['post_id'];
                $comment = trim($_POST['comment'] ?? '');
                
                if (empty($comment)) {
                    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
                    exit;
                }
                
                try {
                    $stmt = $conn->prepare("INSERT INTO announcement_comments (post_id, post_type, user_id, comment) VALUES (?, 'announcement', ?, ?)");
                    $stmt->bind_param('iis', $post_id, $_SESSION['user_id'], $comment);
                    $stmt->execute();
                    
                    // Update comments count
                    $stmt->close();
                    $stmt = $conn->prepare("UPDATE announcements SET comments_count = comments_count + 1 WHERE id = ?");
                    $stmt->bind_param('i', $post_id);
                    $stmt->execute();
                    
                    echo json_encode(['success' => true, 'message' => 'Comment added successfully!']);
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'get_comments':
                $post_id = (int)$_POST['post_id'];
                $page = (int)($_POST['page'] ?? 1);
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                try {
                    // Get total count
                    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM announcement_comments WHERE post_id = ? AND post_type = 'announcement'");
                    $stmt->bind_param('i', $post_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $total_count = $result->fetch_assoc()['total'];
                    $stmt->close();
                    
                    // Get comments with pagination
                    $stmt = $conn->prepare("
                        SELECT c.*, u.full_name, u.profile_img 
                        FROM announcement_comments c 
                        JOIN user_accounts u ON c.user_id = u.user_id 
                        WHERE c.post_id = ? AND c.post_type = 'announcement'
                        ORDER BY c.created_at DESC
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->bind_param('iii', $post_id, $limit, $offset);
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
                $share_message = trim($_POST['share_message'] ?? '');
                
                try {
                    $stmt = $conn->prepare("INSERT INTO announcement_shares (post_id, post_type, user_id, share_message) VALUES (?, 'announcement', ?, ?)");
                    $stmt->bind_param('iis', $post_id, $_SESSION['user_id'], $share_message);
                    $stmt->execute();
                    
                    // Update shares count
                    $stmt->close();
                    $stmt = $conn->prepare("UPDATE announcements SET shares_count = shares_count + 1 WHERE id = ?");
                    $stmt->bind_param('i', $post_id);
                    $stmt->execute();
                    
                    echo json_encode(['success' => true, 'message' => 'Post shared successfully!']);
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'save_post':
                $post_id = (int)$_POST['post_id'];
                
                try {
                    $stmt = $conn->prepare("SELECT id FROM announcement_saves WHERE post_id = ? AND post_type = 'announcement' AND user_id = ?");
                    $stmt->bind_param('ii', $post_id, $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Remove from saved
                        $stmt->close();
                        $stmt = $conn->prepare("DELETE FROM announcement_saves WHERE post_id = ? AND post_type = 'announcement' AND user_id = ?");
                        $stmt->bind_param('ii', $post_id, $_SESSION['user_id']);
                        $stmt->execute();
                        echo json_encode(['success' => true, 'saved' => false, 'message' => 'Post removed from saved']);
                    } else {
                        // Add to saved
                        $stmt->close();
                        $stmt = $conn->prepare("INSERT INTO announcement_saves (post_id, post_type, user_id) VALUES (?, 'announcement', ?)");
                        $stmt->bind_param('ii', $post_id, $_SESSION['user_id']);
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
                        WHERE a.id = ? AND a.status = 'published'
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
        }
    }
}

// Get published announcements for display
$posts = [];
try {
    $stmt = $conn->prepare("
        SELECT a.*, u.full_name as username, u.profile_img, 'announcement' as post_type
        FROM announcements a
        LEFT JOIN user_accounts u ON a.user_id = u.user_id
        WHERE a.status = 'published'
        ORDER BY a.is_pinned DESC, a.created_at DESC
        LIMIT 100
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
                    <h2><i class="bi bi-megaphone"></i> Community Announcements</h2>
                    <p class="text-muted mb-0">Stay updated with important announcements, reminders, guidelines, and alerts from our team</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="row mb-4">
        <div class="col-12">
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
            </div>
        </div>
    </div>

    <!-- Search Bar -->
    <div class="row mb-3">
        <div class="col-md-8 mx-auto">
            <div class="input-group">
                <span class="input-group-text">
                    <i class="bi bi-search"></i>
                </span>
                <input type="text" class="form-control" id="searchInput" 
                       placeholder="Search announcements by title or content..." 
                       onkeyup="searchAnnouncements()">
                <button class="btn btn-outline-secondary" type="button" onclick="clearSearch()">
                    <i class="bi bi-x-circle"></i> Clear
                </button>
            </div>
            <small class="text-muted" id="searchResultsCount" style="display: none;"></small>
        </div>
    </div>

    <!-- Posts Container -->
    <div class="row">
        <div id="posts-container" class="col-12">
            <?php if (empty($posts)): ?>
                <div class="col-lg-8 mx-auto">
                    <div class="text-center py-5">
                        <i class="bi bi-megaphone display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No announcements yet</h4>
                        <p class="text-muted">Check back later for important updates from our team!</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($posts as $post): ?>
                    <div class="col-lg-8 mx-auto mb-4 announcement-card" data-announcement-type="<?= $post['type'] ?>" data-saved="<?= $post['is_saved'] ?>" data-shared="<?= $post['is_shared'] ?>">
                        <div class="card post-card" data-post-id="<?= $post['id'] ?>" data-type="<?= $post['type'] ?>">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div class="d-flex align-items-center">
                                    <img src="<?= !empty($post['profile_img']) ? '../uploads/profile_picture/' . $post['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                         class="rounded-circle me-2" width="40" height="40" alt="Profile">
                                    <div>
                                        <h6 class="mb-0"><?= htmlspecialchars($post['username']) ?></h6>
                                        <small class="text-muted">
                                            <?= date('M j, Y g:i A', strtotime($post['created_at'])) ?>
                                        </small>
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
                                        <span class="badge bg-success ms-1">
                                            <i class="bi bi-pin-angle-fill"></i> Pinned
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($post['is_shared']): ?>
                                        <span class="badge bg-info ms-1" title="You shared this post">
                                            <i class="bi bi-share-fill"></i> Shared by you
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-body">
                                <h5 class="card-title"><?= htmlspecialchars($post['title']) ?></h5>
                                <p class="card-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                                
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
                                                    <?php if ($index >= 5) break; ?>
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
                            </div>
                            
                            <div class="card-footer">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-danger like-btn <?= $post['is_liked'] ? 'active' : '' ?>" 
                                                    data-post-id="<?= $post['id'] ?>">
                                                <i class="bi bi-heart<?= $post['is_liked'] ? '-fill' : '' ?>"></i>
                                                <span class="likes-count"><?= $post['likes_count'] ?? 0 ?></span>
                                            </button>
                                            <button type="button" class="btn btn-outline-primary comment-btn" 
                                                    data-post-id="<?= $post['id'] ?>">
                                                <i class="bi bi-chat"></i>
                                                <span class="comments-count"><?= $post['comments_count'] ?? 0 ?></span>
                                            </button>
                                            <button type="button" class="btn btn-outline-success share-btn" 
                                                    data-post-id="<?= $post['id'] ?>">
                                                <i class="bi bi-share"></i>
                                                <span class="shares-count"><?= $post['shares_count'] ?? 0 ?></span>
                                            </button>
                                            <button type="button" class="btn btn-outline-warning save-btn <?= $post['is_saved'] ? 'active' : '' ?>" 
                                                    data-post-id="<?= $post['id'] ?>">
                                                <i class="bi bi-bookmark<?= $post['is_saved'] ? '-fill' : '' ?>"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <button class="btn btn-sm btn-info" onclick="viewPost(<?= $post['id'] ?>)">
                                            <i class="bi bi-eye"></i> View Details
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Comments Section -->
                                <div class="comments-section mt-3" id="comments-<?= $post['id'] ?>" style="display: none;">
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
</main>

<!-- View Post Details Modal -->
<div class="modal fade" id="viewPostModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Announcement Details</h5>
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
    flex: 1;
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
    padding: 12px 16px;
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

#imageViewerModal .modal-content {
    background: rgba(0,0,0,0.9);
}

/* Search box styling */
#searchInput {
    border: 2px solid #e4e6eb;
    transition: all 0.3s;
}

#searchInput:focus {
    border-color: #0d6efd;
    box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, 0.15);
}

#searchResultsCount {
    display: block;
    margin-top: 8px;
    font-style: italic;
    color: #0d6efd;
    font-size: 0.9rem;
}
</style>

<script>
// Global variables
let currentFilter = 'all';

// Tab switching - Filter by announcement type
document.querySelectorAll('.tab-item').forEach(tab => {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        currentFilter = this.dataset.filter;
        
        // Apply filtering
        filterAnnouncements();
    });
});

// Filter announcements function
function filterAnnouncements() {
    const cards = document.querySelectorAll('.announcement-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        const cardType = card.dataset.announcementType;
        const isSaved = card.dataset.saved == '1';
        const isShared = card.dataset.shared == '1';
        
        let shouldShow = false;
        
        if (currentFilter === 'all') {
            shouldShow = true;
        } else if (currentFilter === 'saved') {
            shouldShow = isSaved;
        } else if (currentFilter === 'shared') {
            shouldShow = isShared;
        } else {
            shouldShow = cardType === currentFilter;
        }
        
        if (shouldShow) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Re-apply search if active
    const searchInput = document.getElementById('searchInput').value;
    if (searchInput) {
        searchAnnouncements();
    }
}

// Search function
function searchAnnouncements() {
    const searchInput = document.getElementById('searchInput').value.toLowerCase();
    const cards = document.querySelectorAll('.announcement-card');
    let visibleCount = 0;
    
    cards.forEach(card => {
        // Check if card matches current filter (type, saved, or shared)
        const cardType = card.dataset.announcementType;
        const isSaved = card.dataset.saved == '1';
        const isShared = card.dataset.shared == '1';
        
        let matchesFilter = false;
        if (currentFilter === 'all') {
            matchesFilter = true;
        } else if (currentFilter === 'saved') {
            matchesFilter = isSaved;
        } else if (currentFilter === 'shared') {
            matchesFilter = isShared;
        } else {
            matchesFilter = cardType === currentFilter;
        }
        
        if (!matchesFilter) {
            card.style.display = 'none';
            return;
        }
        
        // Get searchable text
        const title = card.querySelector('.card-title').textContent.toLowerCase();
        const content = card.querySelector('.card-text').textContent.toLowerCase();
        
        // Check if search matches
        const matchesSearch = searchInput === '' || 
                            title.includes(searchInput) || 
                            content.includes(searchInput);
        
        if (matchesSearch) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Update search results count
    const countElement = document.getElementById('searchResultsCount');
    if (searchInput !== '') {
        countElement.textContent = `Found ${visibleCount} announcement(s)`;
        countElement.style.display = 'block';
    } else {
        countElement.textContent = '';
        countElement.style.display = 'none';
    }
}

// Clear search
function clearSearch() {
    document.getElementById('searchInput').value = '';
    searchAnnouncements();
}

// View post details
function viewPost(id) {
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=get_post_details&id=${id}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displayPostDetails(data.data);
        } else {
            showNotification(data.message, 'error');
        }
    });
}

function displayPostDetails(post) {
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
                    ${post.is_pinned == 1 ? '<span class="badge bg-success"><i class="bi bi-pin-angle-fill"></i> Pinned</span>' : ''}
                </div>
                
                <hr>
                
                <div class="mb-4" style="white-space: pre-wrap;">${post.content}</div>
                
                ${attachmentsHTML}
                
                <hr>
                
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong><i class="bi bi-person-circle"></i> Posted by:</strong> ${post.full_name}</p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong><i class="bi bi-calendar"></i> Posted:</strong> ${new Date(post.created_at).toLocaleString()}</p>
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
        toggleLike(postId, btn);
    }
    
    // Comment button
    if (e.target.closest('.comment-btn')) {
        const btn = e.target.closest('.comment-btn');
        const postId = btn.dataset.postId;
        toggleCommentsSection(postId);
    }
    
    // Share button
    if (e.target.closest('.share-btn')) {
        const btn = e.target.closest('.share-btn');
        const postId = btn.dataset.postId;
        sharePost(postId, btn);
    }
    
    // Save button
    if (e.target.closest('.save-btn')) {
        const btn = e.target.closest('.save-btn');
        const postId = btn.dataset.postId;
        const card = btn.closest('.announcement-card');
        toggleSave(postId, btn, card);
    }
    
    // Submit comment
    if (e.target.closest('.submit-comment')) {
        const btn = e.target.closest('.submit-comment');
        const card = btn.closest('.post-card');
        const postId = card.dataset.postId;
        const input = card.querySelector('.comment-input');
        submitComment(postId, input.value, card);
    }
});

function toggleLike(postId, btn) {
    const formData = new FormData();
    formData.append('action', 'toggle_like');
    formData.append('post_id', postId);
    
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

function toggleCommentsSection(postId) {
    const section = document.getElementById(`comments-${postId}`);
    if (section.style.display === 'none') {
        section.style.display = 'block';
        loadComments(postId);
    } else {
        section.style.display = 'none';
    }
}

function loadComments(postId) {
    const formData = new FormData();
    formData.append('action', 'get_comments');
    formData.append('post_id', postId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const section = document.getElementById(`comments-${postId}`);
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
            
            if (data.comments.length === 0) {
                list.innerHTML = '<p class="text-muted text-center">No comments yet. Be the first to comment!</p>';
            }
        }
    });
}

function submitComment(postId, comment, card) {
    if (!comment.trim()) {
        showNotification('Please enter a comment.', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_comment');
    formData.append('post_id', postId);
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
            loadComments(postId);
            showNotification(data.message, 'success');
        } else {
            showNotification(data.message, 'error');
        }
    });
}

function sharePost(postId, btn) {
    const message = prompt('Add a message to your share (optional):');
    if (message === null) return; // User cancelled
    
    const formData = new FormData();
    formData.append('action', 'share_post');
    formData.append('post_id', postId);
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
            
            // Mark card as shared
            const card = btn.closest('.announcement-card');
            if (card) {
                card.dataset.shared = '1';
                
                // Add shared badge to card header if not already there
                const cardHeader = card.querySelector('.card-header > div:last-child');
                if (cardHeader && !cardHeader.querySelector('.badge.bg-info:has(.bi-share-fill)')) {
                    const sharedBadge = document.createElement('span');
                    sharedBadge.className = 'badge bg-info ms-1';
                    sharedBadge.title = 'You shared this post';
                    sharedBadge.innerHTML = '<i class="bi bi-share-fill"></i> Shared by you';
                    cardHeader.appendChild(sharedBadge);
                }
            }
            
            showNotification(data.message, 'success');
        }
    });
}

function toggleSave(postId, btn, card) {
    const formData = new FormData();
    formData.append('action', 'save_post');
    formData.append('post_id', postId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const icon = btn.querySelector('i');
            if (data.saved) {
                btn.classList.add('active');
                icon.classList.remove('bi-bookmark');
                icon.classList.add('bi-bookmark-fill');
                card.dataset.saved = '1';
            } else {
                btn.classList.remove('active');
                icon.classList.remove('bi-bookmark-fill');
                icon.classList.add('bi-bookmark');
                card.dataset.saved = '0';
            }
            showNotification(data.message, data.saved ? 'success' : 'info');
        }
    });
}

// Image viewer
function openImageModal(imageSrc) {
    document.getElementById('imageViewerImg').src = imageSrc;
    new bootstrap.Modal(document.getElementById('imageViewerModal')).show();
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
</script>

<?php include 'footer.php'; ?>

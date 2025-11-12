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

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $response = ['success' => false];
    
    try {
        if ($action === 'delete_post') {
            $post_id = intval($_POST['post_id']);
            $post_type = $_POST['post_type'];
            
            // Delete from appropriate table
            if ($post_type === 'recipe' || $post_type === 'tip') {
                $stmt = $conn->prepare("DELETE FROM recipes_tips WHERE id = ?");
                $stmt->bind_param('i', $post_id);
            } elseif ($post_type === 'meal_plan') {
                $stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ?");
                $stmt->bind_param('i', $post_id);
            }
            
            if ($stmt && $stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Post deleted successfully!';
            }
            if ($stmt) $stmt->close();
            
        } elseif ($action === 'toggle_visibility') {
            $post_id = intval($_POST['post_id']);
            $post_type = $_POST['post_type'];
            $is_public = intval($_POST['is_public']);
            
            if ($post_type === 'recipe' || $post_type === 'tip') {
                $stmt = $conn->prepare("UPDATE recipes_tips SET is_public = ? WHERE id = ?");
                $stmt->bind_param('ii', $is_public, $post_id);
            } elseif ($post_type === 'meal_plan') {
                $stmt = $conn->prepare("UPDATE meal_plans SET is_public = ? WHERE id = ?");
                $stmt->bind_param('ii', $is_public, $post_id);
            }
            
            if ($stmt && $stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Visibility updated successfully!';
            }
            if ($stmt) $stmt->close();
            
        } elseif ($action === 'feature_post') {
            $post_id = intval($_POST['post_id']);
            $post_type = $_POST['post_type'];
            $featured = intval($_POST['featured']);
            
            // Check if featured column exists, if not create it
            if ($post_type === 'recipe' || $post_type === 'tip') {
                $check = $conn->query("SHOW COLUMNS FROM recipes_tips LIKE 'featured'");
                if (!$check || $check->num_rows === 0) {
                    $conn->query("ALTER TABLE recipes_tips ADD COLUMN featured BOOLEAN DEFAULT FALSE");
                }
                $stmt = $conn->prepare("UPDATE recipes_tips SET featured = ? WHERE id = ?");
                $stmt->bind_param('ii', $featured, $post_id);
            }
            
            if ($stmt && $stmt->execute()) {
                $response['success'] = true;
                $response['message'] = 'Featured status updated!';
            }
            if ($stmt) $stmt->close();
        }
        
    } catch (Exception $e) {
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    
    echo json_encode($response);
    exit();
}

// Get statistics
$stats = [
    'total_posts' => 0,
    'recipes' => 0,
    'tips' => 0,
    'meal_plans' => 0,
    'total_engagement' => 0
];

try {
    // Check if tables exist
    $recipes_table_exists = false;
    $meal_plans_table_exists = false;
    
    $check = $conn->query("SHOW TABLES LIKE 'recipes_tips'");
    if ($check && $check->num_rows > 0) {
        $recipes_table_exists = true;
    }
    
    $check = $conn->query("SHOW TABLES LIKE 'meal_plans'");
    if ($check && $check->num_rows > 0) {
        $meal_plans_table_exists = true;
    }
    
    // Get stats from recipes_tips
    if ($recipes_table_exists) {
        $result = $conn->query("
            SELECT 
                COUNT(*) as total,
                COUNT(CASE WHEN post_type = 'recipe' THEN 1 END) as recipes,
                COUNT(CASE WHEN post_type = 'tip' THEN 1 END) as tips,
                SUM(likes_count) as total_likes,
                SUM(comments_count) as total_comments,
                SUM(shares_count) as total_shares
            FROM recipes_tips
        ");
        
        if ($result) {
            $data = $result->fetch_assoc();
            $stats['recipes'] = $data['recipes'] ?? 0;
            $stats['tips'] = $data['tips'] ?? 0;
            $stats['total_engagement'] = ($data['total_likes'] ?? 0) + ($data['total_comments'] ?? 0) + ($data['total_shares'] ?? 0);
        }
    }
    
    // Get meal plans count
    if ($meal_plans_table_exists) {
        $result = $conn->query("SELECT COUNT(*) as count FROM meal_plans");
        if ($result) {
            $data = $result->fetch_assoc();
            $stats['meal_plans'] = $data['count'] ?? 0;
        }
    }
    
    $stats['total_posts'] = $stats['recipes'] + $stats['tips'] + $stats['meal_plans'];
    
    // Get filter
    $filter_type = isset($_GET['type']) ? $_GET['type'] : '';
    
    // Get all posts
    $all_posts = [];
    
    if ($recipes_table_exists) {
        $query = "
            SELECT rt.*, ua.full_name, ua.profile_img, ua.email, 'recipe_tip' as table_source
            FROM recipes_tips rt
            JOIN user_accounts ua ON rt.user_id = ua.user_id
        ";
        
        if ($filter_type && $filter_type !== 'all') {
            if ($filter_type === 'recipe' || $filter_type === 'tip') {
                $query .= " WHERE rt.post_type = '$filter_type'";
            }
        }
        
        $query .= " ORDER BY rt.created_at DESC LIMIT 100";
        
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $all_posts[] = $row;
            }
        }
    }
    
    if ($meal_plans_table_exists && ($filter_type === '' || $filter_type === 'all' || $filter_type === 'meal_plan')) {
        $result = $conn->query("
            SELECT mp.*, ua.full_name, ua.profile_img, ua.email, 
                   'meal_plan' as post_type, 'meal_plan' as table_source
            FROM meal_plans mp
            JOIN user_accounts ua ON mp.user_id = ua.user_id
            ORDER BY mp.created_at DESC
            LIMIT 50
        ");
        
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $all_posts[] = $row;
            }
        }
    }
    
    // Sort all posts by date
    usort($all_posts, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Get top contributors
    $top_contributors = [];
    if ($recipes_table_exists) {
        $result = $conn->query("
            SELECT ua.user_id, ua.full_name, ua.profile_img,
                   COUNT(*) as post_count,
                   SUM(rt.likes_count) as total_likes
            FROM user_accounts ua
            JOIN recipes_tips rt ON ua.user_id = rt.user_id
            WHERE ua.role = 'resident'
            GROUP BY ua.user_id
            ORDER BY post_count DESC, total_likes DESC
            LIMIT 10
        ");
        
        if ($result) {
            $top_contributors = $result->fetch_all(MYSQLI_ASSOC);
        }
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
        <h1><i class="bi bi-newspaper"></i> Newsfeeds Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Community</li>
                <li class="breadcrumb-item active">Newsfeeds</li>
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
                                <h6 class="text-muted mb-2">Total Posts</h6>
                                <h3 class="mb-0"><?= number_format($stats['total_posts']) ?></h3>
                            </div>
                            <div class="stat-icon bg-primary">
                                <i class="bi bi-file-post"></i>
                            </div>
                        </div>
                        <small class="text-muted">All newsfeed content</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Recipes</h6>
                                <h3 class="mb-0 text-success"><?= number_format($stats['recipes']) ?></h3>
                            </div>
                            <div class="stat-icon bg-success">
                                <i class="bi bi-book"></i>
                            </div>
                        </div>
                        <small class="text-muted">Recipe posts</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Tips</h6>
                                <h3 class="mb-0 text-warning"><?= number_format($stats['tips']) ?></h3>
                            </div>
                            <div class="stat-icon bg-warning">
                                <i class="bi bi-lightbulb"></i>
                            </div>
                        </div>
                        <small class="text-muted">Tip posts</small>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6 mb-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted mb-2">Engagement</h6>
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
            <!-- Main Posts List -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">All Newsfeeds</h5>
                            <div class="btn-group">
                                <button class="btn btn-success" onclick="exportNewsfeeds()">
                                    <i class="bi bi-file-earmark-excel"></i> Export
                                </button>
                                <button class="btn btn-primary" onclick="refreshData()">
                                    <i class="bi bi-arrow-clockwise"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Filters -->
                        <div class="mb-3">
                            <div class="btn-group me-2" role="group">
                                <a href="admin-newsfeeds.php" class="btn btn-sm btn-outline-primary <?= $filter_type === '' ? 'active' : '' ?>">
                                    All (<?= $stats['total_posts'] ?>)
                                </a>
                                <a href="admin-newsfeeds.php?type=recipe" class="btn btn-sm btn-outline-success <?= $filter_type === 'recipe' ? 'active' : '' ?>">
                                    Recipes (<?= $stats['recipes'] ?>)
                                </a>
                                <a href="admin-newsfeeds.php?type=tip" class="btn btn-sm btn-outline-warning <?= $filter_type === 'tip' ? 'active' : '' ?>">
                                    Tips (<?= $stats['tips'] ?>)
                                </a>
                                <a href="admin-newsfeeds.php?type=meal_plan" class="btn btn-sm btn-outline-info <?= $filter_type === 'meal_plan' ? 'active' : '' ?>">
                                    Meal Plans (<?= $stats['meal_plans'] ?>)
                                </a>
                            </div>
                        </div>

                        <!-- Search -->
                        <div class="mb-3">
                            <input type="text" class="form-control" id="searchInput" 
                                   placeholder="Search posts by title, content, or author..." 
                                   onkeyup="searchPosts()">
                        </div>

                        <!-- Posts List -->
                        <div class="posts-container">
                            <?php if (!empty($all_posts)): ?>
                                <?php foreach ($all_posts as $post): ?>
                                    <div class="post-card mb-3 post-row">
                                        <div class="d-flex align-items-start">
                                            <img src="<?= !empty($post['profile_img']) ? '../uploads/profile_picture/' . $post['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                 class="rounded-circle me-3" width="48" height="48" alt="Profile">
                                            <div class="flex-grow-1">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="mb-0"><?= htmlspecialchars($post['full_name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($post['email']) ?></small>
                                                    </div>
                                                    <div>
                                                        <span class="badge bg-<?= $post['post_type'] === 'recipe' ? 'success' : ($post['post_type'] === 'tip' ? 'warning' : 'info') ?>">
                                                            <?= ucfirst($post['post_type']) ?>
                                                        </span>
                                                        <small class="text-muted ms-2"><?= date('M j, Y g:i A', strtotime($post['created_at'])) ?></small>
                                                    </div>
                                                </div>
                                                
                                                <h5 class="mb-2"><?= htmlspecialchars($post['title'] ?? $post['plan_name'] ?? 'Untitled Post') ?></h5>
                                                <p class="text-muted mb-2">
                                                    <?= htmlspecialchars(substr($post['content'] ?? $post['description'] ?? $post['notes'] ?? '', 0, 150)) ?>...
                                                </p>
                                                
                                                <?php if ($post['post_type'] === 'recipe'): ?>
                                                    <div class="recipe-meta mb-2">
                                                        <?php if (!empty($post['cooking_time'])): ?>
                                                            <span class="badge bg-light text-dark me-1">
                                                                <i class="bi bi-clock"></i> <?= $post['cooking_time'] ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($post['difficulty_level'])): ?>
                                                            <span class="badge bg-light text-dark me-1">
                                                                <i class="bi bi-star"></i> <?= ucfirst($post['difficulty_level']) ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($post['servings'])): ?>
                                                            <span class="badge bg-light text-dark">
                                                                <i class="bi bi-people"></i> <?= $post['servings'] ?> servings
                                                            </span>
                                                        <?php endif; ?>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="engagement-stats mb-2">
                                                    <span class="me-3" title="Likes">
                                                        <i class="bi bi-heart-fill text-danger"></i> <?= number_format($post['likes_count'] ?? $post['like_count'] ?? 0) ?>
                                                    </span>
                                                    <span class="me-3" title="Comments">
                                                        <i class="bi bi-chat-fill text-primary"></i> <?= number_format($post['comments_count'] ?? $post['comment_count'] ?? 0) ?>
                                                    </span>
                                                    <span class="me-3" title="Shares">
                                                        <i class="bi bi-share-fill text-success"></i> <?= number_format($post['shares_count'] ?? $post['share_count'] ?? 0) ?>
                                                    </span>
                                                    <span title="Views">
                                                        <i class="bi bi-eye-fill text-info"></i> <?= number_format($post['views_count'] ?? $post['view_count'] ?? 0) ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="post-actions">
                                                    <button class="btn btn-sm btn-outline-info me-1" 
                                                            onclick='viewPost(<?= json_encode($post, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                    <?php if (isset($post['is_public'])): ?>
                                                        <button class="btn btn-sm btn-outline-<?= $post['is_public'] ? 'success' : 'secondary' ?> me-1" 
                                                                onclick="toggleVisibility(<?= $post['id'] ?>, '<?= $post['post_type'] ?>', <?= $post['is_public'] ? 0 : 1 ?>)">
                                                            <i class="bi bi-<?= $post['is_public'] ? 'eye' : 'eye-slash' ?>"></i>
                                                            <?= $post['is_public'] ? 'Public' : 'Private' ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <?php if ($post['table_source'] === 'recipe_tip'): ?>
                                                        <button class="btn btn-sm btn-outline-warning me-1" 
                                                                onclick="featurePost(<?= $post['id'] ?>, '<?= $post['post_type'] ?>', <?= isset($post['featured']) && $post['featured'] ? 0 : 1 ?>)">
                                                            <i class="bi bi-star"></i>
                                                            <?= isset($post['featured']) && $post['featured'] ? 'Unfeature' : 'Feature' ?>
                                                        </button>
                                                    <?php endif; ?>
                                                    <button class="btn btn-sm btn-outline-danger" 
                                                            onclick="deletePost(<?= $post['id'] ?>, '<?= $post['post_type'] ?>')">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-info text-center">
                                    <i class="bi bi-info-circle" style="font-size: 3rem;"></i>
                                    <p class="mt-3 mb-0">No newsfeed posts found</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-lg-4">
                <!-- Top Contributors -->
                <div class="card mb-3">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-star"></i> Top Contributors
                        </h5>
                        <div class="list-group list-group-flush">
                            <?php if (!empty($top_contributors)): ?>
                                <?php 
                                $rank = 1;
                                foreach ($top_contributors as $user): 
                                ?>
                                    <div class="list-group-item px-0">
                                        <div class="d-flex align-items-center">
                                            <span class="badge <?= $rank <= 3 ? 'bg-warning' : 'bg-secondary' ?> me-2">
                                                #<?= $rank ?>
                                            </span>
                                            <img src="<?= !empty($user['profile_img']) ? '../uploads/profile_picture/' . $user['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                                 class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                            <div class="flex-grow-1">
                                                <strong><?= htmlspecialchars($user['full_name']) ?></strong>
                                                <div class="small text-muted">
                                                    <?= $user['post_count'] ?> posts • <?= number_format($user['total_likes']) ?> likes
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

                <!-- Quick Stats -->
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-graph-up"></i> Content Breakdown
                        </h5>
                        <canvas id="contentChart" width="400" height="300"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- View Post Modal -->
<div class="modal fade" id="viewPostModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-post"></i> Post Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="postDetailsContent">
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

.post-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    transition: box-shadow 0.2s;
}

.post-card:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.engagement-stats {
    font-size: 0.9rem;
}

.recipe-meta .badge {
    font-weight: normal;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// Content Breakdown Chart
const ctx = document.getElementById('contentChart');
if (ctx) {
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['Recipes', 'Tips', 'Meal Plans'],
            datasets: [{
                data: [<?= $stats['recipes'] ?>, <?= $stats['tips'] ?>, <?= $stats['meal_plans'] ?>],
                backgroundColor: ['#198754', '#ffc107', '#0dcaf0']
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}

// View Post Details
function viewPost(post) {
    let content = `
        <div class="row">
            <div class="col-12 mb-3">
                <div class="d-flex align-items-center">
                    <img src="${post.profile_img ? '../uploads/profile_picture/' + post.profile_img : '../uploads/profile_picture/no_image.png'}" 
                         class="rounded-circle me-3" width="48" height="48">
                    <div>
                        <h6 class="mb-0">${post.full_name}</h6>
                        <small class="text-muted">${post.email}</small>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Post ID:</strong> #${post.id}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Type:</strong> <span class="badge bg-${post.post_type === 'recipe' ? 'success' : (post.post_type === 'tip' ? 'warning' : 'info')}">${post.post_type.toUpperCase()}</span>
            </div>
            <div class="col-md-6 mb-3">
                <strong>Posted:</strong> ${new Date(post.created_at).toLocaleString()}
            </div>
            <div class="col-md-6 mb-3">
                <strong>Visibility:</strong> ${post.is_public ? '<span class="badge bg-success">Public</span>' : '<span class="badge bg-secondary">Private</span>'}
            </div>
            <div class="col-12 mb-3">
                <strong>Title:</strong>
                <div class="alert alert-light mt-2">${post.title || post.plan_name || 'Untitled Post'}</div>
            </div>
            <div class="col-12 mb-3">
                <strong>Content:</strong>
                <div class="alert alert-light mt-2">${post.content || post.description || post.notes || 'No content'}</div>
            </div>
    `;
    
    if (post.post_type === 'recipe') {
        if (post.ingredients) {
            content += `
                <div class="col-12 mb-3">
                    <strong>Ingredients:</strong>
                    <div class="alert alert-light mt-2">${post.ingredients}</div>
                </div>
            `;
        }
        if (post.instructions) {
            content += `
                <div class="col-12 mb-3">
                    <strong>Instructions:</strong>
                    <div class="alert alert-light mt-2">${post.instructions}</div>
                </div>
            `;
        }
        if (post.cooking_time || post.difficulty_level || post.servings) {
            content += `<div class="col-12 mb-3"><strong>Details:</strong><br>`;
            if (post.cooking_time) content += `<span class="badge bg-light text-dark me-1">⏱ ${post.cooking_time}</span>`;
            if (post.difficulty_level) content += `<span class="badge bg-light text-dark me-1">⭐ ${post.difficulty_level}</span>`;
            if (post.servings) content += `<span class="badge bg-light text-dark">👥 ${post.servings} servings</span>`;
            content += `</div>`;
        }
    }
    
    content += `
            <div class="col-12 mb-3">
                <strong>Engagement:</strong><br>
                <span class="me-3"><i class="bi bi-heart-fill text-danger"></i> ${post.likes_count || 0} Likes</span>
                <span class="me-3"><i class="bi bi-chat-fill text-primary"></i> ${post.comments_count || 0} Comments</span>
                <span class="me-3"><i class="bi bi-share-fill text-success"></i> ${post.shares_count || 0} Shares</span>
                <span><i class="bi bi-eye-fill text-info"></i> ${post.views_count || 0} Views</span>
            </div>
        </div>
    `;
    
    document.getElementById('postDetailsContent').innerHTML = content;
    new bootstrap.Modal(document.getElementById('viewPostModal')).show();
}

// Toggle Visibility
function toggleVisibility(postId, postType, isPublic) {
    const formData = new FormData();
    formData.append('action', 'toggle_visibility');
    formData.append('post_id', postId);
    formData.append('post_type', postType);
    formData.append('is_public', isPublic);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to update', 'error');
        }
    });
}

// Feature Post
function featurePost(postId, postType, featured) {
    const formData = new FormData();
    formData.append('action', 'feature_post');
    formData.append('post_id', postId);
    formData.append('post_type', postType);
    formData.append('featured', featured);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to update', 'error');
        }
    });
}

// Delete Post
function deletePost(postId, postType) {
    if (!confirm('Delete this post? This action cannot be undone!')) return;
    
    const formData = new FormData();
    formData.append('action', 'delete_post');
    formData.append('post_id', postId);
    formData.append('post_type', postType);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => window.location.reload(), 1000);
        } else {
            showNotification(data.message || 'Failed to delete', 'error');
        }
    });
}

// Search Posts
function searchPosts() {
    const searchTerm = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('.post-row');
    
    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(searchTerm) ? '' : 'none';
    });
}

// Export Newsfeeds
function exportNewsfeeds() {
    const data = <?= json_encode($all_posts) ?>;
    
    if (data.length === 0) {
        alert('No data to export');
        return;
    }
    
    const headers = ['ID', 'Type', 'Title', 'Author', 'Email', 'Likes', 'Comments', 'Shares', 'Views', 'Posted Date'];
    let csv = headers.join(',') + '\n';
    
    data.forEach(row => {
        const values = [
            row.id,
            row.post_type,
            `"${row.title}"`,
            `"${row.full_name}"`,
            `"${row.email}"`,
            row.likes_count || 0,
            row.comments_count || 0,
            row.shares_count || 0,
            row.views_count || 0,
            row.created_at
        ];
        csv += values.join(',') + '\n';
    });
    
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'newsfeeds_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Refresh Data
function refreshData() {
    window.location.reload();
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


<?php
include '../config/db.php';
include_once 'challenge_hooks.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle AJAX requests BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_post':
                // Check if user is logged in
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $post_type = $_POST['post_type'] ?? 'recipe';
                $title = trim($_POST['title'] ?? '');                                                                                                                                                                                                           
                $content = trim($_POST['content'] ?? '');
                $ingredients = $_POST['ingredients'] ?? '';
                $instructions = trim($_POST['instructions'] ?? '');
                $cooking_time = (int)($_POST['cooking_time'] ?? 0);
                $difficulty_level = $_POST['difficulty_level'] ?? 'Easy';
                $servings = (int)($_POST['servings'] ?? 1);
                $calories_per_serving = (int)($_POST['calories_per_serving'] ?? 0);
                $tags = $_POST['tags'] ?? '';
                $is_public = isset($_POST['is_public']) ? 1 : 0;
                
                if (empty($title) || empty($content)) {
                    echo json_encode(['success' => false, 'message' => 'Title and content are required.']);
                    exit;
                }
                
                try {
                    $stmt = $conn->prepare("INSERT INTO recipes_tips (user_id, post_type, title, content, ingredients, instructions, cooking_time, difficulty_level, servings, calories_per_serving, tags, is_public) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->bind_param('isssssisssii', 
                        $_SESSION['user_id'], 
                        $post_type, 
                        $title, 
                        $content, 
                        $ingredients, 
                        $instructions, 
                        $cooking_time, 
                        $difficulty_level, 
                        $servings, 
                        $calories_per_serving, 
                        $tags, 
                        $is_public
                    );
                    
                    if ($stmt->execute()) {
                        // Trigger challenge progress update if it's a recipe
                        if ($post_type === 'recipe') {
                            triggerRecipeChallenge($conn, $_SESSION['user_id']);
                        }
                        
                        echo json_encode(['success' => true, 'message' => 'Post created successfully!', 'post_id' => $conn->insert_id]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to create post: ' . $stmt->error]);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'toggle_like':
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'recipe';
                
                // Determine the correct post_type
                if ($post_type !== 'meal_plan') {
                    // For recipes and tips, get the actual post_type from the recipes_tips table
                    $stmt = $conn->prepare("SELECT post_type FROM recipes_tips WHERE id = ?");
                    $stmt->bind_param('i', $post_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $post_type = $row['post_type']; // This will be 'recipe' or 'tip'
                    } else {
                        // If not found in recipes_tips, it might be a meal_plan
                        $post_type = 'meal_plan';
                    }
                    $stmt->close();
                }
                
                try {
                    if ($post_type === 'meal_plan') {
                        // For meal plans, implement like system using recipe_tip_likes table
                        $stmt = $conn->prepare("SELECT id FROM recipe_tip_likes WHERE post_id = ? AND post_type = ? AND user_id = ?");
                        $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            // Unlike the meal plan
                            $stmt->close();
                            $stmt = $conn->prepare("DELETE FROM recipe_tip_likes WHERE post_id = ? AND post_type = ? AND user_id = ?");
                            $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                            $stmt->execute();
                            
                            echo json_encode(['success' => true, 'liked' => false, 'message' => 'Meal plan unliked']);
                        } else {
                            // Like the meal plan
                            $stmt->close();
                            $stmt = $conn->prepare("INSERT INTO recipe_tip_likes (post_id, post_type, user_id) VALUES (?, ?, ?)");
                            $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                            $stmt->execute();
                            
                            echo json_encode(['success' => true, 'liked' => true, 'message' => 'Meal plan liked!']);
                        }
                        $stmt->close();
                    } else {
                        // Check if user already liked this post
                        $stmt = $conn->prepare("SELECT id FROM recipe_tip_likes WHERE post_id = ? AND post_type = ? AND user_id = ?");
                        $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($result->num_rows > 0) {
                            // Unlike the post
                            $stmt->close();
                            $stmt = $conn->prepare("DELETE FROM recipe_tip_likes WHERE post_id = ? AND post_type = ? AND user_id = ?");
                            $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                            $stmt->execute();
                            
                            // Update likes count
                            $stmt->close();
                            $stmt = $conn->prepare("UPDATE recipes_tips SET likes_count = likes_count - 1 WHERE id = ?");
                            $stmt->bind_param('i', $post_id);
                            $stmt->execute();
                            
                            echo json_encode(['success' => true, 'liked' => false, 'message' => 'Post unliked']);
                        } else {
                            // Like the post
                            $stmt->close();
                            $stmt = $conn->prepare("INSERT INTO recipe_tip_likes (post_id, post_type, user_id) VALUES (?, ?, ?)");
                            $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                            $stmt->execute();
                            
                            // Update likes count
                            $stmt->close();
                            $stmt = $conn->prepare("UPDATE recipes_tips SET likes_count = likes_count + 1 WHERE id = ?");
                            $stmt->bind_param('i', $post_id);
                            $stmt->execute();
                            
                            echo json_encode(['success' => true, 'liked' => true, 'message' => 'Post liked']);
                        }
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'share_post':
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'recipe';
                
                // Determine the correct post_type
                if ($post_type !== 'meal_plan') {
                    // For recipes and tips, get the actual post_type from the recipes_tips table
                    $stmt = $conn->prepare("SELECT post_type FROM recipes_tips WHERE id = ?");
                    $stmt->bind_param('i', $post_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $post_type = $row['post_type']; // This will be 'recipe' or 'tip'
                    } else {
                        // If not found in recipes_tips, it might be a meal_plan
                        $post_type = 'meal_plan';
                    }
                    $stmt->close();
                }
                
                $share_message = trim($_POST['share_message'] ?? '');
                
                try {
                    if ($post_type === 'meal_plan') {
                        // For meal plans, we'll use the existing share token system
                        $stmt = $conn->prepare("SELECT share_token, plan_name FROM meal_plans WHERE id = ?");
                        $stmt->bind_param('i', $post_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        if ($meal_plan = $result->fetch_assoc()) {
                            $share_url = "http://" . $_SERVER['HTTP_HOST'] . "/foodify/residents/saved_plans.php?shared=" . $meal_plan['share_token'];
                            
                            // Store share in recipe_tip_shares table for tracking
                            $stmt->close();
                            $stmt = $conn->prepare("INSERT INTO recipe_tip_shares (post_id, post_type, user_id, share_message) VALUES (?, ?, ?, ?)");
                            $stmt->bind_param('isis', $post_id, $post_type, $_SESSION['user_id'], $share_message);
                            $stmt->execute();
                            
                            echo json_encode([
                                'success' => true, 
                                'message' => 'Meal plan shared successfully!',
                                'share_url' => $share_url,
                                'plan_name' => $meal_plan['plan_name']
                            ]);
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Meal plan not found.']);
                        }
                        $stmt->close();
                    } else {
                        $stmt = $conn->prepare("INSERT INTO recipe_tip_shares (post_id, post_type, user_id, share_message) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param('isis', $post_id, $post_type, $_SESSION['user_id'], $share_message);
                        $stmt->execute();
                        
                        // Update shares count
                        $stmt->close();
                        $stmt = $conn->prepare("UPDATE recipes_tips SET shares_count = shares_count + 1 WHERE id = ?");
                        $stmt->bind_param('i', $post_id);
                        $stmt->execute();
                        
                        echo json_encode(['success' => true, 'message' => 'Post shared successfully!']);
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'save_post':
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'recipe';
                
                // Determine the correct post_type
                if ($post_type === 'meal_plan') {
                    // For meal plans, keep as is
                    $post_type = 'meal_plan';
                } else {
                    // For recipes and tips, get the actual post_type from the recipes_tips table
                    $stmt = $conn->prepare("SELECT post_type FROM recipes_tips WHERE id = ?");
                    $stmt->bind_param('i', $post_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $post_type = $row['post_type']; // This will be 'recipe' or 'tip'
                    } else {
                        // If not found in recipes_tips, it might be a meal_plan
                        $post_type = 'meal_plan';
                    }
                    $stmt->close();
                }
                
                try {
                    // Check if already saved
                    $stmt = $conn->prepare("SELECT id FROM recipe_tip_saves WHERE post_id = ? AND post_type = ? AND user_id = ?");
                    $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows > 0) {
                        // Remove from saved
                        $stmt->close();
                        $stmt = $conn->prepare("DELETE FROM recipe_tip_saves WHERE post_id = ? AND post_type = ? AND user_id = ?");
                        $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                        $stmt->execute();
                        echo json_encode(['success' => true, 'saved' => false, 'message' => 'Post removed from saved']);
                    } else {
                        // Add to saved
                        $stmt->close();
                        $stmt = $conn->prepare("INSERT INTO recipe_tip_saves (post_id, post_type, user_id) VALUES (?, ?, ?)");
                        $stmt->bind_param('isi', $post_id, $post_type, $_SESSION['user_id']);
                        $stmt->execute();
                        echo json_encode(['success' => true, 'saved' => true, 'message' => 'Post saved successfully!']);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'add_comment':
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'recipe';
                
                // Determine the correct post_type
                if ($post_type !== 'meal_plan') {
                    // For recipes and tips, get the actual post_type from the recipes_tips table
                    $stmt = $conn->prepare("SELECT post_type FROM recipes_tips WHERE id = ?");
                    $stmt->bind_param('i', $post_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $post_type = $row['post_type']; // This will be 'recipe' or 'tip'
                    } else {
                        // If not found in recipes_tips, it might be a meal_plan
                        $post_type = 'meal_plan';
                    }
                    $stmt->close();
                }
                
                $comment = trim($_POST['comment'] ?? '');
                
                if (empty($comment)) {
                    echo json_encode(['success' => false, 'message' => 'Comment cannot be empty.']);
                    exit;
                }
                
                try {
                    if ($post_type === 'meal_plan') {
                        // For meal plans, store comments in recipe_tip_comments table with post_type
                        $stmt = $conn->prepare("INSERT INTO recipe_tip_comments (post_id, post_type, user_id, comment) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param('isis', $post_id, $post_type, $_SESSION['user_id'], $comment);
                        $stmt->execute();
                        $stmt->close();
                        
                        // Trigger community challenge progress update
                        triggerCommunityChallenge($conn, $_SESSION['user_id']);
                        
                        echo json_encode(['success' => true, 'message' => 'Comment added successfully!']);
                    } else {
                        $stmt = $conn->prepare("INSERT INTO recipe_tip_comments (post_id, post_type, user_id, comment) VALUES (?, ?, ?, ?)");
                        $stmt->bind_param('isis', $post_id, $post_type, $_SESSION['user_id'], $comment);
                        $stmt->execute();
                        
                        // Update comments count
                        $stmt->close();
                        $stmt = $conn->prepare("UPDATE recipes_tips SET comments_count = comments_count + 1 WHERE id = ?");
                        $stmt->bind_param('i', $post_id);
                        $stmt->execute();
                        
                        // Trigger community challenge progress update
                        triggerCommunityChallenge($conn, $_SESSION['user_id']);
                        
                        echo json_encode(['success' => true, 'message' => 'Comment added successfully!']);
                        $stmt->close();
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'get_comments':
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $post_id = (int)$_POST['post_id'];
                $post_type = $_POST['post_type'] ?? 'recipe';
                $page = (int)($_POST['page'] ?? 1);
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                // Determine the correct post_type
                if ($post_type !== 'meal_plan') {
                    // For recipes and tips, get the actual post_type from the recipes_tips table
                    $stmt = $conn->prepare("SELECT post_type FROM recipes_tips WHERE id = ?");
                    $stmt->bind_param('i', $post_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($result->num_rows > 0) {
                        $row = $result->fetch_assoc();
                        $post_type = $row['post_type']; // This will be 'recipe' or 'tip'
                    } else {
                        // If not found in recipes_tips, it might be a meal_plan
                        $post_type = 'meal_plan';
                    }
                    $stmt->close();
                }
                
                try {
                    // Get total count
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as total 
                        FROM recipe_tip_comments 
                        WHERE post_id = ? AND post_type = ?
                    ");
                    $stmt->bind_param('is', $post_id, $post_type);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $total_count = $result->fetch_assoc()['total'];
                    $stmt->close();
                    
                    // Get comments with pagination
                    $stmt = $conn->prepare("
                        SELECT c.*, u.full_name, u.profile_img 
                        FROM recipe_tip_comments c 
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
                        'has_more' => $has_more,
                        'per_page' => $limit
                    ]);
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
                
            case 'get_bookmarks':
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $page = (int)($_POST['page'] ?? 1);
                $limit = 10;
                $offset = ($page - 1) * $limit;
                
                try {
                    // Get bookmarked recipes and tips
                    $stmt = $conn->prepare("
                        SELECT r.*, u.username, u.profile_img, r.post_type,
                        CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END as is_liked,
                        1 as is_saved,
                        s.created_at as bookmarked_at
                        FROM recipes_tips r
                        INNER JOIN recipe_tip_saves s ON r.id = s.post_id AND s.post_type = r.post_type AND s.user_id = ?
                        LEFT JOIN user_accounts u ON r.user_id = u.user_id
                        LEFT JOIN recipe_tip_likes l ON r.id = l.post_id AND l.post_type = r.post_type AND l.user_id = ?
                        WHERE r.is_public = 1
                        ORDER BY s.created_at DESC
                        LIMIT ? OFFSET ?
                    ");
                    $stmt->bind_param('iiii', $_SESSION['user_id'], $_SESSION['user_id'], $limit, $offset);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $posts = [];
                    while ($post = $result->fetch_assoc()) {
                        $posts[] = $post;
                    }
                    
                    // Get bookmarked meal plans
                    $stmt2 = $conn->prepare("
                        SELECT m.*, u.username, u.profile_img, 'meal_plan' as post_type,
                        CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END as is_liked,
                        1 as is_saved,
                        s.created_at as bookmarked_at,
                        COALESCE(like_counts.likes_count, 0) as likes_count,
                        COALESCE(share_counts.shares_count, 0) as shares_count,
                        COALESCE(comment_counts.comments_count, 0) as comments_count
                        FROM meal_plans m
                        INNER JOIN recipe_tip_saves s ON m.id = s.post_id AND s.post_type = 'meal_plan' AND s.user_id = ?
                        LEFT JOIN user_accounts u ON m.user_id = u.user_id
                        LEFT JOIN recipe_tip_likes l ON m.id = l.post_id AND l.post_type = 'meal_plan' AND l.user_id = ?
                        LEFT JOIN (
                            SELECT post_id, COUNT(*) as likes_count 
                            FROM recipe_tip_likes 
                            WHERE recipe_tip_likes.post_type = 'meal_plan' 
                            GROUP BY post_id
                        ) like_counts ON m.id = like_counts.post_id
                        LEFT JOIN (
                            SELECT post_id, COUNT(*) as shares_count 
                            FROM recipe_tip_shares 
                            WHERE recipe_tip_shares.post_type = 'meal_plan' 
                            GROUP BY post_id
                        ) share_counts ON m.id = share_counts.post_id
                        LEFT JOIN (
                            SELECT post_id, COUNT(*) as comments_count 
                            FROM recipe_tip_comments 
                            WHERE recipe_tip_comments.post_type = 'meal_plan' 
                            GROUP BY post_id
                        ) comment_counts ON m.id = comment_counts.post_id
                        WHERE m.is_shared = 1
                        ORDER BY s.created_at DESC
                        LIMIT ? OFFSET ?
                    ");
                    $stmt2->bind_param('iiii', $_SESSION['user_id'], $_SESSION['user_id'], $limit, $offset);
                    $stmt2->execute();
                    $result2 = $stmt2->get_result();
                    
                    while ($meal_plan = $result2->fetch_assoc()) {
                        // Convert meal plan data to match post structure
                        $meal_plan['title'] = $meal_plan['plan_name'];
                        
                        // Handle double-encoded JSON in plan_data
                        $decoded_data = json_decode($meal_plan['plan_data'], true);
                        if (is_string($decoded_data)) {
                            $meal_plan['plan_data'] = json_decode($decoded_data, true);
                        } else {
                            $meal_plan['plan_data'] = $decoded_data;
                        }
                        
                        if (is_array($meal_plan['plan_data'])) {
                            $meal_plan['content'] = 'A comprehensive 7-day meal plan with balanced nutrition.';
                            
                            // Calculate totals
                            $meal_plan['total_calories'] = 0;
                            $meal_plan['total_protein'] = 0;
                            $meal_plan['total_carbs'] = 0;
                            $meal_plan['total_fat'] = 0;
                            
                            foreach ($meal_plan['plan_data'] as $day => $meals) {
                                if (is_array($meals)) {
                                    foreach ($meals as $meal => $dishes) {
                                        if (is_array($dishes)) {
                                            foreach ($dishes as $dish) {
                                                if (isset($dish['calories'])) $meal_plan['total_calories'] += (int)$dish['calories'];
                                                if (isset($dish['protein'])) $meal_plan['total_protein'] += (float)$dish['protein'];
                                                if (isset($dish['carbs'])) $meal_plan['total_carbs'] += (float)$dish['carbs'];
                                                if (isset($dish['fat'])) $meal_plan['total_fat'] += (float)$dish['fat'];
                                            }
                                        }
                                    }
                                }
                            }
                        }
                        
                        $posts[] = $meal_plan;
                    }
                    
                    // Sort all posts by bookmark date (most recent first)
                    usort($posts, function($a, $b) {
                        $bookmarkA = isset($a['bookmarked_at']) ? $a['bookmarked_at'] : $a['created_at'];
                        $bookmarkB = isset($b['bookmarked_at']) ? $b['bookmarked_at'] : $b['created_at'];
                        return strtotime($bookmarkB) - strtotime($bookmarkA);
                    });
                    
                    echo json_encode(['success' => true, 'posts' => $posts]);
                    $stmt->close();
                    $stmt2->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'get_posts':
                // Check if user is logged in
                if (!isset($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $page = (int)($_POST['page'] ?? 1);
                $limit = 10;
                $offset = ($page - 1) * $limit;
                $post_type = $_POST['post_type'] ?? '';
                $user_id = $_POST['user_id'] ?? '';
                
                try {
                    $posts = [];
                    
                    if ($post_type === 'all' || $post_type === '') {
                        // Get all posts (recipes, tips, and meal plans)
                        
                        // First get recipes and tips
                        $stmt = $conn->prepare("
                            SELECT r.*, u.username, u.profile_img, r.post_type,
                            CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END as is_liked,
                            CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as is_saved,
                            COALESCE(like_counts.likes_count, 0) as likes_count,
                            COALESCE(share_counts.shares_count, 0) as shares_count,
                            COALESCE(comment_counts.comments_count, 0) as comments_count
                            FROM recipes_tips r
                            LEFT JOIN user_accounts u ON r.user_id = u.user_id
                            LEFT JOIN recipe_tip_likes l ON r.id = l.post_id AND l.post_type = r.post_type AND l.user_id = ?
                            LEFT JOIN recipe_tip_saves s ON r.id = s.post_id AND s.post_type = r.post_type AND l.user_id = ?
                            LEFT JOIN (
                                SELECT post_id, post_type, COUNT(*) as likes_count 
                                FROM recipe_tip_likes 
                                GROUP BY post_id, post_type
                            ) like_counts ON r.id = like_counts.post_id AND r.post_type = like_counts.post_type
                            LEFT JOIN (
                                SELECT post_id, post_type, COUNT(*) as shares_count 
                                FROM recipe_tip_shares 
                                GROUP BY post_id, post_type
                            ) share_counts ON r.id = share_counts.post_id AND r.post_type = share_counts.post_type
                            LEFT JOIN (
                                SELECT post_id, post_type, COUNT(*) as comments_count
                                FROM recipe_tip_comments 
                                GROUP BY post_id, post_type
                            ) comment_counts ON r.id = comment_counts.post_id AND r.post_type = comment_counts.post_type
                            WHERE r.is_public = 1
                            ORDER BY r.created_at DESC
                            LIMIT ? OFFSET ?
                        ");
                        $stmt->bind_param('iiii', $_SESSION['user_id'], $_SESSION['user_id'], $limit, $offset);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        while ($post = $result->fetch_assoc()) {
                            $posts[] = $post;
                        }
                        $stmt->close();
                        
                        // Then get meal plans (both shared and user's own for bookmarks)
                        $stmt2 = $conn->prepare("
                            SELECT m.*, u.username, u.profile_img, 'meal_plan' as post_type,
                            CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END as is_liked,
                            CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as is_saved,
                            COALESCE(like_counts.likes_count, 0) as likes_count,
                            COALESCE(share_counts.shares_count, 0) as shares_count,
                            COALESCE(comment_counts.comments_count, 0) as comments_count
                            FROM meal_plans m
                            LEFT JOIN user_accounts u ON m.user_id = u.user_id
                            LEFT JOIN recipe_tip_likes l ON m.id = l.post_id AND l.post_type = 'meal_plan' AND l.user_id = ?
                            LEFT JOIN recipe_tip_saves s ON m.id = s.post_id AND s.post_type = 'meal_plan' AND l.user_id = ?
                            LEFT JOIN (
                                SELECT post_id, COUNT(*) as likes_count 
                                FROM recipe_tip_likes 
                                WHERE recipe_tip_likes.post_type = 'meal_plan' 
                                GROUP BY post_id
                            ) like_counts ON m.id = like_counts.post_id
                            LEFT JOIN (
                                SELECT post_id, COUNT(*) as shares_count 
                                FROM recipe_tip_shares 
                                WHERE recipe_tip_shares.post_type = 'meal_plan' 
                                GROUP BY post_id
                            ) share_counts ON m.id = share_counts.post_id
                            LEFT JOIN (
                                SELECT post_id, COUNT(*) as comments_count
                                FROM recipe_tip_comments 
                                WHERE recipe_tip_comments.post_type = 'meal_plan' 
                                GROUP BY post_id
                            ) comment_counts ON m.id = comment_counts.post_id
                            WHERE m.is_shared = 1 OR m.user_id = ?
                            ORDER BY m.created_at DESC
                            LIMIT ? OFFSET ?
                        ");
                        $stmt2->bind_param('iiiii', $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $limit, $offset);
                        $stmt2->execute();
                        $result2 = $stmt2->get_result();
                        
                        while ($meal_plan = $result2->fetch_assoc()) {
                            // Convert meal plan data to match post structure
                            $meal_plan['title'] = $meal_plan['plan_name'];
                            
                            // Create a more detailed content description
                            $content = "Shared Meal Plan: " . $meal_plan['plan_name'];
                            if ($meal_plan['total_calories']) {
                                $content .= "\n\n📊 Nutrition Summary:";
                                $content .= "\n🔥 Total Calories: " . $meal_plan['total_calories'];
                                if ($meal_plan['total_protein']) {
                                    $content .= "\n🥩 Protein: " . $meal_plan['total_protein'] . "g";
                                }
                                if ($meal_plan['total_carbs']) {
                                    $content .= "\n🌾 Carbs: " . $meal_plan['total_carbs'] . "g";
                                }
                                if ($meal_plan['total_fat']) {
                                    $content .= "\n🧈 Fat: " . $meal_plan['total_fat'] . "g";
                                }
                            }
                            
                            $meal_plan['content'] = $content;
                            $meal_plan['post_type'] = 'meal_plan';
                            $meal_plan['tags'] = json_encode(['meal-plan', 'shared', 'nutrition', '7-day-plan']);
                            $posts[] = $meal_plan;
                        }
                        $stmt2->close();
                        
                        // Sort all posts by creation date (newest first)
                        usort($posts, function($a, $b) {
                            return strtotime($b['created_at']) - strtotime($a['created_at']);
                        });
                        
                    } else if ($post_type === 'meal_plan') {
                        // Get shared meal plans
                        $stmt = $conn->prepare("SELECT m.*, u.username, u.profile_img, 'meal_plan' as source_type,
                                                CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END as is_liked,
                                                CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as is_saved,
                                                COALESCE(like_counts.likes_count, 0) as likes_count,
                                                COALESCE(share_counts.shares_count, 0) as shares_count,
                                                COALESCE(comment_counts.comments_count, 0) as comments_count
                                                FROM meal_plans m
                                                LEFT JOIN user_accounts u ON m.user_id = u.user_id
                                                LEFT JOIN recipe_tip_likes l ON m.id = l.post_id AND l.post_type = 'meal_plan' AND l.user_id = ?
                                                LEFT JOIN recipe_tip_saves s ON m.id = s.post_id AND s.post_type = 'meal_plan' AND s.user_id = ?
                                                LEFT JOIN (
                                                    SELECT post_id, COUNT(*) as likes_count 
                                                    FROM recipe_tip_likes 
                                                    WHERE recipe_tip_likes.post_type = 'meal_plan' 
                                                    GROUP BY post_id
                                                ) like_counts ON m.id = like_counts.post_id
                                                LEFT JOIN (
                                                    SELECT post_id, COUNT(*) as shares_count 
                                                    FROM recipe_tip_shares 
                                                    WHERE recipe_tip_shares.post_type = 'meal_plan' 
                                                    GROUP BY post_id
                                                ) share_counts ON m.id = share_counts.post_id
                                                LEFT JOIN (
                                                    SELECT post_id, COUNT(*) as comments_count 
                                                    FROM recipe_tip_comments 
                                                    WHERE recipe_tip_comments.post_type = 'meal_plan' 
                                                    GROUP BY post_id
                                                ) comment_counts ON m.id = comment_counts.post_id
                                                WHERE m.is_shared = 1
                                                ORDER BY m.created_at DESC
                                                LIMIT ? OFFSET ?");
                        $stmt->bind_param('iiii', $_SESSION['user_id'], $_SESSION['user_id'], $limit, $offset);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        while ($meal_plan = $result->fetch_assoc()) {
                            // Convert meal plan data to match post structure
                            $meal_plan['title'] = $meal_plan['plan_name'];
                            
                            // Create a more detailed content description
                            $content = "Shared Meal Plan: " . $meal_plan['plan_name'];
                            if ($meal_plan['total_calories']) {
                                $content .= "\n\n📊 Nutrition Summary:";
                                $content .= "\n🔥 Total Calories: " . $meal_plan['total_calories'];
                                if ($meal_plan['total_protein']) {
                                    $content .= "\n🥩 Protein: " . $meal_plan['total_protein'] . "g";
                                }
                                if ($meal_plan['total_carbs']) {
                                    $content .= "\n🌾 Carbs: " . $meal_plan['total_carbs'] . "g";
                                }
                                if ($meal_plan['total_fat']) {
                                    $content .= "\n🧈 Fat: " . $meal_plan['total_fat'] . "g";
                                }
                            }
                            
                            $meal_plan['content'] = $content;
                            $meal_plan['post_type'] = 'meal_plan';
                            $meal_plan['tags'] = json_encode(['meal-plan', 'shared', 'nutrition', '7-day-plan']);
                            $posts[] = $meal_plan;
                        }
                        $stmt->close();
                    } else {
                        // Get recipes and tips
                        $where_conditions = ["r.is_public = 1"];
                        $params = [];
                        $types = "";
                        
                        if (!empty($post_type)) {
                            $where_conditions[] = "r.post_type = ?";
                            $params[] = $post_type;
                            $types .= "s";
                        }
                        
                        if (!empty($user_id)) {
                            $where_conditions[] = "r.user_id = ?";
                            $params[] = (int)$user_id;
                            $types .= "i";
                        }
                        
                        $where_clause = implode(" AND ", $where_conditions);
                        
                        $sql = "SELECT r.*, u.username, u.profile_img,
                                CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END as is_liked,
                                CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as is_saved
                                FROM recipes_tips r
                                LEFT JOIN user_accounts u ON r.user_id = u.user_id
                                LEFT JOIN recipe_tip_likes l ON r.id = l.post_id AND l.post_type = r.post_type AND l.user_id = ?
                                LEFT JOIN recipe_tip_saves s ON r.id = s.post_id AND s.post_type = r.post_type AND s.user_id = ?
                                WHERE $where_clause
                                ORDER BY r.created_at DESC
                                LIMIT ? OFFSET ?";
                        
                        $params = array_merge([$_SESSION['user_id'] ?? 0, $_SESSION['user_id'] ?? 0], $params, [$limit, $offset]);
                        $types = "ii" . $types . "ii";
                        
                        $stmt = $conn->prepare($sql);
                        $stmt->bind_param($types, ...$params);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        
                        while ($post = $result->fetch_assoc()) {
                            $posts[] = $post;
                        }
                        $stmt->close();
                    }
                    
                    echo json_encode(['success' => true, 'data' => $posts]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
        }
    }
}

// Get posts for display (including shared meal plans)
$posts = [];
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    try {
        // Get recipes and tips
        $stmt = $conn->prepare("SELECT r.*, u.username, u.profile_img, r.post_type as source_type,
                                CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END as is_liked,
                                CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as is_saved
                                FROM recipes_tips r
                                LEFT JOIN user_accounts u ON r.user_id = u.user_id
                                LEFT JOIN recipe_tip_likes l ON r.id = l.post_id AND l.post_type = r.post_type AND l.user_id = ?
                                LEFT JOIN recipe_tip_saves s ON r.id = s.post_id AND s.post_type = r.post_type AND s.user_id = ?
                                WHERE r.is_public = 1
                                ORDER BY r.created_at DESC");
        $stmt->bind_param('ii', $_SESSION['user_id'], $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($post = $result->fetch_assoc()) {
            $posts[] = $post;
        }
        $stmt->close();
        
        // Get shared meal plans
        $stmt = $conn->prepare("SELECT m.*, u.username, u.profile_img, 'meal_plan' as source_type,
                                CASE WHEN l.id IS NOT NULL THEN 1 ELSE 0 END as is_liked,
                                CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END as is_saved,
                                COALESCE(like_counts.likes_count, 0) as likes_count,
                                COALESCE(share_counts.shares_count, 0) as shares_count,
                                COALESCE(comment_counts.comments_count, 0) as comments_count
                                FROM meal_plans m
                                LEFT JOIN user_accounts u ON m.user_id = u.user_id
                                LEFT JOIN recipe_tip_likes l ON m.id = l.post_id AND l.post_type = 'meal_plan' AND l.user_id = ?
                                LEFT JOIN recipe_tip_saves s ON m.id = s.post_id AND s.post_type = 'meal_plan' AND s.user_id = ?
                                LEFT JOIN (
                                    SELECT post_id, COUNT(*) as likes_count 
                                    FROM recipe_tip_likes 
                                    WHERE recipe_tip_likes.post_type = 'meal_plan' 
                                    GROUP BY post_id
                                ) like_counts ON m.id = like_counts.post_id
                                LEFT JOIN (
                                    SELECT post_id, COUNT(*) as shares_count 
                                    FROM recipe_tip_shares 
                                    WHERE recipe_tip_shares.post_type = 'meal_plan' 
                                    GROUP BY post_id
                                ) share_counts ON m.id = share_counts.post_id
                                LEFT JOIN (
                                    SELECT post_id, COUNT(*) as comments_count 
                                    FROM recipe_tip_comments 
                                    WHERE recipe_tip_comments.post_type = 'meal_plan' 
                                    GROUP BY post_id
                                ) comment_counts ON m.id = comment_counts.post_id
                                WHERE m.is_shared = 1
                                ORDER BY m.created_at DESC");
        $stmt->bind_param('ii', $_SESSION['user_id'], $_SESSION['user_id']);
        $stmt->execute();
        $result = $stmt->get_result();
        
        while ($meal_plan = $result->fetch_assoc()) {
            // Convert meal plan data to match post structure
            $meal_plan['title'] = $meal_plan['plan_name'];
            
            // Create a more detailed content description
            $content = "Shared Meal Plan: " . $meal_plan['plan_name'];
            if ($meal_plan['total_calories']) {
                $content .= "\n\n📊 Nutrition Summary:";
                $content .= "\n🔥 Total Calories: " . $meal_plan['total_calories'];
                if ($meal_plan['total_protein']) {
                    $content .= "\n🥩 Protein: " . $meal_plan['total_protein'] . "g";
                }
                if ($meal_plan['total_carbs']) {
                    $content .= "\n🌾 Carbs: " . $meal_plan['total_carbs'] . "g";
                }
                if ($meal_plan['total_fat']) {
                    $content .= "\n🧈 Fat: " . $meal_plan['total_fat'] . "g";
                }
            }
            
            $meal_plan['content'] = $content;
            $meal_plan['post_type'] = 'meal_plan';
            $meal_plan['tags'] = json_encode(['meal-plan', 'shared', 'nutrition', '7-day-plan']);
            $posts[] = $meal_plan;
        }
        $stmt->close();
        
        // Sort all posts by creation date
        usort($posts, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        // Limit to 20 posts
        $posts = array_slice($posts, 0, 20);
        
    } catch (Exception $e) {
        // Handle error silently for now
    }
}

// Include HTML files AFTER AJAX handling
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
                    <h2><i class="bi bi-book-heart"></i> News Feed</h2>
                    <p class="text-muted mb-0">Share your favorite recipes and cooking tips with the community</p>
                </div>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPostModal">
                    <i class="bi bi-plus-circle"></i> Create Post
                </button>
            </div>
        </div>
    </div>

    <!-- Facebook-style Filter Tabs -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="facebook-tabs">
                <div class="tab-item active" id="all-tab" data-target="all-posts">
                    <i class="bi bi-grid"></i>
                    <span>All Posts</span>
                </div>
                <div class="tab-item" id="recipes-tab" data-target="recipes-posts">
                    <i class="bi bi-egg-fried"></i>
                    <span>Recipes</span>
                </div>
                <div class="tab-item" id="tips-tab" data-target="tips-posts">
                    <i class="bi bi-lightbulb"></i>
                    <span>Tips</span>
                </div>
                <div class="tab-item" id="meal-plans-tab" data-target="meal-plans-posts">
                    <i class="bi bi-calendar-week"></i>
                    <span>Meal Plans</span>
                </div>
                <div class="tab-item" id="bookmarks-tab" data-target="bookmarks-posts">
                    <i class="bi bi-bookmark-fill"></i>
                    <span>Bookmarks</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Posts Container -->
    <div class="tab-content" id="postsContent">
        <div class="tab-pane fade show active" id="all-posts" role="tabpanel">
            <div id="posts-container" class="row">
                <?php if (empty($posts)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="bi bi-book display-1 text-muted"></i>
                            <h4 class="text-muted mt-3">No posts yet</h4>
                            <p class="text-muted">Be the first to share a recipe or cooking tip!</p>
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPostModal">
                                <i class="bi bi-plus-circle"></i> Create First Post
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($posts as $post): ?>
                        <div class="col-lg-8 mx-auto mb-4">
                            <div class="card post-card" data-post-id="<?= $post['id'] ?>" data-post-type="<?= $post['post_type'] ?>">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= !empty($post['profile_img']) ? '../uploads/profile_picture/' . $post['profile_img'] : '../uploads/profile_picture/no_image.png' ?>" 
                                             class="rounded-circle me-2" width="40" height="40" alt="Profile">
                                        <div>
                                            <h6 class="mb-0"><?= htmlspecialchars($post['username']) ?></h6>
                                            <small class="text-muted"><?= date('M j, Y g:i A', strtotime($post['created_at'])) ?></small>
                                        </div>
                                    </div>
                                    <span class="badge bg-<?= $post['post_type'] === 'recipe' ? 'success' : ($post['post_type'] === 'meal_plan' ? 'warning' : 'info') ?>">
                                        <?= $post['post_type'] === 'meal_plan' ? 'Meal Plan' : ucfirst($post['post_type']) ?>
                                    </span>
                                </div>
                                
                                <div class="card-body">
                                    <h5 class="card-title"><?= htmlspecialchars($post['title']) ?></h5>
                                    <p class="card-text"><?= nl2br(htmlspecialchars($post['content'])) ?></p>
                                    
                                    <?php if ($post['post_type'] === 'meal_plan'): ?>
                                        <div class="meal-plan-details mb-3">
                                            <div class="d-flex align-items-center mb-3">
                                                <i class="bi bi-calendar-week text-warning me-2" style="font-size: 1.5rem;"></i>
                                                <h6 class="mb-0">7-Day Meal Plan</h6>
                                            </div>
                                            
                                            <?php if ($post['total_calories']): ?>
                                                <div class="nutrition-grid mb-3">
                                                    <div class="nutrition-item">
                                                        <div class="nutrition-icon">🔥</div>
                                                        <div class="nutrition-text">
                                                            <strong><?= $post['total_calories'] ?></strong>
                                                            <small>Total Calories</small>
                                                        </div>
                                                    </div>
                                                    <?php if ($post['total_protein']): ?>
                                                        <div class="nutrition-item">
                                                            <div class="nutrition-icon">🥩</div>
                                                            <div class="nutrition-text">
                                                                <strong><?= $post['total_protein'] ?>g</strong>
                                                                <small>Protein</small>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($post['total_carbs']): ?>
                                                        <div class="nutrition-item">
                                                            <div class="nutrition-icon">🌾</div>
                                                            <div class="nutrition-text">
                                                                <strong><?= $post['total_carbs'] ?>g</strong>
                                                                <small>Carbs</small>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($post['total_fat']): ?>
                                                        <div class="nutrition-item">
                                                            <div class="nutrition-icon">🧈</div>
                                                            <div class="nutrition-text">
                                                                <strong><?= $post['total_fat'] ?>g</strong>
                                                                <small>Fat</small>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if (!empty($post['filters_applied'])): ?>
                                                <?php 
                                                $filters = json_decode($post['filters_applied'], true);
                                                $activeFilters = [];
                                                
                                                if (is_array($filters)) {
                                                    // Filter out empty values and create user-friendly labels
                                                    if (!empty($filters['category'])) {
                                                        $activeFilters[] = 'Category: ' . ucfirst($filters['category']);
                                                    }
                                                    if (!empty($filters['cal_min']) || !empty($filters['cal_max'])) {
                                                        $calRange = '';
                                                        if (!empty($filters['cal_min']) && !empty($filters['cal_max'])) {
                                                            $calRange = $filters['cal_min'] . '-' . $filters['cal_max'] . ' cal';
                                                        } elseif (!empty($filters['cal_min'])) {
                                                            $calRange = '≥' . $filters['cal_min'] . ' cal';
                                                        } elseif (!empty($filters['cal_max'])) {
                                                            $calRange = '≤' . $filters['cal_max'] . ' cal';
                                                        }
                                                        if ($calRange) $activeFilters[] = $calRange;
                                                    }
                                                    if (!empty($filters['protein_min']) || !empty($filters['protein_max'])) {
                                                        $proteinRange = '';
                                                        if (!empty($filters['protein_min']) && !empty($filters['protein_max'])) {
                                                            $proteinRange = $filters['protein_min'] . '-' . $filters['protein_max'] . 'g protein';
                                                        } elseif (!empty($filters['protein_min'])) {
                                                            $proteinRange = '≥' . $filters['protein_min'] . 'g protein';
                                                        } elseif (!empty($filters['protein_max'])) {
                                                            $proteinRange = '≤' . $filters['protein_max'] . 'g protein';
                                                        }
                                                        if ($proteinRange) $activeFilters[] = $proteinRange;
                                                    }
                                                    if (!empty($filters['keyword'])) {
                                                        $activeFilters[] = 'Keyword: ' . $filters['keyword'];
                                                    }
                                                }
                                                
                                                if (!empty($activeFilters)):
                                                ?>
                                                <div class="filters-applied mb-3">
                                                    <div class="d-flex align-items-center">
                                                        <i class="bi bi-funnel text-info me-2"></i>
                                                        <span class="text-muted">Applied Filters:</span>
                                                    </div>
                                                    <div class="filter-tags mt-1">
                                                        <?php foreach ($activeFilters as $filter): ?>
                                                            <span class="badge bg-info text-white me-1 mb-1"><?= htmlspecialchars($filter) ?></span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <div class="meal-plan-actions">
                                                <a href="saved_plans.php?shared=<?= $post['share_token'] ?>" class="btn btn-primary btn-sm">
                                                    <i class="bi bi-eye"></i> View Complete Plan
                                                </a>
                                                <button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="copyToClipboard('<?= $post['share_token'] ?>')">
                                                    <i class="bi bi-share"></i> Copy Link
                                                </button>
                                            </div>
                                        </div>
                                    <?php elseif ($post['post_type'] === 'recipe'): ?>
                                        <div class="recipe-details row mb-3">
                                            <?php if ($post['cooking_time']): ?>
                                                <div class="col-md-3">
                                                    <small class="text-muted">
                                                        <i class="bi bi-clock"></i> <?= $post['cooking_time'] ?> min
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($post['servings']): ?>
                                                <div class="col-md-3">
                                                    <small class="text-muted">
                                                        <i class="bi bi-people"></i> <?= $post['servings'] ?> servings
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($post['difficulty_level']): ?>
                                                <div class="col-md-3">
                                                    <small class="text-muted">
                                                        <i class="bi bi-star"></i> <?= $post['difficulty_level'] ?>
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($post['calories_per_serving']): ?>
                                                <div class="col-md-3">
                                                    <small class="text-muted">
                                                        <i class="bi bi-fire"></i> <?= $post['calories_per_serving'] ?> cal
                                                    </small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (!empty($post['ingredients'])): ?>
                                            <div class="ingredients mb-3">
                                                <h6><i class="bi bi-list-ul"></i> Ingredients:</h6>
                                                <div class="ingredients-list">
                                                    <?php 
                                                    // Try to decode as JSON first, fallback to plain text
                                                    $ingredients = json_decode($post['ingredients'], true);
                                                    if (is_array($ingredients)):
                                                        foreach ($ingredients as $ingredient):
                                                    ?>
                                                        <span class="badge bg-light text-dark me-1 mb-1"><?= htmlspecialchars($ingredient) ?></span>
                                                    <?php 
                                                        endforeach;
                                                    else:
                                                        // Handle plain text ingredients
                                                        $ingredients_text = trim($post['ingredients']);
                                                        if (!empty($ingredients_text)):
                                                    ?>
                                                        <div class="ingredients-text">
                                                            <?= nl2br(htmlspecialchars($ingredients_text)) ?>
                                                        </div>
                                                    <?php 
                                                        endif;
                                                    endif;
                                                    ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($post['instructions'])): ?>
                                            <div class="instructions mb-3">
                                                <h6><i class="bi bi-list-ol"></i> Instructions:</h6>
                                                <p class="text-muted"><?= nl2br(htmlspecialchars($post['instructions'])) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($post['tags'])): ?>
                                        <div class="tags mb-3">
                                            <?php 
                                            $tags = json_decode($post['tags'], true);
                                            if (is_array($tags)):
                                                foreach ($tags as $tag):
                                            ?>
                                                <span class="badge bg-secondary me-1">#<?= htmlspecialchars($tag) ?></span>
                                            <?php 
                                                endforeach;
                                            endif;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-footer">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-outline-danger like-btn <?= $post['is_liked'] ? 'active' : '' ?>" 
                                                        data-post-id="<?= $post['id'] ?>">
                                                    <i class="bi bi-heart<?= $post['is_liked'] ? '-fill' : '' ?>"></i>
                                                    <span class="likes-count"><?= $post['likes_count'] ?></span>
                                                </button>
                                                <button type="button" class="btn btn-outline-primary comment-btn" data-post-id="<?= $post['id'] ?>">
                                                    <i class="bi bi-chat"></i>
                                                    <span class="comments-count"><?= $post['comments_count'] ?></span>
                                                </button>
                                                <button type="button" class="btn btn-outline-success share-btn" data-post-id="<?= $post['id'] ?>">
                                                    <i class="bi bi-share"></i>
                                                    <span class="shares-count"><?= $post['shares_count'] ?></span>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="col-md-6 text-end">
                                            <button type="button" class="btn btn-outline-warning save-btn <?= $post['is_saved'] ? 'active' : '' ?>" 
                                                    data-post-id="<?= $post['id'] ?>">
                                                <i class="bi bi-bookmark<?= $post['is_saved'] ? '-fill' : '' ?>"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="tab-pane fade" id="recipes-posts" role="tabpanel">
            <div id="recipes-container" class="row">
                <!-- Recipes will be loaded here via AJAX -->
            </div>
        </div>
        
        <div class="tab-pane fade" id="tips-posts" role="tabpanel">
            <div id="tips-container" class="row">
                <!-- Tips will be loaded here via AJAX -->
            </div>
        </div>
        
        <div class="tab-pane fade" id="meal-plans-posts" role="tabpanel">
            <div id="meal-plans-container" class="row">
                <!-- Meal plans will be loaded here via AJAX -->
            </div>
        </div>
        
        <div class="tab-pane fade" id="bookmarks-posts" role="tabpanel">
            <div id="bookmarks-container" class="row">
                <!-- Bookmarks will be loaded here via AJAX -->
            </div>
        </div>
    </div>
</div>
</main>

<!-- Create Post Modal -->
<div class="modal fade" id="createPostModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="createPostForm">
                    <div class="mb-3">
                        <label class="form-label">Post Type</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="post_type" id="recipe_type" value="recipe" checked>
                            <label class="btn btn-outline-success" for="recipe_type">
                                <i class="bi bi-egg-fried"></i> Recipe
                            </label>
                            <input type="radio" class="btn-check" name="post_type" id="tip_type" value="tip">
                            <label class="btn btn-outline-info" for="tip_type">
                                <i class="bi bi-lightbulb"></i> Tip
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="post_title" class="form-label">Title</label>
                        <input type="text" class="form-control" id="post_title" name="title" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="post_content" class="form-label">Content</label>
                        <textarea class="form-control" id="post_content" name="content" rows="4" required></textarea>
                    </div>
                    
                    <!-- Recipe-specific fields -->
                    <div id="recipe_fields" class="recipe-specific">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="cooking_time" class="form-label">Cooking Time (minutes)</label>
                                <input type="number" class="form-control" id="cooking_time" name="cooking_time" min="0">
                            </div>
                            <div class="col-md-6">
                                <label for="servings" class="form-label">Servings</label>
                                <input type="number" class="form-control" id="servings" name="servings" min="1">
                            </div>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="difficulty_level" class="form-label">Difficulty Level</label>
                                <select class="form-select" id="difficulty_level" name="difficulty_level">
                                    <option value="Easy">Easy</option>
                                    <option value="Medium">Medium</option>
                                    <option value="Hard">Hard</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="calories_per_serving" class="form-label">Calories per Serving</label>
                                <input type="number" class="form-control" id="calories_per_serving" name="calories_per_serving" min="0">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ingredients" class="form-label">Ingredients (one per line)</label>
                            <textarea class="form-control" id="ingredients" name="ingredients" rows="3" placeholder="Enter ingredients, one per line..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="instructions" class="form-label">Instructions</label>
                            <textarea class="form-control" id="instructions" name="instructions" rows="4" placeholder="Enter step-by-step instructions..."></textarea>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="tags" class="form-label">Tags (comma-separated)</label>
                        <input type="text" class="form-control" id="tags" name="tags" placeholder="e.g., filipino, healthy, quick">
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="is_public" name="is_public" checked>
                        <label class="form-check-label" for="is_public">
                            Make this post public
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="submitPost">Create Post</button>
            </div>
        </div>
    </div>
</div>

<!-- Share Post Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Post</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="shareForm">
                    <div class="mb-3">
                        <label for="share_message" class="form-label">Add a message (optional)</label>
                        <textarea class="form-control" id="share_message" name="share_message" rows="3" placeholder="Share your thoughts about this post..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-success" id="submitShare">Share</button>
            </div>
        </div>
    </div>
</div>

<!-- Comment Modal -->
<div class="modal fade" id="commentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-chat-dots"></i> Comments
                    <span id="commentsCount" class="badge bg-primary ms-2">0</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Add Comment Form -->
                <div class="mb-4 p-3 bg-light rounded">
                    <form id="commentForm">
                        <div class="mb-3">
                            <label for="comment_text" class="form-label">
                                <i class="bi bi-pencil"></i> Add a comment
                            </label>
                            <textarea class="form-control" id="comment_text" name="comment" rows="3" 
                                      placeholder="Share your thoughts about this post..." required></textarea>
                            <div class="form-text">Be respectful and constructive in your comments.</div>
                        </div>
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="bi bi-info-circle"></i> Comments are moderated
                            </small>
                            <button type="button" class="btn btn-primary" id="submitComment">
                                <i class="bi bi-send"></i> Post Comment
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Comments List -->
                <div class="comments-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="mb-0">
                            <i class="bi bi-chat-square-text"></i> All Comments
                        </h6>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="refreshComments">
                            <i class="bi bi-arrow-clockwise"></i> Refresh
                        </button>
                    </div>
                    
                    <div id="commentsContainer">
                        <div class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading comments...</span>
                            </div>
                            <div class="mt-2 text-muted">Loading comments...</div>
                        </div>
                    </div>
                    
                    <!-- Load More Button -->
                    <div id="loadMoreContainer" class="text-center mt-3" style="display: none;">
                        <button type="button" class="btn btn-outline-secondary" id="loadMoreComments">
                            <i class="bi bi-arrow-down-circle"></i> Load More Comments
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>


<style>
.post-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.post-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.like-btn.active,
.save-btn.active {
    background-color: var(--bs-danger);
    border-color: var(--bs-danger);
    color: white;
}

.save-btn.active {
    background-color: var(--bs-warning);
    border-color: var(--bs-warning);
    color: white;
}

.recipe-specific {
    display: none;
}

.recipe-specific.show {
    display: block;
}

/* Facebook-style tabs */
.facebook-tabs {
    display: flex;
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    padding: 0;
    margin-bottom: 20px;
    overflow: hidden;
}

.tab-item {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 16px 12px;
    cursor: pointer;
    transition: all 0.2s ease;
    border-right: 1px solid #e4e6ea;
    background: #fff;
    position: relative;
}

.tab-item:last-child {
    border-right: none;
}

.tab-item:hover {
    background: #f0f2f5;
}

.tab-item.active {
    background: #1877f2;
    color: white;
}

.tab-item.active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: #1877f2;
}

.tab-item i {
    font-size: 18px;
    margin-right: 8px;
}

.tab-item span {
    font-weight: 600;
    font-size: 14px;
}

/* Enhanced post cards */
.post-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 8px;
    margin-bottom: 20px;
}

.post-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.post-card .card-header {
    background: #fff;
    border-bottom: 1px solid #e4e6ea;
    border-radius: 8px 8px 0 0 !important;
    padding: 16px;
}

.post-card .card-body {
    padding: 16px;
}

.post-card .card-footer {
    background: #f8f9fa;
    border-top: 1px solid #e4e6ea;
    border-radius: 0 0 8px 8px !important;
    padding: 12px 16px;
}

.like-btn.active,
.save-btn.active {
    background-color: var(--bs-danger);
    border-color: var(--bs-danger);
    color: white;
}

.save-btn.active {
    background-color: var(--bs-warning);
    border-color: var(--bs-warning);
    color: white;
}

.recipe-specific {
    display: none;
}

.recipe-specific.show {
    display: block;
}

.ingredients-list .badge {
    font-size: 0.875rem;
}

.tags .badge {
    font-size: 0.875rem;
}

.recipe-details small {
    font-size: 0.75rem;
}

/* Meal plan specific styles */
.meal-plan-details {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 16px;
    border-left: 4px solid #ffc107;
}

.meal-plan-details h6 {
    color: #495057;
    margin-bottom: 12px;
    font-weight: 600;
}

.nutrition-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.nutrition-item {
    display: flex;
    align-items: center;
    background: white;
    padding: 12px;
    border-radius: 8px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
    transition: transform 0.2s ease;
}

.nutrition-item:hover {
    transform: translateY(-1px);
}

.nutrition-icon {
    font-size: 1.5rem;
    margin-right: 8px;
}

.nutrition-text {
    display: flex;
    flex-direction: column;
}

.nutrition-text strong {
    font-size: 1.1rem;
    color: #1877f2;
    line-height: 1.2;
}

.nutrition-text small {
    color: #6c757d;
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filters-applied {
    background: white;
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #e4e6ea;
}

.filter-tags .badge {
    font-size: 0.75rem;
    padding: 4px 8px;
}

.meal-plan-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.meal-plan-actions .btn {
    border-radius: 6px;
    font-weight: 500;
}

/* Comments styling */
.comment-item {
    border-bottom: 1px solid #e9ecef;
    padding: 15px 0;
    transition: background-color 0.2s ease;
}

.comment-item:hover {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin: 0 -15px;
}

.comment-item:last-child {
    border-bottom: none;
}

.comment-header {
    display: flex;
    align-items: center;
    margin-bottom: 8px;
    position: relative;
}

.comment-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    margin-right: 12px;
    object-fit: cover;
    border: 2px solid #e9ecef;
}

.comment-meta {
    flex: 1;
}

.comment-author {
    font-weight: 600;
    font-size: 0.9rem;
    color: #333;
    margin: 0;
}

.comment-time {
    font-size: 0.75rem;
    color: #6c757d;
    margin: 0;
}

.comment-content {
    margin-left: 52px;
    line-height: 1.5;
    color: #333;
    word-wrap: break-word;
}

.no-comments {
    text-align: center;
    color: #6c757d;
    font-style: italic;
    padding: 40px 20px;
}

.comments-loading {
    text-align: center;
    padding: 40px 20px;
}

.badge-sm {
    font-size: 0.65rem;
    padding: 0.25rem 0.5rem;
}

.comments-section {
    max-height: 500px;
    overflow-y: auto;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    background-color: #fafafa;
}

.comments-section::-webkit-scrollbar {
    width: 6px;
}

.comments-section::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.comments-section::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.comments-section::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Ingredients styling */
.ingredients-list {
    margin-top: 10px;
}

.ingredients-text {
    background-color: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 8px;
    padding: 15px;
    font-size: 0.9rem;
    line-height: 1.6;
    color: #495057;
}

.ingredients-text br {
    margin-bottom: 8px;
}

.recipe-details {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.recipe-details .col-md-3 {
    margin-bottom: 10px;
}

.instructions {
    background-color: #f8f9fa;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 15px;
}

.instructions p {
    margin-bottom: 0;
    line-height: 1.6;
}

/* Show original button styling */
.show-original-btn {
    transition: all 0.2s ease;
    border-radius: 6px;
    font-weight: 500;
}

.show-original-btn:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(0,123,255,0.3);
}

.show-original-btn i {
    margin-right: 4px;
}


/* Responsive design */
@media (max-width: 768px) {
    .facebook-tabs {
        flex-direction: column;
    }
    
    .tab-item {
        border-right: none;
        border-bottom: 1px solid #e4e6ea;
    }
    
    .tab-item:last-child {
        border-bottom: none;
    }
    
    .tab-item.active::after {
        bottom: auto;
        left: 0;
        right: auto;
        top: 0;
        width: 3px;
        height: 100%;
    }
    
    .comment-content {
        margin-left: 0;
        margin-top: 8px;
    }
    
    .comment-avatar {
        width: 28px;
        height: 28px;
    }
    
    .show-original-btn {
        font-size: 0.8rem;
        padding: 0.25rem 0.5rem;
    }
    
}
</style>

<?php include 'footer.php'; ?>

<script>
// Global variables
let currentPostId = null;
let currentPostType = null;
let currentCommentPage = 1;
let allComments = [];
let totalCommentCount = 0;

// Show notification function
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

// Toggle recipe-specific fields
document.addEventListener('change', function(e) {
    if (e.target.name === 'post_type') {
        const recipeFields = document.getElementById('recipe_fields');
        if (e.target.value === 'recipe') {
            recipeFields.classList.add('show');
        } else {
            recipeFields.classList.remove('show');
        }
    }
});

// Create post functionality
document.getElementById('submitPost').addEventListener('click', function() {
    const form = document.getElementById('createPostForm');
    const formData = new FormData();
    
    // Add all form data
    formData.append('action', 'create_post');
    formData.append('post_type', document.querySelector('input[name="post_type"]:checked').value);
    formData.append('title', document.getElementById('post_title').value);
    formData.append('content', document.getElementById('post_content').value);
    formData.append('tags', document.getElementById('tags').value);
    formData.append('is_public', document.getElementById('is_public').checked ? '1' : '0');
    
    // Add recipe-specific fields if it's a recipe
    if (document.querySelector('input[name="post_type"]:checked').value === 'recipe') {
        formData.append('cooking_time', document.getElementById('cooking_time').value);
        formData.append('servings', document.getElementById('servings').value);
        formData.append('difficulty_level', document.getElementById('difficulty_level').value);
        formData.append('calories_per_serving', document.getElementById('calories_per_serving').value);
        formData.append('ingredients', document.getElementById('ingredients').value);
        formData.append('instructions', document.getElementById('instructions').value);
    }
    
    // Show loading state
    const submitBtn = document.getElementById('submitPost');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Creating...';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            form.reset();
            document.getElementById('recipe_fields').classList.remove('show');
            bootstrap.Modal.getInstance(document.getElementById('createPostModal')).hide();
            // Reload page to show new post
            setTimeout(() => location.reload(), 1000);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while creating the post.', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Like functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.like-btn')) {
        const likeBtn = e.target.closest('.like-btn');
        const postId = likeBtn.dataset.postId;
        const postCard = likeBtn.closest('.post-card');
        const postType = postCard.dataset.postType || 'recipe'; // Get post type from card data
        
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
                const heartIcon = likeBtn.querySelector('i');
                const likesCount = likeBtn.querySelector('.likes-count');
                
                if (data.liked) {
                    likeBtn.classList.add('active');
                    heartIcon.className = 'bi bi-heart-fill';
                    const currentCount = parseInt(likesCount.textContent) || 0;
                    likesCount.textContent = currentCount + 1;
                } else {
                    likeBtn.classList.remove('active');
                    heartIcon.className = 'bi bi-heart';
                    const currentCount = parseInt(likesCount.textContent) || 0;
                    likesCount.textContent = Math.max(0, currentCount - 1);
                }
                showNotification(data.message, 'success');
            } else {
                showNotification(data.message, 'error');
            }
        })
        .catch(error => {
            showNotification('An error occurred while liking the post.', 'error');
        });
    }
});

// Save functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.save-btn')) {
        const saveBtn = e.target.closest('.save-btn');
        const postCard = saveBtn.closest('.post-card');
        const postId = saveBtn.dataset.postId;
        const postType = postCard.dataset.postType || 'recipe';
        
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
                const bookmarkIcon = saveBtn.querySelector('i');
                
                if (data.saved) {
                    saveBtn.classList.add('active');
                    bookmarkIcon.className = 'bi bi-bookmark-fill';
                } else {
                    saveBtn.classList.remove('active');
                    bookmarkIcon.className = 'bi bi-bookmark';
                }
                showNotification(data.message, 'success');
            }
        })
        .catch(error => {
            showNotification('An error occurred while saving the post.', 'error');
        });
    }
});

// Share functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.share-btn')) {
        const shareBtn = e.target.closest('.share-btn');
        const postCard = shareBtn.closest('.post-card');
        currentPostId = shareBtn.dataset.postId;
        currentPostType = postCard.dataset.postType || 'recipe';
        
        const modal = new bootstrap.Modal(document.getElementById('shareModal'));
        modal.show();
    }
});

document.getElementById('submitShare').addEventListener('click', function() {
    const shareMessage = document.getElementById('share_message').value;
    
    const formData = new FormData();
    formData.append('action', 'share_post');
    formData.append('post_id', currentPostId);
    formData.append('post_type', currentPostType);
    formData.append('share_message', shareMessage);
    
    const submitBtn = document.getElementById('submitShare');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Sharing...';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Update shares count for recipe/tip posts
            if (currentPostType !== 'meal_plan') {
                const shareBtn = document.querySelector(`[data-post-id="${currentPostId}"]`);
                const sharesCount = shareBtn.querySelector('.shares-count');
                sharesCount.textContent = parseInt(sharesCount.textContent) + 1;
            }
            
            // If it's a meal plan and we have a share URL, copy it to clipboard
            if (currentPostType === 'meal_plan' && data.share_url) {
                copyToClipboardFromUrl(data.share_url);
            }
            
            bootstrap.Modal.getInstance(document.getElementById('shareModal')).hide();
            document.getElementById('share_message').value = '';
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while sharing the post.', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Comment functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.comment-btn')) {
        const commentBtn = e.target.closest('.comment-btn');
        const postCard = commentBtn.closest('.post-card');
        currentPostId = commentBtn.dataset.postId;
        currentPostType = postCard.dataset.postType || 'recipe';
        
        // Load comments when modal opens
        loadComments(currentPostId, currentPostType);
        
        const modal = new bootstrap.Modal(document.getElementById('commentModal'));
        modal.show();
    }
});

// Function to load and display comments
function loadComments(postId, postType, page = 1, append = false) {
    const commentsContainer = document.getElementById('commentsContainer');
    const loadMoreContainer = document.getElementById('loadMoreContainer');
    
    // Show loading spinner
    if (!append) {
        commentsContainer.innerHTML = `
            <div class="comments-loading">
                <div class="spinner-border spinner-border-sm" role="status">
                    <span class="visually-hidden">Loading comments...</span>
                </div>
                <div class="mt-2">Loading comments...</div>
            </div>
        `;
        allComments = [];
        currentCommentPage = 1;
    } else {
        // Show loading on load more button
        const loadMoreBtn = document.getElementById('loadMoreComments');
        if (loadMoreBtn) {
            loadMoreBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Loading...';
            loadMoreBtn.disabled = true;
        }
    }
    
    const formData = new FormData();
    formData.append('action', 'get_comments');
    formData.append('post_id', postId);
    formData.append('post_type', postType);
    formData.append('page', page);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (append) {
                allComments = [...allComments, ...data.comments];
            } else {
                allComments = data.comments;
            }
            
            totalCommentCount = data.total_count;
            currentCommentPage = data.current_page;
            
            // Update comments count in header
            const commentsCountBadge = document.getElementById('commentsCount');
            if (commentsCountBadge) {
                commentsCountBadge.textContent = totalCommentCount;
            }
            
            displayComments(allComments);
            
            // Show/hide load more button
            if (data.has_more) {
                loadMoreContainer.style.display = 'block';
                const loadMoreBtn = document.getElementById('loadMoreComments');
                if (loadMoreBtn) {
                    loadMoreBtn.innerHTML = '<i class="bi bi-arrow-down-circle"></i> Load More Comments';
                    loadMoreBtn.disabled = false;
                }
            } else {
                loadMoreContainer.style.display = 'none';
            }
        } else {
            commentsContainer.innerHTML = `
                <div class="no-comments">
                    <i class="bi bi-exclamation-triangle text-warning"></i>
                    <div class="mt-2">Error loading comments: ${data.message}</div>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadComments(${postId}, '${postType}')">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading comments:', error);
        commentsContainer.innerHTML = `
            <div class="no-comments">
                <i class="bi bi-exclamation-triangle text-danger"></i>
                <div class="mt-2">Error loading comments: ${error.message}</div>
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadComments(${postId}, '${postType}')">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            </div>
        `;
    });
}

// Function to display comments
function displayComments(comments) {
    const commentsContainer = document.getElementById('commentsContainer');
    
    if (comments.length === 0) {
        commentsContainer.innerHTML = `
            <div class="no-comments text-center py-4">
                <i class="bi bi-chat-dots text-muted" style="font-size: 3rem;"></i>
                <h6 class="text-muted mt-3">No comments yet</h6>
                <p class="text-muted">Be the first to share your thoughts!</p>
            </div>
        `;
        return;
    }
    
    // Sort comments by creation date (newest first)
    const sortedComments = [...comments].sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    
    const commentsHTML = sortedComments.map(comment => `
        <div class="comment-item">
            <div class="comment-header">
                <img src="${comment.profile_img ? '../uploads/profile_picture/' + comment.profile_img : '../uploads/profile_picture/no_image.png'}" 
                     class="comment-avatar" alt="Profile">
                <div class="comment-meta">
                    <div class="comment-author">${comment.user_name}</div>
                    <div class="comment-time">${formatTime(comment.created_at)}</div>
                </div>
                ${comment.is_own_comment ? '<span class="badge bg-primary badge-sm">You</span>' : ''}
            </div>
            <div class="comment-content">${escapeHtml(comment.comment)}</div>
        </div>
    `).join('');
    
    commentsContainer.innerHTML = commentsHTML;
}

// Helper function to format time
function formatTime(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diff = now - date;
    
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    
    if (days > 0) {
        return `${days} day${days > 1 ? 's' : ''} ago`;
    } else if (hours > 0) {
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    } else if (minutes > 0) {
        return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    } else {
        return 'Just now';
    }
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('submitComment').addEventListener('click', function() {
    const commentText = document.getElementById('comment_text').value.trim();
    
    if (!commentText) {
        showNotification('Please enter a comment.', 'warning');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'add_comment');
    formData.append('post_id', currentPostId);
    formData.append('post_type', currentPostType);
    formData.append('comment', commentText);
    
    const submitBtn = document.getElementById('submitComment');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Posting...';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // Update comments count
            const commentBtn = document.querySelector(`[data-post-id="${currentPostId}"]`);
            const commentsCount = commentBtn.querySelector('.comments-count');
            commentsCount.textContent = parseInt(commentsCount.textContent) + 1;
            
            // Clear the comment form
            document.getElementById('comment_text').value = '';
            
            // Reload comments to show the new comment (reset to page 1)
            loadComments(currentPostId, currentPostType, 1, false);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while posting the comment.', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Load more comments functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('#loadMoreComments')) {
        const nextPage = currentCommentPage + 1;
        loadComments(currentPostId, currentPostType, nextPage, true);
    }
    
    if (e.target.closest('#refreshComments')) {
        loadComments(currentPostId, currentPostType, 1, false);
    }
});

// Show original post functionality - redirect to all posts tab
document.addEventListener('click', function(e) {
    if (e.target.closest('.show-original-btn')) {
        const showBtn = e.target.closest('.show-original-btn');
        const postId = showBtn.dataset.postId;
        const postType = showBtn.dataset.postType;
        
        showOriginalPost(postId, postType);
    }
});

// Function to show original post by switching to all posts tab
function showOriginalPost(postId, postType) {
    console.log(`Looking for post ID: ${postId}, Type: ${postType}`);
    
    // Switch to all posts tab
    const allTab = document.getElementById('all-tab');
    if (allTab) {
        // Remove active class from all tabs
        document.querySelectorAll('.tab-item').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Hide all tab content
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });
        
        // Add active class to all posts tab
        allTab.classList.add('active');
        
        // Show all posts content
        const allPostsPane = document.getElementById('all-posts');
        if (allPostsPane) {
            allPostsPane.classList.add('show', 'active');
        }
        
        // Load all posts content first, then scroll to the specific post
        loadPostsByType('all', 'posts-container');
        
        // Scroll to the specific post after content is loaded
        setTimeout(() => {
            // First try to find the post with exact match
            let targetPost = document.querySelector(`[data-post-id="${postId}"]`);
            
            // If not found, try to find by post type and ID combination
            if (!targetPost) {
                console.log('Post not found with exact ID, searching by type...');
                const allPosts = document.querySelectorAll('.post-card');
                console.log(`Found ${allPosts.length} posts in all posts tab`);
                
                // Log all post IDs and types for debugging
                allPosts.forEach((post, index) => {
                    const id = post.getAttribute('data-post-id');
                    const type = post.getAttribute('data-post-type');
                    console.log(`Post ${index}: ID=${id}, Type=${type}`);
                });
                
                // Try to find by matching both ID and type
                targetPost = document.querySelector(`[data-post-id="${postId}"][data-post-type="${postType}"]`);
            }
            
            // Determine display type for user-friendly messages
            const displayType = postType === 'meal_plan' ? 'meal plan' : postType;
            
            if (targetPost) {
                console.log('Target post found, scrolling to it...');
                targetPost.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                
                // Add highlight effect
                targetPost.style.transition = 'all 0.3s ease';
                targetPost.style.boxShadow = '0 0 20px rgba(0, 123, 255, 0.5)';
                targetPost.style.border = '2px solid #007bff';
                
                // Remove highlight after 3 seconds
                setTimeout(() => {
                    targetPost.style.boxShadow = '';
                    targetPost.style.border = '';
                }, 3000);
                
                showNotification(`Showing original ${displayType}`, 'info');
            } else {
                console.log('Post not found in all posts tab');
                showNotification(`${displayType.charAt(0).toUpperCase() + displayType.slice(1)} not found in all posts. This ${displayType} may not be visible in the main feed.`, 'warning');
            }
        }, 1000); // Increased timeout to allow content to load and tab switch to complete
    } else {
        console.log('All posts tab not found');
        showNotification('Unable to switch to all posts tab', 'error');
    }
}

// Facebook-style tab switching
document.addEventListener('click', function(e) {
    const tabItem = e.target.closest('.tab-item');
    if (tabItem) {
        // Remove active class from all tabs
        document.querySelectorAll('.tab-item').forEach(tab => {
            tab.classList.remove('active');
        });
        
        // Hide all tab content
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
        });
        
        // Add active class to clicked tab
        tabItem.classList.add('active');
        
        // Show corresponding content
        const targetId = tabItem.dataset.target;
        const targetPane = document.getElementById(targetId);
        if (targetPane) {
            targetPane.classList.add('show', 'active');
        }
        
        // Load content based on tab
        if (tabItem.id === 'all-tab') {
            loadPostsByType('all', 'posts-container');
        } else if (tabItem.id === 'recipes-tab') {
            loadPostsByType('recipe', 'recipes-container');
        } else if (tabItem.id === 'tips-tab') {
            loadPostsByType('tip', 'tips-container');
        } else if (tabItem.id === 'meal-plans-tab') {
            loadPostsByType('meal_plan', 'meal-plans-container');
        } else if (tabItem.id === 'bookmarks-tab') {
            loadBookmarks('bookmarks-container');
        }
    }
});

function loadPostsByType(postType, containerId) {
    const container = document.getElementById(containerId);
    
    // Show loading
    container.innerHTML = '<div class="col-12 text-center py-4"><i class="spinner-border text-primary"></i> Loading...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_posts');
    formData.append('post_type', postType);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (data.data.length === 0) {
                const icon = postType === 'recipe' ? 'egg-fried' : postType === 'meal_plan' ? 'calendar-week' : 'lightbulb';
                container.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-${icon} display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No ${postType === 'meal_plan' ? 'meal plans' : postType + 's'} yet</h4>
                        <p class="text-muted">Be the first to share a ${postType === 'meal_plan' ? 'meal plan' : postType}!</p>
                    </div>
                `;
            } else {
                container.innerHTML = renderPosts(data.data, false);
            }
        } else {
            const errorMessage = data.message || 'Unknown error occurred';
            container.innerHTML = `
                <div class="col-12 text-center py-4">
                    <p class="text-danger">Error loading posts: ${errorMessage}</p>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadPostsByType('${postType}', '${containerId}')">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading posts:', error);
        container.innerHTML = `
            <div class="col-12 text-center py-4">
                <p class="text-danger">Network error: ${error.message}</p>
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadPostsByType('${postType}', '${containerId}')">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            </div>
        `;
    });
}

function loadBookmarks(containerId) {
    const container = document.getElementById(containerId);
    
    // Show loading
    container.innerHTML = '<div class="col-12 text-center py-4"><i class="spinner-border text-primary"></i> Loading bookmarks...</div>';
    
    const formData = new FormData();
    formData.append('action', 'get_bookmarks');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            if (data.posts.length === 0) {
                container.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-bookmark display-1 text-muted"></i>
                        <h4 class="text-muted mt-3">No bookmarks yet</h4>
                        <p class="text-muted">Save posts you like by clicking the bookmark button!</p>
                    </div>
                `;
            } else {
                container.innerHTML = renderPosts(data.posts, true);
            }
        } else {
            const errorMessage = data.message || 'Unknown error occurred';
            container.innerHTML = `
                <div class="col-12 text-center py-4">
                    <p class="text-danger">Error loading bookmarks: ${errorMessage}</p>
                    <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadBookmarks('${containerId}')">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error loading bookmarks:', error);
        container.innerHTML = `
            <div class="col-12 text-center py-4">
                <p class="text-danger">Network error: ${error.message}</p>
                <button class="btn btn-sm btn-outline-primary mt-2" onclick="loadBookmarks('${containerId}')">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            </div>
        `;
    });
}

function renderPosts(posts, showOriginalButton = false) {
    return posts.map(post => `
        <div class="col-lg-8 mx-auto mb-4">
            <div class="card post-card" data-post-id="${post.id}" data-post-type="${post.post_type}">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center">
                        <img src="${post.profile_img ? '../uploads/profile_picture/' + post.profile_img : '../uploads/profile_picture/no_image.png'}" 
                             class="rounded-circle me-2" width="40" height="40" alt="Profile">
                        <div>
                            <h6 class="mb-0">${post.username || 'Unknown User'}</h6>
                            <small class="text-muted">${post.created_at ? new Date(post.created_at).toLocaleDateString() : ''}</small>
                        </div>
                    </div>
                    <span class="badge bg-${post.post_type === 'recipe' ? 'success' : post.post_type === 'meal_plan' ? 'warning' : 'info'}">
                        ${post.post_type === 'meal_plan' ? 'Meal Plan' : (post.post_type && post.post_type.charAt ? post.post_type.charAt(0).toUpperCase() + post.post_type.slice(1) : 'Post')}
                    </span>
                </div>
                <div class="card-body">
                    <h5 class="card-title">${post.title || 'Untitled'}</h5>
                    <p class="card-text">${post.content ? post.content.replace(/\n/g, '<br>') : ''}</p>
                    ${renderPostDetails(post)}
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-danger like-btn ${post.is_liked ? 'active' : ''}" 
                                        data-post-id="${post.id}">
                                    <i class="bi bi-heart${post.is_liked ? '-fill' : ''}"></i>
                                    <span class="likes-count">${post.likes_count}</span>
                                </button>
                                <button type="button" class="btn btn-outline-primary comment-btn" data-post-id="${post.id}">
                                    <i class="bi bi-chat"></i>
                                    <span class="comments-count">${post.comments_count}</span>
                                </button>
                                <button type="button" class="btn btn-outline-success share-btn" data-post-id="${post.id}">
                                    <i class="bi bi-share"></i>
                                    <span class="shares-count">${post.shares_count}</span>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group" role="group">
                                ${showOriginalButton ? `
                                <button type="button" class="btn btn-outline-info btn-sm show-original-btn" 
                                        data-post-id="${post.id}" data-post-type="${post.post_type}">
                                    <i class="bi bi-eye"></i> Show Original
                                </button>
                                ` : ''}
                                <button type="button" class="btn btn-outline-warning save-btn ${post.is_saved ? 'active' : ''}" 
                                        data-post-id="${post.id}">
                                    <i class="bi bi-bookmark${post.is_saved ? '-fill' : ''}"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

function renderPostDetails(post) {
    let details = '';
    
    if (post.post_type === 'meal_plan') {
        details += '<div class="meal-plan-details mb-3">';
        details += '<div class="d-flex align-items-center mb-3">';
        details += '<i class="bi bi-calendar-week text-warning me-2" style="font-size: 1.5rem;"></i>';
        details += '<h6 class="mb-0">7-Day Meal Plan</h6>';
        details += '</div>';
        
        if (post.total_calories) {
            details += '<div class="nutrition-grid mb-3">';
            details += '<div class="nutrition-item">';
            details += '<div class="nutrition-icon">🔥</div>';
            details += '<div class="nutrition-text">';
            details += `<strong>${post.total_calories}</strong>`;
            details += '<small>Total Calories</small>';
            details += '</div></div>';
            
            if (post.total_protein) {
                details += '<div class="nutrition-item">';
                details += '<div class="nutrition-icon">🥩</div>';
                details += '<div class="nutrition-text">';
                details += `<strong>${post.total_protein}g</strong>`;
                details += '<small>Protein</small>';
                details += '</div></div>';
            }
            
            if (post.total_carbs) {
                details += '<div class="nutrition-item">';
                details += '<div class="nutrition-icon">🌾</div>';
                details += '<div class="nutrition-text">';
                details += `<strong>${post.total_carbs}g</strong>`;
                details += '<small>Carbs</small>';
                details += '</div></div>';
            }
            
            if (post.total_fat) {
                details += '<div class="nutrition-item">';
                details += '<div class="nutrition-icon">🧈</div>';
                details += '<div class="nutrition-text">';
                details += `<strong>${post.total_fat}g</strong>`;
                details += '<small>Fat</small>';
                details += '</div></div>';
            }
            details += '</div>';
        }
        
        if (post.filters_applied) {
            try {
                const filters = JSON.parse(post.filters_applied);
                const activeFilters = [];
                
                if (typeof filters === 'object' && filters !== null) {
                    // Filter out empty values and create user-friendly labels
                    if (filters.category && filters.category.trim() !== '') {
                        activeFilters.push('Category: ' + (filters.category.charAt ? filters.category.charAt(0).toUpperCase() + filters.category.slice(1) : filters.category));
                    }
                    
                    if ((filters.cal_min && filters.cal_min.trim() !== '') || (filters.cal_max && filters.cal_max.trim() !== '')) {
                        let calRange = '';
                        if (filters.cal_min && filters.cal_max && filters.cal_min.trim() !== '' && filters.cal_max.trim() !== '') {
                            calRange = filters.cal_min + '-' + filters.cal_max + ' cal';
                        } else if (filters.cal_min && filters.cal_min.trim() !== '') {
                            calRange = '≥' + filters.cal_min + ' cal';
                        } else if (filters.cal_max && filters.cal_max.trim() !== '') {
                            calRange = '≤' + filters.cal_max + ' cal';
                        }
                        if (calRange) activeFilters.push(calRange);
                    }
                    
                    if ((filters.protein_min && filters.protein_min.trim() !== '') || (filters.protein_max && filters.protein_max.trim() !== '')) {
                        let proteinRange = '';
                        if (filters.protein_min && filters.protein_max && filters.protein_min.trim() !== '' && filters.protein_max.trim() !== '') {
                            proteinRange = filters.protein_min + '-' + filters.protein_max + 'g protein';
                        } else if (filters.protein_min && filters.protein_min.trim() !== '') {
                            proteinRange = '≥' + filters.protein_min + 'g protein';
                        } else if (filters.protein_max && filters.protein_max.trim() !== '') {
                            proteinRange = '≤' + filters.protein_max + 'g protein';
                        }
                        if (proteinRange) activeFilters.push(proteinRange);
                    }
                    
                    if (filters.keyword && filters.keyword.trim() !== '') {
                        activeFilters.push('Keyword: ' + filters.keyword);
                    }
                }
                
                if (activeFilters.length > 0) {
                    details += '<div class="filters-applied mb-3">';
                    details += '<div class="d-flex align-items-center">';
                    details += '<i class="bi bi-funnel text-info me-2"></i>';
                    details += '<span class="text-muted">Applied Filters:</span>';
                    details += '</div>';
                    details += '<div class="filter-tags mt-1">';
                    activeFilters.forEach(filter => {
                        details += `<span class="badge bg-info text-white me-1 mb-1">${filter}</span>`;
                    });
                    details += '</div></div>';
                }
            } catch (e) {
                console.error('Error parsing filters:', e);
            }
        }
        
        details += '<div class="meal-plan-actions">';
        details += `<a href="saved_plans.php?shared=${post.share_token}" class="btn btn-primary btn-sm">`;
        details += '<i class="bi bi-eye"></i> View Complete Plan</a>';
        details += `<button type="button" class="btn btn-outline-secondary btn-sm ms-2" onclick="copyToClipboard('${post.share_token}')">`;
        details += '<i class="bi bi-share"></i> Copy Link</button>';
        details += '</div></div>';
    } else if (post.post_type === 'recipe') {
        details += '<div class="recipe-details row mb-3">';
        if (post.cooking_time) details += `<div class="col-md-3"><small class="text-muted"><i class="bi bi-clock"></i> ${post.cooking_time} min</small></div>`;
        if (post.servings) details += `<div class="col-md-3"><small class="text-muted"><i class="bi bi-people"></i> ${post.servings} servings</small></div>`;
        if (post.difficulty_level) details += `<div class="col-md-3"><small class="text-muted"><i class="bi bi-star"></i> ${post.difficulty_level}</small></div>`;
        if (post.calories_per_serving) details += `<div class="col-md-3"><small class="text-muted"><i class="bi bi-fire"></i> ${post.calories_per_serving} cal</small></div>`;
        details += '</div>';
        
        if (post.ingredients) {
            try {
                // Try to parse as JSON first
                const ingredients = JSON.parse(post.ingredients);
                if (Array.isArray(ingredients)) {
                    details += '<div class="ingredients mb-3"><h6><i class="bi bi-list-ul"></i> Ingredients:</h6>';
                    details += '<div class="ingredients-list">';
                    ingredients.forEach(ingredient => {
                        details += `<span class="badge bg-light text-dark me-1 mb-1">${ingredient}</span>`;
                    });
                    details += '</div></div>';
                }
            } catch (e) {
                // If JSON parsing fails, treat as plain text
                const ingredientsText = post.ingredients ? post.ingredients.trim() : '';
                if (ingredientsText) {
                    details += '<div class="ingredients mb-3"><h6><i class="bi bi-list-ul"></i> Ingredients:</h6>';
                    details += '<div class="ingredients-list">';
                    details += `<div class="ingredients-text">${ingredientsText ? ingredientsText.replace(/\n/g, '<br>') : ''}</div>`;
                    details += '</div></div>';
                }
            }
        }
        
        if (post.instructions) {
            details += `<div class="instructions mb-3"><h6><i class="bi bi-list-ol"></i> Instructions:</h6><p class="text-muted">${post.instructions ? post.instructions.replace(/\n/g, '<br>') : ''}</p></div>`;
        }
    }
    
    if (post.tags) {
        const tags = JSON.parse(post.tags);
        if (Array.isArray(tags)) {
            details += '<div class="tags mb-3">';
            tags.forEach(tag => {
                details += `<span class="badge bg-secondary me-1">#${tag}</span>`;
            });
            details += '</div>';
        }
    }
    
    return details;
}

// Copy to clipboard function
function copyToClipboard(shareToken) {
    const shareUrl = `${window.location.origin}/foodify/residents/saved_plans.php?shared=${shareToken}`;
    
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(shareUrl).then(() => {
            showNotification('Share link copied to clipboard!', 'success');
        }).catch(() => {
            fallbackCopyTextToClipboard(shareUrl);
        });
    } else {
        fallbackCopyTextToClipboard(shareUrl);
    }
}

// Copy URL to clipboard function
function copyToClipboardFromUrl(shareUrl) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(shareUrl).then(() => {
            showNotification('Share link copied to clipboard!', 'success');
        }).catch(() => {
            fallbackCopyTextToClipboard(shareUrl);
        });
    } else {
        fallbackCopyTextToClipboard(shareUrl);
    }
}

function fallbackCopyTextToClipboard(text) {
    const textArea = document.createElement("textarea");
    textArea.value = text;
    textArea.style.position = "fixed";
    textArea.style.left = "-999999px";
    textArea.style.top = "-999999px";
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    
    try {
        document.execCommand('copy');
        showNotification('Share link copied to clipboard!', 'success');
    } catch (err) {
        showNotification('Unable to copy link. Please copy manually: ' + text, 'warning');
    }
    
    document.body.removeChild(textArea);
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    // Show recipe fields if recipe is selected by default
    document.getElementById('recipe_fields').classList.add('show');
});
</script>
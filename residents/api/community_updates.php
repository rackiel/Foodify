<?php
session_start();

// Check database connection
if (!file_exists('../../config/db.php')) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database config not found']);
    exit();
}

include '../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Please log in']);
    exit();
}

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit();
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$updates = [];

try {
    // Get recent recipes shared - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT title, created_at FROM recipes_tips WHERE post_type = 'recipe' AND is_public = 1 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $updates[] = [
                'type' => 'recipe',
                'icon' => '🥗',
                'message' => 'New recipe shared: <strong>' . htmlspecialchars($row['title']) . '</strong>',
                'badge' => 'New',
                'badge_class' => 'bg-success',
                'time' => $row['created_at']
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        // Skip if table doesn't exist
    }
    
    // Get recent food donations - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as count, MAX(created_at) as latest FROM food_donations WHERE status = 'available' AND created_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row && isset($row['count']) && $row['count'] > 0) {
                $updates[] = [
                    'type' => 'donation',
                    'icon' => '🤝',
                    'message' => $row['count'] . ' new food donation' . ($row['count'] > 1 ? 's' : '') . ' posted today.',
                    'badge' => null,
                    'badge_class' => null,
                    'time' => $row['latest']
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Skip if table doesn't exist
    }
    
    // Get community impact stats - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total_donations, SUM(CASE WHEN status = 'claimed' THEN 1 ELSE 0 END) as claimed_donations FROM food_donations WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row && isset($row['claimed_donations'])) {
                $saved_food = ($row['claimed_donations'] ?? 0) * 2; // Estimate 2kg per donation
                $updates[] = [
                    'type' => 'impact',
                    'icon' => '📊',
                    'message' => 'Community has saved <strong>' . $saved_food . 'kg</strong> of food this month!',
                    'badge' => null,
                    'badge_class' => null,
                    'time' => null
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Skip if table doesn't exist
    }
    
    // Get active challenges - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT challenge_name, start_date FROM challenges WHERE status = 'active' AND start_date >= CURDATE() ORDER BY start_date ASC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $days_until = floor((strtotime($row['start_date']) - time()) / 86400);
            $time_text = $days_until == 0 ? 'today' : ($days_until == 1 ? 'tomorrow' : "in $days_until days");
            $updates[] = [
                'type' => 'challenge',
                'icon' => '🏆',
                'message' => '<strong>' . htmlspecialchars($row['challenge_name']) . '</strong> starts ' . $time_text . '! Join the challenge.',
                'badge' => 'Challenge',
                'badge_class' => 'bg-warning',
                'time' => null
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        // Skip if table doesn't exist
    }
    
    // Get recent tips shared - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT title, created_at FROM recipes_tips WHERE post_type = 'tip' AND is_public = 1 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $updates[] = [
                'type' => 'tip',
                'icon' => '💡',
                'message' => 'New tip shared: <strong>' . htmlspecialchars($row['title']) . '</strong>',
                'badge' => 'New',
                'badge_class' => 'bg-info',
                'time' => $row['created_at']
            ];
        }
        $stmt->close();
    } catch (Exception $e) {
        // Skip if table doesn't exist
    }
    
    // Get user's recent activity - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as my_plans FROM meal_plans WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            if ($row['my_plans'] > 0) {
                $updates[] = [
                    'type' => 'personal',
                    'icon' => '✨',
                    'message' => 'You have created <strong>' . $row['my_plans'] . '</strong> meal plan' . ($row['my_plans'] > 1 ? 's' : '') . ' this week!',
                    'badge' => 'You',
                    'badge_class' => 'bg-primary',
                    'time' => null
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Skip if table doesn't exist
    }
    
    echo json_encode([
        'success' => true,
        'updates' => $updates
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

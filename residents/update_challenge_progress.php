<?php
/**
 * Challenge Progress Auto-Update System
 * This script automatically updates challenge progress based on user activities
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only check authorization if called directly (not included)
if (!defined('INCLUDED_FROM_PARENT') && !isset($_SESSION['user_id'])) {
    exit('Unauthorized');
}

// Include db.php only if not already included
if (!isset($conn)) {
    include '../config/db.php';
}

/**
 * Update challenge progress for a specific user
 * Call this function after relevant user actions (donations, recipe posts, etc.)
 */
function updateChallengeProgress($conn, $user_id, $category = null) {
    try {
        // Get all active challenges the user has joined
        $query = "
            SELECT cp.participant_id, cp.challenge_id, cp.progress, cp.completed,
                   c.category, c.target_value, c.points, c.end_date
            FROM challenge_participants cp
            JOIN challenges c ON cp.challenge_id = c.challenge_id
            WHERE cp.user_id = ?
            AND cp.completed = FALSE
            AND c.status = 'active'
            AND c.end_date >= CURDATE()
        ";
        
        if ($category) {
            $query .= " AND c.category = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param('is', $user_id, $category);
        } else {
            $stmt = $conn->prepare($query);
            $stmt->bind_param('i', $user_id);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $challenges = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        foreach ($challenges as $challenge) {
            $new_progress = calculateProgress($conn, $user_id, $challenge['category'], $challenge['end_date']);
            
            // Check if challenge is completed
            $completed = ($new_progress >= $challenge['target_value']);
            $points_earned = $completed ? $challenge['points'] : 0;
            
            // Update progress
            $update_stmt = $conn->prepare("
                UPDATE challenge_participants 
                SET progress = ?,
                    completed = ?,
                    completed_at = IF(? = TRUE AND completed_at IS NULL, NOW(), completed_at),
                    points_earned = ?
                WHERE participant_id = ?
            ");
            $update_stmt->bind_param('iiiii', $new_progress, $completed, $completed, $points_earned, $challenge['participant_id']);
            $update_stmt->execute();
            $update_stmt->close();
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Challenge progress update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate actual progress based on challenge category
 */
function calculateProgress($conn, $user_id, $category, $end_date) {
    $progress = 0;
    
    try {
        switch ($category) {
            case 'donation':
                // Count food donations
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count
                    FROM food_donations
                    WHERE user_id = ?
                    AND created_at >= (SELECT joined_at FROM challenge_participants 
                                      WHERE user_id = ? 
                                      AND challenge_id IN (SELECT challenge_id FROM challenges WHERE category = 'donation')
                                      ORDER BY joined_at DESC LIMIT 1)
                    AND created_at <= ?
                ");
                $stmt->bind_param('iis', $user_id, $user_id, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $progress = $row['count'] ?? 0;
                $stmt->close();
                break;
                
            case 'recipe':
                // Count recipe posts (from recipes_tips table where type = 'recipe')
                $check_table = $conn->query("SHOW TABLES LIKE 'recipes_tips'");
                if ($check_table && $check_table->num_rows > 0) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as count
                        FROM recipes_tips
                        WHERE user_id = ?
                        AND type = 'recipe'
                        AND created_at >= (SELECT joined_at FROM challenge_participants 
                                          WHERE user_id = ? 
                                          AND challenge_id IN (SELECT challenge_id FROM challenges WHERE category = 'recipe')
                                          ORDER BY joined_at DESC LIMIT 1)
                        AND created_at <= ?
                    ");
                    $stmt->bind_param('iis', $user_id, $user_id, $end_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $progress = $row['count'] ?? 0;
                    $stmt->close();
                }
                break;
                
            case 'community':
                // Count community engagement (comments, likes, shares)
                $engagement = 0;
                
                // Count comments on announcements
                $check_table = $conn->query("SHOW TABLES LIKE 'announcement_comments'");
                if ($check_table && $check_table->num_rows > 0) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as count
                        FROM announcement_comments
                        WHERE user_id = ?
                        AND created_at >= (SELECT joined_at FROM challenge_participants 
                                          WHERE user_id = ? 
                                          AND challenge_id IN (SELECT challenge_id FROM challenges WHERE category = 'community')
                                          ORDER BY joined_at DESC LIMIT 1)
                        AND created_at <= ?
                    ");
                    $stmt->bind_param('iis', $user_id, $user_id, $end_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $engagement += $row['count'] ?? 0;
                    $stmt->close();
                }
                
                // Count comments on donations
                $check_table = $conn->query("SHOW TABLES LIKE 'donation_comments'");
                if ($check_table && $check_table->num_rows > 0) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as count
                        FROM donation_comments
                        WHERE user_id = ?
                        AND created_at >= (SELECT joined_at FROM challenge_participants 
                                          WHERE user_id = ? 
                                          AND challenge_id IN (SELECT challenge_id FROM challenges WHERE category = 'community')
                                          ORDER BY joined_at DESC LIMIT 1)
                        AND created_at <= ?
                    ");
                    $stmt->bind_param('iis', $user_id, $user_id, $end_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $engagement += $row['count'] ?? 0;
                    $stmt->close();
                }
                
                // Count recipe comments
                $check_table = $conn->query("SHOW TABLES LIKE 'recipe_comments'");
                if ($check_table && $check_table->num_rows > 0) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as count
                        FROM recipe_comments
                        WHERE user_id = ?
                        AND created_at >= (SELECT joined_at FROM challenge_participants 
                                          WHERE user_id = ? 
                                          AND challenge_id IN (SELECT challenge_id FROM challenges WHERE category = 'community')
                                          ORDER BY joined_at DESC LIMIT 1)
                        AND created_at <= ?
                    ");
                    $stmt->bind_param('iis', $user_id, $user_id, $end_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $engagement += $row['count'] ?? 0;
                    $stmt->close();
                }
                
                $progress = $engagement;
                break;
                
            case 'waste_reduction':
                // Count ingredients used/managed before expiry
                $check_table = $conn->query("SHOW TABLES LIKE 'used_ingredients'");
                if ($check_table && $check_table->num_rows > 0) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as count
                        FROM used_ingredients
                        WHERE user_id = ?
                        AND date_used >= (SELECT joined_at FROM challenge_participants 
                                         WHERE user_id = ? 
                                         AND challenge_id IN (SELECT challenge_id FROM challenges WHERE category = 'waste_reduction')
                                         ORDER BY joined_at DESC LIMIT 1)
                        AND date_used <= ?
                    ");
                    $stmt->bind_param('iis', $user_id, $user_id, $end_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $progress = $row['count'] ?? 0;
                    $stmt->close();
                }
                break;
                
            case 'sustainability':
                // Count multiple sustainable actions (donations + waste reduction + recipes)
                $sustainable_actions = 0;
                
                // Food donations
                $stmt = $conn->prepare("
                    SELECT COUNT(*) as count
                    FROM food_donations
                    WHERE user_id = ?
                    AND created_at >= (SELECT joined_at FROM challenge_participants 
                                      WHERE user_id = ? 
                                      AND challenge_id IN (SELECT challenge_id FROM challenges WHERE category = 'sustainability')
                                      ORDER BY joined_at DESC LIMIT 1)
                    AND created_at <= ?
                ");
                $stmt->bind_param('iis', $user_id, $user_id, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                $row = $result->fetch_assoc();
                $sustainable_actions += $row['count'] ?? 0;
                $stmt->close();
                
                // Used ingredients (waste reduction)
                $check_table = $conn->query("SHOW TABLES LIKE 'used_ingredients'");
                if ($check_table && $check_table->num_rows > 0) {
                    $stmt = $conn->prepare("
                        SELECT COUNT(*) as count
                        FROM used_ingredients
                        WHERE user_id = ?
                        AND date_used >= (SELECT joined_at FROM challenge_participants 
                                         WHERE user_id = ? 
                                         AND challenge_id IN (SELECT challenge_id FROM challenges WHERE category = 'sustainability')
                                         ORDER BY joined_at DESC LIMIT 1)
                        AND date_used <= ?
                    ");
                    $stmt->bind_param('iis', $user_id, $user_id, $end_date);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $row = $result->fetch_assoc();
                    $sustainable_actions += $row['count'] ?? 0;
                    $stmt->close();
                }
                
                $progress = $sustainable_actions;
                break;
                
            default:
                $progress = 0;
        }
        
    } catch (Exception $e) {
        error_log("Progress calculation error: " . $e->getMessage());
    }
    
    return $progress;
}

// If called directly via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_progress'])) {
    header('Content-Type: application/json');
    $user_id = $_SESSION['user_id'];
    $category = $_POST['category'] ?? null;
    
    $success = updateChallengeProgress($conn, $user_id, $category);
    
    echo json_encode(['success' => $success]);
    exit();
}

// Auto-update all users' challenge progress (can be called via cron)
if (isset($_GET['auto_update']) && $_GET['auto_update'] === 'all') {
    $result = $conn->query("
        SELECT DISTINCT user_id 
        FROM challenge_participants 
        WHERE completed = FALSE
    ");
    
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            updateChallengeProgress($conn, $row['user_id']);
        }
    }
    
    echo "Challenge progress updated for all active participants.";
    exit();
}
?>


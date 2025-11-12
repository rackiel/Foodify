<?php
session_start();
include '../../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];

// Handle POST requests (for joining challenges)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'join') {
        $challenge_id = isset($_POST['challenge_id']) ? intval($_POST['challenge_id']) : 0;
        
        if ($challenge_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid challenge ID']);
            exit();
        }
        
        try {
            // Check if already participating (use challenge_participants table)
            $stmt = $conn->prepare("SELECT participant_id FROM challenge_participants WHERE user_id = ? AND challenge_id = ?");
            $stmt->bind_param("ii", $user_id, $challenge_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result && $result->num_rows > 0) {
                echo json_encode(['success' => false, 'message' => 'You are already participating in this challenge']);
                $stmt->close();
                exit();
            }
            $stmt->close();
            
            // Add user to challenge (use challenge_participants table)
            $stmt = $conn->prepare("INSERT INTO challenge_participants (challenge_id, user_id, progress, completed, joined_at) VALUES (?, ?, 0, FALSE, NOW())");
            $stmt->bind_param("ii", $challenge_id, $user_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                echo json_encode(['success' => true, 'message' => 'Successfully joined the challenge!']);
            } else {
                $stmt->close();
                echo json_encode(['success' => false, 'message' => 'Failed to join challenge']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// Fetch challenges data - Same pattern as challenges_events.php
$challenges = [];

try {
    // Get all active challenges with user participation info (matching challenges_events.php)
    try {
        $stmt = $conn->prepare("
            SELECT c.*,
                   cp.participant_id,
                   cp.progress,
                   cp.completed as user_completed,
                   cp.joined_at,
                   COUNT(DISTINCT cp_all.participant_id) as total_participants
            FROM challenges c
            LEFT JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id AND cp.user_id = ?
            LEFT JOIN challenge_participants cp_all ON c.challenge_id = cp_all.challenge_id
            WHERE c.status = 'active'
            AND c.end_date >= CURDATE()
            GROUP BY c.challenge_id
            ORDER BY c.start_date DESC, c.created_at DESC
            LIMIT 6
        ");
        
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $progress = $row['progress'] ?? 0;
                $targetValue = $row['target_value'] ?? 100;
                $progressPercentage = $targetValue > 0 ? round(($progress / $targetValue) * 100) : 0;
                
                $challenges[] = [
                    'challenge_id' => (int)$row['challenge_id'],
                    'challenge_name' => htmlspecialchars($row['title']),
                    'description' => htmlspecialchars($row['description'] ?? ''),
                    'start_date' => date('M j, Y', strtotime($row['start_date'])),
                    'end_date' => date('M j, Y', strtotime($row['end_date'])),
                    'status' => $row['status'],
                    'participants_count' => (int)$row['total_participants'],
                    'is_participating' => !empty($row['participant_id']),
                    'user_progress' => $progressPercentage
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // If challenges table doesn't exist or query fails, return empty array
    }
    
    echo json_encode([
        'success' => true,
        'challenges' => $challenges
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>


<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include '../config/db.php';

$challenge_id = intval($_GET['challenge_id'] ?? 0);

if ($challenge_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid challenge ID']);
    exit();
}

try {
    // Get challenge target value
    $stmt = $conn->prepare("SELECT target_value FROM challenges WHERE challenge_id = ?");
    $stmt->bind_param('i', $challenge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $challenge = $result->fetch_assoc();
    $stmt->close();
    
    if (!$challenge) {
        echo json_encode(['success' => false, 'message' => 'Challenge not found']);
        exit();
    }
    
    // Get participants
    $stmt = $conn->prepare("
        SELECT cp.*, ua.full_name, ua.profile_img, ua.email
        FROM challenge_participants cp
        JOIN user_accounts ua ON cp.user_id = ua.user_id
        WHERE cp.challenge_id = ?
        ORDER BY cp.completed DESC, cp.progress DESC, cp.joined_at ASC
    ");
    $stmt->bind_param('i', $challenge_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $participants = [];
    
    while ($row = $result->fetch_assoc()) {
        $row['target_value'] = $challenge['target_value'];
        $participants[] = $row;
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'participants' => $participants
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>


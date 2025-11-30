<?php
session_start();
include '../config/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'get_expiring') {
    try {
        // Get active ingredients expiring within 7 days
        $stmt = $conn->prepare("
            SELECT 
                ingredient_id,
                ingredient_name,
                category,
                expiration_date,
                DATEDIFF(expiration_date, CURDATE()) as days_until
            FROM ingredient 
            WHERE status = 'active' 
            AND user_id = ?
            AND expiration_date IS NOT NULL
            AND expiration_date >= CURDATE()
            AND expiration_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY expiration_date ASC
        ");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $ingredients = [];
        while ($row = $result->fetch_assoc()) {
            $ingredients[] = [
                'ingredient_id' => $row['ingredient_id'],
                'ingredient_name' => $row['ingredient_name'],
                'category' => $row['category'],
                'expiration_date' => $row['expiration_date'],
                'days_until' => (int)$row['days_until']
            ];
        }
        $stmt->close();
        
        echo json_encode([
            'success' => true,
            'ingredients' => $ingredients,
            'count' => count($ingredients)
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching expiring ingredients: ' . $e->getMessage()
        ]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>


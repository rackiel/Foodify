<?php
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

if (isset($_GET['id'])) {
    $ingredient_id = intval($_GET['id']);
    
    // Only allow access to user's own ingredients
    $stmt = $conn->prepare("SELECT * FROM ingredient WHERE ingredient_id=? AND user_id=?");
    $stmt->bind_param('ii', $ingredient_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $ingredient = $result->fetch_assoc();
        echo json_encode(['success' => true, 'ingredient' => $ingredient]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Ingredient not found or access denied']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'No ID provided']);
}
?>


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

// Initialize default data
$days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$chart_data = array_fill(0, 7, 0);
$stats = [
    'total_plans' => 0,
    'plans_this_month' => 0,
    'avg_calories' => 0
];

try {
    // Get meal plan data for the last 7 days - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT DATE(created_at) as date, COUNT(*) as meal_plans_count FROM meal_plans WHERE user_id = ? AND created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY date ASC");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $chart_data_temp = array_fill_keys($days, 0);
            
            while ($row = $result->fetch_assoc()) {
                $day_name = date('D', strtotime($row['date']));
                $chart_data_temp[$day_name] = (int)$row['meal_plans_count'];
            }
            
            $chart_data = array_values($chart_data_temp);
        }
        $stmt->close();
    } catch (Exception $e) {
        // Keep default values if query fails
    }
    
    // Get additional statistics - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT COUNT(*) as total_plans, COUNT(CASE WHEN created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN 1 END) as plans_this_month, AVG(total_calories) as avg_calories FROM meal_plans WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stats_result = $stmt->get_result();
        
        if ($stats_result && $stats_result->num_rows > 0) {
            $stats = $stats_result->fetch_assoc();
        }
        $stmt->close();
    } catch (Exception $e) {
        // Keep default values if query fails
    }
    
    echo json_encode([
        'success' => true,
        'chart_data' => $chart_data,
        'labels' => $days,
        'stats' => [
            'total_plans' => isset($stats['total_plans']) ? (int)$stats['total_plans'] : 0,
            'plans_this_month' => isset($stats['plans_this_month']) ? (int)$stats['plans_this_month'] : 0,
            'avg_calories' => isset($stats['avg_calories']) ? round($stats['avg_calories'], 0) : 0
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

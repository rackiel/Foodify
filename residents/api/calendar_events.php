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
$events = [];

try {
    // Get meal plan events (when user created meal plans) - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT plan_name as title, created_at as start FROM meal_plans WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY created_at DESC LIMIT 10");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $events[] = [
                    'title' => '📅 Meal Plan: ' . htmlspecialchars($row['title']),
                    'start' => date('Y-m-d', strtotime($row['start'])),
                    'color' => '#007bff',
                    'type' => 'meal_plan'
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Continue if meal_plans table doesn't exist or query fails
    }
    
    // Get food donation events (when user posted donations) - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT title, created_at as start FROM food_donations WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY created_at DESC LIMIT 5");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $events[] = [
                    'title' => '🤝 Donation: ' . htmlspecialchars($row['title']),
                    'start' => date('Y-m-d', strtotime($row['start'])),
                    'color' => '#28a745',
                    'type' => 'donation'
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Continue if food_donations table doesn't exist or query fails
    }
    
    // Get recipe/tip sharing events - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT title, created_at as start, post_type FROM recipes_tips WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY created_at DESC LIMIT 5");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $icon = $row['post_type'] === 'recipe' ? '🥗' : '💡';
                $events[] = [
                    'title' => $icon . ' ' . ucfirst($row['post_type']) . ': ' . htmlspecialchars($row['title']),
                    'start' => date('Y-m-d', strtotime($row['start'])),
                    'color' => '#ffc107',
                    'type' => $row['post_type']
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Continue if recipes_tips table doesn't exist or query fails
    }
    
    // Get ingredient expiration alerts - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT ingredient_name, expiration_date FROM ingredients WHERE user_id = ? AND expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) ORDER BY expiration_date ASC LIMIT 5");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $events[] = [
                    'title' => '⚠️ Expiring: ' . htmlspecialchars($row['ingredient_name']),
                    'start' => date('Y-m-d', strtotime($row['expiration_date'])),
                    'color' => '#dc3545',
                    'type' => 'expiration'
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Continue if ingredients table doesn't exist or query fails
    }
    
    // Get ALL community challenge events - Same pattern as challenges_events.php
    try {
        $stmt = $conn->prepare("SELECT title, start_date, end_date, description, challenge_type, category, target_value, points FROM challenges WHERE status = 'active' AND (start_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) OR (end_date >= CURDATE() AND start_date <= CURDATE())) ORDER BY start_date ASC LIMIT 10");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $start_date = $row['start_date'];
                $end_date = $row['end_date'];
                
                // Format dates for display
                $start_formatted = date('M j, Y', strtotime($start_date));
                $end_formatted = isset($end_date) && $end_date ? date('M j, Y', strtotime($end_date)) : '';
                
                $event_data = [
                    'title' => '🏆 Challenge: ' . htmlspecialchars($row['title']),
                    'start' => date('Y-m-d', strtotime($start_date)),
                    'color' => '#6f42c1',
                    'type' => 'challenge',
                    'allDay' => true,
                    'extendedProps' => [
                        'type' => 'challenge',
                        'description' => isset($row['description']) ? htmlspecialchars($row['description']) : '',
                        'start_date_formatted' => $start_formatted,
                        'end_date_formatted' => $end_formatted,
                        'goal_type' => isset($row['challenge_type']) ? htmlspecialchars($row['challenge_type']) : '',
                        'goal_target' => isset($row['target_value']) ? (int)$row['target_value'] : 0,
                        'category' => isset($row['category']) ? htmlspecialchars($row['category']) : '',
                        'points' => isset($row['points']) ? (int)$row['points'] : 0
                    ]
                ];
                
                // Add end date if available (FullCalendar needs +1 day for inclusive end)
                if (isset($end_date) && $end_date) {
                    $event_data['end'] = date('Y-m-d', strtotime($end_date . ' +1 day'));
                }
                
                $events[] = $event_data;
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Continue if challenges table doesn't exist or query fails
    }
    
    // Get ALL announcement events - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT title, type, priority, created_at as start, content FROM announcements WHERE status = 'published' AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) ORDER BY created_at DESC LIMIT 15");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $priority_icon = '';
                $color = '#17a2b8'; // Default info color
                
                // Set color and icon based on priority
                switch($row['priority']) {
                    case 'critical':
                        $priority_icon = '🚨 ';
                        $color = '#dc3545'; // Red
                        break;
                    case 'high':
                        $priority_icon = '⚠️ ';
                        $color = '#fd7e14'; // Orange
                        break;
                    case 'medium':
                        $priority_icon = '📢 ';
                        $color = '#17a2b8'; // Info
                        break;
                    case 'low':
                        $priority_icon = '💬 ';
                        $color = '#6c757d'; // Gray
                        break;
                    default:
                        $priority_icon = '📢 ';
                }
                
                $event_data = [
                    'title' => $priority_icon . htmlspecialchars($row['title']),
                    'start' => date('Y-m-d', strtotime($row['start'])),
                    'color' => $color,
                    'type' => 'announcement',
                    'allDay' => true
                ];
                
                // Add content as extended props
                if (isset($row['content']) && $row['content']) {
                    $event_data['extendedProps'] = [
                        'priority' => $row['priority'],
                        'announcement_type' => $row['type']
                    ];
                }
                
                $events[] = $event_data;
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Continue if announcements table doesn't exist or query fails
    }
    
    // Get community events (if events table exists) - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT event_name as title, event_date as start, event_end_date as end_date, event_type, description, location FROM events WHERE event_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND event_date <= DATE_ADD(CURDATE(), INTERVAL 60 DAY) AND status = 'active' ORDER BY event_date ASC LIMIT 10");
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $event_icon = '🎉';
                $color = '#20c997'; // Teal
                $start_date = $row['start'];
                $end_date = isset($row['end_date']) ? $row['end_date'] : null;
                
                // Set icon based on event type
                if (isset($row['event_type'])) {
                    switch($row['event_type']) {
                        case 'potluck':
                            $event_icon = '🍽️';
                            break;
                        case 'workshop':
                            $event_icon = '👨‍🍳';
                            break;
                        case 'meeting':
                            $event_icon = '🤝';
                            break;
                        case 'celebration':
                            $event_icon = '🎊';
                            break;
                        default:
                            $event_icon = '🎉';
                    }
                }
                
                // Format dates for display
                $start_formatted = date('M j, Y', strtotime($start_date));
                $end_formatted = $end_date ? date('M j, Y', strtotime($end_date)) : '';
                
                $event_data = [
                    'title' => $event_icon . ' ' . htmlspecialchars($row['title']),
                    'start' => date('Y-m-d', strtotime($start_date)),
                    'color' => $color,
                    'type' => 'community_event',
                    'allDay' => true,
                    'extendedProps' => [
                        'type' => 'community_event',
                        'event_type' => isset($row['event_type']) ? htmlspecialchars($row['event_type']) : '',
                        'description' => isset($row['description']) ? htmlspecialchars($row['description']) : '',
                        'location' => isset($row['location']) ? htmlspecialchars($row['location']) : '',
                        'start_date_formatted' => $start_formatted,
                        'end_date_formatted' => $end_formatted
                    ]
                ];
                
                // Add end date if available
                if ($end_date) {
                    $event_data['end'] = date('Y-m-d', strtotime($end_date . ' +1 day'));
                }
                
                $events[] = $event_data;
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Continue if events table doesn't exist or query fails
    }
    
    // Get user's food request deadlines - Same pattern as topbar.php
    try {
        $stmt = $conn->prepare("SELECT d.title as donation_title, r.created_at, r.status FROM food_requests r JOIN food_donations d ON r.donation_id = d.donation_id WHERE r.requester_id = ? AND r.status = 'pending' AND r.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) ORDER BY r.created_at DESC LIMIT 5");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $events[] = [
                    'title' => '📥 Request: ' . htmlspecialchars($row['donation_title']),
                    'start' => date('Y-m-d', strtotime($row['created_at'])),
                    'color' => '#e83e8c',
                    'type' => 'food_request',
                    'allDay' => true
                ];
            }
        }
        $stmt->close();
    } catch (Exception $e) {
        // Continue if query fails
    }
    
    // Sort events by date
    usort($events, function($a, $b) {
        return strtotime($a['start']) - strtotime($b['start']);
    });
    
    echo json_encode([
        'success' => true,
        'events' => $events
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

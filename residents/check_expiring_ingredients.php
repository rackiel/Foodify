<?php
/**
 * Check Expiring Ingredients Notification System
 * This script should be run daily via cron job or can be manually triggered
 * It checks for ingredients expiring within 7 days and sends email notifications
 */

include '../config/db.php';
include '../phpmailer_setting.php';

// Get all active ingredients expiring within 7 days
$query = "SELECT i.*, u.email, u.firstname, u.lastname 
          FROM ingredient i 
          JOIN users u ON i.user_id = u.user_id 
          WHERE i.status = 'active' 
          AND i.expiration_date IS NOT NULL
          AND i.expiration_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
          ORDER BY u.user_id, i.expiration_date";

$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    // Group ingredients by user
    $user_ingredients = [];
    while ($row = $result->fetch_assoc()) {
        $user_id = $row['user_id'];
        if (!isset($user_ingredients[$user_id])) {
            $user_ingredients[$user_id] = [
                'email' => $row['email'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'ingredients' => []
            ];
        }
        
        // Calculate days until expiration
        $expiration_date = new DateTime($row['expiration_date']);
        $today = new DateTime();
        $interval = $today->diff($expiration_date);
        $days_until = (int)$interval->format('%R%a');
        
        $user_ingredients[$user_id]['ingredients'][] = [
            'name' => $row['ingredient_name'],
            'category' => $row['category'],
            'expiration_date' => $row['expiration_date'],
            'days_until' => $days_until
        ];
    }
    
    // Send email to each user
    foreach ($user_ingredients as $user_id => $user_data) {
        $mail = new PHPMailer(true);
        
        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'docvic.santiago@gmail.com';
            $mail->Password   = 'zyzphvfzxadjmems';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            
            // Recipients
            $mail->setFrom('docvic.santiago@gmail.com', 'Foodify');
            $mail->addAddress($user_data['email'], $user_data['firstname'] . ' ' . $user_data['lastname']);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'Foodify: Ingredients Expiring Soon Alert';
            
            // Build email body
            $body = '<html><body style="font-family: Arial, sans-serif; color: #333;">';
            $body .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border-radius: 10px;">';
            $body .= '<h2 style="color: #ff9800; text-align: center;">⚠️ Ingredients Expiring Soon!</h2>';
            $body .= '<p>Dear ' . htmlspecialchars($user_data['firstname']) . ',</p>';
            $body .= '<p>You have ingredients that are expiring soon. Please review and use them before they expire:</p>';
            $body .= '<table style="width: 100%; border-collapse: collapse; margin: 20px 0;">';
            $body .= '<thead><tr style="background-color: #4caf50; color: white;">';
            $body .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Ingredient</th>';
            $body .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Category</th>';
            $body .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Expires</th>';
            $body .= '<th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Days Left</th>';
            $body .= '</tr></thead><tbody>';
            
            foreach ($user_data['ingredients'] as $ingredient) {
                $urgency_color = $ingredient['days_until'] <= 2 ? '#f44336' : '#ff9800';
                $body .= '<tr style="background-color: white;">';
                $body .= '<td style="padding: 10px; border: 1px solid #ddd;"><strong>' . htmlspecialchars($ingredient['name']) . '</strong></td>';
                $body .= '<td style="padding: 10px; border: 1px solid #ddd;">' . htmlspecialchars($ingredient['category']) . '</td>';
                $body .= '<td style="padding: 10px; border: 1px solid #ddd;">' . date('M d, Y', strtotime($ingredient['expiration_date'])) . '</td>';
                $body .= '<td style="padding: 10px; border: 1px solid #ddd; color: ' . $urgency_color . '; font-weight: bold;">' . $ingredient['days_until'] . ' day(s)</td>';
                $body .= '</tr>';
            }
            
            $body .= '</tbody></table>';
            $body .= '<p style="margin-top: 20px;">Take action now to reduce food waste!</p>';
            $body .= '<div style="text-align: center; margin-top: 30px;">';
            $body .= '<a href="http://localhost/foodify/residents/input_ingredients.php" style="display: inline-block; padding: 12px 30px; background-color: #4caf50; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">View My Ingredients</a>';
            $body .= '</div>';
            $body .= '<hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">';
            $body .= '<p style="font-size: 12px; color: #888; text-align: center;">This is an automated notification from Foodify. Please do not reply to this email.</p>';
            $body .= '</div></body></html>';
            
            $mail->Body = $body;
            $mail->AltBody = strip_tags($body);
            
            $mail->send();
            echo "Notification sent to: " . $user_data['email'] . "\n";
            
        } catch (Exception $e) {
            echo "Failed to send email to " . $user_data['email'] . ": {$mail->ErrorInfo}\n";
        }
    }
    
    echo "\nTotal users notified: " . count($user_ingredients) . "\n";
} else {
    echo "No ingredients expiring soon.\n";
}

// Also update expired ingredients status
$conn->query("UPDATE ingredient SET status='expired' WHERE expiration_date < CURDATE() AND status='active'");
echo "Updated expired ingredients status.\n";
?>


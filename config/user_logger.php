<?php
/**
 * User Activity Logger
 * Helper functions to log user activities across the platform
 */

/**
 * Log a user activity
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID performing the action
 * @param string $action_type Type of action (e.g., 'login', 'create', 'update', 'delete', 'view')
 * @param string $action_description Description of the action
 * @param string $module Module/feature where action occurred (e.g., 'donations', 'profile', 'challenges')
 * @param int|null $related_id ID of related record (optional)
 * @param string|null $related_type Type of related record (optional)
 * @param array|null $metadata Additional metadata as array (will be JSON encoded)
 * @return bool Success status
 */
function logUserActivity($conn, $user_id, $action_type, $action_description, $module, $related_id = null, $related_type = null, $metadata = null) {
    try {
        // Get user IP address
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        
        // Get user agent
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        // Check if user_logs table exists
        $check_table = $conn->query("SHOW TABLES LIKE 'user_logs'");
        if (!$check_table || $check_table->num_rows === 0) {
            // Table doesn't exist, return false silently
            return false;
        }
        
        // Prepare metadata JSON
        $metadata_json = null;
        if ($metadata !== null && is_array($metadata)) {
            $metadata_json = json_encode($metadata);
        }
        
        // Insert log entry
        $stmt = $conn->prepare("
            INSERT INTO user_logs (user_id, action_type, action_description, module, ip_address, user_agent, related_id, related_type, metadata)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param(
            'issssssss',
            $user_id,
            $action_type,
            $action_description,
            $module,
            $ip_address,
            $user_agent,
            $related_id,
            $related_type,
            $metadata_json
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    } catch (Exception $e) {
        // Log error silently to avoid breaking the application
        error_log("User logger error: " . $e->getMessage());
        return false;
    }
}

/**
 * Quick log functions for common actions
 */

function logLogin($conn, $user_id, $email) {
    return logUserActivity($conn, $user_id, 'login', "User logged in: {$email}", 'authentication');
}

function logLogout($conn, $user_id, $email) {
    return logUserActivity($conn, $user_id, 'logout', "User logged out: {$email}", 'authentication');
}

function logDonationCreate($conn, $user_id, $donation_id, $title) {
    return logUserActivity($conn, $user_id, 'create', "Created donation: {$title}", 'donations', $donation_id, 'donation');
}

function logDonationUpdate($conn, $user_id, $donation_id, $title) {
    return logUserActivity($conn, $user_id, 'update', "Updated donation: {$title}", 'donations', $donation_id, 'donation');
}

function logDonationDelete($conn, $user_id, $donation_id, $title) {
    return logUserActivity($conn, $user_id, 'delete', "Deleted donation: {$title}", 'donations', $donation_id, 'donation');
}

function logProfileUpdate($conn, $user_id) {
    return logUserActivity($conn, $user_id, 'update', "Updated profile information", 'profile', $user_id, 'user');
}

function logChallengeJoin($conn, $user_id, $challenge_id, $challenge_name) {
    return logUserActivity($conn, $user_id, 'join', "Joined challenge: {$challenge_name}", 'challenges', $challenge_id, 'challenge');
}

function logAnnouncementView($conn, $user_id, $announcement_id, $title) {
    return logUserActivity($conn, $user_id, 'view', "Viewed announcement: {$title}", 'announcements', $announcement_id, 'announcement');
}

function logRecipeSearch($conn, $user_id, $ingredients) {
    return logUserActivity($conn, $user_id, 'search', "Searched recipes with ingredients: {$ingredients}", 'recipes', null, null, ['ingredients' => $ingredients]);
}

?>


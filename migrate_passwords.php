<?php
/**
 * Password Migration Script
 * This script migrates existing MD5 passwords to secure password_hash()
 * Run this script ONCE after updating the authentication system
 */

include 'config/db.php';

echo "<h2>Password Migration Script</h2>";
echo "<p>Migrating existing MD5 passwords to secure password_hash()...</p>";

// Get all users with MD5 passwords (assuming they are 32 characters long)
$stmt = $conn->prepare("SELECT user_id, password_hash FROM user_accounts WHERE LENGTH(password_hash) = 32");
$stmt->execute();
$result = $stmt->get_result();

$migrated_count = 0;
$error_count = 0;

echo "<ul>";

while ($user = $result->fetch_assoc()) {
    $user_id = $user['user_id'];
    $old_hash = $user['password_hash'];
    
    // For existing MD5 hashes, we need to generate a new random password
    // and send it to the user via email, OR we can keep the MD5 hash
    // and update the system to check both old and new formats during transition
    
    // Option 1: Generate a temporary password and hash it properly
    $temp_password = bin2hex(random_bytes(8)); // 16 character temporary password
    $new_hash = password_hash($temp_password, PASSWORD_DEFAULT);
    
    // Update the password in database
    $update_stmt = $conn->prepare("UPDATE user_accounts SET password_hash = ?, temp_password = ? WHERE user_id = ?");
    $update_stmt->bind_param('ssi', $new_hash, $temp_password, $user_id);
    
    if ($update_stmt->execute()) {
        echo "<li>User ID {$user_id}: Migrated successfully. Temporary password: {$temp_password}</li>";
        $migrated_count++;
    } else {
        echo "<li>User ID {$user_id}: Migration failed</li>";
        $error_count++;
    }
    
    $update_stmt->close();
}

echo "</ul>";

echo "<h3>Migration Summary:</h3>";
echo "<p>Successfully migrated: {$migrated_count} accounts</p>";
echo "<p>Failed migrations: {$error_count} accounts</p>";

if ($migrated_count > 0) {
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Important Notes:</h4>";
    echo "<ul>";
    echo "<li>All migrated users have been assigned temporary passwords</li>";
    echo "<li>Users will need to use 'Forgot Password' to reset their passwords</li>";
    echo "<li>The authentication system now supports both old MD5 and new secure hashes during transition</li>";
    echo "<li>You can delete this migration script after confirming everything works</li>";
    echo "</ul>";
    echo "</div>";
}

$stmt->close();
$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
ul { list-style-type: disc; margin-left: 20px; }
li { margin: 5px 0; }
</style>

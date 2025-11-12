<?php
/**
 * Password Migration Test Script
 * This script tests the new password hashing system
 */

include 'config/db.php';

echo "<h2>Password Migration Test</h2>";

// Test 1: Check if we can create a new secure password hash
echo "<h3>Test 1: Creating new secure password hash</h3>";
$test_password = "testpassword123";
$secure_hash = password_hash($test_password, PASSWORD_DEFAULT);
echo "<p>Original password: {$test_password}</p>";
echo "<p>Secure hash: {$secure_hash}</p>";
echo "<p>Hash length: " . strlen($secure_hash) . " characters</p>";

// Test 2: Verify the password against the hash
echo "<h3>Test 2: Verifying password against hash</h3>";
$verification_result = password_verify($test_password, $secure_hash);
echo "<p>Password verification: " . ($verification_result ? "SUCCESS" : "FAILED") . "</p>";

// Test 3: Check existing users in database
echo "<h3>Test 3: Database password hash analysis</h3>";
$stmt = $conn->prepare("SELECT user_id, username, LENGTH(password_hash) as hash_length, 
                       CASE 
                           WHEN LENGTH(password_hash) = 32 THEN 'MD5 (Legacy)'
                           WHEN LENGTH(password_hash) > 50 THEN 'Secure Hash'
                           ELSE 'Unknown Format'
                       END as hash_type
                       FROM user_accounts LIMIT 10");
$stmt->execute();
$result = $stmt->get_result();

echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>User ID</th><th>Username</th><th>Hash Length</th><th>Hash Type</th></tr>";

while ($user = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>{$user['user_id']}</td>";
    echo "<td>{$user['username']}</td>";
    echo "<td>{$user['hash_length']}</td>";
    echo "<td>{$user['hash_type']}</td>";
    echo "</tr>";
}

echo "</table>";

// Test 4: Count hash types
echo "<h3>Test 4: Password hash statistics</h3>";
$count_stmt = $conn->prepare("SELECT 
    COUNT(CASE WHEN LENGTH(password_hash) = 32 THEN 1 END) as md5_count,
    COUNT(CASE WHEN LENGTH(password_hash) > 50 THEN 1 END) as secure_count,
    COUNT(*) as total_users
    FROM user_accounts");
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$stats = $count_result->fetch_assoc();

echo "<p>Total users: {$stats['total_users']}</p>";
echo "<p>MD5 (Legacy) hashes: {$stats['md5_count']}</p>";
echo "<p>Secure hashes: {$stats['secure_count']}</p>";

if ($stats['md5_count'] > 0) {
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>Warning:</strong> {$stats['md5_count']} users still have MD5 password hashes. ";
    echo "Run the migration script or have users log in to automatically migrate their passwords.";
    echo "</div>";
} else {
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<strong>Success:</strong> All users have secure password hashes!";
    echo "</div>";
}

$stmt->close();
$count_stmt->close();
$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
table { border-collapse: collapse; margin: 10px 0; }
th, td { text-align: left; padding: 8px; }
th { background-color: #f2f2f2; }
</style>

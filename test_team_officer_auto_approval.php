<?php
// Test script to verify team officer auto-approval
session_start();
include 'config/db.php';

echo "<h2>Team Officer Auto-Approval Test</h2>";

// Create a test team officer account
$test_username = 'test_officer_' . time();
$test_email = 'test_' . time() . '@example.com';
$test_password = password_hash('TestPassword123!', PASSWORD_DEFAULT);
$test_full_name = 'Test Team Officer';
$test_phone = '09123456789';
$test_address = 'Purok 1 - Tibanga, Iligan City';
$test_role = 'team officer';
$is_verified = 1;
$is_approved = 1;
$verification_token = bin2hex(random_bytes(16));

echo "<h3>Creating test team officer account...</h3>";
echo "<p>Username: <strong>$test_username</strong></p>";
echo "<p>Email: <strong>$test_email</strong></p>";

$stmt = $conn->prepare("
    INSERT INTO user_accounts (full_name, username, email, password_hash, role, phone_number, address, status, is_verified, is_approved, verification_token)
    VALUES (?, ?, ?, ?, ?, ?, ?, 'approved', ?, ?, ?)
");

$stmt->bind_param('sssssssiss', $test_full_name, $test_username, $test_email, $test_password, $test_role, $test_phone, $test_address, $is_verified, $is_approved, $verification_token);

if ($stmt->execute()) {
    echo "<p style='color: green;'>✅ Test team officer created successfully!</p>";

    // Verify the created account
    $verify_stmt = $conn->prepare("
        SELECT user_id, username, email, role, is_verified, is_approved, status 
        FROM user_accounts 
        WHERE username = ?
    ");
    $verify_stmt->bind_param('s', $test_username);
    $verify_stmt->execute();
    $result = $verify_stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        echo "<h3>✅ Account Details:</h3>";
        echo "<table border='1' cellpadding='10'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>User ID</td><td>" . htmlspecialchars($user['user_id']) . "</td></tr>";
        echo "<tr><td>Username</td><td>" . htmlspecialchars($user['username']) . "</td></tr>";
        echo "<tr><td>Email</td><td>" . htmlspecialchars($user['email']) . "</td></tr>";
        echo "<tr><td>Role</td><td>" . htmlspecialchars($user['role']) . "</td></tr>";
        echo "<tr><td>Is Verified</td><td style='background: " . ($user['is_verified'] == 1 ? 'lightgreen' : 'lightcoral') . "'>" . $user['is_verified'] . " " . ($user['is_verified'] == 1 ? '✅' : '❌') . "</td></tr>";
        echo "<tr><td>Is Approved</td><td style='background: " . ($user['is_approved'] == 1 ? 'lightgreen' : 'lightcoral') . "'>" . $user['is_approved'] . " " . ($user['is_approved'] == 1 ? '✅' : '❌') . "</td></tr>";
        echo "<tr><td>Status</td><td>" . htmlspecialchars($user['status']) . "</td></tr>";
        echo "</table>";

        if ($user['is_verified'] == 1 && $user['is_approved'] == 1) {
            echo "<p style='color: green; font-size: 18px;'><strong>✅ This account can login immediately!</strong></p>";
            echo "<h3>Test Login:</h3>";
            echo "<p><strong>Username:</strong> $test_username</p>";
            echo "<p><strong>Password:</strong> TestPassword123!</p>";
            echo "<p><a href='index.php?tab=login' style='background: green; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Try Login</a></p>";
        } else {
            echo "<p style='color: red; font-size: 18px;'><strong>❌ This account CANNOT login yet!</strong></p>";
        }
    }
    $verify_stmt->close();
} else {
    echo "<p style='color: red;'>❌ Error creating test account: " . htmlspecialchars($stmt->error) . "</p>";
}

$stmt->close();

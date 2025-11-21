<?php
include 'config/db.php';

echo "<h1>Fix User 33 (Sahawi Malik)</h1>";

// Fix user 33 directly
$stmt = $conn->prepare("UPDATE user_accounts SET is_approved=1, is_verified=1, status='active' WHERE user_id=33");
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo "<p style='color: green;'><strong>✓ User 33 (Sahawi Malik) has been fixed!</strong></p>";
    echo "<p>is_approved set to: 1</p>";
    echo "<p>is_verified set to: 1</p>";
    echo "<p>status set to: active</p>";
    echo "<p><strong>User can now login!</strong></p>";
} else {
    echo "<p>No changes made (user might already be fixed or doesn't exist)</p>";
}

$stmt->close();

// Verify the fix
$user = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status FROM user_accounts WHERE user_id=33")->fetch_assoc();

echo "<h2>Verification</h2>";
if ($user) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>User ID</th><td>" . $user['user_id'] . "</td></tr>";
    echo "<tr><th>Full Name</th><td>" . htmlspecialchars($user['full_name']) . "</td></tr>";
    echo "<tr><th>Email</th><td>" . htmlspecialchars($user['email']) . "</td></tr>";
    echo "<tr><th>is_verified</th><td style='background: " . ($user['is_verified'] ? '#90EE90' : '#FFB6C6') . "'>" . $user['is_verified'] . "</td></tr>";
    echo "<tr><th>is_approved</th><td style='background: " . ($user['is_approved'] ? '#90EE90' : '#FFB6C6') . "'>" . $user['is_approved'] . "</td></tr>";
    echo "<tr><th>status</th><td>" . $user['status'] . "</td></tr>";
    echo "</table>";

    if ($user['is_verified'] == 1 && $user['is_approved'] == 1) {
        echo "<p style='color: green;'><strong>✓ User can now login!</strong></p>";
    }
}

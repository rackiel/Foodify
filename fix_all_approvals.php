<?php
include 'config/db.php';

echo "<h1>Manual User Approval Fix</h1>";

// Find all users with is_verified=1 but is_approved=0
$result = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status FROM user_accounts WHERE is_verified=1 AND is_approved=0");

echo "<h2>Users Verified But Not Approved</h2>";

if ($result && $result->num_rows > 0) {
    echo "<p>Found " . $result->num_rows . " users who are verified but not yet approved.</p>";
    echo "<p>Auto-fixing these users...</p>";

    $fixed = 0;

    while ($user = $result->fetch_assoc()) {
        // Approve them
        $user_id = $user['user_id'];
        $stmt = $conn->prepare("UPDATE user_accounts SET is_approved=1, is_verified=1, status='active' WHERE user_id=?");
        $stmt->bind_param('i', $user_id);

        if ($stmt->execute()) {
            $fixed++;
            echo "<p style='color: green;'>✓ User " . $user_id . " (" . htmlspecialchars($user['full_name']) . ") - AUTO-APPROVED</p>";
        }
        $stmt->close();
    }

    echo "<p style='font-weight: bold; color: green;'>Total fixed: $fixed users</p>";
} else {
    echo "<p style='color: orange;'>No users found in this state.</p>";
}

echo "<hr>";

// Now verify all users can login
echo "<h2>Final Verification - Users Who Can Now Login</h2>";

$can_login = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status FROM user_accounts WHERE is_verified=1 AND is_approved=1");

if ($can_login && $can_login->num_rows > 0) {
    echo "<p style='color: green;'><strong>✓ " . $can_login->num_rows . " users can now login</strong></p>";

    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th></tr>";

    while ($user = $can_login->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $user['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($user['email']) . "</td>";
        echo "<td>" . $user['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No users can login.</p>";
}

echo "<hr>";

// Check for any remaining issues
echo "<h2>Remaining Issues</h2>";

$not_verified = $conn->query("SELECT user_id, full_name, email FROM user_accounts WHERE is_verified=0");

if ($not_verified && $not_verified->num_rows > 0) {
    echo "<p>" . $not_verified->num_rows . " users still need to verify their email</p>";
} else {
    echo "<p style='color: green;'>✓ All users have verified their email</p>";
}

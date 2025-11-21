<?php
include 'config/db.php';

echo "<h2>Admin Approval Queue Check</h2>";

// Get users with status 'pending' (not yet approved)
$pending = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status FROM user_accounts WHERE status='pending' ORDER BY user_id DESC");

echo "<h3>Users Waiting for Admin Approval (status='pending')</h3>";

if ($pending && $pending->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Verified?</th><th>Currently Approved?</th><th>Status</th></tr>";

    while ($row = $pending->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td style='background: " . ($row['is_verified'] ? '#90EE90' : '#FFB6C6') . "'>" . ($row['is_verified'] ? 'YES' : 'NO') . "</td>";
        echo "<td style='background: " . ($row['is_approved'] ? '#90EE90' : '#FFB6C6') . "'>" . ($row['is_approved'] ? 'YES' : 'NO') . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: green;'><strong>✓ No users pending approval! All users are either rejected or approved.</strong></p>";
}

// Get users who are approved and verified (can login)
$active = $conn->query("SELECT user_id, full_name, email, status FROM user_accounts WHERE is_verified=1 AND is_approved=1");

echo "<h3>Active Users (Can Login)</h3>";

if ($active && $active->num_rows > 0) {
    echo "<p>Found " . $active->num_rows . " active users:</p>";
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th></tr>";

    while ($row = $active->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'>No active users found.</p>";
}

<?php
include 'config/db.php';

// Get users who are verified but not approved (stuck in "pending" state)
$result = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status, verification_token FROM user_accounts WHERE is_verified=1 AND is_approved=0");

echo "<h2>Users Verified but NOT Approved (Status: Pending)</h2>";
echo "<p>These users have verified their email but are waiting for admin approval.</p>";

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr>";
    echo "<th>User ID</th>";
    echo "<th>Full Name</th>";
    echo "<th>Email</th>";
    echo "<th>is_verified</th>";
    echo "<th>is_approved</th>";
    echo "<th>Status</th>";
    echo "</tr>";

    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . ($row['is_verified'] == 1 ? "✓ 1" : "✗ 0") . "</td>";
        echo "<td>" . ($row['is_approved'] == 1 ? "✓ 1" : "✗ 0") . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: red;'><strong>No users found with is_verified=1 and is_approved=0</strong></p>";
}

echo "<hr>";
echo "<h2>Users NOT Yet Verified (Status: Pending)</h2>";
echo "<p>These users have NOT verified their email yet.</p>";

$result2 = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status FROM user_accounts WHERE is_verified=0 AND status='pending'");

if ($result2 && $result2->num_rows > 0) {
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
    echo "<tr>";
    echo "<th>User ID</th>";
    echo "<th>Full Name</th>";
    echo "<th>Email</th>";
    echo "<th>is_verified</th>";
    echo "<th>is_approved</th>";
    echo "<th>Status</th>";
    echo "</tr>";

    while ($row = $result2->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . ($row['is_verified'] == 1 ? "✓ 1" : "✗ 0") . "</td>";
        echo "<td>" . ($row['is_approved'] == 1 ? "✓ 1" : "✗ 0") . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color: orange;'><strong>No users found with is_verified=0 and status='pending'</strong></p>";
}

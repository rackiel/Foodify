<?php
include 'config/db.php';

// Get all users with verification info
$result = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status, verification_token FROM user_accounts ORDER BY user_id DESC LIMIT 20");

echo "<h2>Recent User Verification Status</h2>";
echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr>";
echo "<th>User ID</th>";
echo "<th>Full Name</th>";
echo "<th>Email</th>";
echo "<th>Verified (is_verified)</th>";
echo "<th>Approved (is_approved)</th>";
echo "<th>Status</th>";
echo "<th>Token Length</th>";
echo "<th>Token Value (first 20 chars)</th>";
echo "</tr>";

while ($row = $result->fetch_assoc()) {
    echo "<tr>";
    echo "<td>" . htmlspecialchars($row['user_id']) . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td style='background: " . ($row['is_verified'] == 1 ? '#90EE90' : '#FFB6C6') . "'>" . ($row['is_verified'] == 1 ? "✓ YES" : "✗ NO") . "</td>";
    echo "<td style='background: " . ($row['is_approved'] == 1 ? '#90EE90' : '#FFB6C6') . "'>" . ($row['is_approved'] == 1 ? "✓ YES" : "✗ NO") . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . strlen($row['verification_token']) . " chars</td>";
    echo "<td>" . (strlen($row['verification_token']) > 0 ? substr(htmlspecialchars($row['verification_token']), 0, 20) . "..." : "NULL/EMPTY") . "</td>";
    echo "</tr>";
}

echo "</table>";

// Test verification token update
echo "<h2>Verification Token Test</h2>";

$test_token = bin2hex(random_bytes(16));
$test_user_id = 999;

echo "<p>Test token generated: " . $test_token . " (length: " . strlen($test_token) . ")</p>";

// Simulate what happens during verification
$stmt = $conn->prepare("UPDATE user_accounts SET is_verified=1 WHERE verification_token=? AND is_verified=0");
$stmt->bind_param('s', $test_token);
echo "<p>Query bound parameter type: 's' (string)</p>";
echo "<p>Affected rows if this token existed: " . $stmt->affected_rows . "</p>";
$stmt->close();

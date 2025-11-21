<?php
include 'config/db.php';

echo "<h2>Verification Token Analysis</h2>";

// Get users with pending verification
$result = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status, verification_token FROM user_accounts WHERE status='pending' OR (is_verified=0 AND is_approved=0) ORDER BY user_id DESC");

echo "<table border='1' cellpadding='10' style='border-collapse: collapse; width: 100%;'>";
echo "<tr>";
echo "<th>User ID</th>";
echo "<th>Full Name</th>";
echo "<th>Email</th>";
echo "<th>is_verified</th>";
echo "<th>is_approved</th>";
echo "<th>Status</th>";
echo "<th>Token Length</th>";
echo "<th>Token (first 30 chars)</th>";
echo "<th>Token Type</th>";
echo "</tr>";

while ($row = $result->fetch_assoc()) {
    $token = $row['verification_token'];
    $token_length = strlen($token);
    $token_type = "VALID HEX";

    // Check if token is valid hex
    if (!ctype_xdigit($token)) {
        $token_type = "❌ INVALID HEX";
    }
    if ($token_length !== 32) {
        $token_type = "❌ WRONG LENGTH (" . $token_length . ")";
    }
    if (empty($token)) {
        $token_type = "❌ EMPTY/NULL";
    }

    echo "<tr>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td style='background: " . ($row['is_verified'] == 1 ? '#90EE90' : '#FFB6C6') . "'>" . $row['is_verified'] . "</td>";
    echo "<td style='background: " . ($row['is_approved'] == 1 ? '#90EE90' : '#FFB6C6') . "'>" . $row['is_approved'] . "</td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . $token_length . "</td>";
    echo "<td><code>" . htmlspecialchars(substr($token, 0, 30)) . "</code></td>";
    echo "<td>" . $token_type . "</td>";
    echo "</tr>";
}

echo "</table>";

// Regenerate valid tokens for users with invalid tokens
echo "<h2>Fixing Invalid Tokens</h2>";

$fix_result = $conn->query("SELECT user_id FROM user_accounts WHERE (is_verified=0 OR verification_token IS NULL OR verification_token='' OR LENGTH(verification_token) != 32) AND status='pending'");

$fixed_count = 0;
if ($fix_result && $fix_result->num_rows > 0) {
    echo "<p>Found " . $fix_result->num_rows . " users with invalid tokens. Regenerating...</p>";

    while ($user = $fix_result->fetch_assoc()) {
        $new_token = bin2hex(random_bytes(16));
        $user_id = $user['user_id'];

        $update_stmt = $conn->prepare("UPDATE user_accounts SET verification_token=? WHERE user_id=?");
        $update_stmt->bind_param('si', $new_token, $user_id);

        if ($update_stmt->execute()) {
            $fixed_count++;
            echo "<p style='color: green;'>✓ User ID $user_id: Token regenerated</p>";
        }
        $update_stmt->close();
    }

    echo "<p style='color: green; font-weight: bold;'>Total fixed: $fixed_count users</p>";
} else {
    echo "<p style='color: orange;'>No users with invalid tokens found.</p>";
}

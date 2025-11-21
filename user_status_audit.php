<?php
include 'config/db.php';

echo "<h2>Complete User Status Audit</h2>";

// Get ALL users with pending status
$result = $conn->query("SELECT user_id, full_name, email, username, is_verified, is_approved, status, verification_token, created_at FROM user_accounts ORDER BY created_at DESC LIMIT 20");

echo "<table border='1' cellpadding='12' style='border-collapse: collapse; width: 100%; font-size: 13px;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>ID</th>";
echo "<th>Full Name</th>";
echo "<th>Email</th>";
echo "<th>Username</th>";
echo "<th>Verified (0/1)</th>";
echo "<th>Approved (0/1)</th>";
echo "<th>Status</th>";
echo "<th>Can Login?</th>";
echo "<th>Created</th>";
echo "</tr>";

$count_verified_not_approved = 0;
$count_not_verified = 0;
$count_can_login = 0;

while ($row = $result->fetch_assoc()) {
    $can_login = ($row['is_verified'] == 1 && $row['is_approved'] == 1);
    $status_text = "";

    if ($can_login) {
        $count_can_login++;
        $status_text = "✓ YES";
    } else if ($row['is_verified'] == 1 && $row['is_approved'] == 0) {
        $count_verified_not_approved++;
        $status_text = "❌ Verified but not approved";
    } else if ($row['is_verified'] == 0) {
        $count_not_verified++;
        $status_text = "❌ Not verified";
    }

    echo "<tr>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
    echo "<td style='background: " . ($row['is_verified'] == 1 ? '#90EE90' : '#FFB6C6') . "; text-align: center;'><strong>" . $row['is_verified'] . "</strong></td>";
    echo "<td style='background: " . ($row['is_approved'] == 1 ? '#90EE90' : '#FFB6C6') . "; text-align: center;'><strong>" . $row['is_approved'] . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td>" . $status_text . "</td>";
    echo "<td>" . $row['created_at'] . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h3>Summary</h3>";
echo "<ul>";
echo "<li><strong>Can login immediately:</strong> $count_can_login users (is_verified=1 AND is_approved=1)</li>";
echo "<li><strong>Verified but awaiting approval:</strong> $count_verified_not_approved users (is_verified=1 AND is_approved=0)</li>";
echo "<li><strong>Not yet verified:</strong> $count_not_verified users (is_verified=0)</li>";
echo "</ul>";

// Check if there are users who need email resent
echo "<h2>Verification Link Issue Detection</h2>";

$unverified = $conn->query("SELECT user_id, full_name, email, verification_token FROM user_accounts WHERE is_verified=0 AND status='pending'");

if ($unverified && $unverified->num_rows > 0) {
    echo "<p>Found " . $unverified->num_rows . " users who haven't verified yet. Checking tokens...</p>";

    while ($user = $unverified->fetch_assoc()) {
        $token = $user['verification_token'];
        $is_valid = !empty($token) && strlen($token) == 32 && ctype_xdigit($token);

        echo "<p>";
        echo "User: " . htmlspecialchars($user['full_name']) . " (" . htmlspecialchars($user['email']) . ")<br>";
        echo "Token: " . (strlen($token) > 0 ? htmlspecialchars(substr($token, 0, 30)) . "..." : "EMPTY/NULL") . "<br>";
        echo "Token Status: " . ($is_valid ? "✓ Valid hex token" : "❌ INVALID TOKEN - cannot verify") . "<br>";
        echo "</p>";
    }
}

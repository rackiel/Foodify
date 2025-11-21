<?php
include 'config/db.php';

echo "<h1>Foodify User Verification & Approval Fix</h1>";

echo "<h2>Step 1: Data Integrity Check</h2>";

// Check for valid is_verified and is_approved values
$result = $conn->query("SELECT user_id, is_verified, is_approved FROM user_accounts WHERE (is_verified NOT IN (0,1) OR is_approved NOT IN (0,1))");

$corrupted_count = $result ? $result->num_rows : 0;

if ($corrupted_count > 0) {
    echo "<p style='color: orange;'><strong>Found $corrupted_count users with corrupted is_verified/is_approved values</strong></p>";
    echo "<p>Attempting to fix...</p>";

    $fix_result = $conn->query("UPDATE user_accounts SET is_verified=IF(is_verified IN (0,1), is_verified, 0), is_approved=IF(is_approved IN (0,1), is_approved, 0)");

    if ($fix_result) {
        echo "<p style='color: green;'>✓ Fixed data corruption</p>";
    }
} else {
    echo "<p style='color: green;'>✓ No data corruption found</p>";
}

echo "<h2>Step 2: Verification Token Validation</h2>";

// Check for users with invalid or missing verification tokens
$invalid_tokens = $conn->query("SELECT user_id, full_name, email, is_verified, verification_token FROM user_accounts WHERE (verification_token IS NULL OR verification_token = '' OR LENGTH(verification_token) != 32) AND is_verified = 0");

$invalid_count = $invalid_tokens ? $invalid_tokens->num_rows : 0;

if ($invalid_count > 0) {
    echo "<p><strong>Found $invalid_count unverified users with invalid tokens</strong></p>";
    echo "<p>Regenerating verification tokens...</p>";

    $invalid_tokens = $conn->query("SELECT user_id FROM user_accounts WHERE (verification_token IS NULL OR verification_token = '' OR LENGTH(verification_token) != 32) AND is_verified = 0");

    $regenerated = 0;
    while ($user = $invalid_tokens->fetch_assoc()) {
        $new_token = bin2hex(random_bytes(16));
        $user_id = $user['user_id'];

        $update_stmt = $conn->prepare("UPDATE user_accounts SET verification_token=? WHERE user_id=?");
        $update_stmt->bind_param('si', $new_token, $user_id);

        if ($update_stmt->execute()) {
            $regenerated++;
        }
        $update_stmt->close();
    }

    echo "<p style='color: green;'>✓ Regenerated $regenerated verification tokens</p>";
} else {
    echo "<p style='color: green;'>✓ All verification tokens are valid</p>";
}

echo "<h2>Step 3: User Status Summary</h2>";

$users = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status FROM user_accounts ORDER BY user_id DESC LIMIT 50");

$stats = [
    'total' => 0,
    'can_login' => 0,
    'verified_pending_approval' => 0,
    'not_verified' => 0,
];

echo "<table border='1' cellpadding='12' style='border-collapse: collapse; width: 100%; font-size: 13px;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>ID</th>";
echo "<th>Full Name</th>";
echo "<th>Email</th>";
echo "<th>Verified?</th>";
echo "<th>Approved?</th>";
echo "<th>Status</th>";
echo "<th>Can Login?</th>";
echo "</tr>";

while ($row = $users->fetch_assoc()) {
    $stats['total']++;

    $verified = $row['is_verified'] == 1;
    $approved = $row['is_approved'] == 1;
    $can_login = $verified && $approved;

    if ($can_login) {
        $stats['can_login']++;
        $login_status = "✓ YES";
        $color = "#90EE90";
    } else if ($verified && !$approved) {
        $stats['verified_pending_approval']++;
        $login_status = "❌ Verified, awaiting approval";
        $color = "#FFFFE0";
    } else {
        $stats['not_verified']++;
        $login_status = "❌ Not verified";
        $color = "#FFB6C6";
    }

    echo "<tr>";
    echo "<td>" . $row['user_id'] . "</td>";
    echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
    echo "<td style='background: " . ($verified ? '#90EE90' : '#FFB6C6') . "; text-align: center;'><strong>" . ($verified ? '✓' : '✗') . "</strong></td>";
    echo "<td style='background: " . ($approved ? '#90EE90' : '#FFB6C6') . "; text-align: center;'><strong>" . ($approved ? '✓' : '✗') . "</strong></td>";
    echo "<td>" . htmlspecialchars($row['status']) . "</td>";
    echo "<td style='background: $color;'><strong>" . $login_status . "</strong></td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Overall Statistics</h2>";
echo "<ul>";
echo "<li><strong>Total Users:</strong> " . $stats['total'] . "</li>";
echo "<li><strong>Can Login:</strong> " . $stats['can_login'] . " (is_verified=1 AND is_approved=1)</li>";
echo "<li><strong>Verified, Awaiting Approval:</strong> " . $stats['verified_pending_approval'] . " (is_verified=1 AND is_approved=0)</li>";
echo "<li><strong>Not Verified:</strong> " . $stats['not_verified'] . " (is_verified=0)</li>";
echo "</ul>";

echo "<h2>Next Steps</h2>";
echo "<ol>";
echo "<li><strong>For users who are verified but not approved:</strong> Go to Admin > User Approvals to approve them</li>";
echo "<li><strong>For users who are not verified:</strong> They need to click the verification link in their email</li>";
echo "<li><strong>For users who cannot find their verification email:</strong> They may need to re-register or you can manually set is_verified=1</li>";
echo "</ol>";

<?php
include 'config/db.php';

echo "<h1>Complete Login Flow Diagnostic</h1>";

// Get all users and simulate their login
$users = $conn->query("SELECT user_id, full_name, email, username, password_hash, is_verified, is_approved, status FROM user_accounts");

echo "<h2>Login Eligibility for All Users</h2>";

$can_login_count = 0;
$cannot_login_count = 0;

echo "<table border='1' cellpadding='12' style='border-collapse: collapse; width: 100%;'>";
echo "<tr style='background: #f0f0f0;'>";
echo "<th>ID</th>";
echo "<th>Name</th>";
echo "<th>Email</th>";
echo "<th>is_verified</th>";
echo "<th>is_approved</th>";
echo "<th>Status</th>";
echo "<th>Login Condition Check</th>";
echo "<th>Can Login?</th>";
echo "<th>Why Not?</th>";
echo "</tr>";

while ($user = $users->fetch_assoc()) {
    $condition = "is_verified == 1 && is_approved == 1";
    $verified = $user['is_verified'] == 1;
    $approved = $user['is_approved'] == 1;
    $can_login = $verified && $approved;

    if ($can_login) {
        $can_login_count++;
        $result_text = "<span style='color: green;'><strong>✓ YES</strong></span>";
        $reason = "";
    } else {
        $cannot_login_count++;
        $reasons = [];
        if (!$verified) $reasons[] = "Not verified (is_verified=" . $user['is_verified'] . ")";
        if (!$approved) $reasons[] = "Not approved (is_approved=" . $user['is_approved'] . ")";
        $result_text = "<span style='color: red;'><strong>✗ NO</strong></span>";
        $reason = implode("; ", $reasons);
    }

    echo "<tr>";
    echo "<td>" . $user['user_id'] . "</td>";
    echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
    echo "<td>" . htmlspecialchars($user['email']) . "</td>";
    echo "<td style='background: " . ($user['is_verified'] ? '#90EE90' : '#FFB6C6') . "; text-align: center;'>" . $user['is_verified'] . "</td>";
    echo "<td style='background: " . ($user['is_approved'] ? '#90EE90' : '#FFB6C6') . "; text-align: center;'>" . $user['is_approved'] . "</td>";
    echo "<td>" . htmlspecialchars($user['status']) . "</td>";
    echo "<td><code>" . $condition . "</code></td>";
    echo "<td>" . $result_text . "</td>";
    echo "<td>" . ($reason ? "<span style='color: red;'>" . $reason . "</span>" : "") . "</td>";
    echo "</tr>";
}

echo "</table>";

echo "<h2>Summary</h2>";
echo "<p><strong>Users who can login:</strong> " . $can_login_count . "</p>";
echo "<p><strong>Users who cannot login:</strong> " . $cannot_login_count . "</p>";

echo "<h2>Instructions for User to Regain Access</h2>";

// Get a sample user who cannot login
$sample_bad_user = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status FROM user_accounts WHERE NOT (is_verified=1 AND is_approved=1) LIMIT 1")->fetch_assoc();

if ($sample_bad_user) {
    echo "<p><strong>Example User Unable to Login:</strong></p>";
    echo "<p>Name: " . htmlspecialchars($sample_bad_user['full_name']) . "</p>";
    echo "<p>Email: " . htmlspecialchars($sample_bad_user['email']) . "</p>";
    echo "<p>is_verified: " . $sample_bad_user['is_verified'] . " (needs to be 1)</p>";
    echo "<p>is_approved: " . $sample_bad_user['is_approved'] . " (needs to be 1)</p>";
    echo "<p>Status: " . $sample_bad_user['status'] . "</p>";

    echo "<p><strong>Solution:</strong></p>";
    if ($sample_bad_user['is_verified'] == 0) {
        echo "<p>1. User needs to verify their email by clicking the link in their verification email</p>";
    }
    if ($sample_bad_user['is_approved'] == 0) {
        echo "<p>2. Admin needs to approve the user in Admin > User Approvals</p>";
    }

    echo "<p><strong>Or, to manually fix this user directly in database:</strong></p>";
    echo "<code>UPDATE user_accounts SET is_verified=1, is_approved=1, status='active' WHERE user_id=" . $sample_bad_user['user_id'] . ";</code>";
}

// Check if there's a potential PHP logic error
echo "<h2>Code Review: Login Logic</h2>";
echo "<p>The login code checks: <code>\$user['is_verified'] == 1 && \$user['is_approved'] == 1</code></p>";
echo "<p>If this condition is FALSE, the modal 'Verification/Approval Pending' is shown.</p>";
echo "<p>If this condition is TRUE, the user is logged in and redirected to their dashboard.</p>";

echo "<h2>Possible Causes</h2>";
echo "<ul>";
echo "<li><strong>1. Email Verification Not Completed:</strong> User hasn't clicked the verify link in their email (is_verified=0)</li>";
echo "<li><strong>2. Admin Approval Not Completed:</strong> Admin hasn't approved the user yet (is_approved=0)</li>";
echo "<li><strong>3. Database Not Updated:</strong> The UPDATE queries for verification or approval failed silently</li>";
echo "<li><strong>4. Browser Cache/Session:</strong> Old session data cached - user needs to clear cookies and try again</li>";
echo "<li><strong>5. Status Field Mismatch:</strong> The 'status' field is set to something other than 'active' even though approval was clicked</li>";
echo "</ul>";

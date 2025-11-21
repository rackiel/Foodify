<?php
include 'config/db.php';

echo "<h2>Test: Manual Verification & Approval Flow</h2>";

// Find a user with is_verified=0 to test the verification flow
$test_user = $conn->query("SELECT user_id, full_name, email, verification_token, is_verified, is_approved FROM user_accounts WHERE is_verified=0 LIMIT 1")->fetch_assoc();

if ($test_user) {
    echo "<h3>Test User: " . htmlspecialchars($test_user['full_name']) . "</h3>";
    echo "<p>Email: " . htmlspecialchars($test_user['email']) . "</p>";
    echo "<p>Current is_verified: " . $test_user['is_verified'] . "</p>";
    echo "<p>Current is_approved: " . $test_user['is_approved'] . "</p>";
    echo "<p>Token: " . htmlspecialchars(substr($test_user['verification_token'], 0, 30) . "...") . "</p>";

    // Step 1: Simulate email verification (user clicks verify link)
    echo "<h3>Step 1: Simulating Email Verification</h3>";
    $token = $test_user['verification_token'];
    $user_id = $test_user['user_id'];

    $stmt = $conn->prepare("UPDATE user_accounts SET is_verified=1 WHERE verification_token=? AND is_verified=0");
    $stmt->bind_param('s', $token);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "<p style='color: green;'>✓ Verification successful - is_verified SET TO 1</p>";
    } else {
        echo "<p style='color: red;'>❌ Verification FAILED - token didn't match or already verified</p>";
        echo "<p>Affected rows: " . $stmt->affected_rows . "</p>";
    }
    $stmt->close();

    // Check the user now
    $updated_user = $conn->query("SELECT is_verified, is_approved FROM user_accounts WHERE user_id=$user_id")->fetch_assoc();
    echo "<p>After verification - is_verified: " . $updated_user['is_verified'] . ", is_approved: " . $updated_user['is_approved'] . "</p>";

    // Step 2: Simulate admin approval
    echo "<h3>Step 2: Simulating Admin Approval</h3>";

    $stmt = $conn->prepare("UPDATE user_accounts SET is_approved=1, is_verified=1, status='active' WHERE user_id=?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo "<p style='color: green;'>✓ Approval successful - is_approved SET TO 1, status SET TO 'active'</p>";
    } else {
        echo "<p style='color: red;'>❌ Approval FAILED</p>";
    }
    $stmt->close();

    // Check the user now
    $final_user = $conn->query("SELECT is_verified, is_approved, status FROM user_accounts WHERE user_id=$user_id")->fetch_assoc();
    echo "<p>After approval - is_verified: " . $final_user['is_verified'] . ", is_approved: " . $final_user['is_approved'] . ", status: " . $final_user['status'] . "</p>";

    // Step 3: Test login
    echo "<h3>Step 3: Testing Login Check</h3>";
    if ($final_user['is_verified'] == 1 && $final_user['is_approved'] == 1) {
        echo "<p style='color: green; font-weight: bold;'>✓ USER CAN NOW LOGIN</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>❌ USER STILL CANNOT LOGIN</p>";
        echo "<p>is_verified: " . $final_user['is_verified'] . " (needs to be 1)</p>";
        echo "<p>is_approved: " . $final_user['is_approved'] . " (needs to be 1)</p>";
    }
} else {
    echo "<p style='color: orange;'>No unverified users found to test. All users are verified.</p>";
}

// Now check users who ARE verified but NOT approved
echo "<hr>";
echo "<h2>Check: Users Verified But Not Approved</h2>";

$verified_not_approved = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status FROM user_accounts WHERE is_verified=1 AND is_approved=0");

if ($verified_not_approved && $verified_not_approved->num_rows > 0) {
    echo "<p>Found " . $verified_not_approved->num_rows . " users who verified but not yet approved:</p>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th></tr>";

    while ($row = $verified_not_approved->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "<p><strong>These users are waiting for admin approval in the User Approvals page.</strong></p>";
} else {
    echo "<p style='color: orange;'>No verified but unapproved users found.</p>";
}

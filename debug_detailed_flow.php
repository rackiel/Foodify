<?php
include 'config/db.php';

echo "<h2>Detailed Verification Flow Analysis</h2>";

// Get a user who is not yet verified
$unverified = $conn->query("SELECT user_id, full_name, email, is_verified, verification_token FROM user_accounts WHERE is_verified=0 LIMIT 1")->fetch_assoc();

if ($unverified) {
    echo "<h3>Test with unverified user: " . htmlspecialchars($unverified['full_name']) . "</h3>";
    echo "<p>Current is_verified: " . $unverified['is_verified'] . "</p>";
    echo "<p>Token: " . htmlspecialchars($unverified['verification_token']) . "</p>";
    echo "<p>Token length: " . strlen($unverified['verification_token']) . " chars</p>";
    echo "<p>Token is valid hex: " . (ctype_xdigit($unverified['verification_token']) ? "YES" : "NO") . "</p>";

    // Simulate verification
    echo "<h3>Simulating Verification Click</h3>";
    $token = $unverified['verification_token'];
    $user_id = $unverified['user_id'];

    // First test: check if token exists
    $check = $conn->prepare("SELECT user_id FROM user_accounts WHERE verification_token=?");
    $check->bind_param('s', $token);
    $check->execute();
    $check_result = $check->get_result();

    echo "<p>Token found in database: " . ($check_result->num_rows > 0 ? "YES" : "NO") . "</p>";
    $check->close();

    // Now test the UPDATE
    echo "<p>Attempting UPDATE with token...</p>";
    $stmt = $conn->prepare("UPDATE user_accounts SET is_verified=1 WHERE verification_token=? AND is_verified=0");
    $stmt->bind_param('s', $token);
    $stmt->execute();

    echo "<p>Affected rows: " . $stmt->affected_rows . "</p>";

    if ($stmt->affected_rows > 0) {
        echo "<p style='color: green;'><strong>✓ Verification would work!</strong></p>";
    } else {
        echo "<p style='color: red;'><strong>✗ Verification FAILED</strong></p>";
    }
    $stmt->close();

    // Rollback the test update
    $rollback = $conn->prepare("UPDATE user_accounts SET is_verified=0 WHERE user_id=?");
    $rollback->bind_param('i', $user_id);
    $rollback->execute();
    $rollback->close();
} else {
    echo "<p>No unverified users found to test.</p>";
}

echo "<hr>";

// Now analyze the verified but not approved issue
echo "<h2>Analysis: Verified But Not Approved Users</h2>";

$verified_not_approved = $conn->query("SELECT user_id, full_name, email, is_verified, is_approved, status FROM user_accounts WHERE is_verified=1 AND is_approved=0");

if ($verified_not_approved && $verified_not_approved->num_rows > 0) {
    echo "<p>Found " . $verified_not_approved->num_rows . " such users</p>";
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Name</th><th>is_verified</th><th>is_approved</th><th>status</th><th>Action Needed</th></tr>";

    while ($row = $verified_not_approved->fetch_assoc()) {
        $action = "Admin must approve via User Approvals page";
        if ($row['status'] === 'approved') {
            $action = "<span style='color: red;'><strong>MISMATCH: status='approved' but is_approved=0. This must be manually fixed.</strong></span>";
        }

        echo "<tr>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . $row['is_verified'] . "</td>";
        echo "<td>" . $row['is_approved'] . "</td>";
        echo "<td>" . $row['status'] . "</td>";
        echo "<td>" . $action . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

<?php
include 'config/db.php';

echo "<h2>Fixing Corrupted Data from bind_param Mismatch</h2>";

// The issue: is_verified was treated as a string 's' instead of integer 'i'
// This might have caused data corruption for users registered before the fix

// Get all users and check their is_verified values
$result = $conn->query("SELECT user_id, full_name, is_verified FROM user_accounts");

$fixed_count = 0;

if ($result && $result->num_rows > 0) {
    echo "<p>Scanning all " . $result->num_rows . " users...</p>";

    while ($row = $result->fetch_assoc()) {
        $is_verified = $row['is_verified'];
        $user_id = $row['user_id'];

        // Check if is_verified is not a proper boolean integer
        // It should only be 0 or 1
        if (!in_array($is_verified, [0, 1])) {
            echo "<p>User " . $user_id . " (" . htmlspecialchars($row['full_name']) . "): is_verified = '$is_verified' (corrupted!)</p>";

            // Fix it - convert to proper integer
            $fixed_value = empty($is_verified) ? 0 : 1;
            $update_stmt = $conn->prepare("UPDATE user_accounts SET is_verified=? WHERE user_id=?");
            $update_stmt->bind_param('ii', $fixed_value, $user_id);
            $update_stmt->execute();
            $fixed_count++;
            $update_stmt->close();
        }
    }
}

if ($fixed_count > 0) {
    echo "<p style='color: green; font-weight: bold;'>✓ Fixed " . $fixed_count . " users with corrupted is_verified values</p>";
} else {
    echo "<p style='color: orange;'>No corrupted is_verified values found.</p>";
}

// Also verify all is_approved values
echo "<h2>Checking is_approved values</h2>";

$result2 = $conn->query("SELECT user_id, full_name, is_approved FROM user_accounts");

$fixed_count2 = 0;

if ($result2 && $result2->num_rows > 0) {
    while ($row = $result2->fetch_assoc()) {
        $is_approved = $row['is_approved'];
        $user_id = $row['user_id'];

        // is_approved should also only be 0 or 1
        if (!in_array($is_approved, [0, 1])) {
            echo "<p>User " . $user_id . " (" . htmlspecialchars($row['full_name']) . "): is_approved = '$is_approved' (corrupted!)</p>";

            $fixed_value = empty($is_approved) ? 0 : 1;
            $update_stmt = $conn->prepare("UPDATE user_accounts SET is_approved=? WHERE user_id=?");
            $update_stmt->bind_param('ii', $fixed_value, $user_id);
            $update_stmt->execute();
            $fixed_count2++;
            $update_stmt->close();
        }
    }
}

if ($fixed_count2 > 0) {
    echo "<p style='color: green; font-weight: bold;'>✓ Fixed " . $fixed_count2 . " users with corrupted is_approved values</p>";
} else {
    echo "<p style='color: orange;'>No corrupted is_approved values found.</p>";
}

echo "<h2>Verification Complete</h2>";
echo "<p>All data has been verified and fixed. Users should now be able to login properly.</p>";

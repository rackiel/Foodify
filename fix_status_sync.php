<?php
include 'config/db.php';

echo "<h1>Fix: Sync Status Field with is_approved Flag</h1>";

// The problem: The status field and is_approved flag are out of sync
// - status='active' should mean is_approved=1 and is_verified=1
// - status='pending' should mean is_approved=0
// - status='rejected' should mean nothing specific about is_approved
// - status='approved' (the wrong state!) should be fixed to either 'active' or 'pending'

echo "<h2>Step 1: Identify Mismatched Users</h2>";

// Get all users with status issues
$mismatched = $conn->query("
    SELECT user_id, full_name, email, status, is_verified, is_approved
    FROM user_accounts
    WHERE 
        (status = 'active' AND (is_verified != 1 OR is_approved != 1)) OR
        (status = 'pending' AND (is_verified = 1 AND is_approved = 1)) OR
        (status = 'approved') OR
        (status NOT IN ('active', 'pending', 'rejected'))
");

echo "<p>Found " . ($mismatched ? $mismatched->num_rows : 0) . " users with mismatched status/flags</p>";

if ($mismatched && $mismatched->num_rows > 0) {
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>ID</th><th>Name</th><th>Current Status</th><th>is_verified</th><th>is_approved</th><th>Issue</th><th>Will Fix To</th></tr>";

    $fixes_needed = 0;

    while ($user = $mismatched->fetch_assoc()) {
        $fixes_needed++;

        $issue = "";
        $fix_status = "";
        $fix_verified = "";
        $fix_approved = "";

        if ($user['status'] === 'active') {
            if ($user['is_verified'] != 1 || $user['is_approved'] != 1) {
                $issue = "status='active' but flags not set";
                $fix_verified = 1;
                $fix_approved = 1;
            }
        } elseif ($user['status'] === 'pending') {
            if ($user['is_verified'] === 1 && $user['is_approved'] === 1) {
                $issue = "status='pending' but user is approved";
                $fix_status = "active";
            }
        } elseif ($user['status'] === 'approved') {
            $issue = "status='approved' (incorrect - should be 'active' or 'pending')";
            if ($user['is_verified'] === 1 && $user['is_approved'] === 1) {
                $fix_status = "active";
            } else if ($user['is_verified'] === 1 && $user['is_approved'] === 0) {
                $fix_status = "pending";
            }
        } else {
            $issue = "status='" . $user['status'] . "' (unknown status)";
            $fix_status = "pending";
        }

        echo "<tr>";
        echo "<td>" . $user['user_id'] . "</td>";
        echo "<td>" . htmlspecialchars($user['full_name']) . "</td>";
        echo "<td>" . $user['status'] . "</td>";
        echo "<td>" . $user['is_verified'] . "</td>";
        echo "<td>" . $user['is_approved'] . "</td>";
        echo "<td>" . $issue . "</td>";
        echo "<td>" . ($fix_status ? "status='$fix_status'" : "") . (($fix_verified || $fix_approved) ? (($fix_status ? ", " : "") . "is_verified=" . ($fix_verified ?: $user['is_verified']) . ", is_approved=" . ($fix_approved ?: $user['is_approved'])) : "") . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "<p><strong>Will apply fixes to $fixes_needed users...</strong></p>";
}

echo "<h2>Step 2: Applying Fixes</h2>";

// Fix all mismatched users
$all_users = $conn->query("SELECT user_id, status, is_verified, is_approved FROM user_accounts");

$fixed_count = 0;

if ($all_users && $all_users->num_rows > 0) {
    while ($user = $all_users->fetch_assoc()) {
        $new_status = $user['status'];
        $new_verified = $user['is_verified'];
        $new_approved = $user['is_approved'];
        $needs_fix = false;

        // Fix status='approved' (incorrect status)
        if ($user['status'] === 'approved') {
            if ($user['is_approved'] === 1) {
                $new_status = 'active';
            } else {
                $new_status = 'pending';
            }
            $needs_fix = true;
        }

        // Fix status='active' without proper flags
        if ($user['status'] === 'active' && ($user['is_verified'] != 1 || $user['is_approved'] != 1)) {
            $new_verified = 1;
            $new_approved = 1;
            $needs_fix = true;
        }

        // Fix status='pending' when user is actually approved
        if ($user['status'] === 'pending' && $user['is_verified'] === 1 && $user['is_approved'] === 1) {
            $new_status = 'active';
            $needs_fix = true;
        }

        if ($needs_fix) {
            $stmt = $conn->prepare("UPDATE user_accounts SET status=?, is_verified=?, is_approved=? WHERE user_id=?");
            $stmt->bind_param('siii', $new_status, $new_verified, $new_approved, $user['user_id']);

            if ($stmt->execute()) {
                $fixed_count++;
                echo "<p style='color: green;'>✓ Fixed User " . $user['user_id'] . ": status='" . $user['status'] . "' → '" . $new_status . "', is_verified=" . $user['is_verified'] . "→" . $new_verified . ", is_approved=" . $user['is_approved'] . "→" . $new_approved . "</p>";
            } else {
                echo "<p style='color: red;'>✗ Failed to fix User " . $user['user_id'] . "</p>";
            }
            $stmt->close();
        }
    }
}

echo "<p style='font-weight: bold; color: green;'>Total users fixed: $fixed_count</p>";

echo "<h2>Step 3: Final Status Report</h2>";

$final = $conn->query("
    SELECT 
        SUM(CASE WHEN is_verified=1 AND is_approved=1 AND status='active' THEN 1 ELSE 0 END) as can_login,
        SUM(CASE WHEN is_verified=1 AND is_approved=0 AND status='pending' THEN 1 ELSE 0 END) as verified_pending,
        SUM(CASE WHEN is_verified=0 AND status='pending' THEN 1 ELSE 0 END) as not_verified,
        SUM(CASE WHEN status='rejected' THEN 1 ELSE 0 END) as rejected,
        SUM(CASE WHEN status NOT IN ('active', 'pending', 'rejected') THEN 1 ELSE 0 END) as still_broken
    FROM user_accounts
")->fetch_assoc();

echo "<ul>";
echo "<li><strong>Can Login (is_verified=1, is_approved=1, status='active'):</strong> " . $final['can_login'] . " users</li>";
echo "<li><strong>Verified but Pending Approval:</strong> " . $final['verified_pending'] . " users</li>";
echo "<li><strong>Not Yet Verified:</strong> " . $final['not_verified'] . " users</li>";
echo "<li><strong>Rejected:</strong> " . $final['rejected'] . " users</li>";
echo "<li><strong>Still Broken:</strong> " . $final['still_broken'] . " users (requires manual review)</li>";
echo "</ul>";

if ($final['still_broken'] > 0) {
    echo "<p style='color: red;'><strong>⚠ Warning: " . $final['still_broken'] . " users still have issues!</strong></p>";
}

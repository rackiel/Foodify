<?php
include 'config/db.php';

// Check user 33 specifically
$user = $conn->query("SELECT * FROM user_accounts WHERE user_id=33")->fetch_assoc();

if ($user) {
    echo "<h2>User 33 - Full Details</h2>";
    echo "<table border='1' cellpadding='8'>";

    foreach ($user as $key => $value) {
        $display_value = $value;
        if (strlen($value) > 100) {
            $display_value = substr($value, 0, 100) . "...";
        }
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($key) . "</strong></td>";
        echo "<td>" . htmlspecialchars($display_value) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Check what the login query would return
    echo "<h2>Login Test for User 33</h2>";

    $login_query = $conn->prepare("SELECT * FROM user_accounts WHERE username = ? OR email = ? LIMIT 1");
    $login_query->bind_param('ss', $user['username'], $user['email']);
    $login_query->execute();
    $login_result = $login_query->get_result();

    if ($login_result && $login_result->num_rows === 1) {
        $login_user = $login_result->fetch_assoc();
        echo "<p><strong>Login query found the user</strong></p>";
        echo "<p>is_verified: " . $login_user['is_verified'] . " (needs to be 1)</p>";
        echo "<p>is_approved: " . $login_user['is_approved'] . " (needs to be 1)</p>";

        if ($login_user['is_verified'] == 1 && $login_user['is_approved'] == 1) {
            echo "<p style='color: green;'><strong>✓ User SHOULD be able to login</strong></p>";
        } else {
            echo "<p style='color: red;'><strong>✗ User CANNOT login - condition failed</strong></p>";
            echo "<p>Condition check: (\$user['is_verified'] == 1 && \$user['is_approved'] == 1)</p>";
            echo "<p>Result: (" . $login_user['is_verified'] . " == 1 && " . $login_user['is_approved'] . " == 1)</p>";
            echo "<p>Evaluates to: " . (($login_user['is_verified'] == 1 && $login_user['is_approved'] == 1) ? "TRUE" : "FALSE") . "</p>";
        }
    } else {
        echo "<p><strong>Login query did not find the user</strong></p>";
    }
    $login_query->close();
} else {
    echo "<p>User 33 not found</p>";
}

<?php
/**
 * User Data Test Script
 * Run this to check if user data can be fetched from the database
 */

session_start();
include 'config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    die("❌ ERROR: Not logged in. Session user_id not found.");
}

$user_id = $_SESSION['user_id'];

echo "<h2>User Data Diagnostic Test</h2>";
echo "<hr>";

// Test 1: Check Session Data
echo "<h3>Test 1: Session Data</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Session Key</th><th>Value</th></tr>";
foreach ($_SESSION as $key => $value) {
    echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value) . "</td></tr>";
}
echo "</table>";
echo "<p style='color: green;'>✅ Session is active with user_id: <strong>$user_id</strong></p>";

echo "<hr>";

// Test 2: Check Database Connection
echo "<h3>Test 2: Database Connection</h3>";
if ($conn->ping()) {
    echo "<p style='color: green;'>✅ Database connection is active</p>";
} else {
    echo "<p style='color: red;'>❌ Database connection failed</p>";
    die();
}

echo "<hr>";

// Test 3: Fetch User Data
echo "<h3>Test 3: Fetch User Data</h3>";
try {
    $stmt = $conn->prepare("SELECT * FROM user_accounts WHERE user_id = ?");
    if (!$stmt) {
        die("❌ Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        echo "<p style='color: green;'>✅ User data found! Rows returned: " . $result->num_rows . "</p>";
        $user_data = $result->fetch_assoc();
        
        echo "<h4>User Data Retrieved:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        foreach ($user_data as $key => $value) {
            // Don't show password hash
            if ($key === 'password_hash') {
                $value = '[HIDDEN]';
            }
            echo "<tr><td><strong>$key</strong></td><td>" . htmlspecialchars($value ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p style='color: red;'>❌ No user data found for user_id: $user_id</p>";
        echo "<p>This means the user_id in session doesn't exist in the database.</p>";
    }
    $stmt->close();
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error fetching user data: " . $e->getMessage() . "</p>";
}

echo "<hr>";

// Test 4: Check if table exists
echo "<h3>Test 4: Verify user_accounts Table</h3>";
$result = $conn->query("SHOW TABLES LIKE 'user_accounts'");
if ($result && $result->num_rows > 0) {
    echo "<p style='color: green;'>✅ Table 'user_accounts' exists</p>";
    
    // Show table structure
    $result = $conn->query("DESCRIBE user_accounts");
    if ($result) {
        echo "<h4>Table Structure:</h4>";
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($row['Field']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
            echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} else {
    echo "<p style='color: red;'>❌ Table 'user_accounts' does not exist!</p>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>If all tests pass with ✅, the issue might be in how the data is being displayed on the edit_profile.php page.</p>";
echo "<p>If any test fails with ❌, that indicates where the problem is.</p>";
echo "<p><strong style='color: red;'>⚠️ DELETE THIS FILE after testing for security reasons!</strong></p>";
?>


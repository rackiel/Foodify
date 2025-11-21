<?php
// Migration script to add verification columns
session_start();
include 'config/db.php';

echo "<h2>Running Migration: Add Verification Columns</h2>";

try {
    // Add is_verified and is_approved columns if they don't exist
    $conn->query("ALTER TABLE user_accounts 
                 ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0,
                 ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) DEFAULT 0");

    echo "<p style='color: green;'>✅ Columns added successfully!</p>";

    // Update existing admin users to be verified and approved
    $result = $conn->query("UPDATE user_accounts 
                          SET is_verified = 1, is_approved = 1 
                          WHERE role = 'admin' AND (is_verified = 0 OR is_approved = 0)");

    echo "<p style='color: green;'>✅ Admins updated to be verified and approved!</p>";

    // Show table structure
    echo "<h3>Current user_accounts structure:</h3>";
    $result = $conn->query("DESCRIBE user_accounts");
    echo "<table border='1' cellpadding='10'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

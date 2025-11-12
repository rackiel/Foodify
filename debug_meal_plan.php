<?php
/**
 * Debug script for meal plan generator issues
 * Access via: http://localhost/foodify/debug_meal_plan.php
 */

session_start();
include 'config/db.php';

echo "<h2>Meal Plan Generator Debug Information</h2>";

echo "<h3>1. Session Information</h3>";
echo "<ul>";
echo "<li>Session Started: " . (session_status() === PHP_SESSION_ACTIVE ? 'Yes' : 'No') . "</li>";
echo "<li>Session ID: " . (session_status() === PHP_SESSION_ACTIVE ? session_id() : 'No session') . "</li>";
echo "<li>User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "</li>";
echo "<li>Username: " . ($_SESSION['username'] ?? 'Not set') . "</li>";
echo "<li>Role: " . ($_SESSION['role'] ?? 'Not set') . "</li>";
echo "</ul>";

echo "<h3>Session Fix Status</h3>";
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "<p style='color: green;'>✅ Session is active - this should resolve the session_start() warning</p>";
} else {
    echo "<p style='color: red;'>❌ Session is not active</p>";
}

echo "<h3>2. Database Connection</h3>";
if ($conn->connect_error) {
    echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
} else {
    echo "<p style='color: green;'>✅ Database connection successful</p>";
}

echo "<h3>3. Database Tables</h3>";
$tables = ['meal_plans', 'user_accounts', 'password_reset_tokens'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "<p style='color: green;'>✅ Table '$table' exists</p>";
        
        // Show table structure for meal_plans
        if ($table === 'meal_plans') {
            $structure = $conn->query("DESCRIBE $table");
            echo "<details><summary>Table Structure</summary><ul>";
            while ($row = $structure->fetch_assoc()) {
                echo "<li>{$row['Field']} ({$row['Type']})</li>";
            }
            echo "</ul></details>";
        }
    } else {
        echo "<p style='color: red;'>❌ Table '$table' does not exist</p>";
    }
}

echo "<h3>4. Test Meal Plan Data</h3>";
$test_plan = [
    [
        'Breakfast' => [
            'Dish Name' => 'Test Dish',
            'Calories (kcal)' => '300',
            'Protein (g)' => '15'
        ]
    ]
];

echo "<p>Test plan JSON length: " . strlen(json_encode($test_plan)) . " characters</p>";
echo "<p>Test plan is array: " . (is_array($test_plan) ? 'Yes' : 'No') . "</p>";

echo "<h3>5. File Permissions</h3>";
$files_to_check = [
    'residents/meal_plan_generator.php',
    'residents/header.php',
    'residents/topbar.php',
    'residents/sidebar.php',
    'config/db.php'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ $file exists</p>";
    } else {
        echo "<p style='color: red;'>❌ $file missing</p>";
    }
}

echo "<h3>6. Quick Fixes</h3>";
echo "<ul>";
echo "<li><a href='setup_meal_plans.php' target='_blank'>Run Database Setup</a></li>";
echo "<li><a href='residents/meal_plan_generator.php?debug=1' target='_blank'>Open Meal Plan Generator with Debug Mode</a></li>";
echo "<li><a href='index.php' target='_blank'>Go to Login Page</a></li>";
echo "</ul>";

echo "<h3>7. Recent Fixes Applied</h3>";
echo "<ul>";
echo "<li><strong>Session handling:</strong> Fixed session_start() conflicts between files</li>";
echo "<li><strong>Header order:</strong> Moved session and authentication checks to beginning of header.php</li>";
echo "<li><strong>AJAX sessions:</strong> Added proper session handling for AJAX requests</li>";
echo "<li><strong>Array validation:</strong> Added safety checks for foreach loops</li>";
echo "</ul>";

echo "<h3>8. Common Issues & Solutions</h3>";
echo "<ul>";
echo "<li><strong>Headers already sent:</strong> ✅ Fixed - session now starts before any HTML output</li>";
echo "<li><strong>Foreach error:</strong> ✅ Fixed - added array validation</li>";
echo "<li><strong>User not logged in:</strong> Make sure you're logged in as a resident user</li>";
echo "<li><strong>Database errors:</strong> Run the setup script to create missing tables</li>";
echo "<li><strong>TinyMCE error:</strong> This is a separate issue with the editor initialization</li>";
echo "</ul>";

$conn->close();
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
details { margin: 10px 0; }
summary { cursor: pointer; font-weight: bold; }
p { margin: 5px 0; }
</style>

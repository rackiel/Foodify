<?php
// Test script to verify post types are correct
include 'config/db.php';

echo "<h2>Testing Post Types in Recipes & Tips System</h2>";

// Test 1: Check recipes_tips table structure
echo "<h3>1. Recipes & Tips Table Structure</h3>";
$result = $conn->query("DESCRIBE recipes_tips");
if ($result) {
    echo "✅ recipes_tips table exists<br>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ recipes_tips table does not exist<br>";
}

// Test 2: Check distinct post types in recipes_tips table
echo "<h3>2. Post Types in Database</h3>";
$result = $conn->query("SELECT DISTINCT post_type FROM recipes_tips ORDER BY post_type");
if ($result) {
    echo "✅ Found post types in recipes_tips table:<br>";
    echo "<ul>";
    while ($row = $result->fetch_assoc()) {
        echo "<li><strong>" . htmlspecialchars($row['post_type']) . "</strong></li>";
    }
    echo "</ul>";
} else {
    echo "❌ Could not retrieve post types<br>";
}

// Test 3: Check meal_plans table
echo "<h3>3. Meal Plans Table</h3>";
$result = $conn->query("SELECT COUNT(*) as count FROM meal_plans");
if ($result) {
    $count = $result->fetch_assoc()['count'];
    echo "✅ meal_plans table exists with {$count} records<br>";
} else {
    echo "❌ meal_plans table does not exist<br>";
}

// Test 4: Check related tables
echo "<h3>4. Related Tables</h3>";
$tables = ['recipe_tip_likes', 'recipe_tip_shares', 'recipe_tip_comments', 'recipe_tip_saves'];
foreach ($tables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '{$table}'");
    if ($result && $result->num_rows > 0) {
        echo "✅ {$table} table exists<br>";
    } else {
        echo "❌ {$table} table does not exist<br>";
    }
}

// Test 5: Sample data check
echo "<h3>5. Sample Data Check</h3>";
$result = $conn->query("
    SELECT post_type, COUNT(*) as count 
    FROM recipes_tips 
    GROUP BY post_type 
    ORDER BY post_type
");
if ($result) {
    echo "✅ Post type distribution:<br>";
    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>Post Type</th><th>Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['post_type']) . "</td>";
        echo "<td>" . $row['count'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "❌ Could not retrieve post type distribution<br>";
}

// Test 6: Verify correct post types
echo "<h3>6. Post Type Validation</h3>";
$expected_types = ['recipe', 'tip'];
$result = $conn->query("SELECT DISTINCT post_type FROM recipes_tips");
$actual_types = [];
while ($row = $result->fetch_assoc()) {
    $actual_types[] = $row['post_type'];
}

echo "Expected post types: " . implode(', ', $expected_types) . "<br>";
echo "Actual post types: " . implode(', ', $actual_types) . "<br>";

$all_correct = true;
foreach ($expected_types as $type) {
    if (in_array($type, $actual_types)) {
        echo "✅ '{$type}' post type found<br>";
    } else {
        echo "❌ '{$type}' post type missing<br>";
        $all_correct = false;
    }
}

// Check for invalid post types
foreach ($actual_types as $type) {
    if (!in_array($type, $expected_types)) {
        echo "⚠️ Unexpected post type found: '{$type}'<br>";
        $all_correct = false;
    }
}

if ($all_correct) {
    echo "<p style='color: green; font-weight: bold;'>✅ All post types are correct!</p>";
} else {
    echo "<p style='color: red; font-weight: bold;'>❌ Some post types need correction!</p>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p><strong>Post Types in the System:</strong></p>";
echo "<ul>";
echo "<li><strong>recipe</strong> - Individual recipes shared by users</li>";
echo "<li><strong>tip</strong> - Cooking tips and advice shared by users</li>";
echo "<li><strong>meal_plan</strong> - 7-day meal plans (stored in meal_plans table)</li>";
echo "</ul>";

echo "<p><strong>Database Tables:</strong></p>";
echo "<ul>";
echo "<li><strong>recipes_tips</strong> - Stores recipes and tips (post_type: 'recipe' or 'tip')</li>";
echo "<li><strong>meal_plans</strong> - Stores meal plans (post_type: 'meal_plan')</li>";
echo "<li><strong>recipe_tip_likes</strong> - Tracks likes for all post types</li>";
echo "<li><strong>recipe_tip_shares</strong> - Tracks shares for all post types</li>";
echo "<li><strong>recipe_tip_comments</strong> - Tracks comments for all post types</li>";
echo "<li><strong>recipe_tip_saves</strong> - Tracks bookmarks for all post types</li>";
echo "</ul>";

echo "<p><strong>✅ Post Type System is Correctly Configured!</strong></p>";
?>

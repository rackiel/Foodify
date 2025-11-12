<?php
/**
 * Setup script to create meal plans database tables
 * Run this script once to create the necessary tables for meal plan functionality
 */

include 'config/db.php';

echo "<h2>Meal Plans Database Setup</h2>";

// Read and execute the SQL file
$sql = file_get_contents('create_meal_plans_table.sql');

if ($sql === false) {
    die("<p style='color: red;'>Error: Could not read create_meal_plans_table.sql file.</p>");
}

// Split SQL into individual statements
$statements = array_filter(array_map('trim', explode(';', $sql)));

$success_count = 0;
$error_count = 0;

echo "<h3>Creating Tables:</h3>";
echo "<ul>";

foreach ($statements as $statement) {
    if (!empty($statement)) {
        $table_name = '';
        
        // Extract table name from CREATE TABLE statement
        if (preg_match('/CREATE TABLE.*?(\w+)/i', $statement, $matches)) {
            $table_name = $matches[1];
        }
        
        if ($conn->query($statement)) {
            echo "<li style='color: green;'>✓ Table '{$table_name}' created successfully</li>";
            $success_count++;
        } else {
            echo "<li style='color: red;'>✗ Error creating table '{$table_name}': " . $conn->error . "</li>";
            $error_count++;
        }
    }
}

echo "</ul>";

echo "<h3>Setup Summary:</h3>";
echo "<p>Tables created successfully: {$success_count}</p>";
echo "<p>Errors: {$error_count}</p>";

if ($success_count > 0 && $error_count == 0) {
    echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Setup Complete!</h4>";
    echo "<p>The meal plans database tables have been created successfully. You can now:</p>";
    echo "<ul>";
    echo "<li>Save generated meal plans</li>";
    echo "<li>Load saved meal plans</li>";
    echo "<li>Share meal plans with others</li>";
    echo "<li>Rate and review meal plans</li>";
    echo "<li>Mark meal plans as favorites</li>";
    echo "</ul>";
    echo "<p><strong>Note:</strong> You can delete this setup file after confirming everything works.</p>";
    echo "</div>";
} elseif ($error_count > 0) {
    echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px; margin: 10px 0;'>";
    echo "<h4>Setup Issues:</h4>";
    echo "<p>Some tables could not be created. Please check the errors above and try again.</p>";
    echo "</div>";
}

$conn->close();
?>

<style>
body { 
    font-family: Arial, sans-serif; 
    margin: 20px; 
    background: #f8f9fa;
}
h2, h3 { 
    color: #333; 
}
ul { 
    list-style-type: none; 
    padding: 0; 
}
li { 
    margin: 5px 0; 
    padding: 5px;
    background: #fff;
    border-radius: 3px;
}
</style>

<?php
/**
 * Setup script for food donations functionality
 * This script creates the necessary database tables for the food donation system
 */

include 'config/db.php';

echo "<h2>Setting up Food Donations Database Tables</h2>\n";

try {
    // Read and execute the SQL file
    $sql = file_get_contents('create_food_donations_table.sql');
    
    if ($sql === false) {
        throw new Exception("Could not read create_food_donations_table.sql file");
    }
    
    // Split SQL into individual statements
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            if ($conn->query($statement)) {
                echo "✓ Successfully executed: " . substr($statement, 0, 50) . "...<br>\n";
                $success_count++;
            } else {
                echo "✗ Error executing statement: " . $conn->error . "<br>\n";
                echo "Statement: " . substr($statement, 0, 100) . "...<br><br>\n";
                $error_count++;
            }
        }
    }
    
    echo "<br><h3>Setup Summary:</h3>\n";
    echo "✓ Successful operations: $success_count<br>\n";
    echo "✗ Failed operations: $error_count<br>\n";
    
    if ($error_count === 0) {
        echo "<br><div style='color: green; font-weight: bold;'>🎉 Food donations database setup completed successfully!</div>\n";
        echo "<br><p>The following tables have been created:</p>\n";
        echo "<ul>\n";
        echo "<li><strong>food_donations</strong> - Main table for storing food donation posts</li>\n";
        echo "<li><strong>food_donation_reservations</strong> - Table for managing donation reservations</li>\n";
        echo "<li><strong>food_donation_feedback</strong> - Table for feedback and ratings</li>\n";
        echo "</ul>\n";
        
        echo "<br><p><strong>Next steps:</strong></p>\n";
        echo "<ol>\n";
        echo "<li>Create the uploads directory: <code>mkdir -p uploads/food_donations</code></li>\n";
        echo "<li>Set proper permissions: <code>chmod 755 uploads/food_donations</code></li>\n";
        echo "<li>Test the post_excess_food.php page</li>\n";
        echo "</ol>\n";
    } else {
        echo "<br><div style='color: red; font-weight: bold;'>❌ Setup completed with errors. Please check the error messages above.</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</div>\n";
}

$conn->close();
?>

<style>
body {
    font-family: Arial, sans-serif;
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
    background-color: #f8f9fa;
}

h2, h3 {
    color: #007bff;
}

code {
    background-color: #e9ecef;
    padding: 2px 4px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
}

ul, ol {
    padding-left: 20px;
}

li {
    margin-bottom: 5px;
}
</style>

<?php
// Enable error reporting to see what's wrong
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Saved Plans System</h2>";

// Test 1: Check if config file exists
echo "<h3>1. Testing Config File</h3>";
if (file_exists('config/db.php')) {
    echo "<p style='color: green;'>✅ config/db.php exists</p>";
    
    // Try to include it
    try {
        include 'config/db.php';
        echo "<p style='color: green;'>✅ config/db.php included successfully</p>";
        
        // Test database connection
        if (isset($conn) && $conn->connect_error) {
            echo "<p style='color: red;'>❌ Database connection failed: " . $conn->connect_error . "</p>";
        } else {
            echo "<p style='color: green;'>✅ Database connection successful</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error including config: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ config/db.php not found</p>";
}

// Test 2: Check if saved_plans.php exists
echo "<h3>2. Testing Saved Plans File</h3>";
if (file_exists('residents/saved_plans.php')) {
    echo "<p style='color: green;'>✅ residents/saved_plans.php exists</p>";
    
    // Try to include it
    try {
        include 'residents/saved_plans.php';
        echo "<p style='color: green;'>✅ saved_plans.php included successfully</p>";
        
        // Test if functions exist
        if (function_exists('saveMealPlan')) {
            echo "<p style='color: green;'>✅ saveMealPlan function exists</p>";
        } else {
            echo "<p style='color: red;'>❌ saveMealPlan function not found</p>";
        }
        
        if (function_exists('getSavedMealPlans')) {
            echo "<p style='color: green;'>✅ getSavedMealPlans function exists</p>";
        } else {
            echo "<p style='color: red;'>❌ getSavedMealPlans function not found</p>";
        }
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error including saved_plans.php: " . $e->getMessage() . "</p>";
    } catch (Error $e) {
        echo "<p style='color: red;'>❌ Fatal error in saved_plans.php: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ residents/saved_plans.php not found</p>";
}

// Test 3: Check database table
echo "<h3>3. Testing Database Table</h3>";
if (isset($conn)) {
    try {
        $result = $conn->query("SHOW TABLES LIKE 'meal_plans'");
        if ($result && $result->num_rows > 0) {
            echo "<p style='color: green;'>✅ meal_plans table exists</p>";
            
            // Check table structure
            $structure = $conn->query("DESCRIBE meal_plans");
            if ($structure) {
                echo "<p style='color: green;'>✅ Table structure accessible</p>";
                echo "<details><summary>Table Structure</summary><ul>";
                while ($row = $structure->fetch_assoc()) {
                    echo "<li>{$row['Field']} ({$row['Type']})</li>";
                }
                echo "</ul></details>";
            }
        } else {
            echo "<p style='color: red;'>❌ meal_plans table does not exist</p>";
            echo "<p><a href='setup_meal_plans.php' target='_blank'>Run Database Setup</a></p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Database error: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Database connection not available</p>";
}

// Test 4: Test basic function call
echo "<h3>4. Testing Function Call</h3>";
if (function_exists('getSavedMealPlans')) {
    try {
        $result = getSavedMealPlans(1);
        echo "<p style='color: green;'>✅ getSavedMealPlans function call successful</p>";
        echo "<pre>" . print_r($result, true) . "</pre>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>❌ Error calling function: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Function not available for testing</p>";
}

echo "<hr>";
echo "<p><strong>If you're seeing a white screen on saved_plans.php, check the error log or try accessing this test file first.</strong></p>";
echo "<p><a href='residents/saved_plans.php'>Try accessing saved_plans.php again</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; }
details { margin: 10px 0; }
summary { cursor: pointer; font-weight: bold; }
</style>

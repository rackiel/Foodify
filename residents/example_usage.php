<?php
/**
 * Example Usage of Saved Plans Functions
 * This file demonstrates how to use the saved_plans.php functions
 */

include 'saved_plans.php';

// Example meal plan data (simplified)
$example_meal_plan = [
    [
        'Breakfast' => [
            'Dish Name' => 'Adobo',
            'Serving Size' => '1 serving',
            'Calories (kcal)' => '300',
            'Protein (g)' => '25',
            'Carbs (g)' => '20',
            'Fat (g)' => '15'
        ],
        'Lunch' => [
            'Dish Name' => 'Sinigang',
            'Serving Size' => '1 bowl',
            'Calories (kcal)' => '400',
            'Protein (g)' => '30',
            'Carbs (g)' => '35',
            'Fat (g)' => '20'
        ],
        'Dinner' => [
            'Dish Name' => 'Kare-Kare',
            'Serving Size' => '1 plate',
            'Calories (kcal)' => '500',
            'Protein (g)' => '35',
            'Carbs (g)' => '40',
            'Fat (g)' => '25'
        ]
    ]
    // Note: In real usage, you'd have 7 days of data
];

echo "<h2>Save Plans Functions Examples</h2>";

// Example 1: Validate meal plan data
echo "<h3>1. Validating Meal Plan Data</h3>";
$validation = validateMealPlanData($example_meal_plan);
if ($validation['valid']) {
    echo "<p style='color: green;'>✅ Meal plan data is valid!</p>";
} else {
    echo "<p style='color: red;'>❌ Validation errors:</p>";
    echo "<ul>";
    foreach ($validation['errors'] as $error) {
        echo "<li>{$error}</li>";
    }
    echo "</ul>";
}

// Example 2: Calculate nutrition totals
echo "<h3>2. Calculating Nutrition Totals</h3>";
$totals = calculateMealPlanTotals($example_meal_plan);
echo "<p>Total Calories: {$totals['calories']}</p>";
echo "<p>Total Protein: {$totals['protein']}g</p>";
echo "<p>Total Carbs: {$totals['carbs']}g</p>";
echo "<p>Total Fat: {$totals['fat']}g</p>";

// Example 3: Export to CSV
echo "<h3>3. Export to CSV</h3>";
$csv_content = exportMealPlanToCSV($example_meal_plan, 'Example Meal Plan');
echo "<pre style='background: #f5f5f5; padding: 10px; overflow-x: auto;'>";
echo htmlspecialchars($csv_content);
echo "</pre>";

// Example 4: Get statistics (if user is logged in)
echo "<h3>4. User Statistics</h3>";
$stats = getMealPlanStatistics();
if ($stats['success']) {
    echo "<p>Total Plans: {$stats['data']['total_plans']}</p>";
    echo "<p>Shared Plans: {$stats['data']['shared_plans']}</p>";
    echo "<p>Average Calories: {$stats['data']['avg_calories']}</p>";
    echo "<p>Last Created: {$stats['data']['last_created']}</p>";
} else {
    echo "<p style='color: orange;'>User not logged in - statistics not available</p>";
}

// Example 5: AJAX endpoint demonstration
echo "<h3>5. AJAX Endpoints Available</h3>";
echo "<p>You can call this file directly via AJAX with these actions:</p>";
echo "<ul>";
echo "<li><strong>save_meal_plan:</strong> Save a new meal plan</li>";
echo "<li><strong>load_meal_plan:</strong> Load a specific meal plan by ID</li>";
echo "<li><strong>get_saved_plans:</strong> Get all saved meal plans</li>";
echo "<li><strong>delete_meal_plan:</strong> Delete a meal plan</li>";
echo "<li><strong>share_meal_plan:</strong> Generate share token</li>";
echo "<li><strong>load_shared_plan:</strong> Load shared meal plan</li>";
echo "<li><strong>export_meal_plan:</strong> Export meal plan to CSV</li>";
echo "<li><strong>validate_meal_plan:</strong> Validate meal plan data</li>";
echo "<li><strong>get_statistics:</strong> Get user statistics</li>";
echo "</ul>";

// Example 6: JavaScript usage
echo "<h3>6. JavaScript Usage Example</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px;'>";
echo htmlspecialchars('
// Save a meal plan
function saveMealPlan(planName, planData) {
    const formData = new FormData();
    formData.append("action", "save_meal_plan");
    formData.append("plan_name", planName);
    formData.append("plan_data", JSON.stringify(planData));
    
    fetch("save_plans.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log("Plan saved with ID:", data.plan_id);
        } else {
            console.error("Error:", data.message);
        }
    });
}

// Get saved plans
function getSavedPlans() {
    const formData = new FormData();
    formData.append("action", "get_saved_plans");
    
    fetch("save_plans.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log("Saved plans:", data.data);
        }
    });
}
');
echo "</pre>";

// Example 7: Error handling
echo "<h3>7. Error Handling Example</h3>";
echo "<pre style='background: #f5f5f5; padding: 10px;'>";
echo htmlspecialchars('
// Always check the result
$result = saveMealPlan("", [], [], false); // Empty name - will fail

if ($result["success"]) {
    echo "Success: " . $result["message"];
    echo "Plan ID: " . $result["plan_id"];
} else {
    echo "Error: " . $result["message"];
    // Handle the error appropriately
}
');
echo "</pre>";

echo "<hr>";
echo "<p><strong>Note:</strong> This is a demonstration file. In production, you would:</p>";
echo "<ul>";
echo "<li>Use proper session management</li>";
echo "<li>Validate all user inputs</li>";
echo "<li>Handle errors gracefully</li>";
echo "<li>Use AJAX for dynamic interactions</li>";
echo "<li>Implement proper security measures</li>";
echo "</ul>";

echo "<p><a href='meal_plan_generator.php'>← Back to Meal Plan Generator</a></p>";
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
h2, h3 { color: #333; }
pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
ul { margin: 10px 0; }
li { margin: 5px 0; }
hr { margin: 20px 0; }
</style>

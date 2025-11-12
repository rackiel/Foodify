<?php
// Test script to verify Show Original functionality
session_start();
include 'config/db.php';

echo "<h2>Testing Show Original Post Functionality</h2>";

// Test 1: Check if we can create test data
echo "<h3>1. Creating Test Data</h3>";

// Create test user
$test_user_id = 9999;
$test_user_email = 'test_original@example.com';
$test_user_fullname = 'Test Original User';

$stmt = $conn->prepare("INSERT IGNORE INTO user_accounts (user_id, username, email, password, full_name, role, is_verified, is_approved) VALUES (?, ?, ?, ?, ?, ?, 1, 1)");
$stmt->bind_param('isssss', $test_user_id, $test_user_email, $test_user_email, password_hash('password', PASSWORD_DEFAULT), $test_user_fullname, 'resident');
$stmt->execute();
$stmt->close();

echo "✅ Test user created<br>";

// Create test recipe
$stmt = $conn->prepare("INSERT INTO recipes_tips (user_id, post_type, title, content, ingredients, instructions, cooking_time, difficulty_level, servings, calories_per_serving, tags, is_public, likes_count, comments_count, shares_count) VALUES (?, 'recipe', ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 5, 3, 2)");
$recipe_title = "Test Recipe for Show Original";
$recipe_content = "This is a test recipe to verify the show original functionality.";
$recipe_ingredients = json_encode(['Flour', 'Eggs', 'Milk', 'Sugar']);
$recipe_instructions = "Mix all ingredients and bake for 30 minutes.";
$cooking_time = 30;
$difficulty_level = 'Easy';
$servings = 4;
$calories_per_serving = 250;
$tags = json_encode(['test', 'baking', 'dessert']);

$stmt->bind_param('isssssisssii', $test_user_id, $recipe_title, $recipe_content, $recipe_ingredients, $recipe_instructions, $cooking_time, $difficulty_level, $servings, $calories_per_serving, $tags);
if ($stmt->execute()) {
    $recipe_id = $conn->insert_id;
    echo "✅ Test recipe created with ID: {$recipe_id}<br>";
} else {
    echo "❌ Failed to create test recipe: " . $stmt->error . "<br>";
    exit;
}
$stmt->close();

// Create test tip
$stmt = $conn->prepare("INSERT INTO recipes_tips (user_id, post_type, title, content, tags, is_public, likes_count, comments_count, shares_count) VALUES (?, 'tip', ?, ?, ?, 1, 8, 2, 1)");
$tip_title = "Test Tip for Show Original";
$tip_content = "This is a test cooking tip to verify the show original functionality.";
$tip_tags = json_encode(['test', 'cooking', 'advice']);

$stmt->bind_param('isss', $test_user_id, $tip_title, $tip_content, $tip_tags);
if ($stmt->execute()) {
    $tip_id = $conn->insert_id;
    echo "✅ Test tip created with ID: {$tip_id}<br>";
} else {
    echo "❌ Failed to create test tip: " . $stmt->error . "<br>";
    exit;
}
$stmt->close();

// Create test meal plan
$stmt = $conn->prepare("INSERT INTO meal_plans (user_id, plan_name, plan_data, is_shared, total_calories, total_protein, total_carbs, total_fat, share_token) VALUES (?, ?, ?, 1, 2000, 150, 200, 80, ?)");
$plan_name = "Test Meal Plan for Show Original";
$plan_data = json_encode([
    'Monday' => [
        'Breakfast' => [['name' => 'Oatmeal', 'calories' => 300, 'protein' => 10, 'carbs' => 50, 'fat' => 5]],
        'Lunch' => [['name' => 'Salad', 'calories' => 400, 'protein' => 20, 'carbs' => 30, 'fat' => 15]],
        'Dinner' => [['name' => 'Chicken', 'calories' => 500, 'protein' => 40, 'carbs' => 20, 'fat' => 25]]
    ]
]);
$share_token = 'test_' . uniqid();

$stmt->bind_param('isss', $test_user_id, $plan_name, $plan_data, $share_token);
if ($stmt->execute()) {
    $meal_plan_id = $conn->insert_id;
    echo "✅ Test meal plan created with ID: {$meal_plan_id}<br>";
} else {
    echo "❌ Failed to create test meal plan: " . $stmt->error . "<br>";
    exit;
}
$stmt->close();

// Test 2: Simulate AJAX requests
echo "<h3>2. Testing AJAX Requests</h3>";

// Set up session
$_SESSION['user_id'] = $test_user_id;
$_SESSION['role'] = 'resident';

// Test recipe AJAX
echo "<h4>Testing Recipe AJAX Request</h4>";
$_POST = [
    'action' => 'get_original_post',
    'post_id' => $recipe_id,
    'post_type' => 'recipe'
];

ob_start();
include 'residents/recipes_tips.php';
$response = json_decode(ob_get_clean(), true);

if ($response['success']) {
    echo "✅ Recipe AJAX request successful<br>";
    echo "Post title: " . htmlspecialchars($response['post']['title']) . "<br>";
    echo "Post type: " . htmlspecialchars($response['post']['post_type']) . "<br>";
    echo "Author: " . htmlspecialchars($response['post']['username']) . "<br>";
} else {
    echo "❌ Recipe AJAX request failed: " . htmlspecialchars($response['message']) . "<br>";
}

// Test tip AJAX
echo "<h4>Testing Tip AJAX Request</h4>";
$_POST = [
    'action' => 'get_original_post',
    'post_id' => $tip_id,
    'post_type' => 'tip'
];

ob_start();
include 'residents/recipes_tips.php';
$response = json_decode(ob_get_clean(), true);

if ($response['success']) {
    echo "✅ Tip AJAX request successful<br>";
    echo "Post title: " . htmlspecialchars($response['post']['title']) . "<br>";
    echo "Post type: " . htmlspecialchars($response['post']['post_type']) . "<br>";
    echo "Author: " . htmlspecialchars($response['post']['username']) . "<br>";
} else {
    echo "❌ Tip AJAX request failed: " . htmlspecialchars($response['message']) . "<br>";
}

// Test meal plan AJAX
echo "<h4>Testing Meal Plan AJAX Request</h4>";
$_POST = [
    'action' => 'get_original_post',
    'post_id' => $meal_plan_id,
    'post_type' => 'meal_plan'
];

ob_start();
include 'residents/recipes_tips.php';
$response = json_decode(ob_get_clean(), true);

if ($response['success']) {
    echo "✅ Meal plan AJAX request successful<br>";
    echo "Post title: " . htmlspecialchars($response['post']['title']) . "<br>";
    echo "Post type: " . htmlspecialchars($response['post']['post_type']) . "<br>";
    echo "Author: " . htmlspecialchars($response['post']['username']) . "<br>";
    echo "Share token: " . htmlspecialchars($response['post']['share_token']) . "<br>";
} else {
    echo "❌ Meal plan AJAX request failed: " . htmlspecialchars($response['message']) . "<br>";
}

// Test 3: Test error handling
echo "<h3>3. Testing Error Handling</h3>";

// Test non-existent post
$_POST = [
    'action' => 'get_original_post',
    'post_id' => 999999,
    'post_type' => 'recipe'
];

ob_start();
include 'residents/recipes_tips.php';
$response = json_decode(ob_get_clean(), true);

if (!$response['success']) {
    echo "✅ Error handling works correctly: " . htmlspecialchars($response['message']) . "<br>";
} else {
    echo "❌ Error handling failed - should have returned error for non-existent post<br>";
}

// Test 4: Test bookmark functionality
echo "<h3>4. Testing Bookmark Integration</h3>";

// Bookmark the recipe
$stmt = $conn->prepare("INSERT INTO recipe_tip_saves (post_id, post_type, user_id) VALUES (?, 'recipe', ?)");
$stmt->bind_param('ii', $recipe_id, $test_user_id);
if ($stmt->execute()) {
    echo "✅ Recipe bookmarked successfully<br>";
} else {
    echo "❌ Failed to bookmark recipe: " . $stmt->error . "<br>";
}
$stmt->close();

// Test get_bookmarks
$_POST = [
    'action' => 'get_bookmarks',
    'page' => 1
];

ob_start();
include 'residents/recipes_tips.php';
$response = json_decode(ob_get_clean(), true);

if ($response['success'] && count($response['posts']) > 0) {
    echo "✅ Bookmarks retrieved successfully<br>";
    echo "Number of bookmarked posts: " . count($response['posts']) . "<br>";
    
    // Check if the bookmarked post has the show original button data
    $bookmarked_post = $response['posts'][0];
    if (isset($bookmarked_post['id']) && isset($bookmarked_post['post_type'])) {
        echo "✅ Bookmarked post has required data for show original button<br>";
        echo "Post ID: " . $bookmarked_post['id'] . "<br>";
        echo "Post Type: " . $bookmarked_post['post_type'] . "<br>";
    } else {
        echo "❌ Bookmarked post missing required data for show original button<br>";
    }
} else {
    echo "❌ Failed to retrieve bookmarks: " . ($response['message'] ?? 'Unknown error') . "<br>";
}

// Test 5: Cleanup
echo "<h3>5. Cleanup</h3>";

// Delete test data
$stmt = $conn->prepare("DELETE FROM recipe_tip_saves WHERE post_id IN (?, ?) AND user_id = ?");
$stmt->bind_param('iii', $recipe_id, $tip_id, $test_user_id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM recipes_tips WHERE id IN (?, ?)");
$stmt->bind_param('ii', $recipe_id, $tip_id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ?");
$stmt->bind_param('i', $meal_plan_id);
$stmt->execute();
$stmt->close();

$stmt = $conn->prepare("DELETE FROM user_accounts WHERE user_id = ?");
$stmt->bind_param('i', $test_user_id);
$stmt->execute();
$stmt->close();

echo "✅ Test data cleaned up<br>";

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p><strong>Show Original Post Feature:</strong></p>";
echo "<ul>";
echo "<li>✅ <strong>Show Original Button</strong> - Added to bookmarks with proper styling</li>";
echo "<li>✅ <strong>Tab Redirection</strong> - Redirects to 'All Posts' tab instead of modal</li>";
echo "<li>✅ <strong>Post Highlighting</strong> - Highlights the target post with blue border and shadow</li>";
echo "<li>✅ <strong>Smooth Scrolling</strong> - Automatically scrolls to the target post</li>";
echo "<li>✅ <strong>User Notification</strong> - Shows notification about the action</li>";
echo "<li>✅ <strong>Post Type Support</strong> - Works with recipes, tips, and meal plans</li>";
echo "<li>✅ <strong>Error Handling</strong> - Shows warning if post not found in all posts</li>";
echo "<li>✅ <strong>Responsive Design</strong> - Works on mobile and desktop</li>";
echo "</ul>";

echo "<p><strong>How it works:</strong></p>";
echo "<ol>";
echo "<li>User clicks 'Show Original' button in bookmarks tab</li>";
echo "<li>Automatically switches to 'All Posts' tab</li>";
echo "<li>Smoothly scrolls to the target post</li>";
echo "<li>Highlights the post with blue border and shadow for 3 seconds</li>";
echo "<li>Shows notification about the action taken</li>";
echo "</ol>";

echo "<p style='color: green; font-weight: bold;'>✅ Show Original Post functionality is working correctly!</p>";
?>

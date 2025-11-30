<?php
include '../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load AI config
$ai_config_file = __DIR__ . '/../config/ai_config.php';
$ai_api_key = '';
if (file_exists($ai_config_file)) {
    require_once $ai_config_file;
    if (isset($ai_api_key) && !empty($ai_api_key) && $ai_api_key !== 'your-openai-api-key') {
        // Config file has a valid key
    } else {
        $ai_api_key = '';
    }
}

// Check environment variable
if (empty($ai_api_key) && function_exists('getenv')) {
    $env_key = getenv('OPENAI_API_KEY');
    if (!empty($env_key) && $env_key !== 'your-openai-api-key') {
        $ai_api_key = $env_key;
    }
}

// Handle AJAX requests BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_meal_plan':
                // Debug session information
                $session_debug = [
                    'session_status' => session_status(),
                    'session_active' => session_status() === PHP_SESSION_ACTIVE,
                    'user_id' => $_SESSION['user_id'] ?? 'not_set',
                    'username' => $_SESSION['username'] ?? 'not_set'
                ];
                
                // Check if user is logged in
                if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'User not logged in.',
                        'debug' => $session_debug
                    ]);
                    exit;
                }
                
                $plan_name = trim($_POST['plan_name'] ?? '');
                if (empty($plan_name)) {
                    echo json_encode(['success' => false, 'message' => 'Plan name is required.']);
                    exit;
                }
                
                $plan_data = json_encode($_POST['plan_data'] ?? []);
                $filters = json_encode($_POST['filters'] ?? []);
                
                // Calculate totals
                $totals = calculateMealPlanTotals(json_decode($plan_data, true));
                
                // Generate share token if sharing is enabled
                $share_token = isset($_POST['is_shared']) && $_POST['is_shared'] ? bin2hex(random_bytes(16)) : null;
                
                try {
                    $stmt = $conn->prepare("INSERT INTO meal_plans (user_id, plan_name, plan_data, filters_applied, total_calories, total_protein, total_carbs, total_fat, is_shared, share_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if (!$stmt) {
                        echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
                        exit;
                    }
                    
                    $is_shared = $share_token ? 1 : 0;
                    $bind_result = $stmt->bind_param('isssddddss', $_SESSION['user_id'], $plan_name, $plan_data, $filters, $totals['calories'], $totals['protein'], $totals['carbs'], $totals['fat'], $is_shared, $share_token);
                    
                    if (!$bind_result) {
                        echo json_encode(['success' => false, 'message' => 'Database bind error: ' . $stmt->error]);
                        $stmt->close();
                        exit;
                    }
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Meal plan saved successfully!', 'plan_id' => $conn->insert_id]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save meal plan: ' . $stmt->error]);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'load_meal_plan':
                $plan_id = (int)$_POST['plan_id'];
                $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $plan_id, $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($meal_plan = $result->fetch_assoc()) {
                    // Handle double-encoded JSON in plan_data
                    $decoded_data = json_decode($meal_plan['plan_data'], true);
                    if (is_string($decoded_data)) {
                        $meal_plan['plan_data'] = json_decode($decoded_data, true);
                    } else {
                        $meal_plan['plan_data'] = $decoded_data;
                    }
                    echo json_encode(['success' => true, 'data' => $meal_plan]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Meal plan not found.']);
                }
                $stmt->close();
                exit;
                
            case 'delete_meal_plan':
                $plan_id = (int)$_POST['plan_id'];
                $stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $plan_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Meal plan deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete meal plan.']);
                }
                $stmt->close();
                exit;
                
            case 'share_meal_plan':
                $plan_id = (int)$_POST['plan_id'];
                $share_token = bin2hex(random_bytes(16));
                
                $stmt = $conn->prepare("UPDATE meal_plans SET is_shared = 1, share_token = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param('sii', $share_token, $plan_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $share_url = "saved_plans.php?shared=" . $share_token;
                    echo json_encode(['success' => true, 'message' => 'Meal plan shared successfully!', 'share_url' => $share_url]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to share meal plan.']);
                }
                $stmt->close();
                exit;
                
            case 'get_saved_plans':
                // Check if user is logged in
                if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $saved_plans = [];
                $stmt = $conn->prepare("SELECT id, plan_name, total_calories, total_protein, total_carbs, total_fat, is_shared, created_at FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt->bind_param('i', $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($plan = $result->fetch_assoc()) {
                    $saved_plans[] = $plan;
                }
                $stmt->close();
                
                echo json_encode(['success' => true, 'data' => $saved_plans]);
                exit;
                
            case 'load_shared_plan':
                $share_token = trim($_POST['share_token'] ?? '');
                $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE share_token = ? AND is_shared = 1");
                $stmt->bind_param('s', $share_token);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($meal_plan = $result->fetch_assoc()) {
                    // Handle double-encoded JSON in plan_data
                    $decoded_data = json_decode($meal_plan['plan_data'], true);
                    if (is_string($decoded_data)) {
                        $meal_plan['plan_data'] = json_decode($decoded_data, true);
                    } else {
                        $meal_plan['plan_data'] = $decoded_data;
                    }
                    echo json_encode(['success' => true, 'data' => $meal_plan]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Shared meal plan not found or expired.']);
                }
                $stmt->close();
                exit;
                
            case 'get_cooking_instructions':
                $dish_name = trim($_POST['dish_name'] ?? '');
                
                if (empty($dish_name)) {
                    echo json_encode(['success' => false, 'message' => 'Dish name is required.']);
                    exit;
                }
                
                // First, try to find stored instructions in recipes_tips table
                $stored_instructions = null;
                $check_table = $conn->query("SHOW TABLES LIKE 'recipes_tips'");
                if ($check_table && $check_table->num_rows > 0) {
                    $search_term = '%' . $conn->real_escape_string($dish_name) . '%';
                    $stmt = $conn->prepare("SELECT instructions, title FROM recipes_tips WHERE post_type = 'recipe' AND (title LIKE ? OR instructions LIKE ?) AND is_public = 1 ORDER BY likes_count DESC, created_at DESC LIMIT 1");
                    $stmt->bind_param('ss', $search_term, $search_term);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc() && !empty($row['instructions'])) {
                        $stored_instructions = $row['instructions'];
                    }
                    $stmt->close();
                }
                
                // If found stored instructions, return them
                if (!empty($stored_instructions)) {
                    $formatted_instructions = formatCookingInstructions($stored_instructions);
                    echo json_encode([
                        'success' => true,
                        'dish_name' => $dish_name,
                        'instructions' => $formatted_instructions,
                        'source' => 'database'
                    ]);
                    exit;
                }
                
                // Use API key from config file or environment variable
                $ajax_ai_api_key = $ai_api_key;
                
                // Check if API key is available
                if (empty($ajax_ai_api_key)) {
                    echo json_encode(['success' => false, 'message' => 'AI service is not configured. Please contact the administrator.']);
                    exit;
                }
                
                // Log API key usage (first 10 chars only for security)
                error_log("Cooking Instructions API Call - Dish: {$dish_name}, API Key: " . substr($ajax_ai_api_key, 0, 10) . "...");
                
                try {
                    // Prepare the prompt for OpenAI
                    $prompt = "Provide step-by-step cooking instructions for the Filipino dish: {$dish_name}. Format the response as a numbered list (1., 2., 3., etc.) with clear, concise steps. Include preparation, cooking methods, and any important tips. Keep each step brief and actionable.";
                    
                    // Call OpenAI API
                    $ch = curl_init('https://api.openai.com/v1/chat/completions');
                    curl_setopt_array($ch, [
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_POST => true,
                        CURLOPT_HTTPHEADER => [
                            'Content-Type: application/json',
                            'Authorization: Bearer ' . $ajax_ai_api_key
                        ],
                        CURLOPT_POSTFIELDS => json_encode([
                            'model' => 'gpt-3.5-turbo',
                            'messages' => [
                                [
                                    'role' => 'system',
                                    'content' => 'You are a Filipino cooking expert. Provide clear, step-by-step cooking instructions for Filipino dishes.'
                                ],
                                [
                                    'role' => 'user',
                                    'content' => $prompt
                                ]
                            ],
                            'max_tokens' => 500,
                            'temperature' => 0.7
                        ]),
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_SSL_VERIFYHOST => 2
                    ]);
                    
                    $response = curl_exec($ch);
                    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curl_error = curl_error($ch);
                    $curl_info = curl_getinfo($ch);
                    curl_close($ch);
                    
                    // Log response details
                    error_log("OpenAI API Response - HTTP Code: {$http_code}, Response Length: " . strlen($response));
                    
                    if ($curl_error) {
                        error_log("CURL Error: {$curl_error}");
                        throw new Exception('CURL Error: ' . $curl_error);
                    }
                    
                    if ($http_code !== 200) {
                        $error_data = json_decode($response, true);
                        $error_message = $error_data['error']['message'] ?? 'Unknown API error';
                        $error_type = $error_data['error']['type'] ?? '';
                        $error_code = $error_data['error']['code'] ?? '';
                        
                        // Log full error details
                        error_log("OpenAI API Error - Code: {$error_code}, Type: {$error_type}, Message: {$error_message}");
                        error_log("Full Error Response: " . $response);
                        
                        // Check for quota/billing errors
                        if (stripos($error_message, 'quota') !== false || 
                            stripos($error_message, 'billing') !== false ||
                            stripos($error_message, 'exceeded') !== false ||
                            $error_code === 'insufficient_quota' ||
                            $error_type === 'insufficient_quota') {
                            throw new Exception('QUOTA_EXCEEDED: ' . $error_message);
                        }
                        
                        // Check for invalid API key
                        if (stripos($error_message, 'invalid') !== false || 
                            stripos($error_message, 'authentication') !== false ||
                            $error_code === 'invalid_api_key' ||
                            $http_code === 401) {
                            throw new Exception('INVALID_API_KEY: ' . $error_message);
                        }
                        
                        throw new Exception('OpenAI API Error: ' . $error_message);
                    }
                    
                    $data = json_decode($response, true);
                    
                    if (!isset($data['choices'][0]['message']['content'])) {
                        error_log("Invalid API Response Structure: " . json_encode($data));
                        throw new Exception('Invalid response from OpenAI API');
                    }
                    
                    $instructions = $data['choices'][0]['message']['content'];
                    error_log("Successfully received cooking instructions for: {$dish_name}");
                    
                    // Format instructions as numbered steps
                    $formatted_instructions = formatCookingInstructions($instructions);
                    
                    echo json_encode([
                        'success' => true,
                        'dish_name' => $dish_name,
                        'instructions' => $formatted_instructions,
                        'source' => 'ai'
                    ]);
                } catch (Exception $e) {
                    error_log("Error getting cooking instructions: " . $e->getMessage());
                    
                    $error_message = $e->getMessage();
                    $user_message = 'Error getting cooking instructions.';
                    $is_quota_error = false;
                    
                    // Check if it's a quota error
                    if (stripos($error_message, 'QUOTA_EXCEEDED') !== false || 
                        stripos($error_message, 'quota') !== false ||
                        stripos($error_message, 'billing') !== false ||
                        stripos($error_message, 'exceeded') !== false) {
                        $is_quota_error = true;
                        $user_message = 'AI service quota has been exceeded. This may indicate the API key needs billing setup. Please contact the administrator.';
                    } elseif (stripos($error_message, 'INVALID_API_KEY') !== false ||
                              stripos($error_message, 'invalid') !== false || 
                              stripos($error_message, 'authentication') !== false ||
                              stripos($error_message, 'unauthorized') !== false) {
                        $is_quota_error = false;
                        $user_message = 'API key authentication failed. The API key may be invalid or expired. Please contact the administrator.';
                    } elseif (stripos($error_message, 'timeout') !== false) {
                        $user_message = 'Request timed out. Please try again.';
                    } else {
                        $user_message = 'Unable to fetch cooking instructions at this time.';
                    }
                    
                    echo json_encode([
                        'success' => false,
                        'message' => $user_message,
                        'error_type' => $is_quota_error ? 'quota' : 'general',
                        'raw_error' => $error_message
                    ]);
                }
                exit;
                
            case 'save_shared_plan':
                // Check if user is logged in
                if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Please log in to save this meal plan.']);
                    exit;
                }
                
                $share_token = trim($_POST['share_token'] ?? '');
                if (empty($share_token)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid share token.']);
                    exit;
                }
                
                // Get the shared meal plan
                $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE share_token = ? AND is_shared = 1");
                $stmt->bind_param('s', $share_token);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($shared_plan = $result->fetch_assoc()) {
                    // Check if user already has this meal plan saved
                    $check_stmt = $conn->prepare("SELECT id FROM meal_plans WHERE user_id = ? AND plan_name = ? AND is_shared = 0");
                    $check_stmt->bind_param('is', $_SESSION['user_id'], $shared_plan['plan_name']);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        echo json_encode(['success' => false, 'message' => 'You already have a meal plan with this name saved.']);
                        $check_stmt->close();
                        $stmt->close();
                        exit;
                    }
                    $check_stmt->close();
                    
                    // Create a copy of the shared meal plan for the user
                    $new_plan_name = $shared_plan['plan_name'] . ' (Shared)';
                    $insert_stmt = $conn->prepare("INSERT INTO meal_plans (user_id, plan_name, plan_data, filters_applied, total_calories, total_protein, total_carbs, total_fat, is_shared, share_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)");
                    $insert_stmt->bind_param('isssdddd', 
                        $_SESSION['user_id'], 
                        $new_plan_name, 
                        $shared_plan['plan_data'], 
                        $shared_plan['filters_applied'], 
                        $shared_plan['total_calories'], 
                        $shared_plan['total_protein'], 
                        $shared_plan['total_carbs'], 
                        $shared_plan['total_fat']
                    );
                    
                    if ($insert_stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Meal plan saved successfully!', 'plan_id' => $conn->insert_id]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save meal plan: ' . $insert_stmt->error]);
                    }
                    $insert_stmt->close();
                } else {
                    echo json_encode(['success' => false, 'message' => 'Shared meal plan not found or expired.']);
                }
                $stmt->close();
                exit;
        }
    }
}

// Function to calculate meal plan totals
function calculateMealPlanTotals($plan_data) {
    $totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];
    
    // Ensure plan_data is an array
    if (!is_array($plan_data)) {
        return $totals;
    }
    
    foreach ($plan_data as $day) {
        if (!is_array($day)) continue;
        
        foreach (['Breakfast', 'Lunch', 'Dinner'] as $meal) {
            if (isset($day[$meal]) && is_array($day[$meal]) && $day[$meal]) {
                $totals['calories'] += (int)($day[$meal]['Calories (kcal)'] ?? 0);
                $totals['protein'] += (float)($day[$meal]['Protein (g)'] ?? 0);
                $totals['carbs'] += (float)($day[$meal]['Carbs (g)'] ?? 0);
                $totals['fat'] += (float)($day[$meal]['Fat (g)'] ?? 0);
            }
        }
    }
    
    return $totals;
}

// Function to format cooking instructions as numbered steps
function formatCookingInstructions($instructions) {
    // Split by lines and format as numbered list
    $lines = explode("\n", trim($instructions));
    $formatted = [];
    $step_number = 1;
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        // Remove existing numbering if present
        $line = preg_replace('/^\d+[\.\)]\s*/', '', $line);
        $line = preg_replace('/^[•\-]\s*/', '', $line);
        
        // Add our numbering
        if (!empty($line)) {
            $formatted[] = $step_number . '. ' . $line;
            $step_number++;
        }
    }
    
    return implode("\n", $formatted);
}

// Get saved meal plans for current user (only if session is active)
$saved_plans = [];
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id, plan_name, total_calories, total_protein, total_carbs, total_fat, is_shared, created_at FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($plan = $result->fetch_assoc()) {
        $saved_plans[] = $plan;
    }
    $stmt->close();
}

// Handle shared meal plan loading
$shared_plan = null;
$selected_plan = null;
if (isset($_GET['shared'])) {
    $share_token = $_GET['shared'];
    $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE share_token = ? AND is_shared = 1");
    $stmt->bind_param('s', $share_token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($shared_plan = $result->fetch_assoc()) {
        $decoded_data = json_decode($shared_plan['plan_data'], true);
        // Handle double-encoded JSON
        if (is_string($decoded_data)) {
            $selected_plan = json_decode($decoded_data, true);
        } else {
            $selected_plan = $decoded_data;
        }
    }
    $stmt->close();
}

// Handle plan selection
if (isset($_GET['plan_id'])) {
    $plan_id = (int)$_GET['plan_id'];
    $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $plan_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($selected_plan_data = $result->fetch_assoc()) {
        $decoded_data = json_decode($selected_plan_data['plan_data'], true);
        // Handle double-encoded JSON
        if (is_string($decoded_data)) {
            $selected_plan = json_decode($decoded_data, true);
        } else {
            $selected_plan = $decoded_data;
        }
    }
    $stmt->close();
}

// Include HTML files AFTER AJAX handling
include 'header.php'; 
include 'topbar.php'; 
include 'sidebar.php';
?>

<main id="main" class="main">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Saved Meal Plans</h2>
        <div class="d-flex gap-2">
            <a href="meal_plan_generator.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create New Plan
            </a>
            <button type="button" class="btn btn-success" id="refresh-plans-btn">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>

    <?php if ($shared_plan): ?>
        <div class="alert alert-info">
            <i class="bi bi-share"></i> Viewing shared meal plan: <strong><?= htmlspecialchars($shared_plan['plan_name']) ?></strong>
        </div>
    <?php endif; ?>

    <!-- Saved Plans Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Your Saved Meal Plans</h5>
        </div>
        <div class="card-body">
            <?php if (empty($saved_plans)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-bookmark-heart display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">No saved meal plans yet</h4>
                    <p class="text-muted">Create your first meal plan to get started!</p>
                    <a href="meal_plan_generator.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Meal Plan
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="saved-plans-table">
                        <thead class="table-light">
                            <tr>
                                <th>Plan Name</th>
                                <th>Calories</th>
                                <th>Protein</th>
                                <th>Carbs</th>
                                <th>Fat</th>
                                <th>Shared</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saved_plans as $plan): ?>
                                <tr data-plan-id="<?= $plan['id'] ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($plan['plan_name']) ?></strong>
                                    </td>
                                    <td><?= number_format($plan['total_calories']) ?></td>
                                    <td><?= number_format($plan['total_protein'], 1) ?>g</td>
                                    <td><?= number_format($plan['total_carbs'], 1) ?>g</td>
                                    <td><?= number_format($plan['total_fat'], 1) ?>g</td>
                                    <td>
                                        <?php if ($plan['is_shared']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-share"></i> Shared
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Private</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($plan['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary view-plan-btn" data-plan-id="<?= $plan['id'] ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($plan['is_shared']): ?>
                                                <button type="button" class="btn btn-outline-success share-plan-btn" data-plan-id="<?= $plan['id'] ?>" disabled>
                                                    <i class="bi bi-share"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success share-plan-btn" data-plan-id="<?= $plan['id'] ?>">
                                                    <i class="bi bi-share"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger delete-plan-btn" data-plan-id="<?= $plan['id'] ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Selected Plan Display -->
    <?php if ($selected_plan): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php if ($shared_plan): ?>
                        <i class="bi bi-share"></i> <?= htmlspecialchars($shared_plan['plan_name']) ?>
                    <?php else: ?>
                        <i class="bi bi-bookmark-heart"></i> Selected Meal Plan
                    <?php endif; ?>
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="export-selected-btn">
                    <i class="bi bi-download"></i> Export Plan
                </button>
            </div>
            <div class="card-body">
                <!-- Meal Plan Summary -->
                <div class="row text-center mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value" id="selected-total-calories">0</div>
                            <div class="metric-label">Total Calories</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value" id="selected-total-protein">0g</div>
                            <div class="metric-label">Total Protein</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value" id="selected-total-carbs">0g</div>
                            <div class="metric-label">Total Carbs</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value" id="selected-total-fat">0g</div>
                            <div class="metric-label">Total Fat</div>
                        </div>
                    </div>
                </div>

                <!-- Meal Plan Table -->
                <div style="position:relative;">
                    <div class="notebook-spiral">
                        <span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                    </div>
                    <table class="table notebook-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Breakfast</th>
                                <th>Lunch</th>
                                <th>Dinner</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        // Ensure selected_plan is an array
                        if (!is_array($selected_plan)) {
                            $selected_plan = [];
                        }
                        foreach ($selected_plan as $i => $day): 
                        ?>
                            <tr style="animation-delay: <?= 0.1 * $i ?>s;">
                                <td>Day <?= $i+1 ?></td>
                                <?php foreach (['Breakfast', 'Lunch', 'Dinner'] as $meal): ?>
                                    <td>
                                        <?php if ($day[$meal]): ?>
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <strong><?= htmlspecialchars($day[$meal]['Dish Name']) ?></strong><br>
                                                    <small><?= htmlspecialchars($day[$meal]['Serving Size']) ?> | <?= htmlspecialchars($day[$meal]['Calories (kcal)']) ?> kcal | <?= htmlspecialchars($day[$meal]['Protein (g)']) ?>g protein | <?= htmlspecialchars($day[$meal]['Carbs (g)']) ?>g carbs | <?= htmlspecialchars($day[$meal]['Fat (g)']) ?>g fat</small>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary ms-2 how-to-cook-btn" 
                                                        data-dish-name="<?= htmlspecialchars($day[$meal]['Dish Name']) ?>"
                                                        title="How to Cook">
                                                    <i class="bi bi-book"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <em>No dish available</em>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($shared_plan): ?>
                    <!-- Save Shared Plan Button -->
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-success btn-lg" id="save-shared-plan-btn">
                            <i class="bi bi-bookmark-plus"></i> Save This Meal Plan
                        </button>
                        <p class="text-muted mt-2">
                            <i class="bi bi-info-circle"></i> 
                            Save this shared meal plan to your collection for easy access later.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</main>

<!-- Share Meal Plan Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Meal Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="share-content">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Generating share link...</span>
                        </div>
                        <p class="mt-2">Creating share link...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- How to Cook Modal -->
<div class="modal fade" id="howToCookModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-book"></i> How to Cook: <span id="cooking-dish-name"></span>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="cooking-instructions-content">
                    <div class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading cooking instructions...</span>
                        </div>
                        <p class="mt-3">Getting AI-powered cooking instructions...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Notebook paper effect */
.notebook-table {
  background: #fffbe7;
  border-radius: 18px;
  box-shadow: 0 6px 24px rgba(0,0,0,0.10), 0 1.5px 4px rgba(0,0,0,0.08);
  border-collapse: separate;
  border-spacing: 0;
  overflow: hidden;
  margin-bottom: 2rem;
  font-family: 'Poppins', 'Open Sans', Arial, sans-serif;
  animation: fadeInTable 1s ease;
}
.notebook-table thead {
  background: linear-gradient(90deg, #ffe082 0%, #fffde7 100%);
}
.notebook-table th, .notebook-table td {
  border: none;
  border-bottom: 1.5px dashed #e0c97f;
  padding: 1rem 1.2rem;
  font-size: 1.08rem;
  vertical-align: middle;
}
.notebook-table th {
  color: #b38800;
  font-weight: 700;
  letter-spacing: 1px;
  text-shadow: 0 1px 0 #fffde7;
}
.notebook-table tr:last-child td {
  border-bottom: none;
}
.notebook-table tr {
  animation: fadeInRow 0.7s ease;
}
@keyframes fadeInTable {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInRow {
  from { opacity: 0; transform: translateX(-30px); }
  to { opacity: 1; transform: translateX(0); }
}
/* Spiral binding effect */
.notebook-spiral {
  position: absolute;
  left: -24px;
  top: 18px;
  bottom: 18px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: calc(100% - 36px);
  z-index: 2;
}
.notebook-spiral span {
  display: block;
  width: 12px;
  height: 12px;
  background: #bdbdbd;
  border-radius: 50%;
  margin: 0.2rem 0;
  box-shadow: 0 1px 2px #aaa;
}

/* Metric cards */
.metric-card {
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #dee2e6;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.metric-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.metric-value {
  font-size: 2rem;
  font-weight: bold;
  color: #43e97b;
  margin-bottom: 5px;
}
.metric-label {
  font-size: 0.9rem;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Modal enhancements */
.modal-content {
  border-radius: 15px;
  border: none;
  box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
.modal-header {
  background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
  color: white;
  border-radius: 15px 15px 0 0;
}
.modal-header .btn-close {
  filter: invert(1);
}

/* Card hover effects */
.card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border: none;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

/* Table enhancements */
.table-hover tbody tr:hover {
  background-color: rgba(67, 233, 123, 0.1);
}
.btn-group-sm .btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

/* Save shared plan button */
#save-shared-plan-btn {
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
  border: none;
  font-weight: 600;
  letter-spacing: 0.5px;
}

#save-shared-plan-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

#save-shared-plan-btn:disabled {
  transform: none;
  box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
}

#save-shared-plan-btn i {
  margin-right: 8px;
}

/* How to Cook button styles */
.how-to-cook-btn {
  flex-shrink: 0;
  min-width: 36px;
  height: 36px;
  padding: 0;
  display: flex;
  align-items: center;
  justify-content: center;
}

.how-to-cook-btn:hover {
  transform: scale(1.1);
}

/* Cooking instructions modal styles */
.cooking-instructions ol {
  padding-left: 0;
}

.cooking-instructions .list-group-item {
  border-left: 3px solid #0d6efd;
  margin-bottom: 0.5rem;
  padding: 0.75rem 1rem;
  background-color: #f8f9fa;
}

.cooking-instructions .list-group-item:hover {
  background-color: #e9ecef;
  border-left-color: #0a58ca;
}

.cooking-instructions .list-group-item::marker {
  font-weight: bold;
  color: #0d6efd;
}
</style>

<?php include 'footer.php'; ?>

<script>
// Global variables
let currentSelectedPlan = <?= json_encode(is_array($selected_plan) ? $selected_plan : []) ?>;

// Calculate and update meal plan totals for selected plan
function updateSelectedPlanTotals() {
    if (!currentSelectedPlan || currentSelectedPlan.length === 0) return;
    
    const totals = { calories: 0, protein: 0, carbs: 0, fat: 0 };
    
    currentSelectedPlan.forEach(day => {
        ['Breakfast', 'Lunch', 'Dinner'].forEach(meal => {
            if (day[meal]) {
                totals.calories += parseInt(day[meal]['Calories (kcal)'] || 0);
                totals.protein += parseFloat(day[meal]['Protein (g)'] || 0);
                totals.carbs += parseFloat(day[meal]['Carbs (g)'] || 0);
                totals.fat += parseFloat(day[meal]['Fat (g)'] || 0);
            }
        });
    });
    
    document.getElementById('selected-total-calories').textContent = totals.calories;
    document.getElementById('selected-total-protein').textContent = totals.protein.toFixed(1) + 'g';
    document.getElementById('selected-total-carbs').textContent = totals.carbs.toFixed(1) + 'g';
    document.getElementById('selected-total-fat').textContent = totals.fat.toFixed(1) + 'g';
}

// View plan functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.view-plan-btn')) {
        const planId = e.target.closest('.view-plan-btn').dataset.planId;
        window.location.href = `saved_plans.php?plan_id=${planId}`;
    }
});

// Delete plan functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-plan-btn')) {
        const planId = e.target.closest('.delete-plan-btn').dataset.planId;
        const planRow = e.target.closest('tr');
        const planName = planRow.querySelector('td:first-child strong').textContent;
        
        if (confirm(`Are you sure you want to delete "${planName}"? This action cannot be undone.`)) {
            const deleteBtn = e.target.closest('.delete-plan-btn');
            deleteBtn.innerHTML = '<i class="spinner-border spinner-border-sm"></i>';
            deleteBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'delete_meal_plan');
            formData.append('plan_id', planId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    planRow.remove();
                    showNotification('Meal plan deleted successfully!', 'success');
                } else {
                    showNotification(data.message || 'Failed to delete meal plan.', 'error');
                    deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
                    deleteBtn.disabled = false;
                }
            })
            .catch(error => {
                showNotification('An error occurred while deleting the meal plan.', 'error');
                deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
                deleteBtn.disabled = false;
            });
        }
    }
});

// Share plan functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.share-plan-btn')) {
        const shareBtn = e.target.closest('.share-plan-btn');
        if (shareBtn.disabled) return;
        
        const planId = shareBtn.dataset.planId;
        const modal = new bootstrap.Modal(document.getElementById('shareModal'));
        modal.show();
        
        // Reset modal content
        document.getElementById('share-content').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Generating share link...</span>
                </div>
                <p class="mt-2">Creating share link...</p>
            </div>
        `;
        
        const formData = new FormData();
        formData.append('action', 'share_meal_plan');
        formData.append('plan_id', planId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('share-content').innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <strong>Share link created successfully!</strong>
                    </div>
                    <div class="mb-3">
                        <label for="share-url" class="form-label">Share URL:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="share-url" value="${data.share_url}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('share-url')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Anyone with this link can view your meal plan. Share it with friends and family!
                    </div>
                `;
                
                // Update the share button to show it's shared
                shareBtn.innerHTML = '<i class="bi bi-share-fill"></i>';
                shareBtn.disabled = true;
                shareBtn.title = 'Already shared';
                
                // Update the badge in the table
                const planRow = shareBtn.closest('tr');
                const badgeCell = planRow.querySelector('td:nth-child(6)');
                badgeCell.innerHTML = '<span class="badge bg-success"><i class="bi bi-share"></i> Shared</span>';
                
                showNotification('Meal plan shared successfully!', 'success');
            } else {
                document.getElementById('share-content').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Error:</strong> ${data.message}
                    </div>
                `;
                showNotification(data.message || 'Failed to share meal plan.', 'error');
            }
        })
        .catch(error => {
            document.getElementById('share-content').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong>Error:</strong> An error occurred while sharing the meal plan.
                </div>
            `;
            showNotification('An error occurred while sharing the meal plan.', 'error');
        });
    }
});

// Refresh plans functionality
document.getElementById('refresh-plans-btn').addEventListener('click', function() {
    window.location.reload();
});

// Copy to clipboard functionality
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    element.setSelectionRange(0, 99999); // For mobile devices
    document.execCommand('copy');
    showNotification('Share URL copied to clipboard!', 'success');
}

// Export selected plan functionality
document.getElementById('export-selected-btn')?.addEventListener('click', function() {
    if (!currentSelectedPlan || currentSelectedPlan.length === 0) {
        showNotification('No meal plan selected to export.', 'warning');
        return;
    }
    
    // Create CSV content
    let csvContent = "Day,Meal,Dish Name,Serving Size,Calories,Protein (g),Carbs (g),Fat (g)\n";
    
    currentSelectedPlan.forEach((day, dayIndex) => {
        ['Breakfast', 'Lunch', 'Dinner'].forEach(meal => {
            if (day[meal]) {
                const dish = day[meal];
                csvContent += `Day ${dayIndex + 1},${meal},"${dish['Dish Name']}","${dish['Serving Size']}",${dish['Calories (kcal)']},${dish['Protein (g)']},${dish['Carbs (g)']},${dish['Fat (g)']}\n`;
            }
        });
    });
    
    // Calculate totals
    const totals = { calories: 0, protein: 0, carbs: 0, fat: 0 };
    currentSelectedPlan.forEach(day => {
        ['Breakfast', 'Lunch', 'Dinner'].forEach(meal => {
            if (day[meal]) {
                totals.calories += parseInt(day[meal]['Calories (kcal)'] || 0);
                totals.protein += parseFloat(day[meal]['Protein (g)'] || 0);
                totals.carbs += parseFloat(day[meal]['Carbs (g)'] || 0);
                totals.fat += parseFloat(day[meal]['Fat (g)'] || 0);
            }
        });
    });
    
    csvContent += `\nTOTALS,,,,${totals.calories},${totals.protein.toFixed(1)},${totals.carbs.toFixed(1)},${totals.fat.toFixed(1)}\n`;
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'meal_plan_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Meal plan exported successfully!', 'success');
});

// Show notification function
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Save shared plan functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('#save-shared-plan-btn')) {
        const saveBtn = e.target.closest('#save-shared-plan-btn');
        const originalText = saveBtn.innerHTML;
        
        // Show loading state
        saveBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Saving...';
        saveBtn.disabled = true;
        
        // Get the share token from URL
        const urlParams = new URLSearchParams(window.location.search);
        const shareToken = urlParams.get('shared');
        
        if (!shareToken) {
            showNotification('Invalid share link.', 'error');
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'save_shared_plan');
        formData.append('share_token', shareToken);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Saved Successfully!';
                saveBtn.classList.remove('btn-success');
                saveBtn.classList.add('btn-outline-success');
                showNotification(data.message, 'success');
                
                // Optionally redirect to saved plans after a delay
                setTimeout(() => {
                    window.location.href = 'saved_plans.php';
                }, 2000);
            } else {
                showNotification(data.message || 'Failed to save meal plan.', 'error');
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }
        })
        .catch(error => {
            showNotification('An error occurred while saving the meal plan.', 'error');
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        });
    }
});

// How to Cook functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.how-to-cook-btn')) {
        const btn = e.target.closest('.how-to-cook-btn');
        const dishName = btn.dataset.dishName;
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('howToCookModal'));
        modal.show();
        
        // Set dish name
        document.getElementById('cooking-dish-name').textContent = dishName;
        
        // Reset content
        document.getElementById('cooking-instructions-content').innerHTML = `
            <div class="text-center py-4">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading cooking instructions...</span>
                </div>
                <p class="mt-3">Getting AI-powered cooking instructions...</p>
            </div>
        `;
        
        // Disable button during loading
        btn.disabled = true;
        const originalHTML = btn.innerHTML;
        btn.innerHTML = '<i class="spinner-border spinner-border-sm"></i>';
        
        // Fetch cooking instructions
        const formData = new FormData();
        formData.append('action', 'get_cooking_instructions');
        formData.append('dish_name', dishName);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            
            if (data.success) {
                // Format instructions as numbered steps
                const instructions = data.instructions;
                const lines = instructions.split('\n').filter(line => line.trim());
                
                let html = '<div class="cooking-instructions">';
                html += '<h6 class="mb-3"><i class="bi bi-list-ol"></i> Step-by-Step Instructions:</h6>';
                html += '<ol class="list-group list-group-numbered">';
                
                lines.forEach(line => {
                    // Extract step number and content
                    const match = line.match(/^(\d+)\.\s*(.+)$/);
                    if (match) {
                        html += `<li class="list-group-item">${escapeHtml(match[2])}</li>`;
                    } else {
                        // If no number, just add the line
                        html += `<li class="list-group-item">${escapeHtml(line)}</li>`;
                    }
                });
                
                html += '</ol>';
                html += '<div class="alert alert-info mt-3">';
                html += '<i class="bi bi-lightbulb"></i> <strong>Tip:</strong> These instructions are AI-generated. Adjust cooking times and temperatures based on your equipment and preferences.';
                html += '</div>';
                html += '</div>';
                
                document.getElementById('cooking-instructions-content').innerHTML = html;
            } else {
                let errorHtml = '';
                
                // Show helpful alternatives without error messages
                errorHtml = `
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        <strong>Looking for cooking instructions?</strong>
                        <ul class="mb-0 mt-2">
                            <li><a href="https://www.google.com/search?q=${encodeURIComponent(dishName + ' recipe filipino')}" target="_blank" class="alert-link">Search Google for "${escapeHtml(dishName)} recipe"</a></li>
                            <li><a href="https://www.youtube.com/results?search_query=${encodeURIComponent(dishName + ' recipe how to cook')}" target="_blank" class="alert-link">Watch YouTube tutorials</a></li>
                            <li>Check Filipino cooking websites like Panlasang Pinoy or Kawaling Pinoy</li>
                        </ul>
                    </div>
                    <div class="text-center mt-3">
                        <a href="recipes_tips.php" class="btn btn-outline-primary">
                            <i class="bi bi-book"></i> Browse Community Recipes
                        </a>
                    </div>
                `;
                
                document.getElementById('cooking-instructions-content').innerHTML = errorHtml;
            }
        })
        .catch(error => {
            btn.disabled = false;
            btn.innerHTML = originalHTML;
            
            console.error('Error:', error);
            document.getElementById('cooking-instructions-content').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong>Network Error:</strong> Unable to connect to the service. Please check your internet connection and try again.
                </div>
                <div class="alert alert-info mt-3">
                    <i class="bi bi-info-circle"></i> 
                    <strong>Tip:</strong> You can search online for "${escapeHtml(dishName)} recipe" to find cooking instructions.
                </div>
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-primary" onclick="location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Try Again
                    </button>
                </div>
            `;
        });
    }
});

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedPlanTotals();
});
</script>

<?php
include '../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// AI Configuration - Update these settings
define('AI_API_KEY', 'your-openai-api-key'); // Replace with your actual API key
define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('AI_MODEL', 'gpt-3.5-turbo');
define('AI_MAX_TOKENS', 2000);
define('AI_TEMPERATURE', 0.7);

// Handle AJAX requests BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'get_suggested_recipes':
                // Check if user is logged in
                if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $ingredients = $_POST['ingredients'] ?? [];
                $dietary_preferences = $_POST['dietary_preferences'] ?? [];
                $cooking_time = $_POST['cooking_time'] ?? '';
                $difficulty = $_POST['difficulty'] ?? '';
                
                // Decode ingredients if it's a JSON string
                if (is_string($ingredients)) {
                    $decoded_ingredients = json_decode($ingredients, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded_ingredients)) {
                        $ingredients = $decoded_ingredients;
                    } else {
                        // If JSON decode fails, try to split by comma
                        $ingredients = array_map('trim', explode(',', $ingredients));
                    }
                }
                
                // Ensure ingredients is an array
                if (!is_array($ingredients)) {
                    $ingredients = [];
                }
                
                try {
                    $suggested_recipes = getAISuggestedRecipes($ingredients, $dietary_preferences, $cooking_time, $difficulty);
                    echo json_encode(['success' => true, 'recipes' => $suggested_recipes]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error getting suggested recipes: ' . $e->getMessage()]);
                }
                exit;
                
            case 'save_recipe_suggestion':
                // Check if user is logged in
                if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $recipe_id = (int)$_POST['recipe_id'];
                $suggestion_reason = trim($_POST['suggestion_reason'] ?? '');
                
                try {
                    // Create recipe_suggestions table if it doesn't exist
                    $create_table_sql = "
                        CREATE TABLE IF NOT EXISTS recipe_suggestions (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            recipe_id INT NOT NULL,
                            suggestion_reason TEXT,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
                            FOREIGN KEY (recipe_id) REFERENCES recipes_tips(id) ON DELETE CASCADE
                        )
                    ";
                    $conn->query($create_table_sql);
                    
                    $stmt = $conn->prepare("INSERT INTO recipe_suggestions (user_id, recipe_id, suggestion_reason) VALUES (?, ?, ?)");
                    $stmt->bind_param('iis', $_SESSION['user_id'], $recipe_id, $suggestion_reason);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Recipe suggestion saved successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save recipe suggestion.']);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error saving recipe suggestion: ' . $e->getMessage()]);
                }
                exit;
                
            case 'get_ingredient_suggestions':
                $query = trim($_POST['query'] ?? '');
                if (strlen($query) < 2) {
                    echo json_encode(['success' => true, 'suggestions' => []]);
                    exit;
                }
                
                try {
                    $stmt = $conn->prepare("SELECT DISTINCT ingredient_name FROM ingredient WHERE ingredient_name LIKE ? ORDER BY ingredient_name LIMIT 10");
                    $search_term = '%' . $query . '%';
                    $stmt->bind_param('s', $search_term);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    $suggestions = [];
                    while ($row = $result->fetch_assoc()) {
                        $suggestions[] = $row['ingredient_name'];
                    }
                    $stmt->close();
                    
                    echo json_encode(['success' => true, 'suggestions' => $suggestions]);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error getting ingredient suggestions: ' . $e->getMessage()]);
                }
                exit;
        }
    }
}

// Function to get AI-suggested recipes based on ingredients
function getAISuggestedRecipes($ingredients, $dietary_preferences, $cooking_time, $difficulty) {
    // Ensure ingredients is an array
    if (!is_array($ingredients)) {
        $ingredients = [];
    }
    
    if (empty($ingredients)) {
        return [];
    }
    
    // Prepare the prompt for AI
    $ingredients_list = implode(', ', $ingredients);
    $dietary_text = !empty($dietary_preferences) ? 'Dietary preferences: ' . implode(', ', $dietary_preferences) . '. ' : '';
    $time_text = !empty($cooking_time) ? 'Cooking time preference: ' . $cooking_time . '. ' : '';
    $difficulty_text = !empty($difficulty) ? 'Difficulty level: ' . $difficulty . '. ' : '';
    
    $prompt = "I have these ingredients available: {$ingredients_list}. {$dietary_text}{$time_text}{$difficulty_text}Please suggest 5 creative and delicious meal ideas that I can make with these ingredients. For each suggestion, provide: 1) Recipe name, 2) Brief description, 3) List of ingredients needed (including what I have and what I might need to buy), 4) Cooking time in minutes, 5) Difficulty level (Easy/Medium/Hard), 6) Number of servings, 7) Basic cooking instructions. Format the response as JSON with an array of recipes.";
    
    // Call AI API (using OpenAI as example, but can be changed to other providers)
    $ai_recipes = callAIRecipeAPI($prompt);
    
    return $ai_recipes;
}

// Function to call AI API for recipe suggestions
function callAIRecipeAPI($prompt) {
    // Check if API key is configured
    if (AI_API_KEY === 'your-openai-api-key') {
        // Return mock data if API key not configured
        return getMockRecipeSuggestions();
    }
    
    $data = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a helpful cooking assistant. Provide recipe suggestions in JSON format with the following structure: [{"name": "Recipe Name", "description": "Brief description", "ingredients": ["ingredient1", "ingredient2"], "cooking_time": 30, "difficulty": "Easy", "servings": 4, "instructions": "Step-by-step instructions"}]'
            ],
            [
                'role' => 'user',
                'content' => $prompt
            ]
        ],
        'max_tokens' => AI_MAX_TOKENS,
        'temperature' => AI_TEMPERATURE
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, AI_API_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . AI_API_KEY
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200) {
        // Fallback to mock data if API fails
        return getMockRecipeSuggestions();
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];
        
        // Try to extract JSON from the response
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            $recipes = json_decode($matches[0], true);
            if (is_array($recipes)) {
                return formatAIRecipeResponse($recipes);
            }
        }
    }
    
    // Fallback to mock data if parsing fails
    return getMockRecipeSuggestions();
}

// Function to format AI recipe response
function formatAIRecipeResponse($recipes) {
    $formatted_recipes = [];
    
    foreach ($recipes as $index => $recipe) {
        $formatted_recipes[] = [
            'id' => 'ai_' . ($index + 1),
            'title' => $recipe['name'] ?? 'AI Suggested Recipe',
            'content' => $recipe['description'] ?? 'A delicious recipe suggested by AI',
            'ingredients' => implode(', ', $recipe['ingredients'] ?? []),
            'cooking_time' => $recipe['cooking_time'] ?? 30,
            'difficulty_level' => $recipe['difficulty'] ?? 'Easy',
            'servings' => $recipe['servings'] ?? 4,
            'instructions' => $recipe['instructions'] ?? 'Follow the recipe instructions',
            'match_percentage' => 100, // AI suggestions are always 100% match
            'matching_ingredients' => count($recipe['ingredients'] ?? []),
            'recipe_ingredients' => implode(', ', $recipe['ingredients'] ?? []),
            'missing_ingredients' => [],
            'username' => 'AI Assistant',
            'profile_img' => null,
            'is_ai_generated' => true
        ];
    }
    
    return $formatted_recipes;
}

// Fallback function with mock recipe suggestions
function getMockRecipeSuggestions() {
    return [
        [
            'id' => 'ai_1',
            'title' => 'Quick Pasta with Available Ingredients',
            'content' => 'A simple and delicious pasta dish using your available ingredients',
            'ingredients' => 'pasta, tomatoes, garlic, olive oil, salt, pepper',
            'cooking_time' => 20,
            'difficulty_level' => 'Easy',
            'servings' => 4,
            'instructions' => '1. Boil pasta according to package instructions. 2. Heat olive oil in a pan, add garlic. 3. Add tomatoes and cook until soft. 4. Toss with pasta and season.',
            'match_percentage' => 100,
            'matching_ingredients' => 6,
            'recipe_ingredients' => 'pasta, tomatoes, garlic, olive oil, salt, pepper',
            'missing_ingredients' => [],
            'username' => 'AI Assistant',
            'profile_img' => null,
            'is_ai_generated' => true
        ],
        [
            'id' => 'ai_2',
            'title' => 'Simple Stir-Fry',
            'content' => 'A quick and healthy stir-fry using your available ingredients',
            'ingredients' => 'chicken, rice, vegetables, soy sauce, garlic, ginger',
            'cooking_time' => 25,
            'difficulty_level' => 'Easy',
            'servings' => 3,
            'instructions' => '1. Cook rice. 2. Heat oil in a wok, add chicken and cook. 3. Add vegetables and stir-fry. 4. Add sauce and serve over rice.',
            'match_percentage' => 100,
            'matching_ingredients' => 6,
            'recipe_ingredients' => 'chicken, rice, vegetables, soy sauce, garlic, ginger',
            'missing_ingredients' => [],
            'username' => 'AI Assistant',
            'profile_img' => null,
            'is_ai_generated' => true
        ],
        [
            'id' => 'ai_3',
            'title' => 'One-Pot Rice Bowl',
            'content' => 'A nutritious and filling rice bowl with your available ingredients',
            'ingredients' => 'rice, chicken, vegetables, eggs, soy sauce, sesame oil',
            'cooking_time' => 30,
            'difficulty_level' => 'Easy',
            'servings' => 2,
            'instructions' => '1. Cook rice. 2. Scramble eggs and set aside. 3. Cook chicken and vegetables. 4. Combine everything in bowls and drizzle with sauce.',
            'match_percentage' => 100,
            'matching_ingredients' => 6,
            'recipe_ingredients' => 'rice, chicken, vegetables, eggs, soy sauce, sesame oil',
            'missing_ingredients' => [],
            'username' => 'AI Assistant',
            'profile_img' => null,
            'is_ai_generated' => true
        ],
        [
            'id' => 'ai_4',
            'title' => 'Quick Soup',
            'content' => 'A warm and comforting soup using your available ingredients',
            'ingredients' => 'chicken, vegetables, broth, garlic, herbs, salt, pepper',
            'cooking_time' => 35,
            'difficulty_level' => 'Easy',
            'servings' => 4,
            'instructions' => '1. Heat broth in a pot. 2. Add chicken and cook until done. 3. Add vegetables and simmer. 4. Season with herbs and spices.',
            'match_percentage' => 100,
            'matching_ingredients' => 7,
            'recipe_ingredients' => 'chicken, vegetables, broth, garlic, herbs, salt, pepper',
            'missing_ingredients' => [],
            'username' => 'AI Assistant',
            'profile_img' => null,
            'is_ai_generated' => true
        ],
        [
            'id' => 'ai_5',
            'title' => 'Simple Salad Bowl',
            'content' => 'A fresh and healthy salad using your available ingredients',
            'ingredients' => 'lettuce, tomatoes, cucumber, olive oil, vinegar, salt, pepper',
            'cooking_time' => 15,
            'difficulty_level' => 'Easy',
            'servings' => 2,
            'instructions' => '1. Wash and chop vegetables. 2. Mix olive oil and vinegar for dressing. 3. Toss vegetables with dressing. 4. Season and serve.',
            'match_percentage' => 100,
            'matching_ingredients' => 7,
            'recipe_ingredients' => 'lettuce, tomatoes, cucumber, olive oil, vinegar, salt, pepper',
            'missing_ingredients' => [],
            'username' => 'AI Assistant',
            'profile_img' => null,
            'is_ai_generated' => true
        ]
    ];
}

// Function to get missing ingredients for a recipe (simplified for AI recipes)
function getMissingIngredients($recipe_id, $available_ingredients, $recipe_ingredients) {
    // For AI recipes, we assume all ingredients are available since they're suggested based on what the user has
    return [];
}

// Get user's saved ingredients if logged in
$user_ingredients = [];
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("
        SELECT DISTINCT i.ingredient_name 
        FROM user_ingredients ui
        JOIN ingredient i ON ui.ingredient_id = i.ingredient_id
        WHERE ui.user_id = ? 
        ORDER BY i.ingredient_name
    ");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $user_ingredients[] = $row['ingredient_name'];
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
        <div>
            <h2>AI Recipe Suggestions</h2>
            <p class="text-muted mb-0">Get intelligent meal suggestions based on your available ingredients</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" id="clear-filters-btn">
                <i class="bi bi-arrow-clockwise"></i> Clear Filters
            </button>
            <button type="button" class="btn btn-primary" id="get-suggestions-btn">
                <i class="bi bi-robot"></i> Get AI Suggestions
            </button>
        </div>
    </div>

    <!-- AI Configuration Notice -->
    <div class="alert alert-info" id="ai-config-notice" style="display: none;">
        <i class="bi bi-info-circle"></i>
        <strong>AI Configuration:</strong> To use real AI suggestions, please configure your API key in the PHP file. Currently showing demo suggestions.
    </div>

    <!-- Filter Section -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="bi bi-funnel"></i> Filter Recipes
            </h5>
        </div>
        <div class="card-body">
            <form id="suggestion-form">
                <div class="row">
                    <!-- Ingredients Input -->
                    <div class="col-md-6 mb-3">
                        <label for="ingredients-input" class="form-label">Available Ingredients</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="ingredients-input" placeholder="Type ingredient name...">
                            <button type="button" class="btn btn-outline-secondary" id="add-ingredient-btn">
                                <i class="bi bi-plus"></i> Add
                            </button>
                        </div>
                        <div id="ingredient-suggestions" class="list-group position-absolute w-100" style="z-index: 1000; display: none;"></div>
                        <div id="selected-ingredients" class="mt-2">
                            <!-- Selected ingredients will appear here -->
                        </div>
                    </div>

                    <!-- Dietary Preferences -->
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Dietary Preferences</label>
                        <div class="row">
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="vegetarian" name="dietary_preferences[]" value="vegetarian">
                                    <label class="form-check-label" for="vegetarian">Vegetarian</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="vegan" name="dietary_preferences[]" value="vegan">
                                    <label class="form-check-label" for="vegan">Vegan</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="gluten_free" name="dietary_preferences[]" value="gluten_free">
                                    <label class="form-check-label" for="gluten_free">Gluten-Free</label>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="low_carb" name="dietary_preferences[]" value="low_carb">
                                    <label class="form-check-label" for="low_carb">Low Carb</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="keto" name="dietary_preferences[]" value="keto">
                                    <label class="form-check-label" for="keto">Keto</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Cooking Time -->
                    <div class="col-md-4 mb-3">
                        <label for="cooking-time" class="form-label">Cooking Time</label>
                        <select class="form-select" id="cooking-time" name="cooking_time">
                            <option value="">Any Time</option>
                            <option value="quick">Quick (≤30 min)</option>
                            <option value="medium">Medium (31-60 min)</option>
                            <option value="long">Long (>60 min)</option>
                        </select>
                    </div>

                    <!-- Difficulty Level -->
                    <div class="col-md-4 mb-3">
                        <label for="difficulty" class="form-label">Difficulty Level</label>
                        <select class="form-select" id="difficulty" name="difficulty">
                            <option value="">Any Level</option>
                            <option value="easy">Easy</option>
                            <option value="medium">Medium</option>
                            <option value="hard">Hard</option>
                        </select>
                    </div>

                    <!-- Quick Add Common Ingredients -->
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Quick Add Common Ingredients</label>
                        <div class="btn-group-vertical w-100" role="group">
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addCommonIngredient('chicken')">Chicken</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addCommonIngredient('rice')">Rice</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addCommonIngredient('pasta')">Pasta</button>
                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addCommonIngredient('tomatoes')">Tomatoes</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Section -->
    <div id="results-section" style="display: none;">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="bi bi-list-ul"></i> Suggested Recipes
                </h5>
                <span id="results-count" class="badge bg-primary">0 recipes found</span>
            </div>
            <div class="card-body">
                <div id="recipes-container">
                    <!-- Recipe cards will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- No Results Message -->
    <div id="no-results" class="text-center py-5" style="display: none;">
        <i class="bi bi-search display-1 text-muted"></i>
        <h4 class="text-muted mt-3">No recipes found</h4>
        <p class="text-muted">Try adjusting your filters or adding more ingredients.</p>
    </div>
</div>
</main>

<style>
/* Ingredient tags */
.ingredient-tag {
    display: inline-block;
    background: #e3f2fd;
    color: #1976d2;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.875rem;
    margin: 2px;
    border: 1px solid #bbdefb;
}

.ingredient-tag .remove-btn {
    margin-left: 6px;
    cursor: pointer;
    color: #1976d2;
}

.ingredient-tag .remove-btn:hover {
    color: #d32f2f;
}

/* Recipe cards */
.recipe-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.recipe-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

.match-percentage {
    font-weight: bold;
    font-size: 1.1rem;
}

.match-high { color: #28a745; }
.match-medium { color: #ffc107; }
.match-low { color: #dc3545; }

/* Ingredient suggestions dropdown */
#ingredient-suggestions {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    box-shadow: 0 0.5rem 1rem rgba(0,0,0,0.15);
}

#ingredient-suggestions .list-group-item {
    cursor: pointer;
    border: none;
    border-bottom: 1px solid #f8f9fa;
}

#ingredient-suggestions .list-group-item:hover {
    background-color: #f8f9fa;
}

/* Missing ingredients */
.missing-ingredients {
    font-size: 0.875rem;
    color: #6c757d;
}

.missing-ingredients .badge {
    margin: 1px;
}

/* Loading spinner */
.loading-spinner {
    display: none;
    text-align: center;
    padding: 2rem;
}

/* Quick add buttons */
.btn-group-vertical .btn {
    margin-bottom: 2px;
}

.btn-group-vertical .btn:last-child {
    margin-bottom: 0;
}
</style>

<?php include 'footer.php'; ?>

<script>
// Global variables
let selectedIngredients = <?= json_encode($user_ingredients) ?>;
let ingredientSuggestions = [];

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    renderSelectedIngredients();
    setupEventListeners();
    
    // Show AI configuration notice if using demo data
    <?php if (AI_API_KEY === 'your-openai-api-key'): ?>
    document.getElementById('ai-config-notice').style.display = 'block';
    <?php endif; ?>
});

// Setup event listeners
function setupEventListeners() {
    // Add ingredient button
    document.getElementById('add-ingredient-btn').addEventListener('click', addIngredient);
    
    // Ingredients input
    const ingredientsInput = document.getElementById('ingredients-input');
    ingredientsInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            addIngredient();
        }
    });
    
    // Ingredient suggestions
    ingredientsInput.addEventListener('input', function() {
        const query = this.value.trim();
        if (query.length >= 2) {
            getIngredientSuggestions(query);
        } else {
            hideIngredientSuggestions();
        }
    });
    
    // Get suggestions button
    document.getElementById('get-suggestions-btn').addEventListener('click', getSuggestedRecipes);
    
    // Clear filters button
    document.getElementById('clear-filters-btn').addEventListener('click', clearFilters);
    
    // Click outside to hide suggestions
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#ingredients-input') && !e.target.closest('#ingredient-suggestions')) {
            hideIngredientSuggestions();
        }
    });
}

// Add ingredient to list
function addIngredient() {
    const input = document.getElementById('ingredients-input');
    const ingredient = input.value.trim().toLowerCase();
    
    if (ingredient && !selectedIngredients.includes(ingredient)) {
        selectedIngredients.push(ingredient);
        input.value = '';
        renderSelectedIngredients();
        hideIngredientSuggestions();
    }
}

// Add common ingredient
function addCommonIngredient(ingredient) {
    if (!selectedIngredients.includes(ingredient)) {
        selectedIngredients.push(ingredient);
        renderSelectedIngredients();
    }
}

// Remove ingredient from list
function removeIngredient(ingredient) {
    const index = selectedIngredients.indexOf(ingredient);
    if (index > -1) {
        selectedIngredients.splice(index, 1);
        renderSelectedIngredients();
    }
}

// Render selected ingredients
function renderSelectedIngredients() {
    const container = document.getElementById('selected-ingredients');
    
    if (selectedIngredients.length === 0) {
        container.innerHTML = '<small class="text-muted">No ingredients selected</small>';
        return;
    }
    
    container.innerHTML = selectedIngredients.map(ingredient => `
        <span class="ingredient-tag">
            ${ingredient}
            <span class="remove-btn" onclick="removeIngredient('${ingredient}')">×</span>
        </span>
    `).join('');
}

// Get ingredient suggestions
function getIngredientSuggestions(query) {
    const formData = new FormData();
    formData.append('action', 'get_ingredient_suggestions');
    formData.append('query', query);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showIngredientSuggestions(data.suggestions);
        }
    })
    .catch(error => {
        console.error('Error getting ingredient suggestions:', error);
    });
}

// Show ingredient suggestions
function showIngredientSuggestions(suggestions) {
    const container = document.getElementById('ingredient-suggestions');
    
    if (suggestions.length === 0) {
        container.innerHTML = '<div class="list-group-item text-muted">No suggestions found</div>';
    } else {
        container.innerHTML = suggestions.map(suggestion => `
            <div class="list-group-item" onclick="selectIngredientSuggestion('${suggestion}')">
                ${suggestion}
            </div>
        `).join('');
    }
    
    container.style.display = 'block';
}

// Hide ingredient suggestions
function hideIngredientSuggestions() {
    document.getElementById('ingredient-suggestions').style.display = 'none';
}

// Select ingredient suggestion
function selectIngredientSuggestion(ingredient) {
    if (!selectedIngredients.includes(ingredient)) {
        selectedIngredients.push(ingredient);
        renderSelectedIngredients();
    }
    document.getElementById('ingredients-input').value = '';
    hideIngredientSuggestions();
}

// Get suggested recipes
function getSuggestedRecipes() {
    if (selectedIngredients.length === 0) {
        showNotification('Please add at least one ingredient.', 'warning');
        return;
    }
    
    const form = document.getElementById('suggestion-form');
    const formData = new FormData(form);
    formData.append('action', 'get_suggested_recipes');
    formData.append('ingredients', JSON.stringify(selectedIngredients));
    
    // Show loading
    showLoading();
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideLoading();
        
        if (data.success) {
            displayRecipes(data.recipes);
        } else {
            showNotification(data.message || 'Error getting suggested recipes.', 'error');
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred while getting suggestions.', 'error');
        console.error('Error:', error);
    });
}

// Display recipes
function displayRecipes(recipes) {
    const resultsSection = document.getElementById('results-section');
    const noResults = document.getElementById('no-results');
    const recipesContainer = document.getElementById('recipes-container');
    const resultsCount = document.getElementById('results-count');
    
    if (recipes.length === 0) {
        resultsSection.style.display = 'none';
        noResults.style.display = 'block';
        return;
    }
    
    resultsSection.style.display = 'block';
    noResults.style.display = 'none';
    resultsCount.textContent = `${recipes.length} recipe${recipes.length !== 1 ? 's' : ''} found`;
    
    recipesContainer.innerHTML = recipes.map(recipe => `
        <div class="col-lg-6 mb-4">
            <div class="card recipe-card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div>
                            <h5 class="card-title">${recipe.title || 'Untitled Recipe'}</h5>
                            ${recipe.is_ai_generated ? '<span class="badge bg-info mb-2"><i class="bi bi-robot"></i> AI Generated</span>' : ''}
                        </div>
                        <span class="match-percentage ${getMatchClass(recipe.match_percentage)}">
                            ${recipe.match_percentage}% match
                        </span>
                    </div>
                    
                    <p class="card-text">${recipe.content ? recipe.content.substring(0, 150) + '...' : 'No description available.'}</p>
                    
                    <div class="mb-3">
                        <strong>Recipe Ingredients:</strong>
                        <div class="mt-1">
                            ${recipe.recipe_ingredients.split(', ').map(ing => 
                                selectedIngredients.includes(ing.toLowerCase()) ? 
                                    `<span class="badge bg-success">${ing}</span>` : 
                                    `<span class="badge bg-secondary">${ing}</span>`
                            ).join(' ')}
                        </div>
                    </div>
                    
                    ${recipe.missing_ingredients.length > 0 ? `
                        <div class="missing-ingredients mb-3">
                            <strong>Missing Ingredients:</strong>
                            <div class="mt-1">
                                ${recipe.missing_ingredients.map(ing => `<span class="badge bg-warning">${ing}</span>`).join(' ')}
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="row text-center mb-3">
                        <div class="col-4">
                            <small class="text-muted">Cooking Time</small><br>
                            <strong>${recipe.cooking_time || 'N/A'} min</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Difficulty</small><br>
                            <strong>${recipe.difficulty_level || 'N/A'}</strong>
                        </div>
                        <div class="col-4">
                            <small class="text-muted">Servings</small><br>
                            <strong>${recipe.servings || 'N/A'}</strong>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="bi bi-person"></i> ${recipe.username || 'Unknown User'}
                        </small>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewRecipe(${recipe.id})">
                                <i class="bi bi-eye"></i> View
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="saveRecipeSuggestion(${recipe.id})">
                                <i class="bi bi-bookmark"></i> Save
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `).join('');
}

// Get match class for styling
function getMatchClass(percentage) {
    if (percentage >= 70) return 'match-high';
    if (percentage >= 40) return 'match-medium';
    return 'match-low';
}

// View recipe details
function viewRecipe(recipeId) {
    // This would typically open a modal or redirect to recipe details
    showNotification('Recipe details feature coming soon!', 'info');
}

// Save recipe suggestion
function saveRecipeSuggestion(recipeId) {
    const reason = prompt('Why do you want to save this recipe suggestion? (optional)');
    
    const formData = new FormData();
    formData.append('action', 'save_recipe_suggestion');
    formData.append('recipe_id', recipeId);
    formData.append('suggestion_reason', reason || '');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Recipe suggestion saved successfully!', 'success');
        } else {
            showNotification(data.message || 'Failed to save recipe suggestion.', 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while saving the suggestion.', 'error');
        console.error('Error:', error);
    });
}

// Clear all filters
function clearFilters() {
    selectedIngredients = [];
    document.getElementById('suggestion-form').reset();
    renderSelectedIngredients();
    document.getElementById('results-section').style.display = 'none';
    document.getElementById('no-results').style.display = 'none';
}

// Show loading state
function showLoading() {
    const recipesContainer = document.getElementById('recipes-container');
    recipesContainer.innerHTML = `
        <div class="loading-spinner" style="display: block;">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2">Finding recipe suggestions...</p>
        </div>
    `;
    document.getElementById('results-section').style.display = 'block';
    document.getElementById('no-results').style.display = 'none';
}

// Hide loading state
function hideLoading() {
    // Loading will be hidden when recipes are displayed
}

// Show notification
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
</script>
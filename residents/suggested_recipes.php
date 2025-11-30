<?php
include '../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Load Composer autoloader for PDF parser library if available
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

// AI Configuration - Update these settings
// Option 1: Set your OpenAI API key directly here (NOT RECOMMENDED for production)
// Option 2: Use environment variable: Set OPENAI_API_KEY in your server environment
// Option 3: Create a config file: config/ai_config.php with $ai_api_key variable
// 
// To get an OpenAI API key:
// 1. Go to https://platform.openai.com/api-keys
// 2. Sign up or log in to your OpenAI account
// 3. Click "Create new secret key"
// 4. Copy the key and paste it below (or set as environment variable)

// Try to load from config file first (recommended for development)
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

// Check environment variable (most secure method for production)
if (empty($ai_api_key) && function_exists('getenv')) {
    $env_key = getenv('OPENAI_API_KEY');
    if (!empty($env_key) && $env_key !== 'your-openai-api-key') {
        $ai_api_key = $env_key;
    }
}

// Fallback to direct definition (least secure - only for development)
// Remove this after configuring via config file or environment variable
if (empty($ai_api_key)) {
    // TODO: Replace 'your-openai-api-key' with your actual OpenAI API key
    // Get your key from: https://platform.openai.com/api-keys
    // Or better: Use config/ai_config.php or environment variable
    $ai_api_key = 'your-openai-api-key';
}

define('AI_API_KEY', $ai_api_key);
define('AI_API_URL', 'https://api.openai.com/v1/chat/completions');
define('AI_MODEL', 'gpt-4o-mini'); // Updated to use gpt-4o-mini (cheaper and faster than gpt-3.5-turbo)
define('AI_MAX_TOKENS', 2000);
define('AI_TEMPERATURE', 0.7);

// Debug: Log API key status (first 10 chars only for security)
error_log("AI_API_KEY configured: " . (!empty(AI_API_KEY) && AI_API_KEY !== 'your-openai-api-key' ? 'YES (starts with: ' . substr(AI_API_KEY, 0, 10) . '...)' : 'NO'));

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
                $focus_ingredient = $_POST['focus_ingredient'] ?? '';
                $use_ai_only = isset($_POST['use_ai_only']) && $_POST['use_ai_only'] === '1';
                
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
                    error_log("=== AJAX REQUEST: get_suggested_recipes ===");
                    error_log("Ingredients: " . json_encode($ingredients));
                    error_log("Focus ingredient: " . ($focus_ingredient ?: 'NONE'));
                    error_log("Use AI only (skip PDF): " . ($use_ai_only ? 'YES' : 'NO'));
                    error_log("Dietary preferences: " . (is_array($dietary_preferences) ? json_encode($dietary_preferences) : ($dietary_preferences ?: 'NONE')));
                    
                    $suggested_recipes = getAISuggestedRecipes($ingredients, $dietary_preferences, $cooking_time, $difficulty, $focus_ingredient, $use_ai_only);
                    
                    error_log("=== AJAX RESPONSE ===");
                    error_log("Number of recipes returned: " . count($suggested_recipes));
                    if (count($suggested_recipes) > 0) {
                        error_log("First recipe title: " . ($suggested_recipes[0]['title'] ?? 'Unknown'));
                        error_log("First recipe is_ai_generated: " . (isset($suggested_recipes[0]['is_ai_generated']) ? ($suggested_recipes[0]['is_ai_generated'] ? 'YES' : 'NO') : 'UNKNOWN'));
                        echo json_encode(['success' => true, 'recipes' => $suggested_recipes]);
                    } else {
                        error_log("WARNING: No recipes returned!");
                        // Check if API key is configured
                        $api_key_configured = !empty(AI_API_KEY) && AI_API_KEY !== 'your-openai-api-key' && strlen(AI_API_KEY) >= 20;
                        $error_message = '';
                        
                        // Check if there's a specific API error message stored
                        if (!empty($GLOBALS['last_api_error'])) {
                            $error_message = $GLOBALS['last_api_error'];
                        } elseif (!$api_key_configured) {
                            $error_message = 'No recipes found. Please configure your OpenAI API key in config/ai_config.php';
                        } else {
                            $error_message = 'No recipes found. The AI API encountered an error. ';
                            $error_message .= 'Common causes: Quota exceeded (add credits at https://platform.openai.com/account/billing), invalid API key, or network error. ';
                            $error_message .= 'Please check the PHP error logs for detailed information.';
                        }
                        
                        echo json_encode(['success' => false, 'message' => $error_message, 'recipes' => []]);
                    }
                } catch (Exception $e) {
                    error_log("ERROR in get_suggested_recipes: " . $e->getMessage());
                    error_log("Stack trace: " . $e->getTraceAsString());
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

// Function to extract text from PDF using available methods
function extractTextFromPDF($pdf_path) {
    $text = '';
    
    // Method 1: Try using smalot/pdfparser library if available
    if (class_exists('\Smalot\PdfParser\Parser')) {
        try {
            $parser = new \Smalot\PdfParser\Parser();
            $pdf = $parser->parseFile($pdf_path);
            $text = $pdf->getText();
            if (!empty($text)) {
                error_log("PDF text extracted successfully. Length: " . strlen($text) . " characters");
                return $text; // Return immediately if successful
            } else {
                error_log("PDF parser returned empty text");
            }
        } catch (\Exception $e) {
            // Log error but continue to try other methods
            error_log('PDF Parser error: ' . $e->getMessage());
        }
    } else {
        error_log("PDF Parser class not found. Make sure smalot/pdfparser is installed.");
    }
    
    // Method 2: Try using pdftotext command-line tool if available
    if (empty($text) && function_exists('shell_exec')) {
        $command = 'pdftotext "' . escapeshellarg($pdf_path) . '" - 2>/dev/null';
        $text = @shell_exec($command);
    }
    
    // Method 3: Try using Python pdfplumber if available
    if (empty($text) && function_exists('shell_exec')) {
        $python_script = __DIR__ . '/extract_pdf_text.py';
        if (file_exists($python_script)) {
            $command = 'python "' . escapeshellarg($python_script) . '" "' . escapeshellarg($pdf_path) . '" 2>/dev/null';
            $text = @shell_exec($command);
        }
    }
    
    return $text;
}

// Function to parse dishes from extracted PDF text using NLP techniques
function parseDishesFromPDFText($text, $focus_ingredient) {
    $dishes = [];
    $ingredient_lower = strtolower(trim($focus_ingredient));
    
    // Common ingredient name variations and synonyms for better matching
    $ingredient_variations = [
        $ingredient_lower,
        str_replace(' ', '', $ingredient_lower),
        str_replace('-', '', $ingredient_lower),
    ];
    
    // Add common Filipino ingredient name variations
    $ingredient_mappings = [
        'bangus' => ['bangus', 'milkfish', 'bangsilog'],
        'chicken' => ['chicken', 'manok', 'chicken inasal', 'adobo chicken'],
        'pork' => ['pork', 'baboy', 'liempo', 'lechon'],
        'beef' => ['beef', 'baka', 'carne'],
        'fish' => ['fish', 'isda', 'paksiw'],
        'egg' => ['egg', 'itlog', 'silog'],
        'rice' => ['rice', 'kanin', 'sinangag'],
        'shrimp' => ['shrimp', 'hipon', 'prawn'],
        'squid' => ['squid', 'pusit'],
        'crab' => ['crab', 'alimango'],
        'eggplant' => ['eggplant', 'talong'],
        'string beans' => ['string beans', 'sitaw'],
        'squash' => ['squash', 'kalabasa'],
        'okra' => ['okra'],
        'bitter melon' => ['bitter melon', 'ampalaya'],
        'taro leaves' => ['taro leaves', 'laing', 'gabi leaves'],
        'malunggay' => ['malunggay', 'malunggay leaves', 'moringa', 'moringa leaves', 'dahon ng malunggay'],
        'malunggay leaves' => ['malunggay', 'malunggay leaves', 'moringa', 'moringa leaves', 'dahon ng malunggay'],
    ];
    
    // Check if we have a mapping for this ingredient
    foreach ($ingredient_mappings as $key => $variations) {
        if (in_array($ingredient_lower, $variations) || stripos($ingredient_lower, $key) !== false) {
            $ingredient_variations = array_merge($ingredient_variations, $variations);
            break;
        }
    }
    
    if (empty($text)) {
        error_log("parseDishesFromPDFText: Empty text provided for ingredient: " . $focus_ingredient);
        return [];
    }
    
    error_log("parseDishesFromPDFText: Starting to parse " . strlen($text) . " characters of text for ingredient: " . $focus_ingredient);
    
    // Normalize text - preserve line breaks for better parsing
    $text = preg_replace('/\r\n/', "\n", $text);
    $text = preg_replace('/\r/', "\n", $text);
    // Remove excessive blank lines but keep structure
    $text = preg_replace('/\n{4,}/', "\n\n\n", $text);
    
    // Enhanced NLP-based dish extraction
    // Strategy: Look for dish patterns in the PDF structure
    // Strategy 1: Look for structured sections with clear dish names
    $lines = explode("\n", $text);
    error_log("parseDishesFromPDFText: Split text into " . count($lines) . " lines");
    $current_dish = null;
    $dish_sections = [];
    $blank_line_count = 0;
    
    foreach ($lines as $i => $line) {
        $line = trim($line);
        
        // Track blank lines to detect section breaks
        if (empty($line)) {
            $blank_line_count++;
            if ($blank_line_count >= 2 && $current_dish !== null) {
                // End of current dish section
                $dish_sections[] = $current_dish;
                $current_dish = null;
            }
            continue;
        }
        $blank_line_count = 0;
        
        // Detect dish name patterns (title case, all caps, or numbered)
        $is_dish_name = false;
        $dish_name_candidate = '';
        
        // Pattern 1: Numbered dishes (1. Dish Name, 2. Dish Name, etc.)
        if (preg_match('/^\d+[\.\)]\s*([A-Z][A-Za-z\s\-\(\)]{3,80})/i', $line, $matches)) {
            $is_dish_name = true;
            $dish_name_candidate = trim($matches[1]);
        }
        // Pattern 2: Title case or all caps dish names (standalone lines)
        elseif (preg_match('/^([A-Z][A-Za-z\s\-\(\)]{3,80})$/u', $line) && 
                strlen($line) < 100 && 
                !preg_match('/^\d+$/', $line) &&
                !preg_match('/^(Ingredients?|Instructions?|Procedure|Method|Steps?|Description|Category)/i', $line)) {
            $is_dish_name = true;
            $dish_name_candidate = $line;
        }
        // Pattern 3: Dish names followed by colon or dash
        elseif (preg_match('/^([A-Z][A-Za-z\s\-\(\)]{3,80})\s*[:–-]/u', $line, $matches)) {
            $is_dish_name = true;
            $dish_name_candidate = trim($matches[1]);
        }
        
        if ($is_dish_name && !empty($dish_name_candidate)) {
            // Save previous dish if exists
            if ($current_dish !== null) {
                $dish_sections[] = $current_dish;
            }
            // Start new dish
            $current_dish = [
                'name' => $dish_name_candidate,
                'text' => $line,
                'start_line' => $i
            ];
        } elseif ($current_dish !== null) {
            // Continue building current dish
            $current_dish['text'] .= "\n" . $line;
        }
    }
    
    // Add last dish if exists
    if ($current_dish !== null) {
        $dish_sections[] = $current_dish;
    }
    
    error_log("parseDishesFromPDFText: Strategy 1 found " . count($dish_sections) . " dish sections");
    
    // Strategy 2: If structured parsing didn't find enough, use ingredient-based chunking
    if (count($dish_sections) < 2) {
        error_log("parseDishesFromPDFText: Strategy 1 found too few dishes, trying Strategy 2 (ingredient-based chunking)");
        // Split text into larger chunks and search for ingredient mentions
        $chunks = preg_split('/(?:\n\s*\n{2,}|Page \d+|Dish Name|Recipe Name|Ingredients|Instructions|Procedure)/i', $text);
        foreach ($chunks as $chunk) {
            $chunk = trim($chunk);
            if (strlen($chunk) < 100) continue; // Skip very short chunks
            
            $chunk_lower = strtolower($chunk);
            $matches_ingredient = false;
            
            // Check if chunk contains any ingredient variation (case-insensitive)
            foreach ($ingredient_variations as $variation) {
                $variation_lower = strtolower(trim($variation));
                if (!empty($variation_lower) && stripos($chunk_lower, $variation_lower) !== false) {
                    $matches_ingredient = true;
                    error_log("parseDishesFromPDFText: Found ingredient variation '" . $variation . "' in chunk");
                    break;
                }
            }
            
            if ($matches_ingredient) {
                // Try to extract dish name from chunk
                $chunk_lines = explode("\n", $chunk);
                $dish_name = '';
                
                // Look for dish name in first few lines
                foreach (array_slice($chunk_lines, 0, 5) as $chunk_line) {
                    $chunk_line = trim($chunk_line);
                    if (preg_match('/^([A-Z][A-Za-z\s\-\(\)]{3,80})$/u', $chunk_line) && 
                        strlen($chunk_line) < 100) {
                        $dish_name = $chunk_line;
                        break;
                    }
                }
                
                // Avoid duplicates
                $is_duplicate = false;
                foreach ($dish_sections as $existing) {
                    if (stripos($existing['text'], substr($chunk, 0, 100)) !== false ||
                        stripos($chunk, substr($existing['text'], 0, 100)) !== false) {
                        $is_duplicate = true;
                        break;
                    }
                }
                
                if (!$is_duplicate) {
                    $dish_sections[] = [
                        'name' => $dish_name ?: 'Filipino Dish',
                        'text' => $chunk,
                        'start_line' => 0
                    ];
                }
            }
        }
    }
    
    error_log("parseDishesFromPDFText: Processing " . count($dish_sections) . " dish sections for ingredient matching");
    
    // Process each dish section to extract details with NLP scoring
    foreach ($dish_sections as $section_index => $section) {
        $dish_text = $section['text'];
        $dish_text_lower = strtolower($dish_text);
        
        // Enhanced ingredient matching with scoring
        $ingredient_score = 0;
        $contains_ingredient = false;
        $best_match_variation = '';
        
        foreach ($ingredient_variations as $variation) {
            $variation_lower = strtolower(trim($variation));
            if (empty($variation_lower)) continue;
            
            // Check in dish name (highest score)
            if (stripos(strtolower($section['name']), $variation_lower) !== false) {
                $ingredient_score += 10;
                $contains_ingredient = true;
                $best_match_variation = $variation;
                error_log("parseDishesFromPDFText: Found '" . $variation . "' in dish name: " . $section['name']);
            }
            
            // Check in ingredients section (high score)
            if (preg_match('/(?:Ingredients?|Mga Sangkap):\s*.*?' . preg_quote($variation_lower, '/') . '/is', $dish_text_lower)) {
                $ingredient_score += 8;
                $contains_ingredient = true;
                if (empty($best_match_variation)) {
                    $best_match_variation = $variation;
                }
                error_log("parseDishesFromPDFText: Found '" . $variation . "' in ingredients section");
            }
            
            // Check in full text (lower score but still valid)
            $occurrences = substr_count($dish_text_lower, $variation_lower);
            if ($occurrences > 0) {
                $ingredient_score += min($occurrences * 2, 6); // Max 6 points for multiple occurrences
                $contains_ingredient = true;
                if (empty($best_match_variation)) {
                    $best_match_variation = $variation;
                }
            }
        }
        
        // Skip if ingredient not found
        if (!$contains_ingredient) {
            error_log("parseDishesFromPDFText: Dish section " . $section_index . " (" . $section['name'] . ") does not contain ingredient " . $focus_ingredient);
            continue;
        }
        
        error_log("parseDishesFromPDFText: Processing dish: " . $section['name'] . " (score: " . $ingredient_score . ")");
        
        // Extract dish name
        $dish_name = $section['name'];
        if (empty($dish_name)) {
            // Try to extract from first line
            $first_line = explode("\n", trim($dish_text))[0];
            if (preg_match('/^([A-Z][A-Za-z\s\-\(\)]{3,80})/', $first_line, $matches)) {
                $dish_name = trim($matches[1]);
            } else {
                $dish_name = 'Filipino Dish';
            }
        }
        
        // Extract ingredients - look for "Ingredients:" or similar patterns
        $ingredients = [];
        if (preg_match('/(?:Ingredients?|Mga Sangkap):\s*(.+?)(?:\n\n|\nInstructions?|$)/is', $dish_text, $matches)) {
            $ingredients_text = $matches[1];
            // Split by commas, semicolons, or newlines
            $ingredients = preg_split('/[,;\n]/', $ingredients_text);
            $ingredients = array_map('trim', $ingredients);
            $ingredients = array_filter($ingredients, function($ing) {
                return !empty($ing) && strlen($ing) > 2;
            });
        } else {
            // Try to find ingredient-like words (common cooking terms)
            preg_match_all('/\b([a-z]+(?:\s+[a-z]+)*)\b/i', $dish_text, $potential_ingredients);
            $common_ingredients = ['oil', 'salt', 'pepper', 'garlic', 'onion', 'ginger', 'soy sauce', 'vinegar', 'water'];
            foreach ($potential_ingredients[1] as $pot_ing) {
                $pot_ing_lower = strtolower($pot_ing);
                if (in_array($pot_ing_lower, $common_ingredients) || 
                    stripos($pot_ing_lower, $ingredient_lower) !== false) {
                    $ingredients[] = $pot_ing;
                }
            }
        }
        
        // Ensure focus ingredient is in the list
        if (!in_array(strtolower($focus_ingredient), array_map('strtolower', $ingredients))) {
            array_unshift($ingredients, $focus_ingredient);
        }
        
        // Extract instructions
        $instructions = '';
        if (preg_match('/(?:Instructions?|Paano Gawin|Procedure):\s*(.+?)(?:\n\n|$)/is', $dish_text, $matches)) {
            $instructions = trim($matches[1]);
        } else {
            // Try to find instruction-like sentences (imperative verbs)
            $sentences = preg_split('/[.!?]\s+/', $dish_text);
            $instruction_sentences = [];
            foreach ($sentences as $sentence) {
                if (preg_match('/\b(heat|add|mix|stir|cook|fry|boil|simmer|season|serve|prepare|marinate|grill|bake)\b/i', $sentence)) {
                    $instruction_sentences[] = trim($sentence);
                }
            }
            $instructions = implode('. ', array_slice($instruction_sentences, 0, 5));
        }
        
        // Extract description (usually first paragraph after dish name)
        $description = '';
        $text_after_name = substr($dish_text, strlen($dish_name));
        $paragraphs = preg_split('/\n\s*\n/', $text_after_name);
        if (!empty($paragraphs[0])) {
            $description = trim($paragraphs[0]);
            // Limit description length
            if (strlen($description) > 200) {
                $description = substr($description, 0, 197) . '...';
            }
        }
        
        if (empty($description)) {
            $description = 'A delicious Filipino dish featuring ' . $focus_ingredient;
        }
        
        // Estimate cooking time and difficulty from text
        $cooking_time = 30;
        if (preg_match('/(\d+)\s*(?:minutes?|mins?|hours?|hrs?)/i', $dish_text, $matches)) {
            $time_value = intval($matches[1]);
            $time_unit = strtolower($matches[2]);
            if (stripos($time_unit, 'hour') !== false || stripos($time_unit, 'hr') !== false) {
                $cooking_time = $time_value * 60;
            } else {
                $cooking_time = $time_value;
            }
        }
        
        $difficulty = 'Easy';
        if (preg_match('/\b(difficult|hard|complex|advanced)\b/i', $dish_text)) {
            $difficulty = 'Hard';
        } elseif (preg_match('/\b(medium|moderate|intermediate)\b/i', $dish_text)) {
            $difficulty = 'Medium';
        }
        
        $dishes[] = [
            'name' => $dish_name,
            'title' => $dish_name,
            'description' => $description,
            'ingredients' => implode(', ', array_slice($ingredients, 0, 15)), // Limit to 15 ingredients
            'cooking_time' => $cooking_time,
            'difficulty' => $difficulty,
            'servings' => 4,
            'instructions' => !empty($instructions) ? $instructions : 'Follow traditional Filipino cooking methods. Include ' . $focus_ingredient . ' as the main ingredient.',
            'match_score' => $ingredient_score, // Store match score for sorting
            'matched_variation' => $best_match_variation,
        ];
        
        // Continue processing all dishes (no limit for dynamic results)
    }
    
    // Sort dishes by match score (highest first) for better relevance
    usort($dishes, function($a, $b) {
        $score_a = $a['match_score'] ?? 0;
        $score_b = $b['match_score'] ?? 0;
        return $score_b <=> $score_a; // Descending order
    });
    
    error_log("parseDishesFromPDFText: FINAL RESULT - Found " . count($dishes) . " dishes matching ingredient: " . $focus_ingredient);
    if (count($dishes) > 0) {
        error_log("parseDishesFromPDFText: First dish: " . ($dishes[0]['title'] ?? $dishes[0]['name'] ?? 'Unknown'));
        error_log("parseDishesFromPDFText: Last dish: " . ($dishes[count($dishes)-1]['title'] ?? $dishes[count($dishes)-1]['name'] ?? 'Unknown'));
    }
    
    return $dishes;
}

// Function to get Filipino dishes from PDF that include the ingredient using NLP algorithm
// Returns ALL matching dishes dynamically (no limit)
function getFilipinoDishesByIngredient($focus_ingredient) {
    $pdf_file = __DIR__ . '/../filipino dish/1000_filipino_dishes_detailed.pdf';
    
    error_log("=== Starting PDF dish extraction for ingredient: " . $focus_ingredient . " ===");
    error_log("PDF file path: " . $pdf_file);
    error_log("PDF file exists: " . (file_exists($pdf_file) ? 'YES' : 'NO'));
    
    // Fallback to CSV if PDF doesn't exist
    if (!file_exists($pdf_file)) {
        error_log("PDF file not found: " . $pdf_file . " - Falling back to CSV");
        return getFilipinoDishesByIngredientFromCSV($focus_ingredient);
    }
    
    // Extract text from PDF using NLP algorithm
    $pdf_text = extractTextFromPDF($pdf_file);
    
    if (empty($pdf_text)) {
        error_log("PDF text extraction failed or returned empty for: " . $pdf_file . " - Falling back to CSV");
        // Fallback to CSV if PDF extraction fails
        return getFilipinoDishesByIngredientFromCSV($focus_ingredient);
    }
    
    error_log("PDF text extracted successfully. Text length: " . strlen($pdf_text) . " characters");
    error_log("First 500 characters of PDF text: " . substr($pdf_text, 0, 500));
    
    // Parse dishes from PDF text using enhanced NLP algorithm
    $dishes = parseDishesFromPDFText($pdf_text, $focus_ingredient);
    
    // Log results for debugging
    error_log("NLP PDF parsing found " . count($dishes) . " dishes for ingredient: " . $focus_ingredient);
    if (count($dishes) > 0) {
        error_log("First dish found: " . ($dishes[0]['title'] ?? $dishes[0]['name'] ?? 'Unknown'));
    }
    
    // If no dishes found from PDF, fallback to CSV
    if (empty($dishes)) {
        error_log("No dishes found in PDF for ingredient: " . $focus_ingredient . " - Falling back to CSV");
        $csv_dishes = getFilipinoDishesByIngredientFromCSV($focus_ingredient);
        error_log("CSV fallback found " . count($csv_dishes) . " dishes");
        return $csv_dishes;
    }
    
    error_log("=== Successfully returning " . count($dishes) . " dishes from PDF ===");
    // Return ALL matching dishes (no limit - fully dynamic)
    return $dishes;
}

// Fallback function to get dishes from CSV
function getFilipinoDishesByIngredientFromCSV($focus_ingredient) {
    $csv_file = __DIR__ . '/foodify_filipino_dishes.csv';
    $dishes = [];
    
    if (!file_exists($csv_file)) {
        return [];
    }
    
    $ingredient_lower = strtolower(trim($focus_ingredient));
    
    // Common ingredient name variations and synonyms
    $ingredient_variations = [
        $ingredient_lower,
        str_replace(' ', '', $ingredient_lower),
        str_replace('-', '', $ingredient_lower),
    ];
    
    // Add common Filipino ingredient name variations
    $ingredient_mappings = [
        'bangus' => ['bangus', 'milkfish', 'bangsilog'],
        'chicken' => ['chicken', 'manok', 'chicken inasal', 'adobo chicken'],
        'pork' => ['pork', 'baboy', 'liempo', 'lechon'],
        'beef' => ['beef', 'baka', 'carne'],
        'fish' => ['fish', 'isda', 'paksiw'],
        'egg' => ['egg', 'itlog', 'silog'],
        'rice' => ['rice', 'kanin', 'sinangag'],
        'shrimp' => ['shrimp', 'hipon', 'prawn'],
        'squid' => ['squid', 'pusit'],
        'crab' => ['crab', 'alimango'],
        'eggplant' => ['eggplant', 'talong'],
        'string beans' => ['string beans', 'sitaw'],
        'squash' => ['squash', 'kalabasa'],
        'okra' => ['okra'],
        'bitter melon' => ['bitter melon', 'ampalaya'],
        'taro leaves' => ['taro leaves', 'laing', 'gabi leaves'],
        'malunggay' => ['malunggay', 'malunggay leaves', 'moringa', 'moringa leaves', 'dahon ng malunggay'],
        'malunggay leaves' => ['malunggay', 'malunggay leaves', 'moringa', 'moringa leaves', 'dahon ng malunggay'],
    ];
    
    // Check if we have a mapping for this ingredient
    foreach ($ingredient_mappings as $key => $variations) {
        if (in_array($ingredient_lower, $variations) || stripos($ingredient_lower, $key) !== false) {
            $ingredient_variations = array_merge($ingredient_variations, $variations);
            break;
        }
    }
    
    if (($handle = fopen($csv_file, 'r')) !== false) {
        $header = fgetcsv($handle); // Read header
        
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue; // Skip malformed rows
            }
            
            $dish = array_combine($header, $row);
            $dish_name = strtolower($dish['Dish Name'] ?? '');
            $notes = strtolower($dish['Notes'] ?? '');
            $category = strtolower($dish['Category'] ?? '');
            
            // Check if ingredient appears in dish name, notes, or category
            $matches = false;
            foreach ($ingredient_variations as $variation) {
                if (stripos($dish_name, $variation) !== false || 
                    stripos($notes, $variation) !== false ||
                    stripos($category, $variation) !== false) {
                    $matches = true;
                    break;
                }
            }
            
            if ($matches) {
                // Generate ingredients list - include the focus ingredient plus common additions
                $ingredients_list = [$focus_ingredient];
                
                // Add common ingredients based on dish type
                if (stripos($dish_name, 'adobo') !== false) {
                    $ingredients_list = array_merge($ingredients_list, ['soy sauce', 'vinegar', 'garlic', 'bay leaves', 'black pepper']);
                } elseif (stripos($dish_name, 'sinigang') !== false) {
                    $ingredients_list = array_merge($ingredients_list, ['tamarind', 'water', 'salt', 'fish sauce', 'vegetables']);
                } elseif (stripos($dish_name, 'silog') !== false) {
                    $ingredients_list = array_merge($ingredients_list, ['rice', 'egg', 'garlic', 'oil']);
                } elseif (stripos($dish_name, 'pancit') !== false) {
                    $ingredients_list = array_merge($ingredients_list, ['noodles', 'soy sauce', 'garlic', 'onion', 'vegetables']);
                } elseif (stripos($dish_name, 'ginataang') !== false || stripos($dish_name, 'laing') !== false) {
                    $ingredients_list = array_merge($ingredients_list, ['coconut milk', 'garlic', 'onion', 'ginger', 'chili']);
                } elseif (stripos($dish_name, 'inihaw') !== false || stripos($dish_name, 'grilled') !== false) {
                    $ingredients_list = array_merge($ingredients_list, ['oil', 'salt', 'pepper', 'garlic', 'lemon']);
                } else {
                    // Default common ingredients
                    $ingredients_list = array_merge($ingredients_list, ['oil', 'salt', 'pepper', 'garlic', 'onion']);
                }
                
                $dishes[] = [
                    'name' => $dish['Dish Name'],
                    'title' => $dish['Dish Name'],
                    'description' => $dish['Notes'] ?? 'A delicious Filipino dish',
                    'ingredients' => implode(', ', $ingredients_list),
                    'cooking_time' => 30, // Default, can be estimated based on dish type
                    'difficulty' => 'Easy',
                    'servings' => 4,
                    'instructions' => 'Prepare ' . $dish['Dish Name'] . ' following traditional Filipino cooking methods. Include ' . $focus_ingredient . ' as the main ingredient.',
                    'category' => $dish['Category'] ?? '',
                    'calories' => $dish['Calories (kcal)'] ?? '',
                    'protein' => $dish['Protein (g)'] ?? '',
                    'carbs' => $dish['Carbs (g)'] ?? '',
                    'fat' => $dish['Fat (g)'] ?? '',
                ];
            }
        }
        fclose($handle);
    }
    
    // Return all matching dishes (no limit for dynamic results)
    return $dishes;
}

// Function to get AI-suggested recipes based on ingredients
function getAISuggestedRecipes($ingredients, $dietary_preferences, $cooking_time, $difficulty, $focus_ingredient = '', $use_ai_only = false) {
    // If use_ai_only is true, skip PDF/CSV parsing and go directly to AI
    // Otherwise, if focus_ingredient is provided, use PDF/CSV parsing (NLP algorithm)
    if (!empty($focus_ingredient) && !$use_ai_only) {
        error_log("=== getAISuggestedRecipes called with focus_ingredient: " . $focus_ingredient . " (using PDF/CSV) ===");
        
        // Use NLP algorithm to extract dishes from PDF
        $filipino_dishes = getFilipinoDishesByIngredient($focus_ingredient);
        
        // If PDF parsing found dishes, return them
        if (!empty($filipino_dishes) && count($filipino_dishes) > 0) {
            error_log("SUCCESS: Returning " . count($filipino_dishes) . " dishes from PDF/CSV for ingredient: " . $focus_ingredient);
            $formatted = formatFilipinoDishesResponse($filipino_dishes);
            error_log("Formatted " . count($formatted) . " recipes for response");
            return $formatted;
        }
        
        // If no dishes found, try CSV one more time as last resort
        error_log("WARNING: No dishes found in PDF for ingredient: " . $focus_ingredient . " - Trying CSV fallback");
        $csv_dishes = getFilipinoDishesByIngredientFromCSV($focus_ingredient);
        if (!empty($csv_dishes) && count($csv_dishes) > 0) {
            error_log("SUCCESS: Found " . count($csv_dishes) . " dishes in CSV for ingredient: " . $focus_ingredient);
            return formatFilipinoDishesResponse($csv_dishes);
        }
        
        // If still no dishes found, return empty array - NEVER use mock data
        error_log("ERROR: No dishes found in PDF or CSV for ingredient: " . $focus_ingredient . " - Returning empty array");
        return [];
    }
    
    // If use_ai_only is true, log that we're skipping PDF/CSV
    if (!empty($focus_ingredient) && $use_ai_only) {
        error_log("=== getAISuggestedRecipes called with focus_ingredient: " . $focus_ingredient . " (using AI only, skipping PDF/CSV) ===");
    }
    
    // Only use AI API if no focus ingredient is specified
    // Ensure ingredients is an array
    if (!is_array($ingredients)) {
        $ingredients = [];
    }
    
    if (empty($ingredients)) {
        return [];
    }
    
    // Prepare the prompt for AI
    $ingredients_list = implode(', ', $ingredients);
    $dietary_text = !empty($dietary_preferences) ? 'Dietary preferences: ' . (is_array($dietary_preferences) ? implode(', ', $dietary_preferences) : $dietary_preferences) . '. ' : '';
    $time_text = !empty($cooking_time) ? 'Cooking time preference: ' . $cooking_time . '. ' : '';
    $difficulty_text = !empty($difficulty) ? 'Difficulty level: ' . $difficulty . '. ' : '';
    
    // If focus_ingredient is specified, emphasize it must be included in the recipe
    $focus_text = '';
    if (!empty($focus_ingredient)) {
        $focus_text = "CRITICAL REQUIREMENT: Every single recipe suggestion MUST include '{$focus_ingredient}' in the ingredients list. The ingredient '{$focus_ingredient}' must appear in the ingredients array of every recipe. Do not suggest any recipe that does not include '{$focus_ingredient}' in its ingredients. ";
    }
    
    $prompt = "I have these ingredients available: {$ingredients_list}. {$focus_text}{$dietary_text}{$time_text}{$difficulty_text}Please suggest 5 creative and delicious meal ideas. 

For each suggestion, provide a JSON object with these exact fields:
- name: Recipe name (string)
- description: Brief description of the dish (string)
- ingredients: Array of ingredient strings (MUST include all ingredients from: {$ingredients_list})
- cooking_time: Cooking time in minutes (integer)
- difficulty: Difficulty level - one of: Easy, Medium, or Hard (string)
- servings: Number of servings (integer)
- instructions: Step-by-step cooking instructions as a STRING with numbered steps. Format MUST be: \"1. First step instruction. 2. Second step instruction. 3. Third step instruction.\" etc. Each step should be on a new line or separated clearly.

CRITICAL: The instructions field MUST be formatted with numbered steps (1., 2., 3., etc.) where each step is clear and actionable.

Return ONLY a valid JSON array of recipe objects, no other text. Example format:
[
  {
    \"name\": \"Recipe Name\",
    \"description\": \"Brief description of the dish\",
    \"ingredients\": [\"ingredient1\", \"ingredient2\"],
    \"cooking_time\": 30,
    \"difficulty\": \"Easy\",
    \"servings\": 4,
    \"instructions\": \"1. First step instruction here.\\n2. Second step instruction here.\\n3. Third step instruction here.\"
  }
]";
    
    // Call AI API (using OpenAI as example, but can be changed to other providers)
    $ai_recipes = callAIRecipeAPI($prompt, $focus_ingredient);
    
    // Validate that focus ingredient is included in all recipes
    if (!empty($focus_ingredient) && !empty($ai_recipes)) {
        $validated_recipes = [];
        foreach ($ai_recipes as $recipe) {
            $ingredients_str = strtolower($recipe['ingredients'] . ' ' . ($recipe['recipe_ingredients'] ?? ''));
            if (stripos($ingredients_str, strtolower($focus_ingredient)) !== false) {
                $validated_recipes[] = $recipe;
            }
        }
        // If we filtered out recipes, return the validated ones
        if (count($validated_recipes) > 0) {
            return $validated_recipes;
        }
    }
    
    return $ai_recipes;
}

// Function to format Filipino dishes response
function formatFilipinoDishesResponse($dishes) {
    $formatted_recipes = [];
    
    foreach ($dishes as $index => $dish) {
        $formatted_recipes[] = [
            'id' => 'filipino_' . ($index + 1),
            'title' => $dish['title'] ?? $dish['name'] ?? 'Filipino Dish',
            'content' => $dish['description'] ?? 'A delicious Filipino dish',
            'description' => $dish['description'] ?? 'A delicious Filipino dish', // Also include as description for frontend compatibility
            'ingredients' => $dish['ingredients'] ?? '',
            'cooking_time' => $dish['cooking_time'] ?? 30,
            'difficulty_level' => $dish['difficulty'] ?? 'Easy',
            'servings' => $dish['servings'] ?? 4,
            'instructions' => $dish['instructions'] ?? 'Follow traditional Filipino cooking methods.',
            'match_percentage' => 100,
            'matching_ingredients' => count(explode(', ', $dish['ingredients'] ?? '')),
            'recipe_ingredients' => $dish['ingredients'] ?? '',
            'missing_ingredients' => [],
            'username' => 'Filipino Cuisine',
            'profile_img' => null,
            'is_ai_generated' => false,
            'category' => $dish['category'] ?? '',
            'calories' => $dish['calories'] ?? '',
        ];
    }
    
    return $formatted_recipes;
}

// Global variable to store the last API error message for user display
$GLOBALS['last_api_error'] = '';

// Function to call AI API for recipe suggestions
function callAIRecipeAPI($prompt, $focus_ingredient = '') {
    global $last_api_error;
    $last_api_error = ''; // Reset error message
    // If focus_ingredient is provided, we should NOT be calling this function
    // Instead, use PDF/CSV parsing. This is a fallback only.
    if (!empty($focus_ingredient)) {
        error_log("WARNING: callAIRecipeAPI called with focus_ingredient. Should use PDF parsing instead.");
        // Try PDF parsing one more time
        $dishes = getFilipinoDishesByIngredient($focus_ingredient);
        if (!empty($dishes)) {
            return formatFilipinoDishesResponse($dishes);
        }
        // Return empty instead of mock data
        return [];
    }
    
    // Check if API key is configured
    error_log("=== callAIRecipeAPI START ===");
    error_log("Checking API key configuration. Key length: " . strlen(AI_API_KEY));
    if (empty(AI_API_KEY) || AI_API_KEY === 'your-openai-api-key' || strlen(AI_API_KEY) < 20) {
        error_log("ERROR: AI API key not configured. Key value: " . (empty(AI_API_KEY) ? 'EMPTY' : substr(AI_API_KEY, 0, 10) . '...'));
        error_log("Returning empty array - no mock data will be shown.");
        // Return empty array - no mock data, system must use AI
        return [];
    }
    
    error_log("API key is configured. Making API call to OpenAI...");
    error_log("Prompt length: " . strlen($prompt));
    error_log("Model: " . AI_MODEL);
    
    $data = [
        'model' => AI_MODEL,
        'messages' => [
            [
                'role' => 'system',
                'content' => 'You are a helpful cooking assistant specialized in creating delicious and practical recipes. Always respond with valid JSON only - a JSON array of recipe objects. Each recipe must have: name (string), description (string), ingredients (array of strings), cooking_time (integer in minutes), difficulty (string: Easy/Medium/Hard), servings (integer), and instructions (string with step-by-step directions formatted as numbered steps: "1. First step.\\n2. Second step.\\n3. Third step." etc.). CRITICAL: If the user specifies a focus ingredient, that ingredient MUST appear in the ingredients array of EVERY recipe. Never suggest a recipe that does not include the user\'s specified ingredient in its ingredients list. Instructions MUST be formatted with numbered steps (1., 2., 3., etc.) where each step is on a new line or clearly separated. Return ONLY the JSON array, no markdown, no code blocks, no explanations.'
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // 30 second timeout
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // 10 second connection timeout
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    error_log("OpenAI API call completed. HTTP Code: " . $http_code);
    
    if ($http_code !== 200) {
        error_log("ERROR: OpenAI API call failed. HTTP Code: " . $http_code);
        $error_details = [];
        $user_friendly_error = '';
        
        if (!empty($curl_error)) {
            error_log("cURL Error: " . $curl_error);
            $error_details[] = "cURL Error: " . $curl_error;
        }
        
        if (!empty($response)) {
            error_log("API Response (first 1000 chars): " . substr($response, 0, 1000));
            $error_data = json_decode($response, true);
            if (isset($error_data['error'])) {
                $error_message = $error_data['error']['message'] ?? 'Unknown error';
                $error_type = $error_data['error']['type'] ?? 'Unknown type';
                $error_code = $error_data['error']['code'] ?? '';
                error_log("OpenAI Error Type: " . $error_type);
                error_log("OpenAI Error Code: " . $error_code);
                error_log("OpenAI Error Message: " . $error_message);
                $error_details[] = "API Error: " . $error_message;
                
                // Set user-friendly error messages based on error type
                if ($error_code === 'insufficient_quota' || $error_type === 'insufficient_quota' || 
                    stripos($error_message, 'quota') !== false || stripos($error_message, 'billing') !== false ||
                    stripos($error_message, 'exceeded your current quota') !== false) {
                    $user_friendly_error = 'Your OpenAI account has exceeded its quota or billing limit. Please add credits to your OpenAI account at https://platform.openai.com/account/billing';
                } elseif (stripos($error_message, 'invalid') !== false && stripos($error_message, 'api key') !== false) {
                    $user_friendly_error = 'Your API key may be invalid or expired. Please check config/ai_config.php';
                } elseif ($http_code === 429 && stripos($error_message, 'rate limit') !== false) {
                    $user_friendly_error = 'Rate limit exceeded. Please try again in a few moments.';
                } elseif ($http_code === 401) {
                    $user_friendly_error = 'Authentication failed. Please check your API key in config/ai_config.php';
                } else {
                    $user_friendly_error = 'OpenAI API Error: ' . $error_message;
                }
                
                // Store error message globally so it can be retrieved
                $GLOBALS['last_api_error'] = $user_friendly_error;
            }
        } else {
            error_log("No response received from API");
            $error_details[] = "No response received from OpenAI API. Check your internet connection.";
            $user_friendly_error = 'No response received from OpenAI API. Please check your internet connection.';
            $GLOBALS['last_api_error'] = $user_friendly_error;
        }
        
        // Store error details for potential retrieval (though we'll return empty for now)
        error_log("Error details: " . implode(" | ", $error_details));
        
        // If focus_ingredient is provided, try PDF parsing instead of mock data
        if (!empty($focus_ingredient)) {
            error_log("Falling back to PDF parsing due to API error");
            $dishes = getFilipinoDishesByIngredient($focus_ingredient);
            if (!empty($dishes)) {
                return formatFilipinoDishesResponse($dishes);
            }
            return [];
        }
        
        // Store error message in a way that can be retrieved (using a global or session)
        // For now, we'll log it and return empty, but the AJAX handler should show the error
        if (!empty($user_friendly_error)) {
            error_log("User-friendly error message: " . $user_friendly_error);
            // We can't return the error message directly, but we'll handle it in the AJAX response
        }
        
        // Return empty array instead of mock data - system must use AI
        error_log("API error occurred. Returning empty array.");
        return [];
    }
    
    $result = json_decode($response, true);
    
    if (isset($result['error'])) {
        $error_info = $result['error'];
        error_log("ERROR: OpenAI API returned an error: " . json_encode($error_info));
        error_log("Error type: " . ($error_info['type'] ?? 'Unknown'));
        error_log("Error code: " . ($error_info['code'] ?? 'Unknown'));
        error_log("Error message: " . ($error_info['message'] ?? 'Unknown'));
        
        // If focus_ingredient is provided, try PDF parsing instead of mock data
        if (!empty($focus_ingredient)) {
            error_log("Falling back to PDF parsing due to API error");
            $dishes = getFilipinoDishesByIngredient($focus_ingredient);
            if (!empty($dishes)) {
                return formatFilipinoDishesResponse($dishes);
            }
            return [];
        }
        // Return empty array instead of mock data - system must use AI
        error_log("API error occurred. Returning empty array.");
        return [];
    }
    
    if (isset($result['choices'][0]['message']['content'])) {
        $content = $result['choices'][0]['message']['content'];
        error_log("OpenAI API Response received. Content length: " . strlen($content));
        error_log("First 500 chars of response: " . substr($content, 0, 500));
        
        // Try to extract JSON from the response
        if (preg_match('/\[.*\]/s', $content, $matches)) {
            error_log("Found JSON array in response. Attempting to parse...");
            $recipes = json_decode($matches[0], true);
            if (is_array($recipes) && !empty($recipes)) {
                error_log("Successfully parsed " . count($recipes) . " recipes from AI response");
                return formatAIRecipeResponse($recipes, $focus_ingredient);
            } else {
                error_log("Failed to parse JSON from AI response. JSON error: " . json_last_error_msg());
                error_log("JSON string that failed: " . substr($matches[0], 0, 500));
            }
        } else {
            error_log("No JSON array found in AI response. Full content (first 1000 chars): " . substr($content, 0, 1000));
            // Try to find JSON even if wrapped in markdown
            if (preg_match('/```json\s*(\[.*?\])\s*```/s', $content, $json_matches)) {
                error_log("Found JSON in markdown code block. Attempting to parse...");
                $recipes = json_decode($json_matches[1], true);
                if (is_array($recipes) && !empty($recipes)) {
                    error_log("Successfully parsed " . count($recipes) . " recipes from markdown-wrapped JSON");
                    return formatAIRecipeResponse($recipes, $focus_ingredient);
                }
            }
        }
    } else {
        error_log("Unexpected API response structure. Full response: " . json_encode($result));
    }
    
    // If focus_ingredient is provided, try PDF parsing instead of mock data
    if (!empty($focus_ingredient)) {
        $dishes = getFilipinoDishesByIngredient($focus_ingredient);
        if (!empty($dishes)) {
            return formatFilipinoDishesResponse($dishes);
        }
        return [];
    }
    
    // Return empty array instead of mock data - system must use AI
    error_log("Failed to parse AI response. Returning empty array - configure API key for dynamic suggestions.");
    return [];
}

// Function to format AI recipe response
function formatAIRecipeResponse($recipes, $focus_ingredient = '') {
    $formatted_recipes = [];
    
    foreach ($recipes as $index => $recipe) {
        $ingredients_array = $recipe['ingredients'] ?? [];
        $ingredients_str = implode(', ', $ingredients_array);
        
        // Ensure focus ingredient is in the ingredients list
        if (!empty($focus_ingredient)) {
            $ingredients_lower = array_map('strtolower', $ingredients_array);
            if (!in_array(strtolower($focus_ingredient), $ingredients_lower)) {
                // Add the focus ingredient if it's missing
                array_unshift($ingredients_array, $focus_ingredient);
                $ingredients_str = implode(', ', $ingredients_array);
            }
        }
        
        // Format instructions to ensure numbered steps
        $instructions = $recipe['instructions'] ?? 'Follow the recipe instructions';
        // Ensure instructions are properly formatted with numbered steps
        $instructions = formatInstructions($instructions);
        
        $formatted_recipes[] = [
            'id' => 'recipe_' . ($index + 1),
            'title' => $recipe['name'] ?? 'Suggested Recipe',
            'content' => $recipe['description'] ?? 'A delicious recipe suggestion',
            'description' => $recipe['description'] ?? 'A delicious recipe suggestion', // Also include as description
            'ingredients' => $ingredients_str,
            'cooking_time' => $recipe['cooking_time'] ?? 30,
            'difficulty_level' => $recipe['difficulty'] ?? 'Easy',
            'servings' => $recipe['servings'] ?? 4,
            'instructions' => $instructions,
            'match_percentage' => 100, // Recipe suggestions are always 100% match
            'matching_ingredients' => count($ingredients_array),
            'recipe_ingredients' => $ingredients_str,
            'missing_ingredients' => [],
            'username' => 'Recipe Assistant',
            'profile_img' => null,
            'is_ai_generated' => true
        ];
    }
    
    return $formatted_recipes;
}

// Helper function to format instructions with numbered steps
function formatInstructions($instructions) {
    if (empty($instructions)) {
        return 'Follow the recipe instructions.';
    }
    
    // Remove any existing markdown or code blocks
    $instructions = preg_replace('/```[\s\S]*?```/', '', $instructions);
    $instructions = preg_replace('/`[^`]*`/', '', $instructions);
    
    // Check if instructions already have numbered format
    if (preg_match('/^\d+[\.\)]\s/', $instructions) || preg_match('/\n\d+[\.\)]\s/', $instructions)) {
        // Already has numbered format, just clean it up
        $instructions = preg_replace('/\r\n|\r/', "\n", $instructions);
        $instructions = preg_replace('/\n{3,}/', "\n\n", $instructions);
        return trim($instructions);
    }
    
    // Split by sentences or newlines and number them
    $sentences = preg_split('/(?<=[.!?])\s+|(?<=\n)/', $instructions);
    $numbered_steps = [];
    $step_num = 1;
    
    foreach ($sentences as $sentence) {
        $sentence = trim($sentence);
        if (empty($sentence) || strlen($sentence) < 5) {
            continue;
        }
        
        // Remove any existing numbering
        $sentence = preg_replace('/^\d+[\.\)]\s*/', '', $sentence);
        $sentence = preg_replace('/^Step\s+\d+[\.\):]\s*/i', '', $sentence);
        
        if (!empty($sentence)) {
            $numbered_steps[] = $step_num . '. ' . $sentence;
            $step_num++;
        }
    }
    
    if (empty($numbered_steps)) {
        // Fallback: split by periods and number
        $parts = explode('.', $instructions);
        $numbered_steps = [];
        $step_num = 1;
        foreach ($parts as $part) {
            $part = trim($part);
            if (!empty($part) && strlen($part) > 5) {
                $numbered_steps[] = $step_num . '. ' . $part . '.';
                $step_num++;
            }
        }
    }
    
    return implode("\n", $numbered_steps);
}

// Removed getMockRecipeSuggestions function - system now uses only dynamic AI suggestions
// No mock/fallback recipes will be shown. Configure API key for AI-powered recipe suggestions.

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
<div class="container-fluid py-5 px-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <a href="input_ingredients.php" class="btn btn-outline-secondary btn-sm mb-2">
                <i class="bi bi-arrow-left"></i> Back to Ingredients
            </a>
            <h2 class="mt-2">Recipe Suggestions</h2>
            <p class="text-muted mb-0">Get intelligent meal suggestions</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-primary" id="clear-filters-btn">
                <i class="bi bi-arrow-clockwise"></i> Clear Filters
            </button>
            <button type="button" class="btn btn-primary" id="get-suggestions-btn">
                <i class="bi bi-search"></i> Get Suggestions
            </button>
        </div>
    </div>

    <!-- Configuration Notice -->
    <div class="alert alert-warning" id="ai-config-notice" style="display: none;">
        <i class="bi bi-exclamation-triangle"></i>
        <strong>Configuration Required:</strong> To use recipe suggestions, please configure your API key. 
        <br><small>
            <strong>How to configure:</strong><br>
            1. Get your API key from <a href="https://platform.openai.com/api-keys" target="_blank">https://platform.openai.com/api-keys</a><br>
            2. Option A: Set environment variable <code>OPENAI_API_KEY</code> (recommended)<br>
            3. Option B: Create <code>config/ai_config.php</code> with <code>$ai_api_key = 'your-key-here';</code><br>
            4. Option C: Edit <code>residents/suggested_recipes.php</code> line 15 and replace <code>'your-openai-api-key'</code><br>
            Currently showing demo/mock suggestions.
        </small>
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
                <div id="recipes-container" class="row g-3">
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
#recipes-container {
    margin: 0;
    padding: 0;
    width: 100%;
    max-width: 100%;
}

#recipes-container > div {
    padding-left: 0.5rem;
    padding-right: 0.5rem;
}

.recipe-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    width: 100%;
    margin: 0;
}

.recipe-card .card-body {
    padding: 1.25rem;
}

/* Reduce whitespace in results section */
#results-section .card {
    margin: 0;
}

#results-section .card-body {
    padding: 1rem;
}

/* Optimize layout to reduce right whitespace */
@media (min-width: 992px) {
    #recipes-container {
        max-width: 100%;
        margin-right: 0;
        margin-left: 0;
    }
    
    #recipes-container > div.col-lg-6 {
        flex: 0 0 50%;
        max-width: 50%;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    
    /* Ensure last card in row doesn't have extra margin */
    #recipes-container > div.col-lg-6:nth-child(2n) {
        padding-right: 0.5rem;
    }
}

/* Reduce container padding to minimize whitespace */
.container-fluid {
    padding-left: 1rem !important;
    padding-right: 1rem !important;
}

/* Ensure results section uses full width */
#results-section {
    width: 100%;
    max-width: 100%;
}

#results-section .card {
    width: 100%;
    margin: 0;
}

/* On smaller screens, make cards full width */
@media (max-width: 991px) {
    #recipes-container > div {
        flex: 0 0 100%;
        max-width: 100%;
    }
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

/* Recipe details */
.recipe-details {
    background-color: #f8f9fa;
    padding: 1rem;
    border-radius: 0.375rem;
}

.instructions-content {
    white-space: pre-line;
    line-height: 1.8;
    font-size: 0.95rem;
}

.instructions-content strong {
    color: #0d6efd;
    font-weight: 600;
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

<!-- html2canvas library for snapshot functionality -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<script>
// Global variables
let selectedIngredients = <?= json_encode($user_ingredients) ?>;
let ingredientSuggestions = [];

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    renderSelectedIngredients();
    setupEventListeners();
    
    // Check if ingredient parameter is passed from input_ingredients.php
    const urlParams = new URLSearchParams(window.location.search);
    const ingredientParam = urlParams.get('ingredient');
    if (ingredientParam) {
        // Add the ingredient to selected ingredients if not already present
        const ingredient = ingredientParam.trim().toLowerCase();
        if (ingredient && !selectedIngredients.includes(ingredient)) {
            selectedIngredients.push(ingredient);
            renderSelectedIngredients();
        }
        // Focus on the ingredients input
        document.getElementById('ingredients-input').focus();
    }
    
    // Show configuration notice if using demo data
    <?php if (empty(AI_API_KEY) || AI_API_KEY === 'your-openai-api-key' || strlen(AI_API_KEY) < 20): ?>
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
            if (data.recipes && data.recipes.length > 0) {
                displayRecipes(data.recipes);
            } else {
                showNotification(data.message || 'No recipes found. Please try different ingredients or check your API configuration.', 'warning');
                document.getElementById('results-section').style.display = 'none';
                document.getElementById('no-results').style.display = 'block';
            }
        } else {
            showNotification(data.message || 'Error getting suggested recipes. Please check your API configuration.', 'error');
            document.getElementById('results-section').style.display = 'none';
            document.getElementById('no-results').style.display = 'block';
        }
    })
    .catch(error => {
        hideLoading();
        showNotification('An error occurred while getting suggestions. Please check the browser console and server logs.', 'error');
        console.error('Error:', error);
        document.getElementById('results-section').style.display = 'none';
        document.getElementById('no-results').style.display = 'block';
    });
}

// Store recipes globally for modal access
let currentRecipes = [];

// Display recipes
function displayRecipes(recipes) {
    const resultsSection = document.getElementById('results-section');
    const noResults = document.getElementById('no-results');
    const recipesContainer = document.getElementById('recipes-container');
    const resultsCount = document.getElementById('results-count');
    
    // Store recipes globally
    currentRecipes = recipes;
    
    if (recipes.length === 0) {
        resultsSection.style.display = 'none';
        noResults.style.display = 'block';
        return;
    }
    
    resultsSection.style.display = 'block';
    noResults.style.display = 'none';
    resultsCount.textContent = `${recipes.length} recipe${recipes.length !== 1 ? 's' : ''} found`;
    
    recipesContainer.innerHTML = recipes.map((recipe, index) => {
        // Format instructions with proper line breaks
        const instructions = (recipe.instructions || 'No instructions available.')
            .replace(/\n/g, '<br>')
            .replace(/(\d+\.\s)/g, '<strong>$1</strong>');
        
        // Format ingredients list
        const ingredientsList = (recipe.recipe_ingredients || recipe.ingredients || '')
            .split(',')
            .map(ing => ing.trim())
            .filter(ing => ing.length > 0);
        
        return `
        <div class="col-12 col-md-6 col-lg-6 mb-4">
            <div class="card recipe-card h-100">
                <div class="card-body">
                    <!-- Title (Top Part) -->
                    <div class="mb-3">
                        <h4 class="card-title mb-2">${recipe.title || recipe.name || 'Untitled Recipe'}</h4>
                    </div>
                    
                    <!-- Description -->
                    <div class="mb-3">
                        <p class="card-text text-muted">${recipe.description || recipe.content || 'No description available.'}</p>
                    </div>
                    
                    <!-- Recipe Details -->
                    <div class="recipe-details mb-3">
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <strong>Cooking time:</strong> ${recipe.cooking_time || 'N/A'} min
                            </div>
                            <div class="col-6">
                                <strong>Difficulty:</strong> ${recipe.difficulty_level || recipe.difficulty || 'N/A'}
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-12">
                                <strong>Servings:</strong> ${recipe.servings || 'N/A'}
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ingredients -->
                    <div class="mb-3">
                        <strong>Ingredients:</strong>
                        <ul class="list-unstyled mt-2">
                            ${ingredientsList.map(ing => {
                                const isAvailable = selectedIngredients.some(sel => 
                                    sel.toLowerCase() === ing.toLowerCase() || 
                                    ing.toLowerCase().includes(sel.toLowerCase())
                                );
                                return `<li class="mb-1">
                                    <span class="badge ${isAvailable ? 'bg-success' : 'bg-secondary'} me-1">${ing}</span>
                                </li>`;
                            }).join('')}
                        </ul>
                    </div>
                    
                    <!-- Instructions (Step by Step) -->
                    <div class="mb-3">
                        <strong>Instructions:</strong>
                        <div class="instructions-content mt-2 p-3 bg-light rounded">
                            ${instructions}
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-flex justify-content-between align-items-center mt-3 pt-3 border-top">
                        <small class="text-muted">
                            <i class="bi bi-person"></i> ${recipe.username || 'Recipe Assistant'}
                        </small>
                        <div class="btn-group">
                            <button class="btn btn-outline-primary btn-sm" onclick="viewRecipeDetails(${index})">
                                <i class="bi bi-eye"></i> View Full
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="downloadRecipeSnapshot(${index})">
                                <i class="bi bi-download"></i> Snapshot
                            </button>
                            <button class="btn btn-outline-success btn-sm" onclick="shareRecipeOnSocialMedia(${index})">
                                <i class="bi bi-share"></i> Share
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    }).join('');
}

// Get match class for styling
function getMatchClass(percentage) {
    if (percentage >= 70) return 'match-high';
    if (percentage >= 40) return 'match-medium';
    return 'match-low';
}

// View recipe details in modal
function viewRecipeDetails(recipeIndex) {
    // Get recipe data from stored recipes
    if (!currentRecipes || !currentRecipes[recipeIndex]) {
        showNotification('Recipe not found.', 'error');
        return;
    }
    
    const recipeData = currentRecipes[recipeIndex];
    
    // Create and show modal with full recipe details
    const modalHtml = `
        <div class="modal fade" id="recipeModal" tabindex="-1" aria-labelledby="recipeModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="recipeModalLabel">${recipeData.title || recipeData.name || 'Recipe Details'}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <strong>Description:</strong>
                            <p class="text-muted">${recipeData.description || recipeData.content || 'No description available.'}</p>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <strong>Cooking time:</strong> ${recipeData.cooking_time || 'N/A'} min
                            </div>
                            <div class="col-md-4">
                                <strong>Difficulty:</strong> ${recipeData.difficulty_level || recipeData.difficulty || 'N/A'}
                            </div>
                            <div class="col-md-4">
                                <strong>Servings:</strong> ${recipeData.servings || 'N/A'}
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Ingredients:</strong>
                            <ul class="list-group list-group-flush">
                                ${(recipeData.recipe_ingredients || recipeData.ingredients || '').split(',').map(ing => 
                                    `<li class="list-group-item">${ing.trim()}</li>`
                                ).join('')}
                            </ul>
                        </div>
                        
                        <div class="mb-3">
                            <strong>Instructions:</strong>
                            <div class="instructions-content p-3 bg-light rounded mt-2">
                                ${(recipeData.instructions || 'No instructions available.').replace(/\n/g, '<br>').replace(/(\d+\.\s)/g, '<strong>$1</strong>')}
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-info" onclick="downloadRecipeSnapshotFromModal(${recipeIndex});">
                            <i class="bi bi-download"></i> Download Snapshot
                        </button>
                        <button type="button" class="btn btn-primary" onclick="shareRecipeOnSocialMedia(${recipeIndex}); bootstrap.Modal.getInstance(document.getElementById('recipeModal')).hide();">
                            <i class="bi bi-share"></i> Share Recipe
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Remove existing modal if any
    const existingModal = document.getElementById('recipeModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Add modal to body
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('recipeModal'));
    modal.show();
    
    // Clean up when modal is hidden
    document.getElementById('recipeModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Download recipe snapshot as image
function downloadRecipeSnapshot(recipeIndex) {
    if (!currentRecipes || !currentRecipes[recipeIndex]) {
        showNotification('Recipe not found.', 'error');
        return;
    }
    
    const recipeData = currentRecipes[recipeIndex];
    const recipeCards = document.querySelectorAll('.recipe-card');
    const recipeCard = recipeCards[recipeIndex];
    
    if (!recipeCard) {
        showNotification('Recipe card not found.', 'error');
        return;
    }
    
    // Use html2canvas to capture the recipe card as an image
    if (typeof html2canvas === 'undefined') {
        // Load html2canvas library if not already loaded
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        script.onload = function() {
            captureRecipeSnapshot(recipeCard, recipeData);
        };
        document.head.appendChild(script);
    } else {
        captureRecipeSnapshot(recipeCard, recipeData);
    }
}

// Capture recipe snapshot
function captureRecipeSnapshot(recipeCard, recipeData) {
    html2canvas(recipeCard, {
        backgroundColor: '#ffffff',
        scale: 2,
        logging: false
    }).then(canvas => {
        // Convert canvas to blob
        canvas.toBlob(function(blob) {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = (recipeData.title || recipeData.name || 'recipe').replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_snapshot.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            showNotification('Recipe snapshot downloaded successfully!', 'success');
        }, 'image/png');
    }).catch(error => {
        console.error('Error capturing snapshot:', error);
        showNotification('Failed to capture recipe snapshot.', 'error');
    });
}

// Download recipe snapshot from modal
function downloadRecipeSnapshotFromModal(recipeIndex) {
    if (!currentRecipes || !currentRecipes[recipeIndex]) {
        showNotification('Recipe not found.', 'error');
        return;
    }
    
    const recipeData = currentRecipes[recipeIndex];
    const modal = document.getElementById('recipeModal');
    
    if (!modal) {
        showNotification('Modal not found.', 'error');
        return;
    }
    
    // Use html2canvas to capture the modal content as an image
    if (typeof html2canvas === 'undefined') {
        const script = document.createElement('script');
        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
        script.onload = function() {
            captureModalSnapshot(modal, recipeData);
        };
        document.head.appendChild(script);
    } else {
        captureModalSnapshot(modal, recipeData);
    }
}

// Capture modal snapshot
function captureModalSnapshot(modal, recipeData) {
    const modalContent = modal.querySelector('.modal-content');
    
    html2canvas(modalContent, {
        backgroundColor: '#ffffff',
        scale: 2,
        logging: false
    }).then(canvas => {
        canvas.toBlob(function(blob) {
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = (recipeData.title || recipeData.name || 'recipe').replace(/[^a-z0-9]/gi, '_').toLowerCase() + '_snapshot.png';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
            showNotification('Recipe snapshot downloaded successfully!', 'success');
        }, 'image/png');
    }).catch(error => {
        console.error('Error capturing snapshot:', error);
        showNotification('Failed to capture recipe snapshot.', 'error');
    });
}

// Share recipe on social media
function shareRecipeOnSocialMedia(recipeIndex) {
    if (!currentRecipes || !currentRecipes[recipeIndex]) {
        showNotification('Recipe not found.', 'error');
        return;
    }
    
    const recipeData = currentRecipes[recipeIndex];
    const recipeTitle = recipeData.title || recipeData.name || 'Recipe';
    const recipeDescription = recipeData.description || recipeData.content || 'Check out this delicious recipe!';
    const recipeUrl = window.location.href;
    
    // Create share text
    const shareText = `${recipeTitle}\n\n${recipeDescription}\n\nView recipe: ${recipeUrl}`;
    
    // Check if Web Share API is available
    if (navigator.share) {
        navigator.share({
            title: recipeTitle,
            text: recipeDescription,
            url: recipeUrl
        }).then(() => {
            showNotification('Recipe shared successfully!', 'success');
        }).catch(error => {
            console.error('Error sharing:', error);
            // Fallback to copy to clipboard
            copyToClipboard(shareText);
        });
    } else {
        // Fallback: Copy to clipboard and show options
        copyToClipboard(shareText);
        showShareOptions(recipeTitle, recipeUrl);
    }
}

// Copy text to clipboard
function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => {
            showNotification('Recipe link copied to clipboard!', 'success');
        }).catch(error => {
            console.error('Error copying to clipboard:', error);
            showNotification('Failed to copy to clipboard.', 'error');
        });
    } else {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        try {
            document.execCommand('copy');
            showNotification('Recipe link copied to clipboard!', 'success');
        } catch (error) {
            console.error('Error copying to clipboard:', error);
            showNotification('Failed to copy to clipboard.', 'error');
        }
        document.body.removeChild(textarea);
    }
}

// Show share options
function showShareOptions(title, url) {
    const shareModal = document.createElement('div');
    shareModal.className = 'modal fade';
    shareModal.id = 'shareModal';
    shareModal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Share Recipe</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="mb-3"><strong>Share "${title}" on:</strong></p>
                    <div class="d-grid gap-2">
                        <a href="https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}" target="_blank" class="btn btn-primary">
                            <i class="bi bi-facebook"></i> Facebook
                        </a>
                        <a href="https://twitter.com/intent/tweet?text=${encodeURIComponent(title)}&url=${encodeURIComponent(url)}" target="_blank" class="btn btn-info">
                            <i class="bi bi-twitter"></i> Twitter
                        </a>
                        <a href="https://wa.me/?text=${encodeURIComponent(title + ' ' + url)}" target="_blank" class="btn btn-success">
                            <i class="bi bi-whatsapp"></i> WhatsApp
                        </a>
                        <a href="mailto:?subject=${encodeURIComponent(title)}&body=${encodeURIComponent(url)}" class="btn btn-secondary">
                            <i class="bi bi-envelope"></i> Email
                        </a>
                    </div>
                    <p class="mt-3 mb-0"><small class="text-muted">Recipe link has been copied to your clipboard!</small></p>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(shareModal);
    const bsModal = new bootstrap.Modal(shareModal);
    bsModal.show();
    
    shareModal.addEventListener('hidden.bs.modal', function() {
        this.remove();
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
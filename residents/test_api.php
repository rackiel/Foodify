<?php
/**
 * API Diagnostic Tool
 * This file helps diagnose OpenAI API connection issues
 */

include '../config/db.php';

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

?>
<!DOCTYPE html>
<html>
<head>
    <title>API Diagnostic Tool</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        .info { color: blue; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .test-section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; border-radius: 5px; }
    </style>
</head>
<body>
    <h1>OpenAI API Diagnostic Tool</h1>
    
    <div class="test-section">
        <h2>1. API Key Configuration</h2>
        <?php if (empty($ai_api_key) || $ai_api_key === 'your-openai-api-key'): ?>
            <p class="error">❌ API Key NOT configured</p>
            <p>Please set your API key in <code>config/ai_config.php</code></p>
        <?php else: ?>
            <p class="success">✅ API Key is configured</p>
            <p>Key starts with: <code><?php echo substr($ai_api_key, 0, 10); ?>...</code></p>
            <p>Key length: <?php echo strlen($ai_api_key); ?> characters</p>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($ai_api_key) && $ai_api_key !== 'your-openai-api-key'): ?>
    <div class="test-section">
        <h2>2. API Connection Test</h2>
        <?php
        $test_prompt = "Say 'API connection successful' in JSON format: {\"status\": \"success\", \"message\": \"API connection successful\"}";
        $data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                ['role' => 'user', 'content' => $test_prompt]
            ],
            'max_tokens' => 50,
            'temperature' => 0.7
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $ai_api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($http_code === 200) {
            echo '<p class="success">✅ API Connection Successful!</p>';
            $result = json_decode($response, true);
            if (isset($result['choices'][0]['message']['content'])) {
                echo '<p>Response: ' . htmlspecialchars($result['choices'][0]['message']['content']) . '</p>';
            }
        } else {
            echo '<p class="error">❌ API Connection Failed</p>';
            echo '<p>HTTP Code: ' . $http_code . '</p>';
            if (!empty($curl_error)) {
                echo '<p class="error">cURL Error: ' . htmlspecialchars($curl_error) . '</p>';
            }
            if (!empty($response)) {
                $error_data = json_decode($response, true);
                if (isset($error_data['error'])) {
                    echo '<p class="error">Error Type: ' . htmlspecialchars($error_data['error']['type'] ?? 'Unknown') . '</p>';
                    echo '<p class="error">Error Message: ' . htmlspecialchars($error_data['error']['message'] ?? 'Unknown') . '</p>';
                    
                    if (isset($error_data['error']['code'])) {
                        echo '<p class="error">Error Code: ' . htmlspecialchars($error_data['error']['code']) . '</p>';
                    }
                } else {
                    echo '<p>Raw Response:</p>';
                    echo '<pre>' . htmlspecialchars(substr($response, 0, 1000)) . '</pre>';
                }
            }
        }
        ?>
    </div>
    
    <div class="test-section">
        <h2>3. Recipe Generation Test</h2>
        <?php
        $recipe_prompt = "I have these ingredients available: chicken, rice, garlic. Please suggest 1 creative and delicious meal idea. 

For the suggestion, provide a JSON object with these exact fields:
- name: Recipe name (string)
- description: Brief description of the dish (string)
- ingredients: Array of ingredient strings (MUST include: chicken, rice, garlic)
- cooking_time: Cooking time in minutes (integer)
- difficulty: Difficulty level - one of: Easy, Medium, or Hard (string)
- servings: Number of servings (integer)
- instructions: Step-by-step cooking instructions as a STRING with numbered steps (1. First step. 2. Second step. etc.)

Return ONLY a valid JSON array with one recipe object, no other text.";

        $recipe_data = [
            'model' => 'gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful cooking assistant. Always respond with valid JSON only - a JSON array of recipe objects. Return ONLY the JSON array, no markdown, no code blocks, no explanations.'
                ],
                [
                    'role' => 'user',
                    'content' => $recipe_prompt
                ]
            ],
            'max_tokens' => 500,
            'temperature' => 0.7
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($recipe_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $ai_api_key
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $recipe_response = curl_exec($ch);
        $recipe_http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $recipe_curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($recipe_http_code === 200) {
            $recipe_result = json_decode($recipe_response, true);
            if (isset($recipe_result['choices'][0]['message']['content'])) {
                $content = $recipe_result['choices'][0]['message']['content'];
                echo '<p class="success">✅ Recipe Generation Test Successful!</p>';
                echo '<p>Response length: ' . strlen($content) . ' characters</p>';
                
                // Try to parse JSON
                if (preg_match('/\[.*\]/s', $content, $matches)) {
                    $recipes = json_decode($matches[0], true);
                    if (is_array($recipes) && !empty($recipes)) {
                        echo '<p class="success">✅ Successfully parsed ' . count($recipes) . ' recipe(s)</p>';
                        echo '<h3>First Recipe:</h3>';
                        echo '<pre>' . json_encode($recipes[0], JSON_PRETTY_PRINT) . '</pre>';
                    } else {
                        echo '<p class="error">❌ Failed to parse JSON. Error: ' . json_last_error_msg() . '</p>';
                        echo '<p>Raw JSON string:</p>';
                        echo '<pre>' . htmlspecialchars(substr($matches[0], 0, 500)) . '</pre>';
                    }
                } else {
                    echo '<p class="warning">⚠️ No JSON array found in response</p>';
                    echo '<p>First 500 characters of response:</p>';
                    echo '<pre>' . htmlspecialchars(substr($content, 0, 500)) . '</pre>';
                }
            }
        } else {
            echo '<p class="error">❌ Recipe Generation Test Failed</p>';
            echo '<p>HTTP Code: ' . $recipe_http_code . '</p>';
            if (!empty($recipe_curl_error)) {
                echo '<p class="error">cURL Error: ' . htmlspecialchars($recipe_curl_error) . '</p>';
            }
            if (!empty($recipe_response)) {
                $error_data = json_decode($recipe_response, true);
                if (isset($error_data['error'])) {
                    echo '<p class="error">Error: ' . htmlspecialchars($error_data['error']['message'] ?? 'Unknown') . '</p>';
                }
            }
        }
        ?>
    </div>
    <?php endif; ?>
    
    <div class="test-section">
        <h2>4. PHP Configuration</h2>
        <p><strong>PHP Version:</strong> <?php echo phpversion(); ?></p>
        <p><strong>cURL Enabled:</strong> <?php echo function_exists('curl_init') ? '✅ Yes' : '❌ No'; ?></p>
        <p><strong>JSON Extension:</strong> <?php echo function_exists('json_encode') ? '✅ Yes' : '❌ No'; ?></p>
        <p><strong>Error Log Location:</strong> <?php echo ini_get('error_log') ?: 'Not configured'; ?></p>
    </div>
    
    <div class="test-section">
        <h2>5. Next Steps</h2>
        <ul>
            <li>If API key is not configured, edit <code>config/ai_config.php</code></li>
            <li>If connection fails, check your internet connection and firewall settings</li>
            <li>If you get authentication errors, verify your API key is correct and active</li>
            <li>If you get quota errors, check your OpenAI account billing and usage limits</li>
            <li>Check PHP error logs for detailed error messages</li>
        </ul>
    </div>
</body>
</html>


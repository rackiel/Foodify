<?php
/**
 * Database Setup Script - Add Quantity Field to Ingredient Table
 * 
 * This script adds the quantity field to the ingredient table.
 * Run this file once to update your database schema.
 * 
 * Usage: Navigate to http://localhost/foodify/setup_quantity_field.php
 */

include 'config/db.php';

// Prevent multiple executions
$setup_complete = false;
$messages = [];

try {
    // Check if quantity column already exists
    $check = $conn->query("SHOW COLUMNS FROM ingredient LIKE 'quantity'");
    
    if ($check && $check->num_rows > 0) {
        $messages[] = [
            'type' => 'warning',
            'text' => '⚠️ Quantity field already exists in the ingredient table.'
        ];
        $setup_complete = true;
    } else {
        // Add quantity column
        $sql1 = "ALTER TABLE ingredient ADD COLUMN quantity DECIMAL(10,2) NULL COMMENT 'Quantity of the ingredient'";
        
        if ($conn->query($sql1) === TRUE) {
            $messages[] = [
                'type' => 'success',
                'text' => '✅ Successfully added quantity field to ingredient table'
            ];
        } else {
            throw new Exception("Error adding quantity field: " . $conn->error);
        }
        
        // Add index for quantity
        $sql2 = "CREATE INDEX idx_ingredient_quantity ON ingredient(quantity)";
        
        if ($conn->query($sql2) === TRUE) {
            $messages[] = [
                'type' => 'success',
                'text' => '✅ Successfully added index for quantity field'
            ];
        } else {
            // Index creation might fail if it already exists, that's okay
            $messages[] = [
                'type' => 'info',
                'text' => 'ℹ️ Index creation skipped (might already exist)'
            ];
        }
        
        $setup_complete = true;
        $messages[] = [
            'type' => 'success',
            'text' => '🎉 Database migration completed successfully!'
        ];
    }
    
} catch (Exception $e) {
    $messages[] = [
        'type' => 'danger',
        'text' => '❌ Error: ' . $e->getMessage()
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Quantity Field - Foodify</title>
    <link href="bootstrap/assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .setup-container {
            max-width: 600px;
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo img {
            height: 80px;
        }
        h2 {
            color: #333;
            margin-bottom: 25px;
            text-align: center;
        }
        .alert {
            margin-bottom: 15px;
        }
        .next-steps {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 25px;
        }
        .btn-group-custom {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-group-custom .btn {
            flex: 1;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="logo">
            <img src="uploads/images/foodify_logo.png" alt="Foodify Logo">
        </div>
        
        <h2>🔧 Database Migration</h2>
        <p class="text-center text-muted">Add Quantity Field to Ingredients</p>
        
        <hr>
        
        <?php foreach ($messages as $message): ?>
            <div class="alert alert-<?= $message['type'] ?> alert-dismissible fade show" role="alert">
                <?= $message['text'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endforeach; ?>
        
        <?php if ($setup_complete): ?>
            <div class="next-steps">
                <h5>✨ What's New:</h5>
                <ul>
                    <li>📊 <strong>Quantity Field</strong> - Track the amount of each ingredient</li>
                    <li>🎯 <strong>Dynamic Display</strong> - Shows quantity with units (e.g., "500 grams")</li>
                    <li>✏️ <strong>Add & Update Forms</strong> - New quantity input field</li>
                    <li>🏷️ <strong>Smart Badges</strong> - Green badge showing quantity on cards</li>
                </ul>
                
                <h5 class="mt-3">📝 Next Steps:</h5>
                <ol>
                    <li>Delete this file (<code>setup_quantity_field.php</code>) for security</li>
                    <li>Go to Ingredients page and add quantities to existing items</li>
                    <li>New ingredients will have the quantity field available</li>
                </ol>
            </div>
            
            <div class="btn-group-custom">
                <a href="residents/input_ingredients.php" class="btn btn-primary">
                    <i class="bi bi-box-seam"></i> Go to Ingredients
                </a>
                <a href="index.php" class="btn btn-success">
                    <i class="bi bi-house"></i> Go to Home
                </a>
            </div>
        <?php else: ?>
            <div class="alert alert-danger">
                <strong>Setup Failed!</strong> Please check the error messages above and try again.
            </div>
            
            <div class="mt-3">
                <a href="setup_quantity_field.php" class="btn btn-warning w-100">
                    🔄 Retry Setup
                </a>
            </div>
        <?php endif; ?>
        
        <div class="text-center mt-4">
            <small class="text-muted">
                ⚠️ For security reasons, please delete this file after successful setup
            </small>
        </div>
    </div>
    
    <script src="bootstrap/assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>


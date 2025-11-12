<?php
/**
 * Announcements System Database Setup
 * Run this file once to create/update all necessary tables
 */

include '../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is team officer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'team officer') {
    die('Access denied. Team officer login required.');
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Announcements Database Setup</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; border-radius: 4px; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; border-radius: 4px; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; border-radius: 4px; }
        h1 { color: #333; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; margin-top: 20px; }
    </style>
</head>
<body>
    <h1>Announcements System Database Setup</h1>";

$errors = [];
$success = [];

// =============================================================================
// 1. CREATE OR UPDATE ANNOUNCEMENTS TABLE
// =============================================================================

echo "<h2>1. Setting up ANNOUNCEMENTS table...</h2>";

// Drop and recreate for clean setup (WARNING: This deletes all data!)
// Comment out these lines if you want to preserve existing data
// $conn->query("DROP TABLE IF EXISTS announcements");

$create_announcements = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('announcement', 'guideline', 'reminder', 'alert') DEFAULT 'announcement',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    is_pinned TINYINT(1) DEFAULT 0,
    images JSON,
    attachments JSON,
    likes_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_created_at (created_at),
    INDEX idx_is_pinned (is_pinned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_announcements)) {
    echo "<div class='success'>✓ Announcements table created/verified</div>";
    $success[] = "Announcements table";
} else {
    echo "<div class='error'>✗ Error creating announcements table: " . $conn->error . "</div>";
    $errors[] = "Announcements table";
}

// Add foreign key if not exists
$fk_check = $conn->query("SELECT CONSTRAINT_NAME FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                          WHERE TABLE_NAME = 'announcements' 
                          AND CONSTRAINT_NAME LIKE '%user_id%'
                          AND TABLE_SCHEMA = DATABASE()");
if ($fk_check && $fk_check->num_rows == 0) {
    $add_fk = "ALTER TABLE announcements 
               ADD CONSTRAINT fk_announcements_user 
               FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE";
    if ($conn->query($add_fk)) {
        echo "<div class='success'>✓ Foreign key constraint added</div>";
    } else {
        echo "<div class='error'>✗ Error adding foreign key: " . $conn->error . "</div>";
    }
}

// =============================================================================
// 2. MIGRATE EXISTING DATA (Add missing columns to existing table)
// =============================================================================

echo "<h2>2. Migrating existing data (if any)...</h2>";

$check_columns = $conn->query("SHOW COLUMNS FROM announcements");
if ($check_columns) {
    $existing_columns = [];
    while ($row = $check_columns->fetch_assoc()) {
        $existing_columns[] = $row['Field'];
    }
    
    $columns_to_add = [
        ['name' => 'user_id', 'definition' => 'INT NOT NULL DEFAULT 1', 'after' => 'id'],
        ['name' => 'is_pinned', 'definition' => 'TINYINT(1) DEFAULT 0', 'after' => 'status'],
        ['name' => 'images', 'definition' => 'JSON', 'after' => 'is_pinned'],
        ['name' => 'attachments', 'definition' => 'JSON', 'after' => 'images'],
        ['name' => 'likes_count', 'definition' => 'INT DEFAULT 0', 'after' => 'attachments'],
        ['name' => 'shares_count', 'definition' => 'INT DEFAULT 0', 'after' => 'likes_count'],
        ['name' => 'comments_count', 'definition' => 'INT DEFAULT 0', 'after' => 'shares_count']
    ];
    
    foreach ($columns_to_add as $column) {
        if (!in_array($column['name'], $existing_columns)) {
            // Check if the 'after' column exists before using it
            if (in_array($column['after'], $existing_columns)) {
                $sql = "ALTER TABLE announcements ADD COLUMN {$column['name']} {$column['definition']} AFTER {$column['after']}";
            } else {
                $sql = "ALTER TABLE announcements ADD COLUMN {$column['name']} {$column['definition']}";
            }
            
            if ($conn->query($sql)) {
                echo "<div class='success'>✓ Added column: {$column['name']}</div>";
            } else {
                echo "<div class='error'>✗ Error adding column {$column['name']}: " . $conn->error . "</div>";
            }
        } else {
            echo "<div class='info'>ℹ Column '{$column['name']}' already exists</div>";
        }
    }
    
    // Update ENUM values
    $conn->query("ALTER TABLE announcements MODIFY COLUMN type ENUM('announcement', 'guideline', 'reminder', 'alert') DEFAULT 'announcement'");
    $conn->query("ALTER TABLE announcements MODIFY COLUMN priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium'");
    echo "<div class='success'>✓ ENUM values updated</div>";
}

// =============================================================================
// 3. CREATE ANNOUNCEMENT_LIKES TABLE
// =============================================================================

echo "<h2>3. Setting up ANNOUNCEMENT_LIKES table...</h2>";

$create_likes = "CREATE TABLE IF NOT EXISTS announcement_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    post_type ENUM('announcement', 'food_donation') NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_like (post_id, post_type, user_id),
    INDEX idx_post (post_id, post_type),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_likes)) {
    echo "<div class='success'>✓ Announcement_likes table created/verified</div>";
    $success[] = "Announcement_likes table";
} else {
    echo "<div class='error'>✗ Error creating announcement_likes table: " . $conn->error . "</div>";
    $errors[] = "Announcement_likes table";
}

// =============================================================================
// 4. CREATE ANNOUNCEMENT_COMMENTS TABLE
// =============================================================================

echo "<h2>4. Setting up ANNOUNCEMENT_COMMENTS table...</h2>";

$create_comments = "CREATE TABLE IF NOT EXISTS announcement_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    post_type ENUM('announcement', 'food_donation') NOT NULL,
    user_id INT NOT NULL,
    comment TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post (post_id, post_type),
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_comments)) {
    echo "<div class='success'>✓ Announcement_comments table created/verified</div>";
    $success[] = "Announcement_comments table";
} else {
    echo "<div class='error'>✗ Error creating announcement_comments table: " . $conn->error . "</div>";
    $errors[] = "Announcement_comments table";
}

// =============================================================================
// 5. CREATE ANNOUNCEMENT_SHARES TABLE
// =============================================================================

echo "<h2>5. Setting up ANNOUNCEMENT_SHARES table...</h2>";

$create_shares = "CREATE TABLE IF NOT EXISTS announcement_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    post_type ENUM('announcement', 'food_donation') NOT NULL,
    user_id INT NOT NULL,
    share_message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_post (post_id, post_type),
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_shares)) {
    echo "<div class='success'>✓ Announcement_shares table created/verified</div>";
    $success[] = "Announcement_shares table";
} else {
    echo "<div class='error'>✗ Error creating announcement_shares table: " . $conn->error . "</div>";
    $errors[] = "Announcement_shares table";
}

// =============================================================================
// 6. CREATE ANNOUNCEMENT_SAVES TABLE
// =============================================================================

echo "<h2>6. Setting up ANNOUNCEMENT_SAVES table...</h2>";

$create_saves = "CREATE TABLE IF NOT EXISTS announcement_saves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    post_type ENUM('announcement', 'food_donation') NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_save (post_id, post_type, user_id),
    INDEX idx_post (post_id, post_type),
    INDEX idx_user (user_id),
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

if ($conn->query($create_saves)) {
    echo "<div class='success'>✓ Announcement_saves table created/verified</div>";
    $success[] = "Announcement_saves table";
} else {
    echo "<div class='error'>✗ Error creating announcement_saves table: " . $conn->error . "</div>";
    $errors[] = "Announcement_saves table";
}

// =============================================================================
// 7. CREATE UPLOAD DIRECTORIES
// =============================================================================

echo "<h2>7. Setting up upload directories...</h2>";

$upload_dirs = [
    '../uploads/announcements/images',
    '../uploads/announcements/files'
];

foreach ($upload_dirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0777, true)) {
            echo "<div class='success'>✓ Created directory: $dir</div>";
        } else {
            echo "<div class='error'>✗ Failed to create directory: $dir</div>";
        }
    } else {
        echo "<div class='info'>ℹ Directory already exists: $dir</div>";
    }
}

// =============================================================================
// SUMMARY
// =============================================================================

echo "<h2>Setup Summary</h2>";

if (count($errors) === 0) {
    echo "<div class='success'>
        <h3>✓ Setup Completed Successfully!</h3>
        <p>All tables have been created/updated successfully.</p>
        <ul>
            <li>✓ Announcements table</li>
            <li>✓ Announcement_likes table</li>
            <li>✓ Announcement_comments table</li>
            <li>✓ Announcement_shares table</li>
            <li>✓ Announcement_saves table</li>
            <li>✓ Upload directories</li>
        </ul>
        <p><strong>You can now use the announcements system!</strong></p>
    </div>";
} else {
    echo "<div class='error'>
        <h3>✗ Setup Completed with Errors</h3>
        <p>Some tables failed to create. Please check the errors above.</p>
        <p><strong>Failed items:</strong></p>
        <ul>";
    foreach ($errors as $error) {
        echo "<li>$error</li>";
    }
    echo "</ul>
        <p>Please fix these errors and run the setup again.</p>
    </div>";
}

echo "<a href='announcements.php' class='btn'>Go to Announcements</a>";
echo "<br><br><p><em>Note: You can run this setup script multiple times safely. It will only add missing tables/columns without deleting existing data.</em></p>";

echo "</body></html>";

$conn->close();
?>


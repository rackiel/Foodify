<?php
// Simple upload test script
session_start();

if (!isset($_SESSION['user_id'])) {
    die("Please log in first");
}

echo "<h2>Profile Picture Upload Test</h2>";

// Check upload directory
$upload_dir = __DIR__ . '/../uploads/profile_picture/';
echo "<p><strong>Upload Directory:</strong> " . $upload_dir . "</p>";
echo "<p><strong>Directory Exists:</strong> " . (is_dir($upload_dir) ? "✅ Yes" : "❌ No") . "</p>";
echo "<p><strong>Directory Writable:</strong> " . (is_writable($upload_dir) ? "✅ Yes" : "❌ No") . "</p>";

// Check PHP upload settings
echo "<h3>PHP Upload Settings:</h3>";
echo "<p>upload_max_filesize: " . ini_get('upload_max_filesize') . "</p>";
echo "<p>post_max_size: " . ini_get('post_max_size') . "</p>";
echo "<p>max_file_uploads: " . ini_get('max_file_uploads') . "</p>";
echo "<p>file_uploads: " . (ini_get('file_uploads') ? "✅ Enabled" : "❌ Disabled") . "</p>";

// Test upload form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['test_file'])) {
    echo "<h3>Upload Test Result:</h3>";
    $file = $_FILES['test_file'];
    
    echo "<p>File Name: " . htmlspecialchars($file['name']) . "</p>";
    echo "<p>File Size: " . $file['size'] . " bytes</p>";
    echo "<p>File Type: " . $file['type'] . "</p>";
    echo "<p>Temp File: " . $file['tmp_name'] . "</p>";
    echo "<p>Error Code: " . $file['error'] . "</p>";
    echo "<p>Temp File Exists: " . (file_exists($file['tmp_name']) ? "✅ Yes" : "❌ No") . "</p>";
    
    if ($file['error'] === 0) {
        $new_filename = 'test_' . uniqid() . '.jpg';
        $upload_path = $upload_dir . $new_filename;
        
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            echo "<p style='color:green;'><strong>✅ SUCCESS!</strong> File uploaded to: " . $upload_path . "</p>";
            echo "<p>File exists: " . (file_exists($upload_path) ? "✅ Yes" : "❌ No") . "</p>";
            // Clean up test file
            if (file_exists($upload_path)) {
                unlink($upload_path);
                echo "<p>Test file cleaned up.</p>";
            }
        } else {
            echo "<p style='color:red;'><strong>❌ FAILED!</strong> Could not move uploaded file.</p>";
            echo "<p>Error: " . (error_get_last()['message'] ?? 'Unknown') . "</p>";
        }
    } else {
        echo "<p style='color:red;'><strong>❌ Upload Error!</strong> Code: " . $file['error'] . "</p>";
    }
}
?>

<h3>Test Upload:</h3>
<form method="POST" enctype="multipart/form-data">
    <input type="file" name="test_file" accept="image/*" required>
    <button type="submit">Test Upload</button>
</form>

<hr>
<a href="edit_profile.php">← Back to Edit Profile</a>


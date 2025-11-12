<?php
/**
 * Upload Configuration Test Script
 * Run this file to check if your server is properly configured for file uploads
 */

// Test 1: Check upload directory
$upload_dir = __DIR__ . '/uploads/profile_picture/';
echo "<h2>Upload Directory Test</h2>";
echo "<p><strong>Path:</strong> " . $upload_dir . "</p>";

if (!is_dir($upload_dir)) {
    echo "<p style='color: red;'>❌ Directory does not exist. Creating...</p>";
    if (mkdir($upload_dir, 0777, true)) {
        echo "<p style='color: green;'>✓ Directory created successfully!</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to create directory. Check parent directory permissions.</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Directory exists</p>";
}

if (is_writable($upload_dir)) {
    echo "<p style='color: green;'>✓ Directory is writable</p>";
} else {
    echo "<p style='color: red;'>❌ Directory is NOT writable. Run this command:</p>";
    echo "<pre>chmod 777 " . $upload_dir . "</pre>";
}

// Test 2: Check PHP upload settings
echo "<h2>PHP Upload Configuration</h2>";
echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
echo "<tr><th>Setting</th><th>Value</th><th>Status</th></tr>";

$upload_settings = [
    'file_uploads' => [
        'value' => ini_get('file_uploads'),
        'good' => '1',
        'name' => 'File Uploads Enabled'
    ],
    'upload_max_filesize' => [
        'value' => ini_get('upload_max_filesize'),
        'good' => '2M',
        'name' => 'Max Upload Size'
    ],
    'post_max_size' => [
        'value' => ini_get('post_max_size'),
        'good' => '8M',
        'name' => 'Max POST Size'
    ],
    'max_file_uploads' => [
        'value' => ini_get('max_file_uploads'),
        'good' => '20',
        'name' => 'Max File Uploads'
    ],
    'upload_tmp_dir' => [
        'value' => ini_get('upload_tmp_dir') ?: sys_get_temp_dir(),
        'good' => 'writable',
        'name' => 'Temp Upload Directory'
    ]
];

foreach ($upload_settings as $key => $setting) {
    $status = "✓";
    $color = "green";
    
    if ($key === 'file_uploads' && $setting['value'] != '1') {
        $status = "❌";
        $color = "red";
    }
    
    echo "<tr>";
    echo "<td>{$setting['name']}</td>";
    echo "<td>{$setting['value']}</td>";
    echo "<td style='color: {$color};'>{$status}</td>";
    echo "</tr>";
}
echo "</table>";

// Test 3: Test file creation
echo "<h2>File Creation Test</h2>";
$test_file = $upload_dir . 'test_' . time() . '.txt';
if (file_put_contents($test_file, 'test content')) {
    echo "<p style='color: green;'>✓ Successfully created test file: " . basename($test_file) . "</p>";
    if (unlink($test_file)) {
        echo "<p style='color: green;'>✓ Successfully deleted test file</p>";
    }
} else {
    echo "<p style='color: red;'>❌ Failed to create test file. Check directory permissions.</p>";
}

// Test 4: Check GD library for image processing
echo "<h2>Image Processing Support</h2>";
if (extension_loaded('gd')) {
    echo "<p style='color: green;'>✓ GD Library is installed</p>";
    $gd_info = gd_info();
    echo "<ul>";
    echo "<li>JPEG Support: " . ($gd_info['JPEG Support'] ? '✓' : '❌') . "</li>";
    echo "<li>PNG Support: " . ($gd_info['PNG Support'] ? '✓' : '❌') . "</li>";
    echo "<li>GIF Support: " . ($gd_info['GIF Read Support'] ? '✓' : '❌') . "</li>";
    echo "</ul>";
} else {
    echo "<p style='color: orange;'>⚠ GD Library is not installed (optional, but recommended)</p>";
}

echo "<hr>";
echo "<h3>Summary</h3>";
echo "<p>If all tests pass, your server should be able to handle file uploads.</p>";
echo "<p>If you see any red ❌ marks, you need to fix those issues before uploads will work.</p>";
echo "<p style='color: red; font-weight: bold;'>⚠ DELETE THIS FILE after testing for security reasons!</p>";
?>


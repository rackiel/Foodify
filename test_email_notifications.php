<?php
// Test script for email notifications
include 'teamofficer/email_notifications.php';

// Test data
$test_donation = [
    'id' => 1,
    'title' => 'Fresh Vegetable Medley',
    'description' => 'A variety of fresh vegetables including carrots, broccoli, and bell peppers. Perfect for healthy cooking!',
    'food_type' => 'raw',
    'quantity' => '2 kg',
    'expiration_date' => '2024-01-15',
    'location_address' => '123 Main Street, City, State 12345',
    'location_lat' => '40.7128',
    'location_lng' => '-74.0060',
    'pickup_time_start' => '09:00',
    'pickup_time_end' => '17:00',
    'contact_method' => 'phone',
    'contact_info' => '+1-555-123-4567',
    'dietary_info' => 'Vegetarian, Vegan',
    'allergens' => 'None',
    'storage_instructions' => 'Keep refrigerated',
    'approval_status' => 'pending',
    'created_at' => '2024-01-10 10:30:00',
    'views_count' => 0
];

$test_donor_email = 'test@example.com';
$test_donor_name = 'John Doe';

echo "<h2>Testing Email Notifications</h2>";

try {
    $emailNotifier = new DonationEmailNotifications();
    
    echo "<h3>1. Testing Approval Email</h3>";
    $result1 = $emailNotifier->sendDonationApproved($test_donation, $test_donor_email, $test_donor_name);
    echo $result1 ? "✅ Approval email sent successfully!" : "❌ Failed to send approval email";
    
    echo "<h3>2. Testing Rejection Email</h3>";
    $result2 = $emailNotifier->sendDonationRejected($test_donation, $test_donor_email, $test_donor_name, "Please provide more specific pickup location details.");
    echo $result2 ? "✅ Rejection email sent successfully!" : "❌ Failed to send rejection email";
    
    echo "<h3>3. Testing Deletion Email</h3>";
    $result3 = $emailNotifier->sendDonationDeleted($test_donation, $test_donor_email, $test_donor_name, "Donation expired and was automatically removed.");
    echo $result3 ? "✅ Deletion email sent successfully!" : "❌ Failed to send deletion email";
    
    echo "<h3>Test Summary</h3>";
    echo "<ul>";
    echo "<li>Approval Email: " . ($result1 ? "✅ Success" : "❌ Failed") . "</li>";
    echo "<li>Rejection Email: " . ($result2 ? "✅ Success" : "❌ Failed") . "</li>";
    echo "<li>Deletion Email: " . ($result3 ? "✅ Success" : "❌ Failed") . "</li>";
    echo "</ul>";
    
    if ($result1 && $result2 && $result3) {
        echo "<p style='color: green; font-weight: bold;'>🎉 All email tests passed! Email notifications are working correctly.</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>⚠️ Some email tests failed. Check your SMTP configuration.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Note:</strong> This test sends emails to test@example.com. Check your email inbox to verify the emails were received.</p>";
echo "<p><strong>SMTP Configuration:</strong> Using Gmail SMTP (smtp.gmail.com:587)</p>";
?>

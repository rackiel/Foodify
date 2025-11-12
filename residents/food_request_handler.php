<?php
include '../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to request food.']);
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $donation_id = intval($_POST['donation_id']);
    $requester_id = $_SESSION['user_id'];
    
    try {
        switch ($action) {
            case 'request':
                $message = trim($_POST['message']);
                $contact_info = trim($_POST['contact_info']);
                
                // Validate input
                if (empty($message)) {
                    echo json_encode(['success' => false, 'message' => 'Please provide a message for your request.']);
                    exit;
                }
                
                if (empty($contact_info)) {
                    echo json_encode(['success' => false, 'message' => 'Please provide your contact information.']);
                    exit;
                }
                
                // Check if donation exists and is available
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email 
                    FROM food_donations fd 
                    JOIN user_accounts ua ON fd.user_id = ua.user_id 
                    WHERE fd.id = ? AND fd.approval_status = 'approved'
                ");
                $stmt->bind_param('i', $donation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $donation = $result->fetch_assoc();
                $stmt->close();
                
                if (!$donation) {
                    echo json_encode(['success' => false, 'message' => 'Donation not found or not available.']);
                    exit;
                }
                
                // Check if user is trying to request their own donation
                if ($donation['user_id'] == $requester_id) {
                    echo json_encode(['success' => false, 'message' => 'You cannot request your own donation.']);
                    exit;
                }
                
                // Check if user already has a pending request for this donation
                $stmt = $conn->prepare("
                    SELECT id FROM food_donation_reservations 
                    WHERE donation_id = ? AND requester_id = ? AND status IN ('pending', 'approved')
                ");
                $stmt->bind_param('ii', $donation_id, $requester_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $existing_request = $result->fetch_assoc();
                $stmt->close();
                
                if ($existing_request) {
                    echo json_encode(['success' => false, 'message' => 'You already have a pending or approved request for this donation.']);
                    exit;
                }
                
                // Create new request
                $stmt = $conn->prepare("
                    INSERT INTO food_donation_reservations (donation_id, requester_id, message, contact_info, status) 
                    VALUES (?, ?, ?, ?, 'pending')
                ");
                $stmt->bind_param('iiss', $donation_id, $requester_id, $message, $contact_info);
                
                if ($stmt->execute()) {
                    $request_id = $conn->insert_id;
                    
                    // Send email notification to donor
                    include '../teamofficer/email_notifications.php';
                    $emailNotifier = new DonationEmailNotifications();
                    
                    // Create a simple notification email for the donor
                    $donor_email = $donation['email'];
                    $donor_name = $donation['full_name'];
                    $requester_name = $_SESSION['full_name'] ?? 'A community member';
                    
                    // Send request notification email
                    $emailSent = $emailNotifier->sendRequestNotification($donation, $donor_email, $donor_name, $requester_name, $message, $contact_info);
                    
                    $response_message = 'Food request submitted successfully!';
                    if ($emailSent) {
                        $response_message .= ' The donor has been notified via email.';
                    } else {
                        $response_message .= ' Note: Email notification could not be sent to the donor.';
                    }
                    
                    echo json_encode([
                        'success' => true, 
                        'message' => $response_message,
                        'request_id' => $request_id
                    ]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to submit request. Please try again.']);
                }
                $stmt->close();
                break;
                
            case 'cancel':
                $request_id = intval($_POST['request_id']);
                
                // Check if request belongs to user
                $stmt = $conn->prepare("
                    SELECT id, status FROM food_donation_reservations 
                    WHERE id = ? AND requester_id = ?
                ");
                $stmt->bind_param('ii', $request_id, $requester_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $request = $result->fetch_assoc();
                $stmt->close();
                
                if (!$request) {
                    echo json_encode(['success' => false, 'message' => 'Request not found.']);
                    exit;
                }
                
                if ($request['status'] !== 'pending') {
                    echo json_encode(['success' => false, 'message' => 'Only pending requests can be cancelled.']);
                    exit;
                }
                
                // Cancel the request
                $stmt = $conn->prepare("
                    UPDATE food_donation_reservations 
                    SET status = 'cancelled' 
                    WHERE id = ? AND requester_id = ?
                ");
                $stmt->bind_param('ii', $request_id, $requester_id);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Request cancelled successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to cancel request.']);
                }
                $stmt->close();
                break;
                
            case 'get_my_requests':
                // Get user's requests
                $stmt = $conn->prepare("
                    SELECT fdr.*, fd.title, fd.description, fd.food_type, fd.quantity, 
                           fd.location_address, fd.pickup_time_start, fd.pickup_time_end,
                           donor.full_name as donor_name, donor.email as donor_email
                    FROM food_donation_reservations fdr
                    JOIN food_donations fd ON fdr.donation_id = fd.id
                    JOIN user_accounts donor ON fd.user_id = donor.user_id
                    WHERE fdr.requester_id = ?
                    ORDER BY fdr.reserved_at DESC
                ");
                $stmt->bind_param('i', $requester_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $requests = $result->fetch_all(MYSQLI_ASSOC);
                $stmt->close();
                
                echo json_encode(['success' => true, 'requests' => $requests]);
                break;
                
            case 'view_donation':
                // Get donation details and increment view count
                $stmt = $conn->prepare("
                    SELECT fd.*, ua.full_name, ua.email, ua.phone_number
                    FROM food_donations fd 
                    JOIN user_accounts ua ON fd.user_id = ua.user_id 
                    WHERE fd.id = ? AND fd.approval_status = 'approved'
                ");
                $stmt->bind_param('i', $donation_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $donation = $result->fetch_assoc();
                $stmt->close();
                
                if ($donation) {
                    // Increment view count
                    $stmt = $conn->prepare("UPDATE food_donations SET views_count = views_count + 1 WHERE id = ?");
                    $stmt->bind_param('i', $donation_id);
                    $stmt->execute();
                    $stmt->close();
                    
                    echo json_encode(['success' => true, 'donation' => $donation]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Donation not found or not available.']);
                }
                break;
                
            default:
                echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// If not a POST request, redirect
header('Location: browse_donations.php');
exit;
?>

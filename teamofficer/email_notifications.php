<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once __DIR__ . '/../vendor/autoload.php';

class DonationEmailNotifications {
    private $mail;
    private $from_email = 'docvic.santiago@gmail.com';
    private $from_name = 'Foodify Team';
    
    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->setupSMTP();
    }
    
    private function setupSMTP() {
        try {
            // Server settings
            $this->mail->isSMTP();
            $this->mail->Host = 'smtp.gmail.com';
            $this->mail->SMTPAuth = true;
            $this->mail->Username = $this->from_email;
            $this->mail->Password = 'zyzphvfzxadjmems';
            $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mail->Port = 587;
            
            // Recipients
            $this->mail->setFrom($this->from_email, $this->from_name);
            $this->mail->isHTML(true);
        } catch (Exception $e) {
            error_log("Email setup failed: " . $e->getMessage());
        }
    }
    
    public function sendDonationApproved($donation, $donor_email, $donor_name) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($donor_email, $donor_name);
            $this->mail->Subject = '🎉 Your Food Donation Has Been Approved!';
            
            $this->mail->Body = $this->getApprovedEmailTemplate($donation, $donor_name);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Approval email failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendDonationRejected($donation, $donor_email, $donor_name, $rejection_reason) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($donor_email, $donor_name);
            $this->mail->Subject = '❌ Food Donation Update - Requires Attention';
            
            $this->mail->Body = $this->getRejectedEmailTemplate($donation, $donor_name, $rejection_reason);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Rejection email failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendDonationDeleted($donation, $donor_email, $donor_name, $deletion_reason = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($donor_email, $donor_name);
            $this->mail->Subject = '🗑️ Your Food Donation Has Been Removed';
            
            $this->mail->Body = $this->getDeletedEmailTemplate($donation, $donor_name, $deletion_reason);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Deletion email failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendRequestNotification($donation, $donor_email, $donor_name, $requester_name, $message, $contact_info) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($donor_email, $donor_name);
            $this->mail->Subject = '🍽️ New Food Request - ' . $donation['title'];
            
            $this->mail->Body = $this->getRequestNotificationTemplate($donation, $donor_name, $requester_name, $message, $contact_info);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Request notification email failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function getApprovedEmailTemplate($donation, $donor_name) {
        $expiration_date = $donation['expiration_date'] ? date('M d, Y', strtotime($donation['expiration_date'])) : 'Not specified';
        $pickup_times = '';
        if ($donation['pickup_time_start'] && $donation['pickup_time_end']) {
            $pickup_times = "Available for pickup: {$donation['pickup_time_start']} - {$donation['pickup_time_end']}";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Donation Approved</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .donation-card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .status-badge { background: #28a745; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; display: inline-block; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #495057; }
                .footer { text-align: center; margin-top: 30px; color: #6c757d; font-size: 14px; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Donation Approved!</h1>
                    <p>Your food donation is now live and available to the community</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$donor_name}</strong>,</p>
                    
                    <p>Great news! Your food donation has been reviewed and approved by our team. It's now live on the Foodify platform and available for community members to view and request.</p>
                    
                    <div class='donation-card'>
                        <h3>📦 Your Donation Details</h3>
                        <div class='info-row'>
                            <span class='label'>Title:</span> {$donation['title']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Description:</span> {$donation['description']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Food Type:</span> " . ucfirst($donation['food_type']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Quantity:</span> {$donation['quantity']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Expiration Date:</span> {$expiration_date}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Location:</span> {$donation['location_address']}
                        </div>
                        " . ($pickup_times ? "<div class='info-row'><span class='label'>{$pickup_times}</span></div>" : "") . "
                        <div class='info-row'>
                            <span class='label'>Status:</span> <span class='status-badge'>✅ Approved</span>
                        </div>
                    </div>
                    
                    <p><strong>What happens next?</strong></p>
                    <ul>
                        <li>Community members can now see your donation in the browse section</li>
                        <li>You may receive contact requests from interested recipients</li>
                        <li>Please respond promptly to coordinate pickup arrangements</li>
                        <li>Update your donation if pickup times or details change</li>
                    </ul>
                    
                    <p>Thank you for contributing to our community and helping reduce food waste! 🌱</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/foodify/residents/browse_donations.php' class='btn'>View All Donations</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Foodify. Please do not reply to this email.</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getRejectedEmailTemplate($donation, $donor_name, $rejection_reason) {
        $expiration_date = $donation['expiration_date'] ? date('M d, Y', strtotime($donation['expiration_date'])) : 'Not specified';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Donation Rejected</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #dc3545, #fd7e14); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .donation-card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .status-badge { background: #dc3545; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; display: inline-block; }
                .rejection-reason { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #495057; }
                .footer { text-align: center; margin-top: 30px; color: #6c757d; font-size: 14px; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>❌ Donation Update Required</h1>
                    <p>Your food donation needs some adjustments before it can be approved</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$donor_name}</strong>,</p>
                    
                    <p>Thank you for your food donation submission. After review by our team, we need to ask for some adjustments before we can approve your donation for the community.</p>
                    
                    <div class='donation-card'>
                        <h3>📦 Your Donation Details</h3>
                        <div class='info-row'>
                            <span class='label'>Title:</span> {$donation['title']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Description:</span> {$donation['description']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Food Type:</span> " . ucfirst($donation['food_type']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Quantity:</span> {$donation['quantity']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Expiration Date:</span> {$expiration_date}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Status:</span> <span class='status-badge'>❌ Needs Review</span>
                        </div>
                    </div>
                    
                    <div class='rejection-reason'>
                        <h4>📝 Reason for Review:</h4>
                        <p><strong>{$rejection_reason}</strong></p>
                    </div>
                    
                    <p><strong>What you can do:</strong></p>
                    <ul>
                        <li>Review the feedback above and make necessary adjustments</li>
                        <li>Submit a new donation with the corrected information</li>
                        <li>Contact our support team if you need clarification</li>
                        <li>Ensure all required fields are properly filled</li>
                    </ul>
                    
                    <p>We appreciate your understanding and look forward to approving your donation once the necessary changes are made.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/foodify/residents/post_excess_food.php' class='btn'>Submit New Donation</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Foodify. Please do not reply to this email.</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getDeletedEmailTemplate($donation, $donor_name, $deletion_reason) {
        $expiration_date = $donation['expiration_date'] ? date('M d, Y', strtotime($donation['expiration_date'])) : 'Not specified';
        $reason_text = $deletion_reason ? "<p><strong>Reason:</strong> {$deletion_reason}</p>" : "";
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Donation Removed</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #6c757d, #495057); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .donation-card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .status-badge { background: #6c757d; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; display: inline-block; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #495057; }
                .footer { text-align: center; margin-top: 30px; color: #6c757d; font-size: 14px; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🗑️ Donation Removed</h1>
                    <p>Your food donation has been removed from the platform</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$donor_name}</strong>,</p>
                    
                    <p>This is to inform you that your food donation has been removed from the Foodify platform.</p>
                    
                    <div class='donation-card'>
                        <h3>📦 Removed Donation Details</h3>
                        <div class='info-row'>
                            <span class='label'>Title:</span> {$donation['title']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Description:</span> {$donation['description']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Food Type:</span> " . ucfirst($donation['food_type']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Quantity:</span> {$donation['quantity']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Expiration Date:</span> {$expiration_date}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Status:</span> <span class='status-badge'>🗑️ Removed</span>
                        </div>
                        {$reason_text}
                    </div>
                    
                    <p><strong>What this means:</strong></p>
                    <ul>
                        <li>Your donation is no longer visible to community members</li>
                        <li>No further pickup requests will be received</li>
                        <li>You can submit a new donation if you have more food to share</li>
                    </ul>
                    
                    <p>If you have any questions about this action, please contact our support team.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/foodify/residents/post_excess_food.php' class='btn'>Submit New Donation</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Foodify. Please do not reply to this email.</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    public function sendRequestApproved($request, $requester_email, $requester_name) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($requester_email, $requester_name);
            $this->mail->Subject = '✅ Your Food Request Has Been Approved!';
            
            $this->mail->Body = $this->getRequestApprovedTemplate($request, $requester_name);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Request approval email failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendRequestRejected($request, $requester_email, $requester_name, $admin_notes = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($requester_email, $requester_name);
            $this->mail->Subject = '❌ Your Food Request Has Been Rejected';
            
            $this->mail->Body = $this->getRequestRejectedTemplate($request, $requester_name, $admin_notes);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Request rejection email failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendRequestCompleted($request, $requester_email, $requester_name) {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($requester_email, $requester_name);
            $this->mail->Subject = '🎉 Your Food Request Has Been Completed!';
            
            $this->mail->Body = $this->getRequestCompletedTemplate($request, $requester_name);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Request completion email failed: " . $e->getMessage());
            return false;
        }
    }
    
    public function sendDonationAssigned($donation, $resident_email, $resident_name, $assignment_notes = '') {
        try {
            $this->mail->clearAddresses();
            $this->mail->addAddress($resident_email, $resident_name);
            $this->mail->Subject = '🎁 Food Donation Assigned to You!';
            
            $this->mail->Body = $this->getDonationAssignedTemplate($donation, $resident_name, $assignment_notes);
            
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Assignment email failed: " . $e->getMessage());
            return false;
        }
    }

    private function getRequestNotificationTemplate($donation, $donor_name, $requester_name, $message, $contact_info) {
        $expiration_date = $donation['expiration_date'] ? date('M d, Y', strtotime($donation['expiration_date'])) : 'Not specified';
        $pickup_times = '';
        if ($donation['pickup_time_start'] && $donation['pickup_time_end']) {
            $pickup_times = "Available for pickup: {$donation['pickup_time_start']} - {$donation['pickup_time_end']}";
        }
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>New Food Request</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .donation-card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .request-card { background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #2196f3; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #495057; }
                .footer { text-align: center; margin-top: 30px; color: #6c757d; font-size: 14px; }
                .btn { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
                .btn-secondary { background: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🍽️ New Food Request!</h1>
                    <p>Someone is interested in your food donation</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$donor_name}</strong>,</p>
                    
                    <p>Great news! <strong>{$requester_name}</strong> is interested in your food donation and would like to arrange a pickup.</p>
                    
                    <div class='donation-card'>
                        <h3>📦 Your Donation Details</h3>
                        <div class='info-row'>
                            <span class='label'>Title:</span> {$donation['title']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Description:</span> {$donation['description']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Food Type:</span> " . ucfirst($donation['food_type']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Quantity:</span> {$donation['quantity']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Expiration Date:</span> {$expiration_date}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Location:</span> {$donation['location_address']}
                        </div>
                        " . ($pickup_times ? "<div class='info-row'><span class='label'>{$pickup_times}</span></div>" : "") . "
                    </div>
                    
                    <div class='request-card'>
                        <h3>💬 Request Details</h3>
                        <div class='info-row'>
                            <span class='label'>Requester:</span> {$requester_name}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Contact Info:</span> {$contact_info}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Message:</span><br>
                            <em>\"{$message}\"</em>
                        </div>
                    </div>
                    
                    <p><strong>What you can do:</strong></p>
                    <ul>
                        <li>Review the request details above</li>
                        <li>Contact the requester using the provided contact information</li>
                        <li>Coordinate pickup time and location</li>
                        <li>Update the request status in your dashboard</li>
                    </ul>
                    
                    <p>Please respond to this request as soon as possible to help reduce food waste and support your community!</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/foodify/residents/donation_history.php' class='btn'>Manage Requests</a>
                        <a href='http://localhost/foodify/residents/browse_donations.php' class='btn btn-secondary'>View All Donations</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Foodify. Please do not reply to this email.</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getRequestApprovedTemplate($request, $requester_name) {
        $donation_title = $request['donation_title'];
        $donor_name = $request['donor_name'];
        $message = $request['message'];
        $contact_info = $request['contact_info'];
        $reserved_at = date('M d, Y \a\t g:i A', strtotime($request['reserved_at']));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Request Approved</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .success-card { background: #d4edda; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #28a745; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #495057; }
                .footer { text-align: center; margin-top: 30px; color: #6c757d; font-size: 14px; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✅ Request Approved!</h1>
                    <p>Your food request has been approved by the donor</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$requester_name}</strong>,</p>
                    
                    <div class='success-card'>
                        <h3>🎉 Great News!</h3>
                        <p>Your request for <strong>{$donation_title}</strong> has been approved by <strong>{$donor_name}</strong>!</p>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Request Date:</span> {$reserved_at}
                    </div>
                    <div class='info-row'>
                        <span class='label'>Your Message:</span><br>
                        <em>\"{$message}\"</em>
                    </div>
                    <div class='info-row'>
                        <span class='label'>Your Contact Info:</span> {$contact_info}
                    </div>
                    
                    <p><strong>Next Steps:</strong></p>
                    <ul>
                        <li>Contact the donor using the information provided in the original donation</li>
                        <li>Coordinate a convenient pickup time and location</li>
                        <li>Follow any specific instructions from the donor</li>
                        <li>Enjoy your food and help reduce waste!</li>
                    </ul>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/foodify/residents/my_requests.php' class='btn'>View My Requests</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Foodify. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getRequestRejectedTemplate($request, $requester_name, $admin_notes) {
        $donation_title = $request['donation_title'];
        $donor_name = $request['donor_name'];
        $message = $request['message'];
        $reserved_at = date('M d, Y \a\t g:i A', strtotime($request['reserved_at']));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Request Rejected</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .rejection-card { background: #f8d7da; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #dc3545; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #495057; }
                .footer { text-align: center; margin-top: 30px; color: #6c757d; font-size: 14px; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>❌ Request Not Approved</h1>
                    <p>Your food request was not approved</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$requester_name}</strong>,</p>
                    
                    <div class='rejection-card'>
                        <h3>Request Status Update</h3>
                        <p>Unfortunately, your request for <strong>{$donation_title}</strong> from <strong>{$donor_name}</strong> was not approved.</p>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Request Date:</span> {$reserved_at}
                    </div>
                    <div class='info-row'>
                        <span class='label'>Your Message:</span><br>
                        <em>\"{$message}\"</em>
                    </div>
                    " . ($admin_notes ? "<div class='info-row'><span class='label'>Reason:</span><br><em>{$admin_notes}</em></div>" : "") . "
                    
                    <p><strong>Don't worry!</strong> There are many other food donations available. You can:</p>
                    <ul>
                        <li>Browse other available food donations</li>
                        <li>Submit new requests for different items</li>
                        <li>Check back later for new donations</li>
                    </ul>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/foodify/residents/browse_donations.php' class='btn'>Browse Donations</a>
                        <a href='http://localhost/foodify/residents/my_requests.php' class='btn'>View My Requests</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Foodify. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getRequestCompletedTemplate($request, $requester_name) {
        $donation_title = $request['donation_title'];
        $donor_name = $request['donor_name'];
        $message = $request['message'];
        $reserved_at = date('M d, Y \a\t g:i A', strtotime($request['reserved_at']));
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Request Completed</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #17a2b8, #138496); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .completion-card { background: #d1ecf1; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #17a2b8; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #495057; }
                .footer { text-align: center; margin-top: 30px; color: #6c757d; font-size: 14px; }
                .btn { display: inline-block; background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎉 Request Completed!</h1>
                    <p>Your food request has been successfully completed</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$requester_name}</strong>,</p>
                    
                    <div class='completion-card'>
                        <h3>✅ Success!</h3>
                        <p>Your request for <strong>{$donation_title}</strong> from <strong>{$donor_name}</strong> has been marked as completed!</p>
                    </div>
                    
                    <div class='info-row'>
                        <span class='label'>Request Date:</span> {$reserved_at}
                    </div>
                    <div class='info-row'>
                        <span class='label'>Your Message:</span><br>
                        <em>\"{$message}\"</em>
                    </div>
                    
                    <p><strong>Thank you for using Foodify!</strong> You've helped reduce food waste and support your community.</p>
                    
                    <p>We hope you enjoyed your food! If you have any feedback about your experience, please let us know.</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/foodify/residents/my_requests.php' class='btn'>View My Requests</a>
                        <a href='http://localhost/foodify/residents/browse_donations.php' class='btn'>Browse More Donations</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Foodify. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    private function getDonationAssignedTemplate($donation, $resident_name, $assignment_notes) {
        $expiration_date = $donation['expiration_date'] ? date('M d, Y', strtotime($donation['expiration_date'])) : 'Not specified';
        $pickup_times = '';
        if ($donation['pickup_time_start'] && $donation['pickup_time_end']) {
            $pickup_times = "Available for pickup: {$donation['pickup_time_start']} - {$donation['pickup_time_end']}";
        }
        $notes_section = $assignment_notes ? "<div class='notes-card'><h4>📝 Assignment Notes:</h4><p><strong>{$assignment_notes}</strong></p></div>" : "";
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Donation Assigned</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
                .donation-card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                .notes-card { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
                .status-badge { background: #007bff; color: white; padding: 8px 16px; border-radius: 20px; font-weight: bold; display: inline-block; }
                .info-row { margin: 10px 0; }
                .label { font-weight: bold; color: #495057; }
                .footer { text-align: center; margin-top: 30px; color: #6c757d; font-size: 14px; }
                .btn { display: inline-block; background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🎁 Food Donation Assigned!</h1>
                    <p>A food donation has been assigned to you</p>
                </div>
                
                <div class='content'>
                    <p>Dear <strong>{$resident_name}</strong>,</p>
                    
                    <p>Great news! A team officer has assigned a food donation to you. This donation is now reserved for you to pick up.</p>
                    
                    <div class='donation-card'>
                        <h3>📦 Donation Details</h3>
                        <div class='info-row'>
                            <span class='label'>Title:</span> {$donation['title']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Description:</span> {$donation['description']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Food Type:</span> " . ucfirst($donation['food_type']) . "
                        </div>
                        <div class='info-row'>
                            <span class='label'>Quantity:</span> {$donation['quantity']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Expiration Date:</span> {$expiration_date}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Location:</span> {$donation['location_address']}
                        </div>
                        " . ($pickup_times ? "<div class='info-row'><span class='label'>{$pickup_times}</span></div>" : "") . "
                        <div class='info-row'>
                            <span class='label'>Contact Info:</span> {$donation['contact_info']}
                        </div>
                        <div class='info-row'>
                            <span class='label'>Status:</span> <span class='status-badge'>✅ Assigned to You</span>
                        </div>
                    </div>
                    
                    {$notes_section}
                    
                    <p><strong>Next Steps:</strong></p>
                    <ul>
                        <li>Contact the donor using the contact information provided above</li>
                        <li>Coordinate a convenient pickup time and location</li>
                        <li>Follow any specific instructions from the donor</li>
                        <li>Enjoy your food and help reduce waste!</li>
                    </ul>
                    
                    <p>Thank you for being part of our community and helping reduce food waste! 🌱</p>
                    
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='http://localhost/foodify/residents/my_requests.php' class='btn'>View My Assignments</a>
                    </div>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message from Foodify. Please do not reply to this email.</p>
                    <p>If you have any questions, please contact our support team.</p>
                </div>
            </div>
        </body>
        </html>";
    }
}
?>

# Ingredient Expiration Notification System

## Overview
This system automatically monitors ingredient expiration dates and sends email notifications to users when their ingredients are about to expire (within 7 days).

## Features
- **Automatic Monitoring**: Checks all active ingredients for upcoming expiration dates
- **Email Notifications**: Sends detailed emails to users with expiring ingredients
- **Grouped by User**: Each user receives one consolidated email with all their expiring ingredients
- **Visual Indicators**: Color-coded urgency (red for 0-2 days, orange for 3-7 days)
- **Auto-Status Update**: Automatically moves expired ingredients to 'expired' status

## Files
- `residents/check_expiring_ingredients.php` - Main notification script

## Setup Instructions

### Manual Execution
Run the script manually from command line:
```bash
php residents/check_expiring_ingredients.php
```

### Automated Daily Notifications (Cron Job)

#### For Linux/Mac:
1. Open crontab editor:
```bash
crontab -e
```

2. Add this line to run daily at 8 AM:
```
0 8 * * * /usr/bin/php /path/to/foodify/residents/check_expiring_ingredients.php
```

#### For Windows (Task Scheduler):
1. Open Task Scheduler
2. Create Basic Task
3. Name: "Foodify Expiration Check"
4. Trigger: Daily at 8:00 AM
5. Action: Start a program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\foodify\residents\check_expiring_ingredients.php`

### Alternative: Web Cron
If you don't have access to server cron, you can use a web cron service (e.g., cron-job.org) to call a web-accessible version of the script.

## Email Configuration
The system uses the PHPMailer configuration from `server_mail.php`:
- SMTP Host: smtp.gmail.com
- Port: 587
- From: docvic.santiago@gmail.com

## Notification Logic
- Checks ingredients with expiration dates between today and 7 days from now
- Only sends notifications for 'active' ingredients
- Groups multiple expiring ingredients per user into one email
- Includes ingredient name, category, expiration date, and days remaining

## Notification Thresholds
- **Critical (0-2 days)**: Red text, urgent action needed
- **Warning (3-7 days)**: Orange text, plan to use soon

## Testing
To test the notification system:
1. Add ingredients with expiration dates within the next 7 days
2. Run the script manually: `php residents/check_expiring_ingredients.php`
3. Check the email inbox of the user who owns the ingredients

## Troubleshooting
- **No emails received**: Check spam folder, verify email addresses in database
- **SMTP errors**: Verify Gmail credentials and app password in `server_mail.php`
- **No ingredients found**: Ensure ingredients have expiration_date set and status='active'


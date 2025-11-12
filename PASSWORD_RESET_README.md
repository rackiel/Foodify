# Password Reset Functionality

This document describes the password reset functionality implemented for the Foodify application.

## Files Created/Modified

### New Files:
1. **forgot-password.php** - The forgot password page where users enter their email
2. **reset-password.php** - The password reset page where users enter their new password
3. **create_password_reset_table.sql** - SQL script to create the password reset tokens table
4. **setup_password_reset.php** - PHP script to run the SQL and create the table
5. **PASSWORD_RESET_README.md** - This documentation file

### Modified Files:
1. **index.php** - Updated the "Forgot password?" link to point to forgot-password.php

## Database Changes

A new table `password_reset_tokens` is created with the following structure:
- `id` - Primary key
- `user_id` - Foreign key to user_accounts table
- `token` - Unique reset token (32 characters)
- `expires_at` - Token expiration time (1 hour from creation)
- `used` - Boolean flag to mark if token has been used
- `created_at` - Timestamp when token was created

## How It Works

### 1. Forgot Password Process:
1. User clicks "Forgot password?" on the login page
2. User is redirected to `forgot-password.php`
3. User enters their email address
4. System checks if email exists and account is verified
5. If valid, a reset token is generated and stored in database
6. An email with reset link is sent using PHPMailer
7. User receives email with link to reset password

### 2. Password Reset Process:
1. User clicks the reset link in their email
2. User is redirected to `reset-password.php` with token parameter
3. System validates the token (checks if exists, not expired, not used)
4. If valid, user can enter new password
5. Password is updated in database using MD5 hash (same as existing system)
6. Token is marked as used
7. User can now login with new password

## Security Features

- **Token Expiration**: Tokens expire after 1 hour
- **Single Use**: Each token can only be used once
- **Email Verification**: Only verified accounts can reset passwords
- **Token Cleanup**: Old tokens are deleted when new ones are created
- **Input Validation**: Password confirmation and length validation
- **SQL Injection Protection**: All database queries use prepared statements

## Setup Instructions

1. **Create the database table**:
   - Run `setup_password_reset.php` in your browser, or
   - Execute the SQL in `create_password_reset_table.sql` manually

2. **Configure PHPMailer** (already done):
   - SMTP settings are in `server_mail.php`
   - Uses Gmail SMTP with app password

3. **Test the functionality**:
   - Go to the login page
   - Click "Forgot password?"
   - Enter a valid email address
   - Check email for reset link
   - Click link and reset password

## Email Template

The reset email includes:
- Foodify logo
- Personalized greeting
- Reset button with secure link
- Security notice about expiration
- Professional styling matching the app theme

## Error Handling

The system handles various error scenarios:
- Invalid email addresses
- Unverified accounts
- Expired tokens
- Used tokens
- Password mismatch
- Email sending failures
- Database errors

## Integration with Existing System

- Uses existing PHPMailer configuration
- Follows existing password hashing (MD5)
- Matches existing UI/UX design
- Uses existing database connection
- Integrates with existing user verification system

## Notes

- The system uses MD5 hashing which matches the existing authentication system
- Tokens are 32-character hexadecimal strings
- Email links are absolute URLs (http://localhost/foodify/)
- The system is designed to work with the existing user roles and verification system

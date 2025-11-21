-- Migration: Add is_verified and is_approved columns to user_accounts table
-- This ensures team officers created by admins can login without waiting for approval

-- Check if columns exist and add them if they don't
ALTER TABLE user_accounts 
ADD COLUMN IF NOT EXISTS is_verified TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS is_approved TINYINT(1) DEFAULT 0;

-- For existing admin users, set them as verified and approved
UPDATE user_accounts 
SET is_verified = 1, is_approved = 1 
WHERE role = 'admin' AND (is_verified = 0 OR is_approved = 0);

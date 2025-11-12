-- ============================================================================
-- ANNOUNCEMENTS SYSTEM - DATABASE SETUP
-- ============================================================================
-- This script creates all necessary tables for the announcements system
-- Run this file in phpMyAdmin or MySQL command line
-- ============================================================================

-- Drop existing tables if you want a clean setup (CAUTION: Deletes all data!)
-- Uncomment the lines below ONLY if you want to start fresh
-- DROP TABLE IF EXISTS announcement_saves;
-- DROP TABLE IF EXISTS announcement_shares;
-- DROP TABLE IF EXISTS announcement_comments;
-- DROP TABLE IF EXISTS announcement_likes;
-- DROP TABLE IF EXISTS announcements;

-- ============================================================================
-- 1. ANNOUNCEMENTS TABLE (Main table)
-- ============================================================================

CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type ENUM('announcement', 'guideline', 'reminder', 'alert') DEFAULT 'announcement',
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    is_pinned TINYINT(1) DEFAULT 0,
    images JSON COMMENT 'Array of image paths',
    attachments JSON COMMENT 'Array of file attachment metadata',
    likes_count INT DEFAULT 0,
    shares_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_user_id (user_id),
    INDEX idx_status (status),
    INDEX idx_type (type),
    INDEX idx_priority (priority),
    INDEX idx_created_at (created_at),
    INDEX idx_is_pinned (is_pinned),
    
    -- Foreign key constraint
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores announcements, guidelines, reminders, and alerts';

-- ============================================================================
-- 2. ANNOUNCEMENT_LIKES TABLE (Like/React system)
-- ============================================================================

CREATE TABLE IF NOT EXISTS announcement_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL COMMENT 'ID of the announcement or food donation',
    post_type ENUM('announcement', 'food_donation') NOT NULL COMMENT 'Type of post being liked',
    user_id INT NOT NULL COMMENT 'User who liked the post',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Unique constraint: one like per user per post
    UNIQUE KEY unique_like (post_id, post_type, user_id),
    
    -- Indexes for performance
    INDEX idx_post (post_id, post_type),
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at),
    
    -- Foreign key constraint
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks likes/reactions on announcements and food donations';

-- ============================================================================
-- 3. ANNOUNCEMENT_COMMENTS TABLE (Comment system)
-- ============================================================================

CREATE TABLE IF NOT EXISTS announcement_comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL COMMENT 'ID of the announcement or food donation',
    post_type ENUM('announcement', 'food_donation') NOT NULL COMMENT 'Type of post',
    user_id INT NOT NULL COMMENT 'User who commented',
    comment TEXT NOT NULL COMMENT 'Comment text',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_post (post_id, post_type),
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at),
    
    -- Foreign key constraint
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores comments on announcements and food donations';

-- ============================================================================
-- 4. ANNOUNCEMENT_SHARES TABLE (Share tracking)
-- ============================================================================

CREATE TABLE IF NOT EXISTS announcement_shares (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL COMMENT 'ID of the announcement or food donation',
    post_type ENUM('announcement', 'food_donation') NOT NULL COMMENT 'Type of post',
    user_id INT NOT NULL COMMENT 'User who shared',
    share_message TEXT COMMENT 'Optional message when sharing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_post (post_id, post_type),
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at),
    
    -- Foreign key constraint
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks shares of announcements and food donations';

-- ============================================================================
-- 5. ANNOUNCEMENT_SAVES TABLE (Bookmark system)
-- ============================================================================

CREATE TABLE IF NOT EXISTS announcement_saves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL COMMENT 'ID of the announcement or food donation',
    post_type ENUM('announcement', 'food_donation') NOT NULL COMMENT 'Type of post',
    user_id INT NOT NULL COMMENT 'User who saved/bookmarked',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Unique constraint: one save per user per post
    UNIQUE KEY unique_save (post_id, post_type, user_id),
    
    -- Indexes for performance
    INDEX idx_post (post_id, post_type),
    INDEX idx_user (user_id),
    INDEX idx_created_at (created_at),
    
    -- Foreign key constraint
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Stores saved/bookmarked posts by users';

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- Check if all tables were created successfully
SELECT 
    'announcements' as table_name, 
    COUNT(*) as record_count 
FROM announcements
UNION ALL
SELECT 'announcement_likes', COUNT(*) FROM announcement_likes
UNION ALL
SELECT 'announcement_comments', COUNT(*) FROM announcement_comments
UNION ALL
SELECT 'announcement_shares', COUNT(*) FROM announcement_shares
UNION ALL
SELECT 'announcement_saves', COUNT(*) FROM announcement_saves;

-- Show structure of announcements table
DESCRIBE announcements;

-- ============================================================================
-- SAMPLE DATA (Optional - Uncomment to insert test data)
-- ============================================================================

/*
-- Insert sample announcement
INSERT INTO announcements (user_id, title, content, type, priority, status, is_pinned) VALUES
(1, 'Welcome to the Community!', 'Welcome everyone! We are excited to have you here.', 'announcement', 'high', 'published', 1),
(1, 'Community Guidelines', 'Please read and follow these important community guidelines...', 'guideline', 'critical', 'published', 1),
(1, 'Monthly Meeting Reminder', 'Don''t forget our monthly meeting this Saturday at 10 AM.', 'reminder', 'medium', 'published', 0),
(1, 'Urgent: System Maintenance', 'The system will be down for maintenance tonight from 11 PM to 2 AM.', 'alert', 'critical', 'published', 1);
*/

-- ============================================================================
-- MIGRATION SCRIPT (For existing announcements table)
-- ============================================================================

/*
If you have an existing announcements table that's missing columns, run these:

-- Add user_id if missing
ALTER TABLE announcements ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE announcements ADD FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE;

-- Add is_pinned if missing
ALTER TABLE announcements ADD COLUMN is_pinned TINYINT(1) DEFAULT 0 AFTER status;

-- Add images column if missing
ALTER TABLE announcements ADD COLUMN images JSON AFTER is_pinned;

-- Add attachments column if missing
ALTER TABLE announcements ADD COLUMN attachments JSON AFTER images;

-- Add engagement columns if missing
ALTER TABLE announcements ADD COLUMN likes_count INT DEFAULT 0 AFTER attachments;
ALTER TABLE announcements ADD COLUMN shares_count INT DEFAULT 0 AFTER likes_count;
ALTER TABLE announcements ADD COLUMN comments_count INT DEFAULT 0 AFTER shares_count;

-- Update type ENUM to include 'alert'
ALTER TABLE announcements MODIFY COLUMN type ENUM('announcement', 'guideline', 'reminder', 'alert') DEFAULT 'announcement';

-- Update priority ENUM to include 'critical'
ALTER TABLE announcements MODIFY COLUMN priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium';
*/

-- ============================================================================
-- CLEANUP QUERIES (Use with caution!)
-- ============================================================================

/*
-- Delete old/test data
DELETE FROM announcements WHERE created_at < '2025-01-01';

-- Reset auto increment
ALTER TABLE announcements AUTO_INCREMENT = 1;

-- Clear all engagement data
UPDATE announcements SET likes_count = 0, shares_count = 0, comments_count = 0;
DELETE FROM announcement_likes;
DELETE FROM announcement_comments;
DELETE FROM announcement_shares;
DELETE FROM announcement_saves;
*/

-- ============================================================================
-- USEFUL QUERIES
-- ============================================================================

-- Get all announcements with engagement stats
SELECT 
    a.id,
    a.title,
    a.type,
    a.priority,
    a.status,
    a.is_pinned,
    a.likes_count,
    a.comments_count,
    a.shares_count,
    u.full_name as created_by,
    a.created_at
FROM announcements a
LEFT JOIN user_accounts u ON a.user_id = u.user_id
ORDER BY a.is_pinned DESC, a.created_at DESC;

-- Get announcements with image/file counts
SELECT 
    a.id,
    a.title,
    a.type,
    JSON_LENGTH(a.images) as image_count,
    JSON_LENGTH(a.attachments) as file_count
FROM announcements a
WHERE a.images IS NOT NULL OR a.attachments IS NOT NULL;

-- Get most liked announcements
SELECT 
    a.title,
    a.type,
    a.likes_count,
    u.full_name
FROM announcements a
LEFT JOIN user_accounts u ON a.user_id = u.user_id
ORDER BY a.likes_count DESC
LIMIT 10;

-- ============================================================================
-- END OF SCRIPT
-- ============================================================================


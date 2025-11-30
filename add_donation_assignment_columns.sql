-- Add columns to food_donations table for assignment functionality
-- This allows team officers to assign donations directly to residents

ALTER TABLE food_donations
ADD COLUMN IF NOT EXISTS assigned_to_user_id INT NULL,
ADD COLUMN IF NOT EXISTS assigned_at TIMESTAMP NULL,
ADD COLUMN IF NOT EXISTS assigned_by INT NULL,
ADD COLUMN IF NOT EXISTS assignment_notes TEXT NULL,
ADD FOREIGN KEY (assigned_to_user_id) REFERENCES user_accounts(user_id) ON DELETE SET NULL,
ADD FOREIGN KEY (assigned_by) REFERENCES user_accounts(user_id) ON DELETE SET NULL,
ADD INDEX idx_assigned_to_user_id (assigned_to_user_id),
ADD INDEX idx_assigned_by (assigned_by);

-- Note: IF NOT EXISTS may not work in all MySQL versions
-- If you get an error, remove the IF NOT EXISTS clause and run the ALTER TABLE statement
-- The columns will be added if they don't exist, or you'll get an error if they already exist (which is fine)


-- Fix recipe_tip_saves table structure
-- This script handles both creation and modification scenarios

-- First, check if the table exists and create it if it doesn't
CREATE TABLE IF NOT EXISTS recipe_tip_saves (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    post_type ENUM('recipe', 'tip', 'meal_plan') NOT NULL DEFAULT 'recipe',
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_save (post_id, post_type, user_id)
);

-- If the table already exists but doesn't have post_type column, add it
-- This will only run if the column doesn't exist
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'recipe_tip_saves' 
     AND COLUMN_NAME = 'post_type') = 0,
    'ALTER TABLE recipe_tip_saves ADD COLUMN post_type ENUM(''recipe'', ''tip'', ''meal_plan'') NOT NULL DEFAULT ''recipe'' AFTER post_id;',
    'SELECT ''Column post_type already exists'' as message;'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Update existing records to have the correct post_type
-- For existing saves, determine if they are recipes, tips, or meal plans
UPDATE recipe_tip_saves s 
INNER JOIN recipes_tips r ON s.post_id = r.id 
SET s.post_type = r.post_type 
WHERE s.post_id IN (SELECT id FROM recipes_tips);

-- Update meal plan saves (assuming meal plans have IDs that don't conflict with recipes_tips)
UPDATE recipe_tip_saves s 
INNER JOIN meal_plans m ON s.post_id = m.id 
SET s.post_type = 'meal_plan' 
WHERE s.post_id NOT IN (SELECT id FROM recipes_tips);

-- Add index for better performance on post_type queries
CREATE INDEX IF NOT EXISTS idx_recipe_tip_saves_post_type ON recipe_tip_saves(post_type);

-- Update the unique constraint to include post_type
-- First drop the old unique constraint if it exists
SET @sql = (SELECT IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS 
     WHERE TABLE_SCHEMA = DATABASE() 
     AND TABLE_NAME = 'recipe_tip_saves' 
     AND INDEX_NAME = 'unique_save') > 0,
    'ALTER TABLE recipe_tip_saves DROP INDEX unique_save;',
    'SELECT ''Index unique_save does not exist'' as message;'
));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add the new unique constraint with post_type
ALTER TABLE recipe_tip_saves 
ADD UNIQUE KEY unique_save (post_id, post_type, user_id);

-- Drop table if exists (to start fresh)
DROP TABLE IF EXISTS user_preferences;

-- Create user_preferences table WITHOUT foreign key constraint first
CREATE TABLE user_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Dietary Restrictions
    dietary_type VARCHAR(50) DEFAULT 'none',
    allergies TEXT,
    food_dislikes TEXT,
    
    -- Calorie & Nutrition Goals
    daily_calorie_goal INT DEFAULT 2000,
    daily_protein_goal INT DEFAULT 50,
    daily_carbs_goal INT DEFAULT 250,
    daily_fat_goal INT DEFAULT 70,
    
    -- Meal Preferences
    meals_per_day INT DEFAULT 3,
    preferred_cuisines TEXT,
    portion_size VARCHAR(20) DEFAULT 'medium',
    
    -- Notification Preferences
    email_meal_reminders TINYINT(1) DEFAULT 0,
    email_expiration_alerts TINYINT(1) DEFAULT 1,
    email_donation_updates TINYINT(1) DEFAULT 1,
    email_weekly_summary TINYINT(1) DEFAULT 0,
    
    -- Other Preferences
    default_serving_size INT DEFAULT 1,
    meal_prep_days INT DEFAULT 7,
    budget_per_week DECIMAL(10,2) DEFAULT 0.00,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_user_preference (user_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Now try to add foreign key constraint (this will fail if there's a data type mismatch, but table will still be created)
-- If this fails, comment it out and the table will work fine without the constraint
ALTER TABLE user_preferences 
ADD CONSTRAINT fk_user_preferences_user 
FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE;

-- Insert default preferences for existing users who don't have preferences yet
INSERT IGNORE INTO user_preferences (user_id)
SELECT user_id FROM user_accounts;


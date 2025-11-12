-- Create user_preferences table for storing dietary settings and preferences
CREATE TABLE IF NOT EXISTS user_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Dietary Restrictions
    dietary_type VARCHAR(50) DEFAULT 'none', -- none, vegetarian, vegan, pescatarian, halal, kosher
    allergies TEXT, -- comma-separated list of allergies
    food_dislikes TEXT, -- comma-separated list of foods to avoid
    
    -- Calorie & Nutrition Goals
    daily_calorie_goal INT DEFAULT 2000,
    daily_protein_goal INT DEFAULT 50,
    daily_carbs_goal INT DEFAULT 250,
    daily_fat_goal INT DEFAULT 70,
    
    -- Meal Preferences
    meals_per_day INT DEFAULT 3,
    preferred_cuisines TEXT, -- comma-separated: Filipino, Japanese, Italian, etc.
    portion_size VARCHAR(20) DEFAULT 'medium', -- small, medium, large
    
    -- Notification Preferences
    email_meal_reminders BOOLEAN DEFAULT 0,
    email_expiration_alerts BOOLEAN DEFAULT 1,
    email_donation_updates BOOLEAN DEFAULT 1,
    email_weekly_summary BOOLEAN DEFAULT 0,
    
    -- Other Preferences
    default_serving_size INT DEFAULT 1,
    meal_prep_days INT DEFAULT 7,
    budget_per_week DECIMAL(10,2) DEFAULT 0.00,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default preferences for existing users who don't have preferences yet
INSERT IGNORE INTO user_preferences (user_id)
SELECT user_id FROM user_accounts 
WHERE user_id NOT IN (SELECT user_id FROM user_preferences);


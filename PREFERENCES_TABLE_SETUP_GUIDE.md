# User Preferences Table Setup Guide

## Issue: Foreign Key Constraint Error

You're getting error `#1005 - Can't create table (errno: 150 "Foreign key constraint is incorrectly formed")`

This usually means:
1. **Data type mismatch** - The `user_id` in `user_preferences` doesn't match the type in `user_accounts`
2. **Missing index** - The referenced column isn't properly indexed
3. **Charset/Collation mismatch** - Tables have different character sets

---

## 🔧 Solution Options

### Option 1: Use Safe SQL (Recommended)

Use the new `create_user_preferences_table_safe.sql` file which:
- Creates the table WITHOUT foreign key first
- Adds foreign key as a separate step (will skip if fails)
- Table will work even if foreign key fails

```bash
# Import via phpMyAdmin or command line
mysql -u root -p your_database_name < create_user_preferences_table_safe.sql
```

### Option 2: Create Without Foreign Key

If Option 1 still fails, run this simplified version:

```sql
-- Copy and paste this in phpMyAdmin SQL tab
DROP TABLE IF EXISTS user_preferences;

CREATE TABLE user_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    dietary_type VARCHAR(50) DEFAULT 'none',
    allergies TEXT,
    food_dislikes TEXT,
    daily_calorie_goal INT DEFAULT 2000,
    daily_protein_goal INT DEFAULT 50,
    daily_carbs_goal INT DEFAULT 250,
    daily_fat_goal INT DEFAULT 70,
    meals_per_day INT DEFAULT 3,
    preferred_cuisines TEXT,
    portion_size VARCHAR(20) DEFAULT 'medium',
    email_meal_reminders TINYINT(1) DEFAULT 0,
    email_expiration_alerts TINYINT(1) DEFAULT 1,
    email_donation_updates TINYINT(1) DEFAULT 1,
    email_weekly_summary TINYINT(1) DEFAULT 0,
    default_serving_size INT DEFAULT 1,
    meal_prep_days INT DEFAULT 7,
    budget_per_week DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user_preference (user_id),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default preferences for existing users
INSERT IGNORE INTO user_preferences (user_id)
SELECT user_id FROM user_accounts;
```

**Note:** This works perfectly fine without the foreign key constraint. The application will still function normally.

### Option 3: Match Data Types

First, check what data type `user_id` is in `user_accounts`:

```sql
SHOW COLUMNS FROM user_accounts LIKE 'user_id';
```

Common results:
- **INT** - Use `user_id INT NOT NULL` in user_preferences
- **BIGINT** - Use `user_id BIGINT NOT NULL` in user_preferences
- **INT UNSIGNED** - Use `user_id INT UNSIGNED NOT NULL` in user_preferences

Then create the table with matching type:

```sql
DROP TABLE IF EXISTS user_preferences;

CREATE TABLE user_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT NOT NULL,  -- Change this to match user_accounts.user_id type
    -- ... rest of columns same as above
);
```

---

## 📋 Quick Check Commands

Run these in phpMyAdmin SQL tab to diagnose:

```sql
-- 1. Check if user_accounts table exists
SHOW TABLES LIKE 'user_accounts';

-- 2. Check user_id column details
SHOW COLUMNS FROM user_accounts LIKE 'user_id';

-- 3. Check user_accounts indexes
SHOW INDEXES FROM user_accounts;

-- 4. Check database name
SELECT DATABASE();
```

---

## ✅ Verify Installation

After creating the table, verify it works:

```sql
-- 1. Check table was created
SHOW TABLES LIKE 'user_preferences';

-- 2. Check structure
DESCRIBE user_preferences;

-- 3. Check if defaults were inserted
SELECT COUNT(*) FROM user_preferences;

-- 4. Test insert
INSERT INTO user_preferences (user_id) VALUES (1);
SELECT * FROM user_preferences WHERE user_id = 1;
```

---

## 🎯 What to Do Now

1. **Try Option 1 first**: Use `create_user_preferences_table_safe.sql`
2. **If that fails**: Use Option 2 (SQL without foreign key)
3. **Test the page**: Navigate to `residents/preferences.php`

The page will work correctly even without the foreign key constraint!

---

## 🐛 Still Having Issues?

If you're still having problems:

1. **Check database name**: Is it `foodify` or `foodify_db`?
2. **Check privileges**: Does your MySQL user have CREATE TABLE permission?
3. **Check InnoDB**: Make sure your MySQL supports InnoDB engine
4. **Manual creation**: Use phpMyAdmin's GUI to create the table visually

---

## 📞 Need Help?

If none of these work, please provide:
- Output of: `SHOW COLUMNS FROM user_accounts;`
- Your MySQL/MariaDB version
- Exact error message you're seeing

---

**Quick Answer:** Just run **Option 2** (the simplified SQL without foreign key). It will work perfectly! 🚀


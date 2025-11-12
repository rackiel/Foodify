# Setup Instructions - Announcements System

## 🎯 Quick Start

You have **TWO OPTIONS** to set up the database tables:

### Option 1: PHP Setup Script (Recommended) ⭐
**Easiest and safest method**

1. Open your browser
2. Navigate to: `http://localhost/foodify/teamofficer/setup_announcements_db.php`
3. The script will automatically:
   - Create all necessary tables
   - Add missing columns to existing tables
   - Preserve all your data
   - Show you a success message
4. Click "Go to Announcements" when done

**Advantages:**
- ✅ One-click setup
- ✅ Automatic migration
- ✅ Visual feedback
- ✅ Error messages if something fails
- ✅ Safe for existing data

### Option 2: SQL File (Advanced)
**For database administrators**

1. Open phpMyAdmin
2. Select your `foodify` database
3. Click on "SQL" tab
4. Open file: `create_announcements_tables.sql`
5. Copy all contents
6. Paste into SQL query box
7. Click "Go"
8. Check for success messages

**Advantages:**
- ✅ Full control
- ✅ Can review before running
- ✅ Can customize as needed
- ✅ Standard SQL approach

## 📋 What Gets Created

### 5 Database Tables:

1. **`announcements`** (Main table)
   - Stores all announcements, guidelines, reminders, alerts
   - Includes images and attachments (JSON)
   - Engagement counters (likes, comments, shares)

2. **`announcement_likes`**
   - Tracks who liked which posts
   - Prevents duplicate likes
   - Works for announcements and food donations

3. **`announcement_comments`**
   - Stores all comments on posts
   - Links to user accounts
   - Sortable by date

4. **`announcement_shares`**
   - Tracks post shares
   - Stores optional share messages
   - Analytics ready

5. **`announcement_saves`**
   - Bookmark system
   - Prevents duplicate saves
   - Personal collections

### 2 Upload Directories:

- `uploads/announcements/images/` - For uploaded images
- `uploads/announcements/files/` - For file attachments

## 🔧 If You Already Have an Announcements Table

### The Problem:
If you created announcements table before, it might be missing new columns like:
- `user_id`
- `images`
- `attachments`
- `is_pinned`
- `likes_count`, `shares_count`, `comments_count`

### The Solution:

**Method 1: Use PHP Setup Script**
- Just run `setup_announcements_db.php`
- It will automatically add missing columns
- Your data is preserved

**Method 2: Run Migration SQL**

Open the `create_announcements_tables.sql` file and find the **MIGRATION SCRIPT** section. Run those ALTER TABLE commands.

Example:
```sql
-- Add missing columns
ALTER TABLE announcements ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id;
ALTER TABLE announcements ADD COLUMN images JSON AFTER is_pinned;
ALTER TABLE announcements ADD COLUMN attachments JSON AFTER images;
-- etc...
```

## ✅ Verification

### Check if setup was successful:

#### Method 1: In PHP
Access `teamofficer/announcements.php`
- If you see the announcements page → ✅ Success!
- If you see "Setup Required" message → Run setup script

#### Method 2: In phpMyAdmin
Run this query:
```sql
DESCRIBE announcements;
```

You should see these columns:
```
id, user_id, title, content, type, priority, status, 
is_pinned, images, attachments, likes_count, 
shares_count, comments_count, created_at, updated_at
```

#### Method 3: Count Tables
```sql
SELECT COUNT(*) as total_tables
FROM information_schema.tables
WHERE table_schema = DATABASE()
AND table_name IN (
    'announcements',
    'announcement_likes',
    'announcement_comments',
    'announcement_shares',
    'announcement_saves'
);
```
Should return: **5**

## 🚨 Troubleshooting

### Error: "Table already exists"
**Solution**: This is OK! Tables were created before. The script handles this.

### Error: "Foreign key constraint fails"
**Solution**: Make sure `user_accounts` table exists
```sql
SHOW TABLES LIKE 'user_accounts';
```

### Error: "Unknown column 'is_pinned'"
**Solution**: Run the migration section from SQL file or use PHP setup script

### Error: "JSON not supported"
**Solution**: Upgrade MySQL to 5.7+ or MariaDB 10.2+
Check version:
```sql
SELECT VERSION();
```

### Error: "Access denied for ALTER"
**Solution**: Use database user with ALTER TABLE privileges
```sql
GRANT ALTER ON foodify.* TO 'your_user'@'localhost';
```

## 📊 Table Structure Reference

### ANNOUNCEMENTS Table Schema:

```sql
Field            Type                         Description
-------------    --------------------------   ----------------------------
id               INT                          Primary key
user_id          INT                          Creator (team officer)
title            VARCHAR(255)                 Announcement title
content          TEXT                         Main content/message
type             ENUM(...)                    announcement/guideline/reminder/alert
priority         ENUM(...)                    low/medium/high/critical
status           ENUM(...)                    draft/published/archived
is_pinned        TINYINT(1)                   Pin to top (1=yes, 0=no)
images           JSON                         Array of image paths
attachments      JSON                         Array of file metadata
likes_count      INT                          Number of likes
shares_count     INT                          Number of shares
comments_count   INT                          Number of comments
created_at       TIMESTAMP                    When created
updated_at       TIMESTAMP                    Last update time
```

### Engagement Tables Schema:

Each follows similar pattern:
- `post_id` - Links to announcement/donation
- `post_type` - 'announcement' or 'food_donation'
- `user_id` - User who performed action
- `created_at` - When action occurred
- Additional fields as needed

## 🎯 Post-Setup Steps

After successful setup:

1. ✅ **Access the page**: Go to `teamofficer/announcements.php`
2. ✅ **Create test announcement**: Click "Create Announcement"
3. ✅ **Upload test image**: Try uploading an image
4. ✅ **Upload test file**: Try uploading a PDF
5. ✅ **Test interactions**: Try liking, commenting, sharing
6. ✅ **Switch views**: Try card view and table view
7. ✅ **Test filtering**: Filter by type (announcement/guideline/reminder/alert)

## 📦 Files Created

1. **`teamofficer/setup_announcements_db.php`** - PHP setup script (run in browser)
2. **`create_announcements_tables.sql`** - SQL file (run in phpMyAdmin)
3. **`SETUP_INSTRUCTIONS.md`** - This file (documentation)
4. **`DATABASE_MIGRATION_GUIDE.md`** - Migration details

## 🔄 Maintenance

### Regular Backups
```bash
# Backup announcements tables
mysqldump -u root -p foodify announcements announcement_likes announcement_comments announcement_shares announcement_saves > announcements_backup.sql
```

### Restore from Backup
```bash
# Restore announcements tables
mysql -u root -p foodify < announcements_backup.sql
```

### Clear Test Data
```sql
-- Delete all test announcements
DELETE FROM announcements WHERE title LIKE '%test%';

-- Reset engagement data
TRUNCATE announcement_likes;
TRUNCATE announcement_comments;
TRUNCATE announcement_shares;
TRUNCATE announcement_saves;
UPDATE announcements SET likes_count=0, shares_count=0, comments_count=0;
```

## 🎓 Learning Resources

### Understanding the System:
1. **Table Relationships**: See how tables connect via foreign keys
2. **JSON Storage**: Learn how images/files are stored as JSON
3. **Engagement System**: Understand likes, comments, shares flow
4. **ENUM Types**: See how type/priority/status are defined

### Explore the Data:
```sql
-- See all announcements with engagement
SELECT a.*, 
       (SELECT COUNT(*) FROM announcement_likes WHERE post_id=a.id AND post_type='announcement') as actual_likes,
       (SELECT COUNT(*) FROM announcement_comments WHERE post_id=a.id AND post_type='announcement') as actual_comments
FROM announcements a;
```

## 🆘 Need Help?

### Common Issues:

**Q: I get "Setup Required" message**
A: Run `setup_announcements_db.php` in your browser

**Q: Old announcements disappeared**
A: Check if you accidentally dropped the table. Restore from backup.

**Q: Can't upload files**
A: Check `uploads/announcements/` folder permissions (should be 777 or 755)

**Q: Foreign key errors**
A: Ensure `user_accounts` table exists with `user_id` column

**Q: JSON errors**
A: Check MySQL version (needs 5.7+)

### Get Support:
1. Check error logs in phpMyAdmin
2. Review `DATABASE_MIGRATION_GUIDE.md`
3. Contact system administrator

## 🎉 Success Checklist

After setup, you should be able to:

- [ ] Access announcements page without errors
- [ ] Create new announcements
- [ ] Upload images (JPG, PNG, etc.)
- [ ] Attach files (PDF, DOC, etc.)
- [ ] See uploaded media in feed
- [ ] Like/comment/share posts
- [ ] Switch between card and table views
- [ ] Filter by type (announcement/guideline/reminder/alert)
- [ ] Pin important announcements
- [ ] Edit existing announcements
- [ ] Delete announcements

## 📝 Summary

### Recommended Setup Process:

1. **Backup** your database (if you have existing data)
2. **Run** `setup_announcements_db.php` in browser
3. **Verify** all tables created successfully
4. **Test** by creating a sample announcement with image
5. **Done**! System ready to use

### Time Required:
- Setup: **< 1 minute**
- Testing: **2-3 minutes**
- Total: **~5 minutes**

---

**Status**: Ready for Setup
**Last Updated**: October 13, 2025
**Support**: See troubleshooting section above


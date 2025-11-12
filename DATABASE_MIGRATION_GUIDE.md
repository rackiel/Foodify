# Database Migration Guide - Announcements Table

## Problem Fixed
**Error**: `Database error: Unknown column 'user_id' in 'field list'`

This error occurred because the `announcements` table existed from a previous version but was missing the new columns required by the updated system.

## Solution Implemented

### Automatic Migration Script
The system now includes an **automatic migration script** that runs every time you access the announcements page. It:

1. ✅ Checks if the `announcements` table exists
2. ✅ Detects which columns are missing
3. ✅ Automatically adds missing columns
4. ✅ Updates ENUM values for existing columns
5. ✅ Preserves all existing data

### Columns Added Automatically

If your table was missing any of these columns, they were added:

| Column | Type | Default | Purpose |
|--------|------|---------|---------|
| `user_id` | INT | Current user | Links announcement to creator |
| `images` | JSON | NULL | Stores uploaded image paths |
| `attachments` | JSON | NULL | Stores file attachment metadata |
| `likes_count` | INT | 0 | Tracks like count |
| `shares_count` | INT | 0 | Tracks share count |
| `comments_count` | INT | 0 | Tracks comment count |

### ENUM Values Updated

The migration also updates ENUM columns to include new values:

#### Type Column:
**Old**: `'announcement', 'guideline', 'reminder'`
**New**: `'announcement', 'guideline', 'reminder', 'alert'`

#### Priority Column:
**Old**: `'low', 'medium', 'high'`
**New**: `'low', 'medium', 'high', 'critical'`

## How It Works

### Migration Process:

```php
1. Check if announcements table exists
   ↓
2. Query all existing columns
   ↓
3. Compare with required columns
   ↓
4. Add missing columns one by one
   ↓
5. Update ENUM definitions
   ↓
6. Done! All data preserved
```

### Code Implementation:

```php
// Check existing columns
$check_columns = "SHOW COLUMNS FROM announcements";
$result = $conn->query($check_columns);

// Get list of existing columns
$existing_columns = [];
while ($row = $result->fetch_assoc()) {
    $existing_columns[] = $row['Field'];
}

// Add missing columns
if (!in_array('user_id', $existing_columns)) {
    $conn->query("ALTER TABLE announcements 
                  ADD COLUMN user_id INT NOT NULL 
                  DEFAULT [current_user_id] 
                  AFTER id");
}
// ... repeat for other columns
```

## What Happens to Existing Data?

### ✅ All Existing Data is SAFE
- **Existing announcements**: Preserved
- **Existing content**: Unchanged
- **Existing timestamps**: Maintained

### New Columns Default Values:
- `user_id`: Set to current logged-in user (for old posts)
- `images`: NULL (no images initially)
- `attachments`: NULL (no files initially)
- `likes_count`: 0
- `shares_count`: 0
- `comments_count`: 0

## Manual Migration (If Needed)

If you prefer to run the migration manually or the automatic migration fails:

### SQL Script:

```sql
-- Add user_id column
ALTER TABLE announcements 
ADD COLUMN user_id INT NOT NULL DEFAULT 1 AFTER id;

-- Add foreign key
ALTER TABLE announcements 
ADD FOREIGN KEY (user_id) REFERENCES user_accounts(user_id) ON DELETE CASCADE;

-- Add images column
ALTER TABLE announcements 
ADD COLUMN images JSON AFTER is_pinned;

-- Add attachments column
ALTER TABLE announcements 
ADD COLUMN attachments JSON AFTER images;

-- Add engagement columns
ALTER TABLE announcements 
ADD COLUMN likes_count INT DEFAULT 0 AFTER attachments,
ADD COLUMN shares_count INT DEFAULT 0 AFTER likes_count,
ADD COLUMN comments_count INT DEFAULT 0 AFTER shares_count;

-- Update type ENUM
ALTER TABLE announcements 
MODIFY COLUMN type ENUM('announcement', 'guideline', 'reminder', 'alert') 
DEFAULT 'announcement';

-- Update priority ENUM
ALTER TABLE announcements 
MODIFY COLUMN priority ENUM('low', 'medium', 'high', 'critical') 
DEFAULT 'medium';
```

## Verification

### Check if migration was successful:

```sql
-- View table structure
DESCRIBE announcements;

-- Should show all columns including:
-- id, user_id, title, content, type, priority, status, 
-- is_pinned, images, attachments, likes_count, 
-- shares_count, comments_count, created_at, updated_at
```

### Expected Output:

```
+----------------+------------------------------------------------------+
| Field          | Type                                                  |
+----------------+------------------------------------------------------+
| id             | int(11)                                              |
| user_id        | int(11)                                              |
| title          | varchar(255)                                         |
| content        | text                                                 |
| type           | enum('announcement','guideline','reminder','alert')  |
| priority       | enum('low','medium','high','critical')               |
| status         | enum('draft','published','archived')                 |
| is_pinned      | tinyint(1)                                          |
| images         | json                                                 |
| attachments    | json                                                 |
| likes_count    | int(11)                                             |
| shares_count   | int(11)                                             |
| comments_count | int(11)                                             |
| created_at     | timestamp                                            |
| updated_at     | timestamp                                            |
+----------------+------------------------------------------------------+
```

## Troubleshooting

### If you still see errors:

#### Error: Foreign key constraint fails
**Solution**: Ensure `user_accounts` table exists and has `user_id` column

```sql
-- Check user_accounts table
DESCRIBE user_accounts;
```

#### Error: JSON not supported
**Solution**: Upgrade MySQL to 5.7+ or MariaDB 10.2+

```sql
-- Check MySQL version
SELECT VERSION();
```

If version is too old, you can change JSON to TEXT:
```sql
ALTER TABLE announcements 
MODIFY COLUMN images TEXT,
MODIFY COLUMN attachments TEXT;
```

#### Error: Column already exists
**Solution**: The migration already ran successfully. Ignore this error.

#### Error: Default value issue
**Solution**: Remove the default value for user_id:

```sql
ALTER TABLE announcements 
MODIFY COLUMN user_id INT NOT NULL;
```

## Migration Status Check

### Run this query to check migration status:

```sql
SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'announcements'
ORDER BY ORDINAL_POSITION;
```

## Rollback (If Needed)

### To remove new columns (NOT RECOMMENDED):

```sql
ALTER TABLE announcements
DROP COLUMN user_id,
DROP COLUMN images,
DROP COLUMN attachments,
DROP COLUMN likes_count,
DROP COLUMN shares_count,
DROP COLUMN comments_count;
```

**⚠️ Warning**: This will delete all uploaded images/files data and engagement metrics!

## Best Practices

### Going Forward:

1. ✅ **Don't manually modify** the announcements table
2. ✅ **Let the system handle** migrations automatically
3. ✅ **Backup database** before major updates
4. ✅ **Test on development** environment first
5. ✅ **Monitor logs** for migration errors

### Backup Command:

```bash
# Backup entire database
mysqldump -u username -p database_name > backup.sql

# Backup just announcements table
mysqldump -u username -p database_name announcements > announcements_backup.sql
```

### Restore Command:

```bash
# Restore entire database
mysql -u username -p database_name < backup.sql

# Restore just announcements table
mysql -u username -p database_name < announcements_backup.sql
```

## Summary

✅ **Problem**: Old table structure missing new columns
✅ **Solution**: Automatic migration script
✅ **Result**: All columns added, data preserved
✅ **Status**: Ready to use!

### What You Can Do Now:

1. ✅ Create announcements with images and files
2. ✅ Use likes, comments, shares features
3. ✅ Switch between card and table views
4. ✅ Filter by type (announcement/guideline/reminder/alert)
5. ✅ Pin important announcements
6. ✅ Set priority levels (including critical)

## Need Help?

If you encounter any issues:

1. Check error logs: `php_error.log`
2. Verify MySQL version: Must be 5.7+ for JSON support
3. Check user permissions: User needs ALTER TABLE privileges
4. Contact system administrator

---

**Status**: ✅ Migration Complete
**Last Updated**: October 13, 2025
**Version**: 4.0 (With Auto-Migration)


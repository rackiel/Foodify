# Ingredient Management System Updates

## Summary of Changes
This document outlines all the updates made to the ingredient management system, including the removal of like/dislike functionality and addition of new features for ingredient lifecycle management.

---

## 🗑️ Removed Features
### Like/Dislike System
- Removed all feedback buttons (heart and broken heart icons)
- Removed `ingredient_feedback` table from database
- Removed all related JavaScript handlers
- Cleaned up CSS related to feedback buttons

---

## ✨ New Features Added

### 1. Ingredient Status Management
**Database Changes:**
- Added `status` field: ENUM('active', 'used', 'expired') with default 'active'
- Added `expiration_date` field: DATE type for tracking expiration
- Created indexes for performance optimization

**Files Modified:**
- `update_ingredient_table.sql` - Database migration script

### 2. Expiration Date Tracking
**Features:**
- Expiration date field added to add/update forms
- Visual indicators for expiring ingredients:
  - **Orange border** for ingredients expiring within 7 days
  - **Red border** for already expired ingredients
- Automatic status update for expired ingredients

### 3. CRUD Operations
**New Functionality:**
- ✏️ **Update**: Edit ingredient details including expiration date
- 🗑️ **Delete**: Permanently remove ingredients
- ✅ **Use**: Mark ingredients as used (moves to used ingredients page)

**Implementation:**
- Update modal with pre-populated fields
- AJAX handlers for seamless updates
- Confirmation dialogs for destructive actions

### 4. Used Ingredients Management
**New Page:** `residents/used_ingredients.php`

**Features:**
- View all ingredients marked as "used"
- **Restore** button: Move ingredients back to active feed
- **Delete** button: Permanently remove from system
- Search functionality
- Green visual theme to indicate used status

### 5. Expired Ingredients Management
**New Page:** `residents/expired_ingredients.php`

**Features:**
- Automatically populated with expired ingredients
- Shows days since expiration
- **Restore** button: Move back to active if still usable
- **Delete** button: Permanently remove
- Red visual theme to indicate expired status
- Auto-update: Ingredients past expiration date automatically moved here

### 6. Email Notification System
**New File:** `residents/check_expiring_ingredients.php`

**Features:**
- Scans for ingredients expiring within 7 days
- Sends consolidated email per user with all their expiring ingredients
- Beautiful HTML email template with:
  - Color-coded urgency (red for 0-2 days, orange for 3-7 days)
  - Table format with ingredient details
  - Direct link to ingredients page
- Uses PHPMailer for reliable email delivery

**Setup Options:**
- Manual execution: `php residents/check_expiring_ingredients.php`
- Automated via cron job (recommended daily at 8 AM)
- Windows Task Scheduler compatible

**Documentation:** `INGREDIENT_EXPIRATION_NOTIFICATION_README.md`

---

## 📁 Files Created/Modified

### New Files
1. `update_ingredient_table.sql` - Database migration
2. `residents/get_ingredient.php` - AJAX endpoint for ingredient data
3. `residents/used_ingredients.php` - Used ingredients page
4. `residents/expired_ingredients.php` - Expired ingredients page
5. `residents/check_expiring_ingredients.php` - Notification script
6. `INGREDIENT_EXPIRATION_NOTIFICATION_README.md` - Notification documentation
7. `INGREDIENT_MANAGEMENT_UPDATES.md` - This file

### Modified Files
1. `residents/input_ingredients.php` - Complete rewrite with new features
2. `residents/sidebar.php` - Added new menu items

---

## 🎨 UI/UX Improvements

### Visual Indicators
- **Expiring Soon (7 days)**: Orange border + warning badge
- **Expired**: Red border + danger badge
- **Used**: Green background + success badge

### Card Interactions
- Smooth hover animations maintained
- Action buttons with clear icons
- Responsive design for all screen sizes

### Search Functionality
- Real-time search across all pages
- Searches: name, category, vitamins, remarks
- "No results" message when applicable

---

## 🔄 Workflow

### Ingredient Lifecycle
```
[Add New] → [Active Feed] → [Mark as Used] → [Used Ingredients]
                ↓                                    ↑
         [Expires] → [Expired Ingredients] → [Restore/Delete]
                                                     ↑
                                              [Restore]
```

### Status Transitions
- **Active → Used**: User manually marks ingredient as used
- **Active → Expired**: Automatic when expiration date passes
- **Used → Active**: User restores ingredient to feed
- **Expired → Active**: User restores if still usable
- **Any → Deleted**: Permanent removal from system

---

## 📧 Email Notification Details

### Trigger Conditions
- Ingredient status = 'active'
- Expiration date between today and 7 days from now
- User has valid email address

### Email Content
- Personalized greeting with user's name
- Table of all expiring ingredients
- Days remaining for each ingredient
- Color-coded urgency
- Direct link to ingredients page
- Responsive HTML design

### Recommended Schedule
- **Daily at 8:00 AM**: Gives users time to plan their day
- Alternative times: 6:00 PM (evening reminder)

---

## 🗄️ Database Schema Updates

```sql
-- New columns in ingredient table
expiration_date DATE NULL
status ENUM('active', 'used', 'expired') DEFAULT 'active'

-- Indexes for performance
idx_ingredient_status (status)
idx_ingredient_expiration (expiration_date)

-- Dropped table
ingredient_feedback (entire table removed)
```

---

## 🚀 Setup Instructions

### 1. Database Migration
```bash
# Run the SQL migration in your database
mysql -u username -p database_name < update_ingredient_table.sql
```

Or import via phpMyAdmin:
1. Open phpMyAdmin
2. Select 'foodify' database
3. Go to Import tab
4. Choose `update_ingredient_table.sql`
5. Click Go

### 2. File Deployment
All files are already in place. Just ensure:
- Web server has write permissions to `uploads/ingredients/`
- PHPMailer is installed via Composer (already configured)

### 3. Setup Email Notifications

#### Option A: Linux/Mac Cron
```bash
crontab -e
# Add this line:
0 8 * * * /usr/bin/php /path/to/foodify/residents/check_expiring_ingredients.php
```

#### Option B: Windows Task Scheduler
1. Open Task Scheduler
2. Create Basic Task → "Foodify Expiration Check"
3. Trigger: Daily 8:00 AM
4. Action: Start program
   - Program: `C:\xampp\php\php.exe`
   - Arguments: `C:\xampp\htdocs\foodify\residents\check_expiring_ingredients.php`

#### Option C: Manual (for testing)
```bash
php residents/check_expiring_ingredients.php
```

### 4. Test the System
1. Add an ingredient with expiration date in 3 days
2. View it in Ingredients Feed (should have orange border)
3. Click "Use" - verify it moves to Used Ingredients page
4. Click "Restore" - verify it returns to feed
5. Run notification script manually to test emails

---

## 🎯 Navigation Updates

The sidebar now includes:
- **Ingredients Feed** (formerly "Input Ingredients")
- **Used Ingredients** (new)
- **Expired Ingredients** (new)
- Meal Plan Generator
- Saved/Printable Plans

All under "Ingredient & Meal Planning" section.

---

## 🔒 Security Considerations

- All database queries use prepared statements
- File uploads validated for image types only
- User authentication required for all operations
- AJAX endpoints validate user session
- Confirmation dialogs for destructive actions

---

## 📊 Benefits

1. **Reduced Food Waste**: Proactive notifications prevent ingredients from expiring
2. **Better Organization**: Clear separation of active, used, and expired ingredients
3. **Full Control**: Users can update, delete, and manage lifecycle of ingredients
4. **Convenience**: Email notifications eliminate need to manually check
5. **Flexibility**: Restore functionality allows recovery from mistakes

---

## 🐛 Troubleshooting

### No emails received
- Check spam/junk folder
- Verify email address in users table
- Check SMTP credentials in `server_mail.php`
- Run script manually to see error messages

### Expired ingredients not showing
- Check expiration dates are set correctly
- Run: `UPDATE ingredient SET status='expired' WHERE expiration_date < CURDATE() AND status='active'`

### Images not displaying
- Check file permissions on `uploads/ingredients/` directory
- Verify image path in database matches actual file location

---

## 📝 Future Enhancements (Suggestions)

1. **Push Notifications**: Browser notifications in addition to email
2. **Statistics Dashboard**: Track usage patterns and waste reduction
3. **Batch Operations**: Select multiple ingredients to mark as used/delete
4. **Categories Management**: Add/edit ingredient categories
5. **Export/Import**: CSV export of ingredient inventory
6. **Shopping List**: Convert low-stock ingredients to shopping list
7. **Recipe Integration**: Link ingredients to recipes that use them

---

## ✅ Testing Checklist

- [ ] Database migration completed successfully
- [ ] Can add new ingredient with expiration date
- [ ] Can update existing ingredient
- [ ] Can delete ingredient
- [ ] Can mark ingredient as used
- [ ] Can view used ingredients page
- [ ] Can restore used ingredient to feed
- [ ] Can view expired ingredients page
- [ ] Expired ingredients show correct badge
- [ ] Email notification script runs without errors
- [ ] Received test email notification
- [ ] Sidebar shows all new menu items
- [ ] Search works on all three pages
- [ ] Mobile responsive design works

---

## 📞 Support

For issues or questions:
1. Check this documentation
2. Review `INGREDIENT_EXPIRATION_NOTIFICATION_README.md` for notification details
3. Check console logs for JavaScript errors
4. Review PHP error logs for server-side issues

---

**Last Updated:** October 8, 2025
**Version:** 2.0.0


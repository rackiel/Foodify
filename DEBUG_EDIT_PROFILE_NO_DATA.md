# Debug: Edit Profile No Data Issue

## Problem
User reports that there's no data showing on the edit_profile.php page.

---

## 🔍 Diagnostic Steps

### Step 1: Run Diagnostic Script
Navigate to: `http://localhost/foodify/test_user_data.php`

This script will check:
1. ✅ Session is active and has user_id
2. ✅ Database connection is working
3. ✅ User data can be fetched from database
4. ✅ user_accounts table exists and has correct structure

**What to look for:**
- If all tests show ✅ - Data is fetching correctly, issue is display-related
- If any test shows ❌ - That's where the problem is

### Step 2: Check Error Messages
When you load `edit_profile.php`, look for red error alert at the top of the page:

**Possible error messages:**
1. "No user data found for user ID: X" - User ID doesn't exist in database
2. "Error fetching user data: [error details]" - Database query error
3. "Unable to load user profile..." - User data is empty

### Step 3: Verify Session
Check if you're logged in:
```php
// In any PHP file
<?php
session_start();
var_dump($_SESSION);
?>
```

Should show:
- `user_id` => [number]
- `username` => [string]
- `email` => [string]
- `role` => [string]

---

## 🛠️ Recent Changes Made

### 1. Added Error Handling
```php
if (empty($user_data)) {
    $error_message = "Unable to load user profile. User ID: " . $user_id . " - Please contact administrator.";
}
```

### 2. Added Null Coalescing Operators
All form fields now have `??` operators to prevent undefined key errors:
```php
value="<?= htmlspecialchars($user_data['full_name'] ?? '') ?>"
```

### 3. Improved Database Query
```php
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $user_data = $result->fetch_assoc();
} else {
    $error_message = "No user data found for user ID: " . $user_id;
}
```

---

## 🔧 Common Issues & Solutions

### Issue 1: User ID Mismatch
**Symptom:** "No user data found for user ID: X"

**Cause:** Session user_id doesn't match any record in database

**Solution:**
```sql
-- Check if user exists
SELECT * FROM user_accounts WHERE user_id = [your_user_id];

-- If no results, the user record was deleted or session is corrupted
-- Solution: Logout and login again
```

### Issue 2: Database Connection Failed
**Symptom:** Page loads but no data, or connection error

**Cause:** Database credentials wrong or MySQL not running

**Solution:**
1. Check `config/db.php` has correct credentials
2. Verify MySQL service is running
3. Check XAMPP control panel

### Issue 3: Corrupted Session
**Symptom:** Random behavior, inconsistent data

**Solution:**
```php
// Add to top of edit_profile.php temporarily
session_destroy();
session_start();
header('Location: ../index.php'); // Force re-login
```

### Issue 4: Table Structure Changed
**Symptom:** "Column not found" errors

**Solution:**
Run diagnostic script to see table structure, verify all expected columns exist.

---

## 📊 Expected Database Schema

The `user_accounts` table should have these columns:

```sql
CREATE TABLE user_accounts (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50),
    email VARCHAR(100),
    password_hash VARCHAR(255),
    full_name VARCHAR(100),
    phone_number VARCHAR(20),
    address TEXT,
    role VARCHAR(20),
    profile_img VARCHAR(255),
    status VARCHAR(20),
    created_at TIMESTAMP,
    -- other columns...
);
```

---

## 🧪 Testing Checklist

### Test 1: Basic Page Load
- [ ] Page loads without PHP errors
- [ ] Error message appears if data missing
- [ ] Forms are visible (even if empty)

### Test 2: With Valid Data
- [ ] Full name appears in input field
- [ ] Email appears in input field
- [ ] Phone number appears (if set)
- [ ] Address appears (if set)
- [ ] Profile picture shows
- [ ] Account details table populated

### Test 3: Tab Navigation
- [ ] Profile Information tab works
- [ ] Security tab works
- [ ] Account Management tab works
- [ ] Tab switching doesn't lose data

---

## 🔍 Debug Code Snippets

### Quick Debug in edit_profile.php
Add after line 144 (after user data fetch):

```php
// TEMPORARY DEBUG - Remove after testing
echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
echo "User ID from session: " . $user_id . "\n";
echo "User data fetched: " . (empty($user_data) ? "NO" : "YES") . "\n";
if (!empty($user_data)) {
    echo "Data fields:\n";
    print_r(array_keys($user_data));
}
echo "</pre>";
```

### Check Specific Field
```php
// Add in form section
echo "<!-- DEBUG: full_name = '" . ($user_data['full_name'] ?? 'NOT SET') . "' -->";
```

---

## 🚨 Security Reminder

**After debugging, remove or delete:**
1. `test_user_data.php` - Contains sensitive debug info
2. Any debug `echo` or `var_dump` statements
3. Temporary error messages with system details

---

## 📝 Verification Steps

### Step 1: Login
1. Go to `http://localhost/foodify/`
2. Login with valid credentials
3. Check that you're redirected to residents dashboard

### Step 2: Access Edit Profile
1. Click on your profile dropdown in topbar
2. Click "Profile" or navigate to `edit_profile.php`
3. Page should load with your data

### Step 3: Check Each Section
1. **Profile Information Tab:**
   - See your profile picture
   - See your name in input field
   - See your email in input field
   
2. **Security Tab:**
   - See account created date
   - See account status badge
   
3. **Account Management Tab:**
   - See your user ID
   - See your username
   - See member since date

---

## 🔄 Reset Instructions

If all else fails, try a fresh login:

```php
<?php
// create fresh_login.php
session_start();
session_destroy();
header('Location: index.php');
?>
```

1. Run `fresh_login.php`
2. Login again
3. Try edit_profile.php again

---

## 📞 Support Information

If issue persists after all tests:

**Collect this information:**
1. Screenshot of diagnostic script output
2. Screenshot of error message (if any)
3. Screenshot of empty edit profile page
4. Copy of session data (from diagnostic)
5. MySQL error log (if database errors)

**Check these locations:**
- `C:\xampp\mysql\data\[dbname].err` - MySQL errors
- `C:\xampp\apache\logs\error.log` - PHP errors
- Browser console (F12) - JavaScript errors

---

## ✅ Success Criteria

Edit profile is working correctly when:
1. ✅ Page loads without errors
2. ✅ All form fields show current user data
3. ✅ Profile picture displays
4. ✅ All three tabs are accessible
5. ✅ Account information tables populated
6. ✅ Can successfully update profile
7. ✅ Can successfully change password
8. ✅ Can successfully upload new picture

---

## 📚 Related Files

- `residents/edit_profile.php` - Main profile edit page
- `residents/topbar.php` - Navigation bar (includes profile picture)
- `residents/header.php` - Page header (includes db.php)
- `config/db.php` - Database connection
- `test_user_data.php` - Diagnostic script (DELETE after use)

---

**Status:** Debugging tools deployed  
**Next Action:** Run diagnostic script  
**Priority:** High  
**Estimated Fix Time:** 5-10 minutes after identifying issue


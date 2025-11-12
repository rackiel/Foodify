# Announcements System - Complete Guide

## 🚀 Quick Fix for Your Error

### The Error You Saw:
```
Fatal error: Unknown column 'is_pinned' in 'announcements'
```

### ✅ Solution (2 Steps):

#### Step 1: Run Database Setup
Open in browser: **`http://localhost/foodify/teamofficer/setup_announcements_db.php`**

This will:
- Create all missing tables
- Add all missing columns
- Fix all errors
- Preserve your existing data

#### Step 2: Access Announcements
Go to: **`http://localhost/foodify/teamofficer/announcements.php`**

Everything should work perfectly now! 🎉

---

## 📚 Complete System Features

### What You Can Do:

#### 1. **Announcements Management**
- ✅ Create announcements, guidelines, reminders, alerts
- ✅ Upload images (JPG, PNG, GIF, WebP, SVG)
- ✅ Attach files (PDF, Word, Excel, PowerPoint, ZIP)
- ✅ Set priority (Low, Medium, High, Critical)
- ✅ Pin important posts to top
- ✅ Draft/Publish/Archive
- ✅ Edit and delete

#### 2. **Social Features** (Like recipes_tips.php)
- ❤️ Like/Unlike posts
- 💬 Comment system
- 📤 Share functionality  
- 🔖 Save/Bookmark posts

#### 3. **Food Donation Moderation**
- View pending donations
- Approve donations
- Reject donations
- All in same interface

#### 4. **Two View Modes**
- **Card View**: Social media feed style
- **Table View**: Professional admin table

#### 5. **Smart Filtering**
- Filter by type (All/Announcements/Guidelines/Reminders/Alerts)
- Filter by post type (All/Announcements/Donations)
- View saved/bookmarked posts

---

## 📁 Files You Need to Know

### Setup Files:
1. **`setup_announcements_db.php`** - Run this first to set up database
2. **`create_announcements_tables.sql`** - Alternative: SQL script for phpMyAdmin

### Main Application:
3. **`announcements.php`** - Main announcements system page

### Documentation:
4. **`SETUP_INSTRUCTIONS.md`** - Detailed setup guide
5. **`DATABASE_MIGRATION_GUIDE.md`** - Migration details
6. **`ANNOUNCEMENTS_SOCIAL_FEATURES.md`** - Social features guide
7. **`ANNOUNCEMENT_FILE_UPLOAD_FEATURE.md`** - Upload features
8. **`TABLE_VIEW_FEATURE.md`** - Table view guide
9. **`QUICK_UPLOAD_GUIDE.md`** - Quick reference for uploads
10. **`ANNOUNCEMENTS_README.md`** - This file

---

## 🗂️ Database Tables Created

### 5 Tables:

| Table | Purpose |
|-------|---------|
| `announcements` | Main announcements data |
| `announcement_likes` | Like/reaction tracking |
| `announcement_comments` | Comment system |
| `announcement_shares` | Share tracking |
| `announcement_saves` | Bookmark system |

### 2 Upload Folders:

| Folder | Purpose |
|--------|---------|
| `uploads/announcements/images/` | Image storage |
| `uploads/announcements/files/` | File attachments |

---

## 🎯 How to Use

### Creating an Announcement with Files:

1. Click **"Create Announcement"** button
2. Fill in:
   - Title (required)
   - Content (required)
   - Type: Announcement/Guideline/Reminder/Alert
   - Priority: Low/Medium/High/Critical
   - Status: Draft/Published/Archived
3. **Upload Images** (optional):
   - Click "Choose Files" under images
   - Select JPG, PNG, GIF, WebP, or SVG files
   - Preview appears instantly
4. **Attach Files** (optional):
   - Click "Choose Files" under attachments
   - Select PDF, Word, Excel, PowerPoint, ZIP files
   - File list shows with sizes
5. Check **"Pin to top"** if important
6. Click **"Save Announcement"**

### Viewing Announcements:

#### Card View (Default):
- Social media feed style
- See full content with images
- Interactive like/comment/share buttons
- Beautiful card layout

#### Table View:
- Click **"Table View"** button (top right)
- See all announcements in organized table
- Filter by type (All/Announcements/Guidelines/Reminders/Alerts)
- Quick action buttons (View/Edit/Delete)

### Managing Content:

- **Edit**: Click edit button, modify, save
- **Delete**: Click delete button, confirm
- **Pin/Unpin**: Check "Pin to top" when editing
- **Change Status**: Set to Draft/Published/Archived

### Social Interactions:

- **Like**: Click ❤️ heart icon
- **Comment**: Click 💬 chat icon, type, send
- **Share**: Click 📤 share icon, add message
- **Save**: Click 🔖 bookmark icon

---

## 🎨 Features Showcase

### Announcement Types:

| Type | Icon | Badge Color | Use Case |
|------|------|-------------|----------|
| Announcement | 📢 | Blue (Info) | General updates |
| Guideline | 📖 | Yellow (Warning) | Rules and policies |
| Reminder | 🔔 | Blue (Primary) | Event reminders |
| Alert | ⚠️ | Red (Danger) | Urgent notices |

### Priority Levels:

| Priority | Color | When to Use |
|----------|-------|-------------|
| Critical | 🔴 Red | Immediate action required |
| High | 🟡 Yellow | Important but not urgent |
| Medium | 🔵 Blue | Regular importance |
| Low | ⚪ Gray | Nice to know |

### Upload Capabilities:

**Images:**
- JPG, JPEG, PNG, GIF, WebP, SVG
- Multiple files supported
- Preview in grid layout
- Click to enlarge

**Files:**
- PDF, Word (DOC/DOCX)
- Excel (XLS/XLSX)
- PowerPoint (PPT/PPTX)
- Text (TXT)
- Archives (ZIP, RAR)
- Download links generated
- File type icons displayed

---

## 🔒 Security Features

- ✅ Team officer authentication required
- ✅ File type validation (whitelist only)
- ✅ Unique filename generation
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars)
- ✅ Foreign key constraints
- ✅ Secure file storage

---

## 🎓 Tips & Best Practices

### For Best Results:

1. **Use Appropriate Types**:
   - Announcements: General updates
   - Guidelines: Important rules
   - Reminders: Time-sensitive events
   - Alerts: Urgent situations

2. **Set Correct Priority**:
   - Critical: System emergencies
   - High: Important deadlines
   - Medium: Regular updates
   - Low: Optional information

3. **Pin Strategically**:
   - Only pin truly important posts
   - Unpin after relevance expires
   - Maximum 2-3 pinned at a time

4. **Use Media Wisely**:
   - Images: For visual clarity
   - Files: For detailed documents
   - Don't overload with too many files

5. **Engage**:
   - Respond to comments
   - Check engagement metrics
   - Share important announcements

---

## 📊 Quick Reference

### Access URLs:

| Page | URL |
|------|-----|
| Setup | `teamofficer/setup_announcements_db.php` |
| Main App | `teamofficer/announcements.php` |

### Key Shortcuts:

| Action | How To |
|--------|--------|
| Create | Click "Create Announcement" button |
| Switch View | Click "Card View" or "Table View" |
| Filter | Click tab at top |
| Quick Like | Click heart on any post |
| Comment | Click chat icon |

---

## ✅ Final Checklist

Before you start using the system:

- [ ] Database tables created (run setup script)
- [ ] Upload directories exist
- [ ] Can access announcements page without errors
- [ ] Tested creating announcement
- [ ] Tested uploading image
- [ ] Tested uploading file
- [ ] Tested editing announcement
- [ ] Tested social features (like, comment)
- [ ] Tested both card and table views
- [ ] Tested type filtering

---

## 🎉 You're All Set!

The announcements system is now ready with:

✨ **Full CRUD operations** for announcements
✨ **Image and file uploads** 
✨ **Social media features** (likes, comments, shares)
✨ **Two viewing modes** (card and table)
✨ **Type filtering** (announcements, guidelines, reminders, alerts)
✨ **Professional admin interface**

**Enjoy your new announcements system!** 🚀

---

**Questions?** Check the documentation files or contact support.

**Last Updated**: October 13, 2025
**Version**: 4.0 (Complete Edition)


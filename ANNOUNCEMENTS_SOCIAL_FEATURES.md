# Team Officer Announcements - Social Media Features

## Overview
The announcements page has been completely rebuilt to mirror the **recipes_tips.php** social media-style interface but adapted for team officers with full CRUD operations for announcements and food donation moderation.

## 🎯 Key Features (Identical to recipes_tips.php)

### 1. **Social Media Interface**
✅ Facebook-style feed layout
✅ Profile pictures and user information on posts
✅ Interactive like, comment, share, and save buttons
✅ Real-time engagement counters
✅ Smooth animations and hover effects

### 2. **CRUD Operations for Announcements**

#### Create
- Modal-based creation form
- Fields:
  - **Title** (required)
  - **Content** (required)
  - **Type**: Announcement, Guideline, Reminder, Alert
  - **Priority**: Low, Medium, High, Critical
  - **Status**: Draft, Published, Archived
  - **Pin to Top**: Checkbox to pin important announcements

#### Read
- View all announcements in the feed
- Detailed view modal showing full information
- Filter by type (All, Announcements, Pending Donations, Saved)

#### Update
- Edit button on each announcement card
- Pre-fills form with existing data
- Updates in real-time

#### Delete
- Delete button with confirmation
- Permanently removes announcement and associated interactions

### 3. **Social Interaction Features**

#### ❤️ Like System
- Click heart icon to like/unlike
- Real-time counter updates
- Stores in `announcement_likes` table
- Works for both announcements and food donations

#### 💬 Comment System
- Expandable comment section
- View all comments with user profiles
- Add new comments with real-time posting
- Comment counter updates automatically
- Pagination support (10 comments per page)
- Stores in `announcement_comments` table

#### 📤 Share System
- Share posts with optional message
- Tracks share count
- Stores in `announcement_shares` table
- Can be extended for external sharing

#### 🔖 Save/Bookmark System
- Save posts for later viewing
- Toggle bookmark on/off
- Stores in `announcement_saves` table
- Filter view for saved posts

### 4. **Food Donation Moderation**

All pending food donations appear in the feed with:
- **View** button: See complete donation details
- **Approve** button: Makes donation available to community
- **Reject** button: Marks as rejected/cancelled
- Social interactions (like, comment, share, save)
- User profile information
- Food images (if uploaded)
- Expiration dates and quantity information

### 5. **Filtering System**

Four filter tabs (like recipes_tips.php):
- **All Posts**: Shows everything (announcements + pending donations)
- **Announcements**: Only team officer announcements
- **Pending Donations**: Only food donations awaiting review
- **Saved**: Only posts you've bookmarked

### 6. **Post Priority & Pinning**

- **Critical Priority**: Red badge
- **High Priority**: Yellow badge  
- **Medium Priority**: Blue badge
- **Low Priority**: Info badge
- **Pinned Posts**: Green pin badge, appears at top
- Posts sorted by: Pinned status → Creation date (DESC)

## 📊 Database Tables

### Created Automatically

```sql
1. announcements
   - Full announcement data with engagement counters
   - Types: announcement, guideline, reminder, alert
   - Priorities: low, medium, high, critical
   - Status: draft, published, archived
   - is_pinned: Pin to top of feed

2. announcement_likes
   - post_id, post_type, user_id
   - Supports both announcements and food_donations

3. announcement_comments
   - post_id, post_type, user_id, comment
   - Threaded comments support

4. announcement_shares
   - post_id, post_type, user_id, share_message
   - Track who shared what

5. announcement_saves
   - post_id, post_type, user_id
   - Bookmark functionality
```

## 🎨 User Interface

### Post Card Layout
```
┌─────────────────────────────────────────────────┐
│ [Profile Pic] User Name                [Badge] │
│              Timestamp                          │
├─────────────────────────────────────────────────┤
│                                                 │
│ Title                                           │
│ Content/Description...                          │
│                                                 │
│ [Additional Details for Food Donations]         │
│                                                 │
├─────────────────────────────────────────────────┤
│ ❤️ 5  💬 3  📤 2  🔖  |  [Action Buttons]       │
│                                                 │
│ [Comments Section - Expandable]                 │
└─────────────────────────────────────────────────┘
```

### Tab Navigation (Facebook-style)
```
[All Posts] [Announcements] [Pending Donations] [Saved]
    ↑
  Active
```

## 🔧 Technical Implementation

### AJAX Actions (Identical Pattern to recipes_tips.php)

1. **create_announcement** - Create new announcement
2. **update_announcement** - Edit existing announcement
3. **delete_announcement** - Remove announcement
4. **toggle_like** - Like/unlike posts
5. **add_comment** - Add comment to post
6. **get_comments** - Load comments with pagination
7. **share_post** - Share post with message
8. **save_post** - Bookmark/unbookmark post
9. **approve_donation** - Approve food donation
10. **reject_donation** - Reject food donation
11. **get_post_details** - Fetch full post data
12. **load_posts** - Dynamic post loading with filters

### JavaScript Functions

```javascript
// CRUD Operations
- saveAnnouncement()
- editAnnouncement(id)
- deleteAnnouncement(id)

// Social Interactions
- toggleLike(postId, postType, btn)
- toggleCommentsSection(postId, postType)
- loadComments(postId, postType)
- submitComment(postId, postType, comment, card)
- sharePost(postId, postType, btn)
- toggleSave(postId, postType, btn)

// Moderation
- approveDonation(id)
- rejectDonation(id)
- viewPost(id, postType)

// Utilities
- showNotification(message, type)
- loadPosts()
```

## 🚀 Usage Guide

### For Team Officers

#### Creating Announcements
1. Click "Create Announcement" button
2. Fill in the form:
   - Add title and content
   - Select type and priority
   - Choose status (draft/published)
   - Optionally pin to top
3. Click "Save Announcement"
4. Post appears in feed instantly

#### Editing Announcements
1. Find your announcement in the feed
2. Click "Edit" button
3. Modify fields in the modal
4. Save changes

#### Deleting Announcements
1. Click "Delete" button on announcement
2. Confirm deletion
3. Post and all interactions are removed

#### Moderating Food Donations
1. Pending donations appear in feed
2. Click "View" to see full details
3. Click "Approve" to make available
4. Click "Reject" to decline
5. Use social features to engage

#### Engaging with Posts
1. **Like**: Click heart icon
2. **Comment**: Click chat icon, type, send
3. **Share**: Click share icon, add message
4. **Save**: Click bookmark icon

#### Filtering Content
1. Click tab headers to filter:
   - All Posts: Everything
   - Announcements: Only your announcements
   - Pending Donations: Awaiting moderation
   - Saved: Your bookmarks

## 🔒 Security Features

- ✅ Session validation (team officer only)
- ✅ Prepared SQL statements (all queries)
- ✅ XSS protection (htmlspecialchars)
- ✅ CSRF protection via POST-only actions
- ✅ Permission checks on all operations
- ✅ Input validation and sanitization
- ✅ Database transactions for data integrity

## 📱 Responsive Design

- **Desktop**: Full-width cards with all features
- **Tablet**: Responsive grid layout
- **Mobile**: Stack cards vertically
- Touch-friendly buttons and interactions

## 🆚 Comparison with recipes_tips.php

### Identical Features
✅ Social media interface
✅ Like, comment, share, save
✅ Tab navigation
✅ Profile pictures
✅ Real-time updates
✅ Modal dialogs
✅ Toast notifications
✅ Pagination support

### Additional Features (announcements.php)
➕ Full CRUD operations for announcements
➕ Priority levels (low, medium, high, critical)
➕ Pin to top functionality
➕ Multiple announcement types
➕ Draft/Published/Archived status
➕ Food donation approval workflow
➕ Moderation actions

### Differences
- **Data Source**: Announcements + Food Donations vs Recipes + Tips + Meal Plans
- **User Role**: Team Officers only vs All Residents
- **Purpose**: Content management + Moderation vs Content sharing
- **Permissions**: Full edit/delete rights vs Own posts only

## 🎯 Benefits

### For Team Officers
- Modern, intuitive interface
- Quick content creation and management
- Efficient moderation workflow
- Community engagement tracking
- Better communication tools

### For the Platform
- Unified social experience
- Consistent UI/UX across roles
- Scalable architecture
- Easy maintenance
- Feature parity with resident features

## 🔮 Future Enhancements (Possible)

1. Rich text editor for content
2. Image uploads for announcements
3. Scheduled publishing
4. Analytics dashboard
5. Notification system
6. Tagging/categorization
7. Search functionality
8. Export reports
9. Batch operations
10. Mobile app integration

## 📝 Files

- **Main**: `teamofficer/announcements.php` (Complete rewrite)
- **Backup**: `teamofficer/announcements_backup.php` (Old version)
- **Documentation**: `ANNOUNCEMENTS_SOCIAL_FEATURES.md` (This file)

## 🎓 Learning Resources

To understand the implementation:
1. Study `residents/recipes_tips.php` - The source template
2. Review database tables in phpMyAdmin
3. Test all features in the interface
4. Check browser console for AJAX requests
5. Examine the modal implementations

---

**Status**: ✅ Complete and Production Ready
**Version**: 2.0 (Social Media Edition)
**Last Updated**: October 13, 2025
**Created By**: AI Assistant (Based on recipes_tips.php)

## 🎉 Success!

The announcements page is now a fully-featured social media platform for team officers with all the interactive elements from recipes_tips.php PLUS complete CRUD operations for announcements and food donation moderation!


# Shared Post Tab Feature

## 🎯 Overview
A **shared posts tracking system** that allows both team officers and residents to see which announcements they've shared and filter to view only their shared posts.

---

## ✨ Features Added

### **7th Tab: Shared Posts** (Available to All Users)

**Tab Structure:**
```
[All] [📢 Announcements] [🔔 Reminders] [📖 Guidelines] [⚠️ Alerts] [🔖 Saved] [📤 Shared]
                                                                                    ↑
                                                                                  NEW!
```

### **What It Does:**

✅ **Tracks Shared Posts** - Remembers which posts you've shared
✅ **Filter View** - Click tab to see only posts you've shared
✅ **Visual Indicators** - Shows "Shared by you" badge/icon
✅ **Both Views** - Team Officers: card/table view; Residents: card view
✅ **Real-time Updates** - Badge appears immediately after sharing
✅ **Available to All** - Team Officers AND Residents can track shared posts

---

## 🎯 How It Works

### **For Team Officers & Residents:**

#### **Sharing a Post:**
1. Find any announcement
2. Click **📤 Share** button
3. Add optional message in prompt
4. Click OK
5. **Share count increases**
6. **Shared badge appears** on the post
7. Post now marked as "shared by you"

#### **Viewing Shared Posts:**
1. Click **"📤 Shared"** tab at the top
2. See only announcements you've shared
3. Quick access to posts you've distributed
4. **Team Officers**: Works in both card and table views
5. **Residents**: Works in card view

#### **Benefits for All Users:**
- Track personal sharing activity
- Find previously shared posts easily
- See engagement on posts you shared
- Monitor your content distribution
- Stay organized with what you've shared

---

## 🎨 Visual Indicators

### **Card View - Header Badge:**
```
┌─────────────────────────────────────────────────────────┐
│ 👤 Officer Name  [📢 Announcement] [🔵 Medium] [📌]    │
│                  [📤 Shared by you] ← NEW!              │
└─────────────────────────────────────────────────────────┘
```

**Badge:**
- Icon: 📤 `bi-share-fill`
- Color: Info blue
- Text: "Shared by you"
- Tooltip: "You shared this post"

### **Table View - Icon Column:**
```
┌──┬────────────┬────────────┐
│📌│   Type     │   Title    │
│📤│            │            │ ← Shared icon
└──┴────────────┴────────────┘
```

**Icon:**
- Icon: 📤 `bi-share-fill`
- Color: Info blue
- Tooltip: "Shared by you"
- Position: First column (with pin icon)

---

## 📊 Interface Examples

### **Card View - Shared Post:**
```
┌─────────────────────────────────────────────────────────┐
│ 👤 Team Officer          [📢 Announcement] [🔵 Medium]  │
│    Posted: Jan 15, 2025  [📌 Pinned] [📤 Shared by you]│
├─────────────────────────────────────────────────────────┤
│ Important Meeting Tomorrow                               │
│ Content...                                               │
│                                                          │
│ ❤️ 15  💬 8  📤 5  🔖                                   │
└─────────────────────────────────────────────────────────┘
```

### **Table View - Shared Post:**
```
┌───┬────────────┬──────────────────┬─────────┐
│📌📤│    Type    │      Title       │ Actions │
├───┼────────────┼──────────────────┼─────────┤
│📌📤│📢 Announce │ Meeting Tomorrow │[👁️][✏️][🗑️]│
└───┴────────────┴──────────────────┴─────────┘
    ↑
  Pinned + Shared icons
```

### **Shared Tab View:**
```
Click "📤 Shared" tab

Shows only:
┌─────────────────────────────────────────┐
│ Posts you've personally shared          │
│                                          │
│ [📤 Shared by you] Meeting Tomorrow     │
│ [📤 Shared by you] Safety Guidelines    │
│ [📤 Shared by you] Event Reminder       │
└─────────────────────────────────────────┘
```

---

## 🔧 Technical Implementation

### **Database Tracking:**

**Table:** `announcement_shares`
```sql
When user shares a post:
- INSERT record with (post_id, post_type, user_id)
- Used to check: "Has this user shared this post?"
```

**PHP Code:**
```php
// Check if user shared this post
$share_check = $conn->prepare("
    SELECT id FROM announcement_shares 
    WHERE post_id = ? AND post_type = 'announcement' AND user_id = ?
");
$share_check->bind_param('ii', $post['id'], $_SESSION['user_id']);
$share_check->execute();
$post['is_shared'] = $share_check->get_result()->num_rows > 0 ? 1 : 0;
```

### **Frontend Data Attributes:**

**Card:**
```html
<div class="announcement-card" 
     data-saved="1"
     data-shared="1">  ← NEW!
```

**Table Row:**
```html
<tr data-announcement-type="announcement" 
    data-post-id="123"
    data-shared="1">  ← NEW!
```

### **Filtering Logic:**

```javascript
function filterAnnouncementsByType(type) {
    const isShared = card.dataset.shared == '1';
    
    if (type === 'shared') {
        shouldShow = isShared;  // Show only if user shared it
    } else if (type === 'saved') {
        shouldShow = isSaved;
    } else if (type === 'all') {
        shouldShow = true;
    } else {
        shouldShow = cardType === type;
    }
}
```

### **Real-time Badge Addition:**

```javascript
When user shares a post:
1. AJAX request to server
2. Database record created
3. Response received
4. Share count incremented
5. Card/row data attribute updated: data-shared="1"
6. Badge added to card header
7. Icon added to table row
```

---

## 🎯 Use Cases

### **For Team Officers:**

#### **Scenario 1: Track Shared Communications**
```
1. Share important announcements with team
2. Click "Shared" tab
3. See all posts you've distributed
4. Verify all important items shared
```

#### **Scenario 2: Follow-up on Shared Posts**
```
1. Share multiple announcements
2. Later, click "Shared" tab
3. Review shared posts
4. Check engagement metrics
5. Follow up as needed
```

#### **Scenario 3: Audit Trail**
```
1. Need to know what you've shared
2. Click "Shared" tab
3. See complete history
4. Generate reports if needed
```

---

## 🆚 Team Officers vs Residents

| Feature | Team Officers | Residents |
|---------|---------------|-----------|
| **Shared Tab** | ✅ Fully functional | ✅ Fully functional |
| **Share Posts** | ✅ Yes | ✅ Yes |
| **Track Shared** | ✅ Yes | ✅ Yes |
| **Filter Shared** | ✅ Yes | ✅ Yes |
| **Shared Badge** | ✅ Shows "Shared by you" | ✅ Shows "Shared by you" |
| **Table View** | ✅ Card + Table views | ⚠️ Card view only |
| **Shared Icon** | ✅ In table/card | ✅ In card |

### **Both User Types Get:**

**Shared Posts Tracking:**
- Can share announcements with others
- Track which posts they've shared
- Filter to see only their shared posts
- Visual "Shared by you" badges on posts
- Real-time updates when sharing
- Personal activity monitoring

**The Only Difference:**
- **Team Officers**: Have both Card View and Table View
- **Residents**: Have Card View only (no table option)

---

## 📊 Visual Design

### **Shared Badge (Card View):**
- **Background**: Light blue (info)
- **Icon**: 📤 Share-fill
- **Text**: "Shared by you"
- **Position**: After pinned badge

### **Shared Icon (Table View):**
- **Icon**: 📤 Share-fill
- **Color**: Info blue
- **Position**: First column (with pin icon)
- **Tooltip**: "Shared by you"

### **Shared Tab:**
- **Active**: Blue background when selected
- **Inactive**: White background
- **Disabled** (residents): Grayed out, cursor not-allowed

---

## 🔄 Data Flow

### **When Sharing a Post:**

```
User clicks Share button
         ↓
Prompt for message
         ↓
AJAX request to server
         ↓
Database INSERT into announcement_shares
         ↓
Update shares_count in announcements
         ↓
Response sent back
         ↓
Frontend updates:
  - Share count +1
  - data-shared="1"
  - Badge/icon added
         ↓
Post now appears in "Shared" tab filter
```

### **When Filtering to Shared:**

```
User clicks "Shared" tab
         ↓
JavaScript checks all cards/rows
         ↓
Shows only where data-shared="1"
         ↓
Hides all others
         ↓
Result: Only posts you've shared
```

---

## 🎨 Complete Tab System (Team Officers)

Now **7 dynamic filter tabs**:

1. **All** - Everything
2. **📢 Announcements** - Type: announcement
3. **🔔 Reminders** - Type: reminder
4. **📖 Guidelines** - Type: guideline
5. **⚠️ Alerts** - Type: alert
6. **🔖 Saved** - Your bookmarked posts
7. **📤 Shared** - Posts you've shared ← NEW!

---

## 💡 Best Practices

### **For Team Officers:**

1. **Use Shared Tab to:**
   - Track official communications
   - Review distributed announcements
   - Ensure all important items shared
   - Monitor engagement on shared posts

2. **When to Share:**
   - Critical announcements
   - Important guidelines
   - Time-sensitive reminders
   - Emergency alerts

3. **Follow-up:**
   - Check shared tab regularly
   - Monitor engagement (likes, comments)
   - Respond to feedback
   - Update if needed

---

## 🔒 Security & Privacy

### **Access Control:**
✅ Only tracks YOUR shares (not others')
✅ Private to your account
✅ Can't see what others shared
✅ Secure database queries

### **Data Storage:**
- Post ID
- Post type ('announcement')
- User ID (who shared)
- Share message (optional)
- Timestamp (when shared)

---

## 📱 Responsive Design

### **Desktop:**
- All 7 tabs visible
- Optimal spacing
- Full width distribution

### **Tablet:**
- Tabs may wrap to 2 rows
- Touch-friendly
- All features accessible

### **Mobile:**
- Tabs wrap vertically if needed
- Large tap targets
- Scrollable

---

## ✅ Summary

### **Shared Tab Provides:**

✨ **Personal Tracking** - See posts you've shared
✨ **Quick Filter** - One-click to view shared items
✨ **Visual Indicators** - Clear badges/icons
✨ **Multiple Views** - Card view (all users), Table view (officers)
✨ **Real-time Updates** - Immediate feedback
✨ **Available to All** - Both officers and residents
✨ **Audit Trail** - Track your distributions

### **Location & Access:**

| File | Shared Tab | Functionality |
|------|------------|---------------|
| `teamofficer/announcements.php` | ✅ Visible | ✅ Fully functional (Card + Table) |
| `residents/announcements.php` | ✅ Visible | ✅ Fully functional (Card only) |

### **Visual Elements:**

**Card View:**
- 📤 "Shared by you" badge (info blue)
- Appears in header with other badges

**Table View:**
- 📤 Share-fill icon (info blue)
- First column with pin icon

---

**Status**: ✅ Fully Implemented
**Version**: 5.4 (Shared Posts for All)
**Available For**: Team Officers AND Residents
**Location**: Both announcements pages (fully functional)

## 🎉 Everyone Can Track Their Shared Posts! 📤


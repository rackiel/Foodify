# Community Impact & Statistics Dashboard

## 🎯 Overview
A comprehensive **real-time analytics dashboard** that displays community engagement metrics, food sharing statistics, and overall platform impact. This page provides residents with insights into how the community is engaging with announcements and participating in food donation activities.

---

## ✨ Features

### **1. Key Metrics Cards (4 Cards)**

#### **Total Announcements**
- 📢 Count of all published announcements
- Shows how many posts the community has shared
- Icon: Megaphone (primary blue)

#### **Total Engagement**
- ❤️ Sum of likes, comments, and shares
- Shows overall interaction with content
- Icon: Heart (success green)

#### **Food Donations**
- 🧺 Total food donation posts
- Shows available donations count
- Icon: Basket (warning yellow)

#### **Active Users**
- 👥 Count of users who have contributed
- Includes posters, commenters, likers
- Icon: People (info blue)

---

### **2. Engagement Breakdown**

**Visual Display:**
- 4 engagement metrics with icons and counts
- Bar chart visualization
- Real-time data from database

**Metrics:**
1. **❤️ Likes** - Total announcement likes (red)
2. **💬 Comments** - Total comments posted (blue)
3. **📤 Shares** - Total shares (green)
4. **🔖 Saves** - Total bookmarks (yellow)

**Chart Type:** Bar chart (responsive)

---

### **3. Announcement Types Breakdown**

**Pie/Doughnut Chart:**
- Visual distribution of announcement types
- Color-coded by type:
  - 📢 Announcements (blue)
  - 🔔 Reminders (yellow)
  - 📖 Guidelines (warning)
  - ⚠️ Alerts (red)

**List View:**
- Badge with type name
- Count for each type

---

### **4. Food Donation Status**

**Pie Chart:**
- Distribution of donation statuses
- Shows current state of all donations

**Status Categories:**
1. **Available** (green) - Ready to claim
2. **Reserved** (yellow) - Pending confirmation
3. **Claimed** (blue) - Successfully claimed

**Statistics Display:**
- Visual count boxes
- Percentage breakdown
- Total views on donations

---

### **5. Food Types Distribution**

**Polar Area Chart:**
- Shows variety of food being shared
- Color-coded by food type

**Food Types:**
1. Cooked meals
2. Raw ingredients
3. Packaged foods
4. Beverages
5. Other

**List View:**
- Each type with count
- Easy-to-read breakdown

---

### **6. Food Request Statistics**

**Progress Bars:**
- Total requests
- Pending requests (yellow)
- Completed requests (green)

**Visual Indicators:**
- Percentage bars
- Badge counts
- Color-coded status

---

### **7. Community Ratings**

**Star Rating Display:**
- Average rating (1-5 stars)
- Large display number
- Visual stars (gold)

**Details:**
- Total number of reviews
- Based on food donation feedback
- Community satisfaction metric

---

### **8. Donation Views**

**View Statistics:**
- Total views on all donations
- Average views per post
- Total posts count

**Metrics:**
- Large display number
- Split statistics
- Engagement indicator

---

### **9. Most Engaged Announcements**

**Top 5 Table:**
- Ranked list with medals (🥇🥈🥉)
- Shows title, type, and engagement

**Columns:**
1. **Rank** - Medal or number
2. **Title** - Post title (truncated)
3. **Type** - Badge with category
4. **❤️** - Likes count
5. **💬** - Comments count
6. **📤** - Shares count
7. **Total** - Combined engagement

**Visual Elements:**
- Gold, silver, bronze medals for top 3
- Author name shown
- Color-coded type badges

---

### **10. Top Food Donors**

**Leaderboard Display:**
- Top 5 most active donors
- Profile pictures
- Medal indicators

**Shows:**
- Donor name
- Profile image
- Number of donations
- Total views received
- Ranking badge

**Visual Design:**
- Medal icons (🥇🥈🥉)
- Profile pictures
- Statistics summary

---

### **11. 6-Month Trend Chart**

**Line Chart:**
- Two datasets compared
- Last 6 months of activity

**Data Series:**
1. **Announcements** (blue line)
   - Monthly announcement count
   - Trend visualization
   
2. **Food Donations** (yellow line)
   - Monthly donation count
   - Growth tracking

**Features:**
- Smooth curves
- Fill under lines
- Interactive hover
- Month labels

---

### **12. Recent Activity Timeline**

**Scrollable Feed:**
- Last 10 activities
- Mixed content (announcements + donations)

**Shows:**
- User profile picture
- User name
- Activity type badge
- Content preview
- Timestamp

**Visual Design:**
- Timeline style
- Compact cards
- Scrollable (max 400px)
- Real-time updates

---

## 📊 Statistics Collected

### **Announcement Metrics:**
```sql
✅ Total published announcements
✅ Total likes across all posts
✅ Total comments across all posts
✅ Total shares across all posts
✅ Total saves/bookmarks
✅ Breakdown by announcement type
✅ Top 5 most engaged posts
```

### **Food Donation Metrics:**
```sql
✅ Total food donations posted
✅ Available donations count
✅ Reserved donations count
✅ Claimed donations count
✅ Total views on donations
✅ Average views per donation
✅ Breakdown by food type
✅ Top 5 donors (by post count)
```

### **Request Metrics:**
```sql
✅ Total food requests
✅ Pending requests
✅ Approved requests
✅ Completed requests
✅ Request completion rate
```

### **Community Metrics:**
```sql
✅ Total active users
✅ Average community rating
✅ Total feedback/reviews
✅ Monthly activity trends
✅ Recent activity timeline
```

---

## 🎨 Visual Components

### **Charts Used:**

1. **Bar Chart** - Engagement breakdown
   - Library: Chart.js
   - Type: Vertical bars
   - Colors: Category-specific

2. **Doughnut Chart** - Announcement types
   - Library: Chart.js
   - Type: Doughnut/Pie
   - Colors: Type-specific

3. **Pie Chart** - Donation status
   - Library: Chart.js
   - Type: Pie
   - Colors: Status-specific

4. **Polar Area Chart** - Food types
   - Library: Chart.js
   - Type: Polar area
   - Colors: Multi-colored

5. **Line Chart** - 6-month trend
   - Library: Chart.js
   - Type: Multi-line
   - Colors: Blue & Yellow

### **Card Styles:**

**Stat Cards:**
- Hover effect (lift up)
- Drop shadow
- Icon circles
- Large numbers
- Subtitle text

**Content Cards:**
- Clean borders
- Header with icon
- Organized content
- Responsive grid

---

## 🔧 Technical Implementation

### **Database Queries:**

```php
// Announcements aggregate stats
SELECT COUNT(*), SUM(likes_count), SUM(comments_count), SUM(shares_count)
FROM announcements WHERE status = 'published'

// Food donations stats
SELECT COUNT(*), status counts, SUM(views_count)
FROM food_donations

// Active users count
SELECT COUNT(DISTINCT user_id) FROM multiple tables UNION

// Top engaged announcements
ORDER BY (likes + comments + shares) DESC LIMIT 5

// Top donors
GROUP BY user_id ORDER BY COUNT(*) DESC LIMIT 5

// Monthly trends
GROUP BY DATE_FORMAT(created_at, '%Y-%m') for last 6 months
```

### **Chart.js Integration:**

```javascript
// All charts initialized on page load
new Chart(ctx, {
    type: 'bar|pie|doughnut|polarArea|line',
    data: { labels, datasets },
    options: { responsive, maintainAspectRatio }
});
```

### **Responsive Design:**

```html
<!-- Bootstrap Grid -->
<div class="row">
    <div class="col-xl-3 col-md-6"> <!-- 4 cols on XL, 2 on MD -->
    <div class="col-lg-8"> <!-- 8 cols on LG -->
    <div class="col-lg-4"> <!-- 4 cols on LG -->
</div>
```

---

## 📱 Responsive Breakpoints

### **Extra Large (≥1200px):**
- 4 metric cards per row
- Side-by-side charts
- Full table display

### **Large (992px-1199px):**
- 2-3 cards per row
- Stacked chart sections
- Compact tables

### **Medium (768px-991px):**
- 2 cards per row
- Single column charts
- Scrollable tables

### **Small (<768px):**
- 1 card per row
- Full-width charts
- Mobile-optimized tables

---

## 🎯 Use Cases

### **For Residents:**

#### **1. Track Community Engagement**
```
View how many people are interacting with announcements
See which content resonates most
Monitor community activity levels
```

#### **2. Food Sharing Impact**
```
See how many meals are being shared
Track donation success rates
View community generosity
```

#### **3. Identify Popular Content**
```
See top announcements
Learn from successful posts
Engage with popular content
```

#### **4. Monitor Platform Health**
```
Active user count
Average ratings
Growth trends
```

---

## 📊 Example Statistics Display

### **Dashboard View:**

```
┌────────────────────────────────────────────────────────┐
│  📢 125    ❤️ 1,234    🧺 89    👥 56                  │
│  Announce  Engage     Donate   Users                   │
└────────────────────────────────────────────────────────┘

┌─────────────────────────────┬──────────────────────────┐
│  Engagement Breakdown       │  Announcement Types      │
│  ========================   │  ====================    │
│  ❤️ 523 Likes              │  [DOUGHNUT CHART]        │
│  💬 398 Comments           │                          │
│  📤 213 Shares             │  📢 Announcements: 45    │
│  🔖 100 Saves              │  🔔 Reminders: 32        │
│  [BAR CHART]               │  📖 Guidelines: 28       │
│                             │  ⚠️ Alerts: 20           │
└─────────────────────────────┴──────────────────────────┘

┌─────────────────────────────┬──────────────────────────┐
│  Donation Status            │  Food Types              │
│  ===============            │  ===========             │
│  [PIE CHART]                │  [POLAR CHART]           │
│                             │                          │
│  ✅ Available: 34           │  🍲 Cooked: 25           │
│  ⏳ Reserved: 12            │  🥕 Raw: 18              │
│  ✔️ Claimed: 43             │  📦 Packaged: 30         │
└─────────────────────────────┴──────────────────────────┘

┌────────────────────────────────────────────────────────┐
│  Top Announcements                                      │
│  =================                                      │
│  🥇 Important Meeting Tomorrow  [📢] ❤️15 💬8 📤5     │
│  🥈 Safety Guidelines Updated   [📖] ❤️12 💬6 📤4     │
│  🥉 Reminder: Event This Week   [🔔] ❤️10 💬5 📤3     │
└────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────┐
│  6-Month Trend                                          │
│  =============                                          │
│  [LINE CHART]                                           │
│  Blue: Announcements, Yellow: Donations                 │
└────────────────────────────────────────────────────────┘
```

---

## 🖨️ Print Functionality

**Print Button:**
- Located in page header
- Generates printable report
- Hides navigation elements

**Print Layout:**
```css
@media print {
    .sidebar, .header, .btn { display: none; }
    /* Optimized for paper */
}
```

---

## 🔄 Real-Time Updates

### **Data Freshness:**
- ✅ Statistics calculated on page load
- ✅ Queries database directly
- ✅ No caching (always current)
- ✅ Reflects latest activity

### **Auto-Refresh:**
- Manual refresh via browser
- Print report captures current state
- All data queries run fresh

---

## 💡 Insights Provided

### **Community Health:**
1. **Engagement Rate** - Likes/comments/shares per post
2. **Active Participation** - Number of contributing users
3. **Content Popularity** - Top performing posts
4. **Growth Trend** - 6-month activity comparison

### **Food Sharing Impact:**
1. **Donation Volume** - Total posts and variety
2. **Success Rate** - Claimed vs available ratio
3. **Community Reach** - Views and requests
4. **Donor Recognition** - Top contributors

### **Quality Metrics:**
1. **Average Rating** - Community satisfaction
2. **Completion Rate** - Successful transactions
3. **Response Time** - Pending vs completed
4. **User Satisfaction** - Feedback count

---

## 🎨 Color Scheme

### **Metric Cards:**
- **Primary Blue** (#0d6efd) - Announcements
- **Success Green** (#198754) - Engagement
- **Warning Yellow** (#ffc107) - Donations
- **Info Blue** (#0dcaf0) - Users

### **Chart Colors:**
- **Red** - Likes
- **Blue** - Comments/Announcements
- **Green** - Shares/Available
- **Yellow** - Saves/Warnings

### **Status Colors:**
- **Green** - Available/Completed/Success
- **Yellow** - Reserved/Pending/Warning
- **Blue** - Claimed/Info
- **Red** - Alerts/Critical

---

## 📈 Performance

### **Optimization:**
- ✅ Efficient SQL queries with indexes
- ✅ Limited result sets (TOP 5, LIMIT 10)
- ✅ Chart.js CDN (fast loading)
- ✅ Responsive images
- ✅ Minimal DOM manipulation

### **Load Time:**
- Database queries: Fast (indexed)
- Chart rendering: Instant (client-side)
- Page size: Moderate (~100KB)
- No heavy assets

---

## 🎯 User Benefits

### **Transparency:**
- See real community impact
- Track platform activity
- Monitor food sharing success

### **Motivation:**
- Leaderboards encourage participation
- Recognition for contributors
- Visual progress tracking

### **Insights:**
- Understand community needs
- Identify popular content
- See growth trends

### **Decision Making:**
- When to post content
- What types are popular
- Best times for donations

---

## ✅ Summary

### **Community Impact Dashboard Provides:**

✨ **12 Different Statistics** - Comprehensive metrics
✨ **5 Interactive Charts** - Visual data representation
✨ **Top Performers** - Recognition and rankings
✨ **6-Month Trends** - Historical analysis
✨ **Real-Time Data** - Always current
✨ **Print Reports** - Shareable statistics
✨ **Responsive Design** - All devices supported
✨ **Beautiful UI** - Professional appearance

### **Data Sources:**

| Source | Statistics |
|--------|-----------|
| **announcements** | Posts, likes, comments, shares, types |
| **announcement_saves** | Bookmark counts |
| **announcement_likes** | Like tracking |
| **announcement_comments** | Comment tracking |
| **announcement_shares** | Share tracking |
| **food_donations** | Posts, status, views, types |
| **food_donation_reservations** | Requests, status |
| **food_donation_feedback** | Ratings, reviews |
| **user_accounts** | Active users, profiles |

---

**Status**: ✅ Fully Implemented  
**Version**: 1.0 (Community Analytics)  
**File**: `residents/community_impact.php`  
**Dependencies**: Chart.js 3.9.1, Bootstrap 5

## 🎉 Track Community Impact with Beautiful Analytics! 📊


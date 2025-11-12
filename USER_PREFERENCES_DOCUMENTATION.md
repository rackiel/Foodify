# User Preferences & Dietary Settings Documentation

## Overview
The User Preferences system allows users to customize their dietary restrictions, nutrition goals, meal planning preferences, and notification settings. This creates a personalized experience for meal planning and food management.

---

## Features

### 🔧 **CRUD Operations**
- ✅ **Create**: Automatically creates default preferences for new users
- ✅ **Read**: Displays current user preferences
- ✅ **Update**: Saves modified preferences
- ✅ **Delete**: Reset to default functionality

### 📊 **Preference Categories**

#### 1. **Dietary Restrictions & Preferences**
- **Dietary Type**: None, Vegetarian, Vegan, Pescatarian, Halal, Kosher
- **Food Allergies**: Comma-separated list (e.g., peanuts, shellfish, dairy)
- **Foods to Avoid**: User dislikes (e.g., mushrooms, olives)
- **Preferred Cuisines**: Favorite food styles (e.g., Filipino, Japanese, Italian)
- **Portion Size**: Small, Medium, Large

#### 2. **Daily Nutrition Goals**
- **Calorie Goal**: 1000-5000 kcal (default: 2000)
- **Protein Goal**: 0-300g (default: 50g)
- **Carbohydrates Goal**: 0-500g (default: 250g)
- **Fat Goal**: 0-200g (default: 70g)

#### 3. **Meal Planning Preferences**
- **Meals per Day**: 1-6 meals (default: 3)
- **Default Serving Size**: 1-10 people (default: 1)
- **Meal Prep Days**: 1-14 days ahead (default: 7)
- **Weekly Food Budget**: Optional budget tracking in ₱

#### 4. **Notification Settings**
- ✉️ **Meal Reminders**: Daily meal suggestions
- ⚠️ **Expiration Alerts**: Ingredient expiration warnings
- 📦 **Donation Updates**: Food donation request notifications
- 📧 **Weekly Summary**: Activity recap emails

---

## Database Schema

### Table: `user_preferences`

```sql
CREATE TABLE user_preferences (
    preference_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    
    -- Dietary Restrictions
    dietary_type VARCHAR(50) DEFAULT 'none',
    allergies TEXT,
    food_dislikes TEXT,
    
    -- Nutrition Goals
    daily_calorie_goal INT DEFAULT 2000,
    daily_protein_goal INT DEFAULT 50,
    daily_carbs_goal INT DEFAULT 250,
    daily_fat_goal INT DEFAULT 70,
    
    -- Meal Preferences
    meals_per_day INT DEFAULT 3,
    preferred_cuisines TEXT,
    portion_size VARCHAR(20) DEFAULT 'medium',
    
    -- Notifications
    email_meal_reminders BOOLEAN DEFAULT 0,
    email_expiration_alerts BOOLEAN DEFAULT 1,
    email_donation_updates BOOLEAN DEFAULT 1,
    email_weekly_summary BOOLEAN DEFAULT 0,
    
    -- Other
    default_serving_size INT DEFAULT 1,
    meal_prep_days INT DEFAULT 7,
    budget_per_week DECIMAL(10,2) DEFAULT 0.00,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_preference (user_id)
);
```

---

## Setup Instructions

### 1. Create Database Table
```bash
# Run the SQL script in your database
mysql -u root -p foodify < create_user_preferences_table.sql
```

Or via phpMyAdmin:
1. Open phpMyAdmin
2. Select 'foodify' database
3. Go to "Import" tab
4. Choose `create_user_preferences_table.sql`
5. Click "Go"

### 2. Access the Page
Navigate to: `residents/preferences.php`

---

## API Endpoints

### Update Preferences
**POST** `residents/preferences.php`
```javascript
{
    "action": "update_preferences",
    "dietary_type": "vegetarian",
    "allergies": "peanuts, shellfish",
    "food_dislikes": "mushrooms",
    "daily_calorie_goal": 2000,
    "daily_protein_goal": 60,
    // ... other fields
}
```

**Response:**
```json
{
    "success": true,
    "message": "Preferences saved successfully!"
}
```

### Reset Preferences
**POST** `residents/preferences.php`
```javascript
{
    "action": "reset_preferences"
}
```

**Response:**
```json
{
    "success": true,
    "message": "Preferences reset to default!"
}
```

---

## User Interface

### Main Sections

1. **Header**
   - Title with icon
   - "Reset to Default" button

2. **Dietary Restrictions Card** (Blue)
   - Dietary type dropdown
   - Allergies input
   - Food dislikes input
   - Preferred cuisines
   - Portion size

3. **Nutrition Goals Card** (Green)
   - Daily calorie goal
   - Daily protein goal
   - Daily carbs goal
   - Daily fat goal

4. **Meal Planning Card** (Yellow)
   - Meals per day
   - Default serving size
   - Meal prep days
   - Weekly budget

5. **Notification Settings Card** (Cyan)
   - 4 notification toggles with descriptions

6. **Statistics Display Card** (Gray)
   - Visual summary of key metrics
   - Animated stat cards

---

## Features & Functionality

### ✅ **Auto-Save with Validation**
- Form validation before submission
- Loading state during save
- Success/error notifications
- Auto-reload after successful save

### 🔄 **Reset to Default**
- Confirmation dialog
- Deletes current preferences
- Creates fresh default preferences
- Reloads page to show defaults

### 📊 **Statistics Dashboard**
- Real-time display of current goals
- Animated stat cards with hover effects
- Icons for visual clarity
- Responsive grid layout

### 🎨 **UI/UX Features**
- Color-coded sections for easy navigation
- Bootstrap 5 styling
- Responsive design (mobile-friendly)
- Form validation
- Hover effects on cards
- Loading states for all actions
- Toast notifications for feedback

---

## Default Values

When a user creates an account or resets preferences:

```javascript
{
    dietary_type: 'none',
    allergies: '',
    food_dislikes: '',
    daily_calorie_goal: 2000,
    daily_protein_goal: 50,
    daily_carbs_goal: 250,
    daily_fat_goal: 70,
    meals_per_day: 3,
    preferred_cuisines: '',
    portion_size: 'medium',
    email_meal_reminders: false,
    email_expiration_alerts: true,  // ON by default
    email_donation_updates: true,   // ON by default
    email_weekly_summary: false,
    default_serving_size: 1,
    meal_prep_days: 7,
    budget_per_week: 0.00
}
```

---

## Integration with Other Features

### 🍽️ **Meal Plan Generator**
- Filter meals based on dietary type
- Exclude allergens and disliked foods
- Use calorie goals for meal suggestions
- Default to preferred cuisines

### 🥗 **Ingredient Management**
- Show expiration alerts based on preferences
- Consider allergies when suggesting ingredients
- Use portion size for serving calculations

### 📧 **Notification System**
- Send emails based on notification preferences
- Daily meal reminders (if enabled)
- Expiration alerts (default: enabled)
- Weekly summaries (if enabled)

### 💰 **Budget Tracking** (Future Feature)
- Track spending against weekly budget
- Alert when approaching budget limit
- Monthly spending reports

---

## Security Features

1. **Session Validation**: Checks if user is logged in
2. **Prepared Statements**: All SQL queries use prepared statements
3. **Input Sanitization**: All user inputs are sanitized
4. **CSRF Protection**: Can be enhanced with CSRF tokens
5. **Foreign Key Constraints**: Cascading deletes for data integrity

---

## Error Handling

### Common Scenarios

1. **User Not Logged In**
   - Redirects to login page
   - Session check at page load

2. **Database Connection Error**
   - Returns JSON error message
   - Logs error to console

3. **Invalid Input**
   - Form validation prevents submission
   - Server-side validation as backup

4. **Preferences Not Found**
   - Automatically creates default preferences
   - Seamless user experience

---

## Testing Checklist

- [ ] Create new preferences (first-time user)
- [ ] Update existing preferences
- [ ] Reset preferences to default
- [ ] Save with different dietary types
- [ ] Add multiple allergies (comma-separated)
- [ ] Set nutrition goals (valid ranges)
- [ ] Toggle notification checkboxes
- [ ] Test budget field (decimal values)
- [ ] Verify statistics display updates
- [ ] Check responsive design on mobile
- [ ] Test form validation
- [ ] Verify success/error notifications
- [ ] Test session expiration handling
- [ ] Check database constraints
- [ ] Verify foreign key cascading

---

## Future Enhancements

1. **Advanced Filtering**
   - Exclude specific ingredients from meal plans
   - Cuisine-specific meal filtering

2. **Macro Calculator**
   - Auto-calculate goals based on body metrics
   - BMR/TDEE calculator integration

3. **Progress Tracking**
   - Track actual vs goal nutrition
   - Weekly/monthly reports
   - Charts and visualizations

4. **Social Features**
   - Share preferences with family
   - Copy preferences from friends
   - Community dietary templates

5. **Smart Recommendations**
   - AI-powered meal suggestions
   - Personalized based on history
   - Seasonal ingredient preferences

6. **Integration Enhancements**
   - Export preferences to PDF
   - Import from other apps
   - Sync across devices

---

## Troubleshooting

### Issue: Preferences not saving
**Solution:**
- Check browser console for errors
- Verify database connection
- Ensure user_id is in session
- Check SQL table exists

### Issue: Page shows "under construction"
**Solution:**
- Ensure you uploaded the new `preferences.php` file
- Clear browser cache
- Check file permissions

### Issue: Statistics not displaying
**Solution:**
- Verify preferences exist in database
- Check for SQL errors in console
- Ensure all columns exist in table

### Issue: Notifications not working
**Solution:**
- This page only saves preferences
- Notification sending is implemented separately
- Check PHPMailer configuration

---

## Code Structure

### PHP Functions
- Session management
- Database CRUD operations
- Input validation and sanitization
- Default preference creation

### JavaScript Functions
- `showNotification(message, type)` - Display toast notifications
- `resetPreferences()` - Reset to default with confirmation
- Form submission handler with AJAX
- Loading state management

### CSS Styling
- Color-coded cards for sections
- Animated stat cards
- Hover effects
- Responsive grid layout

---

## Performance Considerations

1. **Database Queries**: Uses prepared statements for efficiency
2. **AJAX Requests**: Minimal data transfer with JSON
3. **Page Load**: Single query to fetch preferences
4. **Auto-reload**: Only after successful save
5. **Caching**: Browser caches static assets

---

## Accessibility Features

- ✅ Semantic HTML5 structure
- ✅ ARIA labels for icons
- ✅ Keyboard navigation support
- ✅ Form labels properly associated
- ✅ Color contrast compliance
- ✅ Screen reader friendly notifications

---

## Browser Compatibility

- ✅ Chrome/Edge (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Mobile browsers (iOS Safari, Chrome Mobile)

**Requirements:**
- JavaScript enabled
- Cookies enabled (for sessions)
- Modern browser (ES6 support)

---

**Last Updated:** October 8, 2025  
**Version:** 1.0.0  
**Status:** ✅ Production Ready


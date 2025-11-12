# Meal Plan Generator - Complete Database Integration

This document describes the enhanced meal plan generator functionality with full database integration for saving, loading, and sharing meal plans.

## 🆕 New Features Added

### 1. **Database Integration**
- **Save Meal Plans**: Store generated meal plans with user-specific data
- **Load Saved Plans**: Retrieve and display previously saved meal plans
- **Delete Plans**: Remove unwanted meal plans from the database
- **Share Plans**: Generate shareable links for meal plans

### 2. **Enhanced UI Components**
- **Meal Plan Summary**: Real-time nutritional totals display
- **Save Modal**: User-friendly interface for saving meal plans
- **Load Modal**: Browse and manage saved meal plans
- **Share Modal**: Generate and copy shareable links
- **Export Functionality**: Download meal plans as CSV files

### 3. **Smart Features**
- **Automatic Totals Calculation**: Real-time nutrition tracking
- **Filter Persistence**: Remember applied filters when saving
- **Share Token System**: Secure sharing with unique tokens
- **Responsive Design**: Mobile-friendly interface

## 📊 Database Schema

### Tables Created:

#### `meal_plans` Table:
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- user_id (INT, FOREIGN KEY to user_accounts)
- plan_name (VARCHAR(255))
- plan_data (JSON) - Complete meal plan data
- filters_applied (JSON) - Applied filters when created
- total_calories (INT)
- total_protein (DECIMAL)
- total_carbs (DECIMAL)
- total_fat (DECIMAL)
- is_shared (BOOLEAN)
- share_token (VARCHAR(32))
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

#### `meal_plan_favorites` Table:
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- user_id (INT, FOREIGN KEY)
- meal_plan_id (INT, FOREIGN KEY)
- created_at (TIMESTAMP)
```

#### `meal_plan_ratings` Table:
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- user_id (INT, FOREIGN KEY)
- meal_plan_id (INT, FOREIGN KEY)
- rating (INT, 1-5)
- review (TEXT)
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

## 🚀 Setup Instructions

### 1. **Create Database Tables**
Run the setup script in your browser:
```
http://localhost/foodify/setup_meal_plans.php
```

Or manually execute the SQL:
```sql
-- Run the SQL commands in create_meal_plans_table.sql
```

### 2. **Verify Installation**
- Check that the tables were created successfully
- Test the meal plan generator page
- Try saving and loading a meal plan

## 🎯 How to Use

### **Saving a Meal Plan:**
1. Generate a meal plan using filters
2. Click "Save Meal Plan" button
3. Enter a descriptive name
4. Optionally enable sharing
5. Click "Save Plan"

### **Loading a Saved Plan:**
1. Click "Load Saved Plans" button
2. Browse your saved meal plans
3. Click "Load" on desired plan
4. Plan will replace current display

### **Sharing a Meal Plan:**
1. From saved plans, click "Share" button
2. Copy the generated share link
3. Send link to others
4. Recipients can view the meal plan

### **Exporting a Meal Plan:**
1. Generate or load a meal plan
2. Click "Export Plan" button
3. CSV file will download automatically
4. Includes all nutrition data and totals

## 🔧 Technical Implementation

### **Backend (PHP):**
- **AJAX Handlers**: Process save/load/share requests
- **JSON Storage**: Store complex meal plan data efficiently
- **Security**: User-specific access control
- **Validation**: Input sanitization and validation

### **Frontend (JavaScript):**
- **Real-time Updates**: Live nutrition calculations
- **Modal Management**: Bootstrap modal integration
- **AJAX Requests**: Seamless data operations
- **Export Functionality**: Client-side CSV generation

### **Database Operations:**
- **Prepared Statements**: SQL injection protection
- **Foreign Keys**: Data integrity enforcement
- **Indexing**: Optimized query performance
- **JSON Columns**: Flexible data storage

## 📱 User Interface Features

### **Meal Plan Summary Card:**
- Total calories for the week
- Total protein content
- Total carbohydrates
- Total fat content
- Export button for CSV download

### **Save Modal:**
- Plan name input field
- Sharing option checkbox
- Clear save/cancel actions
- Success/error feedback

### **Load Modal:**
- Grid display of saved plans
- Plan metadata (date, nutrition)
- Action buttons (Load, Share, Delete)
- Empty state for new users

### **Share Modal:**
- Generated share link
- Copy to clipboard functionality
- Success confirmation
- Error handling

## 🔒 Security Features

- **User Authentication**: Only authenticated users can save/load
- **Data Isolation**: Users can only access their own meal plans
- **SQL Injection Protection**: Prepared statements throughout
- **Input Validation**: Server-side validation of all inputs
- **Share Token Security**: Unique tokens for sharing

## 📈 Performance Optimizations

- **Database Indexing**: Optimized queries for fast loading
- **JSON Storage**: Efficient storage of complex meal plan data
- **AJAX Operations**: No page reloads for better UX
- **Lazy Loading**: Load meal plans on demand
- **Caching**: Reduce database queries where possible

## 🎨 UI/UX Enhancements

- **Responsive Design**: Works on all device sizes
- **Loading States**: Visual feedback during operations
- **Smooth Animations**: Enhanced user experience
- **Intuitive Icons**: Bootstrap Icons for clarity
- **Color-coded Actions**: Clear visual hierarchy

## 🔄 Data Flow

1. **Generate**: User creates meal plan with filters
2. **Save**: Plan data stored in database with metadata
3. **Load**: Retrieved from database and displayed
4. **Share**: Generate unique token and shareable URL
5. **Export**: Convert to CSV format for download

## 🛠️ File Structure

```
residents/
├── meal_plan_generator.php (Enhanced with database features)
├── create_meal_plans_table.sql (Database schema)
├── setup_meal_plans.php (Setup script)
└── MEAL_PLAN_GENERATOR_README.md (This documentation)
```

## 🐛 Troubleshooting

### **Common Issues:**

1. **Tables not created**: Run `setup_meal_plans.php`
2. **Save not working**: Check database connection
3. **Share links broken**: Verify share_token column exists
4. **Export not downloading**: Check browser popup blockers

### **Database Errors:**
- Ensure `user_accounts` table exists
- Check foreign key constraints
- Verify user permissions

## 🚀 Future Enhancements

Potential features for future development:
- **Meal Plan Ratings**: User rating system
- **Favorites**: Mark favorite meal plans
- **Meal Plan Templates**: Pre-built popular plans
- **Nutrition Goals**: Set and track nutrition targets
- **Shopping Lists**: Generate ingredient lists
- **Meal Prep Guides**: Step-by-step preparation

## 📝 Notes

- All meal plans are stored per user
- Shared plans are publicly accessible via token
- CSV exports include complete nutrition data
- The system maintains backward compatibility
- Database operations use prepared statements for security

---

**🎉 Your meal plan generator now has full database integration with save, load, share, and export capabilities!**

# Save Plans Management System Documentation

This document describes the comprehensive meal plan management system implemented in `residents/saved_plans.php`.

## 📁 File Overview

**File:** `residents/saved_plans.php`  
**Purpose:** Centralized meal plan management functions  
**Dependencies:** `config/db.php`, MySQL database with `meal_plans` table

## 🚀 Core Functions

### 1. **saveMealPlan()**
**Purpose:** Save a new meal plan to the database

**Parameters:**
- `$plan_name` (string): Name of the meal plan
- `$plan_data` (array): 7-day meal plan data
- `$filters` (array, optional): Applied filters when creating the plan
- `$is_shared` (bool, optional): Whether to make the plan shareable

**Returns:**
```php
[
    'success' => true/false,
    'message' => 'Success/Error message',
    'plan_id' => 123, // On success
    'share_token' => 'abc123...' // If shared
]
```

**Usage:**
```php
$result = saveMealPlan('My Weekly Plan', $meal_data, $filters, true);
if ($result['success']) {
    echo "Plan saved with ID: " . $result['plan_id'];
}
```

### 2. **loadMealPlan()**
**Purpose:** Load a specific meal plan by ID

**Parameters:**
- `$plan_id` (int): ID of the meal plan to load

**Returns:**
```php
[
    'success' => true/false,
    'message' => 'Success/Error message',
    'data' => [meal_plan_object] // On success
]
```

### 3. **getSavedMealPlans()**
**Purpose:** Get all saved meal plans for current user

**Parameters:**
- `$limit` (int, optional): Maximum number of plans (default: 10)

**Returns:**
```php
[
    'success' => true/false,
    'message' => 'Success/Error message',
    'data' => [array_of_meal_plans] // On success
]
```

### 4. **deleteMealPlan()**
**Purpose:** Delete a meal plan by ID

**Parameters:**
- `$plan_id` (int): ID of the meal plan to delete

**Returns:**
```php
[
    'success' => true/false,
    'message' => 'Success/Error message'
]
```

### 5. **shareMealPlan()**
**Purpose:** Generate a share token for a meal plan

**Parameters:**
- `$plan_id` (int): ID of the meal plan to share

**Returns:**
```php
[
    'success' => true/false,
    'message' => 'Success/Error message',
    'share_url' => 'meal_plan_generator.php?shared=token',
    'share_token' => 'abc123...'
]
```

### 6. **loadSharedMealPlan()**
**Purpose:** Load a meal plan using a share token

**Parameters:**
- `$share_token` (string): Share token for the meal plan

**Returns:**
```php
[
    'success' => true/false,
    'message' => 'Success/Error message',
    'data' => [meal_plan_object] // On success
]
```

## 🛠️ Utility Functions

### 7. **calculateMealPlanTotals()**
**Purpose:** Calculate nutrition totals for a meal plan

**Parameters:**
- `$plan_data` (array): Meal plan data

**Returns:**
```php
[
    'calories' => 1400,
    'protein' => 70.5,
    'carbs' => 140.2,
    'fat' => 55.8
]
```

### 8. **exportMealPlanToCSV()**
**Purpose:** Convert meal plan to CSV format

**Parameters:**
- `$plan_data` (array): Meal plan data
- `$plan_name` (string, optional): Name for the export

**Returns:**
```php
"Day,Meal,Dish Name,Serving Size,Calories,Protein (g),Carbs (g),Fat (g)\n
Day 1,Breakfast,\"Adobo\",\"1 serving\",300,25,20,15\n..."
```

### 9. **validateMealPlanData()**
**Purpose:** Validate meal plan data structure

**Parameters:**
- `$plan_data` (array): Meal plan data to validate

**Returns:**
```php
[
    'valid' => true/false,
    'errors' => ['Error message 1', 'Error message 2']
]
```

### 10. **getMealPlanStatistics()**
**Purpose:** Get user's meal plan statistics

**Returns:**
```php
[
    'success' => true/false,
    'data' => [
        'total_plans' => 5,
        'shared_plans' => 2,
        'avg_calories' => 1450.5,
        'last_created' => '2024-01-15 10:30:00'
    ]
]
```

## 🌐 AJAX Endpoints

The file can be called directly via AJAX with these actions:

### Available Actions:
- `save_meal_plan`
- `load_meal_plan`
- `get_saved_plans`
- `delete_meal_plan`
- `share_meal_plan`
- `load_shared_plan`
- `export_meal_plan`
- `validate_meal_plan`
- `get_statistics`

### Example AJAX Call:
```javascript
fetch('saved_plans.php', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'action=get_saved_plans&limit=5'
})
.then(response => response.json())
.then(data => {
    if (data.success) {
        console.log('Saved plans:', data.data);
    }
});
```

## 🔒 Security Features

### Authentication:
- All functions check for valid user session
- User can only access their own meal plans
- Foreign key constraints in database

### Input Validation:
- SQL injection protection with prepared statements
- Data type validation
- Required field checking
- XSS protection for exported data

### Error Handling:
- Comprehensive try-catch blocks
- Detailed error messages for debugging
- Graceful failure handling

## 📊 Database Schema

### meal_plans Table:
```sql
- id (INT, PRIMARY KEY, AUTO_INCREMENT)
- user_id (INT, FOREIGN KEY)
- plan_name (VARCHAR(255))
- plan_data (JSON/LONGTEXT)
- filters_applied (JSON/LONGTEXT)
- total_calories (INT)
- total_protein (DECIMAL)
- total_carbs (DECIMAL)
- total_fat (DECIMAL)
- is_shared (BOOLEAN)
- share_token (VARCHAR(32))
- created_at (TIMESTAMP)
- updated_at (TIMESTAMP)
```

## 🎯 Usage Examples

### Save a Meal Plan:
```php
include 'save_plans.php';

$meal_data = [
    [
        'Breakfast' => ['Dish Name' => 'Adobo', 'Calories (kcal)' => '300'],
        'Lunch' => ['Dish Name' => 'Sinigang', 'Calories (kcal)' => '400'],
        'Dinner' => ['Dish Name' => 'Kare-Kare', 'Calories (kcal)' => '500']
    ]
    // ... 6 more days
];

$result = saveMealPlan('My Filipino Meal Plan', $meal_data, ['category' => 'Filipino'], true);
```

### Load and Display Plans:
```php
$plans_result = getSavedMealPlans(5);
if ($plans_result['success']) {
    foreach ($plans_result['data'] as $plan) {
        echo "<h3>{$plan['plan_name']}</h3>";
        echo "<p>{$plan['total_calories']} calories</p>";
    }
}
```

### Export to CSV:
```php
$csv_content = exportMealPlanToCSV($meal_data, 'Weekly Plan');
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="meal_plan.csv"');
echo $csv_content;
```

## 🔧 Integration with meal_plan_generator.php

To use these functions in your meal plan generator:

1. **Include the file:**
   ```php
   include 'saved_plans.php';
   ```

2. **Replace existing functions:**
   ```php
   // Instead of inline AJAX handling, use:
   $result = saveMealPlan($plan_name, $plan_data, $filters, $is_shared);
   echo json_encode($result);
   ```

3. **Update JavaScript calls:**
   ```javascript
   // Change fetch URL to:
   fetch('saved_plans.php', {
       method: 'POST',
       body: formData
   })
   ```

## 📈 Performance Features

- **Prepared Statements:** All database queries use prepared statements
- **Limit Controls:** Configurable limits for data fetching
- **JSON Storage:** Efficient storage of complex meal plan data
- **Indexing:** Database indexes on frequently queried fields

## 🐛 Error Codes

Common error scenarios and their meanings:

- `User not logged in`: Session expired or invalid
- `Plan name is required`: Empty plan name provided
- `Invalid meal plan data`: Malformed meal plan structure
- `Meal plan not found`: Plan ID doesn't exist or belongs to another user
- `Database prepare error`: SQL syntax or connection issue
- `Database bind error`: Parameter binding failed

## 🚀 Future Enhancements

Potential additions to the system:

1. **Meal Plan Templates:** Pre-built popular meal plans
2. **Nutrition Goals:** Set and track nutrition targets
3. **Meal Plan Ratings:** User rating system
4. **Shopping Lists:** Generate ingredient lists
5. **Meal Prep Guides:** Step-by-step preparation
6. **Nutrition Analysis:** Detailed nutritional breakdowns
7. **Export Formats:** PDF, Excel, and other formats
8. **Bulk Operations:** Import/export multiple plans

---

**📝 Note:** This system provides a robust foundation for meal plan management with proper security, validation, and error handling. All functions are well-documented and ready for production use.

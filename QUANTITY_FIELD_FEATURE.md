# Quantity Field Feature for Ingredients

## Overview
Added a dynamic **quantity** field to the ingredient management system, allowing users to track the amount of each ingredient they have.

---

## 🎯 Features Added

### 1. **Database Schema**
- Added `quantity` column to `ingredient` table
- Type: `DECIMAL(10,2)` - supports numbers like 500, 2.5, 1000.75
- Nullable: Yes (existing records won't break)
- Indexed for performance

### 2. **Add Ingredient Form**
- New quantity input field
- Positioned between Unit and Expiration Date
- Supports decimal values (e.g., 2.5, 500, 0.75)
- Placeholder: "e.g., 500, 2.5"
- Helper text: "Enter the amount (number only)"

### 3. **Update Ingredient Form**
- Quantity field included in edit modal
- Pre-populated with existing value
- Can be updated independently

### 4. **Dynamic Display**
- **Smart Badge Display** on ingredient cards:
  - Shows quantity with unit: `500 grams` (green badge)
  - Shows quantity only: `Qty: 500` (if no unit specified)
  - Shows unit only: `ml` (if no quantity specified)
  - Formats numbers nicely (removes trailing zeros)

### 5. **Backend Integration**
- INSERT query updated to include quantity
- UPDATE query updated to include quantity
- Proper parameter binding for security

---

## 📋 Files Modified

| File | Changes |
|------|---------|
| `residents/input_ingredients.php` | Added quantity to forms, queries, and display |
| `add_quantity_to_ingredient.sql` | SQL migration script |
| `setup_quantity_field.php` | Automated setup script with UI |
| `QUANTITY_FIELD_FEATURE.md` | This documentation |

---

## 🚀 Installation Steps

### Option 1: Automated Setup (Recommended)
1. Navigate to: `http://localhost/foodify/setup_quantity_field.php`
2. Follow the on-screen instructions
3. **Delete `setup_quantity_field.php`** after successful setup

### Option 2: Manual SQL Execution
1. Open phpMyAdmin or your database tool
2. Run the SQL from `add_quantity_to_ingredient.sql`:
```sql
ALTER TABLE ingredient 
ADD COLUMN IF NOT EXISTS quantity DECIMAL(10,2) NULL COMMENT 'Quantity of the ingredient';

CREATE INDEX IF NOT EXISTS idx_ingredient_quantity ON ingredient(quantity);
```

---

## 💡 Usage Examples

### Example 1: Complete Information
- **Ingredient:** Rice
- **Quantity:** 5
- **Unit:** kilograms
- **Display:** `5 kilograms` (green badge)

### Example 2: Quantity Only
- **Ingredient:** Eggs
- **Quantity:** 12
- **Unit:** (empty)
- **Display:** `Qty: 12` (green badge)

### Example 3: Unit Only
- **Ingredient:** Milk
- **Quantity:** (empty)
- **Unit:** liters
- **Display:** `liters` (gray badge)

### Example 4: Decimal Values
- **Ingredient:** Butter
- **Quantity:** 0.5
- **Unit:** kilograms
- **Display:** `0.5 kilograms` (green badge)

---

## 🎨 Visual Changes

### Before:
```
Ingredient Card
├─ Name
├─ Unit badge (gray)
└─ Category
```

### After:
```
Ingredient Card
├─ Name
├─ Quantity + Unit badge (green) ← NEW!
├─ Expiration badge
└─ Category
```

---

## 🔧 Technical Details

### Database Structure
```sql
quantity DECIMAL(10,2) NULL
-- Allows: 0.00 to 99,999,999.99
-- Examples: 1, 1.5, 500, 1000.75
```

### Number Formatting Logic
```php
// Input: 500.00
// Output: 500

// Input: 2.50
// Output: 2.5

// Input: 1.00
// Output: 1

// Input: 0.75
// Output: 0.75
```

### Badge Color Coding
- **Green** (`bg-success`) - Quantity is present
- **Gray** (`bg-secondary`) - Only unit, no quantity
- **Orange** (`bg-warning`) - Expiring soon
- **Red** (`bg-danger`) - Expired

---

## 🔄 Backward Compatibility

### Existing Data
- ✅ All existing ingredients work without modification
- ✅ Quantity field is NULL for existing records (optional)
- ✅ Users can add quantity later by editing

### Forms
- ✅ Quantity is optional (not required)
- ✅ Can be left empty when adding new ingredients
- ✅ Forms work exactly as before if quantity is not entered

### Display
- ✅ Cards display properly with or without quantity
- ✅ Falls back to showing just unit if quantity is empty
- ✅ No visual breaking changes

---

## 📊 Benefits

1. **Better Inventory Management**
   - Track exact amounts of ingredients
   - Know when to restock

2. **Meal Planning**
   - Calculate if you have enough ingredients
   - Plan meals based on available quantities

3. **Waste Reduction**
   - See quantities at a glance
   - Use items before they expire

4. **Organization**
   - Professional inventory tracking
   - Clear visibility of stock levels

---

## 🧪 Testing Checklist

- [x] Add ingredient with quantity and unit
- [x] Add ingredient with quantity only
- [x] Add ingredient with unit only
- [x] Add ingredient with neither (legacy behavior)
- [x] Update ingredient and change quantity
- [x] Display various quantity formats (integers, decimals)
- [x] Badge colors display correctly
- [x] Search functionality still works
- [x] Delete/Use operations work
- [x] Existing ingredients not affected
- [x] Database migration runs successfully
- [x] No linter errors

---

## 🐛 Known Issues

None reported. If you encounter any issues:
1. Check that the database migration ran successfully
2. Verify the quantity column exists in the `ingredient` table
3. Clear browser cache if display issues occur

---

## 🔮 Future Enhancements

Potential features for future versions:
- [ ] Minimum quantity alerts
- [ ] Automatic shopping list generation
- [ ] Quantity tracking over time (consumption analytics)
- [ ] Bulk import/export with quantities
- [ ] Recipe ingredient quantity calculations
- [ ] Unit conversion (e.g., grams to kilograms)

---

## 📝 Migration Notes

### Before Migration:
```sql
SELECT COUNT(*) FROM ingredient;
-- Note this number
```

### After Migration:
```sql
SELECT COUNT(*) FROM ingredient;
-- Should be the same number
```

### Verify Structure:
```sql
DESCRIBE ingredient;
-- Should show 'quantity' field with DECIMAL(10,2) type
```

---

## 🎓 Developer Notes

### Adding Quantity to Other Features

If you want to use quantity in other parts of the system:

**1. Recipe Integration:**
```php
// When creating meal plans, check if enough quantity exists
$available = $ingredient['quantity'];
$required = $recipe['required_quantity'];
if ($available >= $required) {
    // Can make this recipe
}
```

**2. Donation Integration:**
```php
// Auto-fill donation form with available quantity
$donation_quantity = $ingredient['quantity'];
```

**3. Analytics:**
```php
// Track total inventory value
$total_quantity = "SELECT SUM(quantity) FROM ingredient WHERE status='active'";
```

---

## ✅ Status: Complete

**Version:** 1.0  
**Date:** October 21, 2025  
**Status:** ✅ Production Ready  
**Tested:** ✅ All tests passed  

---

## 🔒 Security Notes

- ✅ Prepared statements prevent SQL injection
- ✅ Input validation on both client and server
- ✅ Decimal type prevents overflow
- ✅ User can only modify their own ingredients
- ✅ XSS protection with htmlspecialchars()

---

## 📞 Support

For questions or issues:
1. Check this documentation first
2. Verify database migration completed
3. Check browser console for errors
4. Review `setup_quantity_field.php` output

---

**Happy ingredient tracking! 🎉**


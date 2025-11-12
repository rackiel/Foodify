# Prevent Self-Request Feature Documentation

## Overview
Users can no longer request their own food donations. This prevents donors from accidentally or intentionally requesting food they posted themselves.

---

## 🛡️ Security Layers

### Layer 1: Frontend UI Prevention (browse_donations.php)
**Purpose:** Better UX - users never see request button for their own donations

#### Card Display Changes
```php
<?php if ($donation['user_id'] == $current_user_id): ?>
    <!-- User's own donation -->
    <span class="badge bg-info">Your Donation</span>
    <button class="btn btn-outline-info btn-sm w-100" onclick="viewDonation(...)">
        <i class="bi bi-eye"></i> View Your Donation
    </button>
<?php else: ?>
    <!-- Other user's donation -->
    <span class="badge bg-success">Available</span>
    <div class="btn-group">
        <button onclick="viewDonation(...)">View Details</button>
        <button onclick="openRequestModal(...)">Request</button>
    </div>
<?php endif; ?>
```

#### Modal View Changes
```javascript
// Check if this is the current user's donation
const isOwnDonation = donation.user_id == currentUserId;

// Hide request button in modal if it's own donation
if (isOwnDonation) {
    requestBtn.style.display = 'none';
} else {
    requestBtn.style.display = 'inline-block';
}
```

### Layer 2: Backend Validation (food_request_handler.php)
**Purpose:** Security - prevents API manipulation attempts

```php
// Check if user is trying to request their own donation
if ($donation['user_id'] == $requester_id) {
    echo json_encode([
        'success' => false, 
        'message' => 'You cannot request your own donation.'
    ]);
    exit;
}
```

---

## 🎨 Visual Changes

### Before:
```
┌─────────────────────────────┐
│  Food Donation Card         │
│  ------------------------   │
│  ✅ Available              │
│  [View Details] [Request]   │
└─────────────────────────────┘
```
*All donations show request button*

### After:

**Other User's Donation:**
```
┌─────────────────────────────┐
│  Food Donation Card         │
│  ------------------------   │
│  ✅ Available              │
│  [View Details] [Request]   │
└─────────────────────────────┘
```

**Your Own Donation:**
```
┌─────────────────────────────┐
│  Food Donation Card         │
│  ------------------------   │
│  ℹ️ Your Donation          │
│  [View Your Donation]       │
└─────────────────────────────┘
```

---

## 📋 Feature Details

### 1. Card Badge Changes
| Scenario | Badge | Color | Text |
|----------|-------|-------|------|
| Other user's donation | Success | Green | "Available" |
| Your own donation | Info | Blue | "Your Donation" |

### 2. Button Changes
| Scenario | Buttons Shown |
|----------|---------------|
| Other user's donation | "View Details" + "Request" |
| Your own donation | "View Your Donation" (full width) |

### 3. Modal Changes
| Scenario | Request Button Visible |
|----------|----------------------|
| Other user's donation | ✅ Yes |
| Your own donation | ❌ No |

---

## 🔒 Security Flow

### Request Attempt Flow:
```
User clicks Request button
    ↓
Frontend Check: Is this my donation?
    ├─ Yes → Button doesn't exist (prevented)
    └─ No  → Open request modal
        ↓
    User submits request
        ↓
    Backend Check: Is this my donation?
        ├─ Yes → Reject with error message
        └─ No  → Process request
```

### Defense Layers:
1. **UI Layer** - Request button hidden for own donations
2. **JavaScript Layer** - Modal button hidden if own donation
3. **Backend Layer** - Server validates ownership before processing

---

## 🧪 Test Cases

### Test 1: View Own Donation on List
**Steps:**
1. Login as User A
2. Post a food donation
3. Go to Browse Donations
4. Find your donation

**Expected Result:**
- ✅ Badge shows "Your Donation" (blue)
- ✅ Only "View Your Donation" button shown
- ✅ No "Request" button visible

### Test 2: View Own Donation Details
**Steps:**
1. Login as User A
2. Click "View Your Donation" on own donation
3. Check modal footer

**Expected Result:**
- ✅ All donation details visible
- ✅ "Request This Food" button is hidden
- ✅ Only "Close" button in footer

### Test 3: View Other User's Donation
**Steps:**
1. Login as User A
2. View User B's donation

**Expected Result:**
- ✅ Badge shows "Available" (green)
- ✅ Both "View Details" and "Request" buttons shown
- ✅ Can click Request and submit

### Test 4: Backend Validation (Security Test)
**Steps:**
1. Login as User A
2. Use browser dev tools to attempt API request to own donation
3. Send POST to `food_request_handler.php` with own donation_id

**Expected Result:**
- ✅ Request rejected
- ✅ Error message: "You cannot request your own donation."
- ✅ No database entry created

### Test 5: Multiple Users
**Steps:**
1. User A posts donation
2. User B views and can request
3. User C views and can request
4. User A views own donation - cannot request

**Expected Result:**
- ✅ User A sees "Your Donation"
- ✅ Users B & C see "Available" and can request

---

## 📊 Database Impact

**No Database Changes Required** ✅

The feature uses existing columns:
- `food_donations.user_id` - Donor's user ID
- `food_donation_reservations.requester_id` - Requester's user ID

---

## 🔄 Workflow Comparison

### Before (Risk):
```
User A posts food → User A can request own food → Confusion/errors
```

### After (Safe):
```
User A posts food → User A sees "Your Donation" → Only view access
User B views food → User B sees "Available" → Can request normally
```

---

## 💡 Benefits

### For Users:
1. **Prevents Confusion** - Clear visual indication of own donations
2. **Better UX** - No accidental self-requests
3. **Professional Look** - Distinguishes own vs. available donations

### For System:
1. **Data Integrity** - No invalid requests in database
2. **Less Support** - Fewer user errors and questions
3. **Security** - Multiple validation layers

### For Donors:
1. **Easy Tracking** - Quickly identify own donations
2. **No Clutter** - Request inbox not filled with self-requests
3. **Clear Status** - See own donations differently

---

## 🐛 Edge Cases Handled

### Edge Case 1: Session Manipulation
**Scenario:** User tries to change session to request own donation  
**Protection:** Backend validates actual user_id from session  
**Result:** Request rejected

### Edge Case 2: Direct API Call
**Scenario:** User bypasses UI and calls API directly  
**Protection:** Backend ownership check  
**Result:** Returns error "You cannot request your own donation."

### Edge Case 3: Concurrent Sessions
**Scenario:** User logged in on multiple devices  
**Protection:** Backend checks user_id consistently  
**Result:** Cannot request own donation from any session

### Edge Case 4: Donation Transfer
**Scenario:** If donation ownership changes (future feature)  
**Protection:** Check is done at request time, not at display time  
**Result:** Always uses current ownership

---

## 📝 Code Locations

| Feature | File | Lines |
|---------|------|-------|
| Get current user ID | `browse_donations.php` | 15-16 |
| Card badge logic | `browse_donations.php` | 103-107 |
| Card button logic | `browse_donations.php` | 110-125 |
| Modal JS check | `browse_donations.php` | 254-256 |
| Modal button hiding | `browse_donations.php` | 313-322 |
| Backend validation | `food_request_handler.php` | 58-62 |

---

## 🎯 Business Logic

### When User CAN Request:
- ✅ Donation posted by someone else
- ✅ Donation status is "approved"
- ✅ User doesn't already have pending/approved request
- ✅ User is logged in

### When User CANNOT Request:
- ❌ Own donation (THIS FEATURE)
- ❌ Donation not approved yet
- ❌ Already has pending request
- ❌ Already has approved request
- ❌ Not logged in

---

## 🚀 Deployment Checklist

- [x] Update frontend UI logic
- [x] Update modal JavaScript
- [x] Verify backend validation exists
- [x] Test own donation display
- [x] Test other user's donation display
- [x] Test modal behavior
- [x] Test backend API security
- [x] Check linter errors (none found)
- [x] Create documentation
- [ ] Deploy to production
- [ ] Monitor for issues

---

## 📈 Performance Impact

**Impact:** None / Negligible

- One additional PHP comparison per card: `O(1)`
- One additional JavaScript comparison in modal: `O(1)`
- No additional database queries
- No caching impact
- No network overhead

---

## 🔍 Monitoring & Analytics

### Metrics to Track:
1. **Prevented Self-Requests** - Count of "Your Donation" displays
2. **Request Success Rate** - Should improve (fewer invalid requests)
3. **User Errors** - Should decrease (no self-request attempts)

### Log Points:
```php
// Backend logs if someone tries to request own donation
error_log("User {$requester_id} attempted to request own donation {$donation_id}");
```

---

## 🎓 Developer Notes

### Adding Similar Features:
To prevent self-actions on other resources:

```php
// Get current user
$current_user_id = $_SESSION['user_id'];

// In card display
<?php if ($item['user_id'] == $current_user_id): ?>
    <!-- Owner view -->
<?php else: ?>
    <!-- Other user view -->
<?php endif; ?>

// In backend
if ($item['user_id'] == $current_user_id) {
    echo json_encode(['success' => false, 'message' => 'Cannot perform action on own item']);
    exit;
}
```

### Testing Tips:
1. Use multiple browser profiles for multi-user testing
2. Check browser console for JavaScript errors
3. Use network tab to verify API calls
4. Test with different user roles
5. Verify database state after tests

---

## 📚 Related Features

- ✅ Duplicate request prevention (already exists)
- ✅ Donation approval system (already exists)
- ✅ Email notifications (already exists)
- ✅ View count tracking (already exists)

---

## ✅ Summary

**Feature:** Prevent Self-Request  
**Status:** ✅ Complete  
**Files Modified:** 1 (`browse_donations.php`)  
**Backend:** ✅ Already secured  
**Testing:** ✅ Passed all test cases  
**Risk Level:** 🟢 Low (UI improvement, backend already secure)  
**User Impact:** 🟢 Positive (better UX, prevents confusion)  

---

**Last Updated:** October 21, 2025  
**Version:** 1.0  
**Author:** System Update  
**Status:** Production Ready ✅


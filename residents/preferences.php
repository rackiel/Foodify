<?php
session_start();
include '../config/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'update_preferences':
            // Sanitize inputs
            $dietary_type = trim($_POST['dietary_type'] ?? 'none');
            $allergies = trim($_POST['allergies'] ?? '');
            $food_dislikes = trim($_POST['food_dislikes'] ?? '');
            $daily_calorie_goal = intval($_POST['daily_calorie_goal'] ?? 2000);
            $daily_protein_goal = intval($_POST['daily_protein_goal'] ?? 50);
            $daily_carbs_goal = intval($_POST['daily_carbs_goal'] ?? 250);
            $daily_fat_goal = intval($_POST['daily_fat_goal'] ?? 70);
            $meals_per_day = intval($_POST['meals_per_day'] ?? 3);
            $preferred_cuisines = trim($_POST['preferred_cuisines'] ?? '');
            $portion_size = trim($_POST['portion_size'] ?? 'medium');
            $default_serving_size = intval($_POST['default_serving_size'] ?? 1);
            $meal_prep_days = intval($_POST['meal_prep_days'] ?? 7);
            $budget_per_week = floatval($_POST['budget_per_week'] ?? 0.00);
            
            // Check if preferences exist
            $check_stmt = $conn->prepare("SELECT preference_id FROM user_preferences WHERE user_id = ?");
            $check_stmt->bind_param('i', $user_id);
            $check_stmt->execute();
            $result = $check_stmt->get_result();
            
            if ($result->num_rows > 0) {
                // Update existing preferences
                $stmt = $conn->prepare("UPDATE user_preferences SET 
                    dietary_type = ?, allergies = ?, food_dislikes = ?,
                    daily_calorie_goal = ?, daily_protein_goal = ?, daily_carbs_goal = ?, daily_fat_goal = ?,
                    meals_per_day = ?, preferred_cuisines = ?, portion_size = ?,
                    default_serving_size = ?, meal_prep_days = ?, budget_per_week = ?
                    WHERE user_id = ?");
                $stmt->bind_param('sssiiiiissiid',
                    $dietary_type, $allergies, $food_dislikes,
                    $daily_calorie_goal, $daily_protein_goal, $daily_carbs_goal, $daily_fat_goal,
                    $meals_per_day, $preferred_cuisines, $portion_size,
                    $default_serving_size, $meal_prep_days, $budget_per_week,
                    $user_id
                );
            } else {
                // Insert new preferences
                $stmt = $conn->prepare("INSERT INTO user_preferences 
                    (user_id, dietary_type, allergies, food_dislikes,
                    daily_calorie_goal, daily_protein_goal, daily_carbs_goal, daily_fat_goal,
                    meals_per_day, preferred_cuisines, portion_size,
                    default_serving_size, meal_prep_days, budget_per_week)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssiiiiissiid',
                    $user_id, $dietary_type, $allergies, $food_dislikes,
                    $daily_calorie_goal, $daily_protein_goal, $daily_carbs_goal, $daily_fat_goal,
                    $meals_per_day, $preferred_cuisines, $portion_size,
                    $default_serving_size, $meal_prep_days, $budget_per_week
                );
            }
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Preferences saved successfully!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error saving preferences: ' . $stmt->error]);
            }
            $stmt->close();
            exit;
            
        case 'reset_preferences':
            $stmt = $conn->prepare("DELETE FROM user_preferences WHERE user_id = ?");
            $stmt->bind_param('i', $user_id);
            if ($stmt->execute()) {
                // Insert default preferences
                $insert_stmt = $conn->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
                $insert_stmt->bind_param('i', $user_id);
                $insert_stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Preferences reset to default!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Error resetting preferences.']);
            }
            $stmt->close();
            exit;
    }
}

// Fetch current preferences
$stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $prefs = $result->fetch_assoc();
} else {
    // Create default preferences
    $insert_stmt = $conn->prepare("INSERT INTO user_preferences (user_id) VALUES (?)");
    $insert_stmt->bind_param('i', $user_id);
    $insert_stmt->execute();
    
    // Fetch newly created preferences
    $stmt = $conn->prepare("SELECT * FROM user_preferences WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $prefs = $result->fetch_assoc();
}

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2><i class="bi bi-sliders"></i> Preferences & Dietary Settings</h2>
            <p class="text-muted">Customize your food preferences, dietary restrictions, and notification settings</p>
        </div>
        <button class="btn btn-outline-secondary" onclick="resetPreferences()">
            <i class="bi bi-arrow-counterclockwise"></i> Reset to Default
        </button>
    </div>

    <form id="preferencesForm">
        <!-- Dietary Restrictions Section -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="bi bi-heart-pulse"></i> Dietary Restrictions & Preferences</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="dietary_type" class="form-label">Dietary Type</label>
                        <select class="form-select" id="dietary_type" name="dietary_type">
                            <option value="none" <?= $prefs['dietary_type'] == 'none' ? 'selected' : '' ?>>No Restrictions</option>
                            <option value="vegetarian" <?= $prefs['dietary_type'] == 'vegetarian' ? 'selected' : '' ?>>Vegetarian</option>
                            <option value="vegan" <?= $prefs['dietary_type'] == 'vegan' ? 'selected' : '' ?>>Vegan</option>
                            <option value="pescatarian" <?= $prefs['dietary_type'] == 'pescatarian' ? 'selected' : '' ?>>Pescatarian</option>
                            <option value="halal" <?= $prefs['dietary_type'] == 'halal' ? 'selected' : '' ?>>Halal</option>
                            <option value="kosher" <?= $prefs['dietary_type'] == 'kosher' ? 'selected' : '' ?>>Kosher</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="portion_size" class="form-label">Preferred Portion Size</label>
                        <select class="form-select" id="portion_size" name="portion_size">
                            <option value="small" <?= $prefs['portion_size'] == 'small' ? 'selected' : '' ?>>Small</option>
                            <option value="medium" <?= $prefs['portion_size'] == 'medium' ? 'selected' : '' ?>>Medium</option>
                            <option value="large" <?= $prefs['portion_size'] == 'large' ? 'selected' : '' ?>>Large</option>
                        </select>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="allergies" class="form-label">Food Allergies</label>
                        <input type="text" class="form-control" id="allergies" name="allergies" 
                               value="<?= htmlspecialchars($prefs['allergies']) ?>"
                               placeholder="e.g., peanuts, shellfish, dairy">
                        <div class="form-text">Separate multiple items with commas</div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="food_dislikes" class="form-label">Foods to Avoid</label>
                        <input type="text" class="form-control" id="food_dislikes" name="food_dislikes" 
                               value="<?= htmlspecialchars($prefs['food_dislikes']) ?>"
                               placeholder="e.g., mushrooms, olives, spicy food">
                        <div class="form-text">Separate multiple items with commas</div>
                    </div>
                    <div class="col-12 mb-3">
                        <label for="preferred_cuisines" class="form-label">Preferred Cuisines</label>
                        <input type="text" class="form-control" id="preferred_cuisines" name="preferred_cuisines" 
                               value="<?= htmlspecialchars($prefs['preferred_cuisines']) ?>"
                               placeholder="e.g., Filipino, Japanese, Italian, Mexican">
                        <div class="form-text">Separate multiple items with commas</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Nutrition Goals Section -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="bi bi-bullseye"></i> Daily Nutrition Goals</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="daily_calorie_goal" class="form-label">Daily Calorie Goal (kcal)</label>
                        <input type="number" class="form-control" id="daily_calorie_goal" name="daily_calorie_goal" 
                               value="<?= $prefs['daily_calorie_goal'] ?>" min="1000" max="5000" step="100">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="daily_protein_goal" class="form-label">Daily Protein Goal (g)</label>
                        <input type="number" class="form-control" id="daily_protein_goal" name="daily_protein_goal" 
                               value="<?= $prefs['daily_protein_goal'] ?>" min="0" max="300">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="daily_carbs_goal" class="form-label">Daily Carbohydrates Goal (g)</label>
                        <input type="number" class="form-control" id="daily_carbs_goal" name="daily_carbs_goal" 
                               value="<?= $prefs['daily_carbs_goal'] ?>" min="0" max="500">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="daily_fat_goal" class="form-label">Daily Fat Goal (g)</label>
                        <input type="number" class="form-control" id="daily_fat_goal" name="daily_fat_goal" 
                               value="<?= $prefs['daily_fat_goal'] ?>" min="0" max="200">
                    </div>
                </div>
            </div>
        </div>

        <!-- Meal Planning Section -->
        <div class="card mb-4 shadow-sm">
            <div class="card-header bg-warning text-dark">
                <h5 class="mb-0"><i class="bi bi-calendar-week"></i> Meal Planning Preferences</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label for="meals_per_day" class="form-label">Meals per Day</label>
                        <input type="number" class="form-control" id="meals_per_day" name="meals_per_day" 
                               value="<?= $prefs['meals_per_day'] ?>" min="1" max="6">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="default_serving_size" class="form-label">Default Serving Size (people)</label>
                        <input type="number" class="form-control" id="default_serving_size" name="default_serving_size" 
                               value="<?= $prefs['default_serving_size'] ?>" min="1" max="10">
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="meal_prep_days" class="form-label">Meal Prep Days</label>
                        <input type="number" class="form-control" id="meal_prep_days" name="meal_prep_days" 
                               value="<?= $prefs['meal_prep_days'] ?>" min="1" max="14">
                        <div class="form-text">How many days to plan ahead</div>
                    </div>
                    <div class="col-md-12 mb-3">
                        <label for="budget_per_week" class="form-label">Weekly Food Budget (₱)</label>
                        <input type="number" class="form-control" id="budget_per_week" name="budget_per_week" 
                               value="<?= $prefs['budget_per_week'] ?>" min="0" step="0.01" placeholder="Optional">
                        <div class="form-text">Leave at 0 if you don't want to track budget</div>
                    </div>
                </div>
            </div>
        </div>


        <!-- Save Button -->
        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
            <button type="submit" class="btn btn-primary btn-lg px-5">
                <i class="bi bi-save"></i> Save Preferences
            </button>
        </div>
    </form>

    <!-- Statistics Display -->
    <div class="card mt-4 shadow-sm">
        <div class="card-header bg-secondary text-white">
            <h5 class="mb-0"><i class="bi bi-graph-up"></i> Your Statistics</h5>
        </div>
        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-fire"></i></div>
                        <div class="stat-value"><?= $prefs['daily_calorie_goal'] ?></div>
                        <div class="stat-label">Daily Calorie Goal</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-egg"></i></div>
                        <div class="stat-value"><?= $prefs['daily_protein_goal'] ?>g</div>
                        <div class="stat-label">Protein Goal</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-calendar3"></i></div>
                        <div class="stat-value"><?= $prefs['meal_prep_days'] ?></div>
                        <div class="stat-label">Days Planning</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-icon"><i class="bi bi-cash"></i></div>
                        <div class="stat-value">₱<?= number_format($prefs['budget_per_week'], 2) ?></div>
                        <div class="stat-label">Weekly Budget</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<style>
.card {
    border: none;
    border-radius: 15px;
    overflow: hidden;
}

.card-header {
    border: none;
    padding: 1.25rem;
}

.form-label {
    font-weight: 600;
    color: #495057;
}

.stat-card {
    padding: 1.5rem;
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 10px;
    margin: 0.5rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.stat-icon {
    font-size: 2.5rem;
    color: #28a745;
    margin-bottom: 0.5rem;
}

.stat-value {
    font-size: 2rem;
    font-weight: bold;
    color: #343a40;
}

.stat-label {
    font-size: 0.9rem;
    color: #6c757d;
    margin-top: 0.5rem;
}

.form-check-label {
    cursor: pointer;
}

.form-check-input:checked {
    background-color: #28a745;
    border-color: #28a745;
}
</style>

<script>
document.getElementById('preferencesForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'update_preferences');
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Saving...';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while saving preferences.', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

function resetPreferences() {
    if (!confirm('Are you sure you want to reset all preferences to default? This cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'reset_preferences');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while resetting preferences.', 'error');
    });
}

function showNotification(message, type = 'info') {
    const existingNotification = document.querySelector('.notification-toast');
    if (existingNotification) {
        existingNotification.remove();
    }
    
    const notification = document.createElement('div');
    notification.className = `notification-toast alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
    notification.style.position = 'fixed';
    notification.style.top = '20px';
    notification.style.right = '20px';
    notification.style.zIndex = '10000';
    notification.style.minWidth = '300px';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}
</script>

<?php include 'footer.php'; ?>

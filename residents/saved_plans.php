<?php
include '../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle AJAX requests BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'save_meal_plan':
                // Debug session information
                $session_debug = [
                    'session_status' => session_status(),
                    'session_active' => session_status() === PHP_SESSION_ACTIVE,
                    'user_id' => $_SESSION['user_id'] ?? 'not_set',
                    'username' => $_SESSION['username'] ?? 'not_set'
                ];
                
                // Check if user is logged in
                if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                    echo json_encode([
                        'success' => false, 
                        'message' => 'User not logged in.',
                        'debug' => $session_debug
                    ]);
                    exit;
                }
                
                $plan_name = trim($_POST['plan_name'] ?? '');
                if (empty($plan_name)) {
                    echo json_encode(['success' => false, 'message' => 'Plan name is required.']);
                    exit;
                }
                
                $plan_data = json_encode($_POST['plan_data'] ?? []);
                $filters = json_encode($_POST['filters'] ?? []);
                
                // Calculate totals
                $totals = calculateMealPlanTotals(json_decode($plan_data, true));
                
                // Generate share token if sharing is enabled
                $share_token = isset($_POST['is_shared']) && $_POST['is_shared'] ? bin2hex(random_bytes(16)) : null;
                
                try {
                    $stmt = $conn->prepare("INSERT INTO meal_plans (user_id, plan_name, plan_data, filters_applied, total_calories, total_protein, total_carbs, total_fat, is_shared, share_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    if (!$stmt) {
                        echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
                        exit;
                    }
                    
                    $is_shared = $share_token ? 1 : 0;
                    $bind_result = $stmt->bind_param('isssddddss', $_SESSION['user_id'], $plan_name, $plan_data, $filters, $totals['calories'], $totals['protein'], $totals['carbs'], $totals['fat'], $is_shared, $share_token);
                    
                    if (!$bind_result) {
                        echo json_encode(['success' => false, 'message' => 'Database bind error: ' . $stmt->error]);
                        $stmt->close();
                        exit;
                    }
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Meal plan saved successfully!', 'plan_id' => $conn->insert_id]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save meal plan: ' . $stmt->error]);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
                
            case 'load_meal_plan':
                $plan_id = (int)$_POST['plan_id'];
                $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $plan_id, $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($meal_plan = $result->fetch_assoc()) {
                    // Handle double-encoded JSON in plan_data
                    $decoded_data = json_decode($meal_plan['plan_data'], true);
                    if (is_string($decoded_data)) {
                        $meal_plan['plan_data'] = json_decode($decoded_data, true);
                    } else {
                        $meal_plan['plan_data'] = $decoded_data;
                    }
                    echo json_encode(['success' => true, 'data' => $meal_plan]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Meal plan not found.']);
                }
                $stmt->close();
                exit;
                
            case 'delete_meal_plan':
                $plan_id = (int)$_POST['plan_id'];
                $stmt = $conn->prepare("DELETE FROM meal_plans WHERE id = ? AND user_id = ?");
                $stmt->bind_param('ii', $plan_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    echo json_encode(['success' => true, 'message' => 'Meal plan deleted successfully!']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to delete meal plan.']);
                }
                $stmt->close();
                exit;
                
            case 'share_meal_plan':
                $plan_id = (int)$_POST['plan_id'];
                $share_token = bin2hex(random_bytes(16));
                
                $stmt = $conn->prepare("UPDATE meal_plans SET is_shared = 1, share_token = ? WHERE id = ? AND user_id = ?");
                $stmt->bind_param('sii', $share_token, $plan_id, $_SESSION['user_id']);
                
                if ($stmt->execute()) {
                    $share_url = "saved_plans.php?shared=" . $share_token;
                    echo json_encode(['success' => true, 'message' => 'Meal plan shared successfully!', 'share_url' => $share_url]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to share meal plan.']);
                }
                $stmt->close();
                exit;
                
            case 'get_saved_plans':
                // Check if user is logged in
                if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
                    exit;
                }
                
                $saved_plans = [];
                $stmt = $conn->prepare("SELECT id, plan_name, total_calories, total_protein, total_carbs, total_fat, is_shared, created_at FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt->bind_param('i', $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($plan = $result->fetch_assoc()) {
                    $saved_plans[] = $plan;
                }
                $stmt->close();
                
                echo json_encode(['success' => true, 'data' => $saved_plans]);
                exit;
                
            case 'load_shared_plan':
                $share_token = trim($_POST['share_token'] ?? '');
                $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE share_token = ? AND is_shared = 1");
                $stmt->bind_param('s', $share_token);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($meal_plan = $result->fetch_assoc()) {
                    // Handle double-encoded JSON in plan_data
                    $decoded_data = json_decode($meal_plan['plan_data'], true);
                    if (is_string($decoded_data)) {
                        $meal_plan['plan_data'] = json_decode($decoded_data, true);
                    } else {
                        $meal_plan['plan_data'] = $decoded_data;
                    }
                    echo json_encode(['success' => true, 'data' => $meal_plan]);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Shared meal plan not found or expired.']);
                }
                $stmt->close();
                exit;
                
            case 'save_shared_plan':
                // Check if user is logged in
                if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                    echo json_encode(['success' => false, 'message' => 'Please log in to save this meal plan.']);
                    exit;
                }
                
                $share_token = trim($_POST['share_token'] ?? '');
                if (empty($share_token)) {
                    echo json_encode(['success' => false, 'message' => 'Invalid share token.']);
                    exit;
                }
                
                // Get the shared meal plan
                $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE share_token = ? AND is_shared = 1");
                $stmt->bind_param('s', $share_token);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($shared_plan = $result->fetch_assoc()) {
                    // Check if user already has this meal plan saved
                    $check_stmt = $conn->prepare("SELECT id FROM meal_plans WHERE user_id = ? AND plan_name = ? AND is_shared = 0");
                    $check_stmt->bind_param('is', $_SESSION['user_id'], $shared_plan['plan_name']);
                    $check_stmt->execute();
                    $check_result = $check_stmt->get_result();
                    
                    if ($check_result->num_rows > 0) {
                        echo json_encode(['success' => false, 'message' => 'You already have a meal plan with this name saved.']);
                        $check_stmt->close();
                        $stmt->close();
                        exit;
                    }
                    $check_stmt->close();
                    
                    // Create a copy of the shared meal plan for the user
                    $new_plan_name = $shared_plan['plan_name'] . ' (Shared)';
                    $insert_stmt = $conn->prepare("INSERT INTO meal_plans (user_id, plan_name, plan_data, filters_applied, total_calories, total_protein, total_carbs, total_fat, is_shared, share_token) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL)");
                    $insert_stmt->bind_param('isssdddd', 
                        $_SESSION['user_id'], 
                        $new_plan_name, 
                        $shared_plan['plan_data'], 
                        $shared_plan['filters_applied'], 
                        $shared_plan['total_calories'], 
                        $shared_plan['total_protein'], 
                        $shared_plan['total_carbs'], 
                        $shared_plan['total_fat']
                    );
                    
                    if ($insert_stmt->execute()) {
                        echo json_encode(['success' => true, 'message' => 'Meal plan saved successfully!', 'plan_id' => $conn->insert_id]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to save meal plan: ' . $insert_stmt->error]);
                    }
                    $insert_stmt->close();
                } else {
                    echo json_encode(['success' => false, 'message' => 'Shared meal plan not found or expired.']);
                }
                $stmt->close();
                exit;
        }
    }
}

// Function to calculate meal plan totals
function calculateMealPlanTotals($plan_data) {
    $totals = ['calories' => 0, 'protein' => 0, 'carbs' => 0, 'fat' => 0];
    
    // Ensure plan_data is an array
    if (!is_array($plan_data)) {
        return $totals;
    }
    
    foreach ($plan_data as $day) {
        if (!is_array($day)) continue;
        
        foreach (['Breakfast', 'Lunch', 'Dinner'] as $meal) {
            if (isset($day[$meal]) && is_array($day[$meal]) && $day[$meal]) {
                $totals['calories'] += (int)($day[$meal]['Calories (kcal)'] ?? 0);
                $totals['protein'] += (float)($day[$meal]['Protein (g)'] ?? 0);
                $totals['carbs'] += (float)($day[$meal]['Carbs (g)'] ?? 0);
                $totals['fat'] += (float)($day[$meal]['Fat (g)'] ?? 0);
            }
        }
    }
    
    return $totals;
}

// Get saved meal plans for current user (only if session is active)
$saved_plans = [];
if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id, plan_name, total_calories, total_protein, total_carbs, total_fat, is_shared, created_at FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($plan = $result->fetch_assoc()) {
        $saved_plans[] = $plan;
    }
    $stmt->close();
}

// Handle shared meal plan loading
$shared_plan = null;
$selected_plan = null;
if (isset($_GET['shared'])) {
    $share_token = $_GET['shared'];
    $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE share_token = ? AND is_shared = 1");
    $stmt->bind_param('s', $share_token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($shared_plan = $result->fetch_assoc()) {
        $decoded_data = json_decode($shared_plan['plan_data'], true);
        // Handle double-encoded JSON
        if (is_string($decoded_data)) {
            $selected_plan = json_decode($decoded_data, true);
        } else {
            $selected_plan = $decoded_data;
        }
    }
    $stmt->close();
}

// Handle plan selection
if (isset($_GET['plan_id'])) {
    $plan_id = (int)$_GET['plan_id'];
    $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $plan_id, $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($selected_plan_data = $result->fetch_assoc()) {
        $decoded_data = json_decode($selected_plan_data['plan_data'], true);
        // Handle double-encoded JSON
        if (is_string($decoded_data)) {
            $selected_plan = json_decode($decoded_data, true);
        } else {
            $selected_plan = $decoded_data;
        }
    }
    $stmt->close();
}

// Include HTML files AFTER AJAX handling
include 'header.php'; 
include 'topbar.php'; 
include 'sidebar.php';
?>

<main id="main" class="main">
<div class="container py-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Saved Meal Plans</h2>
        <div class="d-flex gap-2">
            <a href="meal_plan_generator.php" class="btn btn-primary">
                <i class="bi bi-plus-circle"></i> Create New Plan
            </a>
            <button type="button" class="btn btn-success" id="refresh-plans-btn">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </button>
        </div>
    </div>

    <?php if ($shared_plan): ?>
        <div class="alert alert-info">
            <i class="bi bi-share"></i> Viewing shared meal plan: <strong><?= htmlspecialchars($shared_plan['plan_name']) ?></strong>
        </div>
    <?php endif; ?>

    <!-- Saved Plans Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Your Saved Meal Plans</h5>
        </div>
        <div class="card-body">
            <?php if (empty($saved_plans)): ?>
                <div class="text-center py-4">
                    <i class="bi bi-bookmark-heart display-1 text-muted"></i>
                    <h4 class="text-muted mt-3">No saved meal plans yet</h4>
                    <p class="text-muted">Create your first meal plan to get started!</p>
                    <a href="meal_plan_generator.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Create Meal Plan
                    </a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover" id="saved-plans-table">
                        <thead class="table-light">
                            <tr>
                                <th>Plan Name</th>
                                <th>Calories</th>
                                <th>Protein</th>
                                <th>Carbs</th>
                                <th>Fat</th>
                                <th>Shared</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($saved_plans as $plan): ?>
                                <tr data-plan-id="<?= $plan['id'] ?>">
                                    <td>
                                        <strong><?= htmlspecialchars($plan['plan_name']) ?></strong>
                                    </td>
                                    <td><?= number_format($plan['total_calories']) ?></td>
                                    <td><?= number_format($plan['total_protein'], 1) ?>g</td>
                                    <td><?= number_format($plan['total_carbs'], 1) ?>g</td>
                                    <td><?= number_format($plan['total_fat'], 1) ?>g</td>
                                    <td>
                                        <?php if ($plan['is_shared']): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-share"></i> Shared
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Private</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= date('M j, Y', strtotime($plan['created_at'])) ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm" role="group">
                                            <button type="button" class="btn btn-outline-primary view-plan-btn" data-plan-id="<?= $plan['id'] ?>">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            <?php if ($plan['is_shared']): ?>
                                                <button type="button" class="btn btn-outline-success share-plan-btn" data-plan-id="<?= $plan['id'] ?>" disabled>
                                                    <i class="bi bi-share"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-outline-success share-plan-btn" data-plan-id="<?= $plan['id'] ?>">
                                                    <i class="bi bi-share"></i>
                                                </button>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-outline-danger delete-plan-btn" data-plan-id="<?= $plan['id'] ?>">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Selected Plan Display -->
    <?php if ($selected_plan): ?>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <?php if ($shared_plan): ?>
                        <i class="bi bi-share"></i> <?= htmlspecialchars($shared_plan['plan_name']) ?>
                    <?php else: ?>
                        <i class="bi bi-bookmark-heart"></i> Selected Meal Plan
                    <?php endif; ?>
                </h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="export-selected-btn">
                    <i class="bi bi-download"></i> Export Plan
                </button>
            </div>
            <div class="card-body">
                <!-- Meal Plan Summary -->
                <div class="row text-center mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value" id="selected-total-calories">0</div>
                            <div class="metric-label">Total Calories</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value" id="selected-total-protein">0g</div>
                            <div class="metric-label">Total Protein</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value" id="selected-total-carbs">0g</div>
                            <div class="metric-label">Total Carbs</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value" id="selected-total-fat">0g</div>
                            <div class="metric-label">Total Fat</div>
                        </div>
                    </div>
                </div>

                <!-- Meal Plan Table -->
                <div style="position:relative;">
                    <div class="notebook-spiral">
                        <span></span><span></span><span></span><span></span><span></span><span></span><span></span>
                    </div>
                    <table class="table notebook-table">
                        <thead>
                            <tr>
                                <th>Day</th>
                                <th>Breakfast</th>
                                <th>Lunch</th>
                                <th>Dinner</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php 
                        // Ensure selected_plan is an array
                        if (!is_array($selected_plan)) {
                            $selected_plan = [];
                        }
                        foreach ($selected_plan as $i => $day): 
                        ?>
                            <tr style="animation-delay: <?= 0.1 * $i ?>s;">
                                <td>Day <?= $i+1 ?></td>
                                <?php foreach (['Breakfast', 'Lunch', 'Dinner'] as $meal): ?>
                                    <td>
                                        <?php if ($day[$meal]): ?>
                                            <strong><?= htmlspecialchars($day[$meal]['Dish Name']) ?></strong><br>
                                            <small><?= htmlspecialchars($day[$meal]['Serving Size']) ?> | <?= htmlspecialchars($day[$meal]['Calories (kcal)']) ?> kcal | <?= htmlspecialchars($day[$meal]['Protein (g)']) ?>g protein | <?= htmlspecialchars($day[$meal]['Carbs (g)']) ?>g carbs | <?= htmlspecialchars($day[$meal]['Fat (g)']) ?>g fat</small>
                                        <?php else: ?>
                                            <em>No dish available</em>
                                        <?php endif; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($shared_plan): ?>
                    <!-- Save Shared Plan Button -->
                    <div class="text-center mt-4">
                        <button type="button" class="btn btn-success btn-lg" id="save-shared-plan-btn">
                            <i class="bi bi-bookmark-plus"></i> Save This Meal Plan
                        </button>
                        <p class="text-muted mt-2">
                            <i class="bi bi-info-circle"></i> 
                            Save this shared meal plan to your collection for easy access later.
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</main>

<!-- Share Meal Plan Modal -->
<div class="modal fade" id="shareModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Meal Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="share-content">
                    <div class="text-center">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Generating share link...</span>
                        </div>
                        <p class="mt-2">Creating share link...</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
/* Notebook paper effect */
.notebook-table {
  background: #fffbe7;
  border-radius: 18px;
  box-shadow: 0 6px 24px rgba(0,0,0,0.10), 0 1.5px 4px rgba(0,0,0,0.08);
  border-collapse: separate;
  border-spacing: 0;
  overflow: hidden;
  margin-bottom: 2rem;
  font-family: 'Poppins', 'Open Sans', Arial, sans-serif;
  animation: fadeInTable 1s ease;
}
.notebook-table thead {
  background: linear-gradient(90deg, #ffe082 0%, #fffde7 100%);
}
.notebook-table th, .notebook-table td {
  border: none;
  border-bottom: 1.5px dashed #e0c97f;
  padding: 1rem 1.2rem;
  font-size: 1.08rem;
  vertical-align: middle;
}
.notebook-table th {
  color: #b38800;
  font-weight: 700;
  letter-spacing: 1px;
  text-shadow: 0 1px 0 #fffde7;
}
.notebook-table tr:last-child td {
  border-bottom: none;
}
.notebook-table tr {
  animation: fadeInRow 0.7s ease;
}
@keyframes fadeInTable {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
@keyframes fadeInRow {
  from { opacity: 0; transform: translateX(-30px); }
  to { opacity: 1; transform: translateX(0); }
}
/* Spiral binding effect */
.notebook-spiral {
  position: absolute;
  left: -24px;
  top: 18px;
  bottom: 18px;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  height: calc(100% - 36px);
  z-index: 2;
}
.notebook-spiral span {
  display: block;
  width: 12px;
  height: 12px;
  background: #bdbdbd;
  border-radius: 50%;
  margin: 0.2rem 0;
  box-shadow: 0 1px 2px #aaa;
}

/* Metric cards */
.metric-card {
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  border-radius: 12px;
  padding: 20px;
  border: 1px solid #dee2e6;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.metric-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.metric-value {
  font-size: 2rem;
  font-weight: bold;
  color: #43e97b;
  margin-bottom: 5px;
}
.metric-label {
  font-size: 0.9rem;
  color: #6c757d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Modal enhancements */
.modal-content {
  border-radius: 15px;
  border: none;
  box-shadow: 0 10px 40px rgba(0,0,0,0.15);
}
.modal-header {
  background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%);
  color: white;
  border-radius: 15px 15px 0 0;
}
.modal-header .btn-close {
  filter: invert(1);
}

/* Card hover effects */
.card {
  transition: transform 0.2s ease, box-shadow 0.2s ease;
  border: none;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}
.card:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 16px rgba(0,0,0,0.15);
}

/* Table enhancements */
.table-hover tbody tr:hover {
  background-color: rgba(67, 233, 123, 0.1);
}
.btn-group-sm .btn {
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
}

/* Save shared plan button */
#save-shared-plan-btn {
  transition: all 0.3s ease;
  box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
  border: none;
  font-weight: 600;
  letter-spacing: 0.5px;
}

#save-shared-plan-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(40, 167, 69, 0.4);
}

#save-shared-plan-btn:disabled {
  transform: none;
  box-shadow: 0 2px 8px rgba(40, 167, 69, 0.2);
}

#save-shared-plan-btn i {
  margin-right: 8px;
}
</style>

<?php include 'footer.php'; ?>

<script>
// Global variables
let currentSelectedPlan = <?= json_encode(is_array($selected_plan) ? $selected_plan : []) ?>;

// Calculate and update meal plan totals for selected plan
function updateSelectedPlanTotals() {
    if (!currentSelectedPlan || currentSelectedPlan.length === 0) return;
    
    const totals = { calories: 0, protein: 0, carbs: 0, fat: 0 };
    
    currentSelectedPlan.forEach(day => {
        ['Breakfast', 'Lunch', 'Dinner'].forEach(meal => {
            if (day[meal]) {
                totals.calories += parseInt(day[meal]['Calories (kcal)'] || 0);
                totals.protein += parseFloat(day[meal]['Protein (g)'] || 0);
                totals.carbs += parseFloat(day[meal]['Carbs (g)'] || 0);
                totals.fat += parseFloat(day[meal]['Fat (g)'] || 0);
            }
        });
    });
    
    document.getElementById('selected-total-calories').textContent = totals.calories;
    document.getElementById('selected-total-protein').textContent = totals.protein.toFixed(1) + 'g';
    document.getElementById('selected-total-carbs').textContent = totals.carbs.toFixed(1) + 'g';
    document.getElementById('selected-total-fat').textContent = totals.fat.toFixed(1) + 'g';
}

// View plan functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.view-plan-btn')) {
        const planId = e.target.closest('.view-plan-btn').dataset.planId;
        window.location.href = `saved_plans.php?plan_id=${planId}`;
    }
});

// Delete plan functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.delete-plan-btn')) {
        const planId = e.target.closest('.delete-plan-btn').dataset.planId;
        const planRow = e.target.closest('tr');
        const planName = planRow.querySelector('td:first-child strong').textContent;
        
        if (confirm(`Are you sure you want to delete "${planName}"? This action cannot be undone.`)) {
            const deleteBtn = e.target.closest('.delete-plan-btn');
            deleteBtn.innerHTML = '<i class="spinner-border spinner-border-sm"></i>';
            deleteBtn.disabled = true;
            
            const formData = new FormData();
            formData.append('action', 'delete_meal_plan');
            formData.append('plan_id', planId);
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    planRow.remove();
                    showNotification('Meal plan deleted successfully!', 'success');
                } else {
                    showNotification(data.message || 'Failed to delete meal plan.', 'error');
                    deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
                    deleteBtn.disabled = false;
                }
            })
            .catch(error => {
                showNotification('An error occurred while deleting the meal plan.', 'error');
                deleteBtn.innerHTML = '<i class="bi bi-trash"></i>';
                deleteBtn.disabled = false;
            });
        }
    }
});

// Share plan functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('.share-plan-btn')) {
        const shareBtn = e.target.closest('.share-plan-btn');
        if (shareBtn.disabled) return;
        
        const planId = shareBtn.dataset.planId;
        const modal = new bootstrap.Modal(document.getElementById('shareModal'));
        modal.show();
        
        // Reset modal content
        document.getElementById('share-content').innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Generating share link...</span>
                </div>
                <p class="mt-2">Creating share link...</p>
            </div>
        `;
        
        const formData = new FormData();
        formData.append('action', 'share_meal_plan');
        formData.append('plan_id', planId);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById('share-content').innerHTML = `
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle"></i> <strong>Share link created successfully!</strong>
                    </div>
                    <div class="mb-3">
                        <label for="share-url" class="form-label">Share URL:</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="share-url" value="${data.share_url}" readonly>
                            <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('share-url')">
                                <i class="bi bi-clipboard"></i> Copy
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> 
                        Anyone with this link can view your meal plan. Share it with friends and family!
                    </div>
                `;
                
                // Update the share button to show it's shared
                shareBtn.innerHTML = '<i class="bi bi-share-fill"></i>';
                shareBtn.disabled = true;
                shareBtn.title = 'Already shared';
                
                // Update the badge in the table
                const planRow = shareBtn.closest('tr');
                const badgeCell = planRow.querySelector('td:nth-child(6)');
                badgeCell.innerHTML = '<span class="badge bg-success"><i class="bi bi-share"></i> Shared</span>';
                
                showNotification('Meal plan shared successfully!', 'success');
            } else {
                document.getElementById('share-content').innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> 
                        <strong>Error:</strong> ${data.message}
                    </div>
                `;
                showNotification(data.message || 'Failed to share meal plan.', 'error');
            }
        })
        .catch(error => {
            document.getElementById('share-content').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle"></i> 
                    <strong>Error:</strong> An error occurred while sharing the meal plan.
                </div>
            `;
            showNotification('An error occurred while sharing the meal plan.', 'error');
        });
    }
});

// Refresh plans functionality
document.getElementById('refresh-plans-btn').addEventListener('click', function() {
    window.location.reload();
});

// Copy to clipboard functionality
function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    element.select();
    element.setSelectionRange(0, 99999); // For mobile devices
    document.execCommand('copy');
    showNotification('Share URL copied to clipboard!', 'success');
}

// Export selected plan functionality
document.getElementById('export-selected-btn')?.addEventListener('click', function() {
    if (!currentSelectedPlan || currentSelectedPlan.length === 0) {
        showNotification('No meal plan selected to export.', 'warning');
        return;
    }
    
    // Create CSV content
    let csvContent = "Day,Meal,Dish Name,Serving Size,Calories,Protein (g),Carbs (g),Fat (g)\n";
    
    currentSelectedPlan.forEach((day, dayIndex) => {
        ['Breakfast', 'Lunch', 'Dinner'].forEach(meal => {
            if (day[meal]) {
                const dish = day[meal];
                csvContent += `Day ${dayIndex + 1},${meal},"${dish['Dish Name']}","${dish['Serving Size']}",${dish['Calories (kcal)']},${dish['Protein (g)']},${dish['Carbs (g)']},${dish['Fat (g)']}\n`;
            }
        });
    });
    
    // Calculate totals
    const totals = { calories: 0, protein: 0, carbs: 0, fat: 0 };
    currentSelectedPlan.forEach(day => {
        ['Breakfast', 'Lunch', 'Dinner'].forEach(meal => {
            if (day[meal]) {
                totals.calories += parseInt(day[meal]['Calories (kcal)'] || 0);
                totals.protein += parseFloat(day[meal]['Protein (g)'] || 0);
                totals.carbs += parseFloat(day[meal]['Carbs (g)'] || 0);
                totals.fat += parseFloat(day[meal]['Fat (g)'] || 0);
            }
        });
    });
    
    csvContent += `\nTOTALS,,,,${totals.calories},${totals.protein.toFixed(1)},${totals.carbs.toFixed(1)},${totals.fat.toFixed(1)}\n`;
    
    // Download CSV
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'meal_plan_' + new Date().toISOString().split('T')[0] + '.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    
    showNotification('Meal plan exported successfully!', 'success');
});

// Show notification function
function showNotification(message, type = 'info') {
    const alertClass = type === 'success' ? 'alert-success' : 
                      type === 'error' ? 'alert-danger' : 
                      type === 'warning' ? 'alert-warning' : 'alert-info';
    
    const notification = document.createElement('div');
    notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
    notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Save shared plan functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('#save-shared-plan-btn')) {
        const saveBtn = e.target.closest('#save-shared-plan-btn');
        const originalText = saveBtn.innerHTML;
        
        // Show loading state
        saveBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Saving...';
        saveBtn.disabled = true;
        
        // Get the share token from URL
        const urlParams = new URLSearchParams(window.location.search);
        const shareToken = urlParams.get('shared');
        
        if (!shareToken) {
            showNotification('Invalid share link.', 'error');
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
            return;
        }
        
        const formData = new FormData();
        formData.append('action', 'save_shared_plan');
        formData.append('share_token', shareToken);
        
        fetch(window.location.href, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                saveBtn.innerHTML = '<i class="bi bi-check-circle"></i> Saved Successfully!';
                saveBtn.classList.remove('btn-success');
                saveBtn.classList.add('btn-outline-success');
                showNotification(data.message, 'success');
                
                // Optionally redirect to saved plans after a delay
                setTimeout(() => {
                    window.location.href = 'saved_plans.php';
                }, 2000);
            } else {
                showNotification(data.message || 'Failed to save meal plan.', 'error');
                saveBtn.innerHTML = originalText;
                saveBtn.disabled = false;
            }
        })
        .catch(error => {
            showNotification('An error occurred while saving the meal plan.', 'error');
            saveBtn.innerHTML = originalText;
            saveBtn.disabled = false;
        });
    }
});

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    updateSelectedPlanTotals();
});
</script>

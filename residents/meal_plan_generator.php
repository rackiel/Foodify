<?php
include '../config/db.php';

// Handle AJAX requests BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Start session for AJAX requests
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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
                    $share_url = "meal_plan_generator.php?shared=" . $share_token;
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
                $stmt = $conn->prepare("SELECT id, plan_name, total_calories, total_protein, total_carbs, total_fat, created_at FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
                $stmt->bind_param('i', $_SESSION['user_id']);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($plan = $result->fetch_assoc()) {
                    $saved_plans[] = $plan;
                }
                $stmt->close();
                
                echo json_encode(['success' => true, 'data' => $saved_plans]);
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
    $stmt = $conn->prepare("SELECT id, plan_name, total_calories, total_protein, total_carbs, total_fat, created_at FROM meal_plans WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
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
if (isset($_GET['shared'])) {
    $share_token = $_GET['shared'];
    $stmt = $conn->prepare("SELECT * FROM meal_plans WHERE share_token = ? AND is_shared = 1");
    $stmt->bind_param('s', $share_token);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($shared_plan = $result->fetch_assoc()) {
        $meal_plan = json_decode($shared_plan['plan_data'], true);
    }
    $stmt->close();
}

// Include HTML files AFTER AJAX handling
include 'header.php'; 
include 'topbar.php'; 
include 'sidebar.php';

// Read CSV and parse dishes
$dishes = [];
if (($handle = fopen('foodify_filipino_dishes.csv', 'r')) !== false) {
    $header = fgetcsv($handle); // skip header
    while (($row = fgetcsv($handle)) !== false) {
        $dishes[] = array_combine($header, $row);
    }
    fclose($handle);
}

// Handle filters
$cal_min = isset($_GET['cal_min']) ? (int)$_GET['cal_min'] : '';
$cal_max = isset($_GET['cal_max']) ? (int)$_GET['cal_max'] : '';

$filtered = array_filter($dishes, function($d) use ($cal_min, $cal_max) {
    // Exclude Snack, Street, Dessert from meal plan
    if (stripos($d['Category'], 'snack') !== false || stripos($d['Category'], 'street') !== false || stripos($d['Category'], 'dessert') !== false) return false;
    $ok = true;
    if ($cal_min !== '' && (int)$d['Calories (kcal)'] < $cal_min) $ok = false;
    if ($cal_max !== '' && (int)$d['Calories (kcal)'] > $cal_max) $ok = false;
    return $ok;
});

// Group for meal plan
$breakfasts = array_filter($filtered, fn($d) => stripos($d['Category'], 'Breakfast') !== false);
$lunches = array_filter($filtered, fn($d) => stripos($d['Category'], 'Lunch') !== false || stripos($d['Category'], 'Dinner') !== false);
$dinners = $lunches;

// Generate meal plan (only if not loading a shared plan)
if (!isset($meal_plan) || !is_array($meal_plan)) {
$meal_plan = [];
for ($i = 0; $i < 7; $i++) {
    $meal_plan[] = [
        'Breakfast' => $breakfasts ? $breakfasts[array_rand($breakfasts)] : null,
        'Lunch'     => $lunches ? $lunches[array_rand($lunches)] : null,
        'Dinner'    => $dinners ? $dinners[array_rand($dinners)] : null,
    ];
    }
}
?>
<main id="main" class="main">
<div class="container py-5">
    <h2>
        Filipino Meal Plan (7 Days) 
        <i class="bi bi-info-circle info-icon-blink ms-2" data-bs-toggle="modal" data-bs-target="#referencesModal" style="cursor: pointer; font-size: 1.5rem;" title="View Data Sources"></i>
    </h2>
    <form method="get" class="row g-3 mb-4">
        <div class="col-md-3">
            <label for="cal_min" class="form-label">Min Calories</label>
            <input type="number" name="cal_min" id="cal_min" class="form-control" value="<?= htmlspecialchars($cal_min) ?>" placeholder="e.g. 200">
        </div>
        <div class="col-md-3">
            <label for="cal_max" class="form-label">Max Calories</label>
            <input type="number" name="cal_max" id="cal_max" class="form-control" value="<?= htmlspecialchars($cal_max) ?>" placeholder="e.g. 800">
        </div>
        <div class="col-md-6 d-flex align-items-end gap-2">
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-funnel"></i> Filter by Calories
            </button>
            <a href="meal_plan_generator.php" class="btn btn-success" id="generate-btn">
                <i class="bi bi-arrow-clockwise"></i> Generate Random
            </a>
            <button type="button" class="btn btn-warning" id="save-btn">
                <i class="bi bi-save"></i> Save Plan
            </button>
            <button type="button" class="btn btn-info" id="load-btn">
                <i class="bi bi-folder-open"></i> Load Plans
            </button>
        </div>
    </form>
    <?php if ($shared_plan): ?>
    <div class="row mb-3">
        <div class="col-12">
            <div class="alert alert-info">
                <i class="bi bi-share"></i> Viewing shared meal plan: <strong><?= htmlspecialchars($shared_plan['plan_name']) ?></strong>
            </div>
        </div>
    </div>
    <?php endif; ?>

<!-- Meal Plan Summary -->
<?php if (!$shared_plan): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Meal Plan Summary</h5>
                <button type="button" class="btn btn-sm btn-outline-primary" id="export-btn">
                    <i class="bi bi-download"></i> Export Plan
                </button>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <div class="metric-card"> <!-- calorie -->
                            <div class="metric-value" id="total-calories">0</div>
                            <div class="metric-label">Total Calories</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card"> <!-- protein -->
                            <div class="metric-value" id="total-protein">0g</div>
                            <div class="metric-label">Total Protein</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card"> <!-- carbs -->
                            <div class="metric-value" id="total-carbs">0g</div>
                            <div class="metric-label">Total Carbs</div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="metric-value" id="total-fat">0g</div>
                            <div class="metric-label">Total Fat</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Save Meal Plan Modal -->
<div class="modal fade" id="saveModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Save Meal Plan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="saveForm">
                    <div class="mb-3">
                        <label for="plan_name" class="form-label">Plan Name</label>
                        <input type="text" class="form-control" id="plan_name" name="plan_name" required placeholder="e.g., My Weekly Filipino Meal Plan">
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="is_shared" name="is_shared">
                            <label class="form-check-label" for="is_shared">
                                Share this meal plan with others
                            </label>
                        </div>
                    </div>
                    <div class="mb-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle"></i> 
                            Sharing allows others to view your meal plan via a special link.
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="confirm-save">Save Plan</button>
            </div>
        </div>
    </div>
</div>

<!-- Load Meal Plans Modal -->
<div class="modal fade" id="loadModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Load Saved Meal Plans</h5>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="refresh-plans-btn" title="Refresh List">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
            </div>
            <div class="modal-body">
                <div id="saved-plans-list">
                    <!-- Content will be loaded dynamically via AJAX -->
                    <div class="text-center py-4">
                        <p class="text-muted">Click "Load Saved Plans" to fetch your meal plans.</p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

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

<!-- Data Sources Reference Modal -->
<div class="modal fade" id="referencesModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title"><i class="bi bi-book"></i> Data Sources & References</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="lead">The meal plan generator uses data based on credible food composition and nutrient datasets:</p>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-primary">
                            <i class="bi bi-globe"></i> USDA's FoodData Central
                        </h6>
                        <p class="card-text">A comprehensive public food composition database maintained by the U.S. Department of Agriculture.</p>
                        <a href="https://fdc.nal.usda.gov/" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-box-arrow-up-right"></i> Visit FoodData Central
                        </a>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-primary">
                            <i class="bi bi-flag"></i> Philippine Food Composition Tables (PhilFCT®)
                        </h6>
                        <p class="card-text">Food and Nutrition Research Institute (FNRI), Department of Science and Technology (DOST), Philippines. Provides nutrient data for local Filipino foods.</p>
                        <a href="https://fnri.dost.gov.ph/images/sources/SeminarSeries/44th/ST/44FSS-02-18-min.pdf" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-file-pdf"></i> View PhilFCT Document (PDF)
                        </a>
                    </div>
                </div>
                
                <div class="card mb-3">
                    <div class="card-body">
                        <h6 class="card-title text-primary">
                            <i class="bi bi-table"></i> FAO/INFOODS Food Composition Tables
                        </h6>
                        <p class="card-text">Food and Agriculture Organization - International Network of Food Data Systems (FAO/INFOODS) listing food composition resources for the Philippines.</p>
                        <a href="https://www.fao.org/infoods/infoods/tables-and-databases/phillipians/en/" target="_blank" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-box-arrow-up-right"></i> Visit FAO/INFOODS Philippines
                        </a>
                    </div>
                </div>
                
                <div class="alert alert-info mt-4">
                    <i class="bi bi-info-circle"></i> <strong>Note:</strong> The meal data in this system is based on these credible sources to provide accurate nutritional information for Filipino dishes.
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

/* Blinking green info icon animation */
.info-icon-blink {
  color: #28a745;
  animation: blinkGreen 2s ease-in-out infinite;
  transition: transform 0.2s ease;
}

.info-icon-blink:hover {
  transform: scale(1.2);
  animation: none;
  color: #218838;
}

@keyframes blinkGreen {
  0%, 100% {
    opacity: 1;
    text-shadow: 0 0 5px rgba(40, 167, 69, 0.5);
  }
  50% {
    opacity: 0.5;
    text-shadow: 0 0 15px rgba(40, 167, 69, 0.8);
  }
}

/* References modal enhancements */
#referencesModal .modal-header {
  background: linear-gradient(90deg, #28a745 0%, #20c997 100%);
}

#referencesModal .card {
  border-left: 4px solid #28a745;
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

#referencesModal .card:hover {
  transform: translateX(5px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}
</style>
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
    // Ensure meal_plan is an array
    if (!is_array($meal_plan)) {
        $meal_plan = [];
    }
    foreach ($meal_plan as $i => $day): 
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
</div>
</main>
<?php include 'footer.php'; ?>
<script>
// Global variables
let currentMealPlan = <?= json_encode(is_array($meal_plan) ? $meal_plan : []) ?>;

// Loading screen logic
const generateBtn = document.getElementById('generate-btn');
if (generateBtn) {
    generateBtn.addEventListener('click', function(e) {
        e.preventDefault();
        showLoadingOverlay();
        setTimeout(function() {
            window.location.href = generateBtn.href;
        }, 1500);
    });
}

// Show loading overlay
function showLoadingOverlay() {
        let overlay = document.createElement('div');
        overlay.id = 'foodify-loading-overlay';
        overlay.style.position = 'fixed';
        overlay.style.top = 0;
        overlay.style.left = 0;
        overlay.style.width = '100vw';
        overlay.style.height = '100vh';
        overlay.style.background = 'rgba(255,255,255,0.97)';
        overlay.style.display = 'flex';
        overlay.style.flexDirection = 'column';
        overlay.style.alignItems = 'center';
        overlay.style.justifyContent = 'center';
        overlay.style.zIndex = 9999;
    
        let logo = document.createElement('img');
        logo.src = '../uploads/images/foodify_icon.png';
        logo.alt = 'Foodify Logo';
        logo.style.width = '120px';
        logo.style.height = '120px';
        logo.style.marginBottom = '20px';
        logo.style.animation = 'foodify-bounce 1s infinite';
    
        let spinner = document.createElement('div');
        spinner.className = 'spinner-border text-success mb-3';
        spinner.style.width = '3rem';
        spinner.style.height = '3rem';
    
        let text = document.createElement('div');
        text.innerText = 'Cooking up your meal plan...';
        text.style.fontSize = '1.5rem';
        text.style.fontWeight = 'bold';
        text.style.color = '#388e3c';
    
        overlay.appendChild(logo);
        overlay.appendChild(spinner);
        overlay.appendChild(text);
        document.body.appendChild(overlay);
}

// Calculate and update meal plan totals
function updateMealPlanTotals() {
    const totals = { calories: 0, protein: 0, carbs: 0, fat: 0 };
    
    currentMealPlan.forEach(day => {
        ['Breakfast', 'Lunch', 'Dinner'].forEach(meal => {
            if (day[meal]) {
                totals.calories += parseInt(day[meal]['Calories (kcal)'] || 0);
                totals.protein += parseFloat(day[meal]['Protein (g)'] || 0);
                totals.carbs += parseFloat(day[meal]['Carbs (g)'] || 0);
                totals.fat += parseFloat(day[meal]['Fat (g)'] || 0);
            }
        });
    });
    
    document.getElementById('total-calories').textContent = totals.calories;
    document.getElementById('total-protein').textContent = totals.protein.toFixed(1) + 'g';
    document.getElementById('total-carbs').textContent = totals.carbs.toFixed(1) + 'g';
    document.getElementById('total-fat').textContent = totals.fat.toFixed(1) + 'g';
}

// Save meal plan functionality
document.getElementById('save-btn').addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('saveModal'));
    modal.show();
});

document.getElementById('confirm-save').addEventListener('click', function() {
    const planName = document.getElementById('plan_name').value.trim();
    const isShared = document.getElementById('is_shared').checked;
    
    if (!planName) {
        alert('Please enter a plan name.');
        return;
    }
    
    // Validate meal plan data
    if (!currentMealPlan || currentMealPlan.length === 0) {
        alert('No meal plan data to save. Please generate a meal plan first.');
        return;
    }
    
    // Show loading state
    const saveBtn = document.getElementById('confirm-save');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Saving...';
    saveBtn.disabled = true;
    
    const formData = new FormData();
    formData.append('action', 'save_meal_plan');
    formData.append('plan_name', planName);
    formData.append('plan_data', JSON.stringify(currentMealPlan));
    formData.append('filters', JSON.stringify(getCurrentFilters()));
    if (isShared) formData.append('is_shared', '1');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Show success message with better styling
            showNotification(data.message, 'success');
            bootstrap.Modal.getInstance(document.getElementById('saveModal')).hide();
            document.getElementById('saveForm').reset();
            // Reload the page to update saved plans list
            setTimeout(() => location.reload(), 1000);
        } else {
            let errorMessage = 'Error: ' + data.message;
            if (data.debug) {
                console.error('Save error with debug info:', data);
                errorMessage += ' (Check console for debug details)';
            } else {
                console.error('Save error:', data);
            }
            showNotification(errorMessage, 'error');
        }
    })
    .catch(error => {
        console.error('Network error:', error);
        showNotification('Network error occurred while saving the meal plan.', 'error');
    })
    .finally(() => {
        // Restore button state
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
});

// Load meal plans functionality
document.getElementById('load-btn').addEventListener('click', function() {
    // Show loading state
    document.getElementById('saved-plans-list').innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-3">Loading saved meal plans...</p>
        </div>
    `;
    
    const modal = new bootstrap.Modal(document.getElementById('loadModal'));
    modal.show();
    
    // Fetch saved plans
    fetchSavedPlans();
});

// Function to fetch saved plans via AJAX
function fetchSavedPlans() {
    const formData = new FormData();
    formData.append('action', 'get_saved_plans');
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            displaySavedPlans(data.data);
        } else {
            document.getElementById('saved-plans-list').innerHTML = `
                <div class="text-center py-4">
                    <i class="bi bi-exclamation-triangle text-warning display-1"></i>
                    <p class="text-muted mt-3">Error loading meal plans: ${data.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error fetching saved plans:', error);
        document.getElementById('saved-plans-list').innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-exclamation-triangle text-danger display-1"></i>
                <p class="text-muted mt-3">Network error occurred while loading meal plans.</p>
            </div>
        `;
    });
}

// Function to display saved plans
function displaySavedPlans(plans) {
    const container = document.getElementById('saved-plans-list');
    
    if (plans.length === 0) {
        container.innerHTML = `
            <div class="text-center py-4">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <p class="text-muted mt-3">No saved meal plans found.</p>
                <p class="text-muted">Generate and save your first meal plan to get started!</p>
            </div>
        `;
        return;
    }
    
    let html = '<div class="row">';
    plans.forEach(plan => {
        const createdDate = new Date(plan.created_at).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric'
        });
        
        html += `
            <div class="col-md-6 mb-3">
                <div class="card h-100">
                    <div class="card-body">
                        <h6 class="card-title">${escapeHtml(plan.plan_name)}</h6>
                        <p class="card-text small text-muted">
                            Created: ${createdDate}<br>
                            <strong>${plan.total_calories}</strong> calories<br>
                            <strong>${plan.total_protein}g</strong> protein
                        </p>
                    </div>
                    <div class="card-footer bg-transparent">
                        <div class="btn-group w-100" role="group">
                            <button type="button" class="btn btn-sm btn-outline-primary load-plan-btn" data-plan-id="${plan.id}">
                                <i class="bi bi-upload"></i> Load
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success share-plan-btn" data-plan-id="${plan.id}">
                                <i class="bi bi-share"></i> Share
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger delete-plan-btn" data-plan-id="${plan.id}">
                                <i class="bi bi-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    });
    html += '</div>';
    
    container.innerHTML = html;
}

// Helper function to escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Load specific meal plan
document.addEventListener('click', function(e) {
    if (e.target.classList.contains('load-plan-btn')) {
        const planId = e.target.dataset.planId;
        loadMealPlan(planId);
    }
    
    if (e.target.classList.contains('delete-plan-btn')) {
        const planId = e.target.dataset.planId;
        if (confirm('Are you sure you want to delete this meal plan?')) {
            deleteMealPlan(planId);
        }
    }
    
    if (e.target.classList.contains('share-plan-btn')) {
        const planId = e.target.dataset.planId;
        shareMealPlan(planId);
    }
});

function loadMealPlan(planId) {
    const formData = new FormData();
    formData.append('action', 'load_meal_plan');
    formData.append('plan_id', planId);
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentMealPlan = JSON.parse(data.data.plan_data);
            updateMealPlanDisplay();
            updateMealPlanTotals();
            bootstrap.Modal.getInstance(document.getElementById('loadModal')).hide();
            showNotification('Meal plan loaded successfully!', 'success');
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while loading the meal plan.', 'error');
    });
}

function deleteMealPlan(planId) {
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
            showNotification(data.message, 'success');
            // Refresh the saved plans list
            fetchSavedPlans();
        } else {
            showNotification('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('An error occurred while deleting the meal plan.', 'error');
    });
}

function shareMealPlan(planId) {
    const modal = new bootstrap.Modal(document.getElementById('shareModal'));
    modal.show();
    
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
                <div class="text-center">
                    <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Meal Plan Shared Successfully!</h5>
                    <p class="text-muted">Share this link with others:</p>
                    <div class="input-group">
                        <input type="text" class="form-control" value="${window.location.origin + '/' + data.share_url}" readonly id="share-link">
                        <button class="btn btn-outline-secondary" type="button" onclick="copyShareLink()">
                            <i class="bi bi-copy"></i> Copy
                        </button>
                    </div>
                </div>
            `;
        } else {
            document.getElementById('share-content').innerHTML = `
                <div class="text-center">
                    <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Error Sharing Meal Plan</h5>
                    <p class="text-muted">${data.message}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        document.getElementById('share-content').innerHTML = `
            <div class="text-center">
                <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Error Sharing Meal Plan</h5>
                <p class="text-muted">An error occurred while sharing the meal plan.</p>
            </div>
        `;
    });
}

function copyShareLink() {
    const shareLink = document.getElementById('share-link');
    shareLink.select();
    shareLink.setSelectionRange(0, 99999);
    document.execCommand('copy');
    alert('Share link copied to clipboard!');
}

function getCurrentFilters() {
    return {
        cal_min: document.getElementById('cal_min').value,
        cal_max: document.getElementById('cal_max').value
    };
}

function updateMealPlanDisplay() {
    // This function would update the visual display of the meal plan
    // For now, we'll just reload the page to show the loaded meal plan
    location.reload();
}

// Export functionality
document.getElementById('export-btn').addEventListener('click', function() {
    exportMealPlan();
});

function exportMealPlan() {
    const totals = { calories: 0, protein: 0, carbs: 0, fat: 0 };
    let csvContent = "Day,Meal,Dish Name,Serving Size,Calories,Protein (g),Carbs (g),Fat (g)\n";
    
    currentMealPlan.forEach((day, dayIndex) => {
        ['Breakfast', 'Lunch', 'Dinner'].forEach(meal => {
            if (day[meal]) {
                const dish = day[meal];
                totals.calories += parseInt(dish['Calories (kcal)'] || 0);
                totals.protein += parseFloat(dish['Protein (g)'] || 0);
                totals.carbs += parseFloat(dish['Carbs (g)'] || 0);
                totals.fat += parseFloat(dish['Fat (g)'] || 0);
                
                csvContent += `Day ${dayIndex + 1},${meal},"${dish['Dish Name']}","${dish['Serving Size']}",${dish['Calories (kcal)']},${dish['Protein (g)']},${dish['Carbs (g)']},${dish['Fat (g)']}\n`;
            }
        });
    });
    
    csvContent += `\nTOTALS,,,,${totals.calories},${totals.protein.toFixed(1)},${totals.carbs.toFixed(1)},${totals.fat.toFixed(1)}\n`;
    
    const blob = new Blob([csvContent], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `meal_plan_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
}

// Notification system
function showNotification(message, type = 'info') {
    // Remove any existing notifications
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
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}

// Debug function to log current meal plan data
function debugMealPlanData() {
    console.log('Current Meal Plan Data:', currentMealPlan);
    console.log('Meal Plan Length:', currentMealPlan ? currentMealPlan.length : 'null');
    console.log('User Session:', {
        user_id: <?= json_encode(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null) ?>,
        username: <?= json_encode(isset($_SESSION['username']) ? $_SESSION['username'] : null) ?>
    });
}

// Add debug button for troubleshooting
if (window.location.search.includes('debug=1')) {
    const debugBtn = document.createElement('button');
    debugBtn.className = 'btn btn-warning btn-sm';
    debugBtn.innerHTML = '<i class="bi bi-bug"></i> Debug';
    debugBtn.onclick = debugMealPlanData;
    document.querySelector('.card-header').appendChild(debugBtn);
}

// Refresh button functionality
document.addEventListener('click', function(e) {
    if (e.target.closest('#refresh-plans-btn')) {
        fetchSavedPlans();
    }
});

// Initialize totals on page load
document.addEventListener('DOMContentLoaded', function() {
    updateMealPlanTotals();
    
    // Debug mode
    if (window.location.search.includes('debug=1')) {
        console.log('Debug mode enabled');
        debugMealPlanData();
    }
});

// Add bounce animation
const style = document.createElement('style');
style.innerHTML = `
@keyframes foodify-bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-20px); }
}`;
document.head.appendChild(style);
</script>

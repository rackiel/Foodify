<?php
session_start();
include '../config/db.php';
include_once 'challenge_hooks.php';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// --- Add Ingredient handler ---
if (isset($_POST['add_ingredient'])) {
  $ingredient_name = trim($_POST['ingredient_name'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $unit = trim($_POST['unit'] ?? '');
  $quantity = $_POST['quantity'] !== '' ? $_POST['quantity'] : null;
  $expiration_date = trim($_POST['expiration_date'] ?? '');
  $vitamins = trim($_POST['vitamins'] ?? '');
  $remarks = trim($_POST['remarks'] ?? '');
  $image_path = ''; // Default to empty string instead of null
  if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === UPLOAD_ERR_OK) {
    $imgTmp = $_FILES['image_path']['tmp_name'];
    $imgName = basename($_FILES['image_path']['name']);
    $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($imgExt, $allowed)) {
      $newName = uniqid('ingredient_', true) . '.' . $imgExt;
      $uploadDir = __DIR__ . '/../uploads/ingredients/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }
      $dest = $uploadDir . $newName;
      if (move_uploaded_file($imgTmp, $dest)) {
        $image_path = 'uploads/ingredients/' . $newName;
      }
    }
  }
  if ($ingredient_name === '' || $category === '') {
    echo json_encode(['success' => false, 'error' => 'Ingredient name and category are required.']);
    exit;
  }
  $stmt = $conn->prepare("INSERT INTO ingredient (ingredient_name, category, unit, quantity, remarks, user_id, image_path, expiration_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
  $stmt->bind_param(
    'sssdssss',
    $ingredient_name,
    $category,
    $unit,
    $quantity,
    $remarks,
    $user_id,
    $image_path,
    $expiration_date
  );
  if ($stmt->execute()) {
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
  }
  exit;
}

// --- Update Ingredient handler ---
if (isset($_POST['update_ingredient'])) {
  $ingredient_id = intval($_POST['ingredient_id']);
  $ingredient_name = trim($_POST['ingredient_name'] ?? '');
  $category = trim($_POST['category'] ?? '');
  $unit = trim($_POST['unit'] ?? '');
  $quantity = $_POST['quantity'] !== '' ? $_POST['quantity'] : null;
  $expiration_date = trim($_POST['expiration_date'] ?? '');
  $remarks = trim($_POST['remarks'] ?? '');

  // Verify ownership before updating
  $verify_stmt = $conn->prepare("SELECT user_id FROM ingredient WHERE ingredient_id=?");
  $verify_stmt->bind_param('i', $ingredient_id);
  $verify_stmt->execute();
  $verify_result = $verify_stmt->get_result();
  if ($verify_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Ingredient not found.']);
    exit;
  }
  $owner = $verify_result->fetch_assoc();
  if ($owner['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to update this ingredient.']);
    exit;
  }

  // Check if new image uploaded
  $image_path = ''; // Default to empty string instead of null
  if (isset($_FILES['image_path']) && $_FILES['image_path']['error'] === UPLOAD_ERR_OK) {
    $imgTmp = $_FILES['image_path']['tmp_name'];
    $imgName = basename($_FILES['image_path']['name']);
    $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (in_array($imgExt, $allowed)) {
      $newName = uniqid('ingredient_', true) . '.' . $imgExt;
      $uploadDir = __DIR__ . '/../uploads/ingredients/';
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
      }
      $dest = $uploadDir . $newName;
      if (move_uploaded_file($imgTmp, $dest)) {
        $image_path = 'uploads/ingredients/' . $newName;
      }
    }
  }

  if ($ingredient_name === '' || $category === '') {
    echo json_encode(['success' => false, 'error' => 'Ingredient name and category are required.']);
    exit;
  }

  if ($image_path) {
    $stmt = $conn->prepare("UPDATE ingredient SET ingredient_name=?, category=?, unit=?, quantity=?, remarks=?, image_path=?, expiration_date=? WHERE ingredient_id=? AND user_id=?");
    $stmt->bind_param('sssdsssii', $ingredient_name, $category, $unit, $quantity, $remarks, $image_path, $expiration_date, $ingredient_id, $user_id);
  } else {
    $stmt = $conn->prepare("UPDATE ingredient SET ingredient_name=?, category=?, unit=?, quantity=?, remarks=?, expiration_date=? WHERE ingredient_id=? AND user_id=?");
    $stmt->bind_param('sssdssii', $ingredient_name, $category, $unit, $quantity, $remarks, $expiration_date, $ingredient_id, $user_id);
  }

  if ($stmt->execute()) {
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
  }
  exit;
}

// --- Delete Ingredient handler ---
if (isset($_POST['delete_ingredient'])) {
  $ingredient_id = intval($_POST['ingredient_id']);

  // Verify ownership before deleting
  $verify_stmt = $conn->prepare("SELECT user_id FROM ingredient WHERE ingredient_id=?");
  $verify_stmt->bind_param('i', $ingredient_id);
  $verify_stmt->execute();
  $verify_result = $verify_stmt->get_result();
  if ($verify_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Ingredient not found.']);
    exit;
  }
  $owner = $verify_result->fetch_assoc();
  if ($owner['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this ingredient.']);
    exit;
  }

  $stmt = $conn->prepare("DELETE FROM ingredient WHERE ingredient_id=? AND user_id=?");
  $stmt->bind_param('ii', $ingredient_id, $user_id);
  if ($stmt->execute()) {
    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
  }
  exit;
}

// --- Use Ingredient handler ---
if (isset($_POST['use_ingredient'])) {
  $ingredient_id = intval($_POST['ingredient_id']);

  // Verify ownership before marking as used
  $verify_stmt = $conn->prepare("SELECT user_id FROM ingredient WHERE ingredient_id=?");
  $verify_stmt->bind_param('i', $ingredient_id);
  $verify_stmt->execute();
  $verify_result = $verify_stmt->get_result();
  if ($verify_result->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Ingredient not found.']);
    exit;
  }
  $owner = $verify_result->fetch_assoc();
  if ($owner['user_id'] != $user_id) {
    echo json_encode(['success' => false, 'error' => 'You do not have permission to use this ingredient.']);
    exit;
  }

  $stmt = $conn->prepare("UPDATE ingredient SET status='used' WHERE ingredient_id=? AND user_id=?");
  $stmt->bind_param('ii', $ingredient_id, $user_id);
  if ($stmt->execute()) {
    // Trigger waste reduction challenge progress update
    triggerWasteReductionChallenge($conn, $user_id);

    echo json_encode(['success' => true]);
  } else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $stmt->error]);
  }
  exit;
}

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>
<style>
  .ingredient-card-animate {
    opacity: 0;
    transform: translateY(30px);
    animation: fadeSlideIn 0.7s ease forwards;
    transition: transform 0.22s cubic-bezier(.4, 2, .6, 1), box-shadow 0.22s, background 0.22s;
    will-change: transform, box-shadow;
  }

  .ingredient-card-animate:hover {
    transform: scale(1.045) translateY(-6px) rotateZ(-0.5deg);
    box-shadow: 0 12px 32px rgba(120, 60, 60, 0.18), 0 2px 8px rgba(60, 120, 60, 0.07);
    border: 1.5px solid rgb(255, 227, 227);
  }

  .ingredient-card-animate:active {
    transform: scale(0.97) translateY(1px) rotateZ(0deg);
    box-shadow: 0 4px 12px rgba(60, 120, 60, 0.10);
  }

  .expiring-soon {
    border: 2px solid #ff9800 !important;
    background-color: #fff3e0;
  }



  .form-check-input:checked {
    background-color: #4caf50;
    border-color: #4caf50;
  }

  .form-check-label {
    cursor: pointer;
    user-select: none;
  }

  @keyframes fadeSlideIn {
    to {
      opacity: 1;
      transform: none;
    }
  }
</style>
<main id="main" class="main">
  <div class="container py-5">
    <h2>Ingredients Feed</h2>
    <div class="mb-4 d-flex justify-content-between align-items-center">
      <input type="text" id="ingredientSearch" class="form-control form-control-lg w-75" placeholder="Search ingredients by name, category, or remarks..." autocomplete="off">
      <button class="btn btn-success btn-lg" data-bs-toggle="modal" data-bs-target="#addIngredientModal"><i class="bi bi-plus-circle"></i> Add Ingredient</button>
    </div>

    <!-- Add Ingredient Modal -->
    <div class="modal fade" id="addIngredientModal" tabindex="-1" aria-labelledby="addIngredientModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="addIngredientForm" enctype="multipart/form-data">
            <div class="modal-header">
              <h5 class="modal-title" id="addIngredientModalLabel">Add Ingredient</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="ingredient_name" class="form-label">Ingredient Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="ingredient_name" name="ingredient_name" required>
              </div>
              <div class="mb-3">
                <label for="category" class="form-label">Category <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="category" name="category" required>
              </div>
              <div class="mb-3">
                <label for="unit" class="form-label">Unit</label>
                <select class="form-control" id="unit" name="unit">
                  <option value="">Select unit</option>
                  <option value="grams">grams</option>
                  <option value="kilograms">kilograms</option>
                  <option value="ml">ml</option>
                  <option value="liters">liters</option>
                  <option value="pcs">pcs</option>
                  <option value="cups">cups</option>
                  <option value="tablespoons">tablespoons</option>
                  <option value="teaspoons">teaspoons</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="quantity" class="form-label">Quantity</label>
                <input type="number" step="0.01" class="form-control" id="quantity" name="quantity" placeholder="e.g., 500, 2.5">
                <small class="form-text text-muted">Enter the amount (number only)</small>
              </div>
              <div class="mb-3">
                <label for="expiration_date" class="form-label">Expiration Date</label>
                <input type="date" class="form-control" id="expiration_date" name="expiration_date">
              </div>
              <div class="mb-3">
                <label for="image_path" class="form-label">Image</label>
                <input type="file" class="form-control" id="image_path" name="image_path" accept="image/*">
              </div>


              <div class="mb-3">
                <label for="remarks" class="form-label">Remarks</label>
                <textarea class="form-control" id="remarks" name="remarks"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-success">Add Ingredient</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Update Ingredient Modal -->
    <div class="modal fade" id="updateIngredientModal" tabindex="-1" aria-labelledby="updateIngredientModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form id="updateIngredientForm" enctype="multipart/form-data">
            <input type="hidden" id="update_ingredient_id" name="ingredient_id">
            <div class="modal-header">
              <h5 class="modal-title" id="updateIngredientModalLabel">Update Ingredient</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <div class="mb-3">
                <label for="update_ingredient_name" class="form-label">Ingredient Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="update_ingredient_name" name="ingredient_name" required>
              </div>
              <div class="mb-3">
                <label for="update_category" class="form-label">Category <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="update_category" name="category" required>
              </div>
              <div class="mb-3">
                <label for="update_unit" class="form-label">Unit</label>
                <select class="form-control" id="update_unit" name="unit">
                  <option value="">Select unit</option>
                  <option value="grams">grams</option>
                  <option value="kilograms">kilograms</option>
                  <option value="ml">ml</option>
                  <option value="liters">liters</option>
                  <option value="pcs">pcs</option>
                  <option value="cups">cups</option>
                  <option value="tablespoons">tablespoons</option>
                  <option value="teaspoons">teaspoons</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="update_quantity" class="form-label">Quantity</label>
                <input type="number" step="0.01" class="form-control" id="update_quantity" name="quantity" placeholder="e.g., 500, 2.5">
                <small class="form-text text-muted">Enter the amount (number only)</small>
              </div>
              <div class="mb-3">
                <label for="update_expiration_date" class="form-label">Expiration Date</label>
                <input type="date" class="form-control" id="update_expiration_date" name="expiration_date">
              </div>
              <div class="mb-3">
                <label for="update_image_path" class="form-label">Image (leave empty to keep current)</label>
                <input type="file" class="form-control" id="update_image_path" name="image_path" accept="image/*">
              </div>


              <div class="mb-3">
                <label for="update_remarks" class="form-label">Remarks</label>
                <textarea class="form-control" id="update_remarks" name="remarks"></textarea>
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-primary">Update Ingredient</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <div class="row g-4" id="ingredientCardGrid">
      <?php
      // Auto-update expired ingredients (move them from active to expired status) - only for current user
      $update_expired = $conn->prepare("UPDATE ingredient SET status='expired' WHERE expiration_date < CURDATE() AND status='active' AND user_id=?");
      $update_expired->bind_param('i', $user_id);
      $update_expired->execute();

      // Get only active ingredients for the current user
      $stmt = $conn->prepare("SELECT * FROM ingredient WHERE status='active' AND user_id=? ORDER BY created_at DESC");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $delay = 0;
      if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          $ingredient_id = $row['ingredient_id'];

          // Check expiration status (only for warning, not expired since they're auto-moved)
          $expiring_class = '';
          $expiration_badge = '';
          if (!empty($row['expiration_date'])) {
            $expiration_date = new DateTime($row['expiration_date']);
            $today = new DateTime();
            $interval = $today->diff($expiration_date);
            $days_diff = (int)$interval->format('%R%a');

            // Only show warning for expiring soon (expired items are already filtered out)
            if ($days_diff <= 7 && $days_diff >= 0) {
              $expiring_class = 'expiring-soon';
              $expiration_badge = '<span class="badge bg-warning">Expiring in ' . $days_diff . ' day(s)</span>';
            }
          }

          $searchText = strtolower(
            $row['ingredient_name'] . ' ' .
              $row['category'] . ' ' .
              $row['remarks']
          );
          echo '<div class="col-12 col-md-6 col-lg-4 ingredient-card-col" data-search="' . htmlspecialchars($searchText) . '">';
          echo '  <div class="card h-100 shadow-sm ingredient-card-animate ' . $expiring_class . '" style="animation-delay: ' . $delay . 's">';
          echo '    <div class="card-body">';
          echo '      <div class="d-flex align-items-center mb-3">';
          echo '        <img src="' . (isset($row['image_path']) && $row['image_path'] ? '../' . htmlspecialchars($row['image_path']) : '../uploads/profile_picture/no_image.png') . '" alt="Ingredient" class="rounded-circle me-3" width="48" height="48">';
          echo '        <div>';
          echo '          <h5 class="card-title mb-0">' . htmlspecialchars($row['ingredient_name']) . '</h5>';
          // Display quantity and unit together
          $quantityDisplay = '';
          if (!empty($row['quantity'])) {
            $quantityDisplay = number_format($row['quantity'], 2);
            // Remove trailing zeros
            $quantityDisplay = rtrim($quantityDisplay, '0');
            $quantityDisplay = rtrim($quantityDisplay, '.');
          }
          if (!empty($quantityDisplay) && !empty($row['unit'])) {
            echo ' <span class="badge bg-success ms-1">' . $quantityDisplay . ' ' . htmlspecialchars($row['unit']) . '</span>';
          } elseif (!empty($quantityDisplay)) {
            echo ' <span class="badge bg-success ms-1">Qty: ' . $quantityDisplay . '</span>';
          } elseif (!empty($row['unit'])) {
            echo ' <span class="badge bg-secondary ms-1">' . htmlspecialchars($row['unit']) . '</span>';
          }
          echo $expiration_badge;
          echo '          <small class="text-muted">' . htmlspecialchars($row['category']) . '</small>';
          echo '        </div>';
          echo '      </div>';
          echo '      <ul class="list-unstyled mb-3">';
          if (!empty($row['expiration_date'])) {
            echo '        <li><strong>Expires:</strong> ' . date('M d, Y', strtotime($row['expiration_date'])) . '</li>';
          }

          echo '      </ul>';
          if (!empty($row['remarks'])) {
            echo '<p class="card-text"><em>' . htmlspecialchars($row['remarks']) . '</em></p>';
          }
          echo '      <div class="d-flex justify-content-between align-items-center mt-3">';
          echo '        <small class="text-muted">Posted on ' . date('M d, Y', strtotime($row['created_at'])) . '</small>';
          echo '      </div>';
          echo '      <div class="d-flex gap-2 mt-3">';
          echo '        <button class="btn btn-primary btn-sm" onclick="openUpdateModal(' . $ingredient_id . ')"><i class="bi bi-pencil"></i> Edit</button>';
          echo '        <button class="btn btn-success btn-sm use-btn" data-id="' . $ingredient_id . '"><i class="bi bi-check-circle"></i> Use</button>';
          echo '        <button class="btn btn-warning btn-sm donate-btn" data-id="' . $ingredient_id . '"><i class="bi bi-gift"></i> Donate</button>';
          echo '        <button class="btn btn-danger btn-sm delete-btn" data-id="' . $ingredient_id . '"><i class="bi bi-trash"></i> Delete</button>';
          echo '      </div>';
          echo '    </div>';
          echo '  </div>';
          echo '</div>';
          $delay += 0.08;
        }
      } else {
        echo '<div class="col-12"><div class="alert alert-info text-center">No ingredients found.</div></div>';
      }
      ?>
    </div>
  </div>
</main>
<script>
  document.addEventListener('DOMContentLoaded', function() {


    // Reset Add Modal when opened
    const addModal = document.getElementById('addIngredientModal');
    addModal.addEventListener('show.bs.modal', function() {
      // Reset form
      document.getElementById('addIngredientForm').reset();
    });

    const searchInput = document.getElementById('ingredientSearch');
    const cardCols = document.querySelectorAll('.ingredient-card-col');

    searchInput.addEventListener('input', function() {
      const val = this.value.trim().toLowerCase();
      let anyVisible = false;
      cardCols.forEach(function(card) {
        if (card.getAttribute('data-search').includes(val)) {
          card.style.display = '';
          anyVisible = true;
        } else {
          card.style.display = 'none';
        }
      });
      const grid = document.getElementById('ingredientCardGrid');
      let noResult = document.getElementById('noIngredientResult');
      if (!anyVisible) {
        if (!noResult) {
          noResult = document.createElement('div');
          noResult.className = 'col-12';
          noResult.id = 'noIngredientResult';
          noResult.innerHTML = '<div class="alert alert-warning text-center">No matching ingredients found.</div>';
          grid.appendChild(noResult);
        }
      } else if (noResult) {
        noResult.remove();
      }
    });

    // Add Ingredient AJAX
    document.getElementById('addIngredientForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const form = this;
      const formData = new FormData(form);
      formData.append('add_ingredient', '1');
      fetch(window.location.href, {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            var modal = bootstrap.Modal.getInstance(document.getElementById('addIngredientModal'));
            modal.hide();
            location.reload();
          } else {
            alert('Error: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(() => alert('AJAX error.'));
    });

    // Update Ingredient AJAX
    document.getElementById('updateIngredientForm').addEventListener('submit', function(e) {
      e.preventDefault();
      const form = this;
      const formData = new FormData(form);
      formData.append('update_ingredient', '1');
      fetch(window.location.href, {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            var modal = bootstrap.Modal.getInstance(document.getElementById('updateIngredientModal'));
            modal.hide();
            location.reload();
          } else {
            alert('Error: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(() => alert('AJAX error.'));
    });

    // Use Ingredient
    document.querySelectorAll('.use-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        if (!confirm('Mark this ingredient as used?')) return;
        const id = this.getAttribute('data-id');
        const formData = new FormData();
        formData.append('use_ingredient', '1');
        formData.append('ingredient_id', id);
        fetch(window.location.href, {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              location.reload();
            } else {
              alert('Error: ' + (data.error || 'Unknown error'));
            }
          })
          .catch(() => alert('AJAX error.'));
      });
    });

    // Delete Ingredient
    document.querySelectorAll('.delete-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        if (!confirm('Are you sure you want to delete this ingredient?')) return;
        const id = this.getAttribute('data-id');
        const formData = new FormData();
        formData.append('delete_ingredient', '1');
        formData.append('ingredient_id', id);
        fetch(window.location.href, {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              location.reload();
            } else {
              alert('Error: ' + (data.error || 'Unknown error'));
            }
          })
          .catch(() => alert('AJAX error.'));
      });
    });

    // Donate Ingredient
    document.querySelectorAll('.donate-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        const id = this.getAttribute('data-id');
        // Fetch ingredient data
        fetch('get_ingredient.php?id=' + id)
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // Store ingredient data in sessionStorage
              sessionStorage.setItem('donateIngredientData', JSON.stringify(data.ingredient));
              // Redirect to post_excess_food.php
              window.location.href = 'post_excess_food.php?from_ingredient=1';
            } else {
              alert('Error loading ingredient data');
            }
          })
          .catch(() => alert('AJAX error.'));
      });
    });
  });

  // Open Update Modal with data
  function openUpdateModal(id) {
    fetch('get_ingredient.php?id=' + id)
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.getElementById('update_ingredient_id').value = data.ingredient.ingredient_id;
          document.getElementById('update_ingredient_name').value = data.ingredient.ingredient_name;
          document.getElementById('update_category').value = data.ingredient.category;
          document.getElementById('update_unit').value = data.ingredient.unit || '';
          document.getElementById('update_quantity').value = data.ingredient.quantity || '';
          document.getElementById('update_expiration_date').value = data.ingredient.expiration_date || '';
          document.getElementById('update_remarks').value = data.ingredient.remarks || '';

          var modal = new bootstrap.Modal(document.getElementById('updateIngredientModal'));
          modal.show();
        } else {
          alert('Error loading ingredient data');
        }
      })
      .catch(() => alert('AJAX error.'));
  }
</script>
<?php include 'footer.php'; ?>
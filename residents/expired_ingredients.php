<?php
session_start();
include '../config/db.php';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Auto-update expired ingredients for current user only
$update_expired = $conn->prepare("UPDATE ingredient SET status='expired' WHERE expiration_date < CURDATE() AND status='active' AND user_id=?");
$update_expired->bind_param('i', $user_id);
$update_expired->execute();

// --- Delete Ingredient handler ---
if (isset($_POST['delete_ingredient'])) {
  $ingredient_id = intval($_POST['ingredient_id']);

  // Verify ownership
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
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
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

// --- Restore Ingredient to Active (if user wants to still use it) ---
if (isset($_POST['restore_ingredient'])) {
  $ingredient_id = intval($_POST['ingredient_id']);

  // Verify ownership
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
    echo json_encode(['success' => false, 'error' => 'Access denied.']);
    exit;
  }

  // Clear expiration_date as well to avoid immediate re-expiration by the auto-update
  $stmt = $conn->prepare("UPDATE ingredient SET status='active', expiration_date=NULL WHERE ingredient_id=? AND user_id=?");
  $stmt->bind_param('ii', $ingredient_id, $user_id);
  if ($stmt->execute()) {
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

  .expired-ingredient {
    background-color: #ffebee;
    border: 2px solid #f44336 !important;
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
    <h2>Expired Ingredients</h2>
    <p class="text-muted">Ingredients that have passed their expiration date. Review and delete or restore if still usable.</p>
    <div class="mb-4">
      <input type="text" id="ingredientSearch" class="form-control form-control-lg" placeholder="Search expired ingredients..." autocomplete="off">
    </div>
    <div class="row g-4" id="ingredientCardGrid">
      <?php
      $stmt = $conn->prepare("SELECT * FROM ingredient WHERE status='expired' AND user_id=? ORDER BY expiration_date DESC");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $delay = 0;
      if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          $ingredient_id = $row['ingredient_id'];

          // Calculate days expired
          $days_expired = 0;
          if (isset($row['expiration_date']) && $row['expiration_date'] !== '') {
            $expiration_date = new DateTime($row['expiration_date']);
            $today = new DateTime();
            $interval = $today->diff($expiration_date);
            $days_expired = abs((int)$interval->format('%R%a'));
          }

          // Build search text using safe accessors to avoid undefined index warnings
          $ingredient_name_safe = isset($row['ingredient_name']) ? $row['ingredient_name'] : '';
          $category_safe = isset($row['category']) ? $row['category'] : '';
          $vitamins_safe = isset($row['vitamins']) ? $row['vitamins'] : '';
          $remarks_safe = isset($row['remarks']) ? $row['remarks'] : '';
          $searchText = strtolower(
            $ingredient_name_safe . ' ' .
              $category_safe . ' ' .
              $vitamins_safe . ' ' .
              $remarks_safe
          );
          echo '<div class="col-12 col-md-6 col-lg-4 ingredient-card-col" data-search="' . htmlspecialchars($searchText) . '">';
          echo '  <div class="card h-100 shadow-sm ingredient-card-animate expired-ingredient" style="animation-delay: ' . $delay . 's">';
          echo '    <div class="card-body">';
          echo '      <div class="d-flex align-items-center mb-3">';
          echo '        <img src="' . (isset($row['image_path']) && $row['image_path'] ? '../' . htmlspecialchars($row['image_path']) : '../uploads/profile_picture/no_image.png') . '" alt="Ingredient" class="rounded-circle me-3" width="48" height="48">';
          echo '        <div>';
          echo '          <h5 class="card-title mb-0">' . htmlspecialchars($row['ingredient_name']) . '</h5>';
          if (isset($row['unit']) && $row['unit'] !== '') {
            echo ' <span class="badge bg-secondary ms-1">' . htmlspecialchars($row['unit']) . '</span>';
          }
          echo ' <span class="badge bg-danger ms-1">Expired ' . $days_expired . ' day(s) ago</span>';
          echo '          <small class="text-muted">' . htmlspecialchars($row['category']) . '</small>';
          echo '        </div>';
          echo '      </div>';
          // Determine presence of nutrition fields and only render the list when something meaningful exists
          $caloriesExists = isset($row['calories']) && $row['calories'] !== '';
          $proteinExists = isset($row['protein']) && $row['protein'] !== '';
          $fatExists = isset($row['fat']) && $row['fat'] !== '';
          $carbsExists = isset($row['carbohydrates']) && $row['carbohydrates'] !== '';
          $fiberExists = isset($row['fiber']) && $row['fiber'] !== '';

          $showList = !empty($row['expiration_date']) || $caloriesExists || $proteinExists || $fatExists || $carbsExists || $fiberExists;

          if ($showList) {
            echo '      <ul class="list-unstyled mb-3">';
            if (!empty($row['expiration_date'])) {
              echo '        <li><strong>Expired on:</strong> ' . date('M d, Y', strtotime($row['expiration_date'])) . '</li>';
            }
            if ($caloriesExists) {
              echo '        <li><strong>Calories:</strong> ' . htmlspecialchars($row['calories']) . '</li>';
            }
            if ($proteinExists) {
              echo '        <li><strong>Protein:</strong> ' . htmlspecialchars($row['protein']) . 'g</li>';
            }
            if ($fatExists) {
              echo '        <li><strong>Fat:</strong> ' . htmlspecialchars($row['fat']) . 'g</li>';
            }
            if ($carbsExists) {
              echo '        <li><strong>Carbs:</strong> ' . htmlspecialchars($row['carbohydrates']) . 'g</li>';
            }
            if ($fiberExists) {
              echo '        <li><strong>Fiber:</strong> ' . htmlspecialchars($row['fiber']) . 'g</li>';
            }
            echo '      </ul>';
          }
          if (isset($row['vitamins']) && $row['vitamins'] !== '') {
            echo '<div class="mb-2"><span class="badge bg-info text-dark">Vitamins: ' . htmlspecialchars($row['vitamins']) . '</span></div>';
          }
          if (isset($row['remarks']) && $row['remarks'] !== '') {
            echo '<p class="card-text"><em>' . htmlspecialchars($row['remarks']) . '</em></p>';
          }
          echo '      <div class="d-flex justify-content-between align-items-center mt-3">';
          echo '        <small class="text-muted">Posted on ' . date('M d, Y', strtotime($row['created_at'])) . '</small>';
          echo '      </div>';
          echo '      <div class="d-flex gap-2 mt-3">';
          echo '        <button class="btn btn-warning btn-sm restore-btn" data-id="' . $ingredient_id . '" title="Restore if still usable"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>';
          echo '        <button class="btn btn-danger btn-sm delete-btn" data-id="' . $ingredient_id . '"><i class="bi bi-trash"></i> Delete</button>';
          echo '      </div>';
          echo '    </div>';
          echo '  </div>';
          echo '</div>';
          $delay += 0.08;
        }
      } else {
        echo '<div class="col-12"><div class="alert alert-success text-center"><i class="bi bi-check-circle"></i> No expired ingredients! Keep up the good work.</div></div>';
      }
      ?>
    </div>
  </div>
</main>
<script>
  document.addEventListener('DOMContentLoaded', function() {
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

    // Restore Ingredient
    document.querySelectorAll('.restore-btn').forEach(function(btn) {
      btn.addEventListener('click', function() {
        if (!confirm('Restore this ingredient back to the ingredients feed? Make sure it is still safe to consume.')) return;
        const id = this.getAttribute('data-id');
        const formData = new FormData();
        formData.append('restore_ingredient', '1');
        formData.append('ingredient_id', id);
        fetch(window.location.href, {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              // After restore, send user back to the Ingredients Feed where restored items appear
              window.location.href = 'input_ingredients.php';
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
        if (!confirm('Are you sure you want to permanently delete this ingredient?')) return;
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
  });
</script>
<?php include 'footer.php'; ?>
<?php
session_start();
include '../config/db.php';
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// --- Restore Ingredient to Active ---
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

  $stmt = $conn->prepare("UPDATE ingredient SET status='active' WHERE ingredient_id=? AND user_id=?");
  $stmt->bind_param('ii', $ingredient_id, $user_id);
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
    border: 1.5px solid rgb(227, 255, 227);
  }

  .ingredient-card-animate:active {
    transform: scale(0.97) translateY(1px) rotateZ(0deg);
    box-shadow: 0 4px 12px rgba(60, 120, 60, 0.10);
  }

  .used-ingredient {
    background-color: #e8f5e9;
    border: 2px solid #4caf50 !important;
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
    <h2>Used Ingredients</h2>
    <p class="text-muted">Ingredients that have been marked as used. You can restore them back to the ingredients feed.</p>
    <div class="mb-4">
      <input type="text" id="ingredientSearch" class="form-control form-control-lg" placeholder="Search used ingredients..." autocomplete="off">
    </div>
    <div class="row g-4" id="ingredientCardGrid">
      <?php
      $stmt = $conn->prepare("SELECT * FROM ingredient WHERE status='used' AND user_id=? ORDER BY created_at DESC");
      $stmt->bind_param('i', $user_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $delay = 0;
      if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
          $ingredient_id = $row['ingredient_id'];
          $searchText = strtolower(
            $row['ingredient_name'] . ' ' .
              $row['category'] . ' ' .
              $row['remarks']
          );
          echo '<div class="col-12 col-md-6 col-lg-4 ingredient-card-col" data-search="' . htmlspecialchars($searchText) . '">';
          echo '  <div class="card h-100 shadow-sm ingredient-card-animate used-ingredient" style="animation-delay: ' . $delay . 's">';
          echo '    <div class="card-body">';
          echo '      <div class="d-flex align-items-center mb-3">';
          echo '        <img src="' . (isset($row['image_path']) && $row['image_path'] ? '../' . htmlspecialchars($row['image_path']) : '../uploads/profile_picture/no_image.png') . '" alt="Ingredient" class="rounded-circle me-3" width="48" height="48">';
          echo '        <div>';
          echo '          <h5 class="card-title mb-0">' . htmlspecialchars($row['ingredient_name']) . '</h5>';
          if (!empty($row['unit'])) {
            echo ' <span class="badge bg-secondary ms-1">' . htmlspecialchars($row['unit']) . '</span>';
          }
          echo ' <span class="badge bg-success ms-1">Used</span>';
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
          echo '        <button class="btn btn-warning btn-sm restore-btn" data-id="' . $ingredient_id . '"><i class="bi bi-arrow-counterclockwise"></i> Restore</button>';
          echo '        <button class="btn btn-danger btn-sm delete-btn" data-id="' . $ingredient_id . '"><i class="bi bi-trash"></i> Delete</button>';
          echo '      </div>';
          echo '    </div>';
          echo '  </div>';
          echo '</div>';
          $delay += 0.08;
        }
      } else {
        echo '<div class="col-12"><div class="alert alert-info text-center">No used ingredients found.</div></div>';
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
        if (!confirm('Restore this ingredient back to the ingredients feed?')) return;
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
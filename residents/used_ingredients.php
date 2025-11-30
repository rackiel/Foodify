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
    
    <!-- Meal Suggestions Section -->
    <div class="card mb-4 border-warning" style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h5 class="card-title mb-1"><i class="bi bi-lightbulb text-warning"></i> Prevent Food Waste</h5>
            <p class="card-text mb-0 text-muted">Get meal suggestions from your expiring ingredients to prevent spoilage</p>
          </div>
          <button class="btn btn-warning btn-lg" id="getMealSuggestionsBtn">
            <i class="bi bi-egg-fried"></i> Get Meal Suggestions
          </button>
        </div>
        <div id="expiringIngredientsList" class="mt-3"></div>
      </div>
    </div>
    
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

<!-- Meal Suggestions Modal -->
<div class="modal fade" id="mealSuggestionsModal" tabindex="-1">
  <div class="modal-dialog modal-xl">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="bi bi-egg-fried"></i> Meal Suggestions to Prevent Spoilage</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="mealSuggestionsContent">
        <div class="text-center py-4">
          <div class="spinner-border text-warning" role="status">
            <span class="visually-hidden">Loading...</span>
          </div>
          <p class="mt-2">Generating meal suggestions...</p>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {
    // Load expiring ingredients on page load
    loadExpiringIngredients();
    
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

    // Load expiring ingredients
    function loadExpiringIngredients() {
      fetch('get_expiring_ingredients.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_expiring'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success && data.ingredients && data.ingredients.length > 0) {
          const listDiv = document.getElementById('expiringIngredientsList');
          let html = '<div class="row g-2">';
          data.ingredients.forEach(ing => {
            const daysLeft = ing.days_until;
            const badgeClass = daysLeft <= 2 ? 'bg-danger' : daysLeft <= 4 ? 'bg-warning' : 'bg-info';
            html += `
              <div class="col-auto">
                <span class="badge ${badgeClass}">${ing.ingredient_name} (${daysLeft} day${daysLeft !== 1 ? 's' : ''} left)</span>
              </div>
            `;
          });
          html += '</div>';
          listDiv.innerHTML = html;
        } else {
          document.getElementById('expiringIngredientsList').innerHTML = 
            '<p class="text-muted mb-0"><i class="bi bi-check-circle"></i> No ingredients expiring soon!</p>';
        }
      })
      .catch(() => {
        // Silently fail if endpoint doesn't exist yet
      });
    }

    // Get meal suggestions
    document.getElementById('getMealSuggestionsBtn').addEventListener('click', function() {
      const btn = this;
      const originalText = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Loading...';
      btn.disabled = true;

      // Show modal
      const modal = new bootstrap.Modal(document.getElementById('mealSuggestionsModal'));
      modal.show();

      // Get expiring ingredients
      fetch('get_expiring_ingredients.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=get_expiring'
      })
      .then(response => response.json())
      .then(data => {
        if (!data.success || !data.ingredients || data.ingredients.length === 0) {
          document.getElementById('mealSuggestionsContent').innerHTML = `
            <div class="text-center py-4">
              <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
              <h5 class="mt-3">No Expiring Ingredients</h5>
              <p class="text-muted">You don't have any ingredients expiring soon. Great job managing your food!</p>
            </div>
          `;
          btn.innerHTML = originalText;
          btn.disabled = false;
          return;
        }

        // Get ingredient names
        const ingredientNames = data.ingredients.map(ing => ing.ingredient_name);
        
        // Get meal suggestions from suggested_recipes.php
        const formData = new FormData();
        formData.append('action', 'get_suggested_recipes');
        formData.append('ingredients', JSON.stringify(ingredientNames));
        formData.append('dietary_preferences', '');
        formData.append('cooking_time', '');
        formData.append('difficulty', '');

        fetch('suggested_recipes.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(suggestions => {
          if (suggestions.success && suggestions.recipes && suggestions.recipes.length > 0) {
            displayMealSuggestions(suggestions.recipes, data.ingredients);
          } else {
            document.getElementById('mealSuggestionsContent').innerHTML = `
              <div class="text-center py-4">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
                <h5 class="mt-3">Unable to Generate Suggestions</h5>
                <p class="text-muted">${suggestions.message || 'Please try again later.'}</p>
              </div>
            `;
          }
          btn.innerHTML = originalText;
          btn.disabled = false;
        })
        .catch(error => {
          document.getElementById('mealSuggestionsContent').innerHTML = `
            <div class="text-center py-4">
              <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
              <h5 class="mt-3">Error</h5>
              <p class="text-muted">An error occurred while generating suggestions. Please try again.</p>
            </div>
          `;
          btn.innerHTML = originalText;
          btn.disabled = false;
        });
      })
      .catch(error => {
        document.getElementById('mealSuggestionsContent').innerHTML = `
          <div class="text-center py-4">
            <i class="bi bi-exclamation-triangle text-danger" style="font-size: 3rem;"></i>
            <h5 class="mt-3">Error</h5>
            <p class="text-muted">Unable to load expiring ingredients. Please try again.</p>
          </div>
        `;
        btn.innerHTML = originalText;
        btn.disabled = false;
      });
    });

    function displayMealSuggestions(recipes, expiringIngredients) {
      const expiringNames = expiringIngredients.map(ing => ing.ingredient_name.toLowerCase());
      
      let html = `
        <div class="alert alert-warning">
          <strong><i class="bi bi-info-circle"></i> Using these expiring ingredients:</strong>
          ${expiringIngredients.map(ing => {
            const daysLeft = ing.days_until;
            const badgeClass = daysLeft <= 2 ? 'bg-danger' : daysLeft <= 4 ? 'bg-warning' : 'bg-info';
            return `<span class="badge ${badgeClass} ms-1">${ing.ingredient_name} (${daysLeft} day${daysLeft !== 1 ? 's' : ''})</span>`;
          }).join('')}
        </div>
        <div class="row g-4">
      `;

      recipes.forEach((recipe, index) => {
        const difficultyBadge = recipe.difficulty_level === 'Easy' ? 'success' : 
                               recipe.difficulty_level === 'Medium' ? 'warning' : 'danger';
        
        html += `
          <div class="col-md-6">
            <div class="card h-100 shadow-sm">
              <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                  <h5 class="card-title">${recipe.title || 'Suggested Recipe'}</h5>
                  <span class="badge bg-${difficultyBadge}">${recipe.difficulty_level || 'Easy'}</span>
                </div>
                <p class="card-text text-muted">${recipe.content || recipe.description || 'A delicious recipe suggestion'}</p>
                
                <div class="mb-3">
                  <strong><i class="bi bi-clock"></i> Cooking Time:</strong> ${recipe.cooking_time || 30} minutes<br>
                  <strong><i class="bi bi-people"></i> Servings:</strong> ${recipe.servings || 4}
                </div>
                
                <div class="mb-3">
                  <strong><i class="bi bi-list-ul"></i> Ingredients:</strong>
                  <p class="small mb-0">${recipe.ingredients || recipe.recipe_ingredients || 'See recipe details'}</p>
                </div>
                
                ${recipe.instructions ? `
                  <div class="mb-3">
                    <strong><i class="bi bi-book"></i> Instructions:</strong>
                    <p class="small mb-0">${recipe.instructions}</p>
                  </div>
                ` : ''}
                
                <div class="d-flex gap-2 mt-3">
                  <a href="suggested_recipes.php" class="btn btn-primary btn-sm">
                    <i class="bi bi-arrow-right"></i> View Full Recipe
                  </a>
                </div>
              </div>
            </div>
          </div>
        `;
      });

      html += '</div>';
      document.getElementById('mealSuggestionsContent').innerHTML = html;
    }
  });
</script>
<?php include 'footer.php'; ?>
<?php
include '../config/db.php';
include_once 'challenge_hooks.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

// Handle AJAX requests BEFORE any HTML output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_donation':
                // Validate required fields
                $required_fields = ['title', 'description', 'food_type', 'quantity', 'location_address', 'contact_method', 'contact_info'];
                $errors = [];
                
                foreach ($required_fields as $field) {
                    if (empty($_POST[$field])) {
                        $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required.';
                    }
                }
                
                if (!empty($errors)) {
                    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
                    exit;
                }
                
                // Sanitize input data
                $title = trim($_POST['title']);
                $description = trim($_POST['description']);
                $food_type = $_POST['food_type'];
                $quantity = trim($_POST['quantity']);
                $expiration_date = !empty($_POST['expiration_date']) ? $_POST['expiration_date'] : null;
                $location_address = trim($_POST['location_address']);
                $location_lat = !empty($_POST['location_lat']) ? floatval($_POST['location_lat']) : null;
                $location_lng = !empty($_POST['location_lng']) ? floatval($_POST['location_lng']) : null;
                $pickup_time_start = !empty($_POST['pickup_time_start']) ? $_POST['pickup_time_start'] : null;
                $pickup_time_end = !empty($_POST['pickup_time_end']) ? $_POST['pickup_time_end'] : null;
                $contact_method = $_POST['contact_method'];
                $contact_info = trim($_POST['contact_info']);
                $dietary_info = !empty($_POST['dietary_info']) ? trim($_POST['dietary_info']) : null;
                $allergens = !empty($_POST['allergens']) ? trim($_POST['allergens']) : null;
                $storage_instructions = !empty($_POST['storage_instructions']) ? trim($_POST['storage_instructions']) : null;
                
                // Handle image uploads
                $uploaded_images = [];
                if (!empty($_FILES['images']['name'][0])) {
                    $upload_dir = '../uploads/food_donations/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0777, true);
                    }
                    
                    foreach ($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_extension = strtolower(pathinfo($_FILES['images']['name'][$key], PATHINFO_EXTENSION));
                            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            
                            if (in_array($file_extension, $allowed_extensions)) {
                                $new_filename = uniqid('food_donation_', true) . '.' . $file_extension;
                                $upload_path = $upload_dir . $new_filename;
                                
                                if (move_uploaded_file($tmp_name, $upload_path)) {
                                    $uploaded_images[] = 'uploads/food_donations/' . $new_filename;
                                }
                            }
                        }
                    }
                }
                
                try {
                    $stmt = $conn->prepare("INSERT INTO food_donations (user_id, title, description, food_type, quantity, expiration_date, location_address, location_lat, location_lng, pickup_time_start, pickup_time_end, contact_method, contact_info, images, dietary_info, allergens, storage_instructions, approval_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $images_json = !empty($uploaded_images) ? json_encode($uploaded_images) : null;
                    $approval_status = 'pending'; // All new donations require approval
                    
                    $stmt->bind_param('isssssssssssssssss', 
                        $_SESSION['user_id'],
                        $title,
                        $description,
                        $food_type,
                        $quantity,
                        $expiration_date,
                        $location_address,
                        $location_lat,
                        $location_lng,
                        $pickup_time_start,
                        $pickup_time_end,
                        $contact_method,
                        $contact_info,
                        $images_json,
                        $dietary_info,
                        $allergens,
                        $storage_instructions,
                        $approval_status
                    );
                    
                    if ($stmt->execute()) {
                        // Trigger challenge progress update
                        triggerDonationChallenge($conn, $_SESSION['user_id']);
                        
                        echo json_encode(['success' => true, 'message' => 'Food donation posted successfully! It will be reviewed by an administrator before being made available to the community.', 'donation_id' => $conn->insert_id]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Failed to post donation: ' . $stmt->error]);
                    }
                    $stmt->close();
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
                exit;
        }
    }
}

// Include HTML files AFTER AJAX handling
include 'header.php'; 
include 'topbar.php'; 
include 'sidebar.php';
?>

<main id="main" class="main">
<div class="container py-4">
    <!-- Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-basket"></i> Post Excess Food</h2>
                    <p class="text-muted mb-0">Share your excess food with the community and help reduce food waste</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Section -->
    <div class="row">
        <div class="col-lg-12 mx-auto">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Food Donation</h5>
                </div>
                <div class="card-body">
                    <form id="donationForm" enctype="multipart/form-data">
                        <!-- Hidden field for ingredient ID (when donating from ingredient) -->
                        <input type="hidden" id="ingredient_id" name="ingredient_id" value="">
                        <!-- Basic Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Basic Information</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="title" class="form-label">Food Item Title *</label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       placeholder="e.g., Fresh Vegetables from Garden">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="food_type" class="form-label">Food Type *</label>
                                <select class="form-select" id="food_type" name="food_type" required>
                                    <option value="">Select food type</option>
                                    <option value="cooked">Cooked Food</option>
                                    <option value="raw">Raw Ingredients</option>
                                    <option value="packaged">Packaged Food</option>
                                    <option value="beverages">Beverages</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="quantity" class="form-label">Quantity *</label>
                                <input type="text" class="form-control" id="quantity" name="quantity" required 
                                       placeholder="e.g., 2 kg, 5 pieces, 1 large container">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="expiration_date" class="form-label">Expiration Date</label>
                                <input type="date" class="form-control" id="expiration_date" name="expiration_date">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="description" class="form-label">Description *</label>
                                <textarea class="form-control" id="description" name="description" rows="3" required 
                                          placeholder="Describe the food item, its condition, and any other relevant details..."></textarea>
                            </div>
                        </div>

                        <!-- Location & Pickup Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Location & Pickup</h6>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="location_address" class="form-label">Pickup Address *</label>
                                <textarea class="form-control" id="location_address" name="location_address" rows="2" required 
                                          placeholder="Enter the full address where the food can be picked up"></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="pickup_time_start" class="form-label">Available From</label>
                                <input type="time" class="form-control" id="pickup_time_start" name="pickup_time_start">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="pickup_time_end" class="form-label">Available Until</label>
                                <input type="time" class="form-control" id="pickup_time_end" name="pickup_time_end">
                            </div>
                            <div class="col-12 mb-3">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="getCurrentLocation()">
                                    <i class="bi bi-geo-alt"></i> Use Current Location
                                </button>
                                <small class="text-muted ms-2">Click to automatically fill location coordinates</small>
                                <input type="hidden" id="location_lat" name="location_lat">
                                <input type="hidden" id="location_lng" name="location_lng">
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Contact Information</h6>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_method" class="form-label">Preferred Contact Method *</label>
                                <select class="form-select" id="contact_method" name="contact_method" required>
                                    <option value="">Select contact method</option>
                                    <option value="phone">Phone</option>
                                    <option value="email">Email</option>
                                    <option value="both">Both</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact_info" class="form-label">Contact Information *</label>
                                <input type="text" class="form-control" id="contact_info" name="contact_info" required 
                                       placeholder="Phone number or email address">
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Additional Information</h6>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="dietary_info" class="form-label">Dietary Information</label>
                                <textarea class="form-control" id="dietary_info" name="dietary_info" rows="2" 
                                          placeholder="e.g., Vegetarian, Vegan, Halal, Kosher, etc."></textarea>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="allergens" class="form-label">Allergens</label>
                                <input type="text" class="form-control" id="allergens" name="allergens" 
                                       placeholder="e.g., Contains nuts, dairy, gluten, etc.">
                            </div>
                            <div class="col-12 mb-3">
                                <label for="storage_instructions" class="form-label">Storage Instructions</label>
                                <textarea class="form-control" id="storage_instructions" name="storage_instructions" rows="2" 
                                          placeholder="How should the food be stored after pickup?"></textarea>
                            </div>
                        </div>

                        <!-- Images -->
                        <div class="row mb-4">
                            <div class="col-12">
                                <h6 class="text-primary border-bottom pb-2 mb-3">Images (Optional)</h6>
                            </div>
                            <div class="col-12 mb-3">
                                <label for="images" class="form-label">Upload Images</label>
                                <input type="file" class="form-control" id="images" name="images[]" multiple 
                                       accept="image/*">
                                <div class="form-text">Upload up to 5 images of the food items (JPG, PNG, GIF, WebP)</div>
                            </div>
                            <div class="col-12" id="image-preview-container"></div>
                        </div>

                        <!-- Submit Button -->
                        <div class="row">
                            <div class="col-12">
                                <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                    <button type="button" class="btn btn-secondary me-md-2" onclick="resetForm()">Reset</button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-plus-circle"></i> Post Donation
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</main>

<style>
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.card-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.125);
}

.form-label {
    font-weight: 600;
    color: #495057;
}

.border-bottom {
    border-bottom: 2px solid #007bff !important;
}

.image-preview {
    position: relative;
    display: inline-block;
    margin: 5px;
}

.image-preview img {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid #e9ecef;
}

.image-preview .remove-image {
    position: absolute;
    top: -5px;
    right: -5px;
    background: #dc3545;
    color: white;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: 12px;
    cursor: pointer;
}

.required {
    color: #dc3545;
}

.form-control:focus, .form-select:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
</style>

<script>
// Global variables
let uploadedImages = [];

// Check if coming from ingredient donation and auto-fill form
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('from_ingredient') === '1') {
        const ingredientDataStr = sessionStorage.getItem('donateIngredientData');
        if (ingredientDataStr) {
            try {
                const ingredient = JSON.parse(ingredientDataStr);
                
                // Auto-fill form fields
                document.getElementById('ingredient_id').value = ingredient.ingredient_id || '';
                document.getElementById('title').value = ingredient.ingredient_name || '';
                
                // Set food type to 'raw' as default for ingredients
                document.getElementById('food_type').value = 'raw';
                
                // Build description from ingredient details
                let description = ingredient.remarks || ingredient.ingredient_name;
                if (ingredient.category) {
                    description += '\nCategory: ' + ingredient.category;
                }
                if (ingredient.calories || ingredient.protein || ingredient.fat || ingredient.carbohydrates) {
                    description += '\n\nNutrition per ' + (ingredient.unit || '100g') + ':';
                    if (ingredient.calories) description += '\n- Calories: ' + ingredient.calories;
                    if (ingredient.protein) description += '\n- Protein: ' + ingredient.protein + 'g';
                    if (ingredient.fat) description += '\n- Fat: ' + ingredient.fat + 'g';
                    if (ingredient.carbohydrates) description += '\n- Carbs: ' + ingredient.carbohydrates + 'g';
                    if (ingredient.fiber) description += '\n- Fiber: ' + ingredient.fiber + 'g';
                }
                document.getElementById('description').value = description;
                
                // Set quantity with unit
                let quantity = '1';
                if (ingredient.unit) {
                    quantity = '1 ' + ingredient.unit;
                }
                document.getElementById('quantity').value = quantity;
                
                // Set expiration date if available
                if (ingredient.expiration_date) {
                    document.getElementById('expiration_date').value = ingredient.expiration_date;
                }
                
                // Set dietary info from vitamins if available
                if (ingredient.vitamins) {
                    document.getElementById('dietary_info').value = 'Rich in vitamins: ' + ingredient.vitamins;
                }
                
                // Show success message
                showNotification('Ingredient details auto-filled! Please complete the remaining required fields.', 'success');
            } catch (e) {
                console.error('Error parsing ingredient data:', e);
            }
        }
    }
});

// Form submission
document.getElementById('donationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'create_donation');
    
    // Show loading state
    const submitBtn = this.querySelector('button[type="submit"]');
    const originalText = submitBtn.innerHTML;
    submitBtn.innerHTML = '<i class="spinner-border spinner-border-sm me-2"></i>Posting...';
    submitBtn.disabled = true;
    
    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
            
            // If coming from ingredient, mark it as used
            const ingredientId = document.getElementById('ingredient_id').value;
            if (ingredientId) {
                // Mark ingredient as used
                const markUsedData = new FormData();
                markUsedData.append('use_ingredient', '1');
                markUsedData.append('ingredient_id', ingredientId);
                fetch('input_ingredients.php', {
                    method: 'POST',
                    body: markUsedData
                }).then(() => {
                    // Clear sessionStorage
                    sessionStorage.removeItem('donateIngredientData');
                    // Redirect to donation history after short delay
                    setTimeout(() => {
                        window.location.href = 'donation_history.php';
                    }, 1500);
                });
            } else {
                resetForm();
                // Optionally redirect to donation history
                // window.location.href = 'donation_history.php';
            }
        } else {
            showNotification(data.message, 'error');
        }
    })
    .catch(error => {
        showNotification('An error occurred while posting the donation.', 'error');
    })
    .finally(() => {
        submitBtn.innerHTML = originalText;
        submitBtn.disabled = false;
    });
});

// Image preview functionality
document.getElementById('images').addEventListener('change', function(e) {
    const files = Array.from(e.target.files);
    const previewContainer = document.getElementById('image-preview-container');
    
    // Clear existing previews
    previewContainer.innerHTML = '';
    uploadedImages = [];
    
    files.forEach((file, index) => {
        if (file.type.startsWith('image/')) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = 'image-preview';
                preview.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    <button type="button" class="remove-image" onclick="removeImage(${index})">&times;</button>
                `;
                previewContainer.appendChild(preview);
                uploadedImages.push(file);
            };
            reader.readAsDataURL(file);
        }
    });
});

// Remove image function
function removeImage(index) {
    uploadedImages.splice(index, 1);
    updateImagePreviews();
}

// Update image previews
function updateImagePreviews() {
    const previewContainer = document.getElementById('image-preview-container');
    previewContainer.innerHTML = '';
    
    uploadedImages.forEach((file, index) => {
        const reader = new FileReader();
        reader.onload = function(e) {
            const preview = document.createElement('div');
            preview.className = 'image-preview';
            preview.innerHTML = `
                <img src="${e.target.result}" alt="Preview">
                <button type="button" class="remove-image" onclick="removeImage(${index})">&times;</button>
            `;
            previewContainer.appendChild(preview);
        };
        reader.readAsDataURL(file);
    });
}

// Get current location
function getCurrentLocation() {
    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            function(position) {
                document.getElementById('location_lat').value = position.coords.latitude;
                document.getElementById('location_lng').value = position.coords.longitude;
                showNotification('Location coordinates updated!', 'success');
            },
            function(error) {
                showNotification('Unable to get current location: ' + error.message, 'error');
            }
        );
    } else {
        showNotification('Geolocation is not supported by this browser.', 'error');
    }
}

// Reset form
function resetForm() {
    document.getElementById('donationForm').reset();
    document.getElementById('image-preview-container').innerHTML = '';
    uploadedImages = [];
    showNotification('Form has been reset.', 'info');
}

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

// Form validation
document.getElementById('donationForm').addEventListener('input', function(e) {
    const field = e.target;
    const value = field.value.trim();
    
    // Remove existing validation classes
    field.classList.remove('is-valid', 'is-invalid');
    
    // Validate required fields
    if (field.hasAttribute('required') && !value) {
        field.classList.add('is-invalid');
    } else if (field.hasAttribute('required') && value) {
        field.classList.add('is-valid');
    }
    
    // Specific validations
    if (field.id === 'contact_info') {
        const contactMethod = document.getElementById('contact_method').value;
        if (contactMethod === 'phone' && value && !/^[\+]?[1-9][\d]{0,15}$/.test(value.replace(/[\s\-\(\)]/g, ''))) {
            field.classList.add('is-invalid');
        } else if (contactMethod === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            field.classList.add('is-invalid');
        } else if (value) {
            field.classList.add('is-valid');
        }
    }
});

// Set minimum date for expiration date to today
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('expiration_date').setAttribute('min', today);
});
</script>

<?php include 'footer.php'; ?>

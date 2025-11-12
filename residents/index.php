<?php
include 'header.php';
include 'topbar.php';
include 'sidebar.php';

// Fetch challenges directly from database - Same pattern as challenges_events.php
$user_id = $_SESSION['user_id'];
$challenges = [];

try {
    // Get all active challenges with user participation info (matching challenges_events.php structure)
    $stmt = $conn->prepare("
        SELECT c.*,
               cp.participant_id,
               cp.progress,
               cp.completed as user_completed,
               cp.joined_at,
               COUNT(DISTINCT cp_all.participant_id) as total_participants
        FROM challenges c
        LEFT JOIN challenge_participants cp ON c.challenge_id = cp.challenge_id AND cp.user_id = ?
        LEFT JOIN challenge_participants cp_all ON c.challenge_id = cp_all.challenge_id
        WHERE c.status = 'active'
        AND c.end_date >= CURDATE()
        GROUP BY c.challenge_id
        ORDER BY c.start_date DESC, c.created_at DESC
        LIMIT 6
    ");
    
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $challenges[] = $row;
        }
    }
    $stmt->close();
} catch (Exception $e) {
    // If challenges table doesn't exist or query fails, keep empty array
}
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1>Residents Dashboard</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item active">Dashboard</li>
            </ol>
        </nav>
    </div><!-- End Page Title -->
    <section class="section dashboard">
        <div class="row">
            <!-- Animated Chart Card -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm animate__animated animate__fadeInLeft">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Meal Plan Trends</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadMealPlanData()" title="Refresh data">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <small class="text-muted">Last 7 days</small>
                            <div class="spinner-border spinner-border-sm" id="chartSpinner" role="status" style="display: none;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <canvas id="mealPlanChart" height="180"></canvas>
                        <div class="mt-3" id="chartStats">
                            <!-- Dynamic stats will be loaded here -->
                        </div>
                    </div>
                </div>
            </div>
            <!-- Calendar Card -->
            <div class="col-lg-6 mb-4">
                <div class="card shadow-sm animate__animated animate__fadeInRight">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Meal & Event Calendar</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadCalendarEvents()" title="Refresh calendar">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Active Challenges Row -->
        <div class="row">
            <div class="col-lg-12 mb-4">
                <div class="card shadow-sm animate__animated animate__fadeInUp">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0"><i class="bi bi-trophy"></i> Active Challenges</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadChallenges()" title="Refresh challenges">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <small class="text-muted">Join challenges to earn rewards and contribute to the community</small>
                            <div class="spinner-border spinner-border-sm" id="challengesSpinner" role="status" style="display: none;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <div id="challenges-container" class="row">
                            <?php if (empty($challenges)): ?>
                                <div class="col-12 text-center py-5">
                                    <i class="bi bi-trophy" style="font-size: 3rem; color: #ddd;"></i>
                                    <p class="text-muted mt-3">No active challenges at the moment</p>
                                    <small class="text-muted">Check back soon for new challenges!</small>
                                </div>
                            <?php else: ?>
                                <?php foreach ($challenges as $challenge): ?>
                                    <?php
                                    $isParticipating = !empty($challenge['participant_id']);
                                    $isCompleted = $challenge['user_completed'] ?? false;
                                    $progress = $challenge['progress'] ?? 0;
                                    $targetValue = $challenge['target_value'] ?? 100;
                                    $progressPercentage = $targetValue > 0 ? round(($progress / $targetValue) * 100) : 0;
                                    $statusBadge = $challenge['status'] === 'active' ? 'success' : 'secondary';
                                    
                                    // Calculate time remaining
                                    $endDate = new DateTime($challenge['end_date']);
                                    $now = new DateTime();
                                    $interval = $now->diff($endDate);
                                    $daysRemaining = $interval->days;
                                    $isExpired = $endDate < $now;
                                    
                                    if ($isExpired) {
                                        $timeRemainingText = '<span class="badge bg-danger">Ended</span>';
                                    } elseif ($daysRemaining === 0) {
                                        $timeRemainingText = '<span class="badge bg-warning">Ends Today!</span>';
                                    } elseif ($daysRemaining === 1) {
                                        $timeRemainingText = '<span class="badge bg-warning">1 day left</span>';
                                    } elseif ($daysRemaining <= 3) {
                                        $timeRemainingText = '<span class="badge bg-warning">' . $daysRemaining . ' days left</span>';
                                    } else {
                                        $timeRemainingText = '<span class="badge bg-info">' . $daysRemaining . ' days left</span>';
                                    }
                                    
                                    // Determine progress color
                                    $progressBarColor = 'success';
                                    if ($progressPercentage < 25) {
                                        $progressBarColor = 'danger';
                                    } elseif ($progressPercentage < 50) {
                                        $progressBarColor = 'warning';
                                    } elseif ($progressPercentage < 75) {
                                        $progressBarColor = 'info';
                                    }
                                    
                                    $startFormatted = date('M j', strtotime($challenge['start_date']));
                                    $endFormatted = date('M j, Y', strtotime($challenge['end_date']));
                                    ?>
                                    <div class="col-lg-4 col-md-6 mb-3">
                                        <div class="challenge-card card h-100 animate__animated animate__fadeIn" data-challenge-id="<?= $challenge['challenge_id'] ?>">
                                            <?php if (!empty($challenge['banner_image'])): ?>
                                                <img src="../<?= htmlspecialchars($challenge['banner_image']) ?>" 
                                                     class="card-img-top" alt="Challenge Banner" 
                                                     style="height: 150px; object-fit: cover;">
                                            <?php endif; ?>
                                            
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title mb-0">🏆 <?= htmlspecialchars($challenge['title']) ?></h6>
                                                    <span class="badge bg-primary"><?= $challenge['points'] ?> pts</span>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <span class="badge bg-info me-1"><?= ucfirst($challenge['challenge_type']) ?></span>
                                                    <span class="badge bg-secondary"><?= ucfirst(str_replace('_', ' ', $challenge['category'])) ?></span>
                                                </div>
                                                
                                                <p class="card-text text-muted small mb-2">
                                                    <?= htmlspecialchars(substr($challenge['description'], 0, 80)) ?><?= strlen($challenge['description']) > 80 ? '...' : '' ?>
                                                </p>
                                                
                                                <div class="mb-2">
                                                    <small class="text-muted d-block">
                                                        <i class="bi bi-calendar"></i> <?= $startFormatted ?> - <?= $endFormatted ?>
                                                    </small>
                                                    <small class="text-muted d-block">
                                                        <i class="bi bi-people"></i> <?= $challenge['total_participants'] ?> participants
                                                    </small>
                                                    <small class="text-muted d-block">
                                                        <i class="bi bi-target"></i> Goal: <?= $targetValue ?> <?= $challenge['category'] === 'donation' ? 'donations' : 'actions' ?>
                                                    </small>
                                                </div>
                                                
                                                <div class="mb-2">
                                                    <?= $timeRemainingText ?>
                                                </div>
                                                
                                                <?php if ($isCompleted): ?>
                                                    <button class="btn btn-success btn-sm w-100" disabled>
                                                        <i class="bi bi-check-circle"></i> Completed!
                                                    </button>
                                                <?php elseif ($isParticipating): ?>
                                                    <div class="mb-2 challenge-progress-container" data-progress="<?= $progressPercentage ?>">
                                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                                            <small class="text-muted">Progress</small>
                                                            <small class="fw-bold text-<?= $progressBarColor ?>"><?= $progress ?>/<?= $targetValue ?></small>
                                                        </div>
                                                        <div class="progress" style="height: 8px;">
                                                            <div class="progress-bar bg-<?= $progressBarColor ?> progress-bar-striped progress-bar-animated" 
                                                                 role="progressbar" 
                                                                 style="width: <?= $progressPercentage ?>%">
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <span class="badge bg-primary w-100"><i class="bi bi-star-fill"></i> Participating</span>
                                                <?php else: ?>
                                                    <button class="btn btn-outline-primary btn-sm w-100" 
                                                            onclick="joinChallenge(<?= $challenge['challenge_id'] ?>)">
                                                        <i class="bi bi-plus-circle"></i> Join Challenge
                                                    </button>
                                                <?php endif; ?>
                                                
                                                <?php if (!empty($challenge['prize_description'])): ?>
                                                    <div class="alert alert-warning py-1 mt-2 mb-0">
                                                        <small><i class="bi bi-gift"></i> <?= htmlspecialchars($challenge['prize_description']) ?></small>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Community Updates Card -->
            <div class="col-lg-12">
                <div class="card shadow-sm animate__animated animate__fadeInUp">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Community Updates</h5>
                            <button class="btn btn-sm btn-outline-primary" onclick="loadCommunityUpdates()" title="Refresh updates">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <small class="text-muted">Real-time community activity</small>
                            <div class="spinner-border spinner-border-sm" id="updatesSpinner" role="status" style="display: none;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                        <ul class="list-group list-group-flush" id="community-updates">
                            <!-- Dynamic updates will be loaded here -->
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main><!-- End #main -->

<?php include 'footer.php'; ?>

<!-- Chart.js & Calendar Scripts -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css"/>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js"></script>
<script>
// Global variables for charts
let mealPlanChart = null;
let calendar = null;

// Function to load meal plan data
async function loadMealPlanData() {
    const spinner = document.getElementById('chartSpinner');
    const statsContainer = document.getElementById('chartStats');
    
    try {
        spinner.style.display = 'block';
        const response = await fetch('api/meal_plan_data.php');
        const data = await response.json();
        
        if (data.success) {
            updateMealPlanChart(data);
            updateChartStats(data.stats);
        } else {
            console.error('Error loading meal plan data:', data.error);
            showError('Failed to load meal plan data');
        }
    } catch (error) {
        console.error('Error fetching meal plan data:', error);
        showError('Network error loading meal plan data');
    } finally {
        spinner.style.display = 'none';
    }
}

// Function to update meal plan chart
function updateMealPlanChart(data) {
    const ctx = document.getElementById('mealPlanChart').getContext('2d');
    
    if (mealPlanChart) {
        mealPlanChart.destroy();
    }
    
    mealPlanChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [{
                label: 'Meals Planned',
                data: data.chart_data,
                borderColor: 'rgba(75, 192, 192, 1)',
                backgroundColor: 'rgba(75, 192, 192, 0.2)',
                tension: 0.4,
                fill: true,
                pointRadius: 5,
                pointHoverRadius: 8,
                pointBackgroundColor: 'rgba(75, 192, 192, 1)'
            }]
        },
        options: {
            responsive: true,
            animation: {
                duration: 1500,
                easing: 'easeInOutQuart'
            },
            plugins: {
                legend: { display: false },
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

// Function to load community updates
async function loadCommunityUpdates() {
    const spinner = document.getElementById('updatesSpinner');
    
    try {
        spinner.style.display = 'block';
        const response = await fetch('api/community_updates.php');
        const data = await response.json();
        
        if (data.success) {
            updateCommunityUpdates(data.updates);
        } else {
            console.error('Error loading community updates:', data.error);
            showError('Failed to load community updates');
        }
    } catch (error) {
        console.error('Error fetching community updates:', error);
        showError('Network error loading community updates');
    } finally {
        spinner.style.display = 'none';
    }
}

// Function to update chart statistics
function updateChartStats(stats) {
    const container = document.getElementById('chartStats');
    container.innerHTML = `
        <div class="row text-center">
            <div class="col-4">
                <div class="border-end">
                    <h6 class="text-muted mb-1">Total Plans</h6>
                    <h5 class="mb-0 text-primary">${stats.total_plans}</h5>
                </div>
            </div>
            <div class="col-4">
                <div class="border-end">
                    <h6 class="text-muted mb-1">This Month</h6>
                    <h5 class="mb-0 text-success">${stats.plans_this_month}</h5>
                </div>
            </div>
            <div class="col-4">
                <h6 class="text-muted mb-1">Avg Calories</h6>
                <h5 class="mb-0 text-info">${stats.avg_calories}</h5>
            </div>
        </div>
    `;
}

// Function to update community updates
function updateCommunityUpdates(updates) {
    const container = document.getElementById('community-updates');
    container.innerHTML = '';
    
    if (updates.length === 0) {
        container.innerHTML = '<li class="list-group-item text-muted text-center">No recent updates</li>';
        return;
    }
    
    updates.forEach(update => {
        const listItem = document.createElement('li');
        listItem.className = 'list-group-item';
        
        let badgeHtml = '';
        if (update.badge) {
            badgeHtml = ` <span class="badge ${update.badge_class}">${update.badge}</span>`;
        }
        
        listItem.innerHTML = `${update.icon} ${update.message}${badgeHtml}`;
        container.appendChild(listItem);
    });
}

// Function to show error messages
function showError(message) {
    // Create a simple toast notification
    const toast = document.createElement('div');
    toast.className = 'alert alert-warning alert-dismissible fade show position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5000);
}

// Function to load challenges with loading skeleton
async function loadChallenges() {
    const spinner = document.getElementById('challengesSpinner');
    const container = document.getElementById('challenges-container');
    
    try {
        spinner.style.display = 'block';
        
        // Show loading skeletons
        container.innerHTML = `
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="challenge-skeleton"></div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="challenge-skeleton"></div>
            </div>
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="challenge-skeleton"></div>
            </div>
        `;
        
        const response = await fetch('api/challenges_data.php');
        const data = await response.json();
        
        if (data.success) {
            displayChallenges(data.challenges);
        } else {
            console.error('Error loading challenges:', data.error);
            container.innerHTML = `
                <div class="col-12 text-center py-4">
                    <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                    <p class="text-muted mt-2">Failed to load challenges</p>
                    <button class="btn btn-sm btn-outline-primary" onclick="loadChallenges()">
                        <i class="bi bi-arrow-clockwise"></i> Retry
                    </button>
                </div>
            `;
        }
    } catch (error) {
        console.error('Error fetching challenges:', error);
        container.innerHTML = `
            <div class="col-12 text-center py-4">
                <i class="bi bi-wifi-off text-danger" style="font-size: 2rem;"></i>
                <p class="text-muted mt-2">Network error loading challenges</p>
                <button class="btn btn-sm btn-outline-primary" onclick="loadChallenges()">
                    <i class="bi bi-arrow-clockwise"></i> Retry
                </button>
            </div>
        `;
    } finally {
        spinner.style.display = 'none';
    }
}

// Function to display challenges with enhanced dynamic features
function displayChallenges(challenges) {
    const container = document.getElementById('challenges-container');
    container.innerHTML = '';
    
    if (challenges.length === 0) {
        container.innerHTML = `
            <div class="col-12 text-center py-5">
                <i class="bi bi-trophy" style="font-size: 3rem; color: #ddd;"></i>
                <p class="text-muted mt-3">No active challenges at the moment</p>
                <small class="text-muted">Check back soon for new challenges!</small>
            </div>
        `;
        return;
    }
    
    challenges.forEach(challenge => {
        const col = document.createElement('div');
        col.className = 'col-lg-4 col-md-6 mb-3';
        
        const progressPercentage = challenge.user_progress || 0;
        const isParticipating = challenge.is_participating || false;
        const statusBadge = challenge.status === 'active' ? 'success' : 'secondary';
        
        // Calculate time remaining
        const endDate = new Date(challenge.end_date);
        const now = new Date();
        const timeRemaining = endDate - now;
        const daysRemaining = Math.ceil(timeRemaining / (1000 * 60 * 60 * 24));
        
        let timeRemainingText = '';
        let timeRemainingClass = '';
        
        if (daysRemaining < 0) {
            timeRemainingText = '<span class="badge bg-danger">Ended</span>';
        } else if (daysRemaining === 0) {
            timeRemainingText = '<span class="badge bg-warning">Ends Today!</span>';
        } else if (daysRemaining === 1) {
            timeRemainingText = '<span class="badge bg-warning">1 day left</span>';
        } else if (daysRemaining <= 3) {
            timeRemainingText = `<span class="badge bg-warning">${daysRemaining} days left</span>`;
        } else {
            timeRemainingText = `<span class="badge bg-info">${daysRemaining} days left</span>`;
        }
        
        // Determine progress color
        let progressBarColor = 'success';
        if (progressPercentage < 25) {
            progressBarColor = 'danger';
        } else if (progressPercentage < 50) {
            progressBarColor = 'warning';
        } else if (progressPercentage < 75) {
            progressBarColor = 'info';
        }
        
        col.innerHTML = `
            <div class="challenge-card card h-100 animate__animated animate__fadeIn" data-challenge-id="${challenge.challenge_id}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="card-title mb-0">🏆 ${challenge.challenge_name}</h6>
                        <span class="badge bg-${statusBadge}">${challenge.status}</span>
                    </div>
                    <p class="card-text text-muted small mb-2">${challenge.description || 'Complete this challenge to earn rewards!'}</p>
                    
                    <div class="mb-2">
                        <small class="text-muted d-block">
                            <i class="bi bi-calendar-event"></i> ${challenge.start_date}
                        </small>
                        <small class="text-muted d-block">
                            <i class="bi bi-calendar-check"></i> ${challenge.end_date}
                        </small>
                    </div>
                    
                    <div class="mb-2">
                        ${timeRemainingText}
                        ${challenge.goal_type ? `<span class="badge bg-secondary ms-1">${challenge.goal_type}: ${challenge.goal_target}</span>` : ''}
                    </div>
                    
                    ${isParticipating ? `
                    <div class="mb-2 challenge-progress-container" data-progress="${progressPercentage}">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <small class="text-muted">Your Progress</small>
                            <small class="fw-bold text-${progressBarColor}">${progressPercentage}%</small>
                        </div>
                        <div class="progress" style="height: 10px;">
                            <div class="progress-bar bg-${progressBarColor} progress-bar-striped progress-bar-animated" 
                                 role="progressbar" 
                                 style="width: ${progressPercentage}%" 
                                 aria-valuenow="${progressPercentage}" 
                                 aria-valuemin="0" 
                                 aria-valuemax="100"></div>
                        </div>
                        ${progressPercentage === 100 ? '<small class="text-success"><i class="bi bi-check-circle-fill"></i> Completed!</small>' : ''}
                    </div>
                    ` : ''}
                    
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <small class="text-muted">
                            <i class="bi bi-people-fill"></i> ${challenge.participants_count} ${challenge.participants_count === 1 ? 'participant' : 'participants'}
                        </small>
                        ${isParticipating ? 
                            '<span class="badge bg-primary"><i class="bi bi-star-fill"></i> Participating</span>' : 
                            `<button class="btn btn-sm btn-outline-primary" onclick="joinChallenge(${challenge.challenge_id})" data-challenge-id="${challenge.challenge_id}">
                                <i class="bi bi-plus-circle"></i> Join
                            </button>`
                        }
                    </div>
                </div>
            </div>
        `;
        
        container.appendChild(col);
    });
    
    // Animate progress bars after render
    setTimeout(() => {
        document.querySelectorAll('.challenge-progress-container').forEach(container => {
            const progress = container.dataset.progress;
            const progressBar = container.querySelector('.progress-bar');
            if (progressBar) {
                progressBar.style.width = '0%';
                setTimeout(() => {
                    progressBar.style.width = progress + '%';
                }, 100);
            }
        });
    }, 100);
}

// Function to join a challenge (using challenges_events.php endpoint)
async function joinChallenge(challengeId) {
    const formData = new FormData();
    formData.append('action', 'join_challenge');
    formData.append('challenge_id', challengeId);
    
    try {
        const response = await fetch('challenges_events.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            showSuccess(data.message || 'Successfully joined the challenge!');
            // Reload the page to show updated data
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showError(data.message || 'Failed to join challenge');
        }
    } catch (error) {
        console.error('Error joining challenge:', error);
        showError('Network error while joining challenge');
    }
}

// Function to leave a challenge (using challenges_events.php endpoint)
function leaveChallenge(challengeId) {
    if (!confirm('Are you sure you want to leave this challenge? Your progress will be lost.')) return;
    
    const formData = new FormData();
    formData.append('action', 'leave_challenge');
    formData.append('challenge_id', challengeId);
    
    fetch('challenges_events.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showSuccess(data.message || 'Left the challenge successfully!');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            showError(data.message || 'Failed to leave challenge');
        }
    })
    .catch(error => {
        console.error('Error leaving challenge:', error);
        showError('Network error while leaving challenge');
    });
}

// Function to show success messages
function showSuccess(message) {
    const toast = document.createElement('div');
    toast.className = 'alert alert-success alert-dismissible fade show position-fixed';
    toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
    toast.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        if (toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 5000);
}

// Function to load calendar events
async function loadCalendarEvents() {
    try {
        const response = await fetch('api/calendar_events.php');
        const data = await response.json();
        
        if (data.success) {
            updateCalendar(data.events);
        } else {
            console.error('Error loading calendar events:', data.error);
        }
    } catch (error) {
        console.error('Error fetching calendar events:', error);
    }
}

// Function to update calendar
function updateCalendar(events) {
    const calendarEl = document.getElementById('calendar');
    
    if (calendar) {
        calendar.destroy();
    }
    
    calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        height: 350,
        events: events,
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,listWeek'
        },
        eventDisplay: 'block',
        eventTimeFormat: {
            hour: '2-digit',
            minute: '2-digit',
            meridiem: false
        },
        eventClick: function(info) {
            info.jsEvent.preventDefault();
            
            let eventDetails = `
                <div class="event-modal-content">
                    <h5 class="mb-3">${info.event.title}</h5>
            `;
            
            // Show formatted dates if available (for challenges), otherwise use default
            if (info.event.extendedProps.start_date_formatted) {
                eventDetails += `<p class="mb-2"><strong>📅 Start Date:</strong> ${info.event.extendedProps.start_date_formatted}</p>`;
                
                if (info.event.extendedProps.end_date_formatted) {
                    eventDetails += `<p class="mb-2"><strong>🏁 End Date:</strong> ${info.event.extendedProps.end_date_formatted}</p>`;
                    
                    // Calculate duration
                    const start = new Date(info.event.start);
                    const end = new Date(info.event.end);
                    const duration = Math.ceil((end - start) / (1000 * 60 * 60 * 24));
                    if (duration > 1) {
                        eventDetails += `<p class="mb-2"><strong>⏱️ Duration:</strong> ${duration} days</p>`;
                    }
                }
            } else {
                // Default date display
                eventDetails += `<p class="mb-2"><strong>📅 Date:</strong> ${info.event.start.toLocaleDateString()}</p>`;
                
                // Add end date if available
                if (info.event.end) {
                    const endDate = new Date(info.event.end);
                    endDate.setDate(endDate.getDate() - 1); // Adjust for FullCalendar's exclusive end
                    eventDetails += `<p class="mb-2"><strong>🏁 End Date:</strong> ${endDate.toLocaleDateString()}</p>`;
                }
            }
            
            // Add type
            if (info.event.extendedProps.type) {
                const typeIcons = {
                    'challenge': '🏆',
                    'announcement': '📢',
                    'meal_plan': '📅',
                    'donation': '🤝',
                    'recipe': '🥗',
                    'tip': '💡',
                    'expiration': '⚠️',
                    'community_event': '🎉',
                    'food_request': '📥'
                };
                const icon = typeIcons[info.event.extendedProps.type] || '📌';
                eventDetails += `<p class="mb-2"><strong>${icon} Type:</strong> ${info.event.extendedProps.type.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>`;
            }
            
            // Add challenge-specific information
            if (info.event.extendedProps.goal_type) {
                eventDetails += `<p class="mb-2"><strong>🎯 Goal Type:</strong> ${info.event.extendedProps.goal_type}</p>`;
            }
            
            if (info.event.extendedProps.goal_target) {
                const category = info.event.extendedProps.category || '';
                const targetLabel = category === 'donation' ? 'donations' : 'actions';
                eventDetails += `<p class="mb-2"><strong>🏁 Target:</strong> ${info.event.extendedProps.goal_target} ${targetLabel}</p>`;
            }
            
            if (info.event.extendedProps.points) {
                eventDetails += `<p class="mb-2"><strong>⭐ Reward:</strong> ${info.event.extendedProps.points} points</p>`;
            }
            
            if (info.event.extendedProps.category) {
                eventDetails += `<p class="mb-2"><strong>📂 Category:</strong> ${info.event.extendedProps.category.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</p>`;
            }
            
            // Add location for community events
            if (info.event.extendedProps.location) {
                eventDetails += `<p class="mb-2"><strong>📍 Location:</strong> ${info.event.extendedProps.location}</p>`;
            }
            
            // Add event type for community events
            if (info.event.extendedProps.event_type) {
                eventDetails += `<p class="mb-2"><strong>🎭 Event Type:</strong> ${info.event.extendedProps.event_type.replace(/\b\w/g, l => l.toUpperCase())}</p>`;
            }
            
            // Add description if available
            if (info.event.extendedProps.description) {
                eventDetails += `<p class="mb-2"><strong>📝 Description:</strong><br>${info.event.extendedProps.description}</p>`;
            }
            
            // Add priority for announcements
            if (info.event.extendedProps.priority) {
                const priorityColors = {
                    'critical': 'danger',
                    'high': 'warning',
                    'medium': 'info',
                    'low': 'secondary'
                };
                const badgeClass = priorityColors[info.event.extendedProps.priority] || 'secondary';
                eventDetails += `<p class="mb-2"><strong>⚠️ Priority:</strong> <span class="badge bg-${badgeClass}">${info.event.extendedProps.priority.toUpperCase()}</span></p>`;
            }
            
            eventDetails += `</div>`;
            
            // Show modal or alert
            showEventModal(eventDetails);
        },
        eventMouseEnter: function(info) {
            info.el.style.cursor = 'pointer';
            info.el.title = info.event.title;
        }
    });
    
    calendar.render();
}

// Function to show event modal
function showEventModal(content) {
    // Remove existing modal if any
    const existingModal = document.getElementById('eventModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Create modal
    const modal = document.createElement('div');
    modal.id = 'eventModal';
    modal.className = 'event-modal';
    modal.innerHTML = `
        <div class="event-modal-backdrop" onclick="closeEventModal()"></div>
        <div class="event-modal-dialog">
            <div class="event-modal-header">
                <h5 class="event-modal-title">Event Details</h5>
                <button type="button" class="event-modal-close" onclick="closeEventModal()">&times;</button>
            </div>
            <div class="event-modal-body">
                ${content}
            </div>
            <div class="event-modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEventModal()">Close</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    
    // Show modal with animation
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
}

// Function to close event modal
function closeEventModal() {
    const modal = document.getElementById('eventModal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEventModal();
    }
});

// Initialize everything when page loads
document.addEventListener('DOMContentLoaded', function() {
    loadMealPlanData();
    // Challenges are already loaded server-side, only load via AJAX on refresh
    loadCommunityUpdates();
    loadCalendarEvents();
    
    // Refresh data every 5 minutes
    setInterval(() => {
        loadMealPlanData();
        loadChallenges(); // Dynamic refresh for challenges
        loadCommunityUpdates();
        loadCalendarEvents();
    }, 300000); // 5 minutes
});
</script>

<style>
/* Event Modal Styles */
.event-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 10000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.event-modal.show {
    opacity: 1;
}

.event-modal-backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.event-modal-dialog {
    position: relative;
    background: white;
    border-radius: 10px;
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    max-width: 500px;
    width: 90%;
    max-height: 80vh;
    overflow: hidden;
    transform: scale(0.9);
    transition: transform 0.3s ease;
}

.event-modal.show .event-modal-dialog {
    transform: scale(1);
}

.event-modal-header {
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.event-modal-title {
    margin: 0;
    font-size: 1.25rem;
    font-weight: 600;
}

.event-modal-close {
    background: none;
    border: none;
    color: white;
    font-size: 2rem;
    line-height: 1;
    cursor: pointer;
    opacity: 0.8;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.event-modal-close:hover {
    opacity: 1;
    background: rgba(255,255,255,0.2);
}

.event-modal-body {
    padding: 1.5rem;
    max-height: 60vh;
    overflow-y: auto;
}

.event-modal-content p {
    line-height: 1.6;
}

.event-modal-footer {
    padding: 1rem 1.5rem;
    border-top: 1px solid #dee2e6;
    text-align: right;
}

/* Calendar Enhancement Styles */
.fc-event {
    cursor: pointer;
    transition: transform 0.2s, box-shadow 0.2s;
}

.fc-event:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}

.fc-daygrid-event {
    padding: 2px 4px;
    border-radius: 4px;
}

.fc-list-event:hover {
    background-color: #f8f9fa;
}

/* Challenge Card Styles */
.challenge-card {
    border: none;
    border-left: 4px solid #6f42c1;
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.challenge-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(135deg, rgba(111, 66, 193, 0.03) 0%, transparent 100%);
    opacity: 0;
    transition: opacity 0.3s ease;
    pointer-events: none;
}

.challenge-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 20px rgba(111, 66, 193, 0.25);
    border-left-width: 6px;
}

.challenge-card:hover::before {
    opacity: 1;
}

.challenge-card .card-title {
    color: #6f42c1;
    font-weight: 600;
    font-size: 1rem;
}

.challenge-card .progress {
    border-radius: 10px;
    background-color: #e9ecef;
    box-shadow: inset 0 1px 2px rgba(0,0,0,0.1);
}

.challenge-card .progress-bar {
    border-radius: 10px;
    transition: width 1s cubic-bezier(0.4, 0, 0.2, 1);
    background-image: linear-gradient(45deg, rgba(255,255,255,0.15) 25%, transparent 25%, transparent 50%, rgba(255,255,255,0.15) 50%, rgba(255,255,255,0.15) 75%, transparent 75%, transparent);
    background-size: 1rem 1rem;
}

.challenge-card .badge {
    font-size: 0.75rem;
    padding: 0.35rem 0.65rem;
    font-weight: 500;
}

.challenge-card .btn-outline-primary {
    transition: all 0.2s ease;
}

.challenge-card .btn-outline-primary:hover {
    transform: scale(1.05);
}

/* Animate fade in */
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.challenge-card.animate__fadeIn {
    animation: fadeIn 0.5s ease forwards;
}

/* Loading skeleton for challenges */
.challenge-skeleton {
    background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
    background-size: 200% 100%;
    animation: loading 1.5s infinite;
    border-radius: 8px;
    height: 200px;
}

@keyframes loading {
    0% {
        background-position: 200% 0;
    }
    100% {
        background-position: -200% 0;
    }
}
</style>

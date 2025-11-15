<?php
include '../config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is team officer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'team officer') {
    header('Location: ../index.php');
    exit;
}

$residents = [];
$total_residents = 0;
$search_query = '';
$filter_status = isset($_GET['status']) ? $_GET['status'] : '';
$team_officer_address = '';

try {
    // Get the logged-in team officer's address
    $stmt = $conn->prepare("SELECT address FROM user_accounts WHERE user_id = ?");
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $officer_data = $result->fetch_assoc();
    $team_officer_address = trim($officer_data['address'] ?? '');
    $stmt->close();

    // Build the query - only get residents with the same address as the team officer
    if (!empty($team_officer_address)) {
        // Use a more robust comparison with TRIM and LOWER for case-insensitive matching
        $query = "SELECT user_id, full_name, email, phone_number, address, role, status, created_at, profile_img 
                  FROM user_accounts 
                  WHERE role = 'resident' 
                  AND LOWER(TRIM(address)) = LOWER(TRIM(?))";
        $params = [$team_officer_address];
        $types = 's';

        // Add search filter
        if (isset($_GET['search']) && !empty($_GET['search'])) {
            $search_query = trim($_GET['search']);
            $query .= " AND (full_name LIKE ? OR email LIKE ? OR phone_number LIKE ?)";
            $search_param = '%' . $search_query . '%';
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            $types .= 'sss';
        }

        // Add status filter
        if (!empty($filter_status)) {
            $query .= " AND status = ?";
            $params[] = $filter_status;
            $types .= 's';
        }

        $query .= " ORDER BY created_at DESC";

        // Prepare and execute query
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $residents = $result->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        }

        // Get total residents count for the same address
        $count_stmt = $conn->prepare("SELECT COUNT(*) as total FROM user_accounts WHERE role = 'resident' AND LOWER(TRIM(address)) = LOWER(TRIM(?))");
        $count_stmt->bind_param('s', $team_officer_address);
        $count_stmt->execute();
        $count_result = $count_stmt->get_result();
        $count_data = $count_result->fetch_assoc();
        $total_residents = $count_data['total'];
        $count_stmt->close();
    }
} catch (Exception $e) {
    $error_message = "Error fetching residents: " . $e->getMessage();
}

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-people"></i> Residents Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="#">Community</a></li>
                <li class="breadcrumb-item active">Residents</li>
            </ol>
        </nav>
    </div>

    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> <?= $error_message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($team_officer_address)): ?>
        <div class="alert alert-warning alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Notice:</strong> Your profile doesn't have an address set. Please update your profile to see residents in your area.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="card-title mb-0">Total Residents: <strong><?= number_format($total_residents) ?></strong></h5>
                            <?php if (!empty($team_officer_address)): ?>
                                <span class="badge bg-info">
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($team_officer_address) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Search and Filter Section -->
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <form method="GET" class="d-flex gap-2">
                                    <input type="text" name="search" class="form-control" placeholder="Search by name, email, or phone..."
                                        value="<?= htmlspecialchars($search_query) ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i> Search
                                    </button>
                                    <?php if ($search_query || $filter_status): ?>
                                        <a href="residents.php" class="btn btn-secondary">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                                        </a>
                                    <?php endif; ?>
                                </form>
                            </div>
                            <div class="col-md-6">
                                <form method="GET" class="d-flex gap-2">
                                    <select name="status" class="form-select" onchange="this.form.submit()">
                                        <option value="">All Status</option>
                                        <option value="active" <?= $filter_status === 'active' ? 'selected' : '' ?>>Active</option>
                                        <option value="pending" <?= $filter_status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                    </select>
                                    <?php if (!empty($_GET['search'])): ?>
                                        <input type="hidden" name="search" value="<?= htmlspecialchars($search_query) ?>">
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>

                        <!-- Residents Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Email</th>
                                        <th>Phone</th>
                                        <th>Address</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Registered</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($residents)): ?>
                                        <?php foreach ($residents as $resident): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <img src="<?= !empty($resident['profile_img']) ? '../uploads/profile_picture/' . htmlspecialchars($resident['profile_img']) : '../uploads/profile_picture/no_image.png' ?>"
                                                            class="rounded-circle me-2" width="32" height="32" alt="Profile">
                                                        <span><?= htmlspecialchars($resident['full_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td><?= htmlspecialchars($resident['email']) ?></td>
                                                <td><?= htmlspecialchars($resident['phone_number'] ?? 'N/A') ?></td>
                                                <td>
                                                    <small><?= htmlspecialchars($resident['address'] ?? 'N/A') ?></small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-info"><?= ucfirst($resident['role']) ?></span>
                                                </td>
                                                <td>
                                                    <span class="badge bg-<?=
                                                                            $resident['status'] === 'active' ? 'success' : ($resident['status'] === 'pending' ? 'warning' : 'secondary')
                                                                            ?>">
                                                        <?= ucfirst($resident['status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <small><?= date('M d, Y', strtotime($resident['created_at'])) ?></small>
                                                    <br><small class="text-muted"><?= date('g:i A', strtotime($resident['created_at'])) ?></small>
                                                </td>
                                                <td>
                                                    <div class="btn-group" role="group">
                                                        <a href="resident-profile.php?id=<?= $resident['user_id'] ?>"
                                                            class="btn btn-sm btn-info" title="View Profile">
                                                            <i class="bi bi-eye"></i>
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-warning"
                                                            data-bs-toggle="modal" data-bs-target="#detailsModal"
                                                            onclick="showDetails(<?= htmlspecialchars(json_encode($resident)) ?>)"
                                                            title="View Details">
                                                            <i class="bi bi-info-circle"></i>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="8" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox"></i> No residents found
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<!-- Details Modal -->
<div class="modal fade" id="detailsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Resident Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="detailsContent">
                    <!-- Details will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showDetails(resident) {
        const detailsHtml = `
        <dl class="row">
            <dt class="col-sm-4">Full Name:</dt>
            <dd class="col-sm-8">${escapeHtml(resident.full_name)}</dd>
            
            <dt class="col-sm-4">Email:</dt>
            <dd class="col-sm-8">${escapeHtml(resident.email)}</dd>
            
            <dt class="col-sm-4">Phone:</dt>
            <dd class="col-sm-8">${escapeHtml(resident.phone_number || 'N/A')}</dd>
            
            <dt class="col-sm-4">Address:</dt>
            <dd class="col-sm-8">${escapeHtml(resident.address || 'N/A')}</dd>
            
            <dt class="col-sm-4">Role:</dt>
            <dd class="col-sm-8"><span class="badge bg-info">${resident.role}</span></dd>
            
            <dt class="col-sm-4">Status:</dt>
            <dd class="col-sm-8">
                <span class="badge bg-${
                    resident.status === 'active' ? 'success' : 
                    (resident.status === 'pending' ? 'warning' : 'secondary')
                }">
                    ${resident.status.charAt(0).toUpperCase() + resident.status.slice(1)}
                </span>
            </dd>
            
            <dt class="col-sm-4">Registered:</dt>
            <dd class="col-sm-8">${new Date(resident.created_at).toLocaleString()}</dd>
        </dl>
    `;
        document.getElementById('detailsContent').innerHTML = detailsHtml;
    }

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }
</script>

<?php include 'footer.php'; ?>
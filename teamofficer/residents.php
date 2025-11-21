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
$team_officer_address = '';

try {
    // Get the logged-in team officer's address
    $stmt = $conn->prepare("SELECT address FROM user_accounts WHERE user_id = ?");
    $stmt->bind_param('i', $_SESSION['user_id']);
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
                <div class="card border-0 shadow-sm" style="border-radius: 12px;">
                    <div class="card-body p-4">
                        <!-- Header Section -->
                        <div class="d-flex justify-content-between align-items-center mb-5">
                            <div>
                                <p class="mb-0 text-muted">
                                    <i class="bi bi-people"></i>
                                    <strong><?= number_format($total_residents) ?></strong>
                                    <?= $total_residents === 1 ? 'resident' : 'residents' ?> in your area
                                </p>
                            </div>
                            <?php if (!empty($team_officer_address)): ?>
                                <span class="badge bg-primary px-3 py-2" style="font-size: 0.9rem; border-radius: 20px;">
                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($team_officer_address) ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- Search Section -->
                        <div class="mb-4">
                            <form method="GET" class="d-flex gap-2">
                                <div class="input-group" style="border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                                    <span class="input-group-text bg-white border-0" style="padding-left: 16px;">
                                        <i class="bi bi-search" style="color: #0d6efd; font-size: 1.1rem;"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control border-0" style="padding: 12px 16px; font-size: 0.95rem;"
                                        placeholder="Search by name, email, or phone..." value="<?= htmlspecialchars($search_query) ?>">
                                </div>
                                <?php if ($search_query): ?>
                                    <a href="residents.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-counterclockwise"></i>
                                    </a>
                                <?php endif; ?>
                            </form>
                        </div>

                        <!-- Residents Table -->
                        <div class="table-responsive">
                            <table class="table align-middle text-center" style="border: none;">
                                <thead style="background-color: #f8f9fa; border-bottom: 2px solid #e9ecef;">
                                    <tr style="text-align: center;">
                                        <th style="font-weight: 600; color: #2c3e50; padding: 16px; text-align: center;">User</th>
                                        <th style="font-weight: 600; color: #2c3e50; padding: 16px; text-align: center;">Email</th>
                                        <th style="font-weight: 600; color: #2c3e50; padding: 16px; text-align: center;">Phone</th>
                                        <th style="font-weight: 600; color: #2c3e50; padding: 16px; text-align: center;">Address</th>
                                        <th style="font-weight: 600; color: #2c3e50; padding: 16px; text-align: center;">Role</th>
                                        <th style="font-weight: 600; color: #2c3e50; padding: 16px; text-align: center;">Registered</th>
                                    </tr>
                                </thead>
                                <tbody style="border-top: none;">
                                    <?php if (!empty($residents)): ?>
                                        <?php foreach ($residents as $resident): ?>
                                            <tr style="border-bottom: 1px solid #e9ecef; transition: background-color 0.2s; text-align: center;">
                                                <td style="padding: 14px 16px; text-align: center;">
                                                    <div class="d-flex align-items-center gap-2">
                                                        <img src="<?= !empty($resident['profile_img']) ? '../uploads/profile_picture/' . htmlspecialchars($resident['profile_img']) : '../uploads/profile_picture/no_image.png' ?>"
                                                            class="rounded-circle" width="40" height="40" alt="Profile" style="object-fit: cover; border: 2px solid #e9ecef;">
                                                        <span style="font-weight: 500; color: #2c3e50;"><?= htmlspecialchars($resident['full_name']) ?></span>
                                                    </div>
                                                </td>
                                                <td style="padding: 14px 16px; color: #555;"><?= htmlspecialchars($resident['email']) ?></td>
                                                <td style="padding: 14px 16px; color: #555;"><?= htmlspecialchars($resident['phone_number'] ?? 'N/A') ?></td>
                                                <td style="padding: 14px 16px; color: #555;">
                                                    <small><?= htmlspecialchars($resident['address'] ?? 'N/A') ?></small>
                                                </td>
                                                <td style="padding: 14px 16px;">
                                                    <span class="badge bg-primary" style="border-radius: 20px; padding: 6px 12px; font-size: 0.85rem;"><?= ucfirst($resident['role']) ?></span>
                                                </td>
                                                <td style="padding: 14px 16px; color: #555;">
                                                    <small><?= date('M d, Y', strtotime($resident['created_at'])) ?></small>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted py-5">
                                                <i class="bi bi-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                                                No residents found
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
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
            <div class="modal-header border-0" style="background-color: #f8f9fa; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title" style="font-weight: 600; color: #2c3e50;">Resident Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div id="detailsContent">
                    <!-- Details will be populated by JavaScript -->
                </div>
            </div>
            <div class="modal-footer border-0 d-none">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius: 6px;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    function showDetails(resident) {
        const detailsHtml = `
        <dl class="row g-4">
            <dt class="col-sm-5" style="font-weight: 600; color: #2c3e50;">Full Name:</dt>
            <dd class="col-sm-7" style="color: #555;">${escapeHtml(resident.full_name)}</dd>
            
            <dt class="col-sm-5" style="font-weight: 600; color: #2c3e50;">Email:</dt>
            <dd class="col-sm-7" style="color: #555;">${escapeHtml(resident.email)}</dd>
            
            <dt class="col-sm-5" style="font-weight: 600; color: #2c3e50;">Phone:</dt>
            <dd class="col-sm-7" style="color: #555;">${escapeHtml(resident.phone_number || 'N/A')}</dd>
            
            <dt class="col-sm-5" style="font-weight: 600; color: #2c3e50;">Address:</dt>
            <dd class="col-sm-7" style="color: #555;">${escapeHtml(resident.address || 'N/A')}</dd>
            
            <dt class="col-sm-5" style="font-weight: 600; color: #2c3e50;">Role:</dt>
            <dd class="col-sm-7"><span class="badge bg-primary" style="border-radius: 20px; padding: 6px 12px;">${resident.role}</span></dd>
            
            <dt class="col-sm-5" style="font-weight: 600; color: #2c3e50;">Registered:</dt>
            <dd class="col-sm-7" style="color: #555;">${new Date(resident.created_at).toLocaleString()}</dd>
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
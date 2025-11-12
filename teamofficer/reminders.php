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

// Get reminders (announcements with type 'reminder')
$stmt = $conn->prepare("
    SELECT a.*, ua.full_name as created_by_name
    FROM announcements a 
    JOIN user_accounts ua ON a.created_by = ua.user_id
    WHERE a.type = 'reminder'
    ORDER BY a.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$reminders = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include 'header.php';
include 'topbar.php';
include 'sidebar.php';
?>

<main id="main" class="main">
    <div class="pagetitle">
        <h1><i class="bi bi-bell"></i> Reminders Management</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item">Content Management</li>
                <li class="breadcrumb-item active">Reminders</li>
            </ol>
        </nav>
    </div>

    <section class="section">
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bell"></i> Community Reminders
                        </h5>
                        <a href="announcements.php?type=reminder" class="btn btn-primary btn-sm">
                            <i class="bi bi-plus-circle"></i> Create New Reminder
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($reminders)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-bell text-muted" style="font-size: 3rem;"></i>
                                <h4 class="mt-3">No Reminders Found</h4>
                                <p class="text-muted">No community reminders have been created yet.</p>
                                <a href="announcements.php?type=reminder" class="btn btn-primary">
                                    <i class="bi bi-plus-circle"></i> Create First Reminder
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($reminders as $reminder): ?>
                                    <div class="col-lg-6 mb-4">
                                        <div class="card h-100">
                                            <div class="card-header d-flex justify-content-between align-items-center">
                                                <h6 class="mb-0"><?php echo htmlspecialchars($reminder['title']); ?></h6>
                                                <span class="badge bg-primary">
                                                    <i class="bi bi-bell"></i> Reminder
                                                </span>
                                            </div>
                                            <div class="card-body">
                                                <p class="card-text"><?php echo htmlspecialchars(substr($reminder['content'], 0, 200)); ?><?php echo strlen($reminder['content']) > 200 ? '...' : ''; ?></p>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        By: <?php echo htmlspecialchars($reminder['created_by_name']); ?><br>
                                                        <?php echo date('M d, Y', strtotime($reminder['created_at'])); ?>
                                                    </small>
                                                    <div class="btn-group" role="group">
                                                        <a href="announcements.php?edit=<?php echo $reminder['id']; ?>" class="btn btn-primary btn-sm">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </a>
                                                        <a href="announcements.php?view=<?php echo $reminder['id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="bi bi-eye"></i> View
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php include 'footer.php'; ?>

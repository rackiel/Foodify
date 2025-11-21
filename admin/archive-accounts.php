<?php
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
include '../config/db.php';
// Handle AJAX unarchive request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['unarchive_user_ajax'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("UPDATE user_accounts SET status='active', is_approved=1, is_verified=1 WHERE user_id=?");
    $stmt->bind_param('i', $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    exit();
}
// Handle AJAX delete request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user_ajax'])) {
    $user_id = $_POST['user_id'];
    $stmt = $conn->prepare("DELETE FROM user_accounts WHERE user_id=?");
    $stmt->bind_param('i', $user_id);
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => $stmt->error]);
    }
    exit();
}
// Get user counts for each role (inactive only)
$all_count = $conn->query("SELECT COUNT(*) as cnt FROM user_accounts WHERE status='inactive'")->fetch_assoc()['cnt'];
$admin_count = $conn->query("SELECT COUNT(*) as cnt FROM user_accounts WHERE status='inactive' AND role='admin'")->fetch_assoc()['cnt'];
$resident_count = $conn->query("SELECT COUNT(*) as cnt FROM user_accounts WHERE status='inactive' AND role='resident'")->fetch_assoc()['cnt'];
$teamofficer_count = $conn->query("SELECT COUNT(*) as cnt FROM user_accounts WHERE status='inactive' AND role='team officer'")->fetch_assoc()['cnt'];
// Get filter from query string
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
// Build query based on filter
if ($role_filter && in_array($role_filter, ['admin', 'resident', 'team officer'])) {
    $stmt = $conn->prepare("SELECT * FROM user_accounts WHERE status='inactive' AND role = ?");
    $stmt->bind_param('s', $role_filter);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query("SELECT * FROM user_accounts WHERE status = 'inactive'");
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include 'header.php'; ?>
<body>
<?php include 'sidebar.php'; ?>
<?php include 'topbar.php'; ?>
<main id="main" class="main">
    <div class="pagetitle">
        <h1>Archive Accounts</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">Archive Accounts</li>
            </ol>
        </nav>
    </div>
    <section class="section">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Inactive Users</h5>
                <div class="mb-3 d-flex gap-2 flex-wrap">
                    <a href="archive-accounts.php" class="btn btn-outline-primary<?php if($role_filter=='') echo ' active'; ?>">ALL (<?php echo $all_count; ?>)</a>
                    <a href="archive-accounts.php?role=admin" class="btn btn-outline-success<?php if($role_filter=='admin') echo ' active'; ?>">Admin (<?php echo $admin_count; ?>)</a>
                    <a href="archive-accounts.php?role=resident" class="btn btn-outline-info<?php if($role_filter=='resident') echo ' active'; ?>">Resident (<?php echo $resident_count; ?>)</a>
                    <a href="archive-accounts.php?role=team%20officer" class="btn btn-outline-warning<?php if($role_filter=='team officer') echo ' active'; ?>">Team Officer (<?php echo $teamofficer_count; ?>)</a>
                </div>
                <div class="table-responsive">
                <table id="archiveTable" class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone Number</th>
                            <th>Address</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    if ($result && $result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            echo '<tr>';
                            echo '<td>' . htmlspecialchars($row['full_name']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['username']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['email']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['role']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['phone_number']) . '</td>';
                            echo '<td>' . htmlspecialchars($row['address']) . '</td>';
                            echo '<td class="text-center">';
                            echo '<div class="d-flex justify-content-center gap-2">';
                            echo '<a href="#" class="btn btn-sm btn-success unarchive-btn" data-user_id="' . htmlspecialchars($row['user_id']) . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Unarchive User"><i class="bi bi-arrow-up-circle"></i></a>';
                            echo '<a href="#" class="btn btn-sm btn-danger delete-btn" data-user_id="' . htmlspecialchars($row['user_id']) . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Delete User"><i class="bi bi-trash"></i></a>';
                            echo '</div>';
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="7" class="text-center">No archived users found.</td></tr>';
                    }
                    ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </section>
</main>
<?php include 'footer.php'; ?>
<script src="../bootstrap/assets/vendor/simple-datatables/simple-datatables.js"></script>
<script>
  const dataTable = new simpleDatatables.DataTable("#archiveTable");
  // Enable Bootstrap tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
  // Handle unarchive button click via AJAX
  document.querySelectorAll('.unarchive-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      if (confirm('Are you sure you want to unarchive this user?')) {
        var userId = this.getAttribute('data-user_id');
        var formData = new FormData();
        formData.append('unarchive_user_ajax', '1');
        formData.append('user_id', userId);
        fetch('archive-accounts.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('User unarchived successfully!');
            location.reload();
          } else {
            alert('Error unarchiving user: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(() => alert('AJAX error.'));
      }
    });
  });
  // Handle delete button click via AJAX
  document.querySelectorAll('.delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
      e.preventDefault();
      if (confirm('Are you sure you want to permanently delete this user? This action cannot be undone.')) {
        var userId = this.getAttribute('data-user_id');
        var formData = new FormData();
        formData.append('delete_user_ajax', '1');
        formData.append('user_id', userId);
        fetch('archive-accounts.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('User deleted successfully!');
            location.reload();
          } else {
            alert('Error deleting user: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(() => alert('AJAX error.'));
      }
    });
  });
</script>
</body>
</html>

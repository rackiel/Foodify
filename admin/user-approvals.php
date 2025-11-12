<?php
// Session and cache control
session_start();
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}
include '../config/db.php';
include '../phpmailer_setting.php';
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require_once '../vendor/autoload.php';

// Approve user function
function approve_user($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE user_accounts SET is_approved=1, status='active' WHERE user_id=?");
    $stmt->bind_param('i', $user_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
// Reject user function
function reject_user($conn, $user_id) {
    $stmt = $conn->prepare("UPDATE user_accounts SET status='rejected' WHERE user_id=?");
    $stmt->bind_param('i', $user_id);
    $success = $stmt->execute();
    $stmt->close();
    return $success;
}
// Send notification email
function send_approval_email($email, $full_name, $type = 'approved') {
    $subject = $type === 'approved' ? 'Your Foodify Account Has Been Approved!' : 'Your Foodify Account Has Been Rejected';
    if ($type === 'approved') {
        $message = '<div style="font-family: Arial, sans-serif; background: #f9f9f9; padding: 32px; border-radius: 12px; max-width: 480px; margin: 0 auto;">' .
            '<div style="text-align:center; margin-bottom: 24px;">' .
                '<img src="http://localhost/foodify/uploads/images/foodify_logo.png" alt="Foodify Logo" style="height: 80px;">' .
            '</div>' .
            '<h2 style="color: #43e97b; text-align:center;">Congratulations, ' . htmlspecialchars($full_name) . '!</h2>' .
            '<p style="font-size: 1.1em; color: #333; text-align:center;">Your account has been <b>approved</b> by the administrator. You can now log in to Foodify.</p>' .
            '<div style="text-align:center; margin: 32px 0;">' .
                '<a href="http://localhost/foodify/index.php" style="background: linear-gradient(90deg, #43e97b 0%, #38f9d7 100%); color: #fff; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: bold; font-size: 1.1em; display: inline-block;">Go to Login</a>' .
            '</div>' .
            '<p style="color: #888; font-size: 0.95em; text-align:center;">Note: Please make sure you have verified your email to complete your registration process.</p>' .
            '<div style="text-align:center; margin-top: 24px; color: #aaa; font-size: 0.9em;">&copy; ' . date('Y') . ' Foodify</div>' .
        '</div>';
    } else {
        $message = '<div style="font-family: Arial, sans-serif; background: #fff3f3; padding: 32px; border-radius: 12px; max-width: 480px; margin: 0 auto;">' .
            '<div style="text-align:center; margin-bottom: 24px;">' .
                '<img src="http://localhost/foodify/uploads/images/foodify_logo.png" alt="Foodify Logo" style="height: 80px;">' .
            '</div>' .
            '<h2 style="color: #e74c3c; text-align:center;">Account Rejected</h2>' .
            '<p style="font-size: 1.1em; color: #333; text-align:center;">We regret to inform you that your Foodify account has been <b>rejected</b> by the administrator.</p>' .
            '<p style="color: #888; font-size: 0.95em; text-align:center;">If you believe this is a mistake, please contact support or try registering again with valid information.</p>' .
            '<div style="text-align:center; margin-top: 24px; color: #aaa; font-size: 0.9em;">&copy; ' . date('Y') . ' Foodify</div>' .
        '</div>';
    }
            $mail = new PHPMailer(true);
            try {
                //Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com'; // Set your SMTP server
                $mail->SMTPAuth   = true;
                $mail->Username   = 'docvic.santiago@gmail.com'; // Your SMTP username
                $mail->Password   = 'zyzphvfzxadjmems'; // Your SMTP password or app password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                //Recipients
                $mail->setFrom('docvic.santiago@gmail.com', 'Foodify');
                $mail->addAddress($email, $full_name);

                // Content
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $message;

                $mail->send();
                } catch (Exception $e) {
                // Optionally log error
                }
}
// Handle AJAX approve/reject requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_user_ajax'])) {
    $user_id = $_POST['user_id'];
    $user = $conn->query("SELECT email, full_name FROM user_accounts WHERE user_id=" . intval($user_id))->fetch_assoc();
    $success = approve_user($conn, $user_id);
    if ($success) {
        send_approval_email($user['email'], $user['full_name'], 'approved');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to approve user.']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reject_user_ajax'])) {
    $user_id = $_POST['user_id'];
    $user = $conn->query("SELECT email, full_name FROM user_accounts WHERE user_id=" . intval($user_id))->fetch_assoc();
    $success = reject_user($conn, $user_id);
    if ($success) {
        send_approval_email($user['email'], $user['full_name'], 'rejected');
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to reject user.']);
    }
    exit();
}

// Get users pending approval (status is empty string)
// Check for rejected filter
$show_rejected = isset($_GET['show']) && $_GET['show'] === 'rejected';
if ($show_rejected) {
    $result = $conn->query("SELECT * FROM user_accounts WHERE status = 'rejected'");
} else {
    $result = $conn->query("SELECT * FROM user_accounts WHERE status = 'pending'");
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
        <h1>User Approvals</h1>
        <nav>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item active">User Approvals</li>
            </ol>
        </nav>
    </div>
    <section class="section">
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Pending User Approvals</h5>
                <div class="mb-3 d-flex gap-2 flex-wrap">
                    <a href="user-approvals.php" class="btn btn-outline-primary<?php if(!$show_rejected) echo ' active'; ?>">Pending</a>
                    <a href="user-approvals.php?show=rejected" class="btn btn-outline-danger<?php if($show_rejected) echo ' active'; ?>">Rejected</a>
                </div>
                <div class="table-responsive">
                <table id="approvalsTable" class="table table-bordered table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Phone Number</th>
                            <th>Address</th>
                            <th>ID/Certification</th>
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
                            echo '<td>';
                            if (!empty($row['id_document'])) {
                                $doc_url = '../' . htmlspecialchars($row['id_document']);
                                $doc_id = 'docModal' . htmlspecialchars($row['user_id']);
                                echo '<a href="#" class="view-doc-link" data-doc-url="' . $doc_url . '" data-doc-type="' . pathinfo($row['id_document'], PATHINFO_EXTENSION) . '" data-bs-toggle="modal" data-bs-target="#viewDocModal">View Document</a>';
                            } else {
                                echo 'N/A';
                            }
                            echo '</td>';
                            echo '<td class="text-center">';
                            if (!$show_rejected) {
                                echo '<div class="d-flex justify-content-center gap-2">';
                                echo '<button class="btn btn-sm btn-success approve-btn" data-user_id="' . htmlspecialchars($row['user_id']) . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Approve User"><i class="bi bi-check-circle"></i></button> ';
                                echo '<button class="btn btn-sm btn-danger reject-btn" data-user_id="' . htmlspecialchars($row['user_id']) . '" data-bs-toggle="tooltip" data-bs-placement="top" title="Reject User"><i class="bi bi-x-circle"></i></button>';
                                echo '</div>';
                            } else {
                                echo '<span class="badge bg-danger">Rejected</span>';
                            }
                            echo '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="8" class="text-center">' . ($show_rejected ? 'No rejected users.' : 'No users pending approval.') . '</td></tr>';
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
<!-- Modal for viewing document -->
<div class="modal fade" id="viewDocModal" tabindex="-1" aria-labelledby="viewDocModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="viewDocModalLabel">ID/Certification Document</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center" id="docModalBody">
        <!-- Content will be injected by JS -->
      </div>
    </div>
  </div>
</div>
<!-- Loading overlay -->
<div id="loadingOverlay" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(255,255,255,0.7); z-index:2000; align-items:center; justify-content:center;">
  <div class="spinner-border text-success" style="width: 4rem; height: 4rem;" role="status">
    <span class="visually-hidden">Loading...</span>
  </div>
  <div style="margin-top: 16px; font-size: 1.2em; color: #43e97b; font-weight: bold;">Processing, please wait...</div>
</div>
<script src="../bootstrap/assets/vendor/simple-datatables/simple-datatables.js"></script>
<script>
  const dataTable = new simpleDatatables.DataTable("#approvalsTable");
  // Enable Bootstrap tooltips
  var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
  var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
  });
  // Show/hide loading overlay
  function showLoading() {
    document.getElementById('loadingOverlay').style.display = 'flex';
  }
  function hideLoading() {
    document.getElementById('loadingOverlay').style.display = 'none';
  }
  // Approve button
  document.querySelectorAll('.approve-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (confirm('Approve this user?')) {
        showLoading();
        var userId = this.getAttribute('data-user_id');
        var formData = new FormData();
        formData.append('approve_user_ajax', '1');
        formData.append('user_id', userId);
        fetch('user-approvals.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          hideLoading();
          if (data.success) {
            alert('User approved!');
            location.reload();
          } else {
            alert('Error: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(() => { hideLoading(); alert('AJAX error.'); });
      }
    });
  });
  // Reject button
  document.querySelectorAll('.reject-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
      if (confirm('Reject this user?')) {
        showLoading();
        var userId = this.getAttribute('data-user_id');
        var formData = new FormData();
        formData.append('reject_user_ajax', '1');
        formData.append('user_id', userId);
        fetch('user-approvals.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          hideLoading();
          if (data.success) {
            alert('User rejected.');
            location.reload();
          } else {
            alert('Error: ' + (data.error || 'Unknown error'));
          }
        })
        .catch(() => { hideLoading(); alert('AJAX error.'); });
      }
    });
  });
  // View document in modal
  document.querySelectorAll('.view-doc-link').forEach(function(link) {
    link.addEventListener('click', function(e) {
      e.preventDefault();
      var url = this.getAttribute('data-doc-url');
      var ext = this.getAttribute('data-doc-type').toLowerCase();
      var modalBody = document.getElementById('docModalBody');
      if (['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(ext)) {
        modalBody.innerHTML = '<img src="' + url + '" alt="Document" style="max-width:100%; max-height:70vh; border-radius:8px;">';
      } else if (ext === 'pdf') {
        modalBody.innerHTML = '<embed src="' + url + '" type="application/pdf" width="100%" height="600px">';
      } else {
        modalBody.innerHTML = '<a href="' + url + '" target="_blank">Download Document</a>';
      }
    });
  });
</script>
</body>
</html>

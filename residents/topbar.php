<!-- ======= Header ======= -->
<header id="header" class="header fixed-top d-flex align-items-center">

<div class="d-flex align-items-center justify-content-between">
  <a href="index.php" class="logo d-flex align-items-center">
    <img src="../uploads/images/foodify_logo.png" alt="">
  </a>
  <i class="bi bi-list toggle-sidebar-btn"></i>
</div><!-- End Logo -->

<nav class="header-nav ms-auto">
  <ul class="d-flex align-items-center">

    <li class="nav-item dropdown pe-3">

      <a class="nav-link nav-profile d-flex align-items-center pe-0" href="#" data-bs-toggle="dropdown">
        <?php 
        // Fetch profile image from database to ensure latest version
        $profile_img = '../uploads/profile_picture/no_image.png'; // Default
        if (isset($_SESSION['user_id'])) {
            try {
                $user_id = $_SESSION['user_id'];
                $stmt = $conn->prepare("SELECT profile_img FROM user_accounts WHERE user_id = ?");
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0) {
                    $user_data = $result->fetch_assoc();
                    if (!empty($user_data['profile_img'])) {
                        $profile_img = '../uploads/profile_picture/' . $user_data['profile_img'];
                    }
                }
                $stmt->close();
            } catch (Exception $e) {
                // Keep default image on error
            }
        }
        $cache_buster = '?v=' . time();
        ?>
        <img src="<?php echo $profile_img . $cache_buster; ?>" alt="Profile" class="rounded-circle" width="40" height="40" style="object-fit: cover;">
        <span class="d-none d-md-block dropdown-toggle ps-2">
          <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'Guest'; ?>
        </span>
      </a><!-- End Profile Iamge Icon -->
      <ul class="dropdown-menu dropdown-menu-end dropdown-menu-arrow profile">
        <li class="dropdown-header">
          <h6><?php echo isset($_SESSION['full_name']) ? htmlspecialchars($_SESSION['full_name']) : 'Guest'; ?></h6>
          <span><?php echo isset($_SESSION['role']) ? htmlspecialchars(ucwords($_SESSION['role'])) : 'Not logged in'; ?></span>
        </li>
        <li><hr class="dropdown-divider"></li>
        <?php if (isset($_SESSION['user_id'])): ?>
        <li>
          <a class="dropdown-item d-flex align-items-center" href="settings.php">
            <i class="bi bi-person"></i>
            <span>Profile</span>
          </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item d-flex align-items-center" href="logout.php">
            <i class="bi bi-box-arrow-right"></i>
            <span>Sign Out</span>
          </a>
        </li>
        <?php else: ?>
        <li>
          <a class="dropdown-item d-flex align-items-center" href="../index.php">
            <i class="bi bi-box-arrow-in-right"></i>
            <span>Login</span>
          </a>
        </li>
        <?php endif; ?>
      </ul><!-- End Profile Dropdown Items -->
    </li><!-- End Profile Nav -->

  </ul>
</nav><!-- End Icons Navigation -->

</header><!-- End Header -->

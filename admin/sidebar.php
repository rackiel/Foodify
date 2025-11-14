<!-- ======= Sidebar ======= -->
<aside id="sidebar" class="sidebar">
  <ul class="sidebar-nav" id="sidebar-nav">
    <!-- Dashboard -->
    <li class="nav-item">
      <a class="nav-link" href="index.php">
        <i class="bi bi-grid"></i>
        <span>Dashboard</span>
      </a>
    </li>
    <!-- User Management -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#user-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-people"></i><span>User Management</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="user-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="users.php"><i class="bi bi-circle"></i><span>Users</span></a></li>
        <li><a href="archive-accounts.php"><i class="bi bi-circle"></i><span>Archive Accounts</span></a></li>
        <li><a href="user-approvals.php"><i class="bi bi-circle"></i><span>User Approvals</span></a></li>
        <li><a href="users-profile.php"><i class="bi bi-circle"></i><span>User Profile</span></a></li>
      </ul>
    </li>
    <!-- Food Sharing Posts -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#food-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-basket"></i><span>Food Sharing Posts</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="food-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="admin-food-posts.php"><i class="bi bi-circle"></i><span>All Posts</span></a></li>
        <li><a href="admin-food-approvals.php"><i class="bi bi-circle"></i><span>Pending Approvals</span></a></li>
        <li><a href="admin-expired-posts.php"><i class="bi bi-circle"></i><span>Expired/Flagged</span></a></li>
      </ul>
    </li>
    <!-- Announcements & Guidelines -->
    <li class="nav-item">
      <a class="nav-link" href="admin-announcement.php">
        <i class="bi bi-megaphone"></i>
        <span>Announcement & Guidelines</span>
      </a>
    </li>
    <!-- Community Management -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#community-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-people-fill"></i><span>Community</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="community-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="user-challenges-progress.php"><i class="bi bi-circle"></i><span>User Challenges Progress</span></a></li>
        <li><a href="admin-newsfeeds.php"><i class="bi bi-circle"></i><span>Newsfeeds</span></a></li>
      </ul>
    </li>
    <!-- Analytics & Reports -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#analytics-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-bar-chart"></i><span>Analytics & Reports</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="analytics-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="admin-analytics.php"><i class="bi bi-circle"></i><span>Analytics Dashboard</span></a></li>
        <li><a href="admin-reports.php"><i class="bi bi-circle"></i><span>Export Reports</span></a></li>
      </ul>
    </li>
    <!-- Settings -->
    <li class="nav-item">
      <a class="nav-link" href="admin-settings.php">
        <i class="bi bi-gear"></i>
        <span>Settings</span>
      </a>
    </li>
    <!-- Logout -->
    <li class="nav-item">
      <a class="nav-link" href="logout.php">
        <i class="bi bi-box-arrow-right"></i>
        <span>Logout</span>
      </a>
    </li>
  </ul>
</aside><!-- End Sidebar-->
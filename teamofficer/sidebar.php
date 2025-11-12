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
    <!-- Food Donation Management -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#donation-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-basket"></i><span>Food Donation Management</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="donation-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="donation-management.php"><i class="bi bi-circle"></i><span>All Donations</span></a></li>
        <li><a href="donation-approvals.php"><i class="bi bi-circle"></i><span>Pending Approvals</span></a></li>
        <li><a href="expired-donations.php"><i class="bi bi-circle"></i><span>Expired/Flagged</span></a></li>
        <li><a href="donation_request.php"><i class="bi bi-circle"></i><span>Donation Requests</span></a></li>
      </ul>
    </li>
    <!-- Announcements -->
    <li class="nav-item">
      <a class="nav-link" href="announcements.php">
        <i class="bi bi-megaphone"></i>
        <span>Announcements</span>
      </a>
    </li>
    <!-- Community Management -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#community-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-people-fill"></i><span>Community</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="community-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="user-reports.php"><i class="bi bi-circle"></i><span>User Reports</span></a></li>
        <li><a href="community-feedback.php"><i class="bi bi-circle"></i><span>Community Feedback</span></a></li>
      </ul>
    </li>
    <!-- Analytics & Reports -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#analytics-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-bar-chart"></i><span>Analytics & Reports</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="analytics-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="community_impact.php"><i class="bi bi-circle"></i><span>Community Impact</span></a></li>
        <li><a href="donation-analytics.php"><i class="bi bi-circle"></i><span>Donation Analytics</span></a></li>
        <li><a href="user-activity.php"><i class="bi bi-circle"></i><span>User Activity</span></a></li>
        <li><a href="reports.php"><i class="bi bi-circle"></i><span>Generate Reports</span></a></li>
      </ul>
    </li>
    <!-- Settings -->
    <li class="nav-item">
      <a class="nav-link" href="settings.php">
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

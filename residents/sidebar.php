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
    <!-- Ingredient & Meal Planning -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#meal-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-egg-fried"></i><span>Ingredient & Meal Planning</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="meal-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="input_ingredients.php"><i class="bi bi-circle"></i><span>Ingredients Feed</span></a></li>
        <li><a href="used_ingredients.php"><i class="bi bi-circle"></i><span>Used Ingredients</span></a></li>
        <li><a href="expired_ingredients.php"><i class="bi bi-circle"></i><span>Expired Ingredients</span></a></li>
        <li><a href="meal_plan_generator.php"><i class="bi bi-circle"></i><span>Meal Plan Generator</span></a></li>
        <li><a href="saved_plans.php"><i class="bi bi-circle"></i><span>Saved/Printable Plans</span></a></li>
      </ul>
    </li>
    <!-- Food Sharing & Donations -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#food-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-basket"></i><span>Food Sharing & Donations</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="food-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="post_excess_food.php"><i class="bi bi-circle"></i><span>Post Excess Food</span></a></li>
        <li><a href="browse_donations.php"><i class="bi bi-circle"></i><span>Browse Donations</span></a></li>
        <li><a href="my_requests.php"><i class="bi bi-circle"></i><span>My Food Requests</span></a></li>
        <li><a href="my_food_status.php"><i class="bi bi-circle"></i><span>My Food Status</span></a></li>
        <li><a href="donation_history.php"><i class="bi bi-circle"></i><span>My Donation History</span></a></li>
      </ul>
    </li>
    <!-- Community Features -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#community-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-people-fill"></i><span>Community Features</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="community-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="challenges_events.php"><i class="bi bi-circle"></i><span>Challenges & Events</span></a></li>
        <li><a href="recipes_tips.php"><i class="bi bi-circle"></i><span>News Feed</span></a></li>
        <li><a href="announcements.php"><i class="bi bi-circle"></i><span>Announcements</span></a></li>
        <li><a href="community_impact.php"><i class="bi bi-circle"></i><span>Community Impact & Statistics</span></a></li>
        <li><a href="user-feedback.php"><i class="bi bi-circle"></i><span>Submit Feedback</span></a></li>
      </ul>
    </li>
    <!-- Profile & Settings -->
    <li class="nav-item">
      <a class="nav-link collapsed" data-bs-target="#profile-nav" data-bs-toggle="collapse" href="#">
        <i class="bi bi-person"></i><span>Profile & Settings</span><i class="bi bi-chevron-down ms-auto"></i>
      </a>
      <ul id="profile-nav" class="nav-content collapse" data-bs-parent="#sidebar-nav">
        <li><a href="settings.php"><i class="bi bi-circle"></i><span>Edit Profile</span></a></li>
        <li><a href="preferences.php"><i class="bi bi-circle"></i><span>Preferences</span></a></li>
      </ul>
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

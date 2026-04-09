<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
    <!-- Fixed Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../../assets/Algomeal.svg" alt="AlgoMeal Logo" class="brand-logo">
            <span class="sidebar-title"><strong>Algo</strong>Meal</span>
        </div>

        
        <ul class="nav-list">
            <li class="nav-item">
                <a href="../dashboard/dashboard.php" class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">dashboard</span>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../students/students.php" class="nav-link <?php echo $currentPage == 'students.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">people</span>
                    <span class="nav-text">Students</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../recipes/recipes.php" class="nav-link <?php echo $currentPage == 'recipes.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">restaurant_menu</span>
                    <span class="nav-text">Recipes</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../meal_planner/meal_planner.php" class="nav-link <?php echo $currentPage == 'meal_planner.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">assignment</span>
                    <span class="nav-text">Meal Planner</span>
                </a>
            </li>
            <li class="nav-item">
                <a href="../management/management.php" class="nav-link <?php echo $currentPage == 'management.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">admin_panel_settings</span>
                    <span class="nav-text">Management</span>
                </a>
            </li>
        </ul>

        <!-- Fixed Sidebar User Profile -->
        <div class="sidebar-user">
            <div class="sidebar-user-avatar-circle">
                <?php echo strtoupper(substr($username, 0, 1)); ?>
            </div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?php echo htmlspecialchars($username); ?></div>
                <div class="sidebar-user-role"><?php echo htmlspecialchars($role); ?></div>
            </div>
        </div>

        <div class="nav-logout nav-item">
            <!-- Triggers the logout modal -->
            <a href="#" onclick="showLogoutModal(); return false;" class="nav-link" style="color: #d93025;">
                <span class="material-icons nav-icon" style="color: #d93025;">logout</span>
                <span class="nav-text">Logout</span>
            </a>
        </div>

    </aside>

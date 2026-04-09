<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <img src="../../assets/Algomeal.svg" alt="AlgoMeal Logo" class="brand-logo">
            <span class="sidebar-title"><strong>Algo</strong>Meal</span>
        </div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="../dashboard/dashboard.php" class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">dashboard</span>
                    Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="../students/students.php" class="nav-link <?php echo $currentPage == 'students.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">people</span>
                    Students
                </a>
            </li>
            <li class="nav-item">
                <a href="../recipes/recipes.php" class="nav-link <?php echo $currentPage == 'recipes.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">restaurant_menu</span>
                    Recipes
                </a>
            </li>
            <li class="nav-item">
                <a href="../meal_planner/meal_planner.php" class="nav-link <?php echo $currentPage == 'meal_planner.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">assignment</span>
                    Meal Planner
                </a>
            </li>
            <li class="nav-item">
                <a href="../management/management.php" class="nav-link <?php echo $currentPage == 'management.php' ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">admin_panel_settings</span>
                    Management
                </a>
            </li>
        </ul>
        <div class="nav-logout">
            <!-- Triggers the logout modal -->
            <a href="#" onclick="showLogoutModal(); return false;" class="nav-link" style="color: var(--error-color); color: #d93025;">
                <span class="material-icons nav-icon">logout</span>
                Logout
            </a>
        </div>
    </aside>

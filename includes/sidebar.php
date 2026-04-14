<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!-- Fixed Sidebar -->
<aside class="sidebar">
    <div class="sidebar-header" id="algo-logo" style="cursor: pointer;">
        <img src="../../assets/Algomeal.svg" alt="AlgoMeal Logo" class="brand-logo">
        <span class="sidebar-title"><strong>Algo</strong>Meal</span>
    </div>


    <ul class="nav-list">
        <li class="nav-item">
            <a href="../dashboard/dashboard.php"
                class="nav-link <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>">
                <span class="material-icons nav-icon">dashboard</span>
                <span class="nav-text">Dashboard</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../students/students.php"
                class="nav-link <?php echo $currentPage == 'students.php' ? 'active' : ''; ?>">
                <span class="material-icons nav-icon">people</span>
                <span class="nav-text">Students</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../recipes/recipes.php"
                class="nav-link <?php echo $currentPage == 'recipes.php' ? 'active' : ''; ?>">
                <span class="material-icons nav-icon">restaurant_menu</span>
                <span class="nav-text">Recipes</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../meal_planner/meal_planner.php"
                class="nav-link <?php echo $currentPage == 'meal_planner.php' ? 'active' : ''; ?>">
                <span class="material-icons nav-icon">assignment</span>
                <span class="nav-text">Meal Planner</span>
            </a>
        </li>
        <li class="nav-item">
            <a href="../management/management.php"
                class="nav-link <?php echo $currentPage == 'management.php' ? 'active' : ''; ?>">
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

    <!-- Hidden Debug Sandbox -->
    <div id="debugSandbox" class="nav-item" style="display:none; padding: 1rem; border-top: 1px dashed var(--error); background: #fef2f2; margin-top: auto;">
        <div style="font-size: 0.7rem; font-weight: 800; color: #b91c1c; text-transform: uppercase; margin-bottom: 0.5rem;"><span class="material-icons" style="font-size: 14px; vertical-align: middle;">bug_report</span> Debug Sandbox</div>
        <p style="font-size: 0.65rem; color: #991b1b; margin: 0 0 0.5rem 0; line-height: 1.2;">Debug logs now attach to optimization payloads.</p>
        <button onclick="triggerDatabaseNuke()" style="width: 100%; border: none; background: #dc2626; color: white; border-radius: 4px; padding: 0.5rem; font-size: 0.75rem; font-weight: 700; cursor: pointer;">NUKE SYSTEM DATA</button>
    </div>

</aside>

<script>
    // Hidden Easter Egg Logic
    let logoClicks = 0;
    let logoClickTimer = null;
    
    document.getElementById('algo-logo').addEventListener('click', () => {
        logoClicks++;
        clearTimeout(logoClickTimer);
        
        if (logoClicks >= 5) {
            logoClicks = 0;
            window.debugMode = true;
            document.getElementById('debugSandbox').style.display = 'block';
            console.warn("ALGO-MEAL DEBUG SANDBOX UNLOCKED");
        } else {
            logoClickTimer = setTimeout(() => { logoClicks = 0; }, 2000);
        }
    });

    async function triggerDatabaseNuke() {
        if (!confirm("CRITICAL WARNING:\n\nYou are about to DELETE all students, recipes, and meal plans from the database. This action is IRREVERSIBLE.\n\nType 'CONFIRM' to proceed.") && false) return;
        
        const verification = prompt("Type 'NUKE' to absolutely confirm data destruction:");
        if (verification === 'NUKE') {
            try {
                const res = await fetch('../../pages/management/api_nuke_database.php', { method: 'POST' });
                const data = await res.json();
                if (data.success) {
                    alert("System completely purged.");
                    window.location.reload();
                } else {
                    alert("Wipe failed: " + data.message);
                }
            } catch (e) {
                alert("Critical network error during wipe.");
            }
        }
    }
</script>
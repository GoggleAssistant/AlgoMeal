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
            <a href="../management/documentation.php"
                class="nav-link <?php echo ($currentPage == 'documentation.php') ? 'active' : ''; ?>">
                <span class="material-icons nav-icon">description</span>
                <span class="nav-text">Documentation</span>
            </a>
        </li>

        <?php if ($role === 'Admin' || $role === 'Super Admin'): ?>
            <li class="nav-item">
                <a href="../management/management.php"
                    class="nav-link <?php echo ($currentPage == 'management.php') ? 'active' : ''; ?>">
                    <span class="material-icons nav-icon">admin_panel_settings</span>
                    <span class="nav-text">Management Hub</span>
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- Fixed Sidebar User Profile -->
    <div class="sidebar-user" style="padding-left: 1.5rem;">
        <?php
        $roleColor = '#64748b'; // Default
        if ($role === 'Faculty')
            $roleColor = '#10b981'; // Green
        elseif ($role === 'Admin')
            $roleColor = '#8b5cf6'; // Purple
        elseif ($role === 'Super Admin')
            $roleColor = '#f59e0b'; // Yellow
        ?>
        <div class="sidebar-user-info" style="padding-left: 0;">
            <div class="sidebar-user-name"><?php echo htmlspecialchars($username); ?></div>
            <div class="sidebar-user-role" style="color: <?php echo $roleColor; ?>; font-weight: 700;">
                <?php echo htmlspecialchars($role); ?></div>
        </div>
    </div>

    <div class="nav-item">
        <a href="#" onclick="showAccountSettingsModal(); return false;" class="nav-link" style="color: var(--text-main);">
            <span class="material-icons nav-icon" style="color: var(--text-muted);">manage_accounts</span>
            <span class="nav-text">Account Settings</span>
        </a>
    </div>

    <div class="nav-logout nav-item">
        <!-- Triggers the logout modal -->
        <a href="#" onclick="showLogoutModal(); return false;" class="nav-link" style="color: #d93025;">
            <span class="material-icons nav-icon" style="color: #d93025;">logout</span>
            <span class="nav-text">Logout</span>
        </a>
    </div>

    <!-- Hidden Debug Sandbox -->
    <div id="debugSandbox" class="nav-item"
        style="display:none; padding: 1rem; border-top: 1px dashed var(--error); background: #fef2f2; margin-top: auto;">
        <div
            style="font-size: 0.7rem; font-weight: 800; color: #b91c1c; text-transform: uppercase; margin-bottom: 0.5rem;">
            <span class="material-icons" style="font-size: 14px; vertical-align: middle;">bug_report</span> Debug
            Sandbox</div>
        <p style="font-size: 0.65rem; color: #991b1b; margin: 0 0 0.5rem 0; line-height: 1.2;">Debug logs now attach to
            optimization payloads.</p>
        <button onclick="triggerDatabaseNuke()"
            style="width: 100%; border: none; background: #dc2626; color: white; border-radius: 4px; padding: 0.5rem; font-size: 0.75rem; font-weight: 700; cursor: pointer;">NUKE
            SYSTEM DATA</button>
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

    function showLogoutModal() {
        AlgoModal.show({
            title: 'Confirm Logout',
            body: '<p class="modal-text">Are you sure you want to end your session and return to the login screen?</p>',
            footer: `
                <button class="btn btn-outline" onclick="AlgoModal.close()">Cancel</button>
                <a href="../../logout.php" class="btn btn-confirm">Logout</a>
            `
        });
    }

    function showAccountSettingsModal() {
        const body = `
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--text-main); margin-bottom:0.4rem;">Display Name</label>
                <input type="text" id="editUserName" value="<?php echo htmlspecialchars($username); ?>" style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px; font-weight:600;">
            </div>
            <div>
                <label style="display:block; font-size:0.75rem; font-weight:700; color:var(--text-main); margin-bottom:0.4rem;">New Password</label>
                <input type="password" id="editUserPass" placeholder="Leave blank to keep current password" style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px; font-weight:600;">
            </div>
        `;
        AlgoModal.show({
            title: 'Account Settings',
            body: body,
            footer: `<button class="btn-m3 btn-m3-outline" onclick="AlgoModal.close()">Cancel</button><button class="btn-m3 btn-m3-primary" onclick="runUpdateProfile()">Save Changes</button>`
        });
    }

    async function runUpdateProfile() {
        const name = document.getElementById('editUserName').value;
        const pass = document.getElementById('editUserPass').value;
        if (!name) return AlgoModal.alert('Missing Input', 'Display name cannot be empty.');

        AlgoModal.close();
        try {
            const res = await fetch('../../pages/auth/api_update_profile.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ faculty_name: name, password: pass })
            });
            const data = await res.json();
            if (data.success) {
                alert('Profile updated successfully.');
                location.reload();
            } else {
                setTimeout(() => AlgoModal.alert('Update Error', data.message), 300);
            }
        } catch (e) {
            alert('Failed to connect to server.');
        }
    }
</script>
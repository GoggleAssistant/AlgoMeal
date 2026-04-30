<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>
<?php
$page_title = 'Administrative Management';
require_once '../../includes/topbar.php';
require_once '../../db.php';

// Strict Admin Check
if ($role !== 'Admin' && $role !== 'Super Admin') {
    echo "<div class='content'><div class='alert alert-error'>Access Denied: Administrative privileges required.</div></div>";
    require_once '../../includes/footer.php';
    exit;
}

// Fetch Settings
$res_settings = $conn->query("SELECT * FROM settings");
$settings = [];
while ($row = $res_settings->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$allocated_budget = (float) ($settings['total_allocated_budget'] ?? 500000.00);
$daily_limit = (float) ($settings['total_daily_budget'] ?? 0);

// Financial Sums
$meal_spent = (float) ($conn->query("SELECT SUM(mp.actual_cost) as total FROM meal_plan mp JOIN daily_meal_plans dmp ON mp.scheduled_date = dmp.scheduled_date WHERE dmp.is_served = 1")->fetch_assoc()['total'] ?? 0);
$logs_spent = (float) ($conn->query("SELECT SUM(amount) as total FROM budget_logs")->fetch_assoc()['total'] ?? 0);
$total_spent = $meal_spent + $logs_spent;
$remaining_funds = $allocated_budget - $total_spent;

// Categories for Chart
$categories = ['Food Supplies' => $meal_spent, 'Labor Costs' => 0, 'Logistics' => 0, 'Misc' => 0];
$res_cat = $conn->query("SELECT category, SUM(amount) as total FROM budget_logs GROUP BY category");
while ($row = $res_cat->fetch_assoc()) {
    if (isset($categories[$row['category']]))
        $categories[$row['category']] += (float) $row['total'];
    else
        $categories[$row['category']] = (float) $row['total'];
}
?>

<style>
    /* MANAGEMENT HUB SPECIFIC STYLES */
    .mgmt-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-end;
        margin-bottom: 2.5rem;
    }

    .mgmt-tabs {
        display: flex;
        gap: 0.75rem;
        margin-top: 1.5rem;
        margin-bottom: 2.5rem;
        padding-bottom: 1rem;
        border-bottom: 1px dashed var(--border);
        overflow-x: auto;
    }

    .tab-btn {
        padding: 0.6rem 1.5rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 100px;
        font-weight: 700;
        font-size: 0.85rem;
        color: var(--text-muted);
        cursor: pointer;
        transition: 0.2s;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tab-btn:hover {
        background: #f1f5f9;
        color: var(--text-main);
    }

    .tab-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px -2px rgba(59, 130, 246, 0.3);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .kpi-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1.5rem;
        margin-bottom: 2rem;
    }

    .kpi-card {
        background: white;
        padding: 1.5rem;
        border-radius: 16px;
        border: 1px solid var(--border);
        box-shadow: var(--shadow-sm);
    }

    .kpi-label {
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 0.5rem;
    }

    .kpi-value {
        font-size: 1.75rem;
        font-weight: 900;
        color: var(--text-main);
    }

    .kpi-subtext {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.25rem;
    }

    .kpi-card.warning {
        border-left: 5px solid var(--warning);
        background: #fffbeb;
    }

    .kpi-card.warning .kpi-value {
        color: var(--warning);
    }

    .main-grid {
        display: grid;
        grid-template-columns: 380px 1fr;
        gap: 2rem;
    }

    .card-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
    }

    .card-header h3 {
        font-size: 1.1rem;
        font-weight: 800;
        margin: 0;
    }

    .chart-container {
        position: relative;
        height: 280px;
        width: 100%;
    }

    .reports-table {
        width: 100%;
        border-collapse: collapse;
    }

    .reports-table th {
        text-align: left;
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--text-muted);
        padding: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .reports-table td {
        padding: 1.25rem 1rem;
        border-bottom: 1px solid #f8fafc;
        font-size: 0.875rem;
    }

    .budget-tag {
        padding: 0.3rem 0.75rem;
        border-radius: 100px;
        font-size: 0.7rem;
        font-weight: 800;
        text-transform: uppercase;
    }

    .tag-labor {
        background: #e0f2fe;
        color: #0369a1;
    }

    .tag-logistics {
        background: #fef3c7;
        color: #92400e;
    }

    .tag-misc {
        background: #f1f5f9;
        color: #475569;
    }

    .tag-food {
        background: #dcfce7;
        color: #15803d;
    }

    .form-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1.5rem;
    }

    .form-field label {
        display: block;
        font-size: 0.75rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        margin-bottom: 0.5rem;
    }

    .form-field input,
    .form-field select {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: 10px;
        font-weight: 600;
        outline: none;
        transition: 0.2s;
    }

    .form-field input:focus {
        border-color: var(--primary);
    }

    @media print {
        .no-print {
            display: none !important;
        }

        .tab-content {
            display: block !important;
        }

        .main-content {
            margin: 0 !important;
            width: 100% !important;
        }
    }
</style>

<div class="content">
    <div class="mgmt-header no-print">
        <div class="mgmt-title">
            <h2 style="font-size: 1.75rem; font-weight: 900; margin-bottom: 0.4rem;">Administrative Management Hub</h2>
            <p style="color: var(--text-muted);">Fiscal control, user accounts, and system configuration tools.</p>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <button class="btn-m3 btn-m3-outline" onclick="window.print()"><span class="material-icons">print</span>
                Print Page</button>
        </div>
    </div>

    <div class="mgmt-tabs no-print">
        <button class="tab-btn <?= (!isset($_GET['tab']) || $_GET['tab'] == 'fiscal') ? 'active' : '' ?>"
            data-tab="fiscal" onclick="switchTab('fiscal')">FISCAL CONTROL</button>
        <button class="tab-btn <?= (isset($_GET['tab']) && $_GET['tab'] == 'users') ? 'active' : '' ?>" data-tab="users"
            onclick="switchTab('users')">USER ACCOUNTS</button>
        <button class="tab-btn <?= (isset($_GET['tab']) && $_GET['tab'] == 'settings') ? 'active' : '' ?>"
            data-tab="settings" onclick="switchTab('settings')">SYSTEM SETTINGS</button>
    </div>

    <?php $activeTab = $_GET['tab'] ?? 'fiscal'; ?>

    <!-- TAB: FISCAL -->
    <div id="tab-fiscal" class="tab-content <?= ($activeTab == 'fiscal') ? 'active' : '' ?>">
        <div class="kpi-row no-print">
            <div class="kpi-card">
                <div class="kpi-label" style="display:flex; justify-content:space-between; align-items:center;">
                    Strategic Allocation
                    <button onclick="toggleBudgetEdit()"
                        style="background:none; border:none; color:var(--primary); cursor:pointer;"><span
                            class="material-icons" style="font-size:18px;">edit</span></button>
                </div>
                <div id="budgetDisplay">
                    <div class="kpi-value">&#8369;<?= number_format($allocated_budget, 2) ?></div>
                    <div class="kpi-subtext">Base funding cap for the school year.</div>
                </div>
                <div id="budgetEdit" style="display:none; margin-top:0.5rem;">
                    <div style="margin-bottom:0.75rem;">
                        <input type="number" id="setAllocBudget" value="<?= $allocated_budget ?>"
                            style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:8px; font-weight:700;">
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn-m3 btn-m3-primary" onclick="saveBudgetSettings()"
                            style="padding:6px 14px; font-size:0.75rem;">Save</button>
                        <button class="btn-m3 btn-m3-outline" onclick="toggleBudgetEdit()"
                            style="padding:6px 14px; font-size:0.75rem;">Cancel</button>
                    </div>
                </div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Cumulative Spend</div>
                <div class="kpi-value" style="color:var(--primary);">&#8369;<?= number_format($total_spent, 2) ?></div>
                <div class="kpi-subtext">Meal costs + Manual operational logs.</div>
            </div>
            <div class="kpi-card <?= $remaining_funds < 50000 ? 'warning' : '' ?>">
                <div class="kpi-label">Liquidity Pool</div>
                <div class="kpi-value" style="<?= $remaining_funds < 0 ? 'color:var(--error);' : '' ?>">
                    &#8369;<?= number_format($remaining_funds, 2) ?></div>
                <div class="kpi-subtext">Daily Limit: &#8369;<?= number_format($daily_limit, 0) ?></div>
            </div>
        </div>

        <div class="main-grid">
            <div style="display:flex; flex-direction:column; gap:2rem;">
                <div class="dashboard-card no-print">
                    <div class="card-header">
                        <span class="material-icons" style="color:var(--primary);">pie_chart</span>
                        <h3>Budget Distribution</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="allocationChart"></canvas>
                    </div>
                </div>
                <div class="dashboard-card no-print">
                    <div class="card-header">
                        <span class="material-icons" style="color:var(--primary);">receipt_long</span>
                        <h3>Manual Log</h3>
                    </div>
                    <div class="logging-form">
                        <div class="form-grid">
                            <div class="form-field"><label>Amount</label><input type="number" id="logAmount"
                                    placeholder="0.00"></div>
                            <div class="form-field">
                                <label>Category</label>
                                <select id="logCategory">
                                    <option>Labor Costs</option>
                                    <option>Logistics</option>
                                    <option>Misc</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-field" style="margin-bottom:1.5rem;">
                            <label>Description</label>
                            <input type="text" id="logDesc" placeholder="e.g., LPG refill, Transport fees">
                        </div>
                        <button class="btn-m3 btn-m3-primary" style="width:100%; border-radius: 12px; height: 48px;"
                            onclick="submitLog()">Record Log</button>
                    </div>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header">
                    <span class="material-icons" style="color:var(--primary);">history</span>
                    <h3>Expenditure Ledger</h3>
                </div>
                <div style="overflow-x: auto;">
                    <table class="reports-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Category</th>
                                <th>Description</th>
                                <th style="text-align:right;">Amount</th>
                                <th style="text-align:center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $l = $conn->query("SELECT * FROM budget_logs ORDER BY created_at DESC LIMIT 10");
                            while ($r = $l->fetch_assoc()):
                                $cat_class = 'tag-' . strtolower(explode(' ', $r['category'])[0]);
                                $log_json = htmlspecialchars(json_encode($r), ENT_QUOTES);
                                ?>
                                <tr>
                                    <td style="white-space:nowrap;"><?= date('M d, Y', strtotime($r['created_at'])) ?></td>
                                    <td><span class="budget-tag <?= $cat_class ?>"><?= $r['category'] ?></span></td>
                                    <td style="color:var(--text-muted); font-weight: 500;">
                                        <?= htmlspecialchars($r['description']) ?></td>
                                    <td style="text-align:right; font-weight:800; color:var(--text-main);">
                                        &#8369;<?= number_format($r['amount'], 2) ?></td>
                                    <td style="text-align:center;">
                                        <button class="btn-m3 btn-m3-outline" style="padding: 4px 10px; font-size: 0.7rem;" onclick='openEditLogModal(<?= $log_json ?>)'>
                                            <span class="material-icons" style="font-size:14px;">edit</span>
                                        </button>
                                        <button class="btn-m3 btn-m3-danger" style="padding: 4px 10px; font-size: 0.7rem;" onclick='deleteLog(<?= $r['id'] ?>)'>
                                            <span class="material-icons" style="font-size:14px;">delete</span>
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- TAB: USERS -->
    <div id="tab-users" class="tab-content <?= ($activeTab == 'users') ? 'active' : '' ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2rem;">
            <div>
                <h3 style="margin:0;">User Account Management</h3>
                <p style="font-size:0.85rem; color:var(--text-muted);">Manage faculty and administrative access
                    privileges.</p>
            </div>
            <button class="btn-m3 btn-m3-primary" onclick="openAddUserModal()">
                <span class="material-icons">person_add</span> Add New User
            </button>
        </div>

        <div class="dashboard-card">
            <table class="reports-table">
                <thead>
                    <tr>
                        <th>Faculty Name</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th style="text-align:right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $users = $conn->query("SELECT * FROM users ORDER BY role ASC, faculty_name ASC");
                    while ($u = $users->fetch_assoc()):
                        ?>
                        <tr>
                            <td style="font-weight:700;"><?= htmlspecialchars($u['faculty_name']) ?></td>
                            <td><span class="badge"
                                    style="background:#f1f5f9; color:var(--text-main);"><?= $u['role'] ?></span></td>
                            <td>
                                <span class="badge"
                                    style="background:<?= $u['status'] == 'Active' ? '#dcfce7' : '#fee2e2' ?>; color:<?= $u['status'] == 'Active' ? '#166534' : '#991b1b' ?>;">
                                    <?= $u['status'] ?>
                                </span>
                            </td>
                            <td style="text-align:right;">
                                <button class="btn-m3 btn-m3-outline" style="padding: 6px 12px;"
                                    onclick="toggleUserStatus(<?= $u['user_id'] ?>)">
                                    <span class="material-icons"
                                        style="font-size:18px;"><?= $u['status'] == 'Active' ? 'block' : 'check_circle' ?></span>
                                </button>
                                <button class="btn-m3 btn-m3-outline" style="padding: 6px 12px; color:var(--error);"
                                    onclick="permanentDeleteUser(<?= $u['user_id'] ?>)">
                                    <span class="material-icons" style="font-size:18px;">delete_forever</span>
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- TAB: SETTINGS -->
    <div id="tab-settings" class="tab-content <?= ($activeTab == 'settings') ? 'active' : '' ?>">
        <div style="margin-bottom:2rem;">
            <h3 style="margin:0;">Global System Settings</h3>
            <p style="font-size:0.85rem; color:var(--text-muted);">Configure school identity and form defaults.</p>
        </div>
        <div class="dashboard-card" style="max-width: 850px;">
            <div class="form-grid">
                <div class="form-field" style="grid-column: span 2;">
                    <label>School Name</label>
                    <input type="text" id="setSchoolName"
                        value="<?= htmlspecialchars($settings['school_name'] ?? '') ?>"
                        placeholder="e.g. Mabini Elementary School">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field"><label>School ID</label><input type="text" id="setSchoolId"
                        value="<?= htmlspecialchars($settings['school_id'] ?? '') ?>"></div>
                <div class="form-field"><label>District</label><input type="text" id="setDistrict"
                        value="<?= htmlspecialchars($settings['district'] ?? '') ?>"></div>
            </div>
            <div class="form-grid">
                <div class="form-field"><label>Division/Region</label><input type="text" id="setRegion"
                        value="<?= htmlspecialchars($settings['region'] ?? '') ?>"></div>
                <div class="form-field"><label>Principal / Head</label><input type="text" id="setPrincipal"
                        value="<?= htmlspecialchars($settings['principal_name'] ?? '') ?>"></div>
            </div>
            <div class="form-field" style="margin-bottom:2rem;">
                <label>SBFP Coordinator</label>
                <input type="text" id="setCoordinator"
                    value="<?= htmlspecialchars($settings['sbfp_coordinator'] ?? '') ?>">
            </div>
            <button class="btn-m3 btn-m3-primary" style="padding: 12px 32px; border-radius: 12px;"
                onclick="saveGlobalSettings()">Update School Identity</button>
        </div>
    </div>
</div>

<script>
    // Chart Initialization
    const ctx = document.getElementById('allocationChart').getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: <?= json_encode(array_keys($categories)) ?>,
            datasets: [{
                data: <?= json_encode(array_values($categories)) ?>,
                backgroundColor: ['#0061ff', '#f59e0b', '#0ea5e9', '#64748b'],
                borderWidth: 0,
                cutout: '75%'
            }]
        },
        options: {
            plugins: { legend: { position: 'bottom', labels: { usePointStyle: true, padding: 20, font: { family: 'Outfit', weight: '600' } } } },
            maintainAspectRatio: false
        }
    });

    function switchTab(id) {
        const url = new URL(window.location);
        url.searchParams.set('tab', id);
        window.history.pushState({}, '', url);
        document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.getAttribute('data-tab') === id));
        document.querySelectorAll('.tab-content').forEach(c => c.classList.toggle('active', c.id === 'tab-' + id));
    }

    function toggleBudgetEdit() {
        const d = document.getElementById('budgetDisplay');
        const e = document.getElementById('budgetEdit');
        const isHidden = e.style.display === 'none';
        d.style.display = isHidden ? 'none' : 'block';
        e.style.display = isHidden ? 'block' : 'none';
    }

    async function toggleUserStatus(id) {
        if (!confirm('Toggle status for this account?')) return;
        try {
            const res = await fetch('api_permanent_delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: id, toggle_only: true })
            });
            const d = await res.json();
            if (d.success) location.reload();
            else alert(d.message);
        } catch (e) { alert('Error updating status.'); }
    }

    async function saveBudgetSettings() {
        const alloc = document.getElementById('setAllocBudget').value;
        const daily = <?= $daily_limit ?>; // Preserve daily limit for now
        try {
            const res = await fetch('api_save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ total_allocated_budget: alloc })
            });
            const d = await res.json();
            if (d.success) location.reload();
            else alert(d.message);
        } catch (e) { alert('Network error'); }
    }

    async function submitLog() {
        const amount = document.getElementById('logAmount').value;
        const category = document.getElementById('logCategory').value;
        const desc = document.getElementById('logDesc').value;
        if (!amount || !desc) return await AlgoModal.alert('Incomplete Form', 'Please complete the log form.');

        try {
            const res = await fetch('api_log_budget.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount, category, description: desc })
            });
            const d = await res.json();
            if (d.success) location.reload();
            else await AlgoModal.alert('Log Error', d.message);
        } catch (e) { await AlgoModal.alert('System Error', 'Error logging data.'); }
    }

    function openEditLogModal(log) {
        document.getElementById('editLogId').value = log.id;
        document.getElementById('editLogAmount').value = log.amount;
        document.getElementById('editLogCategory').value = log.category;
        document.getElementById('editLogDesc').value = log.description;
        document.getElementById('editLogModal').classList.add('active');
    }

    async function submitEditLog() {
        const log_id = document.getElementById('editLogId').value;
        const amount = document.getElementById('editLogAmount').value;
        const category = document.getElementById('editLogCategory').value;
        const description = document.getElementById('editLogDesc').value;

        if (!amount || !description) return await AlgoModal.alert('Incomplete Form', 'Please complete the log form.');

        try {
            const res = await fetch('api_edit_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log_id, amount, category, description })
            });
            const d = await res.json();
            if (d.success) location.reload();
            else await AlgoModal.alert('Edit Error', d.message);
        } catch (e) { await AlgoModal.alert('System Error', 'Error editing log.'); }
    }

    async function deleteLog(log_id) {
        if (!await AlgoModal.confirm("Delete Log", "Are you sure you want to permanently delete this expenditure log?")) return;
        try {
            const res = await fetch('api_delete_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log_id })
            });
            const d = await res.json();
            if (d.success) location.reload();
            else await AlgoModal.alert('Deletion Error', d.message || 'Failed to delete log.');
        } catch (e) { await AlgoModal.alert('System Error', 'Error deleting log.'); }
    }

    async function saveGlobalSettings() {
        const payload = {
            school_name: document.getElementById('setSchoolName').value,
            school_id: document.getElementById('setSchoolId').value,
            district: document.getElementById('setDistrict').value,
            region: document.getElementById('setRegion').value,
            principal_name: document.getElementById('setPrincipal').value,
            sbfp_coordinator: document.getElementById('setCoordinator').value
        };
        try {
            const res = await fetch('api_save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const d = await res.json();
            if (d.success) alert('Settings updated successfully!');
            else alert(d.message);
        } catch (e) { alert('Network error.'); }
    }

    async function permanentDeleteUser(id) {
        if (!confirm('CRITICAL: Permanently delete this account?')) return;
        try {
            const res = await fetch('api_permanent_delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: id })
            });
            const d = await res.json();
            if (d.success) location.reload();
            else alert(d.message);
        } catch (e) { alert('Error deleting user.'); }
    }
</script>

<?php require_once '../../includes/footer.php'; ?>

<!-- Edit Log Modal -->
<div class="modal-overlay" id="editLogModal">
    <div class="modal" style="max-width: 500px; width: 95%;">
        <h2 class="modal-title">Edit Expenditure Log</h2>
        <div>
            <input type="hidden" id="editLogId">
            <div class="form-grid">
                <div class="form-field">
                    <label>Amount (PHP)</label>
                    <input type="number" id="editLogAmount" step="0.01">
                </div>
                <div class="form-field">
                    <label>Category</label>
                    <select id="editLogCategory">
                        <option>Labor Costs</option>
                        <option>Logistics</option>
                        <option>Misc</option>
                    </select>
                </div>
            </div>
            <div class="form-field" style="margin-bottom:1.5rem;">
                <label>Description</label>
                <input type="text" id="editLogDesc">
            </div>
            <div class="modal-actions" style="display:flex; justify-content:flex-end; gap:1rem;">
                <button class="btn-m3 btn-m3-outline" onclick="document.getElementById('editLogModal').classList.remove('active')">Cancel</button>
                <button class="btn-m3 btn-m3-primary" onclick="submitEditLog()">Save Changes</button>
            </div>
        </div>
    </div>
</div>
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

// Kitchen Documentation
$docs = $conn->query("SELECT kd.*, u.faculty_name as uploader FROM kitchen_documentation kd LEFT JOIN users u ON kd.uploaded_by = u.user_id ORDER BY kd.tagged_date DESC, kd.created_at DESC");

// Fetch Users for Account Management
$users_list = $conn->query("SELECT user_id, faculty_name, role, status FROM users ORDER BY role ASC, faculty_name ASC");

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .mgmt-tabs {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 2rem;
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
        color: var(--text-muted);
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }

    .tab-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: var(--text-main);
    }

    .tab-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(0, 97, 255, 0.25);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .doc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .doc-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        transition: 0.2s;
    }

    .doc-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }

    .doc-img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        background: #f1f5f9;
        border-bottom: 1px solid var(--border);
    }

    .doc-meta {
        padding: 1rem;
    }

    .doc-date {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--primary);
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }

    .doc-desc {
        font-size: 0.85rem;
        color: var(--text-main);
        font-weight: 600;
        line-height: 1.4;
        margin-bottom: 0.5rem;
    }

    .doc-uploader {
        font-size: 0.7rem;
        color: var(--text-muted);
        border-top: 1px solid #f1f5f9;
        padding-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .attendance-grid {
        background: white;
        overflow-x: auto;
        border: 1px solid var(--border);
        border-radius: 12px;
    }

    .att-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }

    .att-table th,
    .att-table td {
        border: 1px solid var(--border);
        padding: 0.5rem;
        text-align: center;
    }

    .att-table th {
        background: #f8fafc;
        font-weight: 800;
    }

    .att-table td.name {
        text-align: left;
        font-weight: 700;
        background: #fcfcfc;
        white-space: nowrap;
    }

    .att-status-s {
        color: #10b981;
        font-weight: 900;
    }

    .att-status-a {
        color: #f43f5e;
        font-weight: 900;
    }

    @media print {
        .no-print { display: none !important; }
        .tab-content { display: block !important; }
        
        /* Attendance Roster isolation */
        body.print-roster-only .sidebar,
        body.print-roster-only .topbar,
        body.print-roster-only .mgmt-header,
        body.print-roster-only .mgmt-tabs,
        body.print-roster-only .tab-content:not(#tab-attendance) {
            display: none !important;
        }

        body.print-roster-only .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }

        /* SBFP Form Landscape enforcement */
        body.print-landscape {
            @page { size: landscape; margin: 5mm; }
        }
    }
</style>

<script>
    function printRosterIsolated() {
        document.body.classList.add('print-roster-only');
        window.print();
        setTimeout(() => { document.body.classList.remove('print-roster-only'); }, 500);
    }

    async function saveBudgetSettings() {
        const alloc = document.getElementById('setAllocBudget').value;
        const daily = document.getElementById('setDailyBudget').value;
        if (!alloc || !daily) return alert('Please fill in both budget fields.');
        try {
            const res = await fetch('api_save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ total_allocated_budget: alloc, total_daily_budget: daily })
            });
            const d = await res.json();
            if (d.success) {
                // Flash the button green briefly
                const btn = document.querySelector('[onclick="saveBudgetSettings()"]');
                btn.style.background = '#10b981';
                btn.textContent = '✓ Saved!';
                setTimeout(() => { btn.style.background = ''; btn.innerHTML = '<span class="material-icons" style="font-size:16px; vertical-align:middle;">save</span> Save Parameters'; location.reload(); }, 1200);
            } else { alert('Error: ' + d.message); }
        } catch (e) { alert('Network error saving settings.'); }
    }
</script>

<div class="content">
    <div class="mgmt-header no-print">
        <div class="mgmt-title">
            <h2>Administrative Management Hub</h2>
            <p>Fiscal control, user accounts, and system configuration tools.</p>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <button class="btn-m3 btn-m3-outline" onclick="window.print()"><span class="material-icons">print</span>
                Print Page</button>
        </div>
    </div>

    <div class="mgmt-tabs no-print">
        <button
            class="tab-btn <?= (!isset($_GET['tab']) || $_GET['tab'] == 'fiscal') ? 'active' : '' ?>"
            data-tab="fiscal" onclick="switchTab('fiscal')">FISCAL CONTROL</button>
        <button class="tab-btn <?= (isset($_GET['tab']) && $_GET['tab'] == 'users') ? 'active' : '' ?>" data-tab="users"
            onclick="switchTab('users')">USER ACCOUNTS</button>
        <button class="tab-btn <?= (isset($_GET['tab']) && $_GET['tab'] == 'settings') ? 'active' : '' ?>" data-tab="settings"
            onclick="switchTab('settings')">SYSTEM SETTINGS</button>
    </div>
    <?php 
    $activeTab = $_GET['tab'] ?? 'fiscal';
    ?>

    <!-- TAB: FISCAL -->
    <?php if ($role === 'Admin' || $role === 'Super Admin'): ?>
    <div id="tab-fiscal" class="tab-content <?= ($activeTab == 'fiscal') ? 'active' : '' ?>">
        <div class="kpi-row no-print">
            <div class="kpi-card" style="min-height: 140px;">
                <div class="kpi-label" style="display:flex; justify-content:space-between; align-items:center;">
                    Strategic Allocation
                    <button onclick="toggleBudgetEdit()" style="background:none; border:none; color:var(--primary); cursor:pointer;"><span class="material-icons" style="font-size:16px;">edit</span></button>
                </div>
                <div id="budgetDisplay">
                    <div class="kpi-value">&#8369;<?= number_format($allocated_budget, 2) ?></div>
                    <div class="kpi-subtext">Base funding cap for the school year.</div>
                </div>
                <div id="budgetEdit" style="display:none; margin-top:0.5rem;">
                    <div style="margin-bottom:0.5rem;">
                        <label style="font-size:0.65rem; color:var(--text-muted); font-weight:700;">Total Allocation</label>
                        <input type="number" id="setAllocBudget" value="<?= $allocated_budget ?>" style="width:100%; padding:0.4rem; border:1px solid var(--border); border-radius:6px; font-weight:700;">
                    </div>
                    <div style="margin-bottom:0.75rem;">
                        <label style="font-size:0.65rem; color:var(--text-muted); font-weight:700;">Daily Budget Limit</label>
                        <input type="number" id="setDailyLimit" value="<?= $daily_limit ?>" style="width:100%; padding:0.4rem; border:1px solid var(--border); border-radius:6px; font-weight:700;">
                    </div>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="btn-m3 btn-m3-primary" onclick="saveBudgetSettings()" style="padding:4px 12px; font-size:0.7rem;"><span class="material-icons" style="font-size:14px;">save</span> Save</button>
                        <button class="tab-btn" onclick="toggleBudgetEdit()" style="padding:4px 12px; font-size:0.7rem; border-radius:100px;">Cancel</button>
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
                <div class="kpi-value">&#8369;<?= number_format($remaining_funds, 2) ?></div>
                <div class="kpi-subtext">Daily Limit: &#8369;<?= number_format($daily_limit, 0) ?></div>
            </div>
        </div>

        <div class="main-grid">
            <div style="display:flex; flex-direction:column; gap:1.5rem;">
                <div class="dashboard-card no-print">
                    <div class="card-header">
                        <span class="material-icons">pie_chart</span>
                        <h3>Budget Distribution</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="allocationChart"></canvas>
                    </div>
                </div>
                <div class="dashboard-card no-print">
                    <div class="card-header"><span class="material-icons">receipt_long</span>
                        <h3>Manual Log</h3>
                    </div>
                    <div class="logging-form">
                        <div class="form-grid">
                            <div class="form-field"><label>Amount</label><input type="number" id="logAmount"
                                    placeholder="0.00"></div>
                            <div class="form-field"><label>Category</label><select id="logCategory">
                                    <option>Labor Costs</option>
                                    <option>Logistics</option>
                                    <option>Misc</option>
                                </select></div>
                        </div>
                        <div class="form-field" style="margin-bottom:1rem;"><label>Description</label><input type="text"
                                id="logDesc"></div>
                        <button class="btn-m3 btn-m3-primary" style="width:100%; border-radius: 8px;"
                            onclick="submitLog()">Record Log</button>
                    </div>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header"><span class="material-icons">history</span>
                    <h3>Expediture Ledger</h3>
                </div>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th style="text-align:right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $l = $conn->query("SELECT * FROM budget_logs ORDER BY created_at DESC LIMIT 8");
                        while ($r = $l->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('M d', strtotime($r['created_at'])) ?></td>
                                <td><span
                                        class="budget-tag tag-<?= strtolower(explode(' ', $r['category'])[0]) ?>"><?= $r['category'] ?></span>
                                </td>
                                <td style="color:var(--text-muted);"><?= $r['description'] ?></td>
                                <td style="text-align:right; font-weight:700;">&#8369;<?= number_format($r['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div> <!-- END TAB: FISCAL -->


    <!-- TAB: SETTINGS -->
    <div id="tab-settings" class="tab-content <?= ($activeTab == 'settings') ? 'active' : '' ?>">
        <div style="margin-bottom:1.5rem;">
            <h3 style="margin:0;">Global System Settings</h3>
            <p style="font-size:0.8rem; color:var(--text-muted);">Configure school identity and form defaults.</p>
        </div>
        <div class="dashboard-card" style="max-width: 800px;">
            <div class="form-grid" style="grid-template-columns: 2fr 1fr;">
                <div class="form-field">
                    <label>School Name</label>
                    <input type="text" id="setSchoolName" value="<?= $settings['school_name'] ?? '' ?>" placeholder="e.g. Mabini Elementary School">
                </div>
                <div class="form-field">
                    <label>School ID</label>
                    <input type="text" id="setSchoolId" value="<?= $settings['school_id'] ?? '' ?>" placeholder="6-digit ID">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label>District</label>
                    <input type="text" id="setDistrict" value="<?= $settings['district'] ?? '' ?>">
                </div>
                <div class="form-field">
                    <label>Division/Region</label>
                    <input type="text" id="setRegion" value="<?= $settings['region'] ?? '' ?>">
                </div>
            </div>
            <div class="form-grid">
                <div class="form-field">
                    <label>Principal / School Head</label>
                    <input type="text" id="setPrincipal" value="<?= $settings['principal_name'] ?? '' ?>">
                </div>
                <div class="form-field">
                    <label>SBFP Coordinator</label>
                    <input type="text" id="setCoordinator" value="<?= $settings['sbfp_coordinator'] ?? '' ?>">
                </div>
            </div>
            <div style="margin-top:2rem; display:flex; justify-content:flex-end;">
                <button class="btn-m3 btn-m3-primary" onclick="saveGlobalSettings()"><span class="material-icons">save</span> Save Identity Parameters</button>
            </div>
        </div>
    </div>

    <!-- TAB: USERS -->
    <div id="tab-users" class="tab-content <?= ($activeTab == 'users') ? 'active' : '' ?>">
        <div style="display: grid; grid-template-columns: 1fr 350px; gap: 2rem;">
            <!-- User List -->
            <div class="dashboard-card">
                <div class="card-header"><span class="material-icons">person</span><h3>System Personnel</h3></div>
                <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--border);">
                            <th style="text-align:left; padding:1rem;">Name / Username</th>
                            <th style="text-align:left; padding:1rem;">Access Level</th>
                            <th style="text-align:right; padding:1rem;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($u = $users_list->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding:1rem;">
                                <div style="font-weight:700; color:var(--text-main);"><?= htmlspecialchars($u['faculty_name']) ?></div>
                                <div style="font-size:0.75rem; color:var(--text-muted);">UID: #<?= $u['user_id'] ?></div>
                            </td>
                            <td style="padding:1rem;">
                                <?php 
                                    $badgeStyle = "background:#f1f5f9; color:#64748b;";
                                    if($u['role'] === 'Super Admin') $badgeStyle = "background:#eff6ff; color:#1d4ed8;";
                                    else if($u['role'] === 'Admin') $badgeStyle = "background:#fefce8; color:#a16207;";
                                ?>
                                <span style="padding:4px 10px; border-radius:100px; font-size:0.7rem; font-weight:800; text-transform:uppercase; <?= $badgeStyle ?>">
                                    <?= htmlspecialchars($u['role']) ?>
                                </span>
                                <?php if($u['status'] === 'Disabled'): ?>
                                    <span style="margin-left: 6px; padding:4px 10px; border-radius:100px; font-size:0.7rem; font-weight:800; text-transform:uppercase; background:#fee2e2; color:#b91c1c;">
                                        DISABLED
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:1rem; text-align:right;">
                                <?php if($role === 'Super Admin' && $u['user_id'] != $_SESSION['user_id']): ?>
                                    <?php if($u['status'] === 'Disabled'): ?>
                                        <button class="btn-m3 btn-m3-tonal" style="padding: 6px 12px; font-size: 0.75rem;" title="Restore Access" onclick="deleteUser(<?= $u['user_id'] ?>)">
                                            <span class="material-icons" style="font-size:14px;">how_to_reg</span> Restore Access
                                        </button>
                                    <?php else: ?>
                                        <button class="btn-m3 btn-m3-danger" style="padding: 6px 12px; font-size: 0.75rem;" title="Revoke Access" onclick="deleteUser(<?= $u['user_id'] ?>)">
                                            <span class="material-icons" style="font-size:14px;">no_accounts</span> Revoke Access
                                        </button>
                                    <?php endif; ?>
                                    <button class="btn-m3 btn-m3-outline" style="padding: 6px 12px; font-size: 0.75rem; margin-left: 0.5rem; color: #b91c1c; border-color: #fca5a5;" title="Permanent Delete" onclick="permanentDeleteUser(<?= $u['user_id'] ?>)">
                                        <span class="material-icons" style="font-size:14px;">delete_forever</span>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

            <!-- Create Account -->
            <div class="dashboard-card">
                <div class="card-header"><span class="material-icons">person_add</span><h3>Provision Account</h3></div>
                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div class="form-field">
                        <label>Full Name / Username</label>
                        <input type="text" id="newUserName" placeholder="Enter display name">
                    </div>
                    <div class="form-field">
                        <label>Initial Password</label>
                        <input type="password" id="newUserPass" placeholder="••••••••">
                    </div>
                    <div class="form-field">
                        <label>Access Role</label>
                        <select id="newUserRole">
                            <option value="Faculty">Faculty</option>
                            <?php if($role === 'Super Admin'): ?>
                                <option value="Admin">Administrator</option>
                                <option value="Super Admin">Super Administrator</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    <button class="btn-m3 btn-m3-primary" style="width:100%; margin-top:1rem;" onclick="createUser()">
                        <span class="material-icons">verified_user</span> Create Account
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- DOCUMENTATION TABS REMOVED (Moved to documentation.php) -->
</div> <!-- END .content --></div>



<script>
    function toggleBudgetEdit() {
        const d = document.getElementById('budgetDisplay');
        const e = document.getElementById('budgetEdit');
        if (d.style.display === 'none') {
            d.style.display = 'block';
            e.style.display = 'none';
        } else {
            d.style.display = 'none';
            e.style.display = 'block';
        }
    }

    async function saveBudgetSettings() {
        const alloc = document.getElementById('setAllocBudget').value;
        const daily = document.getElementById('setDailyLimit').value;
        if (!alloc || !daily) return;
        try {
            const res = await fetch('api_save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    total_allocated_budget: alloc,
                    total_daily_budget: daily
                })
            });
            const data = await res.json();
            if (data.success) location.reload();
            else alert(data.message);
        } catch (e) { alert('Failed to save settings.'); }
    }

    // Allocation Chart
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const canvas = document.getElementById('allocationChart');
            if (canvas) {
                const ctxAlloc = canvas.getContext('2d');
                new Chart(ctxAlloc, {
                    type: 'doughnut',
                    data: {
                        labels: ['Food Supplies', 'Labor Costs', 'Logistics', 'Misc'],
                        datasets: [{
                            data: [<?= $categories['Food Supplies'] ?>, <?= $categories['Labor Costs'] ?>, <?= $categories['Logistics'] ?>, <?= $categories['Misc'] ?>],
                            backgroundColor: ['#3b82f6', '#10b981', '#8b5cf6', '#f43f5e'],
                            borderWidth: 0,
                            cutout: '75%'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    }
                });
            }
        } catch (e) { console.error('Chart failed:', e); }
    });

    function switchTab(id) {
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active');
            if (b.getAttribute('data-tab') === id) b.classList.add('active');
        });
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        const target = document.getElementById('tab-' + id);
        if (target) target.classList.add('active');
    }

    function openUploadModal() {
        const body = `
            <div class="input-group"><label>Evidence Photo</label><input type="file" id="upFile" accept="image/*" style="width:100%;"></div>
            <div class="input-group"><label>Service Date</label><input type="date" id="upDate" value="<?= date('Y-m-d') ?>" style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:6px;"></div>
            <div class="input-group"><label>Context Description</label><input type="text" id="upDesc" placeholder="e.g., Preparing 150 servings of Sopas..." style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:6px;"></div>
        `;
        AlgoModal.show({
            title: 'Add Operational Evidence',
            body: body,
            footer: `<button class="btn-m3 btn-m3-outline" onclick="AlgoModal.close()">Cancel</button><button class="btn-m3 btn-m3-primary" onclick="runUpload()">Upload & Tag</button>`
        });
    }

    async function runUpload() {
        const file = document.getElementById('upFile').files[0];
        const date = document.getElementById('upDate').value;
        const desc = document.getElementById('upDesc').value;
        if (!file) return AlgoModal.alert('Missing Input', 'Please select a photo.');

        AlgoModal.close();
        const fd = new FormData();
        fd.append('photo', file);
        fd.append('tagged_date', date);
        fd.append('description', desc);

        try {
            const res = await fetch('api_upload_kitchen_v2.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) location.reload(); else AlgoModal.alert('Upload Error', data.message);
        } catch (e) { }
    }

    async function submitLog() {
        const amount = document.getElementById('logAmount').value;
        const category = document.getElementById('logCategory').value;
        const description = document.getElementById('logDesc').value;
        if (!amount || amount <= 0) return AlgoModal.alert('Invalid Amount', 'Please enter a valid amount.');
        try {
            const res = await fetch('api_log_budget.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ amount, category, description }) });
            const data = await res.json();
            if (data.success) location.reload(); else AlgoModal.alert('Error', data.message);
        } catch (e) { }
    }

    // User Management Functions
    async function createUser() {
        const name = document.getElementById('newUserName').value;
        const pass = document.getElementById('newUserPass').value;
        const role = document.getElementById('newUserRole').value;
        
        if (!name || !pass) return alert('Please enter both name and password.');

        try {
            const res = await fetch('api_add_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ faculty_name: name, password: pass, role: role })
            });
            const d = await res.json();
            if (d.success) {
                location.reload();
            } else {
                alert(d.message);
            }
        } catch (e) {
            alert('Failed to connect to server.');
        }
    }

    async function deleteUser(id) {
        if (!confirm('Are you sure you want to change the access status of this account?')) return;
        
        try {
            const res = await fetch('api_delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: id })
            });
            const d = await res.json();
            if (d.success) {
                location.reload();
            } else {
                alert(d.message);
            }
        } catch (e) {
            alert('Failed to connect to server.');
        }
    }

    async function permanentDeleteUser(id) {
        if (!confirm('CRITICAL WARNING: Are you sure you want to PERMANENTLY DELETE this account? This cannot be undone.')) return;
        
        try {
            const res = await fetch('api_permanent_delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: id })
            });
            const d = await res.json();
            if (d.success) {
                location.reload();
            } else {
                alert(d.message);
            }
        } catch (e) {
            alert('Failed to connect to server.');
        }
    }

    // Global Settings Functions
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
            if (d.success) {
                AlgoModal.alert('Settings Saved', 'School identity parameters updated successfully.');
                setTimeout(() => location.reload(), 1500);
            } else { alert(d.message); }
        } catch (e) { alert('Network error.'); }
    }

    function switchTab(id) {
        // Update URL without refreshing to persist tab on reload
        const url = new URL(window.location);
        url.searchParams.set('tab', id);
        window.history.pushState({}, '', url);

        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active');
            if (b.getAttribute('data-tab') === id) b.classList.add('active');
        });
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        const target = document.getElementById('tab-' + id);
        if (target) target.classList.add('active');
    }

</script>

<?php require_once '../../includes/footer.php'; ?>
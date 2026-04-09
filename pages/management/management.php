<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>
<?php
$page_title = 'System Management';
require_once '../../includes/topbar.php';
require_once '../../db.php';

// Fetch Settings
$res_settings = $conn->query("SELECT * FROM settings");
$settings = [];
while($row = $res_settings->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$allocated_budget = (float)($settings['total_allocated_budget'] ?? 500000.00);
$daily_limit = (float)($settings['total_daily_budget'] ?? 0);

// 1. Fetch Total Meal Cost
$res_meal_spent = $conn->query("SELECT SUM(actual_cost) as total FROM meal_plan");
$meal_spent = (float)($res_meal_spent->fetch_assoc()['total'] ?? 0);

// 2. Fetch Total Manual Logs
$res_logs_spent = $conn->query("SELECT SUM(amount) as total FROM budget_logs");
$logs_spent = (float)($res_logs_spent->fetch_assoc()['total'] ?? 0);

$total_spent = $meal_spent + $logs_spent;
$remaining_funds = $allocated_budget - $total_spent;

// 3. Fetch Category Breakdown for Chart
$categories = ['Food Supplies' => $meal_spent, 'Labor Costs' => 0, 'Logistics' => 0, 'Misc' => 0];
$res_cat = $conn->query("SELECT category, SUM(amount) as total FROM budget_logs GROUP BY category");
while($row = $res_cat->fetch_assoc()) {
    if (isset($categories[$row['category']])) {
        $categories[$row['category']] += (float)$row['total'];
    } else {
        $categories[$row['category']] = (float)$row['total'];
    }
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .mgmt-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem; }
    .mgmt-title h2 { font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin: 0; }
    .mgmt-title p { color: var(--text-muted); font-size: 0.9rem; margin: 0.25rem 0 0 0; }

    .kpi-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
    .kpi-card { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; display: flex; flex-direction: column; gap: 0.5rem; position: relative; overflow: hidden; box-shadow: var(--shadow-sm); }
    .kpi-card::after { content: 'Live'; position: absolute; top: 1rem; right: 1rem; font-size: 0.6rem; font-weight: 800; text-transform: uppercase; background: #e0f2fe; color: #0369a1; padding: 0.2rem 0.6rem; border-radius: 20px; }
    .kpi-card.warning::after { background: #fee2e2; color: #b91c1c; content: 'Low'; }
    .kpi-label { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    .kpi-value { font-size: 1.75rem; font-weight: 900; color: var(--text-main); }
    .kpi-subtext { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; }

    .main-grid { display: grid; grid-template-columns: 1fr 1.5fr; gap: 1.5rem; margin-bottom: 1.5rem; align-items: start; }
    .dashboard-card { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-sm); }
    .card-header { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border); padding-bottom: 1rem; }
    .card-header .material-icons { color: var(--primary); }
    .card-header h3 { font-size: 1.1rem; font-weight: 800; margin: 0; color: var(--text-main); }

    .logging-form { background: #f8fafc; border: 1px dashed var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 1.5rem; }
    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem; }
    .form-field label { display: block; font-size: 0.75rem; font-weight: 700; color: var(--text-main); margin-bottom: 0.4rem; }
    .form-field input, .form-field select { width: 100%; padding: 0.6rem; border: 1px solid var(--border); border-radius: 6px; font-weight: 600; }
    
    .reports-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .reports-table th { text-align: left; font-size: 0.7rem; text-transform: uppercase; color: var(--text-muted); padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); }
    .reports-table td { padding: 1rem; border-bottom: 1px solid var(--bg-color); font-size: 0.8rem; }
    .report-status { font-size: 0.7rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 4px; }
    
    .budget-tag { padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; }
    .tag-labor { background: #dcfce7; color: #166534; }
    .tag-logistics { background: #e0f2fe; color: #0369a1; }
    .tag-misc { background: #fef9c3; color: #854d0e; }
</style>

<div class="content">
    <div class="mgmt-header">
        <div class="mgmt-title">
            <h2>Budget Allocation & Control</h2>
            <p>Administer fiscal limits, track non-meal overhead, and ensure SBFP compliance.</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <button class="btn btn-outline" style="padding: 0.75rem 1.5rem;" onclick="location.reload()">
                <span class="material-icons" style="font-size:1.2rem;">sync</span> Refresh Data
            </button>
        </div>
    </div>

    <div class="kpi-row">
        <div class="kpi-card">
            <div class="kpi-label">Allocated Fiscal Budget</div>
            <div class="kpi-value">&#8369; <?= number_format($allocated_budget, 2) ?></div>
            <div class="kpi-subtext">Approved Budget for SY 2026-2027</div>
        </div>
        <div class="kpi-card">
            <div class="kpi-label">Total Cumulative Spend</div>
            <div class="kpi-value" style="color: var(--primary);">&#8369; <?= number_format($total_spent, 2) ?></div>
            <div class="kpi-subtext">Automated Meal Costs + Manual Logs</div>
        </div>
        <div class="kpi-card <?= $remaining_funds < ($allocated_budget * 0.1) ? 'warning' : '' ?>">
            <div class="kpi-label">Remaining Funds</div>
            <div class="kpi-value">&#8369; <?= number_format($remaining_funds, 2) ?></div>
            <div class="kpi-subtext">Current Liquidity for Operations</div>
        </div>
    </div>

    <div class="main-grid">
        <!-- LEFT: LOGGING & CONFIG -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <div class="dashboard-card">
                <div class="card-header">
                    <span class="material-icons">receipt_long</span>
                    <h3>Log Manual Allocation</h3>
                </div>
                <div class="logging-form">
                    <div class="form-grid">
                        <div class="form-field">
                            <label>Amount (PHP)</label>
                            <input type="number" id="logAmount" placeholder="0.00" step="0.01">
                        </div>
                        <div class="form-field">
                            <label>Category</label>
                            <select id="logCategory">
                                <option value="Labor Costs">Labor Costs</option>
                                <option value="Logistics">Logistics</option>
                                <option value="Misc">Miscellaneous</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-field" style="margin-bottom: 1rem;">
                        <label>Expenditure Description</label>
                        <input type="text" id="logDesc" placeholder="e.g., LPG refill, Kitchen staff stipend...">
                    </div>
                    <button class="btn" style="width: 100%; background: var(--text-main); color: white;" onclick="submitLog()">Record Expenditure</button>
                </div>

                <div class="card-header" style="border:none; margin-bottom: 0.5rem; padding-bottom: 0;">
                    <span class="material-icons" style="font-size: 1.1rem;">settings</span>
                    <h3 style="font-size: 0.9rem;">Quick Thresholds</h3>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1rem; background: #f1f5f9; border-radius: 8px;">
                    <div>
                        <div style="font-size: 0.75rem; font-weight: 700;">Cost-per-serving</div>
                        <div style="font-size: 0.65rem; color: var(--text-muted);">Current: &#8369;<?= number_format($daily_limit, 2) ?></div>
                    </div>
                    <button class="btn btn-outline" style="font-size: 0.7rem; padding: 0.4rem 0.8rem;">Adjust</button>
                </div>
            </div>

            <div class="dashboard-card">
                <div class="card-header">
                    <span class="material-icons">pie_chart</span>
                    <h3>Budget Allocation Analysis</h3>
                </div>
                <div style="display: flex; align-items: center; gap: 2rem;">
                    <div style="width: 160px; height: 160px;">
                        <canvas id="allocationChart"></canvas>
                    </div>
                    <div style="font-size: 0.8rem;">
                        <div style="margin-bottom: 0.5rem;"><span style="color:#3b82f6;">●</span> Food: <?= round(($categories['Food Supplies'] / max(1, $total_spent)) * 100) ?>%</div>
                        <div style="margin-bottom: 0.5rem;"><span style="color:#10b981;">●</span> Labor: <?= round(($categories['Labor Costs'] / max(1, $total_spent)) * 100) ?>%</div>
                        <div><span style="color:#8b5cf6;">●</span> Logistics: <?= round(($categories['Logistics'] / max(1, $total_spent)) * 100) ?>%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: LEDGER & REPORTS -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <div class="dashboard-card">
                <div class="card-header">
                    <span class="material-icons">history</span>
                    <h3>Recent Administrative Ledger</h3>
                </div>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th style="text-align: right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $res_ledger = $conn->query("SELECT * FROM budget_logs ORDER BY created_at DESC LIMIT 5");
                        while($row = $res_ledger->fetch_assoc()): ?>
                        <tr>
                            <td><?= date('M d', strtotime($row['created_at'])) ?></td>
                            <td><span class="budget-tag tag-<?= strtolower(explode(' ', $row['category'])[0]) ?>"><?= $row['category'] ?></span></td>
                            <td style="color: var(--text-muted);"><?= $row['description'] ?></td>
                            <td style="text-align: right; font-weight: 700;">&#8369;<?= number_format($row['amount'], 2) ?></td>
                        </tr>
                        <?php endwhile; if ($res_ledger->num_rows === 0): ?>
                            <tr><td colspan="4" style="text-align:center; color:var(--text-muted);">No manual logs found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="dashboard-card">
                <div class="card-header" style="justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.75rem;">
                        <span class="material-icons">description</span>
                        <h3>SBFP Report Suite</h3>
                    </div>
                    <button class="btn btn-outline" style="font-size: 0.7rem; padding: 0.4rem 0.8rem;">Full Archive</button>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div style="padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <span class="material-icons" style="color: #ef4444; font-size: 1.2rem;">picture_as_pdf</span>
                        <div style="font-size: 0.75rem; font-weight: 700;">SBFP Form 1</div>
                    </div>
                    <div style="padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                        <span class="material-icons" style="color: #10b981; font-size: 1.2rem;">table_view</span>
                        <div style="font-size: 0.75rem; font-weight: 700;">SBFP Form 4</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Allocation Chart
    const ctxAlloc = document.getElementById('allocationChart').getContext('2d');
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

    async function submitLog() {
        const amount = document.getElementById('logAmount').value;
        const category = document.getElementById('logCategory').value;
        const description = document.getElementById('logDesc').value;

        if (!amount || amount <= 0) {
            alert('Please enter a valid amount.');
            return;
        }

        try {
            const res = await fetch('api_log_budget.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ amount, category, description })
            });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        } catch(e) { console.error(e); }
    }
</script>

<?php require_once '../../includes/footer.php'; ?>

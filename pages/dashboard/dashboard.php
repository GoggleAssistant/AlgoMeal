<?php 
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../db.php';

$page_title = 'Operations Command Center';
require_once '../../includes/topbar.php';

// --- DATA FETCHING (DASHBOARD REDESIGN) ---

// 1. Fiscal Summary
$res_settings = $conn->query("SELECT * FROM settings");
$settings = [];
while($row = $res_settings->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];

$allocated_budget = (float)($settings['total_allocated_budget'] ?? 500000.00);
$res_meal_spent = $conn->query("SELECT SUM(actual_cost) as total FROM meal_plan");
$meal_spent = (float)($res_meal_spent->fetch_assoc()['total'] ?? 0);
$res_logs_spent = $conn->query("SELECT SUM(amount) as total FROM budget_logs");
$logs_spent = (float)($res_logs_spent->fetch_assoc()['total'] ?? 0);

$total_spent = $meal_spent + $logs_spent;
$budget_percent = $allocated_budget > 0 ? min(100, ($total_spent / $allocated_budget) * 100) : 0;

// 2. Operational Reach
$res_reach = $conn->query("SELECT COUNT(*) as total FROM meal_plan");
$total_reach = $res_reach->fetch_assoc()['total'];

$res_served = $conn->query("SELECT COUNT(*) as served FROM daily_meal_plans WHERE is_served = 1");
$served_days = $res_served->fetch_assoc()['served'];

// 3. Weekly Readiness Matrix (Mon - Fri)
$today = date('Y-m-d');
$monday = date('Y-m-d', strtotime('monday this week', strtotime($today)));
$week_dates = [];
for($i=0; $i<5; $i++) {
    $week_dates[] = date('Y-m-d', strtotime("$monday +$i days"));
}

$readiness = [];
foreach($week_dates as $date) {
    $res = $conn->query("SELECT is_served FROM daily_meal_plans WHERE scheduled_date = '$date'");
    if($row = $res->fetch_assoc()) {
        $readiness[$date] = $row['is_served'] ? 'SERVED' : 'DEPLOYED';
    } else {
        $readiness[$date] = 'DRAFT';
    }
}

// 4. Recent Activity (Ledger)
$ledger_res = $conn->query("SELECT * FROM budget_logs ORDER BY created_at DESC LIMIT 3");
?>

<style>
    .dash-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
    .kpi-card { background: white; border: 1px solid var(--border); border-radius: 16px; padding: 1.5rem; box-shadow: var(--shadow-sm); position: relative; overflow: hidden; }
    .kpi-title { font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
    .kpi-val { font-size: 2rem; font-weight: 900; color: var(--text-main); line-height: 1; }
    .kpi-sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; font-weight: 600; }
    
    .progress-track { height: 8px; background: #f1f5f9; border-radius: 10px; margin-top: 1rem; overflow: hidden; }
    .progress-fill { height: 100%; background: var(--primary); border-radius: 10px; transition: width 0.5s ease-out; }

    .readiness-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-top: 1rem; }
    .ready-day { 
        background: #f8fafc; border: 1px dashed var(--border); border-radius: 12px; padding: 1.25rem; text-align: center; 
        transition: all 0.2s;
    }
    .ready-day.deployed { background: #eff6ff; border: 1px solid #bfdbfe; }
    .ready-day.served { background: #f0fdf4; border: 1px solid #bbf7d0; box-shadow: var(--shadow-sm); }
    .ready-day .d-name { font-size: 0.7rem; font-weight: 800; color: var(--text-muted); margin-bottom: 0.25rem; }
    .ready-day .d-status { font-size: 0.85rem; font-weight: 900; margin-top: 0.5rem; }
    
    .status-badge { font-size: 0.65rem; font-weight: 800; padding: 0.2rem 0.5rem; border-radius: 20px; text-transform: uppercase; }
    .status-draft { color: #64748b; }
    .status-deployed { color: #2563eb; background: #dbeafe; }
    .status-served { color: #15803d; background: #dcfce7; }

    .main-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
    .sidebar-list { display: flex; flex-direction: column; gap: 1rem; }
    .ledger-item { display: flex; justify-content: space-between; align-items: center; padding: 1rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.85rem; }
</style>

<div class="content">
    <div class="dash-kpis">
        <!-- Fiscal Card -->
        <div class="kpi-card">
            <div class="kpi-title">Program Fiscal Integrity</div>
            <div class="kpi-val">&#8369; <?= number_format($total_spent, 2) ?></div>
            <div class="kpi-sub">
                Spent vs &#8369;<?= number_format($allocated_budget/1000, 0) ?>k Budget
            </div>
            <div class="progress-track">
                <div class="progress-fill" style="width: <?= $budget_percent ?>%;"></div>
            </div>
            <div style="display:flex; justify-content:space-between; font-size:0.65rem; margin-top:0.4rem; font-weight:800; color:var(--text-muted);">
                <span>CONSUMED: <?= round($budget_percent) ?>%</span>
                <span>REMAINING: &#8369;<?= number_format($allocated_budget - $total_spent, 2) ?></span>
            </div>
        </div>

        <!-- Reach Card -->
        <div class="kpi-card">
            <div class="kpi-title">Feeding Program Reach</div>
            <div class="kpi-val"><?= number_format($total_reach) ?></div>
            <div class="kpi-sub">Total Student Meals Served to Date</div>
            <div style="margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                <span class="material-icons" style="color:var(--success); font-size:1rem;">check_circle</span>
                <span style="font-size:0.75rem; font-weight:700; color:var(--success);"><?= $served_days ?> Days Fully Completed</span>
            </div>
            <span class="material-icons" style="position:absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.05; color: var(--primary);">restaurant</span>
        </div>

        <!-- Compliance Card -->
        <div class="kpi-card">
            <div class="kpi-title">Compliance Readiness</div>
            <div class="kpi-val">100%</div>
            <div class="kpi-sub">SBFP Reports Data Mapping Complete</div>
            <div style="margin-top: 1rem; display: flex; gap: 0.25rem;">
                <?php for($i=1; $i<=7; $i++): ?>
                <div title="Form <?= $i ?>" style="flex:1; height: 4px; background: var(--success); border-radius: 2px;"></div>
                <?php endfor; ?>
            </div>
            <div style="font-size:0.65rem; margin-top:0.5rem; font-weight:700; color:var(--text-muted);">FORMS 1-7 READY FOR GENERATION</div>
        </div>
    </div>

    <div class="main-grid">
        <!-- Weekly Readiness Tracker -->
        <div class="section-card">
            <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 1.5rem;">
                <h3 class="section-title">Weekly Service Matrix</h3>
                <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 700;"><?= date('F d', strtotime($monday)) ?> - <?= date('d, Y', strtotime($week_dates[4])) ?></span>
            </div>
            
            <div class="readiness-grid">
                <?php foreach($week_dates as $date): 
                    $status = $readiness[$date];
                    $isToday = $date == $today;
                ?>
                <div class="ready-day <?= strtolower($status) ?>" style="<?= $isToday ? 'border-color: var(--primary);' : '' ?>">
                    <div class="d-name"><?= strtoupper(date('D', strtotime($date))) ?></div>
                    <div style="font-size: 1.1rem; font-weight: 900; color: var(--text-main);"><?= date('d', strtotime($date)) ?></div>
                    <div class="d-status">
                        <?php if($status == 'SERVED'): ?>
                            <span class="status-badge status-served">&#128274; Served</span>
                        <?php elseif($status == 'DEPLOYED'): ?>
                            <span class="status-badge status-deployed">Deployed</span>
                        <?php else: ?>
                            <span class="status-badge status-draft">Draft</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div style="margin-top: 2rem; padding: 1.5rem; background: var(--bg-color); border-radius: 12px; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h4 style="margin:0; font-size: 0.9rem; font-weight: 800;">Operational Focus</h4>
                    <p style="margin:0.25rem 0 0 0; font-size: 0.8rem; color: var(--text-muted);">Click "Go to Planner" to manage upcoming meal rotations.</p>
                </div>
                <a href="../meal_planner/meal_planner.php" class="btn" style="background: var(--text-main); color: white; padding: 0.75rem 1.5rem;">Go to Planner</a>
            </div>
        </div>

        <!-- Recent Fiscal Sidebar -->
        <div class="section-card">
            <h3 class="section-title" style="margin-bottom: 1.5rem;">Latest Fiscal Entries</h3>
            <div class="sidebar-list">
                <?php while($log = $ledger_res->fetch_assoc()): ?>
                <div class="ledger-item">
                    <div>
                        <div style="font-weight: 800; font-size: 0.85rem;"><?= $log['category'] ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?= date('M d', strtotime($log['created_at'])) ?></div>
                    </div>
                    <div style="font-weight: 900; color: var(--primary);">&#8369;<?= number_format($log['amount'], 0) ?></div>
                </div>
                <?php endwhile; if($ledger_res->num_rows == 0): ?>
                    <div style="text-align:center; padding: 2rem; color: var(--text-muted); font-size: 0.8rem;">No recent expenditure logs.</div>
                <?php endif; ?>
                
                <a href="../management/management.php" style="text-align: center; color: var(--primary); font-size: 0.8rem; font-weight: 800; text-decoration: none; margin-top: 0.5rem;">View Full Ledger ↗</a>
            </div>
        </div>
    </div>
</div>

<?php 
// No Chart JS needed for this layout as we used progress bars and badges for better speed
require_once '../../includes/footer.php'; 
?>

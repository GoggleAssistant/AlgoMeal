<?php
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../db.php';

$page_title = 'Command Center';
require_once '../../includes/topbar.php';

// --- DATA FETCHING (DASHBOARD REWORK) ---
$today = date('Y-m-d');

// 1. Fiscal Summary & KPIs
$res_settings = $conn->query("SELECT * FROM settings");
$settings = [];
while ($row = $res_settings->fetch_assoc())
    $settings[$row['setting_key']] = $row['setting_value'];

$allocated_budget = (float) ($settings['total_allocated_budget'] ?? 500000.00);
$res_meal_spent = $conn->query("SELECT SUM(actual_cost) as total FROM meal_plan WHERE feeding_status IN ('Served', 'Double-Fed')");
$meal_spent = (float) ($res_meal_spent->fetch_assoc()['total'] ?? 0);
$res_logs_spent = $conn->query("SELECT SUM(amount) as total FROM budget_logs");
$logs_spent = (float) ($res_logs_spent->fetch_assoc()['total'] ?? 0);

$total_spent = $meal_spent + $logs_spent;
$budget_percent = $allocated_budget > 0 ? min(100, ($total_spent / $allocated_budget) * 100) : 0;

$res_reach = $conn->query("SELECT COUNT(*) as total FROM meal_plan WHERE feeding_status IN ('Served', 'Double-Fed')");
$total_reach = $res_reach->fetch_assoc()['total'] ?? 0;

$res_served = $conn->query("SELECT COUNT(*) as served FROM daily_meal_plans WHERE is_served = 1");
$served_days = $res_served->fetch_assoc()['served'] ?? 0;

// 2. BMI Distribution
$bmi_query = "
    SELECT 
        (SELECT height FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_height,
        (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_weight
    FROM student s
";
$bmi_res = $conn->query($bmi_query);

$bmi_stats = ['Underweight' => 0, 'Normal' => 0, 'Overweight' => 0, 'Obese' => 0];
$total_students = 0;

while ($row = $bmi_res->fetch_assoc()) {
    $h = (float) $row['current_height'];
    $w = (float) $row['current_weight'];
    if ($h > 0 && $w > 0) {
        $bmi = $w / pow($h / 100, 2);
        if ($bmi < 18.5)
            $bmi_stats['Underweight']++;
        elseif ($bmi < 25)
            $bmi_stats['Normal']++;
        elseif ($bmi < 30)
            $bmi_stats['Overweight']++;
        else
            $bmi_stats['Obese']++;
        $total_students++;
    }
}

// Ensure ratios avoid division by zero
$bmi_ratios = [];
foreach ($bmi_stats as $k => $v) {
    $bmi_ratios[$k] = $total_students > 0 ? round(($v / $total_students) * 100, 1) : 0;
}

// 3. Today's Meal
$today_meal_query = "
    SELECT dp.*, 
           rA.recipe_name as a_name, rA.hex_color as a_color, rA.energy_kcal as a_cal,
           rB.recipe_name as b_name, rB.hex_color as b_color, rB.energy_kcal as b_cal
    FROM daily_meal_plans dp
    LEFT JOIN recipes rA ON dp.meal_a_recipe_id = rA.recipe_id
    LEFT JOIN recipes rB ON dp.meal_b_recipe_id = rB.recipe_id
    WHERE dp.scheduled_date = '$today'
";
$today_meal_res = $conn->query($today_meal_query);
$today_meal = $today_meal_res ? $today_meal_res->fetch_assoc() : null;

// Get deployment counts for today's meal if it exists
if ($today_meal) {
    $cntA_res = $conn->query("SELECT COUNT(*) as c FROM meal_plan WHERE scheduled_date = '$today' AND recipe_id = '{$today_meal['meal_a_recipe_id']}'");
    $today_meal['a_count'] = $cntA_res->fetch_assoc()['c'] ?? 0;

    if ($today_meal['meal_b_recipe_id']) {
        $cntB_res = $conn->query("SELECT COUNT(*) as c FROM meal_plan WHERE scheduled_date = '$today' AND recipe_id = '{$today_meal['meal_b_recipe_id']}'");
        $today_meal['b_count'] = $cntB_res->fetch_assoc()['c'] ?? 0;
    } else {
        $today_meal['b_count'] = 0;
    }
}

// 4. Compact Calendar Month
$currYear = date('Y');
$currMonth = date('m');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currMonth, $currYear);
$firstDay = date('w', strtotime("$currYear-$currMonth-01"));

$month_plans_res = $conn->query("
    SELECT dp.scheduled_date, dp.is_served, rA.hex_color as a_color, rB.hex_color as b_color
    FROM daily_meal_plans dp
    LEFT JOIN recipes rA ON dp.meal_a_recipe_id = rA.recipe_id
    LEFT JOIN recipes rB ON dp.meal_b_recipe_id = rB.recipe_id
    WHERE dp.scheduled_date LIKE '$currYear-$currMonth-%'
");
$month_plans = [];
while ($row = $month_plans_res->fetch_assoc()) {
    $month_plans[$row['scheduled_date']] = [
        'is_served' => $row['is_served'],
        'a_color' => $row['a_color'] ?: '#3b82f6',
        'b_color' => $row['b_color'] ?: '#ef4444'
    ];
}

// 5. Recent Activity (Ledger)

$ledger_res = $conn->query("SELECT * FROM budget_logs ORDER BY created_at DESC LIMIT 4");
?>

<style>
    /* COMPACT DASHBOARD MASTER GRID */
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 1.5rem;
        padding-bottom: 3rem;
    }

    .widget-panel {
        background: white;
        border: 1px solid var(--border);
        border-radius: 16px;
        padding: 1.5rem;
        box-shadow: var(--shadow-sm);
    }

    .widget-title {
        font-size: 0.8rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.05em;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    /* Top KPIs */
    .kpi-col {
        grid-column: span 4;
    }

    .kpi-val {
        font-size: 2rem;
        font-weight: 900;
        color: var(--text-main);
        line-height: 1;
    }

    .kpi-sub {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-top: 0.5rem;
        font-weight: 600;
    }

    .progress-track {
        height: 8px;
        background: #f1f5f9;
        border-radius: 10px;
        margin-top: 1rem;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background: var(--primary);
        border-radius: 10px;
        transition: width 0.5s ease-out;
    }

    /* Today's Meal Matrix */
    .meal-col {
        grid-column: span 8;
        display: flex;
        flex-direction: column;
    }

    .meal-cards {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        flex-grow: 1;
    }

    .compact-meal-card {
        background: #f8fafc;
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
        justify-content: center;
    }

    .c-meal-label {
        font-size: 0.65rem;
        font-weight: 800;
        text-transform: uppercase;
        margin-bottom: 0.5rem;
    }

    .c-meal-name {
        font-size: 1.2rem;
        font-weight: 900;
        color: var(--text-main);
        margin-bottom: 0.25rem;
    }

    .c-meal-stats {
        display: flex;
        gap: 1rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: var(--text-muted);
        margin-top: 1rem;
        border-top: 1px solid #e2e8f0;
        padding-top: 0.75rem;
    }

    /* BMI Distribution */
    .bmi-col {
        grid-column: span 4;
    }

    .bmi-stacked-bar {
        display: flex;
        height: 16px;
        border-radius: 8px;
        overflow: hidden;
        margin-bottom: 1.5rem;
    }

    .bmi-legend {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }

    .legend-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 0.8rem;
        font-weight: 700;
    }

    .legend-color {
        width: 12px;
        height: 12px;
        border-radius: 3px;
    }

    /* Compact Calendar */
    .calendar-col {
        grid-column: span 6;
    }

    .mini-cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.4rem;
    }

    .mini-day-head {
        text-align: center;
        font-size: 0.6rem;
        font-weight: 800;
        color: var(--text-muted);
        text-transform: uppercase;
    }

    .mini-day {
        aspect-ratio: 1;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.75rem;
        font-weight: 700;
        border: 1px solid var(--border);
        background: #f8fafc;
    }

    .mini-day.deployed {
        background: #eff6ff;
        border-color: #bfdbfe;
        color: #1d4ed8;
    }

    .mini-day.served {
        background: #f0fdf4;
        border-color: #bbf7d0;
        color: #15803d;
    }

    .mini-day.today {
        box-shadow: 0 0 0 2px var(--primary);
        font-weight: 900;
    }

    .mini-day.empty {
        background: transparent;
        border: none;
    }

    /* Management Sidebar */
    .mgmt-col {
        grid-column: span 6;
    }

    .mgmt-entry {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem;
        border-bottom: 1px solid var(--border);
        font-size: 0.8rem;
    }

    .mgmt-entry:last-child {
        border-bottom: none;
    }
</style>

<div class="content">
    <div class="dashboard-grid">

        <!-- KPI Row -->
        <div class="widget-panel kpi-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">account_balance</span> Fiscal
                Integrity</div>
            <div class="kpi-val">&#8369; <?= number_format($total_spent, 2) ?></div>
            <div class="kpi-sub">Spent vs &#8369;<?= number_format($allocated_budget / 1000, 0) ?>k Budget</div>
            <div class="progress-track">
                <div class="progress-fill" style="width: <?= $budget_percent ?>%;"></div>
            </div>
        </div>

        <div class="widget-panel kpi-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">group</span> Operational
                Reach</div>
            <div class="kpi-val"><?= number_format($total_reach) ?></div>
            <div class="kpi-sub">Total Student Meals Assigned</div>
            <div
                style="margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--success); font-size: 0.8rem; font-weight: 700;">
                <span class="material-icons" style="font-size:16px;">check_circle</span> <?= $served_days ?> Days
                Successfully Served
            </div>
        </div>

        <div class="widget-panel kpi-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">account_balance_wallet</span>
                Budget Utilization</div>
            <?php
            // Temporarily ignore meal_plan cost as it was cleared for maintenance
            $res_budget = $conn->query("SELECT (SELECT setting_value FROM settings WHERE setting_key='total_allocated_budget') as alloc, (SELECT COALESCE(SUM(amount),0) FROM budget_logs) as spent");
            $brow = $res_budget->fetch_assoc();
            $alloc = (float) ($brow['alloc'] ?? 500000);
            $spent = (float) ($brow['spent'] ?? 0);
            $pct = $alloc > 0 ? round(($spent / $alloc) * 100) : 0;
            ?>
            <div class="kpi-val">&#8369; <?= number_format($alloc - $spent, 0) ?></div>
            <div class="kpi-sub">Remaining of &#8369; <?= number_format($alloc, 0) ?> Budget</div>
            <div
                style="margin-top: 1rem; background: var(--border); border-radius: 4px; height: 6px; overflow: hidden;">
                <div
                    style="height: 100%; width: <?= min(100, $pct) ?>%; background: <?= $pct > 80 ? 'var(--error)' : 'var(--primary)' ?>; border-radius: 4px;">
                </div>
            </div>
            <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.4rem;"><?= $pct ?>% utilized</div>
        </div>


        <!-- Middle Row: BMI & Today's Meal -->
        <div class="widget-panel bmi-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">monitor_weight</span> BMI Distribution</div>

            <?php if ($total_students > 0): ?>
                <div class="bmi-stacked-bar">
                    <div title="Underweight" style="width: <?= $bmi_ratios['Underweight'] ?>%; background: #fbbf24;"></div>
                    <div title="Normal" style="width: <?= $bmi_ratios['Normal'] ?>%; background: #10b981;"></div>
                    <div title="Overweight" style="width: <?= $bmi_ratios['Overweight'] ?>%; background: #f97316;"></div>
                    <div title="Obese" style="width: <?= $bmi_ratios['Obese'] ?>%; background: #ef4444;"></div>
                </div>

                <div class="bmi-legend">
                    <div class="legend-item"><div style="display:flex; align-items:center; gap:0.5rem;"><span class="legend-color" style="background:#fbbf24;"></span> Underweight</div><span><?= $bmi_ratios['Underweight'] ?>%</span></div>
                    <div class="legend-item"><div style="display:flex; align-items:center; gap:0.5rem;"><span class="legend-color" style="background:#10b981;"></span> Normal</div><span><?= $bmi_ratios['Normal'] ?>%</span></div>
                    <div class="legend-item"><div style="display:flex; align-items:center; gap:0.5rem;"><span class="legend-color" style="background:#f97316;"></span> Overweight</div><span><?= $bmi_ratios['Overweight'] ?>%</span></div>
                    <div class="legend-item"><div style="display:flex; align-items:center; gap:0.5rem;"><span class="legend-color" style="background:#ef4444;"></span> Obese</div><span><?= $bmi_ratios['Obese'] ?>%</span></div>
                </div>
            <?php else: ?>
                <div style="font-size: 0.85rem; color: var(--text-muted); text-align: center; padding: 2rem;">No nutritional data available.</div>
            <?php endif; ?>
        </div>

        <div class="widget-panel meal-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">restaurant</span> Today's Mission Portfolio</div>
            <div class="meal-cards">
                <?php if ($today_meal): ?>
                    <div class="compact-meal-card" style="border-left:5px solid <?= $today_meal['a_color'] ?>;">
                        <div class="c-meal-label" style="color:<?= $today_meal['a_color'] ?>;">Primary Deployment</div>
                        <div class="c-meal-name"><?= $today_meal['a_name'] ?></div>
                        <div class="c-meal-stats">
                            <span><span class="material-icons" style="font-size:12px;">groups</span> <?= $today_meal['a_count'] ?> Students</span>
                            <span><span class="material-icons" style="font-size:12px;">bolt</span> <?= $today_meal['a_cal'] ?> kcal</span>
                        </div>
                    </div>
                    <?php if ($today_meal['meal_b_recipe_id']): ?>
                    <div class="compact-meal-card" style="border-left:5px solid <?= $today_meal['b_color'] ?>;">
                        <div class="c-meal-label" style="color:<?= $today_meal['b_color'] ?>;">Secondary Deployment</div>
                        <div class="c-meal-name"><?= $today_meal['b_name'] ?></div>
                        <div class="c-meal-stats">
                            <span><span class="material-icons" style="font-size:12px;">groups</span> <?= $today_meal['b_count'] ?> Students</span>
                            <span><span class="material-icons" style="font-size:12px;">bolt</span> <?= $today_meal['b_cal'] ?> kcal</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="compact-meal-card" style="background:transparent; border:1px dashed var(--border); justify-content:center; align-items:center;">
                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight:700;">No Split Deployment Today</div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="grid-column: span 2; text-align:center; padding: 2rem;">
                        <span class="material-icons" style="font-size:32px; color:var(--border);">event_busy</span>
                        <div style="font-size:0.9rem; font-weight:700; color:var(--text-muted); margin-top:0.5rem;">No Plan Deployed for Today.</div>
                        <a href="../meal_planner/meal_planner.php" class="btn btn-outline" style="margin-top:1rem; display:inline-flex;">Open Planner</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom Row: Calendar Matrix & Management -->
        <div class="widget-panel calendar-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">calendar_today</span> Operational Matrix (<?= date('M Y') ?>)</div>
            <div class="mini-cal-grid">
                <?php 
                for ($i = 0; $i < $firstDay; $i++) echo '<div class="mini-day empty"></div>';
                for ($d = 1; $d <= $daysInMonth; $d++): 
                    $dateObj = "$currYear-$currMonth-" . str_padStart($d, 2, '0');
                    $plan = $month_plans[$dateObj] ?? null;
                    $class = $plan ? ($plan['is_served'] ? 'served' : 'deployed') : '';
                    $todayClass = ($dateObj === $today) ? 'today' : '';
                    $style = $plan ? "border-bottom: 3px solid {$plan['a_color']};" : "";
                ?>
                    <div class="mini-day <?= $class ?> <?= $todayClass ?>" style="<?= $style ?>"><?= $d ?></div>
                <?php endfor; ?>
            </div>
            <div style="display:flex; gap:1rem; margin-top:1.25rem; font-size:0.65rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">
                <div style="display:flex; align-items:center; gap:4px;"><span style="width:8px; height:8px; background:#eff6ff; border:1px solid #bfdbfe; border-radius:2px;"></span> Planned</div>
                <div style="display:flex; align-items:center; gap:4px;"><span style="width:8px; height:8px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:2px;"></span> Served</div>
            </div>
        </div>

        <div class="widget-panel mgmt-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">receipt_long</span> Management Ledger</div>
            <div style="flex-grow:1; display:flex; flex-direction:column;">
                <?php while ($log = $ledger_res->fetch_assoc()): ?>
                    <div class="mgmt-entry">
                        <div>
                            <div style="font-weight: 800; color: var(--text-main);"><?= $log['category'] ?></div>
                            <div style="font-size: 0.7rem; color: var(--text-muted);"><?= date('M d', strtotime($log['created_at'])) ?> &middot; Ref: <?= substr(md5($log['id']), 0, 4) ?></div>
                        </div>
                        <div style="font-weight: 900; color: var(--primary);">&#8369;<?= number_format($log['amount'], 0) ?></div>
                    </div>
                <?php endwhile; if ($ledger_res->num_rows == 0): ?>
                    <div style="text-align:center; color: var(--text-muted); font-size: 0.8rem; margin: 2rem 0;">No administrative logs.</div>
                <?php endif; ?>
            </div>
            <a href="../management/management.php" style="display:block; text-align:center; padding-top:1rem; font-weight:800; font-size:0.75rem; color:var(--primary); text-decoration:none;">Open Management Portfolio ↗</a>
        </div>

    </div>
</div>

<?php 
function str_padStart($str, $len, $char) {
    return str_pad($str, $len, $char, STR_PAD_LEFT);
}
require_once '../../includes/footer.php'; ?>
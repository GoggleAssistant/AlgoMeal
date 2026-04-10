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
while($row = $res_settings->fetch_assoc()) $settings[$row['setting_key']] = $row['setting_value'];

$allocated_budget = (float)($settings['total_allocated_budget'] ?? 500000.00);
$res_meal_spent = $conn->query("SELECT SUM(actual_cost) as total FROM meal_plan");
$meal_spent = (float)($res_meal_spent->fetch_assoc()['total'] ?? 0);
$res_logs_spent = $conn->query("SELECT SUM(amount) as total FROM budget_logs");
$logs_spent = (float)($res_logs_spent->fetch_assoc()['total'] ?? 0);

$total_spent = $meal_spent + $logs_spent;
$budget_percent = $allocated_budget > 0 ? min(100, ($total_spent / $allocated_budget) * 100) : 0;

$res_reach = $conn->query("SELECT COUNT(*) as total FROM meal_plan");
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

while($row = $bmi_res->fetch_assoc()) {
    $h = (float)$row['current_height'];
    $w = (float)$row['current_weight'];
    if ($h > 0 && $w > 0) {
        $bmi = $w / pow($h / 100, 2);
        if ($bmi < 18.5) $bmi_stats['Underweight']++;
        elseif ($bmi < 25) $bmi_stats['Normal']++;
        elseif ($bmi < 30) $bmi_stats['Overweight']++;
        else $bmi_stats['Obese']++;
        $total_students++;
    }
}

// Ensure ratios avoid division by zero
$bmi_ratios = [];
foreach($bmi_stats as $k => $v) {
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
while($row = $month_plans_res->fetch_assoc()) {
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
    .widget-title { font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }

    /* Top KPIs */
    .kpi-col { grid-column: span 4; }
    .kpi-val { font-size: 2rem; font-weight: 900; color: var(--text-main); line-height: 1; }
    .kpi-sub { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.5rem; font-weight: 600; }
    
    .progress-track { height: 8px; background: #f1f5f9; border-radius: 10px; margin-top: 1rem; overflow: hidden; }
    .progress-fill { height: 100%; background: var(--primary); border-radius: 10px; transition: width 0.5s ease-out; }

    /* Today's Meal Matrix */
    .meal-col { grid-column: span 8; display:flex; flex-direction: column; }
    .meal-cards { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; flex-grow: 1; }
    .compact-meal-card { background: #f8fafc; border: 1px solid var(--border); border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: center;}
    .c-meal-label { font-size: 0.65rem; font-weight: 800; text-transform: uppercase; margin-bottom: 0.5rem; }
    .c-meal-name { font-size: 1.2rem; font-weight: 900; color: var(--text-main); margin-bottom: 0.25rem; }
    .c-meal-stats { display: flex; gap: 1rem; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); margin-top: 1rem; border-top: 1px solid #e2e8f0; padding-top: 0.75rem; }

    /* BMI Distribution */
    .bmi-col { grid-column: span 4; }
    .bmi-stacked-bar { display: flex; height: 16px; border-radius: 8px; overflow: hidden; margin-bottom: 1.5rem; }
    .bmi-legend { display: grid; grid-template-columns: 1fr; gap: 0.75rem; }
    .legend-item { display: flex; justify-content: space-between; align-items: center; font-size: 0.8rem; font-weight: 700; }
    .legend-color { width: 12px; height: 12px; border-radius: 3px; }

    /* Compact Calendar */
    .calendar-col { grid-column: span 6; }
    .mini-cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.4rem; }
    .mini-day-head { text-align: center; font-size: 0.6rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; }
    .mini-day { aspect-ratio: 1; border-radius: 6px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 700; border: 1px solid var(--border); background: #f8fafc; }
    .mini-day.deployed { background: #eff6ff; border-color: #bfdbfe; color: #1d4ed8; }
    .mini-day.served { background: #f0fdf4; border-color: #bbf7d0; color: #15803d; }
    .mini-day.today { box-shadow: 0 0 0 2px var(--primary); font-weight: 900; }
    .mini-day.empty { background: transparent; border: none; }

    /* Management Sidebar */
    .mgmt-col { grid-column: span 6; }
    .mgmt-entry { display: flex; justify-content: space-between; align-items: center; padding: 0.75rem; border-bottom: 1px solid var(--border); font-size: 0.8rem; }
    .mgmt-entry:last-child { border-bottom: none; }
</style>

<div class="content">
    <div class="dashboard-grid">
        
        <!-- KPI Row -->
        <div class="widget-panel kpi-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">account_balance</span> Fiscal Integrity</div>
            <div class="kpi-val">&#8369; <?= number_format($total_spent, 2) ?></div>
            <div class="kpi-sub">Spent vs &#8369;<?= number_format($allocated_budget/1000, 0) ?>k Budget</div>
            <div class="progress-track"><div class="progress-fill" style="width: <?= $budget_percent ?>%;"></div></div>
        </div>

        <div class="widget-panel kpi-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">group</span> Operational Reach</div>
            <div class="kpi-val"><?= number_format($total_reach) ?></div>
            <div class="kpi-sub">Total Student Meals Assigned</div>
            <div style="margin-top: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--success); font-size: 0.8rem; font-weight: 700;">
                <span class="material-icons" style="font-size:16px;">check_circle</span> <?= $served_days ?> Days Successfully Served
            </div>
        </div>

        <div class="widget-panel kpi-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">account_balance_wallet</span> Budget Utilization</div>
            <?php
            $res_budget = $conn->query("SELECT (SELECT setting_value FROM settings WHERE setting_key='total_allocated_budget') as alloc, (SELECT COALESCE(SUM(actual_cost),0) FROM meal_plan) + (SELECT COALESCE(SUM(amount),0) FROM budget_logs) as spent");
            $brow = $res_budget->fetch_assoc();
            $alloc = (float)($brow['alloc'] ?? 500000);
            $spent = (float)($brow['spent'] ?? 0);
            $pct = $alloc > 0 ? round(($spent / $alloc) * 100) : 0;
            ?>
            <div class="kpi-val">&#8369; <?= number_format($alloc - $spent, 0) ?></div>
            <div class="kpi-sub">Remaining of &#8369; <?= number_format($alloc, 0) ?> Budget</div>
            <div style="margin-top: 1rem; background: var(--border); border-radius: 4px; height: 6px; overflow: hidden;">
                <div style="height: 100%; width: <?= min(100, $pct) ?>%; background: <?= $pct > 80 ? 'var(--error)' : 'var(--primary)' ?>; border-radius: 4px;"></div>
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
                <div class="legend-item">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span class="legend-color" style="background:#fbbf24;"></span> Underweight
                    </div>
                    <span><?= $bmi_ratios['Underweight'] ?>%</span>
                </div>
                <div class="legend-item">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span class="legend-color" style="background:#10b981;"></span> Normal
                    </div>
                    <span><?= $bmi_ratios['Normal'] ?>%</span>
                </div>
                <div class="legend-item">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span class="legend-color" style="background:#f97316;"></span> Overweight
                    </div>
                    <span><?= $bmi_ratios['Overweight'] ?>%</span>
                </div>
                <div class="legend-item">
                    <div style="display:flex; align-items:center; gap:0.5rem;">
                        <span class="legend-color" style="background:#ef4444;"></span> Obese
                    </div>
                    <span><?= $bmi_ratios['Obese'] ?>%</span>
                </div>
            </div>
            <?php else: ?>
                <div style="font-size: 0.8rem; color: var(--text-muted); text-align: center; margin-top: 2rem;">No assessment data available.</div>
            <?php endif; ?>
        </div>

        <div class="widget-panel meal-col">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1rem;">
                <div class="widget-title" style="margin:0;"><span class="material-icons" style="font-size:16px;">restaurant_menu</span> Today's Mission</div>
                <div style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted);"><?= date('F j, Y') ?></div>
            </div>

            <?php if ($today_meal): ?>
            <div class="meal-cards">
                <!-- Meal A -->
                <div class="compact-meal-card" style="border-top: 4px solid <?= $today_meal['a_color'] ?? 'var(--primary)' ?>;">
                    <div class="c-meal-label" style="color: <?= $today_meal['a_color'] ?? 'var(--primary)' ?>">Primary Meal</div>
                    <div class="c-meal-name"><?= $today_meal['a_name'] ?></div>
                    <div class="c-meal-stats">
                        <div><span class="material-icons" style="font-size:12px; vertical-align:middle; color:#f97316;">local_fire_department</span> <?= $today_meal['a_cal'] ?> kcal</div>
                        <div><span class="material-icons" style="font-size:12px; vertical-align:middle;">groups</span> <?= $today_meal['a_count'] ?> assigned</div>
                    </div>
                </div>

                <!-- Meal B -->
                <?php if ($today_meal['meal_b_recipe_id']): ?>
                <div class="compact-meal-card" style="border-top: 4px solid <?= $today_meal['b_color'] ?? 'var(--error)' ?>;">
                    <div class="c-meal-label" style="color: <?= $today_meal['b_color'] ?? 'var(--error)' ?>">Secondary / Alternative</div>
                    <div class="c-meal-name"><?= $today_meal['b_name'] ?></div>
                    <div class="c-meal-stats">
                        <div><span class="material-icons" style="font-size:12px; vertical-align:middle; color:#f97316;">local_fire_department</span> <?= $today_meal['b_cal'] ?> kcal</div>
                        <div><span class="material-icons" style="font-size:12px; vertical-align:middle;">groups</span> <?= $today_meal['b_count'] ?> assigned</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="compact-meal-card" style="border: 1px dashed var(--border); background: transparent; align-items:center;">
                    <div style="color: var(--text-muted); font-size: 0.8rem; font-weight: 700;">No Alternative Meal Deployed</div>
                </div>
                <?php endif; ?>
            </div>
            
            <div style="margin-top: 1rem; border-radius: 8px; padding: 0.75rem 1rem; background: <?= $today_meal['is_served'] ? '#f0fdf4' : '#eff6ff' ?>; color: <?= $today_meal['is_served'] ? '#15803d' : '#1d4ed8' ?>; font-weight: 800; font-size: 0.85rem; display:flex; align-items:center; gap:0.5rem;">
                <span class="material-icons" style="font-size:16px;"><?= $today_meal['is_served'] ? 'check_circle' : 'pending_actions' ?></span>
                Status: <?= $today_meal['is_served'] ? 'Verified & Served' : 'Pending Preparation' ?>
            </div>

            <?php else: ?>
            <div style="flex-grow:1; display:flex; align-items:center; justify-content:center; flex-direction:column; gap:1rem; border: 2px dashed var(--border); border-radius: 12px; background: #fafafa;">
                <div style="font-size: 0.9rem; font-weight: 800; color: var(--text-muted);">No meals assigned for today.</div>
                <a href="../meal_planner/meal_planner.php" class="btn" style="background: var(--text-main); color: white; padding: 0.5rem 1rem;">Open Meal Planner</a>
            </div>
            <?php endif; ?>
        </div>

        <!-- Bottom Row: Calendar Matrix & Management -->
        <div class="widget-panel calendar-col">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">calendar_month</span> Deployment Matrix <span style="margin-left:auto; font-size:0.7rem; color:var(--text-muted); font-weight:800;"><?= date('M Y') ?></span></div>
            <div class="mini-cal-grid">
                <div class="mini-day-head">Su</div><div class="mini-day-head">Mo</div><div class="mini-day-head">Tu</div>
                <div class="mini-day-head">We</div><div class="mini-day-head">Th</div><div class="mini-day-head">Fr</div><div class="mini-day-head">Sa</div>
                
                <?php 
                for ($i = 0; $i < $firstDay; $i++) echo '<div class="mini-day empty"></div>';
                for ($i = 1; $i <= $daysInMonth; $i++) {
                    $dStr = sprintf("%s-%s-%02d", $currYear, $currMonth, $i);
                    $cls = '';
                    $style = '';
                    if (isset($month_plans[$dStr])) {
                        $p = $month_plans[$dStr];
                        $cls = $p['is_served'] ? 'served' : 'deployed';
                        $c1 = $p['a_color'];
                        $c2 = $p['b_color'];
                        
                        if ($p['is_served']) {
                            $style = "background: linear-gradient(135deg, {$c1}44 50%, {$c2}44 50%); border: 1px solid {$c1}; box-shadow: inset 0 0 0 2px rgba(21, 128, 61, 0.2);";
                        } else {
                            $style = "background: linear-gradient(135deg, {$c1}22 50%, {$c2}22 50%); border: 1px dashed {$c1};";
                        }
                    }
                    if ($dStr === $today) $cls .= ' today';
                    echo "<div class='mini-day $cls' style='$style'>$i</div>";
                }
                ?>
            </div>
            <div style="display:flex; justify-content:center; gap:1rem; margin-top:1rem; font-size:0.65rem; font-weight:800; color:var(--text-muted); text-transform:uppercase;">
                <div style="display:flex; align-items:center; gap:0.25rem;"><span style="width:8px; height:8px; background:#bbf7d0; border-radius:2px; border: 1px solid #15803d;"></span> Served & Verified</div>
                <div style="display:flex; align-items:center; gap:0.25rem;"><span style="width:8px; height:8px; background:#e2e8f0; border-radius:2px; border: 1px dashed var(--text-muted);"></span> Deployed</div>
            </div>

        </div>

        <div class="widget-panel mgmt-col" style="display:flex; flex-direction:column;">
            <div class="widget-title"><span class="material-icons" style="font-size:16px;">receipt_long</span> Management Ledger</div>
            <div style="flex-grow:1; display:flex; flex-direction:column; justify-content:center;">
                <?php while($log = $ledger_res->fetch_assoc()): ?>
                <div class="mgmt-entry">
                    <div>
                        <div style="font-weight: 800; color: var(--text-main);"><?= $log['category'] ?></div>
                        <div style="font-size: 0.7rem; color: var(--text-muted);"><?= date('M d, Y', strtotime($log['created_at'])) ?> &middot; Ref: <?= substr(md5($log['id']), 0, 6) ?></div>
                    </div>
                    <div style="font-weight: 900; color: var(--primary);">&#8369;<?= number_format($log['amount'], 2) ?></div>
                </div>
                <?php endwhile; if($ledger_res->num_rows == 0): ?>
                    <div style="text-align:center; color: var(--text-muted); font-size: 0.8rem; font-weight:700;">No recent expenditures.</div>
                <?php endif; ?>
            </div>
            <a href="../management/management.php" style="display:block; text-align:center; padding-top:1rem; font-weight:800; font-size:0.75rem; color:var(--primary); text-decoration:none;">Open Management Portfolio ↗</a>
        </div>

    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

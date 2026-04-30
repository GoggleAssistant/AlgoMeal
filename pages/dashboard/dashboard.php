<?php
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../db.php';

$page_title = 'Command Center';
require_once '../../includes/topbar.php';

// --- DATA FETCHING (DASHBOARD REWORK) ---
$today = date('Y-m-d');

// 1. Fiscal Summary
$res_settings = $conn->query("SELECT * FROM settings");
$settings = [];
while ($row = $res_settings->fetch_assoc())
    $settings[$row['setting_key']] = $row['setting_value'];

$allocated_budget = (float) ($settings['total_allocated_budget'] ?? 500000.00);

// Only count as spent if the daily plan is served
$query_spent = "
    SELECT SUM(mp.actual_cost) as total 
    FROM meal_plan mp 
    JOIN daily_meal_plans dmp ON mp.scheduled_date = dmp.scheduled_date 
    WHERE dmp.is_served = 1 AND mp.feeding_status IN ('Served', 'Double-Fed')
";
$res_meal_spent = $conn->query($query_spent);
$meal_spent = (float) ($res_meal_spent->fetch_assoc()['total'] ?? 0);

// Predicted spend: not served yet, and date is today or in the future
$query_predicted = "
    SELECT SUM(mp.actual_cost) as total 
    FROM meal_plan mp 
    JOIN daily_meal_plans dmp ON mp.scheduled_date = dmp.scheduled_date 
    WHERE dmp.is_served = 0 AND mp.scheduled_date >= CURDATE()
";
$res_meal_predicted = $conn->query($query_predicted);
$meal_predicted = (float) ($res_meal_predicted->fetch_assoc()['total'] ?? 0);

$res_logs_spent = $conn->query("SELECT SUM(amount) as total FROM budget_logs");
$logs_spent = (float) ($res_logs_spent->fetch_assoc()['total'] ?? 0);

$total_spent = $meal_spent + $logs_spent;
$total_projected = $total_spent + $meal_predicted;
$budget_percent = $allocated_budget > 0 ? min(100, ($total_spent / $allocated_budget) * 100) : 0;
$predicted_percent = $allocated_budget > 0 ? min(100, ($meal_predicted / $allocated_budget) * 100) : 0;

// 2. Operational KPIs
$res_total_students = $conn->query("SELECT COUNT(*) as total FROM student WHERE is_enrolled = 1");
$total_enrolled = $res_total_students->fetch_assoc()['total'] ?? 0;

$res_reach = $conn->query("SELECT COUNT(*) as total FROM meal_plan WHERE feeding_status IN ('Served', 'Double-Fed')");
$total_reach = $res_reach->fetch_assoc()['total'] ?? 0;

// 3. Recovery Progress (Weight Gain)
$recovery_query = "
    SELECT COUNT(*) as gained FROM (
        SELECT student_id,
               (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC LIMIT 1) as latest,
               (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date ASC LIMIT 1) as initial
        FROM student s
        WHERE is_enrolled = 1
    ) t WHERE latest > initial
";
$res_recovery = $conn->query($recovery_query);
$students_gaining = $res_recovery->fetch_assoc()['gained'] ?? 0;
$recovery_percent = $total_enrolled > 0 ? round(($students_gaining / $total_enrolled) * 100) : 0;

// 4. BMI Distribution
$bmi_query = "
    SELECT 
        (SELECT height FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_height,
        (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_weight
    FROM student s WHERE is_enrolled = 1
";
$bmi_res = $conn->query($bmi_query);

$bmi_stats = ['Underweight' => 0, 'Normal' => 0, 'Overweight' => 0, 'Obese' => 0];
$total_vitals = 0;

while ($row = $bmi_res->fetch_assoc()) {
    $h = (float) $row['current_height'];
    $w = (float) $row['current_weight'];
    if ($h > 0 && $w > 0) {
        $bmi = $w / pow($h / 100, 2);
        if ($bmi < 18.5) $bmi_stats['Underweight']++;
        elseif ($bmi < 25) $bmi_stats['Normal']++;
        elseif ($bmi < 30) $bmi_stats['Overweight']++;
        else $bmi_stats['Obese']++;
        $total_vitals++;
    }
}
$bmi_ratios = [];
foreach ($bmi_stats as $k => $v) {
    $bmi_ratios[$k] = $total_vitals > 0 ? round(($v / $total_vitals) * 100, 1) : 0;
}

// 5. Today's Meal Matrix
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

// 6. Ledger & Calendar (Compact)
$ledger_res = $conn->query("SELECT * FROM budget_logs ORDER BY created_at DESC LIMIT 5");

$currYear = date('Y');
$currMonth = date('m');
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currMonth, $currYear);
$firstDay = date('w', strtotime("$currYear-$currMonth-01"));
$month_plans_res = $conn->query("SELECT scheduled_date, is_served FROM daily_meal_plans WHERE scheduled_date LIKE '$currYear-$currMonth-%'");
$month_plans = [];
while ($row = $month_plans_res->fetch_assoc()) $month_plans[$row['scheduled_date']] = $row['is_served'];

?>

<style>
    .dashboard-container {
        display: grid;
        grid-template-columns: repeat(12, 1fr);
        gap: 1.75rem;
        padding: 0.5rem 0 3rem;
    }

    .glass-widget {
        background: white;
        border-radius: 28px;
        padding: 2rem;
        border: 1.5px solid rgba(0,0,0,0.04);
        box-shadow: 0 10px 30px -5px rgba(0,0,0,0.03);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .glass-widget:hover {
        transform: translateY(-4px);
        box-shadow: 0 20px 40px -10px rgba(0,0,0,0.06);
    }

    .section-title {
        font-size: 0.75rem;
        font-weight: 900;
        text-transform: uppercase;
        letter-spacing: 0.075em;
        color: #94a3b8;
        margin-bottom: 1.5rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    /* KPI Cards */
    .kpi-card { grid-column: span 4; }
    .kpi-title { font-size: 1.1rem; font-weight: 800; color: #1e293b; margin-bottom: 0.25rem; }
    .kpi-value { font-size: 2.25rem; font-weight: 950; color: #0061ff; letter-spacing: -0.04em; line-height: 1.2; }
    .kpi-trend { font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 0.25rem; margin-top: 0.5rem; }

    /* Progress Rails */
    .progress-rail { height: 10px; background: #f1f5f9; border-radius: 20px; margin-top: 1.5rem; overflow: hidden; position: relative; }
    .progress-bar { height: 100%; border-radius: 20px; transition: width 1s cubic-bezier(0.34, 1.56, 0.64, 1); }

    /* Meal Matrix */
    .meal-matrix { grid-column: span 8; }
    .meal-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.25rem; }
    .meal-item { background: #f8fafc; padding: 1.5rem; border-radius: 20px; border: 1.5px solid #f1f5f9; position: relative; overflow: hidden; }
    .meal-item::before { content: ''; position: absolute; left: 0; top: 0; bottom: 0; width: 6px; background: var(--m-color); }
    .meal-type { font-size: 0.65rem; font-weight: 900; color: var(--m-color); text-transform: uppercase; margin-bottom: 0.5rem; }
    .meal-name { font-size: 1.25rem; font-weight: 900; color: #1e293b; margin-bottom: 1rem; }
    .meal-stats { display: flex; gap: 1.25rem; }
    .stat-pill { display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; font-weight: 700; color: #64748b; background: white; padding: 0.4rem 0.8rem; border-radius: 10px; border: 1px solid #e2e8f0; }

    /* BMI Intelligence */
    .bmi-hub { grid-column: span 4; }
    .bmi-bar-stack { height: 14px; border-radius: 10px; overflow: hidden; display: flex; margin-bottom: 1.75rem; background: #f1f5f9; }
    .bmi-segment { height: 100%; transition: width 0.6s ease; }
    .bmi-list { display: grid; grid-template-columns: 1fr; gap: 0.85rem; }
    .bmi-row { display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 700; }
    .bmi-dot { width: 10px; height: 10px; border-radius: 50%; }

    /* Operational Calendar & Ledger */
    .cal-hub { grid-column: span 6; }
    .ledger-hub { grid-column: span 6; }

    .mini-calendar { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; }
    .cal-day { aspect-ratio: 1; border-radius: 12px; border: 1.5px solid #f1f5f9; background: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; color: #64748b; position: relative; }
    .cal-day.active { background: #eff6ff; border-color: #3b82f6; color: #1e40af; }
    .cal-day.served { background: #f0fdf4; border-color: #10b981; color: #065f46; }
    .cal-day.today { border: 2.5px solid #0061ff; color: #0061ff; box-shadow: 0 0 15px rgba(0, 97, 255, 0.15); z-index: 2; }
    
    .ledger-row { display: flex; justify-content: space-between; align-items: center; padding: 1rem 0; border-bottom: 1px solid #f1f5f9; }
    .ledger-row:last-child { border-bottom: none; }
</style>

<div class="content">
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom: 1.5rem; padding: 0 1rem;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 900; margin:0; color: var(--text-main);">Command Center</h2>
            <div style="font-size: 0.9rem; font-weight: 700; color: var(--text-muted); margin-top: 0.25rem;">
                <?= date('l, F j, Y') ?>
            </div>
        </div>
    </div>
    <div class="dashboard-container">
        <!-- KPI 1: Fiscal Integrity -->
        <div class="glass-widget kpi-card">
            <div class="section-title"><span class="material-icons" style="font-size:14px;">account_balance</span> FISCAL CORE</div>
            <div class="kpi-title">Allocated Budget</div>
            <div class="kpi-value">&#8369;<?= number_format($allocated_budget, 0) ?></div>
            <div class="kpi-trend" style="color: #64748b; font-size: 0.75rem; margin-top: 4px;">
                <span style="color:#0061ff; font-weight:800;">&#8369;<?= number_format($total_spent, 0) ?> Spent</span> &bull; 
                <span style="color:#f59e0b; font-weight:800;">&#8369;<?= number_format($meal_predicted, 0) ?> Predicted</span>
            </div>
            <div class="progress-rail" style="display:flex; overflow:hidden;">
                <div class="progress-bar" style="width: <?= $budget_percent ?>%; background: linear-gradient(90deg, #0061ff, #60a5fa); border-radius:0;"></div>
                <div class="progress-bar" style="width: <?= $predicted_percent ?>%; background: repeating-linear-gradient(45deg, #fcd34d, #fcd34d 5px, #fbbf24 5px, #fbbf24 10px); border-radius:0;"></div>
            </div>
        </div>

        <!-- KPI 2: Recovery Momentum -->
        <div class="glass-widget kpi-card">
            <div class="section-title"><span class="material-icons" style="font-size:14px;">show_chart</span> RECOVERY PROGRESS</div>
            <div class="kpi-title">Weight Gain Momentum</div>
            <div class="kpi-value"><?= $recovery_percent ?>%</div>
            <div class="kpi-trend" style="color: #10b981;">
                <span class="material-icons" style="font-size:16px;">groups</span> <?= $students_gaining ?> Recovery Cases
            </div>
            <div class="progress-rail">
                <div class="progress-bar" style="width: <?= $recovery_percent ?>%; background: linear-gradient(90deg, #10b981, #34d399);"></div>
            </div>
        </div>

        <!-- KPI 3: Operational Reach -->
        <div class="glass-widget kpi-card">
            <div class="section-title"><span class="material-icons" style="font-size:14px;">groups</span> OPERATIONS</div>
            <div class="kpi-title">Total Active Enrollees</div>
            <div class="kpi-value"><?= number_format($total_enrolled) ?></div>
            <div class="kpi-trend" style="color: #6366f1;">
                <span class="material-icons" style="font-size:16px;">restaurant</span> <?= number_format($total_reach) ?> Meals Assigned
            </div>
            <div class="progress-rail">
                <div class="progress-bar" style="width: 100%; background: linear-gradient(90deg, #6366f1, #818cf8);"></div>
            </div>
        </div>

        <!-- Today's Meal Portfolio -->
        <div class="glass-widget meal-matrix">
            <div class="section-title"><span class="material-icons" style="font-size:14px;">restaurant_menu</span> MEAL OF THE DAY</div>
            <div class="meal-grid">
                <?php if ($today_meal): ?>
                    <div class="meal-item" style="--m-color: #0061ff;">
                        <div class="meal-type">Primary Deployment</div>
                        <div class="meal-name"><?= $today_meal['a_name'] ?></div>
                        <div class="meal-stats">
                            <div class="stat-pill"><span class="material-icons" style="font-size:14px;">bolt</span> <?= $today_meal['a_cal'] ?> kcal</div>
                        </div>
                    </div>
                    <?php if ($today_meal['meal_b_recipe_id']): ?>
                    <div class="meal-item" style="--m-color: #f97316;">
                        <div class="meal-type">Secondary Deployment</div>
                        <div class="meal-name"><?= $today_meal['b_name'] ?></div>
                        <div class="meal-stats">
                            <div class="stat-pill"><span class="material-icons" style="font-size:14px;">bolt</span> <?= $today_meal['b_cal'] ?> kcal</div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="meal-item" style="display:flex; align-items:center; justify-content:center; border: 2px dashed #e2e8f0; background: transparent;">
                        <div style="text-align:center; color: #94a3b8;">
                            <span class="material-icons" style="font-size:24px; margin-bottom:0.5rem;">add_circle_outline</span>
                            <div style="font-size:0.75rem; font-weight:800;">SINGLE DEPLOYMENT</div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div style="grid-column: span 2; padding: 3rem; text-align:center; background: #f8fafc; border-radius: 20px;">
                        <span class="material-icons" style="font-size:48px; color: #cbd5e1; margin-bottom: 1rem;">event_busy</span>
                        <div style="font-weight:900; font-size:1.1rem; color: #64748b;">No Active Deployment</div>
                        <div style="font-size:0.85rem; color: #94a3b8; margin-top:0.25rem;">Initiate planning in the Operational Planner.</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- BMI Intelligence -->
        <div class="glass-widget bmi-hub">
            <div class="section-title"><span class="material-icons" style="font-size:14px;">biotech</span> NUTRITIONAL INTEL</div>
            <?php if ($total_vitals > 0): ?>
            <div class="bmi-bar-stack">
                <div class="bmi-segment" style="width: <?= $bmi_ratios['Underweight'] ?>%; background: #fbbf24;"></div>
                <div class="bmi-segment" style="width: <?= $bmi_ratios['Normal'] ?>%; background: #10b981;"></div>
                <div class="bmi-segment" style="width: <?= $bmi_ratios['Overweight'] ?>%; background: #f97316;"></div>
                <div class="bmi-segment" style="width: <?= $bmi_ratios['Obese'] ?>%; background: #ef4444;"></div>
            </div>
            <div class="bmi-list">
                <div class="bmi-row">
                    <div style="display:flex; align-items:center; gap:0.75rem;"><div class="bmi-dot" style="background:#fbbf24;"></div> Underweight</div>
                    <div style="color:#1e293b;"><?= $bmi_ratios['Underweight'] ?>%</div>
                </div>
                <div class="bmi-row">
                    <div style="display:flex; align-items:center; gap:0.75rem;"><div class="bmi-dot" style="background:#10b981;"></div> Normal</div>
                    <div style="color:#1e293b;"><?= $bmi_ratios['Normal'] ?>%</div>
                </div>
                <div class="bmi-row">
                    <div style="display:flex; align-items:center; gap:0.75rem;"><div class="bmi-dot" style="background:#f97316;"></div> Overweight</div>
                    <div style="color:#1e293b;"><?= $bmi_ratios['Overweight'] ?>%</div>
                </div>
                <div class="bmi-row">
                    <div style="display:flex; align-items:center; gap:0.75rem;"><div class="bmi-dot" style="background:#ef4444;"></div> Obese</div>
                    <div style="color:#1e293b;"><?= $bmi_ratios['Obese'] ?>%</div>
                </div>
            </div>
            <?php else: ?>
                <div style="text-align:center; padding: 2rem; color: #94a3b8; font-size:0.85rem; font-weight:700;">Awaiting Diagnostic Data</div>
            <?php endif; ?>
        </div>

        <!-- Operational Matrix -->
        <div class="glass-widget cal-hub">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
                <div class="section-title" style="margin-bottom:0;"><span class="material-icons" style="font-size:14px; vertical-align:middle;">calendar_month</span> OPERATIONAL MATRIX</div>
                <div style="font-weight:900; color:#1e293b; font-size:0.9rem;"><?= date('F Y') ?></div>
            </div>
            <div class="mini-calendar" style="margin-top: 0.5rem;">
                <!-- Day Headers -->
                <div style="text-align:center; font-size:0.7rem; font-weight:800; color:#94a3b8;">SU</div>
                <div style="text-align:center; font-size:0.7rem; font-weight:800; color:#94a3b8;">MO</div>
                <div style="text-align:center; font-size:0.7rem; font-weight:800; color:#94a3b8;">TU</div>
                <div style="text-align:center; font-size:0.7rem; font-weight:800; color:#94a3b8;">WE</div>
                <div style="text-align:center; font-size:0.7rem; font-weight:800; color:#94a3b8;">TH</div>
                <div style="text-align:center; font-size:0.7rem; font-weight:800; color:#94a3b8;">FR</div>
                <div style="text-align:center; font-size:0.7rem; font-weight:800; color:#94a3b8;">SA</div>
                <?php
                for ($i = 0; $i < $firstDay; $i++) echo '<div></div>';
                for ($d = 1; $d <= $daysInMonth; $d++):
                    $dateObj = "$currYear-$currMonth-" . str_pad($d, 2, '0', STR_PAD_LEFT);
                    $isServed = $month_plans[$dateObj] ?? null;
                    $class = ($isServed === '1' || $isServed === 1) ? 'served' : (($isServed === '0' || $isServed === 0) ? 'active' : '');
                    $todayClass = ($dateObj === $today) ? 'today' : '';
                    echo "<div class='cal-day $class $todayClass' title='$dateObj'>$d</div>";
                endfor;
                ?>
            </div>
        </div>

        <!-- Management Ledger -->
        <div class="glass-widget ledger-hub">
            <div class="section-title"><span class="material-icons" style="font-size:14px;">history_edu</span> MANAGEMENT LEDGER</div>
            <div class="ledger-list">
                <?php while($log = $ledger_res->fetch_assoc()): ?>
                <div class="ledger-row">
                    <div>
                        <div style="font-weight:900; color:#1e293b; font-size:0.9rem;"><?= $log['category'] ?></div>
                        <div style="font-size:0.7rem; font-weight:700; color:#94a3b8;"><?= date('M d, Y', strtotime($log['created_at'])) ?></div>
                    </div>
                    <div style="font-weight:950; color:#0061ff;">&#8369;<?= number_format($log['amount'], 0) ?></div>
                </div>
                <?php endwhile; ?>
            </div>
            <a href="../management/management.php" style="display:flex; align-items:center; gap:0.5rem; justify-content:center; margin-top:2rem; font-size:0.75rem; font-weight:900; color:#0061ff; text-decoration:none;">
                VIEW FULL PORTFOLIO <span class="material-icons" style="font-size:14px;">arrow_forward</span>
            </a>
        </div>

    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>
?>
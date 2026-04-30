<?php
require_once 'db.php';
header('Content-Type: application/json');

$res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'total_allocated_budget'");
$allocated = 0;
while($r = $res->fetch_assoc()) $allocated = (float)$r['setting_value'];

// Only count as spent if the daily plan is served
$query_spent = "
    SELECT COALESCE(SUM(mp.actual_cost), 0) as t 
    FROM meal_plan mp 
    JOIN daily_meal_plans dmp ON mp.scheduled_date = dmp.scheduled_date 
    WHERE dmp.is_served = 1 AND mp.feeding_status IN ('Served', 'Double-Fed')
";
$meal_spent = (float)($conn->query($query_spent)->fetch_assoc()['t']);

// Predicted spend: not served yet, and date is today or in the future
$query_predicted = "
    SELECT COALESCE(SUM(mp.actual_cost), 0) as t 
    FROM meal_plan mp 
    JOIN daily_meal_plans dmp ON mp.scheduled_date = dmp.scheduled_date 
    WHERE dmp.is_served = 0 AND mp.scheduled_date >= CURDATE()
";
$meal_predicted = (float)($conn->query($query_predicted)->fetch_assoc()['t']);

$logs_spent = (float)($conn->query("SELECT COALESCE(SUM(amount),0) as t FROM budget_logs")->fetch_assoc()['t']);

$spent = $meal_spent + $logs_spent;
$remaining = $allocated - $spent;
$pct = $allocated > 0 ? min(100, round(($spent / $allocated) * 100, 1)) : 0;
$predicted_pct = $allocated > 0 ? min(100, round(($meal_predicted / $allocated) * 100, 1)) : 0;

echo json_encode([
    'allocated' => $allocated,
    'spent'     => $spent,
    'predicted' => $meal_predicted,
    'remaining' => $remaining,
    'pct'       => $pct,
    'predicted_pct' => $predicted_pct,
]);

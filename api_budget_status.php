<?php
require_once 'db.php';
header('Content-Type: application/json');

$res = $conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key = 'total_allocated_budget'");
$allocated = 0;
while($r = $res->fetch_assoc()) $allocated = (float)$r['setting_value'];

$meal_spent = (float)($conn->query("SELECT COALESCE(SUM(mp.actual_cost),0) as t FROM meal_plan mp JOIN daily_meal_plans dmp ON mp.scheduled_date = dmp.scheduled_date WHERE dmp.is_served = 1")->fetch_assoc()['t']);
$logs_spent = (float)($conn->query("SELECT COALESCE(SUM(amount),0) as t FROM budget_logs")->fetch_assoc()['t']);
$spent = $meal_spent + $logs_spent;
$remaining = $allocated - $spent;
$pct = $allocated > 0 ? min(100, round(($spent / $allocated) * 100, 1)) : 0;

echo json_encode([
    'allocated' => $allocated,
    'spent'     => $spent,
    'remaining' => $remaining,
    'pct'       => $pct,
]);

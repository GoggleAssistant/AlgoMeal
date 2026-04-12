<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/heuristic_engine.php';

// Recipe name lookup
$rmap = [];
$rres = $conn->query("SELECT recipe_id, recipe_name FROM recipes");
while($r = $rres->fetch_assoc()) $rmap[$r['recipe_id']] = $r['recipe_name'];

$conn->begin_transaction();

$dates = ['2026-12-14','2026-12-15','2026-12-16','2026-12-17','2026-12-18','2026-12-21','2026-12-22'];
$conn->query("DELETE FROM daily_meal_plans WHERE scheduled_date >= '2026-12-14' AND scheduled_date <= '2026-12-22'");

foreach($dates as $d) {
    $plan = generate_plan_for_date($conn, $d, 500);
    $ma = $plan['meal_a'];
    echo $d . ": " . ($rmap[$ma] ?? $ma) . "\n";
    $stmt = $conn->prepare("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES (?, ?)");
    $stmt->bind_param('ss', $d, $ma);
    $stmt->execute();
}

$conn->rollback();
?>

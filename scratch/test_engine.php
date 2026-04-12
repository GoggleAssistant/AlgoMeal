<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/heuristic_engine.php';

$conn->begin_transaction();

// Cleanup test dates first just in case
$conn->query("DELETE FROM daily_meal_plans WHERE scheduled_date IN ('2026-12-14', '2026-12-15', '2026-12-16')");

// Day 1
$d1 = '2026-12-14';
$plan1 = generate_plan_for_date($conn, $d1, 500);
$ma1 = $plan1['meal_a'];
echo "14th: A=" . $ma1 . "\n";
$stmt = $conn->prepare("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES (?, ?)");
$stmt->bind_param('ss', $d1, $ma1);
$stmt->execute();

// Day 2
$d2 = '2026-12-15';
$plan2 = generate_plan_for_date($conn, $d2, 500);
$ma2 = $plan2['meal_a'];
echo "15th: A=" . $ma2 . "\n";
$stmt = $conn->prepare("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES (?, ?)");
$stmt->bind_param('ss', $d2, $ma2);
$stmt->execute();

// Day 3
$d3 = '2026-12-16';
$plan3 = generate_plan_for_date($conn, $d3, 500);
$ma3 = $plan3['meal_a'];
echo "16th: A=" . $ma3 . "\n";

$conn->rollback();
?>

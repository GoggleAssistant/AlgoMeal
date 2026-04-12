<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/heuristic_engine.php';

$d = date('Y-m-d');
echo "Testing Heuristic Engine for $d...\n";

$plan = generate_plan_for_date($conn, $d, 500);

echo "\nResult:\n";
echo "Type: " . $plan['type'] . "\n";
echo "Meal A: " . $plan['meal_a'] . "\n";
echo "Meal B: " . ($plan['meal_b'] ?? 'none') . "\n";
echo "Meal A Students: " . count($plan['meal_a_list']) . "\n";
echo "Meal B Students: " . count($plan['meal_b_list']) . "\n";
?>

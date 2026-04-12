<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$conn->query('DELETE FROM meal_plan');
$conn->query('DELETE FROM daily_meal_plans');
$r1 = $conn->query('SELECT COUNT(*) as c FROM meal_plan')->fetch_assoc()['c'];
$r2 = $conn->query('SELECT COUNT(*) as c FROM daily_meal_plans')->fetch_assoc()['c'];
echo "Done. meal_plan: $r1 rows, daily_meal_plans: $r2 rows\n";
?>

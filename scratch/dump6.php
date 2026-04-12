<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/heuristic_engine.php';

$res = generate_plan_for_date($conn, '2026-04-14', 500);
print_r($res);
?>

<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$conn->begin_transaction();
$conn->query("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES ('2026-11-01', 'REC001')");
$res = $conn->query("SELECT COUNT(*) as c FROM daily_meal_plans WHERE meal_a_recipe_id = 'REC001' AND scheduled_date = '2026-11-01'");
echo "Count inside txn: " . $res->fetch_assoc()['c'] . "\n";
$conn->rollback();
?>

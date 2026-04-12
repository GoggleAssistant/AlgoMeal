<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$res = $conn->query("SELECT meal_a_recipe_id, meal_b_recipe_id, scheduled_date FROM daily_meal_plans ORDER BY scheduled_date");
echo "Meal History:\n";
while($r = $res->fetch_assoc()) {
    echo $r['scheduled_date'] . ": A=" . $r['meal_a_recipe_id'] . ", B=" . $r['meal_b_recipe_id'] . "\n";
}
?>

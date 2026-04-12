<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$res = $conn->query('SELECT * FROM daily_meal_plans ORDER BY scheduled_date');
while($row = $res->fetch_assoc()) {
    echo $row['scheduled_date'] . ' -> ' . $row['meal_a_recipe_id'] . PHP_EOL;
}
?>

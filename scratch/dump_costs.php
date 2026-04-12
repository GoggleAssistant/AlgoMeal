<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$res = $conn->query('SELECT recipe_id, recipe_name, base_cost_per_serving FROM recipes');
while($row = $res->fetch_assoc()) echo $row['recipe_id'] . " -> cost: " . $row['base_cost_per_serving'] . "\n";
?>

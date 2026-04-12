<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$res = $conn->query("SHOW COLUMNS FROM daily_meal_plans");
while($row = $res->fetch_assoc()) print_r($row);
?>

<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$res = $conn->query('SELECT * FROM daily_meal_plans');
while($row = $res->fetch_assoc()) print_r($row);
?>

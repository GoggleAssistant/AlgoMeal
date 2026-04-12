<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$_POST = json_decode('{"start_date":"2026-04-15","days_count":"5","weekdays":[1,2,3,4,5],"overwrite":true}', true);
file_put_contents('php://input', json_encode($_POST));
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/api_bulk_generate.php';
?>

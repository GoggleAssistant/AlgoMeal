<?php
require_once '../db.php';
$res = $conn->query("SELECT * FROM daily_meal_plans");
$rows = [];
if($res){
    while($row = $res->fetch_assoc()) $rows[] = $row;
}
echo json_encode($rows);

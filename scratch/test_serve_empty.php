<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$postData = json_encode(['action' => 'mark_served', 'date' => '2025-01-01']);
$ch = curl_init('http://localhost/AlgoMeal/pages/meal_planner/api_save_meal_plan.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);
echo "Result: " . $result;

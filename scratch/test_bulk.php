<?php
$ch = curl_init('http://localhost/AlgoMeal/pages/meal_planner/api_bulk_generate.php');
$payload = json_encode([
    'start_date' => '2026-04-14',
    'days_count' => 14,
    'weekdays' => [1,2,3,4,5],
    'overwrite' => true
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type:application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
curl_close($ch);
echo $result;
?>

<?php
require_once '../../db.php';
header('Content-Type: application/json');

$month = $_GET['month'] ?? date('Y-m'); // YYYY-MM
$res = $conn->query("
    SELECT dp.scheduled_date, dp.is_served,
           rA.hex_color as a_color, rB.hex_color as b_color
    FROM daily_meal_plans dp
    LEFT JOIN recipes rA ON dp.meal_a_recipe_id = rA.recipe_id
    LEFT JOIN recipes rB ON dp.meal_b_recipe_id = rB.recipe_id
    WHERE dp.scheduled_date LIKE '$month-%'
");

$data = [];
while ($row = $res->fetch_assoc()) {
    $data[$row['scheduled_date']] = [
        'a_color' => $row['a_color'] ?: '#3b82f6',
        'b_color' => $row['b_color'] ?: null, // null if no Meal B assigned
        'is_served' => $row['is_served'] == 1
    ];
}
echo json_encode(['success' => true, 'data' => $data]);
?>

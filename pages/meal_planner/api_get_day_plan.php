<?php
require_once '../../db.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? ''; 

if (empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Missing date parameter']);
    exit;
}

$sql = "
    SELECT meal_a_recipe_id, meal_b_recipe_id, is_served
    FROM daily_meal_plans
    WHERE scheduled_date = '$date'
";

$result = $conn->query($sql);

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        'success'   => true,
        'meal_a'    => $row['meal_a_recipe_id'],
        'meal_b'    => $row['meal_b_recipe_id'],
        'is_served' => (bool)$row['is_served']
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'No plan found for this date'
    ]);
}
?>

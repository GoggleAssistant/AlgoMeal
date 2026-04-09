<?php
require_once '../../db.php';

header('Content-Type: application/json');

$month = $_GET['month'] ?? ''; // Format: YYYY-MM

if (empty($month)) {
    echo json_encode(['success' => false, 'message' => 'Missing month parameter']);
    exit;
}

// Get all unique dates in the meal_plan table that fall within this month
$sql = "
    SELECT d.scheduled_date, 
           COUNT(m.student_id) as count,
           CONCAT(d.meal_a_recipe_id, ',', d.meal_b_recipe_id) as assigned_recipes
    FROM daily_meal_plans d
    LEFT JOIN meal_plan m ON d.scheduled_date = m.scheduled_date
    WHERE d.scheduled_date LIKE '$month-%'
    GROUP BY d.scheduled_date
";
$result = $conn->query($sql);
$dates = [];

while ($row = $result->fetch_assoc()) {
    $dates[$row['scheduled_date']] = [
        'status' => 'deployed',
        'count' => $row['count'],
        'assigned_recipes' => $row['assigned_recipes']
    ];
}

echo json_encode([
    'success' => true,
    'data' => $dates
]);
?>

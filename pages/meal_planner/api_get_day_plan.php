<?php
// api_get_day_plan.php
require_once '../../db.php';
header('Content-Type: application/json');

$date = $_GET['date'] ?? null;
if (!$date) {
    echo json_encode(['success' => false, 'message' => 'Date needed.']);
    exit;
}

$q_plan = "SELECT * FROM daily_meal_plans WHERE scheduled_date = ?";
$stmt = $conn->prepare($q_plan);
$stmt->bind_param('s', $date);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows === 0) {
    echo json_encode(['success' => false, 'has_plan' => false]);
    exit;
}

$plan = $res->fetch_assoc();
$meal_a = $plan['meal_a_recipe_id'];
$meal_b = $plan['meal_b_recipe_id'];

// Get Students List
$q_students = "
    SELECT m.student_id as id, m.feeding_status, m.recipe_id, s.first_name, s.last_name, s.section,
           (SELECT GROUP_CONCAT(dr.restriction_name) 
            FROM student_allergy_map sam 
            JOIN dietary_restrictions dr ON sam.restriction_id = dr.restriction_id 
            WHERE sam.student_id = s.student_id) as restriction_names,
           (SELECT GROUP_CONCAT(sam.restriction_id)
            FROM student_allergy_map sam
            WHERE sam.student_id = s.student_id) as restriction_ids
    FROM meal_plan m
    JOIN student s ON m.student_id = s.student_id
    WHERE m.scheduled_date = ?
    ORDER BY s.section, s.last_name, s.first_name
";
$stmt2 = $conn->prepare($q_students);
$stmt2->bind_param('s', $date);
$stmt2->execute();
$res2 = $stmt2->get_result();

$meal_a_list = [];
$meal_b_list = [];

while ($s = $res2->fetch_assoc()) {
    $student_data = [
        'id'               => $s['id'],
        'name'             => $s['first_name'] . ' ' . $s['last_name'],
        'section'          => $s['section'],
        'restriction_names'=> $s['restriction_names'] ?? '',
        'restriction_ids'  => $s['restriction_ids'] ? array_values(array_filter(explode(',', $s['restriction_ids']))) : [],
        'feeding_status'   => $s['feeding_status'] ?? 'Served'
    ];
    if ($s['recipe_id'] === $meal_a) $meal_a_list[] = $student_data;
    else if ($s['recipe_id'] === $meal_b) $meal_b_list[] = $student_data;
}

echo json_encode([
    'success' => true,
    'has_plan' => true,
    'is_served' => $plan['is_served'] == 1,
    'meal_a' => $meal_a,
    'meal_b' => $meal_b,
    'meal_a_list' => $meal_a_list,
    'meal_b_list' => $meal_b_list
]);
?>

<?php
// Test api_get_day_plan.php response for a date that has a plan
// First generate a plan, then check the API response
require 'c:/xampp/htdocs/AlgoMeal/db.php';
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/heuristic_engine.php';

$conn->begin_transaction();

// Generate a plan for a test date
$d = '2026-12-20';
$conn->query("DELETE FROM daily_meal_plans WHERE scheduled_date = '$d'");
$conn->query("DELETE FROM meal_plan WHERE scheduled_date = '$d'");

$plan = generate_plan_for_date($conn, $d, 500);
echo "Engine result:\n";
echo "  Meal A: " . $plan['meal_a'] . "\n";
echo "  Meal B: " . ($plan['meal_b'] ?? 'none') . "\n";
echo "  Type: " . $plan['type'] . "\n";
echo "  Meal A students (" . count($plan['meal_a_list']) . "): " . implode(', ', $plan['meal_a_list']) . "\n";
echo "  Meal B students (" . count($plan['meal_b_list']) . "): " . implode(', ', $plan['meal_b_list']) . "\n";

// Insert the plan
$stmt_i = $conn->prepare("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id, meal_b_recipe_id) VALUES (?, ?, ?)");
$stmt_i->bind_param('sss', $d, $plan['meal_a'], $plan['meal_b']);
$stmt_i->execute();

$insert = $conn->prepare("INSERT INTO meal_plan (student_id, recipe_id, scheduled_date, actual_cost) VALUES (?, ?, ?, ?)");
foreach($plan['meal_a_list'] as $sid) {
    $insert->bind_param('sssd', $sid, $plan['meal_a'], $d, $cost=0);
    $insert->execute();
}
foreach($plan['meal_b_list'] as $sid) {
    $insert->bind_param('sssd', $sid, $plan['meal_b'], $d, $cost=0);
    $insert->execute();
}

// Now simulate api_get_day_plan.php
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
$stmt2->bind_param('s', $d);
$stmt2->execute();
$res2 = $stmt2->get_result();

echo "\nStudent data returned by API:\n";
while($s = $res2->fetch_assoc()) {
    $res_ids = $s['restriction_ids'] ? array_values(array_filter(explode(',', $s['restriction_ids']))) : [];
    echo "  " . $s['first_name'] . " " . $s['last_name'] . " [recipe=" . $s['recipe_id'] . "]:";
    echo " restriction_names=" . ($s['restriction_names'] ?? 'none');
    echo " | restriction_ids=" . json_encode($res_ids) . "\n";
}

$conn->rollback();
echo "\nDone (rolled back).\n";
?>

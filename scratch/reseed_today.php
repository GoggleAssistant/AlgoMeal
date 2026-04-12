<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/heuristic_engine.php';

$d = date('Y-m-d'); // Today: 2026-04-12
$conn->query("DELETE FROM daily_meal_plans WHERE scheduled_date = '$d'");
$conn->query("DELETE FROM meal_plan WHERE scheduled_date = '$d'");

// Ensure we have a Halal student for testing conflict
// Juan Dela Cruz (LRN-006) should be Halal
$res_check = $conn->query("SELECT * FROM student_allergy_map WHERE student_id='LRN-006' AND restriction_id=9");
if($res_check->num_rows == 0) {
    echo "Adding Halal restriction to LRN-006 for testing.\n";
    $conn->query("INSERT INTO student_allergy_map (student_id, restriction_id) VALUES ('LRN-006', 9)");
}

// Generate
$plan = generate_plan_for_date($conn, $d, 500);
echo "Result for $d: Meal A=" . $plan['meal_a'] . ", Meal B=" . ($plan['meal_b'] ?? 'none') . ", Type=" . $plan['type'] . "\n";

// Insert
$stmt = $conn->prepare("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id, meal_b_recipe_id) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $d, $plan['meal_a'], $plan['meal_b']);
$stmt->execute();

$insert = $conn->prepare("INSERT INTO meal_plan (student_id, recipe_id, scheduled_date, actual_cost) VALUES (?, ?, ?, 0)");
foreach($plan['meal_a_list'] as $sid) {
    $insert->bind_param('sss', $sid, $plan['meal_a'], $d);
    $insert->execute();
}
foreach($plan['meal_b_list'] as $sid) {
    $insert->bind_param('sss', $sid, $plan['meal_b'], $d);
    $insert->execute();
}
echo "Plan inserted for $d.\n";
?>

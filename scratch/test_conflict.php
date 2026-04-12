<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/heuristic_engine.php';

$rmap = [];
$rres = $conn->query("SELECT recipe_id, recipe_name FROM recipes");
while($r = $rres->fetch_assoc()) $rmap[$r['recipe_id']] = $r['recipe_name'];

// Check how many students have Halal restriction (id=9) or require Non-Halal-free food
$res_halal = $conn->query("SELECT COUNT(*) as c FROM student_allergy_map WHERE restriction_id = 9");
echo "Halal students (req 9): " . $res_halal->fetch_assoc()['c'] . "\n";

// Ginisang Kangkong (REC012) has restriction_id 12,16,17 -- 17=Non-Halal
// Let's simulate: add fake history to penalize everything except REC012
$conn->begin_transaction();

// Penalize all EXCEPT REC012 by adding fake history
$penalize = ['REC001','REC002','REC003','REC004','REC005','REC006','REC007','REC008','REC009','REC010','REC011'];
$fakedate1 = '2026-12-01';
$fakedate2 = '2026-12-02';
foreach($penalize as $rid) {
    $stmt = $conn->prepare("INSERT IGNORE INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES (?, ?)");
    $stmt->bind_param('ss', $fakedate1, $rid);
    $stmt->execute();
    $stmt2 = $conn->prepare("INSERT IGNORE INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES (?, ?)");
    $stmt2->bind_param('ss', $fakedate2, $rid);
    $stmt2->execute();
}

// Now generate for a date 3 days later
$plan = generate_plan_for_date($conn, '2026-12-05', 500);
echo "Meal A: " . ($rmap[$plan['meal_a']] ?? $plan['meal_a']) . "\n";
echo "Meal B: " . ($plan['meal_b'] ? ($rmap[$plan['meal_b']] ?? $plan['meal_b']) : 'None') . "\n";
echo "Type: " . $plan['type'] . "\n";
echo "Meal A students: " . count($plan['meal_a_list']) . "\n";
echo "Meal B students: " . count($plan['meal_b_list']) . "\n";

$conn->rollback();
?>

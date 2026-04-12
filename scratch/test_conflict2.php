<?php
// Force a scenario where REC012 (Ginisang Kangkong, Non-Halal) is the only dish and see if Meal B fires
require 'c:/xampp/htdocs/AlgoMeal/db.php';
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/heuristic_engine.php';

$rmap = [];
$rres = $conn->query("SELECT recipe_id, recipe_name FROM recipes");
while($r = $rres->fetch_assoc()) $rmap[$r['recipe_id']] = $r['recipe_name'];

$conn->begin_transaction();

// Penalize ALL but REC012 (Ginisang Kangkong = Non-Halal)
$all_except = ['REC001','REC002','REC003','REC004','REC005','REC006','REC007','REC008','REC009','REC010','REC011'];
// Insert them as served TWICE in last 14 days to trigger hard limit
for($i=1; $i<=2; $i++) {
    $fd = '2026-12-0' . $i;
    // Use a workaround: insert each recipe into a different date
    foreach($all_except as $rid) {
        // Can't put two recipes on same date in daily_meal_plans - use meal_b for second entry
        $stmt = $conn->prepare("INSERT IGNORE INTO daily_meal_plans (scheduled_date, meal_a_recipe_id, meal_b_recipe_id) VALUES (?, ?, ?)");
        $d = '2026-12-' . str_pad($i * 2, 2, '0', STR_PAD_LEFT); // 02, 04
        $other = 'REC012';
        $stmt->bind_param('sss', $d, $rid, $other);
        break; // Only do one per date
    }
    // Actually let's just insert each recipe on separate dates
}

// Simpler: just insert 2 records for each recipe on 2 different dates
$conn->query("DELETE FROM daily_meal_plans WHERE scheduled_date BETWEEN '2026-11-01' AND '2026-11-30'");
foreach($all_except as $i => $rid) {
    $d1 = '2026-11-' . str_pad(($i * 2 + 1), 2, '0', STR_PAD_LEFT);
    $d2 = '2026-11-' . str_pad(($i * 2 + 2), 2, '0', STR_PAD_LEFT);
    $conn->query("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES ('$d1', '$rid')");
    $conn->query("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES ('$d2', '$rid')");
}

// Now generate for Dec 5 — only REC012 (Ginisang Kangkong) should have variety_score=1000
$plan = generate_plan_for_date($conn, '2026-12-05', 500);
echo "Meal A: " . ($rmap[$plan['meal_a']] ?? $plan['meal_a']) . "\n";
echo "Meal B: " . ($plan['meal_b'] ? ($rmap[$plan['meal_b']] ?? $plan['meal_b']) : 'None') . "\n";
echo "Type: " . $plan['type'] . "\n";
echo "Meal A students: " . count($plan['meal_a_list']) . "\n";
echo "Meal B students: " . count($plan['meal_b_list']) . "\n";

$conn->rollback();
?>

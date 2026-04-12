<?php
// Check exactly what variety scores are computed when we have penalized data
require 'c:/xampp/htdocs/AlgoMeal/db.php';

$conn->begin_transaction();

// Penalize all except REC012
$all_except = ['REC001','REC002','REC003','REC004','REC005','REC006','REC007','REC008','REC009','REC010','REC011'];
$conn->query("DELETE FROM daily_meal_plans WHERE scheduled_date BETWEEN '2026-11-01' AND '2026-11-30'");
foreach($all_except as $i => $rid) {
    $d1 = '2026-11-' . str_pad(($i * 2 + 1), 2, '0', STR_PAD_LEFT);
    $d2 = '2026-11-' . str_pad(($i * 2 + 2), 2, '0', STR_PAD_LEFT);
    $conn->query("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES ('$d1', '$rid')");
    $conn->query("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id) VALUES ('$d2', '$rid')");
}

$target = '2026-12-05';
$q = "
    SELECT r.recipe_id, r.recipe_name,
           GROUP_CONCAT(DISTINCT rat.restriction_id) as restriction_ids,
           (SELECT MAX(dp.scheduled_date) FROM daily_meal_plans dp 
            WHERE (dp.meal_a_recipe_id = r.recipe_id OR dp.meal_b_recipe_id = r.recipe_id) AND dp.scheduled_date < ?) as last_served,
           (SELECT COUNT(*) FROM daily_meal_plans dp 
            WHERE (dp.meal_a_recipe_id = r.recipe_id OR dp.meal_b_recipe_id = r.recipe_id)
              AND dp.scheduled_date >= DATE_SUB(?, INTERVAL 14 DAY) AND dp.scheduled_date < ?) as recent_count
    FROM recipes r
    LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id
    GROUP BY r.recipe_id
";
$stmt = $conn->prepare($q);
$stmt->bind_param('sss', $target, $target, $target);
$stmt->execute();
$res = $stmt->get_result();

echo "Scores for $target:\n";
while($row = $res->fetch_assoc()) {
    $rc = $row['recent_count'];
    $ls = $row['last_served'];
    if($rc >= 2) $vs = -999999;
    elseif($ls) {
        $days = floor((strtotime($target) - strtotime($ls)) / 86400);
        $vs = $days <= 2 ? -999999 : min(1000, pow($days,2)*20);
    } else $vs = 1000;
    echo $row['recipe_name'] . ": recent=$rc, last=$ls, variety=$vs, ids=[" . $row['restriction_ids'] . "]\n";
}

$conn->rollback();
?>

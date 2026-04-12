<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
require 'c:/xampp/htdocs/AlgoMeal/pages/meal_planner/heuristic_engine.php';

// Expose internal scoring by manually running the query
$target_date = '2026-04-14';
$budget_limit = 500;

$q_recipes = "
    SELECT r.*,
           GROUP_CONCAT(DISTINCT rat.restriction_id) as restriction_ids,
           (SELECT MAX(dp.scheduled_date) 
            FROM daily_meal_plans dp 
            WHERE (dp.meal_a_recipe_id = r.recipe_id OR dp.meal_b_recipe_id = r.recipe_id)
              AND dp.scheduled_date < ?) as last_served,
           (SELECT COUNT(*) 
            FROM daily_meal_plans dp 
            WHERE (dp.meal_a_recipe_id = r.recipe_id OR dp.meal_b_recipe_id = r.recipe_id)
              AND dp.scheduled_date >= DATE_SUB(?, INTERVAL 14 DAY)
              AND dp.scheduled_date < ?) as recent_count
    FROM recipes r
    LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id
    GROUP BY r.recipe_id
";
$stmt = $conn->prepare($q_recipes);
$stmt->bind_param('sss', $target_date, $target_date, $target_date);
$stmt->execute();
$res = $stmt->get_result();

// Budget check
$res_settings = $conn->query("SELECT setting_value FROM settings WHERE setting_key='total_daily_budget'");
$budget = $res_settings->num_rows > 0 ? (float)$res_settings->fetch_assoc()['setting_value'] : 500.00;

$res_stud = $conn->query("SELECT COUNT(*) as c FROM student");
$total_students = $res_stud->fetch_assoc()['c'];
$max_cost_per_head = $budget / max(1, $total_students);

echo "Budget: $budget, Students: $total_students, Max cost/head: $max_cost_per_head\n\n";

while ($row = $res->fetch_assoc()) {
    $ids = $row['restriction_ids'] ? array_filter(explode(',', $row['restriction_ids'])) : [];
    $recent = $row['recent_count'];
    $last = $row['last_served'];
    $cost = $row['base_cost_per_serving'];
    $energy = $row['energy_kcal'];
    
    // Cost check
    $over_budget = $cost > $max_cost_per_head;
    
    // Variety score
    if ($recent >= 2) {
        $vs = -999999;
    } elseif ($last) {
        $diff = strtotime($target_date) - strtotime($last);
        $days = floor($diff / 86400);
        $vs = $days <= 2 ? -999999 : min(1000, pow($days, 2) * 20);
    } else {
        $vs = 1000;
    }
    
    $dist_kcal = abs($energy - 500) / 500;
    $dist_prot = abs($row['protein_g'] - 15) / 15;
    $nutri = max(0, 100 - (($dist_kcal + $dist_prot) * 50));
    $final = ($vs * 0.8) + ($nutri * 0.2);
    
    echo $row['recipe_name'] . " [" . $row['recipe_id'] . "]\n";
    echo "  Cost: $cost | Over budget: " . ($over_budget ? 'YES' : 'no') . "\n";
    echo "  Restrictions: [" . implode(',', $ids) . "]\n";
    echo "  recent_count: $recent | last_served: " . ($last ?: 'never') . "\n";
    echo "  variety_score: $vs | nutri_score: " . round($nutri,2) . " | final: " . round($final,2) . "\n\n";
}
?>

<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$target_date = '2026-04-20';
$q_recipes = "
    SELECT r.*,
           GROUP_CONCAT(DISTINCT rat.restriction_id) as restriction_ids,
           (SELECT MAX(dp.scheduled_date) 
            FROM daily_meal_plans dp 
            WHERE (dp.meal_a_recipe_id = r.recipe_id OR dp.meal_b_recipe_id = r.recipe_id)
              AND dp.scheduled_date < ?) as last_served
    FROM recipes r
    LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id
    GROUP BY r.recipe_id
";
$stmt = $conn->prepare($q_recipes);
$stmt->bind_param('s', $target_date);
$stmt->execute();
$res_recipes = $stmt->get_result();
while ($row = $res_recipes->fetch_assoc()) {
    print_r($row);
}
?>

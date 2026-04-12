<?php
require 'c:\xampp\htdocs\AlgoMeal\db.php';
$res_recipes = $conn->query("SELECT r.*, GROUP_CONCAT(DISTINCT dr.restriction_name) as allergens, GROUP_CONCAT(DISTINCT rat.restriction_id) as restriction_ids FROM recipes r LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id LEFT JOIN dietary_restrictions dr ON rat.restriction_id = dr.restriction_id GROUP BY r.recipe_id ORDER BY r.recipe_name");
$recipes = [];
while ($row = $res_recipes->fetch_assoc()) {
    $recipes[] = $row;
}
print_r($recipes);
?>

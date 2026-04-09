<?php
require_once '../../db.php';

header('Content-Type: application/json');

$sql = "
    SELECT r.*, 
           GROUP_CONCAT(DISTINCT dr.restriction_name) as allergens,
           GROUP_CONCAT(DISTINCT dr.restriction_id) as restriction_ids,
           GROUP_CONCAT(DISTINCT dr.type) as restriction_types
    FROM recipes r
    LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id
    LEFT JOIN dietary_restrictions dr ON rat.restriction_id = dr.restriction_id
    GROUP BY r.recipe_id
";

$result = $conn->query($sql);
$recipes = [];

while ($row = $result->fetch_assoc()) {
    $row['ingredients'] = [];
    $row['instructions'] = [];
    
    // Fetch Ingredients
    $ing_res = $conn->query("SELECT name, amount, unit FROM recipe_ingredients WHERE recipe_id = '{$row['recipe_id']}'");
    while($i = $ing_res->fetch_assoc()) $row['ingredients'][] = $i;
    
    // Fetch Instructions
    $inst_res = $conn->query("SELECT step_no, instruction FROM recipe_instructions WHERE recipe_id = '{$row['recipe_id']}' ORDER BY step_no ASC");
    while($i = $inst_res->fetch_assoc()) $row['instructions'][] = $i;
    
    $recipes[] = $row;
}

echo json_encode($recipes);
?>

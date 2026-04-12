<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$res = $conn->query('SELECT r.recipe_id, r.recipe_name, r.energy_kcal, GROUP_CONCAT(rat.restriction_id) as res FROM recipes r LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id GROUP BY r.recipe_id');
while($row = $res->fetch_assoc()) {
    echo $row['recipe_name'] . ' -> res: ' . $row['res'] . "\n";
}
?>

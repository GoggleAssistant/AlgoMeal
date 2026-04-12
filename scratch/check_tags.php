<?php
require_once 'db.php';
$res = $conn->query("SELECT * FROM dietary_restrictions");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

$res = $conn->query("SELECT r.recipe_name, r.recipe_id, d.restriction_name FROM recipes r LEFT JOIN recipe_allergen_tags t ON r.recipe_id = t.recipe_id LEFT JOIN dietary_restrictions d ON t.restriction_id = d.restriction_id");
echo "RECIPES:\n";
while($row = $res->fetch_assoc()) {
    print_r($row);
}
?>

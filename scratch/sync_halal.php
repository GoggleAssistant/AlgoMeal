<?php
require_once '../db.php';

echo "<pre>";
echo "Synchronizing Halal Conflict Logic...\n";

// 1. Ensure the restriction is named 'Halal' for the student profile
$conn->query("UPDATE dietary_restrictions SET restriction_name = 'Halal' WHERE restriction_name = 'Non-Halal (Pork)' OR restriction_name = 'Halal / Pork-Free'");

// 2. Fetch the ID
$res = $conn->query("SELECT restriction_id FROM dietary_restrictions WHERE restriction_name = 'Halal'");
$row = $res->fetch_assoc();
$halal_id = $row['restriction_id'];

echo "Halal Restriction ID: $halal_id\n";

// 3. RECIPES: Tag those that CONTAIN PORK with the 'Halal' conflict ID
// Clearning first to be precise
$conn->query("DELETE FROM recipe_allergen_tags WHERE restriction_id = $halal_id");

// Recipes with Pork (Non-Halal)
$pork_recipes = ['REC003', 'REC007']; // Picadillo, Misua with Pork
foreach($pork_recipes as $rid) {
    $conn->query("INSERT INTO recipe_allergen_tags (recipe_id, restriction_id) VALUES ('$rid', $halal_id)");
    echo "Recipe $rid tagged with Halal conflict (Contains Pork).\n";
}

echo "\nLogic Sync Complete.\n";
echo "</pre>";
?>

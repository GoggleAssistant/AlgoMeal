<?php
require_once '../db.php';

echo "<pre>";
echo "Updating Dietary Restriction naming convention...\n";

// 1. Rename the restriction globally
$conn->query("UPDATE dietary_restrictions SET restriction_name = 'Non-Halal (Pork)' WHERE restriction_name = 'Halal / Pork-Free'");

// 2. Fetch the ID
$res = $conn->query("SELECT restriction_id FROM dietary_restrictions WHERE restriction_name = 'Non-Halal (Pork)'");
$row = $res->fetch_assoc();
$rid = $row['restriction_id'];

echo "New Restriction ID: $rid\n";

// 3. Meticulous Tag Audit:
// Any recipe previously tagged as 'Halal / Pork-Free' was meant to be COMPLIANT. 
// BUT, the user wants 'Non-Halal' as the warning (restriction). 
// So I must REMOVE the tag from Halal recipes and ADD it to recipes WITH Pork.

// Clear existing tags for this ID
$conn->query("DELETE FROM recipe_allergen_tags WHERE restriction_id = $rid");

// Add tag strictly to recipes WITH Pork
$recipes_with_pork = ['REC003', 'REC007']; // Picadillo, Misua with Pork
foreach ($recipes_with_pork as $id) {
    if ($conn->query("INSERT INTO recipe_allergen_tags (recipe_id, restriction_id) VALUES ('$id', $rid)")) {
        echo "Tagged $id as Non-Halal (Pork)\n";
    }
}

echo "\nAudit and Renaming Complete.\n";
echo "</pre>";
?>

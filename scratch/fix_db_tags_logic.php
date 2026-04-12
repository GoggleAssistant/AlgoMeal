<?php
require_once 'db.php';

// 1. Add "Non-Halal" if it doesn't exist
$conn->query("INSERT IGNORE INTO dietary_restrictions (restriction_name, type) VALUES ('Non-Halal', 'Religious')");

// 2. Fetch the ID for Non-Halal
$res = $conn->query("SELECT restriction_id FROM dietary_restrictions WHERE restriction_name = 'Non-Halal'");
$non_halal_id = $res->fetch_assoc()['restriction_id'];

// 3. The old script tagged recipes that have pork with "Halal" (ID 9). We should change those tags to "Non-Halal".
// Let's find recipes tagged with 9 and if they are pork dishes, move them to non-halal.
$pork_recipes = ['REC003', 'REC007']; // Pork and Veggie Picadillo, Misua with Pork
foreach($pork_recipes as $rid) {
    // Delete old Halal tag
    $conn->query("DELETE FROM recipe_allergen_tags WHERE recipe_id = '$rid' AND restriction_id = 9");
    // Add Non-Halal tag
    $conn->query("INSERT IGNORE INTO recipe_allergen_tags (recipe_id, restriction_id) VALUES ('$rid', $non_halal_id)");
}
echo "Database updated.\n";
?>

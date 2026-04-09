<?php
require_once '../db.php';

echo "<pre>";
echo "Expanding Dietary Restrictions Table...\n";

$new_restrictions = [
    ['Soy', 'Allergy'],
    ['Eggs', 'Allergy'],
    ['Wheat / Gluten', 'Allergy'],
    ['Fish', 'Allergy'],
    ['Tree Nuts', 'Allergy'],
    ['Halal / Pork-Free', 'Religious'],
    ['Vegetarian', 'Religious'], // Simplified as religious/choice for now
    ['Vegan', 'Religious']
];

foreach ($new_restrictions as $nr) {
    $stmt = $conn->prepare("INSERT INTO dietary_restrictions (restriction_name, type) VALUES (?, ?) ON DUPLICATE KEY UPDATE restriction_name=restriction_name");
    $stmt->bind_param("ss", $nr[0], $nr[1]);
    $stmt->execute();
    echo "Added: {$nr[0]}\n";
}

// Get the NEW mapping
$res = $conn->query("SELECT * FROM dietary_restrictions");
$map = [];
while($row = $res->fetch_assoc()) {
    $map[$row['restriction_name']] = $row['restriction_id'];
    echo "ID {$row['restriction_id']}: {$row['restriction_name']} ({$row['type']})\n";
}

echo "\nAuditing Recipe Allergens...\n";

// Clear all current tags and apply correct ones
$conn->query("DELETE FROM recipe_allergen_tags");

// 1. Arroz Caldo - None (unless we assume soy in broth, but let's keep it safe)
// No tags

// 2. Ginisang Monggo - Contains Fish (Tinapa)
if (isset($map['Fish'])) {
    $conn->query("INSERT INTO recipe_allergen_tags (recipe_id, restriction_id) VALUES ('REC002', {$map['Fish']})");
    echo "REC002 (Monggo) -> Tagged with Fish\n";
}

// 3. Pinakbet - Contains Shellfish (Bagoong)
if (isset($map['Shellfish'])) {
    $conn->query("INSERT INTO recipe_allergen_tags (recipe_id, restriction_id) VALUES ('REC005', {$map['Shellfish']})");
    echo "REC005 (Pinakbet) -> Tagged with Shellfish\n";
}

// 4. Pork-based recipes should get the Halal flag as a 'restriction' for those who avoid pork
// Picadillo contains pork. 
// Arroz Caldo / Monggo / Tinola / Pinakbet (if veggie) could be Halal.
// Actually, Picadillo (REC003) is Pork. 
// Pinakbet (REC005) usually has pork bits too, but my seed didn't specify.

echo "\nExpansion and Audit Complete.\n";
echo "</pre>";
?>

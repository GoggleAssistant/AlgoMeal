<?php
// Direct unit test: call evaluateMeal equivalent with Halal student vs Non-Halal recipe
require 'c:/xampp/htdocs/AlgoMeal/db.php';

// Simulate what the engine does
$conflict_map = [
    9  => [17], // Halal student conflicts with Non-Halal recipe
    16 => [17], // Red Meat-Free conflicts with Non-Halal
];

// Halal student (restriction_id = 9)
$student = ['student_id' => 'LRN-006', 'restriction_ids' => ['9']];

// Recipe: Pork and Veggie Picadillo (restriction_id = 17 = Non-Halal)
$recipe_picadillo = ['recipe_id' => 'REC003', 'restriction_ids' => ['17']];
// Recipe: Ginisang Kangkong (restriction_id = 12,16,17)
$recipe_kangkong  = ['recipe_id' => 'REC012', 'restriction_ids' => ['12','16','17']];
// Recipe: Arroz Caldo (no restrictions)
$recipe_arroz     = ['recipe_id' => 'REC001', 'restriction_ids' => []];

function hasConflict($student, $recipe, $conflict_map) {
    foreach($student['restriction_ids'] as $res_id) {
        if(in_array($res_id, $recipe['restriction_ids'])) return true;
        if(isset($conflict_map[$res_id])) {
            foreach($conflict_map[$res_id] as $ct) {
                if(in_array($ct, $recipe['restriction_ids'])) return true;
            }
        }
    }
    return false;
}

echo "Halal student vs Picadillo (Non-Halal): " . (hasConflict($student, $recipe_picadillo, $conflict_map) ? 'CONFLICT ✓' : 'no conflict ✗') . "\n";
echo "Halal student vs Kangkong (Non-Halal):  " . (hasConflict($student, $recipe_kangkong, $conflict_map) ? 'CONFLICT ✓' : 'no conflict ✗') . "\n";
echo "Halal student vs Arroz Caldo (none):    " . (hasConflict($student, $recipe_arroz, $conflict_map) ? 'CONFLICT ✗' : 'no conflict ✓') . "\n";
?>

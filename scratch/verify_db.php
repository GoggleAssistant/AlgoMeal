<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
echo "--- DB Structure Check ---\n";
$tables = ['recipes', 'daily_meal_plans', 'meal_plan', 'student', 'student_allergy_map', 'recipe_allergen_tags', 'dietary_restrictions'];
foreach($tables as $t) {
    echo "\nTable: $t\n";
    $res = $conn->query("DESCRIBE $t");
    if($res) {
        while($r = $res->fetch_assoc()) {
            echo "  " . $r['Field'] . " (" . $r['Type'] . ")\n";
        }
    } else {
        echo "  [FAIL] Does not exist or error: " . $conn->error . "\n";
    }
}
?>

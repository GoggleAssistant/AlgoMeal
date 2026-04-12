<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';

// IDs to remove
$veg_ids = [10, 11]; // 10=Vegetarian, 11=Vegan

foreach($veg_ids as $id) {
    $conn->query("DELETE FROM student_allergy_map WHERE restriction_id = $id");
    $conn->query("DELETE FROM recipe_allergen_tags WHERE restriction_id = $id");
    $conn->query("DELETE FROM dietary_restrictions WHERE restriction_id = $id");
}

// Clear meal planner
$conn->query("DELETE FROM meal_plan");
$conn->query("DELETE FROM daily_meal_plans");

$r1 = $conn->query("SELECT COUNT(*) as c FROM meal_plan")->fetch_assoc()['c'];
$r2 = $conn->query("SELECT COUNT(*) as c FROM daily_meal_plans")->fetch_assoc()['c'];
$r3 = $conn->query("SELECT COUNT(*) as c FROM dietary_restrictions WHERE restriction_id IN (10,11)")->fetch_assoc()['c'];

echo "Vegan/Vegetarian restrictions remaining: $r3\n";
echo "meal_plan rows: $r1\n";
echo "daily_meal_plans rows: $r2\n";
?>

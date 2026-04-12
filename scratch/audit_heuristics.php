<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
echo "--- RECIPES ---\n";
$res = $conn->query("SELECT recipe_id, recipe_name, energy_kcal, base_cost_per_serving FROM recipes");
while($row = $res->fetch_assoc()) {
    printf("[%s] %-25s | Kcal: %4d | Cost: %5.2f\n", $row['recipe_id'], $row['recipe_name'], $row['energy_kcal'], $row['base_cost_per_serving']);
}

echo "\n--- NUTRITIONAL TARGET CHECK ---\n";
$res = $conn->query("SELECT (SELECT count(*) FROM student) as total_students, (SELECT count(*) FROM nutritional_record nr JOIN (SELECT student_id, MAX(assessment_date) as max_date FROM nutritional_record GROUP BY student_id) latest ON nr.student_id = latest.student_id AND nr.assessment_date = latest.max_date WHERE nr.nutritional_status IN ('Severely Wasted', 'Wasted') OR nr.nutritional_status LIKE '%Underweight%') as underweight_count");
$row = $res->fetch_assoc();
$total = $row['total_students'];
$under = $row['underweight_count'];
$target = 500;
if ($total > 0 && ($under / $total) > 0.5) $target = 575;
echo "Total Students: $total | Underweight: $under | Current Target: $target Kcal\n";
?>

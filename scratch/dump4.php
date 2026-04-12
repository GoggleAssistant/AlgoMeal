<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
echo "--- ALLERGENS ---\n";
$res = $conn->query('SELECT recipe_id, restriction_id FROM recipe_allergen_tags');
while($r = $res->fetch_assoc()) print_r($r);

echo "--- STUDENTS ---\n";
$res = $conn->query('SELECT student_id, restriction_id FROM student_allergy_map');
while($r = $res->fetch_assoc()) print_r($r);
?>

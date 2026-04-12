<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';

// Check the student restriction detail
$res = $conn->query("
    SELECT s.student_id, s.first_name, s.last_name, 
           GROUP_CONCAT(dr.restriction_name) as restrictions
    FROM student s 
    LEFT JOIN student_allergy_map sam ON s.student_id = sam.student_id
    LEFT JOIN dietary_restrictions dr ON sam.restriction_id = dr.restriction_id
    WHERE sam.restriction_id IS NOT NULL
    GROUP BY s.student_id
");
if($res->num_rows == 0) {
    echo "NO students have any dietary restrictions.\n";
} else {
    while($r = $res->fetch_assoc()) {
        echo $r['first_name'] . ' ' . $r['last_name'] . ': ' . $r['restrictions'] . "\n";
    }
}

// Also check restriction ID 9 specifically
$res2 = $conn->query("SELECT COUNT(*) as c FROM student_allergy_map WHERE restriction_id = 9");
echo "Halal (id=9) student count: " . $res2->fetch_assoc()['c'] . "\n";

// Check what restriction Pork and Veggie Picadillo has
$res3 = $conn->query("SELECT GROUP_CONCAT(restriction_id) as ids FROM recipe_allergen_tags WHERE recipe_id = 'REC003'");
echo "REC003 (Picadillo) restriction_ids: " . $res3->fetch_assoc()['ids'] . "\n";

// List all halal students
$res4 = $conn->query("SELECT student_id FROM student_allergy_map WHERE restriction_id = 9");
echo "Students with restriction 9 (Halal): ";
while($r = $res4->fetch_assoc()) echo $r['student_id'] . " ";
echo "\n";

// List all students that map with restriction 17 (Non-Halal)
$res5 = $conn->query("SELECT student_id FROM student_allergy_map WHERE restriction_id = 17");
echo "Students with restriction 17 (Non-Halal): ";
while($r = $res5->fetch_assoc()) echo $r['student_id'] . " ";
echo "\n";
?>

<?php
require_once '../../db.php';

header('Content-Type: application/json');

$section = $_GET['section'] ?? '';
$recipe_id = $_GET['recipe_id'] ?? '';

if (empty($section) || empty($recipe_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing section or recipe ID']);
    exit;
}

// 1. Get Recipe Restrictions
$res_recipe = $conn->query("
    SELECT dr.restriction_name 
    FROM recipe_allergen_tags rat
    JOIN dietary_restrictions dr ON rat.restriction_id = dr.restriction_id
    WHERE rat.recipe_id = '$recipe_id'
");
$recipe_restrictions = [];
while($row = $res_recipe.fetch_assoc()) $recipe_restrictions[] = $row['restriction_name'];

// 2. Get Students in Section and their Restrictions
$res_students = $conn->query("
    SELECT s.student_id, s.first_name, s.last_name, 
           GROUP_CONCAT(dr.restriction_name) as student_restrictions
    FROM student s
    LEFT JOIN student_allergy_map sam ON s.student_id = sam.student_id
    LEFT JOIN dietary_restrictions dr ON sam.restriction_id = dr.restriction_id
    WHERE s.section = '$section'
    GROUP BY s.student_id
");

$conflicts = [];
$total_students = 0;

while($s = $res_students->fetch_assoc()) {
    $total_students++;
    if (empty($s['student_restrictions'])) continue;

    $s_res = explode(',', $s['student_restrictions']);
    $intersect = array_intersect($s_res, $recipe_restrictions);
    
    if (!empty($intersect)) {
        $conflicts[] = [
            'name' => $s['first_name'] . ' ' . $s['last_name'],
            'id' => $s['student_id'],
            'triggers' => array_values($intersect)
        ];
    }
}

echo json_encode([
    'success' => true,
    'total_students' => $total_students,
    'conflict_count' => count($conflicts),
    'conflicts' => $conflicts,
    'is_safe' => count($conflicts) === 0
]);
?>

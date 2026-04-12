<?php
require_once '../../db.php';
header('Content-Type: application/json');

$meal_a = $_GET['meal_a'] ?? '';
$meal_b = $_GET['meal_b'] ?? '';

if (empty($meal_a)) {
    echo json_encode(['success' => false, 'message' => 'Meal A is required']);
    exit;
}

// 1. Get Restrictions for Meal A (NAMES)
$res_a = $conn->query("SELECT dr.restriction_name FROM recipe_allergen_tags rat JOIN dietary_restrictions dr ON rat.restriction_id = dr.restriction_id WHERE rat.recipe_id = '$meal_a'");
$a_restrictions = [];
if ($res_a) while($row = $res_a->fetch_assoc()) $a_restrictions[] = $row['restriction_name'];

// 2. Get Restrictions for Meal B (NAMES)
$b_restrictions = [];
if (!empty($meal_b)) {
    $res_b = $conn->query("SELECT dr.restriction_name FROM recipe_allergen_tags rat JOIN dietary_restrictions dr ON rat.restriction_id = dr.restriction_id WHERE rat.recipe_id = '$meal_b'");
    if ($res_b) while($row = $res_b->fetch_assoc()) $b_restrictions[] = $row['restriction_name'];
}

// 3. Fetch all students
$res_students = $conn->query("
    SELECT s.student_id, s.first_name, s.last_name, s.section,
           GROUP_CONCAT(dr.restriction_name ORDER BY sam.restriction_id) as restriction_names
    FROM student s
    LEFT JOIN student_allergy_map sam ON s.student_id = sam.student_id
    LEFT JOIN dietary_restrictions dr ON sam.restriction_id = dr.restriction_id
    GROUP BY s.student_id
    ORDER BY s.section, s.last_name, s.first_name
");

$can_eat_a_only = []; // Allergic to B only -> forced to A
$can_eat_b_only = []; // Allergic to A only -> forced to B
$can_eat_both  = []; // No restriction on either -> balanced split
$excluded_list = []; // Allergic to both

function getConflicts($student_reqs, $recipe_tags) {
    if (empty($student_reqs)) return [];
    $triggers = [];
    foreach ($student_reqs as $res) {
        if ($res === 'Halal') {
            if (in_array('Non-Halal', $recipe_tags)) $triggers[] = 'Contains Pork/Non-Halal';
        } elseif ($res === 'Vegan') {
            if (!in_array('Vegan', $recipe_tags) && !in_array('Vegetarian', $recipe_tags)) $triggers[] = 'Not Vegan/Vegetarian';
        } elseif ($res === 'Vegetarian') {
            if (!in_array('Vegetarian', $recipe_tags) && !in_array('Vegan', $recipe_tags)) $triggers[] = 'Not Vegetarian';
        } else {
            if (in_array($res, $recipe_tags)) $triggers[] = $res;
        }
    }
    return $triggers;
}

while($s = $res_students->fetch_assoc()) {
    $s_reqs = empty($s['restriction_names']) ? [] : explode(',', $s['restriction_names']);
    
    $triggers_a = getConflicts($s_reqs, $a_restrictions);
    $triggers_b = empty($meal_b) ? ['No Meal B'] : getConflicts($s_reqs, $b_restrictions);

    $conflict_a = !empty($triggers_a);
    $conflict_b = !empty($triggers_b);

    $student_data = [
        'id' => $s['student_id'],
        'name' => $s['first_name'] . ' ' . $s['last_name'],
        'section' => $s['section'],
        'restriction_names' => $s['restriction_names'] ?? ''
    ];

    if (!$conflict_a && !$conflict_b) {
        $can_eat_both[] = $student_data;
    } elseif (!$conflict_a && $conflict_b) {
        $student_data['forced'] = 'meal_a';
        $can_eat_a_only[] = $student_data;
    } elseif ($conflict_a && !$conflict_b) {
        $student_data['forced'] = 'meal_b';
        $student_data['reason'] = 'Conflict with Meal A';
        $can_eat_b_only[] = $student_data;
    } else {
        // Conflict with both
        $merged_triggers = array_unique(array_merge($triggers_a, $triggers_b === ['No Meal B'] ? [] : $triggers_b));
        $student_data['triggers'] = array_values($merged_triggers);
        $excluded_list[] = $student_data;
    }
}

// 4. Balanced even distribution of "can_eat_both" students
$meal_a_list = $can_eat_a_only;
$meal_b_list = $can_eat_b_only;

$toggle = 0;
foreach ($can_eat_both as $s) {
    if ($toggle % 2 === 0) {
        $meal_a_list[] = $s;
    } else {
        $meal_b_list[] = $s;
    }
    $toggle++;
}

echo json_encode([
    'success' => true,
    'meal_a_count' => count($meal_a_list),
    'meal_b_count' => count($meal_b_list),
    'excluded_count' => count($excluded_list),
    'meal_a_list' => $meal_a_list,
    'meal_b_list' => $meal_b_list,
    'excluded_list' => $excluded_list
]);
?>

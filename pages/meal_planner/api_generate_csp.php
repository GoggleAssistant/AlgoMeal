<?php
require_once '../../db.php';

header('Content-Type: application/json');

// --- 1. Fetch Students and their Restrictions ---
$res_students = $conn->query("
    SELECT s.student_id, GROUP_CONCAT(sam.restriction_id) as restriction_ids
    FROM student s
    LEFT JOIN student_allergy_map sam ON s.student_id = sam.student_id
    GROUP BY s.student_id
");

$students = [];
while($row = $res_students->fetch_assoc()) {
    $students[] = [
        'id' => $row['student_id'],
        'reqs' => empty($row['restriction_ids']) ? [] : explode(',', $row['restriction_ids'])
    ];
}

// --- 2. Fetch Recipes and their Rotation History ---
$res_recipes = $conn->query("
    SELECT r.recipe_id, r.base_cost_per_serving, r.energy_kcal,
           GROUP_CONCAT(rat.restriction_id) as restriction_ids,
           (SELECT MAX(scheduled_date) FROM daily_meal_plans 
            WHERE meal_a_recipe_id = r.recipe_id OR meal_b_recipe_id = r.recipe_id) as last_deployed
    FROM recipes r
    LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id
    WHERE r.energy_kcal >= 100 -- Hard Constraint 1 (Relaxed to allow rotation)
    GROUP BY r.recipe_id
");

$recipes = [];
while($row = $res_recipes->fetch_assoc()) {
    $recipes[$row['recipe_id']] = [
        'id' => $row['recipe_id'],
        'cost' => (float)$row['base_cost_per_serving'],
        'kcal' => (int)$row['energy_kcal'],
        'restrictions' => empty($row['restriction_ids']) ? [] : explode(',', $row['restriction_ids']),
        'last_deployed' => $row['last_deployed'] ?? '1970-01-01' // Never used = oldest
    ];
}

$target_date = $_GET['date'] ?? date('Y-m-d');
$banned_recipes = [];
// Fetch Yesterday's Plan to prevent back-to-back repeats
$yesterday = date('Y-m-d', strtotime($target_date . ' -1 day'));
$res_prev = $conn->query("SELECT meal_a_recipe_id, meal_b_recipe_id FROM daily_meal_plans WHERE scheduled_date = '$yesterday'");
if ($row = $res_prev->fetch_assoc()) {
    $banned_recipes[] = $row['meal_a_recipe_id'];
    $banned_recipes[] = $row['meal_b_recipe_id'];
}

// Sort recipes for Meal A rotation: Oldest (or never) deployed first
$recipe_ids_rotated = array_keys($recipes);
usort($recipe_ids_rotated, function($a, $b) use ($recipes) {
    return strcmp($recipes[$a]['last_deployed'], $recipes[$b]['last_deployed']);
});

// For Meal B pairing, we can keep a shuffled or cost-sorted list
$recipe_ids_pairing = array_keys($recipes);
shuffle($recipe_ids_pairing);

// --- 3. CSP Engine Loop ---
$best_pair = null;
$best_score = [
    'excluded' => PHP_INT_MAX,
    'rotation_rank' => PHP_INT_MAX,
    'avg_cost' => PHP_FLOAT_MAX
];

// Outer loop follows strict rotation for Meal A
for ($i = 0; $i < count($recipe_ids_rotated); $i++) {
    $id_a = $recipe_ids_rotated[$i];
    
    // Hard Constraint: No Back-to-Back for Meal A
    if (in_array($id_a, $banned_recipes)) continue;

    for ($j = 0; $j < count($recipe_ids_pairing); $j++) {
        $id_b = $recipe_ids_pairing[$j];
        
        if ($id_a === $id_b) continue; // Must be different recipes
        
        // Hard Constraint: No Back-to-Back for Meal B
        if (in_array($id_b, $banned_recipes)) continue;

        $rec_a = $recipes[$id_a];
        $rec_b = $recipes[$id_b];

        $fed_a = 0;
        $fed_b = 0;
        $excluded = 0;

        foreach ($students as $st) {
            $conflict_a = array_intersect($st['reqs'], $rec_a['restrictions']);
            if (empty($conflict_a)) {
                $fed_a++;
            } else {
                $conflict_b = array_intersect($st['reqs'], $rec_b['restrictions']);
                if (empty($conflict_b)) {
                    $fed_b++;
                } else {
                    $excluded++;
                }
            }
        }

        $total_fed = $fed_a + $fed_b;
        $total_cost = ($fed_a * $rec_a['cost']) + ($fed_b * $rec_b['cost']);
        $avg_cost = $total_fed > 0 ? ($total_cost / $total_fed) : PHP_FLOAT_MAX;

        // --- Heuristic Evaluation ---
        // 1. Primary: Rotation Priority (Strictly follow oldest recipes).
        // 2. Secondary: Fewest absolute exclusions.
        // 3. Tertiary: Lowering the average cost per serving.
        
        // Safety: If this Meal A excludes more than 50% of total students, it's a poor anchor.
        if ($excluded > (count($students) / 2)) continue;

        $is_better = false;
        
        if ($i < $best_score['rotation_rank']) {
            // New recipe is earlier in rotation sequence
            $is_better = true;
        } else if ($i === $best_score['rotation_rank']) {
            // Same Meal A, look for better Meal B pairing
            if ($excluded < $best_score['excluded']) {
                $is_better = true;
            } else if ($excluded === $best_score['excluded']) {
                if ($avg_cost < $best_score['avg_cost']) {
                    $is_better = true;
                }
            }
        }

        if ($is_better) {
            $best_score = [
                'rotation_rank' => $i,
                'excluded' => $excluded,
                'avg_cost' => $avg_cost
            ];
            $best_pair = [
                'meal_a' => $id_a,
                'meal_b' => $id_b
            ];
        }
    }
}

if ($best_pair) {
    echo json_encode([
        'success' => true,
        'meal_a' => $best_pair['meal_a'],
        'meal_b' => $best_pair['meal_b'],
        'stats' => $best_score
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'CSP was unable to find any valid combinations based on hard constraints.']);
}
?>

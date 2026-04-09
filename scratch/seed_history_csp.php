<?php
require_once __DIR__ . '/../db.php';

echo "Starting Enhanced CSP History Seeding (with constraint tracking)...\n<br>";

// 1. Clean Slate
$conn->query("DELETE FROM meal_plan");
$conn->query("DELETE FROM daily_meal_plans");

// 2. Get Students
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

// 3. Get Recipes
$res_recipes = $conn->query("
    SELECT r.recipe_id, r.base_cost_per_serving, r.energy_kcal,
           GROUP_CONCAT(rat.restriction_id) as restriction_ids
    FROM recipes r
    LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id
    WHERE r.energy_kcal >= 300
    GROUP BY r.recipe_id
");
$recipes = [];
while($row = $res_recipes->fetch_assoc()) {
    $recipes[$row['recipe_id']] = [
        'cost' => (float)$row['base_cost_per_serving'],
        'kcal' => (int)$row['energy_kcal'],
        'restrictions' => empty($row['restriction_ids']) ? [] : explode(',', $row['restriction_ids'])
    ];
}
$recipe_ids = array_keys($recipes);

// Function to find best CSP pair subject to banned list
function findBestPair($recipes, $recipe_ids, $students, $banned) {
    $best_pair = null;
    $best_score = ['excluded' => PHP_INT_MAX, 'avg_cost' => PHP_FLOAT_MAX];

    // Introducing Random Variety
    shuffle($recipe_ids);

    for ($i = 0; $i < count($recipe_ids); $i++) {
        for ($j = 0; $j < count($recipe_ids); $j++) {
            if ($i === $j) continue;

            $id_a = $recipe_ids[$i]; 
            $id_b = $recipe_ids[$j];
            
            // Apply Ban List Constraint
            if (in_array($id_a, $banned) || in_array($id_b, $banned)) continue;

            $rec_a = $recipes[$id_a]; $rec_b = $recipes[$id_b];
            $fed_a = 0; $fed_b = 0; $excluded = 0;

            foreach ($students as $st) {
                if (empty(array_intersect($st['reqs'], $rec_a['restrictions']))) {
                    $fed_a++;
                } else if (empty(array_intersect($st['reqs'], $rec_b['restrictions']))) {
                    $fed_b++;
                } else {
                    $excluded++;
                }
            }
            $total_fed = $fed_a + $fed_b;
            $avg_cost = $total_fed > 0 ? (($fed_a * $rec_a['cost']) + ($fed_b * $rec_b['cost'])) / $total_fed : PHP_FLOAT_MAX;

            $is_better = false;
            // Introduce slight randomization / cycling priority so that days don't infinitely cycle the exact same two alternatives
            // To do this we can introduce a micro-penalty based on ID to force array shifting if scores are heavily tied
            if ($excluded < $best_score['excluded']) {
                $is_better = true;
            } else if ($excluded === $best_score['excluded'] && $avg_cost < $best_score['avg_cost']) {
                $is_better = true;
            }

            if ($is_better) {
                $best_score = ['excluded' => $excluded, 'avg_cost' => $avg_cost];
                $best_pair = ['a' => $id_a, 'b' => $id_b];
            }
        }
    }
    return $best_pair;
}


// 4. Generate Dates
$start = new DateTime('2026-03-01');
$end = new DateTime('2026-04-09'); // Include today!
$interval = new DateInterval('P1D');
$period = new DatePeriod($start, $interval, $end->modify('+1 day'));

$conn->begin_transaction();
try {
    $stmt_students = $conn->prepare("INSERT INTO meal_plan (student_id, recipe_id, scheduled_date, actual_cost, feeding_status) VALUES (?, ?, ?, ?, 'Served')");
    $stmt_daily = $conn->prepare("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id, meal_b_recipe_id) VALUES (?, ?, ?)");
    
    $count = 0;
    
    // We must track the banned array chronologically as we simulate the engine sequentially 
    $last_day_banned = [];
    
    // We can also track a "recently used within 3 days" to force higher variety since we have 10 recipes
    $recent_history = [];

    foreach ($period as $date) {
        // Skip weekends
        if ($date->format('N') >= 6) continue;
        
        $dStr = $date->format('Y-m-d');
        
        // Build dynamic strict ban list from recent history to force rotating colors/recipes
        $active_bans = array_merge($last_day_banned, $recent_history);
        
        $bestCSP = findBestPair($recipes, $recipe_ids, $students, $active_bans);
        
        // If we run out of valid recipes due to the cyclic strict ban, just use the previous day ban exclusively
        if (!$bestCSP) {
            $bestCSP = findBestPair($recipes, $recipe_ids, $students, $last_day_banned);
        }

        if (!$bestCSP) {
            die("Critical failure for date $dStr. No valid recipes exist.");
        }
        
        $cost_a = $recipes[$bestCSP['a']]['cost'];
        $cost_b = $recipes[$bestCSP['b']]['cost'];
        
        // Save Daily Global state
        $stmt_daily->bind_param("sss", $dStr, $bestCSP['a'], $bestCSP['b']);
        $stmt_daily->execute();

        // Assign individual students
        foreach ($students as $st) {
            $sid = $st['id'];
            $reqs = $st['reqs'];
            
            if (empty(array_intersect($reqs, $recipes[$bestCSP['a']]['restrictions']))) {
                $stmt_students->bind_param("sssd", $sid, $bestCSP['a'], $dStr, $cost_a);
                $stmt_students->execute();
            } else if (empty(array_intersect($reqs, $recipes[$bestCSP['b']]['restrictions']))) {
                $stmt_students->bind_param("sssd", $sid, $bestCSP['b'], $dStr, $cost_b);
                $stmt_students->execute();
            }
        }
        
        // Update trackers
        $last_day_banned = [$bestCSP['a'], $bestCSP['b']];
        // Shift recent history, keeping max 4 items (2 previous days) to force maximum color variety
        array_unshift($recent_history, $bestCSP['a'], $bestCSP['b']);
        $recent_history = array_slice($recent_history, 0, 4);

        $count++;
    }
    
    $conn->commit();
    echo "Successfully wiped old data and re-seeded $count days of strictly-rotated historical meal data!<br>";

} catch (Exception $e) {
    $conn->rollback();
    echo "Error: " . $e->getMessage();
}
?>

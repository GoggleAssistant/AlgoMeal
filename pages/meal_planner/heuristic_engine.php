<?php
// heuristic_engine.php
// Core logic for Generating a Meal Plan for a Specific Date using Heuristics

function generate_plan_for_date($conn, $target_date, $budget_limit)
{
    // 1. Get all Active Recipes with Last Served info for Variety Score
    // Variety Rule: 0 if served in last 2 days (today/yesterday relative to target date). 
    // +10 per day after. Max out at some cap.

    // We get last served and frequency in the last 14 days relative to $target_date
    $q_recipes = "
        SELECT r.*,
               GROUP_CONCAT(DISTINCT rat.restriction_id) as restriction_ids,
               (SELECT MAX(dp.scheduled_date) 
                FROM daily_meal_plans dp 
                WHERE (dp.meal_a_recipe_id = r.recipe_id OR dp.meal_b_recipe_id = r.recipe_id)
                  AND dp.scheduled_date < ?) as last_served,
               (SELECT COUNT(*) 
                FROM daily_meal_plans dp 
                WHERE (dp.meal_a_recipe_id = r.recipe_id OR dp.meal_b_recipe_id = r.recipe_id)
                  AND dp.scheduled_date >= DATE_SUB(?, INTERVAL 14 DAY)
                  AND dp.scheduled_date < ?) as recent_count
        FROM recipes r
        LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id
        WHERE r.category != 'Snack'
        GROUP BY r.recipe_id
    ";

    $stmt = $conn->prepare($q_recipes);
    $stmt->bind_param('sss', $target_date, $target_date, $target_date);
    $stmt->execute();
    $res_recipes = $stmt->get_result();

    // -- NEW: RECENT CATEGORY TRACKING --
    // Find the categories of the most recent plan session to prevent category fatigue
    $recent_cats = [];
    $q_last_cats = "
        SELECT r.category 
        FROM daily_meal_plans dp
        JOIN recipes r ON (dp.meal_a_recipe_id = r.recipe_id OR dp.meal_b_recipe_id = r.recipe_id)
        WHERE dp.scheduled_date = (SELECT MAX(scheduled_date) FROM daily_meal_plans WHERE scheduled_date < ?)
    ";
    $stmt_lc = $conn->prepare($q_last_cats);
    $stmt_lc->bind_param('s', $target_date);
    $stmt_lc->execute();
    $res_lc = $stmt_lc->get_result();
    while ($lc = $res_lc->fetch_assoc())
        $recent_cats[] = $lc['category'];


    $recipes_debug_logs = [];
    $recipes = [];
    while ($row = $res_recipes->fetch_assoc()) {
        $row['restriction_ids'] = $row['restriction_ids'] ? array_filter(explode(',', $row['restriction_ids'])) : [];

        $score = 0;
        $log = [];

        // 1. Dietary restrictions base penalty
        $restr_penalty = count($row['restriction_ids']);
        $score -= $restr_penalty;
        $log['restrictions'] = -$restr_penalty;

        // 2. Variety Buff (+5 points per day)
        if ($row['last_served']) {
            $diff = strtotime($target_date) - strtotime($row['last_served']);
            $days = floor($diff / 86400); // Days since last served
            $days = max(0, $days);
            $var_buff = ($days * 5);
            $score += $var_buff;
            $log['variety'] = $var_buff;
        } else {
            // Massive buff if completely unserved (simulates 30 unserved days)
            $var_buff = (30 * 5);
            $score += $var_buff;
            $log['variety'] = $var_buff;
        }

        // 3. Category Fatigue Penalty
        $cat_pen = 0;
        if (in_array($row['category'], $recent_cats)) {
            $cat_pen = -10;
            $score += $cat_pen;
        }
        $log['category_fatigue'] = $cat_pen;

        // 4. Nutritional Bonuses
        // +2 points for every 50 kcal
        $nut_kcal = (floor($row['energy_kcal'] / 50) * 2);
        // +1 point for every 5g protein
        $nut_prot = (floor($row['protein_g'] / 5) * 1);
        $score += ($nut_kcal + $nut_prot);
        $log['nutrition'] = ($nut_kcal + $nut_prot);

        $row['score'] = $score;
        $recipes[] = $row;

        $log['name'] = $row['recipe_name'];
        $log['total'] = $score;
        $recipes_debug_logs[] = $log;
    }

    // Sort debug logs for UI
    usort($recipes_debug_logs, function ($a, $b) {
        return $b['total'] <=> $a['total'];
    });

    // ----------------------------------------------------
    // PARALLEL SNACK MINI-RECOMMENDATION SYSTEM
    // ----------------------------------------------------
    $q_snacks = "
        SELECT r.*, GROUP_CONCAT(DISTINCT rat.restriction_id) as restriction_ids,
               (SELECT MAX(scheduled_date) 
                FROM daily_meal_plans dp 
                WHERE dp.snack_recipe_id = r.recipe_id
                  AND dp.scheduled_date < ?) as last_served
        FROM recipes r
        LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id
        WHERE r.category = 'Snack'
        GROUP BY r.recipe_id
    ";

    $stmt_snacks = $conn->prepare($q_snacks);
    $stmt_snacks->bind_param('s', $target_date);
    $stmt_snacks->execute();
    $res_snacks = $stmt_snacks->get_result();

    $snacks = [];
    while ($row = $res_snacks->fetch_assoc()) {
        $row['restriction_ids'] = $row['restriction_ids'] ? array_filter(explode(',', $row['restriction_ids'])) : [];
        $score = 0;

        $restr_penalty = count($row['restriction_ids']);
        $score -= $restr_penalty;

        if ($row['last_served']) {
            $diff = strtotime($target_date) - strtotime($row['last_served']);
            $score += (max(0, floor($diff / 86400)) * 5);
        } else {
            $score += (30 * 5);
        }

        $score += (floor($row['energy_kcal'] / 50) * 2);
        $score += (floor($row['protein_g'] / 5) * 1);

        $row['score'] = $score;
        $snacks[] = $row;
    }

    usort($snacks, function ($a, $b) {
        if ($a['score'] == $b['score'])
            return strcmp($a['recipe_id'], $b['recipe_id']);
        return $b['score'] <=> $a['score'];
    });


    // 2. Get Students and their restrictions and current BMI/Nutritional Status to find Targets
    $q_students = "
        SELECT s.student_id,
               GROUP_CONCAT(sam.restriction_id) as restriction_ids,
               (SELECT nutritional_status FROM nutritional_record nr WHERE nr.student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as nut_status
        FROM student s
        LEFT JOIN student_allergy_map sam ON s.student_id = sam.student_id
        GROUP BY s.student_id
    ";
    $res_students = $conn->query($q_students);
    $students = [];
    $underweight_count = 0;
    while ($row = $res_students->fetch_assoc()) {
        $row['restriction_ids'] = $row['restriction_ids'] ? array_filter(explode(',', $row['restriction_ids'])) : [];
        if ($row['nut_status'] === 'Severely Wasted' || $row['nut_status'] === 'Wasted' || stripos($row['nut_status'], 'Underweight') !== false) {
            $underweight_count++;
        }
        $students[] = $row;
    }

    $total_students = count($students);
    if ($total_students === 0)
        return ['success' => false, 'message' => 'No students found.'];

    // Define Nutritional Target per student
    // Standard DepEd baseline ~ 500 kcal for lunch
    $target_kcal = 500;
    $target_prot = 15;

    // Rule: If >50% are underweight, boost targets by 15%
    if ($total_students > 0 && ($underweight_count / $total_students) > 0.5) {
        $target_kcal *= 1.15;
        $target_prot *= 1.15;
    }

    // Cost cap (used as informational/soft weight, NOT a hard disqualifier)
    $max_cost_per_head = $total_students > 0 ? ($budget_limit / $total_students) : 999;

    // Semantic conflict map: student restriction ID => recipe tag IDs that conflict with it
    // e.g. A student requiring Halal (9) conflicts with a recipe tagged Non-Halal (17)
    $conflict_map = [
        9 => [17], // Halal student → conflicts with Non-Halal recipe tag
        16 => [17], // Red Meat-Free student → conflicts with Non-Halal (pork/meat) recipe tag
    ];

    // Helper to evaluate a recipe for a list of students
    $evaluateMeal = function ($recipe, $student_list) use ($conflict_map) {
        $can_eat = [];
        $cannot_eat = [];
        foreach ($student_list as $s) {
            $conflict = false;
            foreach ($s['restriction_ids'] as $res_id) {
                // Direct match: student restriction tag matches recipe tag (e.g. Peanut Allergy on both)
                if (in_array($res_id, $recipe['restriction_ids'])) {
                    $conflict = true;
                    break;
                }
                // Semantic match: student has a restriction that semantically conflicts
                if (isset($conflict_map[$res_id])) {
                    foreach ($conflict_map[$res_id] as $conflicting_tag) {
                        if (in_array($conflicting_tag, $recipe['restriction_ids'])) {
                            $conflict = true;
                            break 2;
                        }
                    }
                }
            }
            if ($conflict)
                $cannot_eat[] = $s;
            else
                $can_eat[] = $s;
        }
        return ['can_eat' => $can_eat, 'cannot_eat' => $cannot_eat];
    };


    // 3. DETERMINE PLAN OUTCOME
    // Sort recipes by score DESCENDING
    usort($recipes, function ($a, $b) {
        if ($a['score'] == $b['score']) {
            // Tie-breaker: ID string compare to be deterministic
            return strcmp($a['recipe_id'], $b['recipe_id']);
        }
        return $b['score'] <=> $a['score'];
    });

    if (count($recipes) === 0) {
        return ['success' => false, 'message' => 'No active recipes could be analyzed.'];
    }

    // Meal A is always the highest scoring recipe
    $meal_a = $recipes[0];

    // Evaluate students against Meal A
    $eval_a = $evaluateMeal($meal_a, $students);

    if (count($eval_a['cannot_eat']) === 0) {
        // ------------------
        // Determine Snack via Mini-Heuristic
        // ------------------
        $snack_id = null;
        $snack_blacklist = [];
        if (count($snacks) > 0) {
            $best_snack = $snacks[0];
            $snack_id = $best_snack['recipe_id'];
            $eval_snack = $evaluateMeal($best_snack, $students);
            $snack_blacklist = array_column($eval_snack['cannot_eat'], 'student_id');
        }

        // Everyone can eat Meal A! Single meal plan.
        return [
            'success' => true,
            'meal_a' => $meal_a['recipe_id'],
            'meal_b' => null,
            'snack' => $snack_id,
            'snack_blacklist' => $snack_blacklist,
            'meal_a_list' => array_column($eval_a['can_eat'], 'student_id'),
            'meal_b_list' => [],
            'type' => 'single',
            'debug' => array_slice($recipes_debug_logs, 0, 15)
        ];
    } else {
        // Some students cannot eat Meal A. We need a Meal B for them.
        // Find the next highest scoring recipe that ALL excluded students can eat.
        // To find the best Meal B, we clone the recipes (except Meal A) 
        // and apply an intra-day category variety penalty (-10) before picking.
        $b_candidates = [];
        for ($i = 1; $i < count($recipes); $i++) {
            $c = $recipes[$i];
            if ($c['category'] === $meal_a['category']) {
                $c['score'] -= 10;
            }
            $b_candidates[] = $c;
        }

        // Re-sort candidates for Meal B based on adjusted scores
        usort($b_candidates, function ($a, $b) {
            if ($a['score'] == $b['score'])
                return strcmp($a['recipe_id'], $b['recipe_id']);
            return $b['score'] <=> $a['score'];
        });

        $meal_b = null;
        $meal_b_students = [];

        foreach ($b_candidates as $candidate_b) {
            // Check if candidate covers students who can't eat Meal A
            $eval_b = $evaluateMeal($candidate_b, $eval_a['cannot_eat']);

            if (count($eval_b['cannot_eat']) === 0) {
                $meal_b = $candidate_b;
                $meal_b_students = $eval_b['can_eat'];
                break;
            }
        }

        // ------------------
        // Determine Snack via Mini-Heuristic
        // ------------------
        $snack_id = null;
        $snack_blacklist = [];
        if (count($snacks) > 0) {
            $best_snack = $snacks[0];
            $snack_id = $best_snack['recipe_id'];
            $eval_snack = $evaluateMeal($best_snack, $students);
            $snack_blacklist = array_column($eval_snack['cannot_eat'], 'student_id');
        }

        if ($meal_b) {
            // Found a working pair.
            // Students who can eat Meal A stay on Meal A. The rest get Meal B.
            return [
                'success' => true,
                'meal_a' => $meal_a['recipe_id'],
                'meal_b' => $meal_b['recipe_id'],
                'snack' => $snack_id,
                'snack_blacklist' => $snack_blacklist,
                'meal_a_list' => array_column($eval_a['can_eat'], 'student_id'),
                'meal_b_list' => array_column($meal_b_students, 'student_id'),
                'type' => 'conflict_pair',
                'debug' => array_slice($recipes_debug_logs, 0, 15)
            ];
        } else {
            // Failsafe: if NO Meal B covers the remaining students perfectly,
            // just serve the highest scoring remaining meal anyway.
            $meal_b = $recipes[1];
            $eval_b = $evaluateMeal($meal_b, $eval_a['cannot_eat']);
            return [
                'success' => true,
                'meal_a' => $meal_a['recipe_id'],
                'meal_b' => $meal_b['recipe_id'],
                'snack' => $snack_id,
                'snack_blacklist' => $snack_blacklist,
                'meal_a_list' => array_column($eval_a['can_eat'], 'student_id'),
                'meal_b_list' => array_column($eval_b['can_eat'], 'student_id'),
                'type' => 'fallback_pair',
                'debug' => array_slice($recipes_debug_logs, 0, 15)
            ];
        }
    }

    return ['success' => false, 'message' => 'No active recipes could be analyzed.', 'debug' => array_slice($recipes_debug_logs, 0, 15)];
}
?>
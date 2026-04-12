<?php
// heuristic_engine.php
// Core logic for Generating a Meal Plan for a Specific Date using Heuristics

function generate_plan_for_date($conn, $target_date, $budget_limit) {
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
        GROUP BY r.recipe_id
    ";
    
    $stmt = $conn->prepare($q_recipes);
    $stmt->bind_param('sss', $target_date, $target_date, $target_date);
    $stmt->execute();
    $res_recipes = $stmt->get_result();
    
    $recipes = [];
    while ($row = $res_recipes->fetch_assoc()) {
        $row['restriction_ids'] = $row['restriction_ids'] ? array_filter(explode(',', $row['restriction_ids'])) : [];
        
        // --- NEW POINT SYSTEM ---
        // 1. Initial 10 points
        $score = 10;
        
        // 2. Minus 1 point for every dietary restriction
        $score -= count($row['restriction_ids']);
        
        // 3. +2 points for every day it hasn't shown up
        if ($row['last_served']) {
            $diff = strtotime($target_date) - strtotime($row['last_served']);
            $days = floor($diff / 86400); // Days since last served
            // Ensure no negative days if generating in the past out of order
            $days = max(0, $days);
            $score += ($days * 2);
        } else {
            // If never served, treat it as 14 days unserved to give it a starting buff
            $score += (14 * 2); 
        }
        
        $row['score'] = $score;
        $recipes[] = $row;
    }

    
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
    if ($total_students === 0) return ['success' => false, 'message' => 'No students found.'];
    
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
        9  => [17], // Halal student → conflicts with Non-Halal recipe tag
        16 => [17], // Red Meat-Free student → conflicts with Non-Halal (pork/meat) recipe tag
    ];

    // Helper to evaluate a recipe for a list of students
    $evaluateMeal = function($recipe, $student_list) use ($conflict_map) {
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
            if ($conflict) $cannot_eat[] = $s; else $can_eat[] = $s;
        }
        return ['can_eat' => $can_eat, 'cannot_eat' => $cannot_eat];
    };


    // 3. DETERMINE PLAN OUTCOME
    // Sort recipes by score DESCENDING
    usort($recipes, function($a, $b) {
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
        // Everyone can eat Meal A! Single meal plan.
        return [
            'success'     => true,
            'meal_a'      => $meal_a['recipe_id'],
            'meal_b'      => null,
            'meal_a_list' => array_column($eval_a['can_eat'], 'student_id'),
            'meal_b_list' => [],
            'type'        => 'single'
        ];
    } else {
        // Some students cannot eat Meal A. We need a Meal B for them.
        // Find the next highest scoring recipe that ALL excluded students can eat.
        $meal_b = null;
        $meal_b_students = [];
        
        for ($i = 1; $i < count($recipes); $i++) {
            $candidate_b = $recipes[$i];
            
            // Check if candidate covers students who can't eat Meal A
            $eval_b = $evaluateMeal($candidate_b, $eval_a['cannot_eat']);
            
            if (count($eval_b['cannot_eat']) === 0) {
                // Works perfectly for all excluded users!
                $meal_b = $candidate_b;
                $meal_b_students = $eval_b['can_eat'];
                break;
            }
        }
        
        if ($meal_b) {
            // Found a working pair.
            // Students who can eat Meal A stay on Meal A. The rest get Meal B.
            return [
                'success'     => true,
                'meal_a'      => $meal_a['recipe_id'],
                'meal_b'      => $meal_b['recipe_id'],
                'meal_a_list' => array_column($eval_a['can_eat'], 'student_id'),
                'meal_b_list' => array_column($meal_b_students, 'student_id'),
                'type'        => 'conflict_pair'
            ];
        } else {
            // Failsafe: if NO Meal B covers the remaining students perfectly,
            // just serve the highest scoring remaining meal anyway.
            $meal_b = $recipes[1];
            $eval_b = $evaluateMeal($meal_b, $eval_a['cannot_eat']);
            return [
                'success'     => true,
                'meal_a'      => $meal_a['recipe_id'],
                'meal_b'      => $meal_b['recipe_id'],
                'meal_a_list' => array_column($eval_a['can_eat'], 'student_id'),
                'meal_b_list' => array_column($eval_b['can_eat'], 'student_id'),
                'type'        => 'fallback_pair'
            ];
        }
    }
}
?>

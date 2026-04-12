<?php
// api_update_day_recipes.php
require_once '../../db.php';
require_once 'heuristic_engine.php'; // We can't reuse the whole function, but we can reuse the evaluator logic.

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['date']) || !isset($data['meal_a'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

$date = $data['date'];
$meal_a = $data['meal_a'];
$meal_b = $data['meal_b'] ?? null;

$conn->begin_transaction();
try {
    // 1. Get recipes data
    $q_recipes = "
        SELECT r.*, GROUP_CONCAT(DISTINCT rat.restriction_id) as restriction_ids 
        FROM recipes r 
        LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id 
        WHERE r.recipe_id IN (?, ?) 
        GROUP BY r.recipe_id
    ";
    $stmt_r = $conn->prepare($q_recipes);
    $empty = '';
    $temp_b = $meal_b ?: $empty;
    $stmt_r->bind_param('ss', $meal_a, $temp_b);
    $stmt_r->execute();
    $res_r = $stmt_r->get_result();
    $recipes = [];
    while($row = $res_r->fetch_assoc()) {
        $row['restriction_ids'] = $row['restriction_ids'] ? explode(',', $row['restriction_ids']) : [];
        $recipes[$row['recipe_id']] = $row;
    }
    
    if(!isset($recipes[$meal_a])) throw new Exception("Meal A recipe not found.");
    $rA = $recipes[$meal_a];
    $rB = $meal_b && isset($recipes[$meal_b]) ? $recipes[$meal_b] : null;

    // 2. Get students
    $q_students = "
        SELECT s.student_id, GROUP_CONCAT(sam.restriction_id) as restriction_ids
        FROM meal_plan m
        JOIN student s ON m.student_id = s.student_id
        LEFT JOIN student_allergy_map sam ON s.student_id = sam.student_id
        WHERE m.scheduled_date = ?
        GROUP BY s.student_id
    ";
    $stmt_s = $conn->prepare($q_students);
    $stmt_s->bind_param('s', $date);
    $stmt_s->execute();
    $res_s = $stmt_s->get_result();
    $students = [];
    while($row = $res_s->fetch_assoc()) {
        $row['restriction_ids'] = $row['restriction_ids'] ? explode(',', $row['restriction_ids']) : [];
        $students[] = $row;
    }
    
    // Evaluate helper
    $evaluateMeal = function($recipe, $student_list) {
        $can_eat = []; $cannot_eat = [];
        foreach ($student_list as $s) {
            $conflict = false;
            foreach ($s['restriction_ids'] as $res_id) {
                if (in_array($res_id, $recipe['restriction_ids'])) {
                    $conflict = true; break;
                }
            }
            if ($conflict) $cannot_eat[] = $s; else $can_eat[] = $s;
        }
        return ['can_eat' => $can_eat, 'cannot_eat' => $cannot_eat];
    };

    // Re-Allocation
    $final_a_list = [];
    $final_b_list = [];
    
    $evalA = $evaluateMeal($rA, $students);
    
    if(!$rB) {
        // If no meal B was passed, everybody goes to A (warnings will just show up).
        $final_a_list = $students;
    } else {
        $evalB = $evaluateMeal($rB, $evalA['cannot_eat']);
        $final_a_list = $evalA['cannot_eat']; // wait, no.
        // Actually:
        $final_b_list = $evalB['can_eat']; // students who can't eat A but can eat B
        
        $both_eval = $evaluateMeal($rB, $evalA['can_eat']);
        $can_eat_both = $both_eval['can_eat'];
        $a_only = $both_eval['cannot_eat'];
        
        $final_a_list = $a_only;
        
        shuffle($can_eat_both);
        $half = floor(count($can_eat_both) / 2);
        $final_a_list = array_merge($final_a_list, array_slice($can_eat_both, 0, $half));
        $final_b_list = array_merge($final_b_list, array_slice($can_eat_both, $half));
        
        // Anyone totally excluded? Put them in A by default. (Or whatever)
        $fully_excluded = $evalB['cannot_eat'];
        $final_a_list = array_merge($final_a_list, $fully_excluded);
    }
    
    // 3. Update DB
    $stmt_u1 = $conn->prepare("UPDATE daily_meal_plans SET meal_a_recipe_id = ?, meal_b_recipe_id = ? WHERE scheduled_date = ?");
    $stmt_u1->bind_param('sss', $meal_a, $meal_b, $date);
    $stmt_u1->execute();
    
    $stmt_del = $conn->prepare("DELETE FROM meal_plan WHERE scheduled_date = ?");
    $stmt_del->bind_param('s', $date);
    $stmt_del->execute();
    
    $insert_meal_plan = $conn->prepare("INSERT INTO meal_plan (student_id, recipe_id, scheduled_date, actual_cost) VALUES (?, ?, ?, ?)");
    $cost_a = $rA['base_cost_per_serving'];
    foreach($final_a_list as $s) {
        $insert_meal_plan->bind_param('sssd', $s['student_id'], $meal_a, $date, $cost_a);
        $insert_meal_plan->execute();
    }
    
    if($rB) {
        $cost_b = $rB['base_cost_per_serving'];
        foreach($final_b_list as $s) {
            $insert_meal_plan->bind_param('sssd', $s['student_id'], $meal_b, $date, $cost_b);
            $insert_meal_plan->execute();
        }
    }
    
    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

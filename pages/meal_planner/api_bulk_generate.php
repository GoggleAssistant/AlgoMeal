<?php
// api_bulk_generate.php
require_once '../../db.php';
require_once 'heuristic_engine.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['start_date']) || !isset($data['days_count']) || !isset($data['weekdays'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit;
}

$start_date = $data['start_date'];
$days_count = (int)$data['days_count'];
$weekdays = $data['weekdays']; // e.g. [1,3,5] for Mon, Wed, Fri (0 = Sunday in PHP date('w'))
$overwrite = isset($data['overwrite']) ? (bool)$data['overwrite'] : false;

$res_settings = $conn->query("SELECT setting_value FROM settings WHERE setting_key='total_daily_budget'");
$budget_limit = $res_settings->num_rows > 0 ? (float)$res_settings->fetch_assoc()['setting_value'] : 500.00;

$current_date = strtotime($start_date);
$generated_days = 0;
$skipped_days = 0;
$error_days = 0;
$generated_summary = [];

$total_planned = 0;

$conn->begin_transaction();

try {
    while ($total_planned < $days_count) {
        // Safe-guard to prevent infinite loop
        if (($current_date - strtotime($start_date)) > (365 * 24 * 60 * 60)) break;

        $w_day = (int)date('w', $current_date);
        $date_str = date('Y-m-d', $current_date);
        
        if (in_array($w_day, $weekdays)) {
            // It's a valid feeding day
            $stmt = $conn->prepare("SELECT scheduled_date, is_served FROM daily_meal_plans WHERE scheduled_date = ?");
            $stmt->bind_param('s', $date_str);
            $stmt->execute();
            $stmt_res = $stmt->get_result();
            $exists = $stmt_res->num_rows > 0;
            $existing_row = $exists ? $stmt_res->fetch_assoc() : null;
            
            if ($exists && !$overwrite) {
                // Skip
                $skipped_days++;
                $total_planned++;
            } else {
                if ($exists && $overwrite) {
                    if ($existing_row['is_served'] == 1) {
                        $skipped_days++;
                        $total_planned++;
                        continue;
                    }
                    $stmt_del1 = $conn->prepare("DELETE FROM meal_plan WHERE scheduled_date = ?");
                    $stmt_del1->bind_param('s', $date_str);
                    $stmt_del1->execute();
                    
                    $stmt_del2 = $conn->prepare("DELETE FROM daily_meal_plans WHERE scheduled_date = ?");
                    $stmt_del2->bind_param('s', $date_str);
                    $stmt_del2->execute();
                }
                
                // Generate new one
                $plan_data = generate_plan_for_date($conn, $date_str, $budget_limit);
                if ($plan_data['success']) {
                    $meal_a = $plan_data['meal_a'];
                    $meal_b = $plan_data['meal_b']; // Can be NULL
                    $snack  = $plan_data['snack'] ?? null;

                    $stmt_i = $conn->prepare("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id, meal_b_recipe_id, snack_recipe_id) VALUES (?, ?, ?, ?)");
                    $stmt_i->bind_param('ssss', $date_str, $meal_a, $meal_b, $snack);
                    $stmt_i->execute();

                    $insert_meal_plan = $conn->prepare("INSERT INTO meal_plan (student_id, recipe_id, scheduled_date, actual_cost, with_snack) VALUES (?, ?, ?, ?, ?)");
                    
                    $q_costs = "SELECT recipe_id, base_cost_per_serving FROM recipes WHERE recipe_id IN (?, ?)";
                    $stmt_c = $conn->prepare($q_costs);
                    $empty = '';
                    $temp_b = $meal_b ?: $empty;
                    $stmt_c->bind_param('ss', $meal_a, $temp_b);
                    $stmt_c->execute();
                    $costs_res = $stmt_c->get_result();
                    $costs = [];
                    while ($cr = $costs_res->fetch_assoc()) {
                        $costs[$cr['recipe_id']] = $cr['base_cost_per_serving'];
                    }

                    $cost_a = $costs[$meal_a] ?? 0;
                    $blacklist = $plan_data['snack_blacklist'] ?? [];

                    foreach ($plan_data['meal_a_list'] as $s_id) {
                        $snack_flag = in_array($s_id, $blacklist) ? 0 : 1;
                        $insert_meal_plan->bind_param('sssdi', $s_id, $meal_a, $date_str, $cost_a, $snack_flag);
                        $insert_meal_plan->execute();
                    }

                    if ($meal_b && isset($costs[$meal_b])) {
                        $cost_b = $costs[$meal_b];
                        foreach ($plan_data['meal_b_list'] as $s_id) {
                            $snack_flag = in_array($s_id, $blacklist) ? 0 : 1;
                            $insert_meal_plan->bind_param('sssdi', $s_id, $meal_b, $date_str, $cost_b, $snack_flag);
                            $insert_meal_plan->execute();
                        }
                    }
                    
                    // Add to summary
                    $generated_summary[] = [
                        'date' => $date_str,
                        'meal_a' => $meal_a,
                        'meal_b' => $meal_b
                    ];
                    
                    $generated_days++;
                } else {
                    $error_days++;
                }
                $total_planned++;
            }
        }
        $current_date = strtotime('+1 day', $current_date);
    }
    
    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => "Bulk Generation Complete: $generated_days Generated, $skipped_days Skipped, $error_days Failed.",
        'stats' => ['generated' => $generated_days, 'skipped' => $skipped_days, 'failed' => $error_days],
        'details' => $generated_summary
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

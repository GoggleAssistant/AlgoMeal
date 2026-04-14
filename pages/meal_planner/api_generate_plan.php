<?php
// api_generate_plan.php
require_once '../../db.php';
require_once 'heuristic_engine.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['date'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data. Date is required.']);
    exit;
}

$date = $data['date'];

// Get Budget limit
$res_settings = $conn->query("SELECT setting_value FROM settings WHERE setting_key='total_daily_budget'");
$budget_limit = $res_settings->num_rows > 0 ? (float)$res_settings->fetch_assoc()['setting_value'] : 500.00;

// Run Engine
$plan_data = generate_plan_for_date($conn, $date, $budget_limit);

if (!$plan_data['success']) {
    echo json_encode($plan_data);
    exit;
}

// Transaction
$conn->begin_transaction();
try {
    // Check if plan exists
    $stmt = $conn->prepare("SELECT scheduled_date FROM daily_meal_plans WHERE scheduled_date = ?");
    $stmt->bind_param('s', $date);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("A meal plan already exists for this date. You must overwrite or skip.");
    }

    $meal_a = $plan_data['meal_a'];
    $meal_b = $plan_data['meal_b']; // Can be NULL
    $snack  = $plan_data['snack'] ?? null; // Can be NULL

    $stmt = $conn->prepare("INSERT INTO daily_meal_plans (scheduled_date, meal_a_recipe_id, meal_b_recipe_id, snack_recipe_id) VALUES (?, ?, ?, ?)");
    $stmt->bind_param('ssss', $date, $meal_a, $meal_b, $snack);
    $stmt->execute();

    // Insert student assignments
    $insert_meal_plan = $conn->prepare("INSERT INTO meal_plan (student_id, recipe_id, scheduled_date, actual_cost, with_snack) VALUES (?, ?, ?, ?, ?)");
    
    // We need cost of recipes
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
        $insert_meal_plan->bind_param('sssdi', $s_id, $meal_a, $date, $cost_a, $snack_flag);
        $insert_meal_plan->execute();
    }

    if ($meal_b && isset($costs[$meal_b])) {
        $cost_b = $costs[$meal_b];
        foreach ($plan_data['meal_b_list'] as $s_id) {
            $snack_flag = in_array($s_id, $blacklist) ? 0 : 1;
            $insert_meal_plan->bind_param('sssdi', $s_id, $meal_b, $date, $cost_b, $snack_flag);
            $insert_meal_plan->execute();
        }
    }

    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => "Plan generated successfully. Type: " . $plan_data['type'],
        'debug_data' => $plan_data['debug'] ?? []
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

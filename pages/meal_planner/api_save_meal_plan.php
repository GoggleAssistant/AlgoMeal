<?php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? 'deploy';
$date = $data['date'] ?? '';

if (empty($date)) {
    echo json_encode(['success' => false, 'message' => 'Missing date']);
    exit;
}

// --- ACTION: Mark as Served (Lock the day) ---
if ($action === 'mark_served') {
    // Check it exists first
    $check = $conn->query("SELECT is_served FROM daily_meal_plans WHERE scheduled_date = '$date'");
    if (!$check || !$check->fetch_assoc()) {
        echo json_encode(['success' => false, 'message' => 'No plan found for this date to mark as served.']);
        exit;
    }
    $conn->query("UPDATE daily_meal_plans SET is_served = 1 WHERE scheduled_date = '$date'");
    echo json_encode(['success' => true, 'message' => 'Day marked as served and locked.']);
    exit;
}

// --- ACTION: Unserve (Unlock the day) ---
if ($action === 'unserve') {
    $conn->query("UPDATE daily_meal_plans SET is_served = 0 WHERE scheduled_date = '$date'");
    echo json_encode(['success' => true, 'message' => 'Day unlocked successfully.']);
    exit;
}

// --- ACTION: Undeploy (Reset the day) ---

if ($action === 'undeploy') {
    // Check if it's served first
    $check = $conn->query("SELECT is_served FROM daily_meal_plans WHERE scheduled_date = '$date'");
    if ($row = $check->fetch_assoc()) {
        if ($row['is_served']) {
            echo json_encode(['success' => false, 'message' => 'Cannot undeploy a day that has already been served.']);
            exit;
        }
    }
    
    $conn->begin_transaction();
    try {
        $conn->query("DELETE FROM meal_plan WHERE scheduled_date = '$date'");
        $conn->query("DELETE FROM daily_meal_plans WHERE scheduled_date = '$date'");
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Plan removed successfully.']);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit;
}

// --- ACTION: Deploy (Save the meal plan) ---
$meal_a_recipe = $data['meal_a_recipe'] ?? '';
$meal_b_recipe = $data['meal_b_recipe'] ?? '';
$meal_a_list   = $data['meal_a_list'] ?? [];
$meal_b_list   = $data['meal_b_list'] ?? [];

if (empty($meal_a_recipe)) {
    echo json_encode(['success' => false, 'message' => 'Missing primary meal']);
    exit;
}

// Prevent editing served days
$check = $conn->query("SELECT is_served FROM daily_meal_plans WHERE scheduled_date = '$date'");
if ($check && $row = $check->fetch_assoc()) {
    if ($row['is_served']) {
        echo json_encode(['success' => false, 'message' => 'This day has been marked as Served and cannot be edited.']);
        exit;
    }
}

// Get Recipe Costs
$res_a = $conn->query("SELECT base_cost_per_serving FROM recipes WHERE recipe_id = '$meal_a_recipe'");
$cost_a = $res_a->fetch_assoc()['base_cost_per_serving'] ?? 0;
$cost_b = 0;
if (!empty($meal_b_recipe)) {
    $res_b = $conn->query("SELECT base_cost_per_serving FROM recipes WHERE recipe_id = '$meal_b_recipe'");
    $cost_b = $res_b->fetch_assoc()['base_cost_per_serving'] ?? 0;
}

$conn->begin_transaction();
try {
    // Clear and replace existing plan for this date
    $conn->query("DELETE FROM meal_plan WHERE scheduled_date = '$date'");

    // Persist to Daily Global Tracker
    $m_a = $conn->real_escape_string($meal_a_recipe);
    $m_b = $conn->real_escape_string(!empty($meal_b_recipe) ? $meal_b_recipe : $meal_a_recipe);
    $conn->query("REPLACE INTO daily_meal_plans (scheduled_date, meal_a_recipe_id, meal_b_recipe_id, is_served) VALUES ('$date', '$m_a', '$m_b', 0)");

    // Insert Meal A student assignments
    $stmt = $conn->prepare("INSERT INTO meal_plan (student_id, recipe_id, scheduled_date, actual_cost, feeding_status) VALUES (?, ?, ?, ?, 'Served')");
    foreach ($meal_a_list as $sid) {
        $stmt->bind_param("sssd", $sid, $meal_a_recipe, $date, $cost_a);
        $stmt->execute();
    }

    // Insert Meal B student assignments
    if (!empty($meal_b_recipe) && !empty($meal_b_list)) {
        foreach ($meal_b_list as $sid) {
            $stmt->bind_param("sssd", $sid, $meal_b_recipe, $date, $cost_b);
            $stmt->execute();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

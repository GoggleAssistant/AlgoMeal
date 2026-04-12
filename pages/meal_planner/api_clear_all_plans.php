<?php
// api_clear_all_plans.php
require_once '../../db.php';

header('Content-Type: application/json');

try {
    $conn->begin_transaction();

    // Delete all records from the meal_plan (individual student assignments)
    $conn->query("DELETE FROM meal_plan");
    
    // Delete all records from daily_meal_plans (day assignments)
    $conn->query("DELETE FROM daily_meal_plans");

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'All meal plan data has been successfully cleared.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>

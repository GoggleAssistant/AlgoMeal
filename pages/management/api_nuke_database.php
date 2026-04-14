<?php
// api_nuke_database.php
// Highly destructive endpoint. Truncates all major system tables.
require_once '../../db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid Request']);
    exit;
}

$conn->begin_transaction();
try {
    // Disable foreign key checks to allow TRUNCATE
    $conn->query("SET FOREIGN_KEY_CHECKS = 0;");
    
    // Core Operational Data
    $conn->query("TRUNCATE TABLE daily_meal_plans;");
    $conn->query("TRUNCATE TABLE meal_plan;");
    $conn->query("TRUNCATE TABLE meal_plan_attendance;");
    
    // Recipes & Taxonomy
    $conn->query("TRUNCATE TABLE recipes;");
    $conn->query("TRUNCATE TABLE recipe_allergen_tags;");
    
    // Students & Biometrics
    $conn->query("TRUNCATE TABLE student;");
    $conn->query("TRUNCATE TABLE student_allergy_map;");
    $conn->query("TRUNCATE TABLE nutritional_record;");
    
    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Database successfully wiped.']);
} catch (Exception $e) {
    $conn->rollback();
    $conn->query("SET FOREIGN_KEY_CHECKS = 1;");
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

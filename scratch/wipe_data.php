<?php
require_once __DIR__ . '/../db.php';

// 1. Clear Meal Plan related tables
echo "Clearing Meal Plans...\n";
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE meal_plan");
$conn->query("TRUNCATE TABLE daily_meal_plans");
$conn->query("TRUNCATE TABLE budget_logs"); // Optional: related to fiscal records of meals
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
echo "Meal Plans Cleared.\n\n";

// 2. Clear Student related tables
echo "Clearing Students and associated records...\n";
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE student");
$conn->query("TRUNCATE TABLE student_allergy_map");
$conn->query("TRUNCATE TABLE nutritional_record");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");
echo "Students Cleared.\n\n";

echo "Wipe Complete! The system is now ready for a fresh student roster and new meal planning cycle.";
?>

<?php
require_once __DIR__ . '/../db.php';
$conn->query("ALTER TABLE daily_meal_plans ADD COLUMN snack_recipe_id INT NULL");
$conn->query("ALTER TABLE meal_plan ADD COLUMN with_snack TINYINT(1) DEFAULT 0");
echo "DB Updated successfully, " . $conn->error;
?>

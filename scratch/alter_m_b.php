<?php
require_once 'c:/xampp/htdocs/AlgoMeal/db.php';
try {
    $conn->query("ALTER TABLE daily_meal_plans MODIFY meal_b_recipe_id VARCHAR(10) NULL;");
    echo "Successfully modified daily_meal_plans table.";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>

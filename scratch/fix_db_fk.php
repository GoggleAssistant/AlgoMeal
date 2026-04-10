<?php
require_once 'db.php';

try {
    // 1. Nutritional Record
    $conn->query("ALTER TABLE nutritional_record DROP FOREIGN KEY nutritional_record_ibfk_1");
    $conn->query("ALTER TABLE nutritional_record ADD CONSTRAINT nutritional_record_ibfk_1 FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE CASCADE ON UPDATE CASCADE");
    echo "Fixed nutritional_record FK\n";

    // 2. Meal Plan
    $conn->query("ALTER TABLE meal_plan DROP FOREIGN KEY meal_plan_ibfk_1");
    $conn->query("ALTER TABLE meal_plan ADD CONSTRAINT meal_plan_ibfk_1 FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE CASCADE ON UPDATE CASCADE");
    echo "Fixed meal_plan FK\n";

    // 3. Student Allergy Map
    $conn->query("ALTER TABLE student_allergy_map DROP FOREIGN KEY student_allergy_map_ibfk_1");
    $conn->query("ALTER TABLE student_allergy_map ADD CONSTRAINT student_allergy_map_ibfk_1 FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE CASCADE ON UPDATE CASCADE");
    echo "Fixed student_allergy_map FK\n";

    echo "Database schema updated successfully with ON UPDATE CASCADE.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

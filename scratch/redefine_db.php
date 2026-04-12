<?php
require_once 'db.php';

$queries = [
    // 1. Remove Double-Fed from ENUM
    "ALTER TABLE meal_plan MODIFY COLUMN feeding_status ENUM('Served', 'Absent') DEFAULT 'Served';",
    
    // 2. Add tagged_date to kitchen_documentation
    "ALTER TABLE kitchen_documentation ADD COLUMN IF NOT EXISTS tagged_date DATE NULL AFTER photo_path;",
    
    // 3. Ensure tagged_date defaults to created_at date for existing records
    "UPDATE kitchen_documentation SET tagged_date = DATE(created_at) WHERE tagged_date IS NULL;"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Successfully executed: " . substr($q, 0, 50) . "...\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
?>

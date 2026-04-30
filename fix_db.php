<?php
require_once 'db.php';

$queries = [
    "ALTER TABLE nutritional_record ADD COLUMN IF NOT EXISTS min_target_weight DECIMAL(5,2) DEFAULT 0.00",
    "ALTER TABLE nutritional_record ADD COLUMN IF NOT EXISTS max_target_weight DECIMAL(5,2) DEFAULT 0.00"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Success: $q\n";
    } else {
        echo "Error: " . $conn->error . "\n";
    }
}
?>

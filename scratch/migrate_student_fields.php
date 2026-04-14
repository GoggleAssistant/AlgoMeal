<?php
require_once __DIR__ . '/../db.php';

$queries = [
    "ALTER TABLE student ADD COLUMN IF NOT EXISTS parent_milk_consent TINYINT(1) DEFAULT 0",
    "ALTER TABLE student ADD COLUMN IF NOT EXISTS participation_consent TINYINT(1) DEFAULT 0",
    "ALTER TABLE nutritional_record ADD COLUMN IF NOT EXISTS hfa_status VARCHAR(20) DEFAULT NULL",
    "ALTER TABLE nutritional_record ADD COLUMN IF NOT EXISTS age_years INT DEFAULT NULL",
    "ALTER TABLE nutritional_record ADD COLUMN IF NOT EXISTS age_months INT DEFAULT NULL"
];

foreach ($queries as $q) {
    if ($conn->query($q)) {
        echo "Success: $q\n";
    } else {
        echo "Error: " . $conn->error . " in $q\n";
    }
}
?>

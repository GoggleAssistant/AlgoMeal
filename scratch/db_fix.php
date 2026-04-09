<?php
require_once '../db.php';

echo "Shifting dates to create historical timeline...\n";

// Update 1: Shift records back in time
// This spreads them over the last 12 months based on their ID
$sql = "UPDATE nutritional_record SET assessment_date = DATE_SUB(CURDATE(), INTERVAL (record_id % 12) MONTH) WHERE assessment_date >= CURDATE()";

if ($conn->query($sql)) {
    echo "Successfully updated " . $conn->affected_rows . " records.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

echo "Done.\n";
?>

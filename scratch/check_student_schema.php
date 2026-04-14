<?php
require_once __DIR__ . '/../db.php';
foreach (['student', 'nutritional_record'] as $t) {
    echo "--- Table: $t ---\n";
    $res = $conn->query("DESCRIBE $t");
    while ($r = $res->fetch_assoc()) {
        echo "Field: {$r['Field']} | Type: {$r['Type']} | Null: {$r['Null']}\n";
    }
    echo "\n";
}
?>

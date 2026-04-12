<?php
require_once 'db.php';
$tables = ['meal_plan', 'daily_meal_plans', 'budget_logs', 'kitchen_documentation'];
foreach($tables as $t) {
    echo "TABLE: $t\n";
    $res = $conn->query("DESCRIBE $t");
    while($row = $res->fetch_assoc()) {
        echo "  {$row['Field']} ({$row['Type']})\n";
    }
}
?>

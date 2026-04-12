<?php
$dir = 'c:\xampp\htdocs\AlgoMeal\pages\meal_planner';
$files = glob($dir . '\api_*.php');
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
        echo "Deleted: " . basename($file) . "\n";
    }
}
?>

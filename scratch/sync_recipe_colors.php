<?php
require_once __DIR__ . '/../db.php';

$category_colors = [
    'Rice Meal'  => '#f59e0b',
    'Soup'       => '#0ea5e9',
    'Viand'      => '#ef4444',
    'Pasta'      => '#f97316',
    'Snack'      => '#a855f7',
    'Vegetable'  => '#10b981',
    'General'    => '#64748b',
];

foreach ($category_colors as $cat => $hex) {
    $stmt = $conn->prepare("UPDATE recipes SET hex_color = ? WHERE category = ?");
    $stmt->bind_param("ss", $hex, $cat);
    $stmt->execute();
}

echo "Successfully synchronized " . count($category_colors) . " category colors across the recipe database.";
?>

<?php
require_once __DIR__ . '/../db.php';

// 1. ALTER TABLE
$alter_query = "ALTER TABLE recipes ADD hex_color VARCHAR(7) DEFAULT '#3b82f6'";
if ($conn->query($alter_query)) {
    echo "Column added successfully.\n<br>";
} else {
    echo "Column might exist or error: " . $conn->error . "\n<br>";
}

// 2. Generate smooth colors for existing ones
$result = $conn->query("SELECT recipe_id FROM recipes");
$colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#6366f1', '#84cc16'];
$idx = 0;

while ($row = $result->fetch_assoc()) {
    $id = $row['recipe_id'];
    $color = $colors[$idx % count($colors)];
    $conn->query("UPDATE recipes SET hex_color = '$color' WHERE recipe_id = '$id'");
    $idx++;
}

echo "Colors seeded successfully!";
?>

<?php
require_once __DIR__ . '/../db.php';
$result = $conn->query("ALTER TABLE recipes ADD COLUMN IF NOT EXISTS category VARCHAR(50) NOT NULL DEFAULT 'General' AFTER recipe_name");
echo $result ? "Column added OK" : "Error: " . $conn->error;
?>

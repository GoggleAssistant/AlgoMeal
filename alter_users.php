<?php
require_once 'db.php';
$conn->query("ALTER TABLE users ADD COLUMN status ENUM('Active', 'Disabled') DEFAULT 'Active'");
echo "Done";
?>

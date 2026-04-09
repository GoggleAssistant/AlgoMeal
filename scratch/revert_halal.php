<?php
require_once '../db.php';
$conn->query("UPDATE dietary_restrictions SET restriction_name = 'Halal' WHERE restriction_name = 'Non-Halal (Pork)' OR restriction_name = 'Halal / Pork-Free'");
echo "DB updated to: Halal";
?>

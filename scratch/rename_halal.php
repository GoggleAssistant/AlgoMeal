<?php
require_once '../db.php';
$conn->query("UPDATE dietary_restrictions SET restriction_name = 'Non-Halal (Pork)' WHERE restriction_name = 'Halal / Pork-Free'");
echo "Updated DB restriction name.";
?>

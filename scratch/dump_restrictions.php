<?php
require 'c:/xampp/htdocs/AlgoMeal/db.php';
$res = $conn->query("SELECT * FROM dietary_restrictions ORDER BY restriction_id");
while($r = $res->fetch_assoc()) {
    echo $r['restriction_id'] . ": " . $r['restriction_name'] . " (" . $r['type'] . ")\n";
}
?>

<?php
require_once 'db.php';
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_array()) echo $row[0] . "\n";
?>

<?php
require 'db.php';
$res = $conn->query("SELECT student_id, first_name, last_name FROM student");
echo "STUDENT COUNT: " . ($res ? $res->num_rows : "ERROR") . "\n";
while($r = $res->fetch_assoc()) echo $r['student_id'] . ": " . $r['first_name'] . " " . $r['last_name'] . "\n";

$res2 = $conn->query("SELECT COUNT(*) as co FROM nutritional_record");
$row2 = $res2->fetch_assoc();
echo "ASSESSMENT COUNT: " . $row2['co'] . "\n";
?>

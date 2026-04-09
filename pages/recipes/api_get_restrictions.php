<?php
require_once '../../db.php';
header('Content-Type: application/json');

$res = $conn->query("SELECT * FROM dietary_restrictions ORDER BY restriction_name ASC");
$restrictions = [];
while($row = $res->fetch_assoc()) {
    $restrictions[] = $row;
}
echo json_encode($restrictions);
?>

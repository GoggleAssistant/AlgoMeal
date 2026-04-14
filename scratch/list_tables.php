<?php
require_once __DIR__ . '/../db.php';
$res = $conn->query("SHOW TABLES");
while($row = $res->fetch_row()) {
    echo $row[0] . "\n";
}
?>

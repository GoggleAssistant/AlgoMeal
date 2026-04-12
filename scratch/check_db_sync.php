<?php
require_once 'db.php';
$res = $conn->query("SHOW TABLES");
echo "TABLES:\n";
while($row = $res->fetch_array()) {
    echo "- " . $row[0] . "\n";
}

$res = $conn->query("DESC STUDENT");
echo "\nSTUDENT COLUMNS:\n";
while($row = $res->fetch_assoc()) {
    echo "- " . $row['Field'] . " (" . $row['Type'] . ")\n";
}

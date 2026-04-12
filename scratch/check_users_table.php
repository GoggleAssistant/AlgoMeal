<?php
require_once 'db.php';
$res = $conn->query("DESCRIBE users");
if ($res) {
    while($row = $res->fetch_assoc()) {
        echo $row['Field'] . "\n";
    }
} else {
    echo "Query failed: " . $conn->error;
}
?>

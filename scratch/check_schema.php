<?php
require_once 'db.php';
$res = $conn->query("DESCRIBE budget_logs");
while($row = $res->fetch_assoc()) {
    print_r($row);
}

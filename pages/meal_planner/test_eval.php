<?php
require_once 'heuristic_engine.php';
require_once '../../db.php';
print_r(generate_plan_for_date($conn, '2026-04-18', 500));
?>

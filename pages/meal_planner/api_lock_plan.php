<?php
// api_lock_plan.php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['date'])) {
    echo json_encode(['success' => false, 'message' => 'Date needed.']);
    exit;
}

$date = $data['date'];
$total_cost = isset($data['total_cost']) ? (float)$data['total_cost'] : 0.00;

$conn->begin_transaction();

try {
    // 1. Mark the day as served
    $stmt = $conn->prepare("UPDATE daily_meal_plans SET is_served = 1 WHERE scheduled_date = ?");
    $stmt->bind_param('s', $date);
    $stmt->execute();

    // 2. Count served students to calculate pro-rated cost
    $count_res = $conn->query("SELECT COUNT(*) as c FROM meal_plan WHERE scheduled_date = '$date' AND feeding_status = 'Served'");
    $served_count = $count_res->fetch_assoc()['c'];

    if ($served_count > 0 && $total_cost > 0) {
        $per_student_cost = $total_cost / $served_count;
        $stmt_cost = $conn->prepare("UPDATE meal_plan SET actual_cost = ? WHERE scheduled_date = ? AND feeding_status = 'Served'");
        $stmt_cost->bind_param('ds', $per_student_cost, $date);
        $stmt_cost->execute();
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

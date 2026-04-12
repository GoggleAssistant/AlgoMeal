<?php
// api_reschedule_plan.php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['old_date']) || !isset($data['new_date'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

$old_date = $data['old_date'];
$new_date = $data['new_date'];

$conn->begin_transaction();
try {
    // Check if new date has plan
    $stmt = $conn->prepare("SELECT scheduled_date FROM daily_meal_plans WHERE scheduled_date = ?");
    $stmt->bind_param('s', $new_date);
    $stmt->execute();
    if ($stmt->get_result()->num_rows > 0) {
        throw new Exception("A meal plan already exists for the new date.");
    }

    $stmt2 = $conn->prepare("UPDATE daily_meal_plans SET scheduled_date = ? WHERE scheduled_date = ?");
    $stmt2->bind_param('ss', $new_date, $old_date);
    $stmt2->execute();

    $stmt3 = $conn->prepare("UPDATE meal_plan SET scheduled_date = ? WHERE scheduled_date = ?");
    $stmt3->bind_param('ss', $new_date, $old_date);
    $stmt3->execute();

    $conn->commit();
    echo json_encode(['success' => true, 'message' => "Plan rescheduled."]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

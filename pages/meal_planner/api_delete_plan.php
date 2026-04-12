<?php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['date'])) {
    echo json_encode(['success' => false, 'message' => 'Date needed.']);
    exit;
}

$date = $data['date'];

$conn->begin_transaction();
try {
    // Check if locked
    $stmt_check = $conn->prepare("SELECT is_served FROM daily_meal_plans WHERE scheduled_date = ?");
    $stmt_check->bind_param('s', $date);
    $stmt_check->execute();
    $res = $stmt_check->get_result();
    if($res->num_rows > 0 && $res->fetch_assoc()['is_served'] == 1) {
        throw new Exception("Cannot delete a plan that has already been verified and served.");
    }

    $stmt_del1 = $conn->prepare("DELETE FROM meal_plan WHERE scheduled_date = ?");
    $stmt_del1->bind_param('s', $date);
    $stmt_del1->execute();
    
    $stmt_del2 = $conn->prepare("DELETE FROM daily_meal_plans WHERE scheduled_date = ?");
    $stmt_del2->bind_param('s', $date);
    $stmt_del2->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

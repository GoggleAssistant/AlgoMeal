<?php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['date'])) {
    echo json_encode(['success' => false, 'message' => 'Date needed.']);
    exit;
}

$date = $data['date'];

$stmt = $conn->prepare("UPDATE daily_meal_plans SET is_served = 0 WHERE scheduled_date = ?");
$stmt->bind_param('s', $date);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
?>

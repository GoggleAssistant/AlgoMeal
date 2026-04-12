<?php
// api_update_status.php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['student_id']) || !isset($data['date']) || !isset($data['status'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

$id = $data['student_id'];
$date = $data['date'];
$status = $data['status'];

$stmt = $conn->prepare("UPDATE meal_plan SET feeding_status = ? WHERE student_id = ? AND scheduled_date = ?");
$stmt->bind_param('sss', $status, $id, $date);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt->error]);
}
?>

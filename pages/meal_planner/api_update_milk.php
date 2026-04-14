<?php
// api_update_milk.php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$stud_id = $data['student_id'] ?? null;
$date = $data['date'] ?? null;
$with_milk = isset($data['with_milk']) ? (int)$data['with_milk'] : 0;

if (!$stud_id || !$date) {
    echo json_encode(['success' => false, 'message' => 'Missing student ID or date.']);
    exit;
}

$stmt = $conn->prepare("UPDATE meal_plan SET with_milk = ? WHERE student_id = ? AND scheduled_date = ?");
$stmt->bind_param('iss', $with_milk, $stud_id, $date);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>

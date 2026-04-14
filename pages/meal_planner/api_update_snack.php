<?php
// api_update_snack.php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$stud_id = $data['student_id'] ?? null;
$date = $data['date'] ?? null;
$with_snack = isset($data['with_snack']) ? (int)$data['with_snack'] : 0;

if (!$stud_id || !$date) {
    echo json_encode(['success' => false, 'message' => 'Missing student ID or date.']);
    exit;
}

$stmt = $conn->prepare("UPDATE meal_plan SET with_snack = ? WHERE student_id = ? AND scheduled_date = ?");
$stmt->bind_param('iss', $with_snack, $stud_id, $date);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>

<?php
// api_update_assignment.php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data || !isset($data['student_id']) || !isset($data['date']) || !isset($data['recipe_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid data.']);
    exit;
}

$id = $data['student_id'];
$date = $data['date'];
$recipe_id = $data['recipe_id'];

// Get recipe cost
$stmt = $conn->prepare("SELECT base_cost_per_serving FROM recipes WHERE recipe_id = ?");
$stmt->bind_param('s', $recipe_id);
$stmt->execute();
$res = $stmt->get_result();
$cost = $res->num_rows > 0 ? $res->fetch_assoc()['base_cost_per_serving'] : 0.00;

$stmt2 = $conn->prepare("UPDATE meal_plan SET recipe_id = ?, actual_cost = ? WHERE student_id = ? AND scheduled_date = ?");
$stmt2->bind_param('sdss', $recipe_id, $cost, $id, $date);
if ($stmt2->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $stmt2->error]);
}
?>

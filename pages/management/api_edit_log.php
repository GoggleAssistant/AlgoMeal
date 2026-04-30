<?php
session_start();
require_once '../../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$log_id = (int)($data['log_id'] ?? 0);
$amount = (float)($data['amount'] ?? 0);
$category = $data['category'] ?? '';
$description = $data['description'] ?? '';

if ($log_id <= 0 || $amount <= 0 || empty($category) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing data.']);
    exit;
}

$stmt = $conn->prepare("UPDATE budget_logs SET amount = ?, category = ?, description = ? WHERE id = ?");
$stmt->bind_param("dssi", $amount, $category, $description, $log_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>

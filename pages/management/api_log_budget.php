<?php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$amount = (float)($data['amount'] ?? 0);
$category = $data['category'] ?? '';
$description = $data['description'] ?? '';

if ($amount <= 0 || empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Invalid amount or category.']);
    exit;
}

$stmt = $conn->prepare("INSERT INTO budget_logs (amount, category, description) VALUES (?, ?, ?)");
$stmt->bind_param("dss", $amount, $category, $description);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>

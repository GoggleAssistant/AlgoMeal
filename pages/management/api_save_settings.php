<?php
require_once '../../db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) { echo json_encode(['success' => false, 'message' => 'Invalid input.']); exit; }

$allowed_keys = ['total_allocated_budget', 'total_daily_budget'];
$conn->begin_transaction();
try {
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    foreach ($allowed_keys as $key) {
        if (isset($data[$key]) && is_numeric($data[$key])) {
            $val = (string)(float)$data[$key];
            $stmt->bind_param('ss', $key, $val);
            $stmt->execute();
        }
    }
    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Budget settings saved.']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

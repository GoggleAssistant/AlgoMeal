<?php
require_once '../../db.php';
header('Content-Type: application/json');

// Ensure settings table exists
$conn->query("CREATE TABLE IF NOT EXISTS settings (setting_key VARCHAR(50) PRIMARY KEY, setting_value VARCHAR(255))");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('daily_budget_limit', '25.00')");

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $key = $conn->real_escape_string($data['key'] ?? '');
    $value = $conn->real_escape_string($data['value'] ?? '');
    if (empty($key)) { echo json_encode(['success' => false, 'message' => 'Missing key']); exit; }
    $conn->query("REPLACE INTO settings (setting_key, setting_value) VALUES ('$key', '$value')");
    echo json_encode(['success' => true]);
} else {
    $key = $conn->real_escape_string($_GET['key'] ?? 'daily_budget_limit');
    $res = $conn->query("SELECT setting_value FROM settings WHERE setting_key = '$key'");
    $row = $res ? $res->fetch_assoc() : null;
    echo json_encode(['success' => true, 'value' => $row ? $row['setting_value'] : '25.00']);
}
?>

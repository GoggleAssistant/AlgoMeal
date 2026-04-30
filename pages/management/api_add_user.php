<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'Admin' && $_SESSION['role'] !== 'Super Admin')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$faculty_name = $data['faculty_name'] ?? '';
$password = $data['password'] ?? '';
$role = $data['role'] ?? 'Faculty';

if (empty($faculty_name) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Name and password are required.']);
    exit;
}

// Hierarchy Check
if ($_SESSION['role'] === 'Admin') {
    if ($role !== 'Faculty') {
        echo json_encode(['success' => false, 'message' => 'Admins can only create Faculty accounts.']);
        exit;
    }
}

// Check if username exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE faculty_name = ?");
$stmt->bind_param("s", $faculty_name);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username already exists.']);
    exit;
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users (faculty_name, password_hash, role) VALUES (?, ?, ?)");
$stmt->bind_param("sss", $faculty_name, $hash, $role);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>

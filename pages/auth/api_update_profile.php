<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$faculty_name = $data['faculty_name'] ?? '';
$password = $data['password'] ?? '';
$user_id = $_SESSION['user_id'];

if (empty($faculty_name)) {
    echo json_encode(['success' => false, 'message' => 'Name cannot be empty.']);
    exit;
}

// Check if username already exists for another user
$stmt = $conn->prepare("SELECT user_id FROM users WHERE faculty_name = ? AND user_id != ?");
$stmt->bind_param("si", $faculty_name, $user_id);
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username already exists.']);
    exit;
}

if (!empty($password)) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET faculty_name = ?, password_hash = ? WHERE user_id = ?");
    $stmt->bind_param("ssi", $faculty_name, $hash, $user_id);
} else {
    $stmt = $conn->prepare("UPDATE users SET faculty_name = ? WHERE user_id = ?");
    $stmt->bind_param("si", $faculty_name, $user_id);
}

if ($stmt->execute()) {
    $_SESSION['faculty_name'] = $faculty_name; // Update session
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>

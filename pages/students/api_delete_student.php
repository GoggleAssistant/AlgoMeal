<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'Admin' && ($_SESSION['role'] ?? '') !== 'Super Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Administrator privileges required to delete student profiles.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';

    if (empty($student_id)) {
        echo json_encode(['success' => false, 'error' => 'Student ID is required.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM student WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to delete student: ' . $conn->error]);
    }
}
?>

<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'Admin' && ($_SESSION['role'] ?? '') !== 'Super Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $status = isset($_POST['status']) ? (int)$_POST['status'] : 0; // 1 for enroll, 0 for unenroll

    if (empty($student_id)) {
        echo json_encode(['success' => false, 'error' => 'Missing student ID.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE student SET is_enrolled = ? WHERE student_id = ?");
    $stmt->bind_param("is", $status, $student_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => $status ? 'Student re-enrolled.' : 'Student unenrolled.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Update failed.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>

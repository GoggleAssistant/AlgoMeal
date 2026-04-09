<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $target_weight = $_POST['target_weight'] ?? '';

    if (empty($student_id) || empty($target_weight)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
        exit;
    }

    $stmt = $conn->prepare("UPDATE student SET target_weight = ? WHERE student_id = ?");
    if($stmt) {
        $stmt->bind_param("ds", $target_weight, $student_id);
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database update failed.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Statement prep failed.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>

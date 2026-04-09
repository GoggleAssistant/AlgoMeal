<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete record requires ID
    $record_id = $_POST['record_id'] ?? '';
    
    if (empty($record_id)) {
        echo json_encode(['success' => false, 'error' => 'Missing record ID.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM nutritional_record WHERE record_id = ?");
    if($stmt) {
        $stmt->bind_param("i", $record_id);
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Database deletion failed.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Statement prep failed.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>

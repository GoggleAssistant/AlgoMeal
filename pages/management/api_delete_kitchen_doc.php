<?php
session_start();
require_once '../../db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$doc_id = (int)($data['id'] ?? 0);

if ($doc_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    exit;
}

// Fetch photo path to delete the file too
$stmt = $conn->prepare("SELECT photo_path FROM kitchen_documentation WHERE id = ?");
$stmt->bind_param("i", $doc_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();

if (!$row) {
    echo json_encode(['success' => false, 'message' => 'Document not found.']);
    exit;
}

// Delete DB record
$del = $conn->prepare("DELETE FROM kitchen_documentation WHERE id = ?");
$del->bind_param("i", $doc_id);

if ($del->execute()) {
    // Also delete the physical file
    $file_path = '../../' . $row['photo_path'];
    if (file_exists($file_path)) {
        unlink($file_path);
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}
?>

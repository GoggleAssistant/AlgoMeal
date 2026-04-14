<?php
// api_update_bulk_students.php
header('Content-Type: application/json');
require_once '../../db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['students']) || !is_array($data['students'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid or missing data payload.']);
    exit;
}

$students = $data['students'];
$successCount = 0;
$errors = [];

$conn->begin_transaction();

try {
    $stmt = $conn->prepare("UPDATE student SET first_name=?, last_name=?, sex=?, birth_date=?, grade_level=?, section=? WHERE student_id=?");
    
    foreach ($students as $index => $st) {
        // Data integrity check
        if (empty($st['lrn']) || empty($st['first_name']) || empty($st['last_name'])) {
            $errors[] = "Index $index: Missing LRN or Name.";
            continue;
        }

        $stmt->bind_param("sssssss", 
            $st['first_name'], 
            $st['last_name'], 
            $st['sex'], 
            $st['birth_date'], 
            $st['grade_level'], 
            $st['section'], 
            $st['lrn']
        );

        if ($stmt->execute()) {
            $successCount++;
        } else {
            $errors[] = "LRN {$st['lrn']}: " . $conn->error;
        }
    }

    $conn->commit();
    echo json_encode([
        'success' => true,
        'message' => "Successfully updated $successCount existing student profiles.",
        'errors' => $errors
    ]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => 'Fatal Exception: ' . $e->getMessage()]);
}
?>

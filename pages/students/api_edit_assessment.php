<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

$allowed_roles = ['Faculty', 'Admin', 'Super Admin'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id = $_POST['record_id'] ?? '';
    $height = $_POST['height'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $date = $_POST['assessment_date'] ?? '';

    if (empty($record_id) || empty($height) || empty($weight)) {
        echo json_encode(['success' => false, 'error' => 'Missing fields.']);
        exit;
    }

    $ns_status = $_POST['ns_status'] ?? 'Normal';
    $hfa_status = $_POST['hfa_status'] ?? 'Normal';
    $min_target = (float) ($_POST['min_target_weight'] ?? 0);
    $max_target = (float) ($_POST['max_target_weight'] ?? 0);

    $stmt = $conn->prepare("UPDATE nutritional_record SET height = ?, weight = ?, bmi = ?, nutritional_status = ?, hfa_status = ?, assessment_date = ?, min_target_weight = ?, max_target_weight = ? WHERE record_id = ?");
    if ($stmt) {
        $stmt->bind_param("dddsss ddi", $height, $weight, $bmi, $ns_status, $hfa_status, $date, $min_target, $max_target, $record_id);
        if ($stmt->execute()) {
            // Update student targets if this is the latest record (optional but helpful)
            if ($min_target > 0 && $max_target > 0) {
                // Get student_id first
                $q = $conn->prepare("SELECT student_id FROM nutritional_record WHERE record_id = ?");
                $q->bind_param("i", $record_id);
                $q->execute();
                $sid = $q->get_result()->fetch_assoc()['student_id'] ?? null;
                if ($sid) {
                    $upd = $conn->prepare("UPDATE student SET min_target_weight = ?, max_target_weight = ? WHERE student_id = ?");
                    $upd->bind_param("dds", $min_target, $max_target, $sid);
                    $upd->execute();
                }
            }
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error.']);
    }
}
?>
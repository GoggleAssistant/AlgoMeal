<?php
session_start();
require_once '../../db.php';
require_once '../../includes/bmi_helper.php';

header('Content-Type: application/json');

$allowed_roles = ['Faculty', 'Admin', 'Super Admin'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles)) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $height = (float) ($_POST['height'] ?? 0);
    $weight = (float) ($_POST['weight'] ?? 0);
    $assessment_date = $_POST['assessment_date'] ?? date('Y-m-d');
    $created_by = (int) ($_SESSION['user_id'] ?? 1);

    if (empty($student_id) || $height <= 0 || $weight <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing or invalid required fields (Height/Weight).']);
        exit;
    }

    $ns_status = $_POST['ns_status'] ?? 'Normal';
    $hfa_status = $_POST['hfa_status'] ?? 'Normal';
    $min_target = (float) ($_POST['min_target_weight'] ?? 0);
    $max_target = (float) ($_POST['max_target_weight'] ?? 0);

    // Fetch student birthdate for age calculation
    $stmt_b = $conn->prepare("SELECT birth_date FROM student WHERE student_id = ?");
    $stmt_b->bind_param("s", $student_id);
    $stmt_b->execute();
    $s_data = $stmt_b->get_result()->fetch_assoc();

    $age_y = 0;
    $age_m = 0;
    if ($s_data) {
        $dob = new DateTime($s_data['birth_date']);
        $ref = new DateTime($assessment_date);
        $diff = $ref->diff($dob);
        $age_y = $diff->y;
        $age_m = $diff->m;
    }
    $height_m = $height / 100;
    $bmi = $weight / ($height_m * $height_m);

    $conn->begin_transaction();

    try {
        // 1. Insert Record
        $stmt = $conn->prepare("INSERT INTO nutritional_record (student_id, created_by, height, weight, bmi, nutritional_status, hfa_status, assessment_date, age_years, age_months, min_target_weight, max_target_weight) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sidddsssiidd", $student_id, $created_by, $height, $weight, $bmi, $ns_status, $hfa_status, $assessment_date, $age_y, $age_m, $min_target, $max_target);
        if (!$stmt->execute())
            throw new Exception("Assessment log failed.");

        // 2. Sync Student Target Weights
        if ($min_target > 0 && $max_target > 0) {
            $upd = $conn->prepare("UPDATE student SET min_target_weight = ?, max_target_weight = ? WHERE student_id = ?");
            $upd->bind_param("dds", $min_target, $max_target, $student_id);
            $upd->execute();
        }

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>
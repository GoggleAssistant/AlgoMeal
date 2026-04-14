<?php
session_start();
require_once '../../db.php';
require_once '../../includes/bmi_helper.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Admins only.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $height = (float)($_POST['height'] ?? 0);
    $weight = (float)($_POST['weight'] ?? 0);
    $assessment_date = $_POST['assessment_date'] ?? date('Y-m-d');
    $created_by = $_SESSION['user_id'] ?? 1;

    if (empty($student_id) || $height <= 0 || $weight <= 0) {
        echo json_encode(['success' => false, 'error' => 'Missing or invalid required fields (Height/Weight).']);
        exit;
    }

    // Auto-compute targets based on NEW height
    $min_target = 18.5 * pow($height / 100, 2);
    $max_target = 24.9 * pow($height / 100, 2);

    // Calculate BMI and default status
    $height_m = $height / 100;
    $bmi = $weight / ($height_m * $height_m);
    $status = getNutritionalStatus($bmi);

    // Fetch student birthdate for age calculation
    $stmt_b = $conn->prepare("SELECT birth_date FROM student WHERE student_id = ?");
    $stmt_b->bind_param("s", $student_id);
    $stmt_b->execute();
    $s_data = $stmt_b->get_result()->fetch_assoc();
    
    $age_y = 0; $age_m = 0;
    if ($s_data) {
        $dob = new DateTime($s_data['birth_date']);
        $ref = new DateTime($assessment_date);
        $diff = $ref->diff($dob);
        $age_y = $diff->y;
        $age_m = $diff->m;
    }

    $conn->begin_transaction();

    try {
        // 1. Insert Record
        $stmt = $conn->prepare("INSERT INTO nutritional_record (student_id, created_by, height, weight, bmi, nutritional_status, assessment_date, age_years, age_months) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sidddssii", $student_id, $created_by, $height, $weight, $bmi, $status, $assessment_date, $age_y, $age_m);
        if(!$stmt->execute()) throw new Exception("Assessment log failed.");

        // 2. Sync Student Target Weights
        $upd = $conn->prepare("UPDATE student SET min_target_weight = ?, max_target_weight = ? WHERE student_id = ?");
        $upd->bind_param("dds", $min_target, $max_target, $student_id);
        $upd->execute();

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

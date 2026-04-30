<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'Admin' && ($_SESSION['role'] ?? '') !== 'Super Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Administrator privileges required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $lrn = trim($_POST['student_id'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $sex = $_POST['sex'] ?? 'Male';
    $birth_date = $_POST['birth_date'] ?? '';
    $grade_level = $_POST['grade_level'] ?? '';
    $section = $_POST['section'] ?? '';
    $manual_section = trim($_POST['manual_section'] ?? '');

    // NEW FIELDS
    $parent_milk_consent = (int) ($_POST['parent_milk_consent'] ?? 0);
    $participation_consent = (int) ($_POST['participation_consent'] ?? 0);
    $is_4ps = (int) ($_POST['is_4ps'] ?? 0);
    $is_dewormed = (int) ($_POST['is_dewormed'] ?? 0);

    // TARGET WEIGHTS - Derived from WHO pediatric standards passed from frontend
    $min_target_weight = (float) ($_POST['min_target_weight'] ?? 0);
    $max_target_weight = (float) ($_POST['max_target_weight'] ?? 0);

    // Use manual section if provided
    if (trim($section) === 'Other' && !empty($manual_section)) {
        $section = $manual_section;
    }

    if (empty($lrn) || empty($last_name) || empty($first_name) || empty($section)) {
        echo json_encode(['success' => false, 'error' => 'All required fields must be filled.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare("INSERT INTO student (student_id, last_name, first_name, sex, birth_date, grade_level, section, min_target_weight, max_target_weight, parent_milk_consent, participation_consent, deworming_status, is_4ps_beneficiary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        if (!$stmt)
            throw new Exception("Statement prep failed: " . $conn->error);

        $stmt->bind_param("sssssssddiiii", $lrn, $last_name, $first_name, $sex, $birth_date, $grade_level, $section, $min_target_weight, $max_target_weight, $parent_milk_consent, $participation_consent, $is_dewormed, $is_4ps);

        if (!$stmt->execute())
            throw new Exception("Failed to add student. ID may already exist.");

        // Handle Dietary Restrictions (Allergies)
        if (isset($_POST['allergies']) && is_array($_POST['allergies'])) {
            $allergy_stmt = $conn->prepare("INSERT INTO student_allergy_map (student_id, restriction_id) VALUES (?, ?)");
            foreach ($_POST['allergies'] as $restriction_id) {
                if (!empty($restriction_id)) {
                    $allergy_stmt->bind_param("si", $lrn, $restriction_id);
                    $allergy_stmt->execute();
                }
            }
        }

        // Handle initial assessment
        $init_height = $_POST['init_height'] ?? '';
        $init_weight = $_POST['init_weight'] ?? '';
        $assess_date = $_POST['assessment_date'] ?? date('Y-m-d');
        $ns_status = $_POST['ns_status'] ?? 'Normal';
        $hfa_status = $_POST['hfa_status'] ?? 'Normal';
        $age_y = (int) ($_POST['age_years'] ?? 0);
        $age_m = (int) ($_POST['age_months'] ?? 0);

        if (!empty($init_height) && !empty($init_weight)) {
            $created_by = $_SESSION['user_id'] ?? 1;
            $height_m = $init_height / 100;
            $bmi = $init_weight / ($height_m * $height_m);

            $hist_stmt = $conn->prepare("INSERT INTO nutritional_record (student_id, created_by, height, weight, bmi, nutritional_status, hfa_status, age_years, age_months, assessment_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $hist_stmt->bind_param("sidddssiis", $lrn, $created_by, $init_height, $init_weight, $bmi, $ns_status, $hfa_status, $age_y, $age_m, $assess_date);
            if (!$hist_stmt->execute())
                throw new Exception("Failed to save initial assessment.");
        }

        $conn->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>
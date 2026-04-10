<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Administrator privileges required to manage student profiles.']);
    exit;
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $original_lrn = $_POST['original_lrn'] ?? '';
    $new_lrn = trim($_POST['student_id'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $sex = $_POST['sex'] ?? 'Male';
    $birth_date = $_POST['birth_date'] ?? '';
    $grade_level = $_POST['grade_level'] ?? '';
    $section = trim($_POST['section'] ?? '');
    $manual_section = trim($_POST['manual_section'] ?? '');
    // Use manual section if provided
    if (trim($section) === 'Other' && !empty($manual_section)) {
        $section = $manual_section;
    }

    if (empty($original_lrn) || empty($new_lrn) || empty($last_name) || empty($first_name) || empty($section)) {
        echo json_encode(['success' => false, 'error' => 'All required fields must be filled.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        // 1. Update student demographics
        $min_target = $_POST['min_target_weight'] ?? 0;
        $max_target = $_POST['max_target_weight'] ?? 0;

        $stmt = $conn->prepare("UPDATE student SET student_id = ?, last_name = ?, first_name = ?, sex = ?, birth_date = ?, grade_level = ?, section = ?, min_target_weight = ?, max_target_weight = ? WHERE student_id = ?");
        if(!$stmt) throw new Exception("Prepare failed: " . $conn->error);
        
        // s (new_id), s (last), s (first), s (sex), s (birth), s (grade), s (section), d (min), d (max), s (orig_id)
        $stmt->bind_param("sssssssdds", $new_lrn, $last_name, $first_name, $sex, $birth_date, $grade_level, $section, $min_target, $max_target, $original_lrn);
        
        if(!$stmt->execute()) throw new Exception("Failed to update student profile. LRN might be taken.");

        // 2. Synchronize Allergies
        // Clear old ones for this student
        $del = $conn->prepare("DELETE FROM student_allergy_map WHERE student_id = ?");
        $del->bind_param("s", $new_lrn);
        $del->execute();

        // Insert new ones
        if (isset($_POST['allergies']) && is_array($_POST['allergies'])) {
            $ins = $conn->prepare("INSERT INTO student_allergy_map (student_id, restriction_id) VALUES (?, ?)");
            foreach ($_POST['allergies'] as $rid) {
                if(!empty($rid)) {
                    $ins->bind_param("si", $new_lrn, $rid);
                    $ins->execute();
                }
            }
        }

        $conn->commit();
        echo json_encode(['success' => true, 'new_lrn' => $new_lrn]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}
?>

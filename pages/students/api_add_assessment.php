<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'] ?? '';
    $height = $_POST['height'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $assessment_date = $_POST['assessment_date'] ?? date('Y-m-d');
    $created_by = $_SESSION['user_id'] ?? 1; // Fallback to 1 if testing without deep login

    if (empty($student_id) || empty($height) || empty($weight)) {
        echo json_encode(['success' => false, 'error' => 'Missing required fields.']);
        exit;
    }

    // Calculate BMI algorithmically
    $height_m = $height / 100;
    $bmi = $weight / ($height_m * $height_m);
    
    $status = getNutritionalStatus($bmi);

    $stmt = $conn->prepare("INSERT INTO nutritional_record (student_id, created_by, height, weight, bmi, nutritional_status, assessment_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    if($stmt) {
        $stmt->bind_param("sidddss", $student_id, $created_by, $height, $weight, $bmi, $status, $assessment_date);
        if(!$stmt->execute()) throw new Exception("Database insertion failed.");

        // Optional: Update Student Target Range
        $min_target_weight = $_POST['min_target_weight'] ?? '';
        $max_target_weight = $_POST['max_target_weight'] ?? '';
        if (!empty($min_target_weight) && !empty($max_target_weight)) {
            $upd = $conn->prepare("UPDATE student SET min_target_weight = ?, max_target_weight = ? WHERE student_id = ?");
            $upd->bind_param("dds", $min_target_weight, $max_target_weight, $student_id);
            $upd->execute();
        }

        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Statement prep failed.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
}
?>

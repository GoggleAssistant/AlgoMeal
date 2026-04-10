<?php
session_start();
require_once '../../db.php';

header('Content-Type: application/json');

if (($_SESSION['role'] ?? '') !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized: Admins only.']);
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

    require_once '../../includes/bmi_helper.php';
    $height_m = $height / 100;
    $bmi = $weight / ($height_m * $height_m);
    
    $status = getNutritionalStatus($bmi);

    $stmt = $conn->prepare("UPDATE nutritional_record SET height = ?, weight = ?, bmi = ?, nutritional_status = ?, assessment_date = ? WHERE record_id = ?");
    if($stmt) {
        $stmt->bind_param("dddss i", $height, $weight, $bmi, $status, $date, $record_id);
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Update failed.']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Database error.']);
    }
}
?>

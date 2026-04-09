<?php
session_start();
require_once '../../db.php';

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    die("Unauthorized");
}

$active_section = $_GET['section'] ?? '';

if (empty($active_section)) {
    die("No section specified");
}

// Fetch students for the specified section (or all)
$query = "
    SELECT 
        s.student_id, 
        s.first_name, 
        s.last_name, 
        s.sex,
        s.birth_date,
        s.grade_level,
        s.section,
        s.min_target_weight,
        s.max_target_weight,
        (SELECT height FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC LIMIT 1) as current_height,
        (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC LIMIT 1) as current_weight,
        (SELECT bmi FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC LIMIT 1) as current_bmi,
        (SELECT nutritional_status FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC LIMIT 1) as nutritional_status
    FROM student s
    " . ($active_section === 'All' ? "" : "WHERE s.section = ?") . "
    ORDER BY s.last_name ASC
";

$stmt = $conn->prepare($query);
if ($active_section !== 'All') {
    $stmt->bind_param("s", $active_section);
}
$stmt->execute();
$result = $stmt->get_result();

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=Students_' . str_replace(' ', '_', $active_section) . '_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// Header row
fputcsv($output, ['LRN', 'First Name', 'Last Name', 'Sex', 'Birth Date', 'Grade', 'Section', 'Height (cm)', 'Weight (kg)', 'BMI', 'Status', 'Min Target (kg)', 'Max Target (kg)']);

while ($row = $result->fetch_assoc()) {
    fputcsv($output, [
        $row['student_id'],
        $row['first_name'],
        $row['last_name'],
        $row['sex'],
        $row['birth_date'],
        $row['grade_level'],
        $row['section'],
        $row['current_height'] ?? '--',
        $row['current_weight'] ?? '--',
        $row['current_bmi'] ?? '--',
        $row['nutritional_status'] ?? '--',
        $row['min_target_weight'],
        $row['max_target_weight']
    ]);
}

fclose($output);
exit;
?>

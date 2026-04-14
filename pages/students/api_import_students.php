<?php
header('Content-Type: application/json');
require_once '../../db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

if (!isset($_FILES['csvFile']) || $_FILES['csvFile']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
    exit;
}

$file = $_FILES['csvFile']['tmp_name'];
$handle = fopen($file, "r");

if ($handle === false) {
    echo json_encode(['success' => false, 'error' => 'Could not open the file.']);
    exit;
}

// Skip header
fgetcsv($handle);

$successCount = 0;
$skippedCount = 0;
$errors = [];
$duplicates = [];
$line = 1;

while (($data = fgetcsv($handle, 1000, ",")) !== false) {
    $line++;
    // Data format: LRN, Last, First, Sex, BirthDate, Grade, Section
    if (count($data) < 7) {
        $errors[] = "Line $line: Incomplete data.";
        continue;
    }

    $lrn = trim($data[0]);
    $last = trim($data[1]);
    $first = trim($data[2]);
    $sex = trim($data[3]);
    $birth = trim($data[4]);
    $grade = trim($data[5]);
    $section = trim($data[6]);

    if (empty($lrn) || empty($last) || empty($first)) {
        $errors[] = "Line $line: ID, First Name, and Last Name are required.";
        continue;
    }

    // Check if exists
    $stmt = $conn->prepare("SELECT student_id, first_name, last_name, grade_level, section FROM student WHERE student_id = ?");
    $stmt->bind_param("s", $lrn);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res->num_rows > 0) {
        $old_data = $res->fetch_assoc();
        $duplicates[] = [
            'lrn' => $lrn,
            'old' => [
                'name' => trim($old_data['first_name'] . ' ' . $old_data['last_name']),
                'grade' => $old_data['grade_level'] ?: 'Unassigned',
                'section' => $old_data['section'] ?: 'Unassigned'
            ],
            'new' => [
                'name' => trim($first . ' ' . $last),
                'first_name' => $first,
                'last_name' => $last,
                'sex' => $sex,
                'birth_date' => $birth,
                'grade_level' => $grade,
                'section' => $section
            ]
        ];
        $skippedCount++;
        continue;
    }

    // Insert
    $ins = $conn->prepare("INSERT INTO student (student_id, last_name, first_name, sex, birth_date, grade_level, section) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $ins->bind_param("sssssss", $lrn, $last, $first, $sex, $birth, $grade, $section);
    
    if ($ins->execute()) {
        $successCount++;
    } else {
        $errors[] = "Line $line: Database error (" . $conn->error . ")";
    }
}

fclose($handle);

echo json_encode([
    'success' => true, 
    'message' => "Successfully imported $successCount students. Found $skippedCount existing records.",
    'errors' => $errors,
    'duplicates' => $duplicates
]);

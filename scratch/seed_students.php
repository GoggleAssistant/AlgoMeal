<?php
require_once __DIR__ . '/../db.php';

$students = [
    ['102938475601', 'Dela Cruz', 'Juan', 'Male', '2016-05-15', 'Grade 1', 'Mabini', 18.5, 22.0, 1, 1, 1, 1],
    ['102938475602', 'Santos', 'Maria Clara', 'Female', '2016-08-20', 'Grade 1', 'Mabini', 17.0, 21.0, 1, 1, 0, 0],
    ['102938475603', 'Reyes', 'Jose', 'Male', '2015-03-10', 'Grade 2', 'Rizal', 20.0, 24.5, 0, 1, 1, 0],
    ['102938475604', 'Bautista', 'Ana', 'Female', '2015-11-02', 'Grade 2', 'Rizal', 19.5, 23.0, 1, 0, 1, 1],
    ['102938475605', 'Garcia', 'Antonio', 'Male', '2014-07-22', 'Grade 3', 'Bonifacio', 22.0, 28.0, 1, 1, 1, 0],
    ['102938475606', 'Mendoza', 'Elena', 'Female', '2014-12-15', 'Grade 3', 'Bonifacio', 21.5, 27.0, 0, 1, 0, 0],
    ['102938475607', 'Lopez', 'Ricardo', 'Male', '2013-02-28', 'Grade 4', 'Aguinaldo', 25.0, 31.0, 1, 0, 1, 1],
    ['102938475608', 'Hernandez', 'Sofia', 'Female', '2013-09-14', 'Grade 4', 'Aguinaldo', 24.0, 30.0, 1, 1, 1, 0],
    ['102938475609', 'Aquino', 'Fernando', 'Male', '2012-04-05', 'Grade 5', 'Jacinto', 28.0, 35.0, 0, 1, 1, 0],
    ['102938475610', 'Del Rosario', 'Isabel', 'Female', '2012-10-30', 'Grade 5', 'Jacinto', 27.5, 34.0, 1, 1, 1, 1],
    ['102938475611', 'Villanueva', 'Gabriel', 'Male', '2011-01-12', 'Grade 6', 'Luna', 32.0, 40.0, 1, 0, 1, 0],
    ['102938475612', 'Castro', 'Patricia', 'Female', '2011-06-25', 'Grade 6', 'Luna', 31.0, 39.0, 1, 1, 0, 0],
];

echo "Seeding Students...\n";
$stmt = $conn->prepare("INSERT INTO student (student_id, last_name, first_name, sex, birth_date, grade_level, section, min_target_weight, max_target_weight, parent_milk_consent, participation_consent, deworming_status, is_4ps_beneficiary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($students as $s) {
    $stmt->bind_param("sssssssddiiii", $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8], $s[9], $s[10], $s[11], $s[12]);
    if ($stmt->execute()) {
        echo "Added: {$s[2]} {$s[1]}\n";
        
        // Add Initial Assessment
        $heights = [110, 115, 120, 125, 130, 135, 140, 145, 150];
        $h = $heights[array_rand($heights)];
        $w = $s[7] - 2; // Baseline slightly under target
        $bmi = $w / (($h/100) * ($h/100));
        
        $ns = ($bmi < 14) ? 'Wasted' : 'Normal';
        $hfa = (rand(0,10) > 8) ? 'Stunted' : 'Normal';
        
        // Calculate Age
        $dob = new DateTime($s[4]);
        $ref = new DateTime('2026-04-13');
        $diff = $ref->diff($dob);
        
        $hist = $conn->prepare("INSERT INTO nutritional_record (student_id, created_by, height, weight, bmi, nutritional_status, hfa_status, age_years, age_months, assessment_date) VALUES (?, 1, ?, ?, ?, ?, ?, ?, ?, '2026-04-13')");
        $hist->bind_param("sdddssii", $s[0], $h, $w, $bmi, $ns, $hfa, $diff->y, $diff->m);
        $hist->execute();
    } else {
        echo "Error adding {$s[2]}: " . $conn->error . "\n";
    }
}

// Add random allergies to some students
echo "\nAssigning Random Allergies...\n";
$restrictions = [1, 2, 3, 4, 5]; // Assuming these IDs exist for Egg, Milk, etc.
foreach ($students as $s) {
    if (rand(0, 10) > 7) {
        $rid = $restrictions[array_rand($restrictions)];
        $conn->query("INSERT IGNORE INTO student_allergy_map (student_id, restriction_id) VALUES ('{$s[0]}', $rid)");
        echo "Assigned allergy to {$s[2]}\n";
    }
}

echo "\nSeeding Complete! RAHH!";
?>

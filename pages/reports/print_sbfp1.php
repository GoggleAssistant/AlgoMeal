<?php
session_start();
require_once '../../db.php';

// Access Check
$role = $_SESSION['role'] ?? '';
if ($role !== 'Admin' && $role !== 'Super Admin' && $role !== 'Faculty') {
    die("Unauthorized access.");
}

$school_year = "2023-2024"; // Or fetch from settings
$school_name = "Sample Elementary School"; // Or fetch from settings
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>SBFP Form 1 - Print</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap');
        body {
            font-family: 'Inter', sans-serif;
            margin: 0;
            padding: 20px;
            color: #000;
            background: #fff;
        }
        @page {
            size: landscape;
            margin: 10mm;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .header h2 {
            margin: 5px 0;
            font-size: 14px;
            font-weight: 600;
        }
        .school-info {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            margin-bottom: 10px;
            font-weight: 700;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }
        th, td {
            border: 1px solid #000;
            padding: 6px;
            text-align: center;
        }
        th {
            background: #f1f5f9;
            font-weight: 800;
            text-transform: uppercase;
        }
        .name-col {
            text-align: left;
            font-weight: 700;
            white-space: nowrap;
        }
        /* Hide buttons when printing */
        @media print {
            .no-print { display: none !important; }
        }
        .print-btn {
            background: #0061ff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-family: 'Inter', sans-serif;
            font-weight: 700;
            cursor: pointer;
            position: fixed;
            top: 20px;
            right: 20px;
        }
    </style>
</head>
<body>

    <button class="print-btn no-print" onclick="window.print()">Print Document</button>

    <div class="header">
        <h1>Department of Education</h1>
        <h2>School-Based Feeding Program (SBFP)</h2>
        <h2>FORM 1: Master List of Beneficiaries</h2>
    </div>

    <div class="school-info">
        <div>School: <?= htmlspecialchars($school_name) ?></div>
        <div>School Year: <?= htmlspecialchars($school_year) ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">Name</th>
                <th rowspan="2">Sex</th>
                <th rowspan="2">Grade / Section</th>
                <th rowspan="2">Date of Birth<br>(MM/DD/YYYY)</th>
                <th rowspan="2">Date of Weighing<br>(MM/DD/YYYY)</th>
                <th rowspan="2">Age in<br>Yrs/Mos</th>
                <th rowspan="2">Weight<br>(kg)</th>
                <th rowspan="2">Height<br>(cm)</th>
                <th colspan="2" style="background: #e2e8f0;">Nutritional Status (NS)</th>
                <th rowspan="2" style="background: #fff7ed;">BMI for 6 y.o.<br>and above</th>
                <th rowspan="2">Milk Consent?</th>
                <th rowspan="2">Dewormed?</th>
                <th rowspan="2">Participation<br>Consent?</th>
                <th rowspan="2">In 4Ps?</th>
            </tr>
            <tr>
                <th>BMI-A</th>
                <th>HFA</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // We fetch ONLY enrolled students
            $sql_f1 = "
                SELECT s.*, 
                    nr.weight as w, 
                    nr.height as h, 
                    nr.nutritional_status as ns_bmi, 
                    nr.hfa_status as ns_hfa, 
                    nr.age_years as y, 
                    nr.age_months as am, 
                    nr.assessment_date as ad
                FROM student s
                LEFT JOIN nutritional_record nr ON nr.record_id = (
                    SELECT MAX(record_id) FROM nutritional_record WHERE student_id = s.student_id
                )
                WHERE s.is_enrolled = 1
                ORDER BY s.grade_level, s.section, s.last_name, s.first_name
            ";
            $res_f1 = $conn->query($sql_f1);

            if ($res_f1 && $res_f1->num_rows > 0):
                while ($r = $res_f1->fetch_assoc()):
                    $isSix = ($r['ad'] && $r['y'] >= 6) ? 'Yes' : ($r['ad'] ? 'No' : '--');
            ?>
            <tr>
                <td class="name-col"><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></td>
                <td><?= $r['sex'] == 'Female' ? 'F' : 'M' ?></td>
                <td><?= htmlspecialchars($r['grade_level'] . ' / ' . $r['section']) ?></td>
                <td><?= date('m/d/Y', strtotime($r['birth_date'])) ?></td>
                <td><?= $r['ad'] ? date('m/d/Y', strtotime($r['ad'])) : '--' ?></td>
                <td><?= $r['ad'] ? ($r['y'] . " Y / " . $r['am'] . " M") : '--' ?></td>
                <td><?= $r['w'] ?: '--' ?></td>
                <td><?= $r['h'] ?: '--' ?></td>
                <td style="font-weight: 700;"><?= $r['ns_bmi'] ?: '--' ?></td>
                <td><?= $r['ns_hfa'] ?: '--' ?></td>
                <td style="font-weight: 800; background: #fff7ed;"><?= $isSix ?></td>
                <td><?= $r['parent_milk_consent'] ? 'Yes' : 'No' ?></td>
                <td><?= $r['deworming_status'] ? 'Yes' : 'No' ?></td>
                <td><?= $r['participation_consent'] ? 'Yes' : 'No' ?></td>
                <td><?= $r['is_4ps_beneficiary'] ? 'Yes' : 'No' ?></td>
            </tr>
            <?php 
                endwhile;
            else: 
            ?>
            <tr>
                <td colspan="15" style="padding: 20px; text-align: center; color: #666;">No student records found.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <script>
        // Auto print on load
        window.onload = function() {
            setTimeout(() => { window.print(); }, 500);
        };
    </script>
</body>
</html>

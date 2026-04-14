<?php
require_once '../../db.php';

// Force Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename=SBFP_Form1_Export_' . date('Y-m-d') . '.xls');

// SBFP SQL Logic
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
    ORDER BY s.last_name, s.first_name
";
$res_f1 = $conn->query($sql_f1);
?>
<meta charset="UTF-8">
<table border="1">
    <thead>
        <tr style="background-color: #f1f5f9; text-align: center; font-weight: bold;">
            <th colspan="15" style="font-size: 1.25rem; padding: 10px;">SBFP FORM 1: Master List of Beneficiaries</th>
        </tr>
        <tr style="background-color: #e2e8f0; font-weight: bold;">
            <th>Name</th>
            <th>Sex</th>
            <th>Grade/Section</th>
            <th>Date of Birth</th>
            <th>Date of Weighing</th>
            <th>Age (Y/M)</th>
            <th>Weight (kg)</th>
            <th>Height (cm)</th>
            <th>Nutritional Status (BMI)</th>
            <th>Nutritional Status (HFA)</th>
            <th>BMI for 6+?</th>
            <th>Milk Consent</th>
            <th>Dewormed</th>
            <th>Consent</th>
            <th>4Ps</th>
        </tr>
    </thead>
    <tbody>
        <?php while ($r = $res_f1->fetch_assoc()): 
            $isSix = ($r['ad'] && $r['y'] >= 6) ? 'Yes' : ($r['ad'] ? 'No' : '--');
        ?>
            <tr>
                <td style="padding: 5px;"><?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['sex'] == 'Female' ? 'F' : 'M' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['grade_level'] ?> / <?= $r['section'] ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['birth_date'] ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['ad'] ?: '--' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['ad'] ? ($r['y'] . "Y / " . $r['am'] . "M") : '--' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['w'] ?: '--' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['h'] ?: '--' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['ns_bmi'] ?: '--' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['ns_hfa'] ?: '--' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $isSix ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['parent_milk_consent'] ? 'Yes' : 'No' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['deworming_status'] ? 'Yes' : 'No' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['participation_consent'] ? 'Yes' : 'No' ?></td>
                <td style="padding: 5px; text-align: center;"><?= $r['is_4ps_beneficiary'] ? 'Yes' : 'No' ?></td>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

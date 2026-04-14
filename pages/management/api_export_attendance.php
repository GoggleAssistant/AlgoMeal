<?php
require_once '../../db.php';

// Force Excel download
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename=AlgoMeal_Attendance_Report_' . date('Y-m-d') . '.xls');

// Fetch dates
$days = [];
$dishes = [];
$res_served = $conn->query("
    SELECT dp.scheduled_date, ra.recipe_name AS meal_a, rb.recipe_name AS meal_b 
    FROM daily_meal_plans dp 
    LEFT JOIN recipes ra ON dp.meal_a_recipe_id = ra.recipe_id 
    LEFT JOIN recipes rb ON dp.meal_b_recipe_id = rb.recipe_id 
    WHERE dp.is_served = 1 
    ORDER BY dp.scheduled_date ASC
");
while ($rd = $res_served->fetch_assoc()) {
    $days[] = $rd['scheduled_date'];
    $dish_name = $rd['meal_a'] ?: 'Unknown Dish';
    if (!empty($rd['meal_b']))
        $dish_name .= ' & ' . $rd['meal_b'];
    $dishes[$rd['scheduled_date']] = $dish_name;
}

$meal_data = [];
if (!empty($days)) {
    $d_in = implode("','", $days);
    $res_meals = $conn->query("SELECT student_id, scheduled_date, feeding_status FROM meal_plan WHERE scheduled_date IN ('$d_in')");
    while ($rm = $res_meals->fetch_assoc())
        $meal_data[$rm['student_id']][$rm['scheduled_date']] = $rm['feeding_status'];
}

$res_stud_att = $conn->query("SELECT student_id, CONCAT(first_name, ' ', last_name) as full_name FROM student ORDER BY full_name");
?>
<meta charset="UTF-8">
<table border="1">
    <thead>
        <tr style="background-color: #f1f5f9; font-weight: bold;">
            <th style="padding: 10px;">Student Name</th>
            <?php foreach ($days as $d): ?>
                <th style="padding: 10px; text-align: center;">
                    <?= date('Y-m-d', strtotime($d)) ?><br>
                    <?= htmlspecialchars($dishes[$d]) ?>
                </th>
            <?php endforeach; ?>
        </tr>
    </thead>
    <tbody>
        <?php while ($s = $res_stud_att->fetch_assoc()): ?>
            <tr>
                <td style="padding: 8px; font-weight: bold;"><?= htmlspecialchars($s['full_name']) ?></td>
                <?php foreach ($days as $d):
                    $status = $meal_data[$s['student_id']][$d] ?? '--';
                    echo "<td style='padding: 8px; text-align: center;'>" . ($status === 'Served' ? '✓' : ($status === 'Absent' ? 'X' : '--')) . "</td>";
                endforeach; ?>
            </tr>
        <?php endwhile; ?>
    </tbody>
</table>

<?php
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../db.php';

$student_id = $_GET['id'] ?? '';
if (empty($student_id)) {
    header('Location: students.php');
    exit;
}

// Fetch Student Main Info
$stmt = $conn->prepare("
    SELECT s.*, 
           (SELECT height FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_height,
           (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_weight,
           (SELECT nutritional_status FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as ns_status,
           (SELECT hfa_status FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as hfa_status,
           (SELECT assessment_date FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as last_weighing
    FROM student s 
    WHERE s.student_id = ?
");
$stmt->bind_param("s", $student_id);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    echo "<div style='padding:2rem; text-align:center;'>Student not found.</div>";
    require_once '../../includes/footer.php';
    exit;
}

// Calculate Current Age
$dob = new DateTime($student['birth_date']);
$now = new DateTime();
$diff = $now->diff($dob);
$age_text = $diff->y . " Years, " . $diff->m . " Months";

// Fetch Assessment History
$history_res = $conn->query("SELECT * FROM nutritional_record WHERE student_id = '$student_id' ORDER BY assessment_date ASC");

// Fetch Diet Restrictions
$res_stmt = $conn->prepare("SELECT dr.restriction_name FROM student_allergy_map sam JOIN dietary_restrictions dr ON sam.restriction_id = dr.restriction_id WHERE sam.student_id = ?");
$res_stmt->bind_param("s", $student_id);
$res_stmt->execute();
$restrictions_res = $res_stmt->get_result();
$allergies = [];
while($r = $restrictions_res->fetch_assoc()) $allergies[] = $r['restriction_name'];

// Fetch Sections
$section_result = $conn->query("SELECT grade_level, section FROM student GROUP BY grade_level, section ORDER BY grade_level, section");
$sections = [];
while ($row = $section_result->fetch_assoc()) $sections[] = $row;

// Fetch All Restrictions
$restrictions_result = $conn->query("SELECT restriction_id, restriction_name FROM dietary_restrictions ORDER BY restriction_name ASC");
$all_restrictions = [];
while ($row = $restrictions_result->fetch_assoc()) $all_restrictions[] = $row;

// Fetch Average Supplemental Calories from Scheduled Meals
$kcal_stmt = $conn->prepare("
    SELECT AVG(r.energy_kcal) as avg_kcal 
    FROM meal_plan mp 
    JOIN recipes r ON mp.recipe_id = r.recipe_id 
    WHERE mp.student_id = ?
");
$kcal_stmt->bind_param("s", $student_id);
$kcal_stmt->execute();
$avg_kcal_res = $kcal_stmt->get_result()->fetch_assoc();
$avg_kcal = $avg_kcal_res['avg_kcal'] ? (float)$avg_kcal_res['avg_kcal'] : 500.0; // Fallback to 500 if no meals

$page_title = 'Student Profile';
require_once '../../includes/topbar.php';

// Prepare Chart Data
$chart_labels = [];
$chart_raw_data = [];

// Create a copy of the history for the chart
$chart_res = $conn->query("SELECT record_id, assessment_date, weight, height, nutritional_status, hfa_status, age_years, age_months FROM nutritional_record WHERE student_id = '$student_id' ORDER BY assessment_date ASC");
$student_dob = strtotime($student['birth_date']);
while($c = $chart_res->fetch_assoc()) {
    $date_ts = strtotime($c['assessment_date']);
    $age_years = ($date_ts - $student_dob) / (365.25 * 86400);
    $bmi = ($c['height'] > 0) ? round($c['weight'] / pow($c['height']/100, 2), 1) : 0;
    $chart_labels[] = date('M d, Y', $date_ts);
    $chart_raw_data[] = [
        'x'        => $date_ts,
        'y'        => $bmi,
        'weight'   => (float)$c['weight'],
        'height'   => (float)$c['height'],
        'age'      => $age_years,
        'record_id'=> (int)$c['record_id'],
        'date_label'=> date('M d, Y', $date_ts),
        'ns_status'=> $c['nutritional_status'],
        'hfa_status'=> $c['hfa_status'],
        'age_y'    => (int)$c['age_years'],
        'age_m'    => (int)$c['age_months']
    ];
}
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="content">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
        <div style="display:flex; align-items:center; gap: 1.5rem;">
            <div style="width: 80px; height: 80px; background: var(--primary); color: white; border-radius: 50%; display:flex; align-items:center; justify-content:center; font-size: 2rem; font-weight: 900; box-shadow: 0 8px 16px -4px rgba(59, 130, 246, 0.4);">
                <?= strtoupper(substr($student['first_name'], 0, 1)) ?>
            </div>
            <div>
                <h2 style="margin: 0; font-size: 1.75rem; font-weight: 900; color: var(--text-main);"><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></h2>
                <div style="display:flex; gap: 0.75rem; align-items:center; margin-top:0.25rem;">
                    <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">LRN: <?= $student['student_id'] ?></span>
                    <span style="width: 4px; height: 4px; background: #cbd5e1; border-radius: 50%;"></span>
                    <span style="font-size: 0.9rem; color: var(--primary); font-weight: 800;"><?= $student['grade_level'] ?> - <?= $student['section'] ?></span>
                </div>
            </div>
        </div>
        <div style="display:flex; gap: 0.75rem;">
            <?php if ($role === 'Admin' || $role === 'Super Admin' || $role === 'Faculty'): ?>
            <button class="btn-m3 btn-m3-tonal" onclick="openAddAssessmentModal()">
                <span class="material-icons" style="font-size: 18px;">add</span>
                <span>Add Assessment</span>
            </button>
            <?php endif; ?>

            <?php if ($role === 'Admin' || $role === 'Super Admin'): ?>
            <button class="btn-m3 btn-m3-tonal" onclick="openEditStudentModal()">
                <span class="material-icons" style="font-size: 18px;">edit</span>
                <span>Edit Profile</span>
            </button>
            <button class="btn-m3 <?= $student['is_enrolled'] ? 'btn-m3-outline' : 'btn-m3-primary' ?>" onclick="toggleEnrollment()">
                <span class="material-icons" style="font-size: 18px;"><?= $student['is_enrolled'] ? 'person_off' : 'person_add' ?></span>
                <span><?= $student['is_enrolled'] ? 'Unenroll' : 'Re-enroll' ?></span>
            </button>
            <?php endif; ?>

            <button class="btn-m3 btn-m3-outline" onclick="window.history.back()">
                <span class="material-icons" style="font-size: 18px;">arrow_back</span>
                <span>Back to Roster</span>
            </button>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
        <!-- Left Column: Core Info -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <div class="dashboard-card" style="padding: 1.5rem;">
                <h3 style="margin: 0 0 1.5rem 0; font-size: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem;">Biometric & Consent Information</h3>
                
                <div style="display:grid; gap: 1rem;">
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Sex</div>
                        <div style="font-weight: 800; color: var(--text-main);"><?= $student['sex'] ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Birth Date</div>
                        <div style="font-weight: 800; color: var(--text-main);"><?= date('F d, Y', strtotime($student['birth_date'])) ?></div>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Current Age</div>
                        <div style="font-weight: 800; color: var(--text-main); "><?= $age_text ?></div>
                    </div>
                    
                    <hr style="border:none; border-top: 1px solid var(--border); margin: 0.5rem 0;">

                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Milk Consent</div>
                        <span class="badge" style="background: <?= $student['parent_milk_consent'] ? '#dcfce7' : '#fee2e2' ?>; color: <?= $student['parent_milk_consent'] ? '#166534' : '#991b1b' ?>; font-weight: 800;">
                            <?= $student['parent_milk_consent'] ? 'YES' : 'NO' ?>
                        </span>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Participation Consent</div>
                        <span class="badge" style="background: <?= $student['participation_consent'] ? '#dcfce7' : '#fee2e2' ?>; color: <?= $student['participation_consent'] ? '#166534' : '#991b1b' ?>; font-weight: 800;">
                            <?= $student['participation_consent'] ? 'YES' : 'NO' ?>
                        </span>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Dewormed</div>
                        <span class="badge" style="background: <?= $student['deworming_status'] ? '#e0f2fe' : '#f1f5f9' ?>; color: <?= $student['deworming_status'] ? '#075985' : '#64748b' ?>; font-weight: 800;">
                            <?= $student['deworming_status'] ? 'YES' : 'NO' ?>
                        </span>
                    </div>
                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">4Ps Beneficiary</div>
                        <span class="badge" style="background: <?= $student['is_4ps_beneficiary'] ? '#fef3c7' : '#f1f5f9' ?>; color: <?= $student['is_4ps_beneficiary'] ? '#92400e' : '#64748b' ?>; font-weight: 800;">
                            <?= $student['is_4ps_beneficiary'] ? 'YES' : 'NO' ?>
                        </span>
                    </div>

                    <hr style="border:none; border-top: 1px solid var(--border); margin: 0.5rem 0;">

                    <div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 0.05em;">Dietary Restrictions</div>
                        <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-top:0.4rem;">
                            <?php foreach($allergies as $a): ?>
                                <span style="font-size:0.65rem; font-weight:800; background:#fee2e2; color:#991b1b; padding:2px 8px; border-radius:4px; border:1px solid #991b1b22;"><?= $a ?></span>
                            <?php endforeach; if(empty($allergies)) echo '<span style="color:var(--text-muted); font-size:0.8rem;">None recorded</span>'; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Nutritional Grid -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            <!-- Current Status Cards -->
            <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 1rem;">
                <div class="dashboard-card" style="padding: 1.25rem; border-left: 4px solid var(--primary);">
                    <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Latest Weight</div>
                    <div style="font-size: 1.5rem; font-weight: 900;"><?= $student['current_weight'] ?? '--' ?> <span style="font-size: 0.8rem; color:var(--text-muted);">kg</span></div>
                </div>
                <div class="dashboard-card" style="padding: 1.25rem; border-left: 4px solid #10b981;">
                    <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">Latest Height</div>
                    <div style="font-size: 1.5rem; font-weight: 900;"><?= $student['current_height'] ?? '--' ?> <span style="font-size: 0.8rem; color:var(--text-muted);">cm</span></div>
                </div>
                <div class="dashboard-card" style="padding: 1.25rem; border-left: 4px solid #f59e0b;">
                    <div style="font-size: 0.7rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase;">BMI-A / HFA</div>
                    <div style="font-size: 0.95rem; font-weight: 900; margin-top:0.25rem;">
                        <span style="display:block;"><?= $student['ns_status'] ?: 'N/A' ?></span>
                        <span style="font-size:0.75rem; color:var(--text-muted); font-weight:500;"><?= $student['hfa_status'] ?: 'No HFA Data' ?></span>
                    </div>
                </div>
            </div>

            <?php
            $ns_status = $student['ns_status'] ?? 'Unknown';
            $health_advice = "Monitor growth progress regularly.";
            $advice_icon = "info";
            $advice_color = "#3b82f6";

            if (strpos($ns_status, 'Wasted') !== false) {
                $health_advice = "High priority for SBFP. Recommend a caloric surplus with protein-rich meals (e.g., eggs, chicken, legumes) and nutrient-dense snacks. Monitor weight gain progress bi-weekly.";
                $advice_icon = "warning";
                $advice_color = "#ef4444";
            } elseif ($ns_status === 'Normal') {
                $health_advice = "Maintain current balanced diet. Continue serving diverse meals including vegetables, lean proteins, and complex carbohydrates to support steady growth.";
                $advice_icon = "check_circle";
                $advice_color = "#10b981";
            } elseif ($ns_status === 'Overweight' || $ns_status === 'Obese') {
                $health_advice = "Focus on portion control and nutrient-dense, low-calorie foods (more vegetables, less fried items). Encourage daily physical activity to stabilize BMI.";
                $advice_icon = "fitness_center";
                $advice_color = "#f59e0b";
            }
            ?>
            <!-- Health Recommendations -->
            <div class="dashboard-card" style="padding: 1.5rem; background: <?= $advice_color ?>15; border: 1px solid <?= $advice_color ?>50;">
                <div style="display:flex; align-items:center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <span class="material-icons" style="color: <?= $advice_color ?>;"><?= $advice_icon ?></span>
                    <h3 style="margin: 0; font-size: 1rem; color: <?= $advice_color ?>;">Health & Nutrition Recommendations</h3>
                </div>
                <p style="margin: 0; font-size: 0.85rem; color: var(--text-main); line-height: 1.5;">
                    <?= $health_advice ?>
                </p>
            </div>

            <!-- Growth Chart -->
            <div class="dashboard-card" style="padding: 1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 1rem;">
                    <h3 style="margin: 0; font-size: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; flex:1;">Growth Analysis Line Graph</h3>
                    <div id="predictionInsight" style="display:none; text-align:right;">
                        <span class="badge" style="background:#ecfdf5; color:#059669; font-weight:800; padding:6px 12px; border-radius:8px;">
                            <span class="material-icons" style="font-size:14px; vertical-align:middle;">speed</span>
                            <span id="recoveryTimeDisplay">Calculating recovery...</span>
                        </span>
                    </div>
                </div>
                
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; gap: 1rem; flex-wrap: wrap; background: #f8fafc; padding: 0.75rem; border-radius: 8px; border: 1px solid var(--border);">
                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                        <label style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Date Filter:</label>
                        <input type="date" id="chartStartDate" style="padding: 0.25rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.8rem;">
                        <span style="font-size:0.8rem; color:var(--text-muted);">to</span>
                        <input type="date" id="chartEndDate" style="padding: 0.25rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.8rem;">
                        <button id="applyChartFilter" class="btn-m3 btn-m3-tonal" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;">Apply</button>
                        <button id="clearChartFilter" class="btn-m3 btn-m3-outline" style="padding: 0.25rem 0.75rem; font-size: 0.8rem;">Clear</button>
                    </div>
                    <div>
                        <label class="switch-label" style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; font-weight:600; cursor:pointer; color: var(--text-main);">
                            <div class="switch">
                                <input type="checkbox" id="togglePredictions" checked>
                                <span class="slider round"></span>
                            </div>
                            Show Prediction Nodes
                        </label>
                        <style>
                        .switch { position: relative; display: inline-block; width: 34px; height: 20px; }
                        .switch input { opacity: 0; width: 0; height: 0; }
                        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .4s; }
                        .slider:before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .4s; }
                        input:checked + .slider { background-color: var(--primary); }
                        input:checked + .slider:before { transform: translateX(14px); }
                        .slider.round { border-radius: 20px; }
                        .slider.round:before { border-radius: 50%; }
                        </style>
                    </div>
                </div>

                <div style="height: 300px; display: flex; align-items: center; justify-content: center; position: relative;">
                    <?php if (empty($chart_raw_data)): ?>
                        <div style="text-align: center; color: var(--text-muted); padding: 2rem;">
                            <div style="background: var(--bg-color); width: 64px; height: 64px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1rem; border: 1px solid var(--border);">
                                <span class="material-icons" style="font-size: 32px; color: var(--primary);">monitoring</span>
                            </div>
                            <h4 style="margin: 0; color: var(--text-main); font-weight: 800;">No Biometric History</h4>
                            <p style="margin: 0.5rem 0 0; font-size: 0.85rem;">Add a nutritional assessment to begin tracking growth progress.</p>
                        </div>
                    <?php else: ?>
                        <canvas id="growthChart"></canvas>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dashboard-card" style="padding:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 1.5rem;">
                    <h3 style="margin: 0; font-size: 1rem;">Assessment History</h3>
                </div>
                
                <table style="width:100%; border-collapse: collapse; font-size: 0.9rem;">
                    <thead>
                        <tr style="text-align:left; border-bottom: 2px solid var(--border);">
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-muted); font-size: 0.75rem;">DATE</th>
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-muted); font-size: 0.75rem;">AGE</th>
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-muted); font-size: 0.75rem;">WEIGHT</th>
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-muted); font-size: 0.75rem;">HEIGHT</th>
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-muted); font-size: 0.75rem;">BMI-A</th>
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-muted); font-size: 0.75rem;">HFA</th>
                            <th style="padding: 0.75rem 0.5rem; color: var(--text-muted); font-size: 0.75rem;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Re-query history with record_id included
                        $history_detail_res = $conn->query("SELECT * FROM nutritional_record WHERE student_id = '$student_id' ORDER BY assessment_date DESC, record_id DESC");
                        while($h = $history_detail_res->fetch_assoc()):
                            $bmi_val = ($h['height'] > 0) ? round($h['weight'] / pow($h['height']/100, 2), 1) : 0;
                            $ns_color = match($h['nutritional_status']) {
                                'Normal' => '#059669',
                                'Obese', 'Overweight' => '#dc2626',
                                default => '#d97706'
                            };
                            $rec_json = htmlspecialchars(json_encode([
                                'date'   => date('F d, Y', strtotime($h['assessment_date'])),
                                'age_y'  => $h['age_years'],
                                'age_m'  => $h['age_months'],
                                'weight' => $h['weight'],
                                'height' => $h['height'],
                                'bmi'    => $bmi_val,
                                'ns'     => $h['nutritional_status'],
                                'hfa'    => $h['hfa_status'] ?: '---'
                            ]), ENT_QUOTES);
                        ?>
                        <tr style="border-bottom: 1px solid var(--border); transition: background 0.15s;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background=''">
                            <td style="padding: 0.75rem 0.5rem; font-weight: 800;"><?= date('M d, Y', strtotime($h['assessment_date'])) ?></td>
                            <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);"><?= $h['age_years'] ?>Y, <?= $h['age_months'] ?>M</td>
                            <td style="padding: 0.75rem 0.5rem; font-weight: 700;"><?= $h['weight'] ?> kg</td>
                            <td style="padding: 0.75rem 0.5rem; font-weight: 700;"><?= $h['height'] ?> cm</td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <span style="font-weight: 800; color: <?= $ns_color ?>;"><?= $h['nutritional_status'] ?></span>
                            </td>
                            <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);"><?= $h['hfa_status'] ?: '---' ?></td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <button class="btn-m3 btn-m3-tonal" style="padding: 4px 10px; font-size: 0.7rem;" onclick='showAssessmentDetail(<?= $rec_json ?>)'>
                                    <span class="material-icons" style="font-size:13px;">open_in_new</span> View
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; if($history_detail_res->num_rows === 0): ?>
                            <tr><td colspan="7" style="padding: 3rem; text-align:center; color:var(--text-muted);">No assessments recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="dashboard-card" style="padding:1.5rem; background: #fdf2f2; border: 1px solid #fecaca;">
                <h3 style="margin: 0 0 1rem 0; font-size: 0.9rem; color: #991b1b;"><span class="material-icons" style="font-size:18px; vertical-align:middle;">warning</span> Target Optimization</h3>
                <p style="font-size: 0.8rem; color: #991b1b; margin-bottom: 1rem;">Assigned targets for heuristic meal generation engine:</p>
                <div style="display:flex; gap: 2rem;">
                    <div>
                        <div style="font-size: 0.65rem; color: #991b1b; font-weight: 700; text-transform: uppercase;">Min Target Weight</div>
                        <div style="font-size: 1.25rem; font-weight: 900; color: #991b1b;"><?= number_format($student['min_target_weight'], 1) ?> <span style="font-size:0.7rem;">kg</span></div>
                    </div>
                    <div>
                        <div style="font-size: 0.65rem; color: #991b1b; font-weight: 700; text-transform: uppercase;">Max Target Weight</div>
                        <div style="font-size: 1.25rem; font-weight: 900; color: #991b1b;"><?= number_format($student['max_target_weight'], 1) ?> <span style="font-size:0.7rem;">kg</span></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const ctx = document.getElementById('growthChart').getContext('2d');
        const rawDataMaster = <?= json_encode($chart_raw_data) ?>;
        let growthChartInstance = null;

        if (rawDataMaster.length === 0) {
            return;
        }

        // --- PREDICTIVE ENGINE (Biologically-Based Forecasting) ---
        const avgSupplementalKcal = parseFloat(<?= json_encode($avg_kcal) ?>) || 0;
        const currentHeightCm = parseFloat(<?= json_encode($student['current_height'] ?? 0) ?>) || 0;
        const currentWeightKg = parseFloat(<?= json_encode($student['current_weight'] ?? 0) ?>) || 0;

        // Initialize date inputs (Left empty to show unrestricted full history by default)

        // Helper to approximate WHO BMI-for-Age 5th and 85th Percentiles
        function getDynamicBMIBounds(ageYears) {
            let clampedAge = Math.max(5, Math.min(19, ageYears));
            let minBMI = 13.0 + (clampedAge - 5) * 0.4;
            let maxBMI = 16.5 + (clampedAge - 5) * 0.6;
            return { min: minBMI, max: maxBMI };
        }

        function getPredictionDays(workingData) {
            let daysOut = [30, 60, 90, 120];
            if (workingData.length < 1 || currentHeightCm <= 0) return daysOut;
            const lastData = workingData[workingData.length-1];
            const currentBMI = lastData.y;
            const bounds = getDynamicBMIBounds(lastData.age);
            
            if (currentBMI < bounds.min && avgSupplementalKcal > 0) {
                const heightM = currentHeightCm / 100;
                const targetWeight = bounds.min * (heightM * heightM);
                const weightDiff = targetWeight - currentWeightKg;
                
                if (weightDiff > 0) {
                    const dailyWeightGain = avgSupplementalKcal / 7700;
                    const daysLimit = Math.ceil(weightDiff / dailyWeightGain);
                    if (daysLimit > 0 && daysLimit < 1000) {
                        daysOut = daysOut.filter(d => d < daysLimit);
                        daysOut.push(daysLimit);
                    }
                }
            }
            return daysOut;
        }

        function predictNextPoints(workingData, daysOut) {
            if (workingData.length < 1 || currentHeightCm <= 0) return [];
            const lastData = workingData[workingData.length - 1];
            
            const dailyWeightGain = avgSupplementalKcal / 7700;
            const heightM = lastData.height / 100;
            const bounds = getDynamicBMIBounds(lastData.age);
            
            return daysOut.map(d => {
                const projectedWeight = currentWeightKg + (dailyWeightGain * d);
                const projectedBMI = projectedWeight / (heightM * heightM);
                
                const date = new Date(lastData.x * 1000);
                date.setDate(date.getDate() + d);
                
                // Calculate age at projection
                const projectedAge = lastData.age + (d / 365.25);
                const projBounds = getDynamicBMIBounds(projectedAge);
                
                let label = "Predicted: " + date.toLocaleDateString('en-US', {month:'short', day:'numeric'});
                if (Math.abs(projectedBMI - projBounds.min) < 0.2) {
                    label = "Target Normal: " + date.toLocaleDateString('en-US', {month:'short', day:'numeric'});
                }

                return {
                    label: label,
                    val: parseFloat(projectedBMI.toFixed(1)),
                    age: projectedAge
                };
            });
        }

        function calculateRecovery(workingData) {
            if (workingData.length < 1 || currentHeightCm <= 0) return;
            
            const lastData = workingData[workingData.length-1];
            const currentBMI = lastData.y;
            const bounds = getDynamicBMIBounds(lastData.age);
            
            let targetBMI = 0;
            let verb = "";
            
            if (currentBMI < bounds.min) {
                targetBMI = bounds.min;
                verb = "Normal Coverage";
            } else if (currentBMI > bounds.max) {
                return; 
            }

            if (targetBMI > 0 && avgSupplementalKcal > 0) {
                const heightM = currentHeightCm / 100;
                const targetWeight = targetBMI * (heightM * heightM);
                const weightDiff = targetWeight - currentWeightKg;
                
                if (weightDiff > 0) {
                    const dailyWeightGain = avgSupplementalKcal / 7700;
                    const daysLimit = Math.ceil(weightDiff / dailyWeightGain);
                    
                    if (daysLimit > 0 && daysLimit < 1000) {
                        const targetDate = new Date(workingData[workingData.length-1].x * 1000);
                        targetDate.setDate(targetDate.getDate() + daysLimit);
                        document.getElementById('predictionInsight').style.display = 'block';
                        document.getElementById('recoveryTimeDisplay').innerText = `Est. ${daysLimit} days to reach ${verb} (${targetDate.toLocaleDateString()})`;
                    }
                }
            } else {
                document.getElementById('predictionInsight').style.display = 'none';
            }
        }

        function renderChart() {
            const startStr = document.getElementById('chartStartDate').value;
            const endStr = document.getElementById('chartEndDate').value;
            const showPredictions = document.getElementById('togglePredictions').checked;

            let filteredData = rawDataMaster;
            if (startStr && endStr) {
                const startTs = new Date(startStr).getTime() / 1000;
                // Add 1 day to endTs to make it inclusive
                const endTs = (new Date(endStr).getTime() / 1000) + 86400; 
                filteredData = rawDataMaster.filter(d => d.x >= startTs && d.x <= endTs);
            }

            if(filteredData.length === 0) filteredData = rawDataMaster;

            const filteredLabels = filteredData.map(d => new Date(d.x * 1000).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'}));
            const filteredBmis = filteredData.map(d => {
                const hM = d.height / 100;
                return parseFloat((d.weight / (hM * hM)).toFixed(1));
            });

            let fullLabels = [...filteredLabels];
            let actualData = [...filteredBmis];
            let predictedDataset = [];
            
            // To calculate min/max lines over time, we use the exact age at that specific data point
            let combinedDataPoints = [...filteredData];

            calculateRecovery(filteredData);

            if (showPredictions) {
                const predictionDays = getPredictionDays(filteredData);
                const predictions = predictNextPoints(filteredData, predictionDays);
                const predictionLabels = predictions.map(p => p.label);
                
                if (predictions.length > 0) {
                    const lastActual = actualData[actualData.length - 1];
                    const predictionValues = [lastActual, ...predictions.map(p => p.val)];
                    predictedDataset = Array(actualData.length - 1).fill(null).concat(predictionValues);
                    fullLabels = [...filteredLabels, ...predictionLabels];
                    
                    // Push the prediction ages to extend the dynamic bands
                    predictions.forEach(p => combinedDataPoints.push({ age: p.age }));
                }
            }

            const minBMILine = combinedDataPoints.map(d => parseFloat(getDynamicBMIBounds(d.age).min.toFixed(1)));
            const maxBMILine = combinedDataPoints.map(d => parseFloat(getDynamicBMIBounds(d.age).max.toFixed(1)));

            if (growthChartInstance) {
                growthChartInstance.destroy();
            }

            growthChartInstance = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: fullLabels,
                    datasets: [
                        {
                            label: 'Actual BMI',
                            data: actualData,
                            borderColor: '#3b82f6',
                            backgroundColor: '#3b82f622',
                            borderWidth: 4,
                            tension: 0.4,
                            fill: true,
                            pointRadius: 5,
                            pointBackgroundColor: '#fff',
                            z: 10
                        },
                        {
                            label: 'Predicted Growth Path',
                            data: predictedDataset,
                            borderColor: '#94a3b8',
                            backgroundColor: 'transparent',
                            borderDash: [6, 4],
                            borderWidth: 2,
                            tension: 0.4,
                            pointRadius: 4,
                            pointBackgroundColor: '#f8fafc'
                        },
                        {
                            label: 'Healthy Range',
                            data: minBMILine,
                            borderColor: '#059669',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: false,
                            pointRadius: 0
                        },
                        {
                            label: '_healthy_max',
                            data: maxBMILine,
                            borderColor: '#059669',
                            borderWidth: 2,
                            borderDash: [5, 5],
                            fill: '-1',
                            backgroundColor: 'rgba(5, 150, 105, 0.07)',
                            pointRadius: 0
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    onClick: (evt, elements) => {
                        // Only respond to clicks on the Actual BMI dataset (index 0)
                        const actualPoint = elements.find(e => e.datasetIndex === 0);
                        if (actualPoint) {
                            const d = filteredData[actualPoint.index];
                            if (d && d.record_id) {
                                showAssessmentDetail({
                                    date:   d.date_label,
                                    age_y:  d.age_y,
                                    age_m:  d.age_m,
                                    weight: d.weight,
                                    height: d.height,
                                    bmi:    d.y,
                                    ns:     d.ns_status,
                                    hfa:    d.hfa_status
                                });
                            }
                        }
                    },
                    plugins: {
                        legend: { 
                            position: 'top', 
                            onClick: null,
                            labels: {
                                usePointStyle: true,
                                font: { weight: 'bold' },
                                filter: (item) => !item.text.startsWith('_')
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    let label = context.dataset.label || '';
                                    if (label.includes('Predicted')) return `Projected BMI: ${context.parsed.y}`;
                                    return `BMI: ${context.parsed.y} kg/m²`;
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: false,
                            title: { display: true, text: 'BMI (kg/m²)', font: { weight: 'bold' } },
                            suggestedMin: 12,
                            suggestedMax: 28
                        }
                    }
                }
            });

            // Make chart cursor a pointer when hovering actual data points
            growthChartInstance.canvas.style.cursor = 'default';
            growthChartInstance.canvas.addEventListener('mousemove', (e) => {
                const pts = growthChartInstance.getElementsAtEventForMode(e, 'nearest', { intersect: true }, false);
                const onActual = pts.some(p => p.datasetIndex === 0);
                growthChartInstance.canvas.style.cursor = onActual ? 'pointer' : 'default';
            });
        }

        renderChart();

        document.getElementById('applyChartFilter').addEventListener('click', renderChart);
        document.getElementById('clearChartFilter').addEventListener('click', () => {
            document.getElementById('chartStartDate').value = '';
            document.getElementById('chartEndDate').value = '';
            renderChart();
        });
        document.getElementById('togglePredictions').addEventListener('change', renderChart);
    });

    function showAssessmentDetail(d) {
        const nsColors = {
            'Normal': { bg: '#dcfce7', color: '#166534' },
            'Obese':  { bg: '#fee2e2', color: '#991b1b' },
            'Overweight': { bg: '#fee2e2', color: '#991b1b' },
            'Wasted': { bg: '#fef3c7', color: '#92400e' },
            'Severely Wasted': { bg: '#fef3c7', color: '#92400e' }
        };
        const nsStyle = nsColors[d.ns] || { bg: '#f1f5f9', color: '#475569' };
        const hfaLabel = d.hfa || '---';
        const bmiVal = d.bmi ? parseFloat(d.bmi).toFixed(1) : 'N/A';
        
        const body = `
            <div style="text-align:center; margin-bottom: 1.25rem;">
                <div style="font-size:0.75rem; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:0.05em; margin-bottom:0.25rem;">Assessment Date</div>
                <div style="font-size:1.15rem; font-weight:900; color:var(--text-main);">${d.date}</div>
                <div style="font-size:0.8rem; color:var(--text-muted); margin-top:0.15rem;">${d.age_y} Years, ${d.age_m} Months old</div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:0.75rem; margin-bottom:1.25rem;">
                <div style="background:#f0f7ff; border-radius:10px; padding:1rem; text-align:center;">
                    <div style="font-size:0.65rem; font-weight:800; color:var(--primary); text-transform:uppercase; margin-bottom:0.25rem;">Weight</div>
                    <div style="font-size:1.5rem; font-weight:900; color:var(--text-main);">${d.weight}</div>
                    <div style="font-size:0.7rem; color:var(--text-muted);">kg</div>
                </div>
                <div style="background:#f0fdf4; border-radius:10px; padding:1rem; text-align:center;">
                    <div style="font-size:0.65rem; font-weight:800; color:#059669; text-transform:uppercase; margin-bottom:0.25rem;">Height</div>
                    <div style="font-size:1.5rem; font-weight:900; color:var(--text-main);">${d.height}</div>
                    <div style="font-size:0.7rem; color:var(--text-muted);">cm</div>
                </div>
                <div style="background:#fefce8; border-radius:10px; padding:1rem; text-align:center;">
                    <div style="font-size:0.65rem; font-weight:800; color:#d97706; text-transform:uppercase; margin-bottom:0.25rem;">BMI</div>
                    <div style="font-size:1.5rem; font-weight:900; color:var(--text-main);">${bmiVal}</div>
                    <div style="font-size:0.7rem; color:var(--text-muted);">kg/m²</div>
                </div>
            </div>
            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                <div style="border-radius:10px; padding:0.85rem 1rem; background:${nsStyle.bg};">
                    <div style="font-size:0.65rem; font-weight:800; color:${nsStyle.color}; text-transform:uppercase; margin-bottom:0.3rem;">BMI-for-Age Status</div>
                    <div style="font-size:1rem; font-weight:900; color:${nsStyle.color};">${d.ns || 'N/A'}</div>
                </div>
                <div style="border-radius:10px; padding:0.85rem 1rem; background:#f8fafc; border:1px solid var(--border);">
                    <div style="font-size:0.65rem; font-weight:800; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.3rem;">Height-for-Age Status</div>
                    <div style="font-size:1rem; font-weight:900; color:var(--text-main);">${hfaLabel}</div>
                </div>
            </div>
        `;
        AlgoModal.show({
            title: 'Assessment Details',
            body: body,
            footer: `<button class="btn-m3 btn-m3-primary" onclick="AlgoModal.close()">Close</button>`
        });
    }

    // EDIT STUDENT LOGIC
    const studentData = <?= json_encode($student) ?>;
    const studentAllergies = <?= json_encode($allergies) ?>;

    function openEditStudentModal() {
        document.getElementById('edit_original_lrn').value = studentData.student_id;
        document.getElementById('edit_student_id').value = studentData.student_id;
        document.getElementById('edit_first_name').value = studentData.first_name;
        document.getElementById('edit_last_name').value = studentData.last_name;
        document.getElementById('edit_sex').value = studentData.sex;
        document.getElementById('edit_birth_date').value = studentData.birth_date;
        document.getElementById('edit_grade_level').value = studentData.grade_level;
        
        const secSelect = document.getElementById('edit_section_select');
        let secFound = false;
        Array.from(secSelect.options).forEach(opt => {
            if (opt.value === studentData.section) secFound = true;
        });
        
        if (secFound) {
            secSelect.value = studentData.section;
            document.getElementById('editManualSectionInput').style.display = 'none';
        } else {
            secSelect.value = 'Other';
            document.getElementById('editManualSectionInput').style.display = 'block';
            document.getElementById('editManualSectionInput').value = studentData.section;
        }

        document.getElementById('edit_milk_consent').value = studentData.parent_milk_consent;
        document.getElementById('edit_participation_consent').value = studentData.participation_consent;
        document.getElementById('edit_is_dewormed').value = studentData.deworming_status;
        document.getElementById('edit_is_4ps').value = studentData.is_4ps_beneficiary;
        
        document.getElementById('edit_min_target').value = studentData.min_target_weight;
        document.getElementById('edit_max_target').value = studentData.max_target_weight;

        // Reset and check allergies
        document.querySelectorAll('.edit-allergy-cb').forEach(cb => cb.checked = false);
        document.querySelectorAll('.edit-allergy-cb').forEach(cb => {
            if (studentAllergies.includes(cb.parentElement.textContent.trim())) {
                cb.checked = true;
            }
        });

        document.getElementById('editStudentModal').classList.add('active');
    }

    function toggleManualSectionEdit(val) {
        document.getElementById('editManualSectionInput').style.display = (val === 'Other') ? 'block' : 'none';
    }

    document.getElementById('editStudentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        try {
            const res = await fetch('api_edit_student.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                // If LRN changed, redirect to new LRN URL
                const newLrn = fd.get('student_id');
                if (newLrn && newLrn !== studentData.student_id) {
                    window.location.href = `student_profile.php?id=${newLrn}`;
                } else {
                    location.reload();
                }
            } else {
                alert(data.error || 'Failed to update student.');
            }
        } catch(err) {
            alert('Network error.');
        }
    });

    async function deleteStudentAdmin() {
        if(!confirm('Are you sure you want to permanently delete this student?')) return;
        const fd = new FormData();
        fd.append('student_id', studentData.student_id);
        try {
            const res = await fetch('api_delete_student.php', { method: 'POST', body: fd });
            const data = await res.json();
            if(data.success) {
                location.href = 'students.php';
            } else {
                alert(data.error || 'Failed to delete student.');
            }
        } catch(err) {
            alert('Network error.');
        }
    }

    function openAddAssessmentModal() {
        document.getElementById('add_assessment_date').value = new Date().toISOString().split('T')[0];
        document.getElementById('add_height').value = '';
        document.getElementById('add_weight').value = '';
        document.getElementById('addAssessmentModal').classList.add('active');
    }

    document.getElementById('addAssessmentForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(e.target);
        fd.append('student_id', studentData.student_id);
        
        try {
            const res = await fetch('api_add_assessment.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Failed to add assessment.');
            }
        } catch(err) {
            alert('Network error.');
        }
    });

    async function toggleEnrollment() {
        const currentStatus = <?= $student['is_enrolled'] ?>;
        const newStatus = currentStatus ? 0 : 1;
        const actionText = currentStatus ? "unenroll" : "re-enroll";
        
        if (!confirm(`Are you sure you want to ${actionText} ${studentData.first_name}?`)) return;

        const fd = new FormData();
        fd.append('student_id', studentData.student_id);
        fd.append('status', newStatus);

        try {
            const res = await fetch('api_toggle_enrollment.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.error || 'Operation failed.');
            }
        } catch(err) {
            alert('Network error.');
        }
    }
</script>

<!-- Assessment Detail Modal is rendered via AlgoModal JS -->

<!-- Add Assessment Modal -->
<div class="modal-overlay" id="addAssessmentModal">
    <div class="modal" style="max-width: 400px; width: 95%;">
        <h2 class="modal-title">New Nutritional Assessment</h2>
        <form id="addAssessmentForm">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Assessment Date</label>
                <input type="date" name="assessment_date" id="add_assessment_date" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Height (cm)</label>
                    <input type="number" step="0.1" name="height" id="add_height" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Weight (kg)</label>
                    <input type="number" step="0.1" name="weight" id="add_weight" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
            </div>

            <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" class="btn-m3 btn-m3-outline" onclick="document.getElementById('addAssessmentModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn-m3 btn-m3-primary">Save Assessment</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Student Modal -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal" style="max-width: 500px; width: 95%; max-height: 90vh; overflow-y: auto;">
        <h2 class="modal-title">Edit Student Profile</h2>
        <form id="editStudentForm">
            <input type="hidden" name="original_lrn" id="edit_original_lrn">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">LRN (Student ID)</label>
                    <input type="text" name="student_id" id="edit_student_id" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Birth Date</label>
                    <input type="date" name="birth_date" id="edit_birth_date" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">First Name</label>
                    <input type="text" name="first_name" id="edit_first_name" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Last Name</label>
                    <input type="text" name="last_name" id="edit_last_name" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Sex</label>
                    <select name="sex" id="edit_sex" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Grade Level</label>
                    <select name="grade_level" id="edit_grade_level" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="Grade 1">Grade 1</option>
                        <option value="Grade 2">Grade 2</option>
                        <option value="Grade 3">Grade 3</option>
                        <option value="Grade 4">Grade 4</option>
                        <option value="Grade 5">Grade 5</option>
                        <option value="Grade 6">Grade 6</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Section</label>
                <select name="section" id="edit_section_select" onchange="toggleManualSectionEdit(this.value)" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    <option value="">Select Section</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['section']); ?>">
                            <?php echo htmlspecialchars($s['section']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="Other">Other (Type New...)</option>
                </select>
                <input type="text" name="manual_section" id="editManualSectionInput" placeholder="Enter new section name" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; margin-top: 0.5rem; display: none;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Parent's Milk Consent</label>
                    <select name="parent_milk_consent" id="edit_milk_consent" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">4Ps Beneficiary</label>
                    <select name="is_4ps" id="edit_is_4ps" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Dewormed?</label>
                    <select name="is_dewormed" id="edit_is_dewormed" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Participation Consent</label>
                    <select name="participation_consent" id="edit_participation_consent" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Dietary Restrictions / Allergies</label>
                <div style="display:flex; flex-wrap: wrap; gap: 0.5rem; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: white; max-height: 100px; overflow-y: auto;">
                    <?php foreach ($all_restrictions as $res): ?>
                        <label style="display:flex; align-items:center; gap: 0.25rem; font-size: 0.75rem; background: var(--bg-color); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid var(--border); cursor:pointer;">
                            <input type="checkbox" name="allergies[]" class="edit-allergy-cb" value="<?php echo $res['restriction_id']; ?>">
                            <?php echo htmlspecialchars($res['restriction_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Min Target Weight (kg)</label>
                    <input type="number" step="0.1" name="min_target_weight" id="edit_min_target" readonly style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; font-weight:700;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Max Target Weight (kg)</label>
                    <input type="number" step="0.1" name="max_target_weight" id="edit_max_target" readonly style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; font-weight:700;">
                </div>
            </div>

            <div class="modal-actions" style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem;">
                <button type="button" class="btn-m3 btn-m3-danger" onclick="deleteStudentAdmin()">
                    <span class="material-icons" style="font-size: 18px;">delete</span>
                    Delete Student
                </button>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="btn-m3 btn-m3-outline" onclick="document.getElementById('editStudentModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn-m3 btn-m3-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once '../../includes/footer.php'; ?>

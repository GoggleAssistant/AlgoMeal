<?php
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../db.php';
require_once '../../includes/bmi_helper.php';

$lrn = $_GET['lrn'] ?? '';

if (empty($lrn)) {
    echo "<div class='content'><h1>Student not found.</h1><a href='students.php'>Back to List</a></div>";
    exit;
}

// 1. Fetch Student Details
$sql = "
    SELECT s.*, 
    GROUP_CONCAT(dr.restriction_name SEPARATOR ', ') as allergens_list
    FROM student s
    LEFT JOIN student_allergy_map sam ON s.student_id = sam.student_id
    LEFT JOIN dietary_restrictions dr ON sam.restriction_id = dr.restriction_id
    WHERE s.student_id = ?
    GROUP BY s.student_id
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $lrn);
$stmt->execute();
$student = $stmt->get_result()->fetch_assoc();

if (!$student) {
    echo "<div class='content'><h1>Student LRN: $lrn not found.</h1><a href='students.php'>Back to List</a></div>";
    exit;
}

// 2. Fetch History
$hist_sql = "SELECT nr.*, u.faculty_name FROM nutritional_record nr LEFT JOIN users u ON nr.created_by = u.user_id WHERE nr.student_id = ? ORDER BY assessment_date ASC, nr.record_id ASC";
$hstmt = $conn->prepare($hist_sql);
$hstmt->bind_param("s", $lrn);
$hstmt->execute();
$history = $hstmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Calculation helpers
$current_record = !empty($history) ? $history[count($history)-1] : null;
$baseline_record = !empty($history) ? $history[0] : null;

$current_bmi = 0;
if ($current_record) {
    $current_bmi = round($current_record['weight'] / pow($current_record['height']/100, 2), 1);
}
$bmi_info = categorizeBMI($current_bmi);

// Fetch all restrictions for the edit modal
$all_res_q = "SELECT * FROM dietary_restrictions ORDER BY restriction_name ASC";
$all_res_r = $conn->query($all_res_q);
$available_restrictions = [];
while ($r = $all_res_r->fetch_assoc()) $available_restrictions[] = $r;

// Current allergy IDs for checkmarking
$my_allergies_q = "SELECT restriction_id FROM student_allergy_map WHERE student_id = ?";
$mastmt = $conn->prepare($my_allergies_q);
$mastmt->bind_param("s", $lrn);
$mastmt->execute();
$my_allergy_ids = $mastmt->get_result()->fetch_all(MYSQLI_ASSOC);
$my_allergy_array = array_column($my_allergy_ids, 'restriction_id');

$page_title = 'Student Profile';
require_once '../../includes/topbar.php';
?>

<div class="content">
    <div style="margin-bottom: 1.5rem;">
        <a href="students.php" style="text-decoration: none; color: var(--text-muted); font-size: 0.875rem; display: flex; align-items:center; gap:0.25rem;">
            <span class="material-icons" style="font-size:16px;">arrow_back</span> Back to Students
        </a>
    </div>

    <!-- PROFILE HEADER CARD -->
    <div class="section-card" style="display: flex; gap: 2.5rem; align-items: center; padding: 2.5rem; margin-bottom: 2rem; position: relative; overflow: hidden;">
        <!-- Backdrop accent -->
        <div style="position: absolute; top: -50px; right: -50px; width: 150px; height: 150px; background: var(--secondary); border-radius: 50%; opacity: 0.5;"></div>
        
        <div style="width: 120px; height: 120px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 3.5rem; font-weight: 800; box-shadow: 0 10px 20px rgba(0, 97, 255, 0.2); z-index: 1;">
            <?php echo strtoupper(substr($student['first_name'], 0, 1)); ?>
        </div>
        
        <div style="flex: 1; z-index: 1;">
            <div style="display:flex; align-items:center; gap: 1.25rem; flex-wrap: wrap;">
                <h1 style="margin: 0; font-size: 2.5rem; font-weight: 800; letter-spacing: -0.5px; color: var(--text-main);">
                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                </h1>
                <span style="font-size: 0.9rem; padding: 0.4rem 1.25rem; border-radius: 50px; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.05); <?php echo $bmi_info['style']; ?>">
                    <?php echo strtoupper($bmi_info['label']); ?>
                </span>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 2.5rem; margin-top: 2rem;">
                <div>
                    <div style="font-size: 0.70rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.25rem;">Student LRN</div>
                    <div style="font-weight: 700; font-family: 'Roboto Mono', monospace; font-size: 1.1rem; color: var(--primary);"><?php echo $student['student_id']; ?></div>
                </div>
                <div>
                    <div style="font-size: 0.70rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.25rem;">Grade & Section</div>
                    <div style="font-weight: 700; font-size: 1.1rem;"><?php echo $student['grade_level'] . ' • ' . $student['section']; ?></div>
                </div>
                <div>
                    <div style="font-size: 0.70rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.25rem;">Sex / Gender</div>
                    <div style="font-weight: 700; font-size: 1.1rem;"><?php echo $student['sex']; ?></div>
                </div>
                <div>
                    <div style="font-size: 0.70rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 0.25rem;">Birth Date</div>
                    <div style="font-weight: 700; font-size: 1.1rem;"><?php echo date('M d, Y', strtotime($student['birth_date'])); ?></div>
                </div>
            </div>
        </div>
        
        <div style="display: flex; flex-direction: column; gap: 0.75rem; z-index: 1;">
            <button class="btn" style="padding: 0.8rem 1.5rem; width: 100%;" onclick="openProfileEditModal()">
                <span class="material-icons" style="font-size: 18px; margin-right: 0.5rem;">edit</span> Edit Profile
            </button>
            <button class="btn" style="background: var(--surface); color: var(--text-main); border: 1px solid var(--border); padding: 0.8rem 1.5rem; width: 100%;" onclick="location.reload()">
                <span class="material-icons" style="font-size: 18px; margin-right: 0.5rem;">refresh</span> Update View
            </button>
        </div>
    </div>

    <!-- MAIN DASHBOARD CONTENT -->
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem;">
        
        <!-- LEFT COLUMN: ANALYTICS & HISTORY -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            
            <!-- PROGRESS CHART -->
            <div class="section-card" style="padding: 2rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
                    <div>
                        <h3 class="section-title" style="margin:0; font-size: 1.25rem; font-weight: 700;">Nutritional Progression</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Historical BMI tracking and target range analysis</p>
                    </div>
                    <div style="display:flex; gap: 1rem;">
                        <div style="display:flex; align-items:center; gap:0.5rem; font-size: 0.75rem; font-weight: 600;">
                            <span style="width: 12px; height: 12px; border-radius: 50%; background: #0061ff;"></span> Actual BMI
                        </div>
                        <div style="display:flex; align-items:center; gap:0.5rem; font-size: 0.75rem; font-weight: 600;">
                            <span style="width: 12px; height: 12px; border-radius: 50%; border: 2px dashed #059669; background: transparent;"></span> Target Range
                        </div>
                    </div>
                </div>
                <div style="height: 350px; width: 100%;">
                    <canvas id="progressionChart"></canvas>
                </div>
            </div>

            <!-- HISTORY TABLE -->
            <div class="section-card" style="padding: 2rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 2rem;">
                    <div>
                        <h3 class="section-title" style="margin:0; font-size: 1.25rem; font-weight: 700;">Assessment Records</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem;">Tabulated history of health checks</p>
                    </div>
                    <button class="btn" style="padding: 0.6rem 1.25rem;" onclick="openProfileAddAssessmentModal()">
                        <span class="material-icons" style="font-size:18px; margin-right: 0.5rem;">add</span> Add Record
                    </button>
                </div>
                <table style="width: 100%; border-spacing: 0;">
                    <thead>
                        <tr style="background: var(--bg-color);">
                            <th style="padding: 1rem; border-radius: 8px 0 0 8px;">Date</th>
                            <th style="padding: 1rem;">Height</th>
                            <th style="padding: 1rem;">Weight</th>
                            <th style="padding: 1rem;">BMI</th>
                            <th style="padding: 1rem; border-radius: 0 8px 8px 0; text-align: right;">Managed By</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($history)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding: 3rem; color: var(--text-muted);">No records found. Click "Add Record" to begin.</td>
                        </tr>
                        <?php else: ?>
                            <?php foreach (array_reverse($history) as $row): ?>
                            <?php 
                                $row_bmi = round($row['weight'] / pow($row['height']/100, 2), 1);
                                $row_cat = categorizeBMI($row_bmi);
                            ?>
                            <tr style="transition: background 0.2s;">
                                <td style="padding: 1.25rem 1rem; font-weight: 600; color: var(--text-main);"><?php echo date('M d, Y', strtotime($row['assessment_date'])); ?></td>
                                <td style="padding: 1.25rem 1rem; color: var(--text-muted);"><?php echo $row['height']; ?> <span style="font-size: 0.7rem;">cm</span></td>
                                <td style="padding: 1.25rem 1rem; color: var(--text-muted);"><?php echo $row['weight']; ?> <span style="font-size: 0.7rem;">kg</span></td>
                                <td style="padding: 1.25rem 1rem;">
                                    <div style="display:flex; align-items:center; gap: 0.75rem;">
                                        <span style="font-weight: 800; color: var(--primary); font-size: 1.1rem;"><?php echo $row_bmi; ?></span>
                                        <span style="font-size: 0.65rem; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 700; <?php echo $row_cat['style']; ?>"><?php echo strtoupper($row_cat['label']); ?></span>
                                    </div>
                                </td>
                                <td style="padding: 1.25rem 1rem; text-align: right; color: var(--text-muted); font-size: 0.8rem;">
                                    <?php echo htmlspecialchars($row['faculty_name'] ?: 'System'); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- RIGHT COLUMN: SNAPSHOTS & DIETARY -->
        <div style="display: flex; flex-direction: column; gap: 2rem;">
            
            <!-- HEALTH SNAPSHOT -->
            <div class="section-card" style="padding: 2rem;">
                <h3 class="section-title" style="margin-bottom: 2rem; font-size: 1.1rem; font-weight: 700;">Health Snapshot</h3>
                
                <div style="display: flex; flex-direction: column; gap: 2rem;">
                    <!-- Weight Comp -->
                    <div>
                        <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 1rem;">Weight Transformation</div>
                        <div style="display: flex; align-items: center; justify-content: space-between; background: var(--secondary); padding: 1.25rem; border-radius: 12px; position: relative;">
                            <div>
                                <div style="font-size: 0.65rem; color: var(--primary); font-weight: 800; text-transform: uppercase;">Baseline</div>
                                <div style="font-size: 1.5rem; font-weight: 800;"><?php echo $baseline_record['weight'] ?? '--'; ?> <span style="font-size: 0.8rem; font-weight: 500;">kg</span></div>
                            </div>
                            <div class="material-icons" style="color: var(--primary); opacity: 0.3; font-size: 32px;">trending_up</div>
                            <div style="text-align: right;">
                                <div style="font-size: 0.65rem; color: var(--success); font-weight: 800; text-transform: uppercase;">Current</div>
                                <div style="font-size: 1.5rem; font-weight: 800; color: var(--success);"><?php echo $current_record['weight'] ?? '--'; ?> <span style="font-size: 0.8rem; font-weight: 500;">kg</span></div>
                            </div>
                        </div>
                    </div>

                    <!-- Target Progress -->
                    <div>
                        <?php
                            $target_min = $student['min_target_weight'] ?: 1;
                            $target_max = $student['max_target_weight'] ?: 1;
                            $current_w = $current_record['weight'] ?? 0;
                            // Progress bar logic: 0% is baseline or low, 100% is hitting target_min
                            $prog_pct = ($current_w > 0) ? min(100, round(($current_w / $target_min) * 100)) : 0;
                        ?>
                        <div style="display:flex; justify-content: space-between; align-items: flex-end; margin-bottom: 0.75rem;">
                            <div style="font-size: 0.70rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Rehab Progress</div>
                            <div style="font-size: 0.85rem; font-weight: 800; color: var(--primary);"><?php echo $prog_pct; ?>%</div>
                        </div>
                        <div style="height: 12px; background: var(--bg-color); border-radius: 6px; overflow: hidden; border: 1px solid var(--border);">
                            <div style="height: 100%; width: <?php echo $prog_pct; ?>%; background: linear-gradient(90deg, var(--primary), #00c6ff); border-radius: 6px; transition: width 1s cubic-bezier(0.175, 0.885, 0.32, 1.275);"></div>
                        </div>
                        <div style="display:flex; justify-content: space-between; margin-top: 0.5rem; font-size: 0.7rem; color: var(--text-muted); font-weight: 600;">
                            <span>Current Status</span>
                            <span>Target: <?php echo $target_min; ?>-<?php echo $target_max; ?> kg</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- DIETARY PROFILE -->
            <div class="section-card" style="padding: 2rem;">
                <h3 class="section-title" style="margin-bottom: 2rem; font-size: 1.1rem; font-weight: 700;">Dietary Profile</h3>
                
                <div style="background: rgba(220, 38, 38, 0.03); border-left: 4px solid var(--error); padding: 1.25rem; border-radius: 4px 12px 12px 4px; margin-bottom: 2rem;">
                    <div style="font-size: 0.7rem; font-weight: 800; color: var(--error); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.5rem;">Allergies & Restrictions</div>
                    <div style="font-weight: 700; font-size: 1rem; color: var(--text-main);">
                        <?php echo $student['allergens_list'] ?: 'NONE REPORTED'; ?>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- ==========================================
     MODALS (DEDICATED COPIES FOR PROFILE PAGE)
     ========================================== -->

<!-- 1. Edit Student Modal -->
<div class="modal-overlay" id="profileEditStudentModal">
    <div class="modal" style="max-width: 600px; width: 95%;">
        <form id="profileEditStudentForm">
            <input type="hidden" name="original_lrn" value="<?php echo $lrn; ?>">
            <h2 class="modal-title">Edit Student Profile</h2>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">LRN</label>
                    <input type="text" name="student_id" value="<?php echo $student['student_id']; ?>" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Birth Date</label>
                    <input type="date" name="birth_date" value="<?php echo $student['birth_date']; ?>" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">First Name</label>
                    <input type="text" name="first_name" value="<?php echo htmlspecialchars($student['first_name']); ?>" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Last Name</label>
                    <input type="text" name="last_name" value="<?php echo htmlspecialchars($student['last_name']); ?>" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
                </div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Sex</label>
                    <select name="sex" style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
                        <option value="Male" <?php if($student['sex']=='Male') echo 'selected'; ?>>Male</option>
                        <option value="Female" <?php if($student['sex']=='Female') echo 'selected'; ?>>Female</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Grade Level</label>
                    <select name="grade_level" style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
                        <option value="Kinder" <?php if($student['grade_level']=='Kinder') echo 'selected'; ?>>Kinder</option>
                        <?php for($i=1;$i<=6;$i++) { $v="Grade $i"; $sel=($student['grade_level']==$v)?'selected':''; echo "<option value='$v' $sel>$v</option>"; } ?>
                    </select>
                </div>
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Section</label>
                <input type="text" name="section" value="<?php echo htmlspecialchars($student['section']); ?>" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Allergies / Restrictions</label>
                <div style="display:flex; flex-wrap:wrap; gap:0.5rem; padding:0.5rem; border:1px solid var(--border); border-radius:6px; background:#fff; max-height: 100px; overflow-y:auto;">
                    <?php foreach ($available_restrictions as $res): ?>
                        <label style="font-size:0.75rem; display:flex; align-items:center; gap:0.25rem;">
                            <input type="checkbox" name="allergies[]" value="<?php echo $res['restriction_id']; ?>" <?php echo in_array($res['restriction_id'], $my_allergy_array) ? 'checked' : ''; ?>>
                            <?php echo $res['restriction_name']; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div style="display:flex; gap:1.5rem; margin-bottom: 1.5rem;">
                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem;"><input type="checkbox" name="is_4ps_beneficiary" value="1" <?php echo $student['is_4ps_beneficiary'] ? 'checked' : ''; ?>> 4Ps Beneficiary</label>
                <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.85rem;"><input type="checkbox" name="deworming_status" value="1" <?php echo $student['deworming_status'] ? 'checked' : ''; ?>> Dewormed</label>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeProfileModal('profileEditStudentModal')">Cancel</button>
                <button type="submit" class="btn">Apply Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- 2. Add Assessment Modal -->
<div class="modal-overlay" id="profileAddAssessmentModal">
    <div class="modal" style="max-width: 400px; width: 95%;">
        <form id="profileAddAssessmentForm">
            <input type="hidden" name="student_id" value="<?php echo $lrn; ?>">
            <h2 class="modal-title">New Record</h2>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Height (cm)</label>
                <input type="number" step="0.1" name="height" id="prof_h" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Weight (kg)</label>
                <input type="number" step="0.1" name="weight" id="prof_w" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; font-size: 0.8rem; font-weight: 600; margin-bottom: 0.4rem;">Assessment Date</label>
                <input type="date" name="assessment_date" value="<?php echo date('Y-m-d'); ?>" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeProfileModal('profileAddAssessmentModal')">Cancel</button>
                <button type="submit" class="btn">Save Assessment</button>
            </div>
        </form>
    </div>
</div>

<script>
    // 1. Chart Data Preparation
    const labels = [<?php echo implode(',', array_map(function($r){ return "'".date('M d', strtotime($r['assessment_date']))."'"; }, $history)); ?>];
    const bmiData = [<?php echo implode(',', array_map(function($r){ return round($r['weight'] / pow($r['height']/100, 2), 1); }, $history)); ?>];
    const heights = [<?php echo implode(',', array_map(function($r){ return $r['height']; }, $history)); ?>];
    const weights = [<?php echo implode(',', array_map(function($r){ return $r['weight']; }, $history)); ?>];
    const minLine = labels.map(() => 18.5);
    const maxLine = labels.map(() => 24.9);

    const ctx = document.getElementById('progressionChart').getContext('2d');
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Actual BMI',
                    data: bmiData,
                    heights: heights,
                    weights: weights,
                    borderColor: '#0061ff',
                    backgroundColor: 'rgba(0, 97, 255, 0.1)',
                    borderWidth: 4,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#fff',
                    pointBorderColor: '#0061ff',
                    pointBorderWidth: 3,
                    pointRadius: 5,
                    pointHoverRadius: 8
                },
                {
                    label: 'Healthy Range (Min)',
                    data: minLine,
                    borderColor: '#059669',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: false,
                    pointRadius: 0
                },
                {
                    label: 'Healthy Range (Max)',
                    data: maxLine,
                    borderColor: '#059669',
                    borderWidth: 2,
                    borderDash: [5, 5],
                    fill: '-1',
                    backgroundColor: 'rgba(5, 150, 105, 0.05)',
                    pointRadius: 0
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label === 'Actual BMI') {
                                const h = context.dataset.heights[context.dataIndex];
                                const w = context.dataset.weights[context.dataIndex];
                                return [
                                    `BMI: ${context.parsed.y}`,
                                    `Height: ${h} cm`,
                                    `Weight: ${w} kg`
                                ];
                            }
                            return `${label}: ${context.parsed.y}`;
                        }
                    }
                }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { weight: 600 } } },
                y: { 
                    grid: { borderDash: [5, 5], color: '#e3e8ee' },
                    suggestedMin: 12,
                    suggestedMax: 28,
                    ticks: { font: { weight: 600 } }
                }
            }
        }
    });

    // 2. Modal Controls
    function openProfileEditModal() { document.getElementById('profileEditStudentModal').classList.add('active'); }
    function openProfileAddAssessmentModal() { document.getElementById('profileAddAssessmentModal').classList.add('active'); }
    function closeProfileModal(id) { document.getElementById(id).classList.remove('active'); }

    // 3. Form Submissions (Isolated AJAX)
    document.getElementById('profileEditStudentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api_edit_student.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json()).then(data => {
            if (data.success) location.reload();
            else alert(data.error);
        });
    });

    document.getElementById('profileAddAssessmentForm').addEventListener('submit', function(e) {
        e.preventDefault();
        fetch('api_add_assessment.php', { method: 'POST', body: new FormData(this) })
        .then(r => r.json()).then(data => {
            if (data.success) location.reload();
            else alert(data.error);
        });
    });
</script>

<?php require_once '../../includes/footer.php'; ?>

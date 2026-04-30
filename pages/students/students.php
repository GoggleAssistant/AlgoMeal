<?php
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../db.php'; // Include the database connection

$page_title = 'Students';
require_once '../../includes/topbar.php';
?>
<script>
    function updateAgeCalculation() {
        const activeOverlay = document.querySelector('.modal-overlay.active');
        if (!activeOverlay) return;
        const activeModal = activeOverlay.querySelector('.modal');
        if (!activeModal) return;

        const bIn = activeModal.querySelector('input[name="birth_date"]');
        const aIn = activeModal.querySelector('input[name="assessment_date"]');
        if (!bIn || !bIn.value) return;

        const dob = new Date(bIn.value);
        const ref = (aIn && aIn.value) ? new Date(aIn.value) : new Date();

        if (isNaN(dob.getTime())) return;

        let years = ref.getFullYear() - dob.getFullYear();
        let months = ref.getMonth() - dob.getMonth();
        if (months < 0 || (months === 0 && ref.getDate() < dob.getDate())) {
            years--;
            months += 12;
        }

        const ageDisplay = activeModal.querySelector('.calculated_age_display');
        const hiddenY = activeModal.querySelector('.hidden_age_y');
        const hiddenM = activeModal.querySelector('.hidden_age_m');

        if (ageDisplay) ageDisplay.value = years + "Y / " + months + "M";
        if (hiddenY) hiddenY.value = years;
        if (hiddenM) hiddenM.value = months;
    }

    document.addEventListener('change', function (e) {
        if (e.target.name === 'birth_date' || e.target.name === 'assessment_date') {
            updateAgeCalculation();
        }
    });
</script>
<?php

// Fetch distinct sections and their student counts
$section_query = "SELECT grade_level, section, COUNT(student_id) as enrolled FROM student GROUP BY grade_level, section ORDER BY grade_level, section";
$section_result = $conn->query($section_query);
$sections = [];
while ($row = $section_result->fetch_assoc()) {
    $sections[] = $row;
}

// Determine active filters
$active_section = $_GET['section'] ?? 'All';
$active_grade = $_GET['grade'] ?? 'All';

// Total counts for the "All" view
$total_students_enrolled = array_sum(array_column($sections, 'enrolled'));

// Fetch students (For a specific section or ALL)
$students = [];
if (true) {
    $where_clauses = [];
    $params = [];
    $types = "";

    if ($active_section !== 'All') {
        $where_clauses[] = "s.section = ?";
        $params[] = $active_section;
        $types .= "s";
    }

    if ($active_grade !== 'All') {
        $where_clauses[] = "s.grade_level = ?";
        $params[] = $active_grade;
        $types .= "s";
    }

    $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

    $sql = "
        SELECT 
            s.student_id, 
            s.first_name, 
            s.last_name, 
            s.sex,
            s.birth_date,
            s.grade_level,
            s.section,
            s.is_4ps_beneficiary,
            s.deworming_status,
            s.parent_milk_consent,
            s.participation_consent,
            s.min_target_weight,
            s.max_target_weight,
            s.is_enrolled,
            (SELECT height FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_height,
            (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_weight,
            (SELECT nutritional_status FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as latest_status,
            (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date ASC, record_id ASC LIMIT 1) as baseline_weight,
            (SELECT COUNT(*) FROM nutritional_record WHERE student_id = s.student_id) as record_count,
            GROUP_CONCAT(dr.restriction_name SEPARATOR ', ') as allergens,
            GROUP_CONCAT(sam.restriction_id SEPARATOR ',') as allergen_ids
        FROM student s
        LEFT JOIN student_allergy_map sam ON s.student_id = sam.student_id
        LEFT JOIN dietary_restrictions dr ON sam.restriction_id = dr.restriction_id
        $where_sql
        GROUP BY s.student_id
    ";

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $students[] = $row;
    }

    // Fetch all historical records for charting
    $history_data = [];
    if (!empty($students)) {
        $student_ids = array_map(function ($s) use ($conn) {
            return "'" . $conn->real_escape_string($s['student_id']) . "'";
        }, $students);
        $ids_str = implode(',', $student_ids);
        $hist_query = "SELECT nr.record_id, nr.student_id, nr.height, nr.weight, nr.assessment_date, u.faculty_name as created_by_name FROM nutritional_record nr LEFT JOIN users u ON nr.created_by = u.user_id WHERE nr.student_id IN ($ids_str) ORDER BY nr.assessment_date ASC, nr.record_id ASC";
        $hist_res = $conn->query($hist_query);
        while ($r = $hist_res->fetch_assoc()) {
            if (!isset($history_data[$r['student_id']])) {
                $history_data[$r['student_id']] = [];
            }
            // Math for each point so chart renders true bmi
            if ($r['height'] > 0) {
                $calc_bmi = $r['weight'] / pow($r['height'] / 100, 2);
                $r['computed_bmi'] = round($calc_bmi, 1);
            } else {
                $r['computed_bmi'] = 0;
            }
            $history_data[$r['student_id']][] = [
                'record_id' => $r['record_id'],
                'date' => date('M Y', strtotime($r['assessment_date'])),
                'accurate_date' => $r['assessment_date'],
                'height' => $r['height'],
                'weight' => $r['weight'],
                'bmi' => $r['computed_bmi'],
                'author' => $r['created_by_name'] ?? 'System'
            ];
        }
    }
}

// Fetch all dietary restrictions
$restrictions_query = "SELECT restriction_id, restriction_name FROM dietary_restrictions ORDER BY restriction_name ASC";
$restrictions_result = $conn->query($restrictions_query);
$all_restrictions = [];
while ($row = $restrictions_result->fetch_assoc()) {
    $all_restrictions[] = $row;
}

require_once '../../includes/bmi_helper.php';

// Fetch all dietary restrictions
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 2rem;
    }

    .page-header h2 {
        font-size: 1.5rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
    }

    .page-header p {
        color: var(--text-muted);
        font-size: 0.875rem;
    }

    .header-actions {
        display: flex;
        gap: 0.75rem;
    }

    /* Section Tabs */
    .filter-label {
        font-size: 0.75rem;
        text-transform: uppercase;
        color: var(--text-muted);
        font-weight: 500;
        margin-top: 2rem;
        margin-bottom: 1rem;
        display: flex;
        justify-content: space-between;
    }

    .section-cards {
        display: flex;
        gap: 1rem;
        margin-bottom: 2rem;
        overflow-x: auto;
        padding-bottom: 0.5rem;
    }

    .section-tab {
        min-width: 240px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 1.25rem;
        cursor: pointer;
        text-decoration: none;
        color: var(--text-main);
        transition: all 0.2s ease;
        position: relative;
    }

    .section-tab:hover {
        border-color: #bbdefb;
    }

    .section-tab.active {
        border: 2px solid var(--primary);
        background-color: var(--secondary);
    }

    .tab-title {
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 0.25rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .tab-subtitle {
        font-size: 0.75rem;
        color: var(--text-muted);
        margin-bottom: 1rem;
    }

    .progress-wrapper {
        margin-top: 0.5rem;
    }

    .progress-text {
        font-size: 0.75rem;
        color: var(--text-muted);
        display: flex;
        justify-content: space-between;
        margin-bottom: 0.35rem;
    }

    .progress-bar {
        height: 4px;
        background-color: var(--border);
        border-radius: 2px;
        overflow: hidden;
    }

    .progress-fill {
        height: 100%;
        background-color: var(--success);
        width: 60%;
        /* Mock value for now */
    }

    /* Grade Filters */
    .grade-filter-container {
        display: flex;
        gap: 0.5rem;
        margin-bottom: 1.5rem;
        flex-wrap: wrap;
    }

    .grade-filter-btn {
        padding: 0.5rem 1.25rem;
        border-radius: 20px;
        background: white;
        border: 1px solid var(--border);
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-muted);
        cursor: pointer;
        transition: all 0.2s;
    }

    .grade-filter-btn:hover {
        border-color: var(--primary);
        color: var(--primary);
    }

    .grade-filter-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
    }

    /* Table Container */
    .data-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 8px;
        overflow: hidden;
    }

    .data-header {
        padding: 1.25rem 1.5rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        border-bottom: 1px solid var(--border);
    }

    .data-title {
        font-weight: 700;
        font-size: 1.125rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .data-count {
        font-size: 0.75rem;
        background: var(--bg-color);
        padding: 0.2rem 0.5rem;
        border-radius: 12px;
        color: var(--text-muted);
        font-weight: 500;
    }

    .data-filters {
        font-size: 0.875rem;
        color: var(--text-muted);
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .clear-filters {
        color: var(--primary);
        text-decoration: none;
        font-weight: 500;
    }

    /* Custom Table Styling */
    .table-container {
        width: 100%;
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 1rem 1.5rem;
        font-weight: 500;
        font-size: 0.75rem;
        color: var(--text-muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    td {
        padding: 1.25rem 1.5rem;
        border-top: 1px solid var(--border);
        vertical-align: top;
        height: 100px;
        /* Enforce uniform row size */
    }

    /* Student Cell */
    .student-cell {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .student-info {
        display: flex;
        flex-direction: column;
        gap: 0.15rem;
    }

    .student-name {
        font-weight: 500;
        color: var(--text-main);
    }

    .student-restriction {
        font-size: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.25rem;
    }

    .restriction-alert {
        color: #d93025;
        /* Red */
    }

    .restriction-none {
        color: var(--text-muted);
    }

    /* Stats Cells */
    .bmi-value {
        font-weight: 500;
        color: var(--primary);
        background-color: var(--secondary);
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        font-size: 0.875rem;
        display: inline-block;
    }

    .weight-gain {
        font-weight: 500;
        font-size: 0.875rem;
    }

    .gain-up {
        color: var(--text-main);
    }

    .gain-down {
        color: #d93025;
    }

    .gain-stable {
        color: var(--text-muted);
    }

    .meal-badge {
        font-size: 0.75rem;
        font-weight: 500;
        padding: 0.25rem 0.75rem;
        border-radius: 12px;
        background-color: #e0f2f1;
        /* Teal light */
        color: #00796b;
        /* Teal */
        display: inline-block;
    }

    .btn-icon {
        width: 32px;
        height: 32px;
        background: transparent;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }

    .btn-icon:hover {
        background-color: var(--secondary);
        color: var(--primary);
        transform: scale(1.1);
    }

    .btn-text {
        background: transparent;
        border: 1px solid var(--border);
        color: var(--text-main);
        cursor: pointer;
        padding: 0.5rem 1rem;
        border-radius: 8px;
        font-weight: 700;
        font-size: 0.75rem;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .btn-text:hover {
        background: var(--bg-color);
        border-color: var(--primary);
        color: var(--primary);
    }

    /* Stacked Modal Management */
    #chartModal {
        z-index: 1000;
    }

    #addAssessmentModal,
    #editAssessmentModal,
    #viewAssessmentModal,
    #editStudentModal {
        z-index: 1100;
    }
</style>

<div class="content">
    <div class="page-header">
        <div>
            <h2>Students</h2>
            <p>Manage and monitor student nutritional progress per section.</p>
        </div>
        <div class="header-actions">
            <a href="export_students.php?section=<?php echo urlencode($active_section); ?>"
                class="btn-m3 btn-m3-outline"><span class="material-icons" style="font-size: 16px;">file_download</span>
                Export List</a>
            <?php if ($role === 'Admin' || $role === 'Super Admin'): ?>
                <button class="btn-m3 btn-m3-tonal"
                    onclick="document.getElementById('importStudentModal').classList.add('active')">
                    <span class="material-icons" style="font-size: 16px;">upload_file</span> Import CSV
                </button>
                <button class="btn-m3 btn-m3-primary"
                    onclick="document.getElementById('addStudentModal').classList.add('active')">
                    <span class="material-icons" style="font-size: 16px;">add</span> Add Student
                </button>
            <?php endif; ?>
        </div>

    </div>

    <div class="filter-label">
        <span><span class="material-icons"
                style="font-size: 14px; vertical-align: middle; margin-right: 4px;">filter_alt</span> SELECT
            GRADE</span>
    </div>

    <div class="grade-filter-container">
        <button class="grade-filter-btn <?php echo ($active_grade === 'All') ? 'active' : ''; ?>"
            onclick="location.href='?grade=All&section=All'">All Grades</button>
        <?php
        $unique_grades = array_unique(array_column($sections, 'grade_level'));
        sort($unique_grades);
        foreach ($unique_grades as $g):
            ?>
            <button class="grade-filter-btn <?php echo ($active_grade === $g) ? 'active' : ''; ?>"
                onclick="location.href='?grade=<?php echo urlencode($g); ?>&section=All'"><?php echo htmlspecialchars($g); ?></button>
        <?php endforeach; ?>
    </div>

    <div class="filter-label" style="margin-top: 2rem;">
        <span><span class="material-icons"
                style="font-size: 14px; vertical-align: middle; margin-right: 4px;">groups</span> SELECT
            SECTION</span>
        <span>Showing
            <?php echo ($active_grade === 'All') ? count($sections) : count(array_filter($sections, fn($s) => $s['grade_level'] === $active_grade)); ?>
            Sections</span>
    </div>

    <div class="section-cards" id="sectionCardsContainer">
        <!-- Global/Grade "All Students" Card -->
        <?php
        $all_card_count = ($active_grade === 'All') ? $total_students_enrolled : array_sum(array_map(fn($s) => ($s['grade_level'] === $active_grade) ? $s['enrolled'] : 0, $sections));
        $all_card_label = ($active_grade === 'All') ? "Global School Overview" : "All " . $active_grade . " Students";
        ?>
        <a href="?section=All&grade=<?php echo urlencode($active_grade); ?>"
            class="section-tab <?php echo ($active_section === 'All') ? 'active' : ''; ?>">
            <div class="tab-title">
                <span style="<?php echo ($active_section === 'All') ? 'color: var(--primary);' : ''; ?>">All
                    Students</span>
                <?php if ($active_section === 'All'): ?>
                    <span class="material-icons" style="color: var(--primary); font-size: 18px;">check_circle</span>
                <?php endif; ?>
            </div>
            <div class="tab-subtitle"><?php echo $all_card_label; ?></div>
            <div style="margin-top: 1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Enrollment</span>
                    <span
                        style="font-size: 0.875rem; font-weight: 700; color: var(--primary);"><?php echo $all_card_count; ?></span>
                </div>
            </div>
        </a>

        <?php foreach ($sections as $s): ?>
            <?php
            // Only show sections for the active grade, unless All is selected
            if ($active_grade !== 'All' && $s['grade_level'] !== $active_grade)
                continue;

            $isActive = ($s['section'] == $active_section && $s['grade_level'] == $active_grade);
            $enrolled = $s['enrolled'];
            ?>
            <a href="?section=<?php echo urlencode($s['section']); ?>&grade=<?php echo urlencode($s['grade_level']); ?>"
                class="section-tab <?php echo $isActive ? 'active' : ''; ?>">
                <div class="tab-title">
                    <span
                        style="<?php echo $isActive ? 'color: var(--primary);' : ''; ?>"><?php echo htmlspecialchars($s['grade_level'] . ' - ' . $s['section']); ?></span>
                    <?php if ($isActive): ?>
                        <span class="material-icons" style="color: var(--primary); font-size: 16px;">check_circle</span>
                    <?php endif; ?>
                </div>
                <div class="tab-subtitle"><?php echo $s['enrolled']; ?> Students Enrolled</div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="data-card">
        <div class="data-header">
            <div class="data-title">
                <span class="material-icons" style="color: var(--primary);">groups</span>
                <?php echo $active_section === 'All' ? 'School-Wide Student Roster' : 'Students in ' . htmlspecialchars($active_section); ?>
                <span class="data-count"><?php echo count($students); ?> Total</span>
            </div>
            <div class="data-filters" style="display: flex; gap: 0.5rem; align-items: center; width: 65%;">
                <input type="text" id="searchInput" oninput="filterTable()" placeholder="Search students, ID Number..."
                    style="padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; flex-grow: 1;">

                <select id="bmiFilter" onchange="filterTable()"
                    style="padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    <option value="">All BMI Status</option>
                    <option value="Normal">Normal</option>
                    <option value="Wasted">Wasted</option>
                    <option value="Severely Wasted">Severely Wasted</option>
                    <option value="Overweight">Overweight</option>
                    <option value="Obese">Obese</option>
                    <option value="Unknown">No Data</option>
                </select>

                <select id="enrollFilter" onchange="filterTable()"
                    style="padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    <option value="1">Enrolled Only</option>
                    <option value="0">Unenrolled Only</option>
                    <option value="">All Students</option>
                </select>
                <a href="#" class="clear-filters" onclick="clearFilters(); return false;">Clear</a>
            </div>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Student Name</th>
                        <th>Grade & Section</th>
                        <th>Dietary Restrictions</th>
                        <th>BMI & Target Weight</th>
                        <th>Weight Gain</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No students
                                found in this section.</td>
                        </tr>
                    <?php else: ?>
                        <?php
                        // Palette for avatars to look random and colorful as in the mockup
                        $colors = ['#4285F4', '#0F9D58', '#DB4437', '#F4B400', '#AB47BC', '#00ACC1'];
                        ?>
                        <?php foreach ($students as $index => $st): ?>
                            <?php
                            // Calculate proper BMI: weight (kg) / height (m)^2
                            $calculated_bmi = null;
                            if ($st['current_height'] > 0 && $st['current_weight'] > 0) {
                                $height_m = $st['current_height'] / 100;
                                $calculated_bmi = $st['current_weight'] / ($height_m * $height_m);
                            }

                            // Calculate weight difference
                            if ($st['record_count'] == 0) {
                                $diffStr = 'No Data';
                                $diffClass = 'gain-stable';
                            } else if ($st['record_count'] == 1) {
                                $diffStr = '-- (Baseline Only)';
                                $diffClass = 'gain-stable';
                            } else {
                                $diff = $st['current_weight'] - $st['baseline_weight'];
                                $diffStr = '- Stable';
                                $diffClass = 'gain-stable';
                                if ($diff > 0) {
                                    $diffStr = '↗ +' . number_format($diff, 1) . ' kg';
                                    $diffClass = 'gain-up';
                                } else if ($diff < 0) {
                                    $diffStr = '↘ ' . number_format($diff, 1) . ' kg';
                                    $diffClass = 'gain-down';
                                }
                            }

                            // Format restrictions and charts
                            // Always re-categorize to match the current target standards (18.5 - 25.0)
                            $bmiData = categorizeBMI($calculated_bmi);
                            $chartJSON = htmlspecialchars(json_encode($history_data[$st['student_id']] ?? []));
                            $allergens = $st['allergens'] ? $st['allergens'] : 'No Restrictions';
                            $isAllergic = $st['allergens'] != '';

                            $color = $colors[$index % count($colors)];
                            ?>
                            <tr class="student-row" data-lrn="<?php echo htmlspecialchars($st['student_id']); ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($st['first_name'] . ' ' . $st['last_name'])); ?>"
                                data-allergies="<?php echo htmlspecialchars(strtolower($allergens)); ?>"
                                data-bmi="<?php echo htmlspecialchars($bmiData['label']); ?>"
                                data-enrolled="<?php echo $st['is_enrolled']; ?>">
                                <td>
                                    <span
                                        style="font-size: 0.875rem; color: var(--text-muted); font-weight: 500; font-family: monospace;">
                                        <?php echo htmlspecialchars($st['student_id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="student-cell" style="gap: 0;">
                                        <div class="student-info">
                                            <span style="color: var(--text-main); font-weight: 800; display: inline-block;">
                                                <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>
                                            </span>
                                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem;">
                                                <?php echo $st['current_height'] ?: 'No Data'; ?> cm •
                                                <?php echo $st['current_weight'] ?: 'No Data'; ?> kg
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                                        <?php echo htmlspecialchars($st['grade_level'] ?: 'Unassigned'); ?>
                                    </div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">
                                        <?php echo htmlspecialchars($st['section'] ?: '--'); ?>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        style="font-size: 0.875rem; <?php echo $isAllergic ? 'color: #d93025; font-weight: 500;' : 'color: var(--text-muted);'; ?>">
                                        <?php echo htmlspecialchars($allergens); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="bmi-value"><small
                                            style="font-weight:700; color:var(--text-muted); font-size: 0.65rem; text-transform:uppercase;">BMI:
                                        </small><?php echo $calculated_bmi ? number_format($calculated_bmi, 1) : 'No Data'; ?></span>
                                    <?php if ($calculated_bmi): ?>
                                        <span class="<?php echo $bmiData['class']; ?>"
                                            style="font-size:0.65rem; padding: 0.15rem 0.5rem; margin-top:0.35rem; display:block; text-align:center; width: max-content; <?php echo $bmiData['style']; ?>"><?php echo $bmiData['label']; ?></span>
                                        <div style="margin-top: 0.5rem;">
                                            <span style="font-weight: 500;">Target Range:</span><br>
                                            <span style="color: var(--primary); font-weight: 600;">
                                                <?php echo number_format($st['min_target_weight'], 1); ?> -
                                                <?php echo number_format($st['max_target_weight'], 1); ?> kg
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="weight-gain <?php echo $diffClass; ?>"><?php echo $diffStr; ?></span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <a href="student_profile.php?id=<?php echo urlencode($st['student_id']); ?>"
                                            class="btn-m3 btn-m3-tonal"
                                            style="padding: 6px 12px; font-size: 0.75rem; border-radius: 12px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-icons" style="font-size:14px;">visibility</span> View
                                        </a>
                                        <button class="btn-m3 btn-m3-primary add-assessment-btn"
                                            data-student_id="<?php echo htmlspecialchars($st['student_id']); ?>"
                                            data-height="<?php echo $st['current_height'] ?? ''; ?>"
                                            data-weight="<?php echo $st['current_weight'] ?? ''; ?>"
                                            data-min-target="<?php echo $st['min_target_weight']; ?>"
                                            data-max-target="<?php echo $st['max_target_weight']; ?>"
                                            data-sex="<?php echo $st['sex']; ?>" data-birth="<?php echo $st['birth_date']; ?>"
                                            style="padding: 8px 16px; font-size: 0.75rem; border-radius: 50px; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 8px rgba(0, 97, 255, 0.2);">
                                            <span class="material-icons" style="font-size:16px;">add_circle</span> Assess
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>



<!-- Legacy Modal Removed -->

<!-- Add Student Modal -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal" style="max-width: 500px; width: 95%; max-height: 90vh; overflow-y: auto;">
        <h2 class="modal-title">Register New Student</h2>
        <form id="addStudentForm">
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">LRN
                        (Student ID)</label>
                    <input type="text" name="student_id" required
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Birth
                        Date</label>
                    <input type="date" name="birth_date" id="birth_date_enroll" required
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">First
                        Name</label>
                    <input type="text" name="first_name" required
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Last
                        Name</label>
                    <input type="text" name="last_name" required
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Sex</label>
                    <select name="sex" id="sex_enroll"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Grade
                        Level</label>
                    <select name="grade_level"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
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
                <label
                    style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Section</label>
                <select name="section" id="sectionSelect" onchange="toggleManualSection(this.value)"
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    <option value="">Select Section</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['section']); ?>">
                            <?php echo htmlspecialchars($s['section']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="Other">Other (Type New...)</option>
                </select>
                <input type="text" name="manual_section" id="manualSectionInput" placeholder="Enter new section name"
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; margin-top: 0.5rem; display: none;">
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Dietary
                    Restrictions / Allergies</label>
                <div
                    style="display:flex; flex-wrap: wrap; gap: 0.5rem; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: white; max-height: 100px; overflow-y: auto;">
                    <?php foreach ($all_restrictions as $res): ?>
                        <label
                            style="display:flex; align-items:center; gap: 0.25rem; font-size: 0.75rem; background: var(--bg-color); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid var(--border); cursor:pointer;">
                            <input type="checkbox" name="allergies[]" value="<?php echo $res['restriction_id']; ?>">
                            <?php echo htmlspecialchars($res['restriction_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top:0.5rem; display:flex; gap:0.5rem;">
                    <input type="text" id="customRestrictionInput" placeholder="Add custom allergy..."
                        style="flex:1; padding:0.4rem; border:1px solid var(--border); border-radius:6px; font-size:0.75rem;">
                    <button type="button" class="btn-m3 btn-m3-outline"
                        onclick="addCustomRestriction('addStudentForm', 'customRestrictionInput')"
                        style="padding:4px 12px; font-size:0.7rem;">+ Add</button>
                </div>
                <p style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem;">Select all that apply or
                    add a new one.</p>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Parent's
                        Milk Consent</label>
                    <select name="parent_milk_consent"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">4Ps
                        Beneficiary</label>
                    <select name="is_4ps"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Dewormed?</label>
                    <select name="is_dewormed"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Participation
                        Consent</label>
                    <select name="participation_consent"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
            </div>

            <div
                style="background: #f8fafc; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; border: 1px dashed var(--border);">
                <h4 style="margin: 0 0 1rem 0; font-size: 0.85rem; font-weight: 800; color: var(--primary);">Initial
                    Assessment Data</h4>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label
                            style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Assessment
                            Date</label>
                        <input type="date" name="assessment_date"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;"
                            value="<?= date('Y-m-d') ?>">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Age
                            (calculated)</label>
                        <input type="text" readonly placeholder="Y / M" class="calculated_age_display"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background:#e2e8f0; font-weight:bold;">
                        <input type="hidden" name="age_years" class="hidden_age_y">
                        <input type="hidden" name="age_months" class="hidden_age_m">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label
                            style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Weight
                            (kg)</label>
                        <input type="number" step="0.01" name="init_weight"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    </div>
                    <div>
                        <label
                            style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Height
                            (cm)</label>
                        <input type="number" step="0.1" name="init_height"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div>
                        <label
                            style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Nutritional
                            Status (BMI-A)</label>
                        <input type="text" id="ns_status_enroll" name="ns_status" readonly
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; font-weight: 800;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">HFA
                            Status</label>
                        <input type="text" id="hfa_status_enroll" name="hfa_status" readonly
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; font-weight: 800;">
                    </div>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 1rem;">
                    <div>
                        <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Min
                            Target (kg)</label>
                        <input type="number" step="0.1" name="min_target_weight" id="min_target_weight_enroll" readonly
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; color: var(--text-muted); font-weight: 700;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Max
                            Target (kg)</label>
                        <input type="number" step="0.1" name="max_target_weight" id="max_target_weight_enroll" readonly
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; color: var(--text-muted); font-weight: 700;">
                    </div>
                </div>
            </div>

            <!-- SBFP Indicators Removed -->

            <div class="modal-actions">
                <button type="button" class="btn-cancel"
                    onclick="document.getElementById('addStudentModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn">Register Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Assessment Modal -->
<!-- Add Assessment Modal -->
<div class="modal-overlay" id="addAssessmentModal">
    <div class="modal"
        style="max-width: 500px; width: 95%; border-radius: 28px; padding: 2.5rem; border: none; box-shadow: 0 20px 40px rgba(0,0,0,0.1);">
        <h2 class="modal-title"
            style="font-weight: 900; font-size: 1.85rem; margin-bottom: 1.5rem; color: #1e293b; letter-spacing: -0.02em;">
            New Nutritional Assessment</h2>
        <form id="assessmentForm">
            <input type="hidden" id="assessLrn" name="student_id">
            <input type="hidden" id="assess_birth_date" name="birth_date_hidden">
            <input type="hidden" id="assess_sex" name="sex_hidden">

            <div style="margin-bottom: 1.5rem;">
                <label
                    style="display:block; font-size: 0.75rem; font-weight: 800; color: #64748b; margin-bottom: 0.5rem; text-transform: none;">Assessment
                    Date</label>
                <div style="position: relative;">
                    <input type="date" name="assessment_date" value="<?php echo date('Y-m-d'); ?>" required
                        style="width: 100%; padding: 0.85rem 1.25rem; border: 1.5px solid #e2e8f0; border-radius: 16px; font-weight: 600; color: #334155; background: #fff;">
                </div>
            </div>

            <div id="assess_live_summary"
                style="display:grid; grid-template-columns: 1fr 1.2fr 1fr; gap: 0; margin-bottom: 2rem; background: #f0f9ff; padding: 1.25rem; border-radius: 20px; border: 1px solid #bae6fd;">
                <div style="text-align: center;">
                    <div
                        style="font-size: 0.7rem; color: #0369a1; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                        BMI</div>
                    <div id="assess_live_bmi" style="font-size: 1.4rem; font-weight: 900; color: #0c4a6e;">--</div>
                </div>
                <div style="text-align: center; border-left: 1px solid #bae6fd; border-right: 1px solid #bae6fd;">
                    <div
                        style="font-size: 0.7rem; color: #0369a1; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                        STATUS</div>
                    <div id="assess_live_status" style="font-size: 0.85rem; font-weight: 900; color: #0c4a6e;">--</div>
                </div>
                <div style="text-align: center;">
                    <div
                        style="font-size: 0.7rem; color: #0369a1; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 4px;">
                        HFA</div>
                    <div id="assess_live_hfa" style="font-size: 0.85rem; font-weight: 900; color: #0c4a6e;">--</div>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Height
                        (cm)</label>
                    <input type="number" step="0.1" name="height" id="assess_height" required
                        style="width: 100%; padding: 0.85rem 1.25rem; border: 1.5px solid #e2e8f0; border-radius: 16px; font-weight: 700; color: #1e293b;">
                </div>
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Weight
                        (kg)</label>
                    <input type="number" step="0.1" name="weight" id="assess_weight" required
                        style="width: 100%; padding: 0.85rem 1.25rem; border: 1.5px solid #e2e8f0; border-radius: 16px; font-weight: 700; color: #1e293b;">
                </div>
            </div>

            <div style="display:none;">
                <input type="text" id="assess_ns_status" name="ns_status">
                <input type="text" id="assess_hfa_status" name="hfa_status">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 2rem;">
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Min
                        Target (kg)</label>
                    <input type="text" name="min_target_weight" id="assess_min_target_weight" readonly
                        style="width: 100%; padding: 0.85rem 1.25rem; border: 1.5px solid #f1f5f9; border-radius: 16px; background: #f8fafc; font-weight: 800; color: #1e293b;">
                </div>
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Max
                        Target (kg)</label>
                    <input type="text" name="max_target_weight" id="assess_max_target_weight" readonly
                        style="width: 100%; padding: 0.85rem 1.25rem; border: 1.5px solid #f1f5f9; border-radius: 16px; background: #f8fafc; font-weight: 800; color: #1e293b;">
                </div>
            </div>

            <hr style="border: none; border-top: 1px solid #f1f5f9; margin-bottom: 2rem;">

            <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button" onclick="document.getElementById('addAssessmentModal').classList.remove('active')"
                    style="padding: 0.9rem 2.25rem; border-radius: 50px; border: 1.5px solid #e2e8f0; background: white; color: #475569; font-weight: 800; cursor: pointer; transition: all 0.2s;">
                    Cancel
                </button>
                <button type="submit"
                    style="padding: 0.9rem 2.5rem; border-radius: 50px; border: none; background: #0061ff; color: white; font-weight: 800; cursor: pointer; box-shadow: 0 4px 15px rgba(0, 97, 255, 0.3); transition: all 0.2s;">
                    Save Assessment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Assessment Modal -->
<div class="modal-overlay" id="editAssessmentModal">
    <div class="modal" style="max-width: 500px; width: 95%; border-radius: 24px; padding: 2rem;">
        <h2 class="modal-title" style="font-weight: 900; font-size: 1.75rem; margin-bottom: 1.5rem; color: #1e293b;">
            Edit Nutritional Assessment</h2>
        <form id="editAssessmentForm">
            <input type="hidden" id="editRecordId" name="record_id">
            <input type="hidden" id="edit_birth_date" name="birth_date_hidden">
            <input type="hidden" id="edit_sex" name="sex_hidden">

            <div id="edit_live_summary"
                style="display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 0; margin-bottom: 1.5rem; background: #f0fdf4; padding: 1.25rem; border-radius: 16px; border: 1px solid #bbf7d0;">
                <div style="text-align: center;">
                    <div
                        style="font-size: 0.7rem; color: #166534; font-weight: 800; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 4px;">
                        BMI</div>
                    <div id="edit_live_bmi" style="font-size: 1.35rem; font-weight: 900; color: #14532d;">--</div>
                </div>
                <div style="text-align: center; border-left: 1px solid #bbf7d0; border-right: 1px solid #bbf7d0;">
                    <div
                        style="font-size: 0.7rem; color: #166534; font-weight: 800; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 4px;">
                        Status</div>
                    <div id="edit_live_status" style="font-size: 0.85rem; font-weight: 900; color: #14532d;">--</div>
                </div>
                <div style="text-align: center;">
                    <div
                        style="font-size: 0.7rem; color: #166534; font-weight: 800; text-transform: uppercase; letter-spacing: 0.025em; margin-bottom: 4px;">
                        HFA</div>
                    <div id="edit_live_hfa" style="font-size: 0.85rem; font-weight: 900; color: #14532d;">--</div>
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label
                    style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Assessment
                    Date</label>
                <input type="date" id="editDate" name="assessment_date" required
                    style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 12px; font-weight: 600; color: #334155;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 1.25rem;">
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Height
                        (cm)</label>
                    <input type="number" step="0.1" id="editHeight" name="height" required
                        style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 12px; font-weight: 700; color: #1e293b;">
                </div>
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Weight
                        (kg)</label>
                    <input type="number" step="0.1" id="editWeight" name="weight" required
                        style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #e2e8f0; border-radius: 12px; font-weight: 700; color: #1e293b;">
                </div>
            </div>

            <div style="display:none;">
                <input type="text" id="edit_ns_status" name="ns_status">
                <input type="text" id="edit_hfa_status" name="hfa_status">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; margin-bottom: 2rem;">
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Min
                        Target (kg)</label>
                    <input type="text" name="min_target_weight" id="edit_assess_min_target" readonly
                        style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #f1f5f9; border-radius: 12px; background: #f8fafc; font-weight: 800; color: #1e293b;">
                </div>
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 700; color: #64748b; margin-bottom: 0.5rem;">Max
                        Target (kg)</label>
                    <input type="text" name="max_target_weight" id="edit_assess_max_target" readonly
                        style="width: 100%; padding: 0.75rem 1rem; border: 1.5px solid #f1f5f9; border-radius: 12px; background: #f8fafc; font-weight: 800; color: #1e293b;">
                </div>
            </div>

            <hr style="border: none; border-top: 1px solid #f1f5f9; margin-bottom: 2rem;">

            <div class="modal-actions" style="display: flex; justify-content: flex-end; gap: 1rem;">
                <button type="button"
                    onclick="document.getElementById('editAssessmentModal').classList.remove('active')"
                    style="padding: 0.8rem 2rem; border-radius: 50px; border: 1.5px solid #e2e8f0; background: white; color: #475569; font-weight: 800; cursor: pointer; transition: all 0.2s;">
                    Cancel
                </button>
                <button type="submit"
                    style="padding: 0.8rem 2.5rem; border-radius: 50px; border: none; background: #10b981; color: white; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25); transition: all 0.2s;">
                    Update Assessment
                </button>
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
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">LRN
                        (Student ID)</label>
                    <input type="text" name="student_id" id="edit_student_id" required
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Birth
                        Date</label>
                    <input type="date" name="birth_date" id="edit_birth_date" required
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">First
                        Name</label>
                    <input type="text" name="first_name" id="edit_first_name" required
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Last
                        Name</label>
                    <input type="text" name="last_name" id="edit_last_name" required
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Sex</label>
                    <select name="sex" id="edit_sex"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Grade
                        Level</label>
                    <select name="grade_level" id="edit_grade_level"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
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
                <label
                    style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Section</label>
                <select name="section" id="edit_section_select" onchange="toggleManualSectionEdit(this.value)"
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    <option value="">Select Section</option>
                    <?php foreach ($sections as $s): ?>
                        <option value="<?php echo htmlspecialchars($s['section']); ?>">
                            <?php echo htmlspecialchars($s['section']); ?>
                        </option>
                    <?php endforeach; ?>
                    <option value="Other">Other (Type New...)</option>
                </select>
                <input type="text" name="manual_section" id="editManualSectionInput"
                    placeholder="Enter new section name"
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; margin-top: 0.5rem; display: none;">
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Parent's
                        Milk Consent</label>
                    <select name="parent_milk_consent" id="edit_milk_consent"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">4Ps
                        Beneficiary</label>
                    <select name="is_4ps" id="edit_is_4ps"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Dewormed?</label>
                    <select name="is_dewormed" id="edit_is_dewormed"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
                <div>
                    <label
                        style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Participation
                        Consent</label>
                    <select name="participation_consent" id="edit_participation_consent"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                        <option value="0">No</option>
                        <option value="1">Yes</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Dietary
                    Restrictions / Allergies</label>
                <div
                    style="display:flex; flex-wrap: wrap; gap: 0.5rem; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: white; max-height: 100px; overflow-y: auto;">
                    <?php foreach ($all_restrictions as $res): ?>
                        <label
                            style="display:flex; align-items:center; gap: 0.25rem; font-size: 0.75rem; background: var(--bg-color); padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid var(--border); cursor:pointer;">
                            <input type="checkbox" name="allergies[]" class="edit-allergy-cb"
                                value="<?php echo $res['restriction_id']; ?>">
                            <?php echo htmlspecialchars($res['restriction_name']); ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Min
                        Target Weight (kg)</label>
                    <input type="number" step="0.1" name="min_target_weight" id="edit_min_target" readonly
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; font-weight:700;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Max
                        Target Weight (kg)</label>
                    <input type="number" step="0.1" name="max_target_weight" id="edit_max_target" readonly
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; font-weight:700;">
                </div>
            </div>

            <!-- SBFP Indicators Removed -->


            <div class="modal-actions"
                style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem;">
                <button type="button" class="btn-m3 btn-m3-danger" onclick="deleteStudentAdmin()">
                    <span class="material-icons" style="font-size: 18px;">delete</span>
                    Delete Student
                </button>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="btn-m3 btn-m3-outline"
                        onclick="document.getElementById('editStudentModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn-m3 btn-m3-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Import Student Modal -->
<div class="modal-overlay" id="importStudentModal">
    <div class="modal" style="max-width: 450px; width: 95%;">
        <h2 class="modal-title">Bulk Student Import</h2>

        <form id="importForm">
            <div id="csvDropZone" class="input-group"
                style="position:relative; overflow:hidden; margin-bottom: 1.5rem; background: #f8fafc; border: 2px dashed #94a3b8; border-radius: 8px; text-align: center; transition: all 0.2s ease;">
                <div style="padding: 1.5rem; pointer-events:none;">
                    <span class="material-icons"
                        style="font-size: 32px; color: var(--primary); margin-bottom: 0.5rem;">upload_file</span>
                    <label
                        style="display:block; font-weight: 700; color: var(--text-main); margin-bottom: 0.35rem;">Drag &
                        Drop or Click to Browse</label>
                    <div id="csvFileName"
                        style="font-size: 0.75rem; color: var(--text-muted); font-weight:600; word-break: break-all;">No
                        CSV portfolio selected yet.</div>
                </div>
                <input type="file" id="csvFileInput" name="csvFile" accept=".csv" required
                    style="position:absolute; top:0; left:0; width:100%; height:100%; opacity:0; cursor: pointer;">
            </div>

            <div
                style="background: #f0f7ff; padding: 0.75rem 1rem; border-radius: 6px; margin-bottom: 1.5rem; border: 1px solid #cce3ff; display: flex; align-items: center; justify-content: space-between;">
                <div style="font-size: 0.75rem; color: #004085; line-height: 1.4;">
                    <span class="material-icons" style="font-size: 14px; vertical-align: middle;">info</span>
                    Need the format? Existing LRNs will be securely skipped.
                </div>
                <a href="api_download_template.php"
                    style="font-size: 0.75rem; font-weight: 700; color: #0061ff; text-decoration: none; white-space: nowrap; padding-left: 0.5rem;">
                    Download Template
                </a>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-m3 btn-m3-outline"
                    onclick="document.getElementById('importStudentModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn-m3 btn-m3-primary" style="flex-grow: 1;">Start Processing</button>
            </div>
        </form>
    </div>
</div>
<div class="modal-overlay" id="chartModal">
    <div class="modal" style="max-width: 600px; width: 95%; max-height: 90vh; overflow-y: auto;">
        <h2 class="modal-title" style="display:flex; justify-content:space-between; align-items:center;">
            <span id="chartModalTitle">Student Progress</span>
            <div style="display:flex; gap: 0.5rem;">
                <?php if ($role === 'Admin' || $role === 'Super Admin'): ?>
                    <button id="mainEditStudentBtn" class="btn-icon" title="Edit Student Profile"
                        style="color: var(--primary);"><span class="material-icons">edit</span></button>
                <?php endif; ?>
                <button class="btn-icon" onclick="closeChartModal()" title="Close"><span
                        class="material-icons">close</span></button>
            </div>

        </h2>
        <div style="height: 250px; width: 100%;">
            <canvas id="bmiChart"></canvas>
        </div>

        <div
            style="display: flex; gap: 1rem; margin-top: 1rem; background: var(--bg-color); padding: 0.75rem; border-radius: 8px; align-items: center;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">
                Filter Date Range:</div>
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <label style="font-size: 0.75rem;">From:</label>
                <input type="date" id="chartFilterFrom" onchange="updateHistoryView()"
                    style="padding: 0.25rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.8rem;">
            </div>
            <div style="display:flex; align-items:center; gap:0.5rem;">
                <label style="font-size: 0.75rem;">To:</label>
                <input type="date" id="chartFilterTo" onchange="updateHistoryView()"
                    style="padding: 0.25rem 0.5rem; border: 1px solid var(--border); border-radius: 4px; font-size: 0.8rem;">
            </div>
        </div>

        <h3 style="font-size: 1rem; margin: 1.5rem 0 0.5rem 0;">Nutritional History</h3>
        <table style="width: 100%; font-size: 0.875rem;" id="historyTable">
            <thead>
                <tr>
                    <th style="padding: 0.5rem; text-align:left;">Date</th>
                    <th style="padding: 0.5rem; text-align:left;">Height</th>
                    <th style="padding: 0.5rem; text-align:left;">Weight</th>
                    <th style="padding: 0.5rem; text-align:left;">BMI</th>
                    <th style="padding: 0.5rem; text-align:left;">Added By</th>
                    <th style="padding: 0.5rem; text-align:left;">Action</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>


    </div>
</div>

<!-- NEW: View Record Modal (for chart node interaction) -->
<div class="modal-overlay" id="viewAssessmentModal">
    <div class="modal" style="max-width: 350px;">
        <h2 class="modal-title" style="display:flex; justify-content:space-between; align-items:center;">
            Record Details
            <div style="display:flex; gap: 0.5rem;">
                <?php if ($role === 'Admin' || $role === 'Super Admin'): ?>
                    <button class="btn-icon" id="viewModalEditBtn" style="color: var(--primary);" title="Edit Record">
                        <span class="material-icons">edit</span>
                    </button>
                <?php endif; ?>

                <button class="btn-icon"
                    onclick="document.getElementById('viewAssessmentModal').classList.remove('active')" title="Close">
                    <span class="material-icons">close</span>
                </button>
            </div>
        </h2>
        <div id="viewModalContent" style="font-size: 0.9rem; line-height: 1.6;">
            <!-- Content dynamic -->
        </div>

    </div>
</div>

<script>
    // No special grade JS needed, handled via URL parameters for stability
    function filterByGrade(grade) {
        location.href = `?grade=${encodeURIComponent(grade)}&section=All`;
    }

    // Map the HTML call to our function
    window.filterByGrade = filterByGrade;

    // Persist filter on load
    window.addEventListener('load', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const gradeFilter = urlParams.get('grade_filter') || 'All';
        filterSectionsByGrade(gradeFilter);
    });

    // Filter Logic
    function filterTable() {
        const q = document.getElementById('searchInput').value.toLowerCase();
        const bmi = document.getElementById('bmiFilter').value;
        const enrolled = document.getElementById('enrollFilter').value;
        const rows = document.querySelectorAll('.student-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const name = row.getAttribute('data-name');
            const lrn = row.getAttribute('data-lrn');
            const allergens = row.getAttribute('data-allergies') || '';
            const rowBmi = row.getAttribute('data-bmi');
            const rowEnrolled = row.getAttribute('data-enrolled');

            const matchesSearch = name.includes(q) || lrn.includes(q) || allergens.includes(q);
            const matchesBmi = bmi === "" || rowBmi === bmi;
            const matchesEnroll = enrolled === "" || rowEnrolled === enrolled;

            if (matchesSearch && matchesBmi && matchesEnroll) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        // Update the display count in the header
        const countSpan = document.querySelector('.data-header .data-count');
        if (countSpan) countSpan.innerText = visibleCount + (visibleCount === 1 ? ' student' : ' total');
    }

    function clearFilters() {
        document.getElementById('searchInput').value = '';
        document.getElementById('bmiFilter').value = '';
        document.getElementById('enrollFilter').value = '1';
        filterTable();
    }

    function toggleManualSectionEdit(val) {
        const input = document.getElementById('editManualSectionInput');
        input.style.display = (val === 'Other') ? 'block' : 'none';
        if (val === 'Other') input.focus();
    }

    function toggleManualSection(val) {
        const input = document.getElementById('manualSectionInput');
        input.style.display = (val === 'Other') ? 'block' : 'none';
        if (val === 'Other') input.focus();
    }

    function openEditAssessmentModal(id, h, w, d) {
        document.getElementById('editRecordId').value = id;
        document.getElementById('editHeight').value = h;
        document.getElementById('editWeight').value = w;
        document.getElementById('editDate').value = d;

        // Use global currentStudentData which is set when show-progress-btn is clicked
        if (currentStudentData) {
            document.getElementById('edit_birth_date').value = currentStudentData.birth;
            document.getElementById('edit_sex').value = currentStudentData.sex;
        }

        document.getElementById('editAssessmentModal').classList.add('active');

        // Trigger calculation
        if (window.nutritionEngine) {
            window.nutritionEngine.attach('#editAssessmentForm', 'height', 'weight', 'edit_ns_status', 'edit_hfa_status', 'edit_assess_min_target', 'edit_assess_max_target', 'birth_date_hidden', 'sex_hidden');
        }
    }

    document.getElementById('bmiFilter').addEventListener('change', filterTable);

    const canManageAssessments = <?php echo json_encode($role === 'Admin' || $role === 'Super Admin' || $role === 'Faculty'); ?>;
    const isAdmin = <?php echo json_encode($role === 'Admin' || $role === 'Super Admin'); ?>;

    let progressChart = null;
    let currentStudentData = null; // Global tracker for editing

    function closeChartModal() {
        document.getElementById('chartModal').classList.remove('active');
    }

    function closeChartModal() {
        document.getElementById('chartModal').classList.remove('active');
    }

    // Background DOM refresh strategy for SPA-like feel
    let globalHistory = [];

    // Sync categorization with PHP helper colors
    function categorizeBMI_JS(bmi) {
        if (!bmi || bmi <= 0) return { label: 'Unknown', style: 'background-color: #f1f3f4; color: #5f6368;' };
        if (bmi < 16.0) return { label: 'Severely Wasted (Severe Thinness)', style: 'background-color: #ffebee; color: #c62828;' };
        if (bmi < 18.5) return { label: 'Moderate/Mild Thinness', style: 'background-color: #fff3e0; color: #ef6c00;' };
        if (bmi < 25.0) return { label: 'Healthy/Normal Weight', style: 'background-color: #e8f5e9; color: #2e7d32;' };
        if (bmi < 30.0) return { label: 'Overweight', style: 'background-color: #f3e5f5; color: #7b1fa2;' };
        if (bmi < 35.0) return { label: 'Obese Class I (Moderate)', style: 'background-color: #212121; color: #ffffff;' };
        if (bmi < 40.0) return { label: 'Obese Class II (Severe)', style: 'background-color: #1a1a1a; color: #ffffff;' };
        return { label: 'Obese Class III (Very Severe/Morbid)', style: 'background-color: #000000; color: #ffffff;' };
    }

    function updateHistoryView() {
        const from = document.getElementById('chartFilterFrom').value;
        const to = document.getElementById('chartFilterTo').value;

        let filtered = globalHistory;
        if (from) filtered = filtered.filter(h => h.accurate_date >= from);
        if (to) filtered = filtered.filter(h => h.accurate_date <= to);

        renderChartAndTable(filtered);
    }

    function renderChartAndTable(history) {
        // Build History Table
        const tbody = document.querySelector('#historyTable tbody');
        tbody.innerHTML = '';

        // Always Latest -> Oldest for the table
        const listItems = [...history].reverse();

        listItems.forEach(item => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td style="padding: 0.5rem; border-top: 1px solid var(--border);">${item.accurate_date}</td>
                <td style="padding: 0.5rem; border-top: 1px solid var(--border);">${item.height || '--'} cm</td>
                <td style="padding: 0.5rem; border-top: 1px solid var(--border);">${item.weight || '--'} kg</td>
                <td style="padding: 0.5rem; border-top: 1px solid var(--border); font-weight: 500; color: var(--primary);">${item.bmi || '--'}</td>
                <td style="padding: 0.5rem; border-top: 1px solid var(--border);">${item.author || '--'}</td>
                <td style="padding: 0.5rem; border-top: 1px solid var(--border); gap: 0.5rem; display: ${canManageAssessments ? 'flex' : 'none'};">
                    <button class="btn-icon" onclick="openEditAssessmentModal(${item.record_id}, ${item.height}, ${item.weight}, '${item.accurate_date}')" style="color: var(--primary); background: none; border: none; cursor: pointer;"><span class="material-icons" style="font-size: 16px;">edit</span></button>
                    <button class="btn-icon" onclick="deleteRecord(${item.record_id})" style="color: #d93025; background: none; border: none; cursor: pointer;"><span class="material-icons" style="font-size: 16px;">delete</span></button>
                </td>
            `;
            tbody.appendChild(tr);

        });

        // Chart render
        const ctx = document.getElementById('bmiChart').getContext('2d');
        if (progressChart) progressChart.destroy();

        // --- PREDICTIVE ENGINE ---
        function predictNextPoints(data, daysOut = [30, 60, 90]) {
            if (data.length < 2) return [];
            const n = data.length;
            let sumX = 0, sumY = 0, sumXY = 0, sumXX = 0;
            // Parse dates to relative days
            const parseDate = (d) => new Date(d).getTime() / 1000;
            const firstX = parseDate(data[0].accurate_date);

            for (let i = 0; i < n; i++) {
                const x = (parseDate(data[i].accurate_date) - firstX) / (24 * 60 * 60);
                const y = parseFloat(data[i].bmi);
                sumX += x; sumY += y;
                sumXY += x * y; sumXX += x * x;
            }
            const m = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);
            const b = (sumY - m * sumX) / n;

            const lastX = (parseDate(data[data.length - 1].accurate_date) - firstX) / (24 * 60 * 60);
            return daysOut.map(d => {
                const targetDay = lastX + d;
                const date = new Date(data[data.length - 1].accurate_date);
                date.setDate(date.getDate() + d);
                return {
                    label: "Predicted: " + date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }),
                    val: parseFloat((m * targetDay + b).toFixed(1))
                };
            });
        }

        const labels = history.map(item => item.date);
        const bmiData = history.map(item => item.bmi);
        const predictions = predictNextPoints(history);
        const predictionLabels = predictions.map(p => p.label);
        const predictionValues = [history[history.length - 1].bmi, ...predictions.map(p => p.val)];
        const fullLabels = [...labels, ...predictionLabels];
        const predictedDataset = Array(labels.length - 1).fill(null).concat(predictionValues);
        const minBMILine = fullLabels.map(() => 18.5);
        const maxBMILine = fullLabels.map(() => 24.9);

        progressChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: fullLabels,
                datasets: [
                    {
                        label: 'Actual BMI',
                        data: bmiData,
                        borderColor: '#0061ff',
                        backgroundColor: 'rgba(0, 97, 255, 0.1)',
                        borderWidth: 4,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#fff',
                        pointBorderColor: '#0061ff',
                        pointBorderWidth: 3,
                        pointRadius: 5,
                        pointHoverRadius: 8,
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
                        label: 'Healthy Range (Min)',
                        data: minBMILine,
                        borderColor: '#059669',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0
                    },
                    {
                        label: 'Healthy Range (Max)',
                        data: maxBMILine,
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
                    legend: {
                        display: true,
                        position: 'top',
                        labels: {
                            font: { weight: 600 },
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                let label = context.dataset.label || '';
                                if (label.includes('Predicted')) {
                                    return `Projected BMI: ${context.parsed.y}`;
                                }
                                return `BMI: ${context.parsed.y} kg/m²`;
                            }
                        }
                    }
                },
                onClick: (e, items) => {
                    if (items.length > 0) {
                        const index = items[0].index;
                        const record = history[index];
                        if (record) openViewAssessmentModal(record);
                    }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { font: { weight: 600 } }
                    },
                    y: {
                        beginAtZero: false,
                        grid: { borderDash: [5, 5], color: '#e3e8ee' },
                        title: {
                            display: true,
                            text: 'BMI (kg/m²)',
                            font: { weight: 600 }
                        },
                        suggestedMin: 12,
                        suggestedMax: 30
                    }
                }
            }
        });
    }

    function toggleManualSection(val) {
        const input = document.getElementById('manualSectionInput');
        if (val === "Other") {
            input.style.display = "block";
            input.setAttribute('required', 'required');
        } else {
            input.style.display = "none";
            input.removeAttribute('required');
        }
    }

    function toggleManualSectionEdit(val) {
        const input = document.getElementById('editManualSectionInput');
        if (val === "Other") {
            input.style.display = "block";
            input.setAttribute('required', 'required');
        } else {
            input.style.display = "none";
            input.removeAttribute('required');
        }
    }

    function refreshTableSilent(activeModalToClose = null) {
        if (activeModalToClose) document.getElementById(activeModalToClose).classList.remove('active');

        fetch(window.location.href)
            .then(r => r.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                // Re-inject table body cleanly
                document.querySelector('.table-container').innerHTML = doc.querySelector('.table-container').innerHTML;
                filterTable(); // Reapply user's filter search
            });
    }

    function openViewAssessmentModal(record) {
        const cat = categorizeBMI_JS(record.bmi);
        document.getElementById('viewModalContent').innerHTML = `
            <div style="margin-bottom: 0.5rem; color: var(--text-muted);">Assessment for ${record.accurate_date}</div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                <div>
                    <strong>Height</strong><br>${record.height} cm
                </div>
                <div>
                    <strong>Weight</strong><br>${record.weight} kg
                </div>
            </div>
            <div style="margin-top: 1rem;">
                <strong>BMI</strong><br>
                <span style="font-size: 1.1rem; font-weight: 700; color: var(--primary);">${record.bmi}</span>
                <span style="font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 4px; margin-left: 0.5rem; ${cat.style}">${cat.label}</span>
            </div>
            <div style="margin-top: 1rem; font-size: 0.8rem; color: var(--text-muted);">
                Recorded by ${record.author}
            </div>
        `;

        document.getElementById('viewModalEditBtn').onclick = () => {
            document.getElementById('viewAssessmentModal').classList.remove('active');
            openEditAssessmentModal(record.record_id, record.height, record.weight, record.accurate_date);
        };

        document.getElementById('viewAssessmentModal').classList.add('active');
    }

    // Event Delegation for Table actions
    document.querySelector('.table-container').addEventListener('click', function (e) {
        // Add Assessment Button
        const addBtn = e.target.closest('.add-assessment-btn');
        if (addBtn) {
            document.getElementById('assessLrn').value = addBtn.getAttribute('data-student_id');
            document.getElementById('assess_height').value = addBtn.getAttribute('data-height');
            document.getElementById('assess_weight').value = addBtn.getAttribute('data-weight');
            document.getElementById('assess_min_target_weight').value = addBtn.getAttribute('data-min-target');
            document.getElementById('assess_max_target_weight').value = addBtn.getAttribute('data-max-target');
            // Populate hidden fields for automation
            document.getElementById('assess_birth_date').value = addBtn.getAttribute('data-birth');
            document.getElementById('assess_sex').value = addBtn.getAttribute('data-sex');
            document.getElementById('addAssessmentModal').classList.add('active');

            // Trigger calculation
            if (window.nutritionEngine) {
                window.nutritionEngine.attach('#assessmentForm', 'height', 'weight', 'assess_ns_status', 'assess_hfa_status', 'assess_min_target_weight', 'assess_max_target_weight', 'birth_date_hidden', 'sex_hidden');
            }
            return;
        }


        // Show Progress Button
        const progBtn = e.target.closest('.show-progress-btn');
        if (progBtn) {
            e.preventDefault();

            // Capture data for "Edit Student" button to use later
            currentStudentData = {
                id: progBtn.getAttribute('data-student_id'),
                fname: progBtn.getAttribute('data-fname'),
                lname: progBtn.getAttribute('data-lname'),
                sex: progBtn.getAttribute('data-sex'),
                birth: progBtn.getAttribute('data-birth'),
                grade: progBtn.getAttribute('data-grade'),
                section: progBtn.getAttribute('data-section'),
                height: progBtn.getAttribute('data-height'),
                milk: progBtn.getAttribute('data-milk'),
                participation: progBtn.getAttribute('data-participation'),
                is_4ps: progBtn.getAttribute('data-4ps'),
                dewormed: progBtn.getAttribute('data-dewormed'),
                allergens: progBtn.getAttribute('data-allergens') ? progBtn.getAttribute('data-allergens').split(',') : []
            };

            const historyStr = progBtn.getAttribute('data-history');
            if (!historyStr) return;

            globalHistory = JSON.parse(historyStr);
            const minTarget = progBtn.getAttribute('data-min-target');
            const maxTarget = progBtn.getAttribute('data-max-target');
            const status = progBtn.getAttribute('data-status');
            const statusStyle = progBtn.getAttribute('data-status-style');

            document.getElementById('chartModalTitle').innerHTML = `
            <div style="display:flex; align-items:center; gap:0.75rem;">
                ${progBtn.getAttribute('data-name')}'s Progress
                <span style="font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 4px; ${statusStyle}">${status}</span>
            </div>
        `;
            document.getElementById('chartModal').classList.add('active');

            // Update Edit Form hidden values
            document.getElementById('edit_min_target').value = minTarget;
            document.getElementById('edit_max_target').value = maxTarget;

            // Reset filter inputs
            document.getElementById('chartFilterFrom').value = '';
            document.getElementById('chartFilterTo').value = '';

            renderChartAndTable(globalHistory);
        }
    });

    // AJAX Form Submissions
    document.getElementById('assessmentForm').addEventListener('submit', function (e) {
        e.preventDefault();
        fetch('api_add_assessment.php', {
            method: 'POST',
            body: new FormData(this)
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.reset();
                    refreshTableSilent('addAssessmentModal');
                } else alert('Error adding assessment: ' + data.error);
            });
    });

    // AJAX Add Student
    document.getElementById('addStudentForm').addEventListener('submit', function (e) {
        e.preventDefault();
        fetch('api_add_student.php', {
            method: 'POST',
            body: new FormData(this)
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    this.reset();
                    location.reload(); // Refresh to update classroom tabs if new section added
                } else alert('Error adding student: ' + data.error);
            });
    });

    // Edit Student Button (Inside Progress Modal) - Admin only
    const mainEditStudentBtn = document.getElementById('mainEditStudentBtn');
    if (mainEditStudentBtn) {
        mainEditStudentBtn.addEventListener('click', function () {
            if (!currentStudentData) return;

            // Fill Edit Modal
            document.getElementById('edit_original_lrn').value = currentStudentData.id;
            document.getElementById('edit_student_id').value = currentStudentData.id;
            document.getElementById('edit_first_name').value = currentStudentData.fname;
            document.getElementById('edit_last_name').value = currentStudentData.lname;
            document.getElementById('edit_birth_date').value = currentStudentData.birth;
            document.getElementById('edit_sex').value = currentStudentData.sex;
            document.getElementById('edit_grade_level').value = currentStudentData.grade;
            document.getElementById('edit_milk_consent').value = currentStudentData.milk;
            document.getElementById('edit_is_4ps').value = currentStudentData.is_4ps;
            document.getElementById('edit_is_dewormed').value = currentStudentData.dewormed;
            document.getElementById('edit_participation_consent').value = currentStudentData.participation;

            // Handle Section Select vs Manual
            const sectSelect = document.getElementById('edit_section_select');
            const sectManual = document.getElementById('editManualSectionInput');
            let found = false;
            for (let i = 0; i < sectSelect.options.length; i++) {
                if (sectSelect.options[i].value === currentStudentData.section) {
                    sectSelect.value = currentStudentData.section;
                    found = true;
                    break;
                }
            }
            if (!found) {
                sectSelect.value = "Other";
                sectManual.value = currentStudentData.section;
                sectManual.style.display = "block";
            } else {
                sectManual.value = "";
                sectManual.style.display = "none";
            }

            // Handle Allergies
            document.querySelectorAll('.edit-allergy-cb').forEach(cb => {
                cb.checked = currentStudentData.allergens.includes(cb.value);
            });

            closeChartModal();
            document.getElementById('editStudentModal').classList.add('active');
        });
    } // end if (mainEditStudentBtn)

    // AJAX Edit Student
    document.getElementById('editStudentForm').addEventListener('submit', function (e) {
        e.preventDefault();
        fetch('api_edit_student.php', {
            method: 'POST',
            body: new FormData(this)
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Student profile updated successfully.');
                    location.reload(); // Hard reload since section/name might change drastically
                } else alert('Error updating profile: ' + data.error);
            });
    });

    // AJAX Edit Assessment
    document.getElementById('editAssessmentForm').addEventListener('submit', function (e) {
        e.preventDefault();
        fetch('api_edit_assessment.php', {
            method: 'POST',
            body: new FormData(this)
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    refreshTableSilent('editAssessmentModal');
                } else alert('Error updating record: ' + data.error);
            });
    });


    // AJAX Delete
    window.deleteRecord = function (id) {
        if (!confirm("Are you sure you want to permanently delete this record?")) return;
        const fd = new FormData();
        fd.append('record_id', id);

        fetch('api_delete_assessment.php', {
            method: 'POST',
            body: fd
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) refreshTableSilent('chartModal');
                else alert('Error deleting: ' + data.error);
            });
    }

    window.deleteStudentAdmin = function () {
        if (!currentStudentData || !currentStudentData.id) return;

        const fullName = `${currentStudentData.fname} ${currentStudentData.lname}`;
        if (!confirm(`CAUTION: Are you sure you want to PERMANENTLY delete ${fullName}?\n\nThis will also remove all their nutritional history and meal plans. This action cannot be undone.`)) return;

        const fd = new FormData();
        fd.append('student_id', currentStudentData.id);

        fetch('api_delete_student.php', {
            method: 'POST',
            body: fd
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Student deleted successfully.');
                    location.reload();
                } else {
                    alert('Error deleting student: ' + data.error);
                }
            });
    }
    // Drag & Drop File Visualizer
    const fileInput = document.getElementById('csvFileInput');
    const dropZone = document.getElementById('csvDropZone');
    const fileNameDisplay = document.getElementById('csvFileName');

    if (fileInput) {
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                fileNameDisplay.innerText = "Selected: " + fileInput.files[0].name;
                fileNameDisplay.style.color = "var(--primary)";
                dropZone.style.borderColor = "var(--primary)";
                dropZone.style.background = "#f0fdf4"; // faint green
            } else {
                fileNameDisplay.innerText = "No CSV portfolio selected yet.";
                fileNameDisplay.style.color = "var(--text-muted)";
                dropZone.style.borderColor = "#94a3b8";
                dropZone.style.background = "#f8fafc";
            }
        });
        fileInput.addEventListener('dragenter', () => {
            dropZone.style.borderColor = "var(--primary)";
            dropZone.style.background = "#eff6ff";
            dropZone.style.transform = "scale(1.02)";
        });
        fileInput.addEventListener('dragleave', () => {
            dropZone.style.transform = "scale(1)";
            if (!fileInput.files.length) {
                dropZone.style.borderColor = "#94a3b8";
                dropZone.style.background = "#f8fafc";
            }
        });
        fileInput.addEventListener('drop', () => {
            dropZone.style.transform = "scale(1)";
        });
    }

    // Import Student Action
    document.getElementById('importForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn = this.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerText = 'Processing...';

        try {
            const res = await fetch('api_import_students.php', {
                method: 'POST',
                body: new FormData(this)
            });
            const data = await res.json();
            if (data.success) {
                if (data.duplicates && data.duplicates.length > 0) {
                    let dupRows = data.duplicates.map(d => `
                        <div style="background:var(--bg-color); padding: 0.75rem; border-radius: 8px; margin-bottom:0.5rem; border:1px solid var(--border);">
                            <div style="font-weight:900; color:var(--text-main); font-family:monospace; margin-bottom:4px;">LRN: ${d.lrn}</div>
                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:0.5rem; font-size:0.8rem;">
                                <div><strong style="color:var(--text-muted);">Existing:</strong> ${d.old.name} <br> <span style="font-size:0.7rem;">${d.old.grade} - ${d.old.section}</span></div>
                                <div><strong style="color:var(--primary);">Incoming:</strong> ${d.new.name} <br> <span style="font-size:0.7rem;">${d.new.grade} - ${d.new.section}</span></div>
                            </div>
                        </div>
                    `).join('');

                    document.getElementById('importStudentModal').classList.remove('active');

                    AlgoModal.show({
                        title: 'Notice: Existing LRNs Detected',
                        body: `
                            <p style="margin-top:0; font-size:0.85rem; color:var(--text-muted);">${data.message}</p>
                            <p style="font-size:0.85rem; color:var(--text-main); font-weight:700;">Do you want to overwrite demographic info for the following students with the CSV data?</p>
                            <div style="max-height: 250px; overflow-y:auto; margin-bottom:1rem; padding-right:8px;">${dupRows}</div>
                        `,
                        footer: `
                            <button class="btn-m3 btn-m3-outline" onclick="location.reload()">Ignore & Finish</button>
                            <button class="btn-m3 btn-m3-primary" id="btnConfirmDups">Overwrite Records</button>
                        `
                    });

                    document.getElementById('btnConfirmDups').onclick = async () => {
                        document.getElementById('btnConfirmDups').disabled = true;
                        document.getElementById('btnConfirmDups').innerText = 'Updating...';
                        try {
                            const upRes = await fetch('api_update_bulk_students.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({ students: data.duplicates.map(d => d.new).map((n, i) => ({ ...n, lrn: data.duplicates[i].lrn })) })
                            });
                            const upData = await upRes.json();
                            alert(upData.message);
                            location.reload();
                        } catch (err) {
                            alert('Failed to update duplicate records.');
                            location.reload();
                        }
                    };
                } else {
                    alert(data.message);
                    location.reload();
                }
            } else {
                alert('Import Failed: ' + data.error);
            }
        } catch (e) {
            alert('A system error occurred during extraction.');
        } finally {
            btn.disabled = false;
            btn.innerText = 'Start Import';
        }
    });

    /**
     * NutritionEngine: WHO Growth Standards (5-13 years)
     * Handles real-time calculation of BMI-A and HFA statuses.
     */
    const NutritionEngine = {
        bmi_boys: { 5: [15.3, 12.1, 13.0, 14.1, 16.6, 18.3], 6: [15.3, 12.1, 13.0, 14.1, 16.8, 18.5], 7: [15.4, 12.1, 13.1, 14.1, 17.0, 19.0], 8: [15.5, 12.2, 13.2, 14.3, 17.4, 19.7], 9: [15.8, 12.3, 13.4, 14.5, 17.9, 20.5], 10: [16.2, 12.4, 13.6, 14.8, 18.5, 21.4], 11: [16.6, 12.7, 13.9, 15.2, 19.2, 22.5], 12: [17.1, 13.0, 14.3, 15.6, 20.0, 23.6], 13: [17.7, 13.4, 14.8, 16.2, 20.8, 24.8] },
        bmi_girls: { 5: [15.2, 11.8, 12.7, 13.9, 16.9, 18.9], 6: [15.2, 11.7, 12.7, 13.8, 17.0, 19.2], 7: [15.3, 11.8, 12.7, 14.0, 17.3, 19.8], 8: [15.6, 11.9, 12.9, 14.2, 17.7, 20.6], 9: [15.9, 12.1, 13.1, 14.4, 18.3, 21.5], 10: [16.4, 12.4, 13.5, 14.8, 19.0, 22.6], 11: [17.0, 12.7, 13.9, 15.3, 19.9, 23.7], 12: [17.6, 13.2, 14.4, 15.9, 20.8, 25.0], 13: [18.2, 13.6, 15.0, 16.5, 21.8, 26.2] },
        hfa_boys: { 5: [110, 98.7, 102.5, 117.5], 6: [116, 103.8, 107.9, 124.2], 7: [121.7, 108.6, 113, 130.4], 8: [127.3, 113.3, 118, 136.6], 9: [132.6, 117.8, 122.8, 142.5], 10: [137.8, 122.2, 127.4, 148.4], 11: [143.1, 126.8, 132.2, 154.5], 12: [149.1, 131.8, 137.6, 161.4], 13: [156, 137.6, 143.7, 169.3] },
        hfa_girls: { 5: [109.4, 98.4, 102.1, 116.7], 6: [115.1, 103.2, 107.2, 123], 7: [120.8, 108, 112.2, 129.5], 8: [126.4, 112.8, 117.3, 135.9], 9: [132.2, 117.9, 122.6, 142.5], 10: [138.6, 123.5, 128.5, 150.1], 11: [145, 129.4, 134.6, 157.8], 12: [151.2, 135.2, 140.6, 164.7], 13: [156.4, 140.3, 145.7, 170] },

        calculate: (height, weight, birthDate, sex, assessDate) => {
            if (!height || !weight || !birthDate || isNaN(new Date(birthDate))) return null;
            const h_m = height / 100;
            const bmi = weight / (h_m * h_m);
            const dob = new Date(birthDate);
            const ref = assessDate ? new Date(assessDate) : new Date();

            let age = ref.getFullYear() - dob.getFullYear();
            let mDiff = ref.getMonth() - dob.getMonth();
            if (mDiff < 0 || (mDiff === 0 && ref.getDate() < dob.getDate())) age--;

            const lookupAge = Math.max(5, Math.min(13, age));

            const bmiTable = (sex?.toLowerCase() === 'female') ? NutritionEngine.bmi_girls : NutritionEngine.bmi_boys;
            const hfaTable = (sex?.toLowerCase() === 'female') ? NutritionEngine.hfa_girls : NutritionEngine.hfa_boys;

            let ns = 'Normal';
            if (bmi < 16.0) ns = 'Severely Wasted';
            else if (bmi < 18.5) ns = 'Wasted';
            else if (bmi >= 30.0) ns = 'Obese';
            else if (bmi >= 25.0) ns = 'Overweight';

            const hRef = hfaTable[lookupAge];
            let hfa = 'Normal';
            if (height < hRef[1]) hfa = 'Severely Stunted';
            else if (height < hRef[2]) hfa = 'Stunted';
            else if (height > hRef[3]) hfa = 'Tall';

            const minBMI = 18.5; // Reverted to user preference
            const maxBMI = 24.9;

            return { bmi: bmi.toFixed(1), ns, hfa, min: (minBMI * h_m * h_m).toFixed(1), max: (maxBMI * h_m * h_m).toFixed(1) };
        },

        attach: (formId, hName, wName, nsId, hfaId, minId, maxId, bName, sName, aName = 'assessment_date') => {
            const form = typeof formId === 'string' ? document.querySelector(formId) : formId;
            if (!form) return;
            const hIn = form.querySelector(`[name="${hName}"]`), wIn = form.querySelector(`[name="${wName}"]`);
            const bIn = form.querySelector(`[name="${bName}"]`), sIn = form.querySelector(`[name="${sName}"]`);
            const aIn = form.querySelector(`[name="${aName}"]`);
            const nsOut = form.querySelector(`#${nsId}`), hfaOut = form.querySelector(`#${hfaId}`);
            const minOut = form.querySelector(`#${minId}`), maxOut = form.querySelector(`#${maxId}`);

            const update = () => {
                if (!hIn.value || !wIn.value || !bIn.value) {
                    if (nsOut) nsOut.value = 'Fill Birth Date';
                    if (hfaOut) hfaOut.value = 'Fill Birth Date';
                    return;
                }
                const res = NutritionEngine.calculate(parseFloat(hIn.value), parseFloat(wIn.value), bIn.value, sIn.value, aIn?.value);
                if (res) {
                    if (nsOut) nsOut.value = res.ns;
                    if (hfaOut) hfaOut.value = res.hfa;
                    if (minOut) minOut.value = res.min;
                    if (maxOut) maxOut.value = res.max;

                    // Update Live Summaries if they exist
                    const liveBmi = form.querySelector('[id*="live_bmi"]');
                    const liveStatus = form.querySelector('[id*="live_status"]');
                    const liveHfa = form.querySelector('[id*="live_hfa"]');
                    if (liveBmi) liveBmi.innerText = res.bmi;
                    if (liveStatus) liveStatus.innerText = res.ns;
                    if (liveHfa) liveHfa.innerText = res.hfa;
                }
            };
            hIn.addEventListener('input', update); wIn.addEventListener('input', update);
            if (bIn) bIn.addEventListener('input', update); if (sIn) sIn.addEventListener('input', update);
            if (aIn) aIn.addEventListener('input', update);
            update();
        }
    };
    window.nutritionEngine = NutritionEngine;

    document.addEventListener('DOMContentLoaded', () => {
        // Register Student
        NutritionEngine.attach('#addStudentForm', 'init_height', 'init_weight', 'ns_status_enroll', 'hfa_status_enroll', 'min_target_weight_enroll', 'max_target_weight_enroll', 'birth_date', 'sex');
        // Add Assessment
        NutritionEngine.attach('#assessmentForm', 'height', 'weight', 'assess_ns_status', 'assess_hfa_status', 'assess_min_target_weight', 'assess_max_target_weight', 'birth_date_hidden', 'sex_hidden');
        // Edit Assessment
        NutritionEngine.attach('#editAssessmentForm', 'height', 'weight', 'edit_ns_status', 'edit_hfa_status', 'edit_assess_min_target', 'edit_assess_max_target', 'birth_date_hidden', 'sex_hidden');
    });

    async function addCustomRestriction(formId, inputId) {
        const input = document.getElementById(inputId);
        const name = input.value.trim();
        if (!name) return;

        try {
            const res = await fetch('../management/api_add_restriction.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name })
            });
            const data = await res.json();
            if (data.success) {
                const form = document.getElementById(formId);
                const grid = form.querySelector('.allergy-grid') || form.querySelector('div[style*="flex-wrap: wrap"]');

                // Create new checkbox
                const label = document.createElement('label');
                label.style = "display:flex; align-items:center; gap: 0.25rem; font-size: 0.75rem; background: #fffbeb; padding: 0.15rem 0.4rem; border-radius: 4px; border: 1px solid #f59e0b; cursor:pointer;";
                label.innerHTML = `<input type="checkbox" name="allergies[]" value="${data.restriction_id}" checked> ${name}`;

                grid.appendChild(label);
                input.value = '';
            } else {
                alert(data.message);
            }
        } catch (e) { alert('Failed to add restriction.'); }
    }

</script>

<?php require_once '../../includes/footer.php'; ?>
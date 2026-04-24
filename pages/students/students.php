<?php
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../db.php'; // Include the database connection

$page_title = 'Students';
require_once '../../includes/topbar.php';
?>
<script>
    function updateAgeCalculation() {
        // Find inputs in the currently visible/active form (Add or Edit)
        const activeModal = document.querySelector('.modal.active');
        if (!activeModal) return;

        const bdateVal = activeModal.querySelector('input[name="birth_date"]').value;
        const assessDateVal = activeModal.querySelector('input[name="assessment_date"]').value;
        if (!bdateVal) return;

        const dob = new Date(bdateVal);
        const ref = assessDateVal ? new Date(assessDateVal) : new Date();

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

// Determine active section (default to 'All' if not specified)
$active_section = $_GET['section'] ?? 'All';
$active_grade_filter = $_GET['grade_filter'] ?? 'All';

$active_grade = '';
if ($active_section != 'All') {
    foreach ($sections as $s) {
        if ($s['section'] == $active_section)
            $active_grade = $s['grade_level'];
    }
}

// Total counts for the "All" view
$total_students_enrolled = array_sum(array_column($sections, 'enrolled'));

// Fetch students (For a specific section or ALL)
$students = [];
if ($active_section != '') {
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
            (SELECT height FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_height,
            (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC, record_id DESC LIMIT 1) as current_weight,
            (SELECT weight FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date ASC, record_id ASC LIMIT 1) as baseline_weight,
            (SELECT COUNT(*) FROM nutritional_record WHERE student_id = s.student_id) as record_count,
            GROUP_CONCAT(dr.restriction_name SEPARATOR ', ') as allergens,
            GROUP_CONCAT(sam.restriction_id SEPARATOR ',') as allergen_ids
        FROM student s
        LEFT JOIN student_allergy_map sam ON s.student_id = sam.student_id
        LEFT JOIN dietary_restrictions dr ON sam.restriction_id = dr.restriction_id
        " . ($active_section === 'All' ? "" : "WHERE s.section = ?") . "
        GROUP BY s.student_id
    ";

    $stmt = $conn->prepare($sql);
    if ($active_section !== 'All') {
        $stmt->bind_param("s", $active_section);
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
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--border);
        vertical-align: middle;
    }

    /* Student Cell */
    .student-cell {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .avatar {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 500;
        font-size: 0.875rem;
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
            <?php if ($role === 'Admin'): ?>
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
            SECTION</span>
        <span>Showing <?php echo count($sections); ?> Sections</span>
    </div>

    <?php
    // Get unique grades for the filter bar
    $unique_grades = array_unique(array_column($sections, 'grade_level'));
    sort($unique_grades);
    ?>
    <div class="grade-filter-container">
        <button class="grade-filter-btn <?php echo ($active_grade_filter === 'All') ? 'active' : ''; ?>"
            onclick="filterByGrade('All')">All</button>
        <?php foreach ($unique_grades as $g): ?>
            <button class="grade-filter-btn <?php echo ($active_grade_filter === $g) ? 'active' : ''; ?>"
                onclick="filterByGrade('<?php echo htmlspecialchars($g); ?>')"><?php echo htmlspecialchars($g); ?></button>
        <?php endforeach; ?>
    </div>

    <div class="section-cards" id="sectionCardsContainer">
        <!-- Global "All Students" Card -->
        <a href="?section=All&grade_filter=All"
            class="section-tab <?php echo ($active_section === 'All') ? 'active' : ''; ?>" data-grade="All">
            <div class="tab-title">
                <span style="<?php echo ($active_section === 'All') ? 'color: var(--primary);' : ''; ?>">All
                    Students</span>
                <?php if ($active_section === 'All'): ?>
                    <span class="material-icons" style="color: var(--primary); font-size: 18px;">check_circle</span>
                <?php endif; ?>
            </div>
            <div class="tab-subtitle">Global School Overview</div>
            <div style="margin-top: 1rem;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size: 0.75rem; color: var(--text-muted);">Total Enrollment</span>
                    <span
                        style="font-size: 0.875rem; font-weight: 700; color: var(--primary);"><?php echo $total_students_enrolled; ?></span>
                </div>
            </div>
        </a>

        <?php foreach ($sections as $s): ?>
            <?php
            $isActive = ($s['section'] == $active_section);
            // Calculate section progress (mock logic for now)
            $enrolled = $s['enrolled'];
            $progress = $enrolled > 0 ? min(100, (6 / $enrolled) * 100) : 0;
            ?>
            <a href="?section=<?php echo urlencode($s['section']); ?>&grade_filter=<?php echo urlencode($active_grade_filter); ?>"
                class="section-tab <?php echo $isActive ? 'active' : ''; ?>"
                data-grade="<?php echo htmlspecialchars($s['grade_level']); ?>">
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

                <select id="bmiFilter" style="padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    <option value="">All BMI</option>
                    <option value="Normal">Normal</option>
                    <option value="Wasted">Wasted</option>
                    <option value="Severely Wasted">Severely Wasted</option>
                    <option value="Overweight">Overweight</option>
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
                            if ($st['record_count'] <= 1) {
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
                            $bmiData = categorizeBMI($calculated_bmi);
                            $chartJSON = htmlspecialchars(json_encode($history_data[$st['student_id']] ?? []));
                            $allergens = $st['allergens'] ? $st['allergens'] : 'No Restrictions';
                            $isAllergic = $st['allergens'] != '';

                            $color = $colors[$index % count($colors)];
                            ?>
                            <tr class="student-row" data-lrn="<?php echo htmlspecialchars($st['student_id']); ?>"
                                data-name="<?php echo htmlspecialchars(strtolower($st['first_name'] . ' ' . $st['last_name'])); ?>"
                                data-allergies="<?php echo htmlspecialchars(strtolower($allergens)); ?>"
                                data-bmi="<?php echo htmlspecialchars($bmiData['label']); ?>">
                                <td>
                                    <span
                                        style="font-size: 0.875rem; color: var(--text-muted); font-weight: 500; font-family: monospace;">
                                        <?php echo htmlspecialchars($st['student_id']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="student-cell">
                                        <div class="avatar" style="background-color: <?php echo $color; ?>;">
                                            <?php echo strtoupper(substr($st['first_name'], 0, 1)); ?>
                                        </div>
                                        <div class="student-info">
                                            <a href="student_profile.php?id=<?php echo urlencode($st['student_id']); ?>"
                                                class="student-name-link"
                                                style="text-decoration: none; color: var(--primary); font-weight: 800; display: inline-block;">
                                                <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>
                                            </a>
                                            <style>
                                                .student-name-link:hover {
                                                    text-decoration: underline !important;
                                                    cursor: pointer;
                                                }
                                            </style>
                                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem;">
                                                <?php echo $st['current_height'] ?: '--'; ?> cm •
                                                <?php echo $st['current_weight'] ?: '--'; ?> kg
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-main);">
                                        <?php echo htmlspecialchars($st['grade_level'] ?: 'Unassigned'); ?></div>
                                    <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">
                                        <?php echo htmlspecialchars($st['section'] ?: '--'); ?></div>
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
                                        </small><?php echo $calculated_bmi ? number_format($calculated_bmi, 1) : '--'; ?></span>
                                    <?php if ($calculated_bmi): ?>
                                        <span class="<?php echo $bmiData['class']; ?>"
                                            style="font-size:0.65rem; padding: 0.15rem 0.5rem; margin-top:0.35rem; display:block; text-align:center; width: max-content; <?php echo $bmiData['style']; ?>"><?php echo $bmiData['label']; ?></span>
                                    <?php endif; ?>
                                    <span style="font-weight: 500;">Target Range:</span>
                                    <span
                                        style="color: var(--primary); font-weight: 600;"><?php echo number_format($st['min_target_weight'], 1); ?>
                                        - <?php echo number_format($st['max_target_weight'], 1); ?> kg</span>
                                </td>
                                <td>
                                    <span class="weight-gain <?php echo $diffClass; ?>"><?php echo $diffStr; ?></span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; align-items: center;">
                                        <button class="btn-m3 btn-m3-tonal show-progress-btn"
                                            data-min-target="<?php echo $st['min_target_weight']; ?>"
                                            data-max-target="<?php echo $st['max_target_weight']; ?>"
                                            data-name="<?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>"
                                            data-fname="<?php echo htmlspecialchars($st['first_name']); ?>"
                                            data-lname="<?php echo htmlspecialchars($st['last_name']); ?>"
                                            data-student_id="<?php echo htmlspecialchars($st['student_id']); ?>"
                                            data-height="<?php echo $st['current_height'] ?? ''; ?>"
                                            data-sex="<?php echo $st['sex']; ?>" data-birth="<?php echo $st['birth_date']; ?>"
                                            data-grade="<?php echo htmlspecialchars($st['grade_level']); ?>"
                                            data-section="<?php echo htmlspecialchars($st['section']); ?>"
                                            data-allergens="<?php echo $st['allergen_ids']; ?>"
                                            data-history="<?php echo $chartJSON; ?>"
                                            data-status="<?php echo $bmiData['label']; ?>"
                                            data-status-style="<?php echo $bmiData['style']; ?>"
                                            data-milk="<?php echo $st['parent_milk_consent']; ?>"
                                            data-participation="<?php echo $st['participation_consent']; ?>"
                                            data-4ps="<?php echo $st['is_4ps_beneficiary']; ?>"
                                            data-dewormed="<?php echo $st['deworming_status']; ?>"
                                            style="padding: 6px 12px; font-size: 0.75rem; border-radius: 12px;">
                                            <span class="material-icons" style="font-size:14px;">visibility</span> View
                                        </button>
                                        <button class="btn-m3 btn-m3-outline add-assessment-btn"
                                            data-student_id="<?php echo htmlspecialchars($st['student_id']); ?>"
                                            data-height="<?php echo $st['current_height'] ?? ''; ?>"
                                            data-weight="<?php echo $st['current_weight'] ?? ''; ?>"
                                            data-min-target="<?php echo $st['min_target_weight']; ?>"
                                            data-max-target="<?php echo $st['max_target_weight']; ?>"
                                            style="padding: 6px 12px; font-size: 0.75rem; border-radius: 12px; background:transparent;">
                                            <span class="material-icons" style="font-size:14px;">fitness_center</span> Assess
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



<!-- Add Assessment Modal -->
<div class="modal-overlay" id="addAssessmentModal">
    <div class="modal" style="max-width: 400px; width: 95%;">
        <h2 class="modal-title">New Nutritional Record</h2>
        <form id="assessmentForm">
            <input type="hidden" id="assessLrn" name="student_id">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Height
                    (cm)</label>
                <input type="number" step="0.1" name="height" id="assess_height" required
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Weight
                    (kg)</label>
                <input type="number" step="0.1" name="weight" id="assess_weight" required
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Min
                        Target (kg)</label>
                    <input type="number" step="0.1" name="min_target_weight" id="assess_min_target_weight"
                        placeholder="Min" readonly
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; color: var(--text-muted); font-weight: 700;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Max
                        Target (kg)</label>
                    <input type="number" step="0.1" name="max_target_weight" id="assess_max_target_weight"
                        placeholder="Max" readonly
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; color: var(--text-muted); font-weight: 700;">
                </div>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Assessment
                    Date</label>
                <input type="date" name="assessment_date" value="<?php echo date('Y-m-d'); ?>" required
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel"
                    onclick="document.getElementById('addAssessmentModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn" style="background: var(--primary); color: white;">Save Record</button>
            </div>
        </form>
    </div>
</div>

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
                    <input type="date" name="birth_date" required
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
                    <select name="sex"
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
                    <input type="text" id="customRestrictionInput" placeholder="Add custom allergy..." style="flex:1; padding:0.4rem; border:1px solid var(--border); border-radius:6px; font-size:0.75rem;">
                    <button type="button" class="btn-m3 btn-m3-outline" onclick="addCustomRestriction('addStudentForm', 'customRestrictionInput')" style="padding:4px 12px; font-size:0.7rem;">+ Add</button>
                </div>
                <p style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem;">Select all that apply or add a new one.</p>
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
                        <select name="ns_status"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                            <option value="Normal">Normal</option>
                            <option value="Wasted">Wasted</option>
                            <option value="Severely Wasted">Severely Wasted</option>
                            <option value="Overweight">Overweight</option>
                            <option value="Obese">Obese</option>
                        </select>
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">HFA
                            Status</label>
                        <select name="hfa_status"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                            <option value="Normal">Normal</option>
                            <option value="Stunted">Stunted</option>
                            <option value="Severely Stunted">Severely Stunted</option>
                            <option value="Tall">Tall</option>
                        </select>
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
                <button type="submit" class="btn" style="background: var(--primary); color: white;">Register
                    Student</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Assessment Modal -->
<div class="modal-overlay" id="addAssessmentModal">
    <div class="modal" style="max-width: 400px; width: 95%;">
        <h2 class="modal-title">New Nutritional Record</h2>
        <form id="assessmentForm">
            <input type="hidden" id="assessLrn" name="student_id">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Height
                    (cm)</label>
                <input type="number" step="0.1" id="assess_height" name="height" required
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Weight
                    (kg)</label>
                <input type="number" step="0.1" id="assess_weight" name="weight" required
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                <label
                    style="display:block; font-size: 0.75rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">Calculated
                    Target Weight</label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <input type="number" step="0.1" name="min_target_weight" id="assess_min_target_weight"
                        placeholder="Min" readonly
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; font-weight:700;">
                    <input type="number" step="0.1" name="max_target_weight" id="assess_max_target_weight"
                        placeholder="Max" readonly
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; background: #f1f5f9; font-weight:700;">
                </div>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Assessment
                    Date</label>
                <input type="date" name="assessment_date" value="<?php echo date('Y-m-d'); ?>" required
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel"
                    onclick="document.getElementById('addAssessmentModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn" style="background: var(--primary); color: white;">Save Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Assessment Modal -->
<div class="modal-overlay" id="editAssessmentModal">
    <div class="modal" style="max-width: 400px; width: 95%;">
        <h2 class="modal-title">Edit Nutritional Record</h2>
        <form id="editAssessmentForm">
            <input type="hidden" id="editRecordId" name="record_id">
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Height
                    (cm)</label>
                <input type="number" step="0.1" id="editHeight" name="height" required
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Weight
                    (kg)</label>
                <input type="number" step="0.1" id="editWeight" name="weight" required
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Assessment
                    Date</label>
                <input type="date" id="editDate" name="assessment_date" required
                    style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel"
                    onclick="document.getElementById('editAssessmentModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn" style="background: var(--primary); color: white;">Update
                    Record</button>
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
                <?php if ($role === 'Admin'): ?>
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
                <?php if ($role === 'Admin'): ?>
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
    function filterSectionsByGrade(grade) {
        // Update button states
        document.querySelectorAll('.grade-filter-btn').forEach(btn => {
            if (btn.innerText === grade) btn.classList.add('active');
            else btn.classList.remove('active');
        });

        // Filter Cards
        document.querySelectorAll('.section-tab').forEach(tab => {
            const tabGrade = tab.getAttribute('data-grade');
            if (grade === 'All' || tabGrade === grade || tabGrade === 'All') {
                tab.style.display = '';

                // Update the link to keep the grade filter persistent
                let url = new URL(tab.href);
                url.searchParams.set('grade_filter', grade);
                tab.href = url.toString();
            } else {
                tab.style.display = 'none';
            }
        });
    }

    // Map the HTML call to our function
    window.filterByGrade = filterSectionsByGrade;

    // Persist filter on load
    window.addEventListener('load', () => {
        const urlParams = new URLSearchParams(window.location.search);
        const gradeFilter = urlParams.get('grade_filter') || 'All';
        filterSectionsByGrade(gradeFilter);
    });

    // Filter Logic
    function filterTable() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const bmi = document.getElementById('bmiFilter').value;
        let visibleCount = 0;

        document.querySelectorAll('.student-row').forEach(row => {
            const rowLrn = row.getAttribute('data-lrn').toLowerCase();
            const rowName = row.getAttribute('data-name');
            const rowBmi = row.getAttribute('data-bmi');

            let matchSearch = search === '' || rowName.includes(search) || rowLrn.includes(search);
            let matchBmi = bmi === '' || rowBmi === bmi;

            if (matchSearch && matchBmi) {
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
        document.getElementById('editAssessmentModal').classList.add('active');
    }

    document.getElementById('bmiFilter').addEventListener('change', filterTable);

    const isAdmin = <?php echo json_encode($role === 'Admin'); ?>;

    let progressChart = null;
    let currentStudentData = null; // Global tracker for editing

    function closeChartModal() {
        document.getElementById('chartModal').classList.remove('active');
    }

    function calculateTargetWeights(height, minEl, maxEl) {
        if (!height || height <= 0) {
            minEl.value = '';
            maxEl.value = '';
            return;
        }
        const min = 18.5 * Math.pow(height / 100, 2);
        const max = 24.9 * Math.pow(height / 100, 2);
        minEl.value = min.toFixed(1);
        maxEl.value = max.toFixed(1);
    }

    // Attach listeners to various height fields
    document.addEventListener('input', function (e) {
        if (e.target.name === 'init_height' || e.target.id === 'init_height_enroll') {
            const form = e.target.closest('form');
            const min = form.querySelector('[name="min_target_weight"]');
            const max = form.querySelector('[name="max_target_weight"]');
            if (min && max) calculateTargetWeights(e.target.value, min, max);
        }
        if (e.target.name === 'height' && e.target.id === 'assess_height') {
            const min = document.getElementById('assess_min_target_weight');
            const max = document.getElementById('assess_max_target_weight');
            if (min && max) calculateTargetWeights(e.target.value, min, max);
        }
    });

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
                <td style="padding: 0.5rem; border-top: 1px solid var(--border); gap: 0.5rem; display: ${isAdmin ? 'flex' : 'none'};">
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
            document.getElementById('addAssessmentModal').classList.add('active');
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

    // BMI Real-time Assistant
    function updateNutritionalStatusRealtime(formSelector, hName, wName, targetName) {
        const form = document.querySelector(formSelector);
        if (!form) return;

        const hIn = form.querySelector(`[name="${hName}"]`);
        const wIn = form.querySelector(`[name="${wName}"]`);
        const select = form.querySelector(`[name="${targetName}"]`);

        if (!hIn || !wIn || !select) return;

        const calculate = () => {
            const h = parseFloat(hIn.value);
            const w = parseFloat(wIn.value);
            if (!h || !w || h <= 0) return;

            const bmi = w / Math.pow(h / 100, 2);
            let status = 'Normal';
            if (bmi < 16) status = 'Severely Wasted';
            else if (bmi < 18.5) status = 'Wasted';
            else if (bmi >= 25 && bmi < 30) status = 'Overweight';
            else if (bmi >= 30) status = 'Obese';

            select.value = status;
        };

        hIn.addEventListener('input', calculate);
        wIn.addEventListener('input', calculate);
    }

    document.addEventListener('DOMContentLoaded', () => {
        // For Register Student Modal
        updateNutritionalStatusRealtime('#addStudentForm', 'init_height', 'init_weight', 'ns_status');
        // For New Assessment Modal
        updateNutritionalStatusRealtime('#assessmentForm', 'height', 'weight', 'ns_status');
        // For Edit Assessment Modal
        updateNutritionalStatusRealtime('#editAssessmentForm', 'height', 'weight', 'ns_status');
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
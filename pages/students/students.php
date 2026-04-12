<?php
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../db.php'; // Include the database connection

$page_title = 'Students';
require_once '../../includes/topbar.php';

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

    .btn-outline {
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--text-main);
        padding: 0.5rem 1rem;
        border-radius: 4px;
        font-weight: 500;
        font-size: 0.875rem;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        text-decoration: none;
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
        background: none;
        border: none;
        color: var(--text-muted);
        cursor: pointer;
        padding: 0.25rem;
        border-radius: 50%;
    }

    .btn-icon:hover {
        background-color: var(--bg-color);
        color: var(--text-main);
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
            <a href="export_students.php?section=<?php echo urlencode($active_section); ?>" class="btn-outline"><span
                    class="material-icons" style="font-size: 16px;">file_download</span> Export List</a>
            <?php if ($role === 'Admin'): ?>
            <button class="btn" style="display: flex; align-items: center; gap: 0.5rem;"
                onclick="document.getElementById('addStudentModal').classList.add('active')"><span
                    class="material-icons" style="font-size: 16px;">add</span> Add New Student</button>
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
                <input type="text" id="searchInput" placeholder="Search students, ID Number..."

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
                        <th>Dietary Restrictions</th>
                        <th>BMI & Target Weight</th>
                        <th>Weight Gain</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--text-muted); padding: 2rem;">No students
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
                                            <div class="student-name-link" style="text-decoration: none; color: inherit; font-weight: 700;">
                                                <?php echo htmlspecialchars($st['first_name'] . ' ' . $st['last_name']); ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.1rem;">
                                                <?php echo $st['current_height'] ?: '--'; ?> cm •
                                                <?php echo $st['current_weight'] ?: '--'; ?> kg
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span
                                        style="font-size: 0.875rem; <?php echo $isAllergic ? 'color: #d93025; font-weight: 500;' : 'color: var(--text-muted);'; ?>">
                                        <?php echo htmlspecialchars($allergens); ?>
                                    </span>
                                </td>
                                <td>
                                    <span
                                        class="bmi-value"><?php echo $calculated_bmi ? number_format($calculated_bmi, 1) : '--'; ?></span>
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
                                    <div style="display: flex; flex-direction: column; gap: 0.25rem;">
                                        <button class="btn-text show-progress-btn"
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
                                            style="font-size: 0.75rem; padding: 0.25rem 0.5rem; text-transform:none; border: 1px solid var(--border); border-radius:4px;">Show
                                            Progress</button>
                                        <button class="btn-text add-assessment-btn"
                                            data-student_id="<?php echo htmlspecialchars($st['student_id']); ?>"
                                            data-height="<?php echo $st['current_height'] ?? ''; ?>"
                                            data-weight="<?php echo $st['current_weight'] ?? ''; ?>"
                                            data-min-target="<?php echo $st['min_target_weight']; ?>"
                                            data-max-target="<?php echo $st['max_target_weight']; ?>"
                                            style="font-size: 0.75rem; padding: 0.25rem 0.5rem; text-transform:none; border: 1px solid var(--border); border-radius:4px; background:var(--bg-color);">Add
                                            Assessment</button>
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
                    <label
                        style="display:flex; justify-content:space-between; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">
                        Min Target (kg)
                        <a href="#" onclick="suggestTargetWeightAssessment(); return false;"
                            style="color: var(--primary); font-size: 0.75rem; font-weight: 700;">SUGGEST</a>
                    </label>
                    <input type="number" step="0.1" name="min_target_weight" id="assess_min_target_weight"
                        placeholder="Min"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Max
                        Target (kg)</label>
                    <input type="number" step="0.1" name="max_target_weight" id="assess_max_target_weight"
                        placeholder="Max"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
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
                <p style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.25rem;">Select all that apply.</p>
            </div>

            <div style="margin-top: 1rem; padding: 1rem; background: var(--bg-color); border-radius: 8px;">
                <label
                    style="display:block; font-size: 0.875rem; font-weight: 700; color: var(--primary); margin-bottom: 0.75rem;">Initial
                    Assessment (Optional)</label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <div>
                        <label
                            style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Weight
                            (kg)</label>
                        <input type="number" step="0.1" name="init_weight" id="init_weight_enroll"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    </div>
                    <div>
                        <label
                            style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Height
                            (cm)</label>
                        <input type="number" step="0.1" name="init_height" id="init_height_enroll"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    </div>
                </div>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-top: 0.75rem;">
                    <div>
                        <label
                            style="display:flex; justify-content:space-between; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">
                            Min Target (kg)
                            <a href="#" onclick="suggestTargetWeight(); return false;"
                                style="color: var(--primary); font-size: 0.65rem; font-weight: 700;">SUGGEST</a>
                        </label>
                        <input type="number" step="0.1" name="min_target_weight" id="min_target_weight_enroll"
                            value="0.0"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Max
                            Target (kg)</label>
                        <input type="number" step="0.1" name="max_target_weight" id="max_target_weight_enroll"
                            value="0.0"
                            style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
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
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Height (cm)</label>
                <input type="number" step="0.1" id="assess_height" name="height" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Weight (kg)</label>
                <input type="number" step="0.1" id="assess_weight" name="weight" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div style="margin-bottom: 1rem; border-top: 1px solid var(--border); padding-top: 1rem;">
                <label style="display:flex; justify-content:space-between; font-size: 0.75rem; font-weight: 700; color: var(--primary); margin-bottom: 0.5rem;">
                    Target Weight (Optional)
                    <a href="#" onclick="suggestTargetWeightAssessment(); return false;" style="font-size: 0.65rem;">SUGGEST</a>
                </label>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                    <input type="number" step="0.1" name="min_target_weight" id="assess_min_target_weight" placeholder="Min" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                    <input type="number" step="0.1" name="max_target_weight" id="assess_max_target_weight" placeholder="Max" style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
            </div>
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 500; margin-bottom: 0.5rem;">Assessment Date</label>
                <input type="date" name="assessment_date" value="<?php echo date('Y-m-d'); ?>" required style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="document.getElementById('addAssessmentModal').classList.remove('active')">Cancel</button>
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
                    <label style="display:flex; justify-content:space-between; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">
                        Min Target Weight (kg)
                        <a href="#" onclick="suggestTargetWeightEdit(); return false;"
                            style="color: var(--primary); font-size: 0.75rem; font-weight: 700;">SUGGEST</a>
                    </label>
                    <input type="number" step="0.1" name="min_target_weight" id="edit_min_target"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 500; margin-bottom: 0.25rem;">Max
                        Target Weight (kg)</label>
                    <input type="number" step="0.1" name="max_target_weight" id="edit_max_target"
                        style="width: 100%; padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px;">
                </div>
            </div>

            <!-- SBFP Indicators Removed -->


            <div class="modal-actions" style="display: flex; justify-content: space-between; align-items: center; margin-top: 2rem;">
                <button type="button" class="btn" onclick="deleteStudentAdmin()" 
                        style="background: none; border: 1px solid #d93025; color: #d93025; padding: 0.5rem 1rem; border-radius: 4px; display: flex; align-items: center; gap: 0.5rem; cursor: pointer; transition: background 0.2s;">
                    <span class="material-icons" style="font-size: 18px;">delete</span>
                    Delete Student
                </button>
                <div style="display: flex; gap: 1rem;">
                    <button type="button" class="btn-cancel"
                        onclick="document.getElementById('editStudentModal').classList.remove('active')">Cancel</button>
                    <button type="submit" class="btn" style="background: var(--primary); color: white; padding: 0.5rem 1.5rem; border-radius: 4px;">Save Changes</button>
                </div>
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
        const meal = document.getElementById('mealFilter').value;

        document.querySelectorAll('.student-row').forEach(row => {
            const rowLrn = row.getAttribute('data-lrn').toLowerCase();
            const rowName = row.getAttribute('data-name');
            const rowBmi = row.getAttribute('data-bmi');

            let matchSearch = search === '' || rowName.includes(search) || rowLrn.includes(search);
            let matchBmi = bmi === '' || rowBmi === bmi;

            if (matchSearch && matchBmi) {
                row.style.display = '';
                visibleCount++;      } else {
                row.style.display = 'none';
            }
        });
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

    function suggestTargetWeightAssessment() {
        const h = document.getElementById('assess_height').value;
        if (!h || h <= 0) {
            alert("Please enter Height first to calculate target weights.");
            return;
        }
        const min = 18.5 * Math.pow(h / 100, 2);
        const max = 24.9 * Math.pow(h / 100, 2);
        document.getElementById('assess_min_target_weight').value = min.toFixed(1);
        document.getElementById('assess_max_target_weight').value = max.toFixed(1);
    }

    function suggestTargetWeight() {
        const h = document.getElementById('init_height_enroll').value;
        if (!h || h <= 0) {
            alert("Please enter Height first to calculate target weights.");
            return;
        }
        const min = 18.5 * Math.pow(h / 100, 2);
        const max = 24.9 * Math.pow(h / 100, 2);
        document.getElementById('min_target_weight_enroll').value = min.toFixed(1);
        document.getElementById('max_target_weight_enroll').value = max.toFixed(1);
    }

    function suggestTargetWeightEdit() {
        const h = currentStudentData ? currentStudentData.height : null;
        if (!h || h <= 0) {
            alert("No height record found for this student. Please add a Nutritional Assessment first to calculate target weights.");
            return;
        }
        const min = 18.5 * Math.pow(h / 100, 2);
        const max = 24.9 * Math.pow(h / 100, 2);
        document.getElementById('edit_min_target').value = min.toFixed(1);
        document.getElementById('edit_max_target').value = max.toFixed(1);
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
                <td style="padding: 0.5rem; border-top: 1px solid var(--border); gap: 0.5rem; display: ${isAdmin ? 'flex' : 'none'};">
                    <button class="btn-icon" onclick="openEditAssessmentModal(${item.record_id}, ${item.height}, ${item.weight}, '${item.accurate_date}')" style="color: var(--primary); background: none; border: none; cursor: pointer;"><span class="material-icons" style="font-size: 16px;">edit</span></button>
                    <button class="btn-icon" onclick="deleteRecord(${item.record_id})" style="color: #d93025; background: none; border: none; cursor: pointer;"><span class="material-icons" style="font-size: 16px;">delete</span></button>
                </td>
            `;
            tbody.appendChild(tr);

        });

        // Chart render
        const labels = history.map(item => item.date);
        const bmiData = history.map(item => item.bmi);
        const heights = history.map(item => item.height);
        const weights = history.map(item => item.weight);
        const minBMILine = history.map(() => 18.5);
        const maxBMILine = history.map(() => 24.9);

        const ctx = document.getElementById('bmiChart').getContext('2d');
        if (progressChart) progressChart.destroy();

        progressChart = new Chart(ctx, {
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
    if (mainEditStudentBtn) { mainEditStudentBtn.addEventListener('click', function () {
        if (!currentStudentData) return;

        // Fill Edit Modal
        document.getElementById('edit_original_lrn').value = currentStudentData.id;
        document.getElementById('edit_student_id').value = currentStudentData.id;
        document.getElementById('edit_first_name').value = currentStudentData.fname;
        document.getElementById('edit_last_name').value = currentStudentData.lname;
        document.getElementById('edit_birth_date').value = currentStudentData.birth;
        document.getElementById('edit_sex').value = currentStudentData.sex;
        document.getElementById('edit_grade_level').value = currentStudentData.grade;

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
    }); } // end if (mainEditStudentBtn)

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

    window.deleteStudentAdmin = function() {
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
</script>

<?php require_once '../../includes/footer.php'; ?>
<?php 
require_once '../../includes/header.php';
require_once '../../includes/sidebar.php';
require_once '../../db.php';

$page_title = 'Status Dashboard';
require_once '../../includes/topbar.php';

// --- DATA FETCHING ---
// 1. Total Enrolled
$total_q = $conn->query("SELECT COUNT(*) as count FROM student");
$total_students = $total_q->fetch_assoc()['count'];

// 2. Underweight Calculation (Latest record per student)
$uw_sql = "
    SELECT COUNT(*) as uw_count FROM (
        SELECT s.student_id,
        (SELECT weight / pow(height/100, 2) FROM nutritional_record WHERE student_id = s.student_id ORDER BY assessment_date DESC LIMIT 1) as latest_bmi
        FROM student s
    ) as health_status WHERE latest_bmi < 18.5
";
$uw_q = $conn->query($uw_sql);
$underweight_count = $uw_q->fetch_assoc()['uw_count'];
$uw_percentage = $total_students > 0 ? round(($underweight_count / $total_students) * 100, 1) : 0;

// 3. Recent Activity (Historical items)
$activity_sql = "
    SELECT nr.*, s.first_name, s.last_name 
    FROM nutritional_record nr 
    JOIN student s ON nr.student_id = s.student_id 
    ORDER BY nr.assessment_date DESC LIMIT 5
";
$activity_res = $conn->query($activity_sql);

?>

<div class="content">
    <!-- Header Analytics -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 1.5rem; margin-bottom: 2rem;">
        
        <div class="section-card" style="margin-bottom: 0; padding: 1.5rem; position: relative; overflow: hidden;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Total Enrolled</div>
            <div style="font-size: 2rem; font-weight: 800; margin-top: 0.5rem; color: var(--text-main);"><?php echo number_format($total_students); ?></div>
            <div style="font-size: 0.75rem; color: var(--success); margin-top: 0.25rem;">↗ 24 new this month</div>
            <span class="material-icons" style="position:absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.05; color: var(--primary);">school</span>
        </div>

        <div class="section-card" style="margin-bottom: 0; padding: 1.5rem; position: relative; overflow: hidden;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Underweight Rate</div>
            <div style="font-size: 2rem; font-weight: 800; margin-top: 0.5rem; color: var(--warning);"><?php echo $uw_percentage; ?>%</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;"><?php echo $underweight_count; ?> Students at risk</div>
            <span class="material-icons" style="position:absolute; right: -10px; bottom: -10px; font-size: 5rem; opacity: 0.05; color: var(--warning);">monitor_weight</span>
        </div>

        <div class="section-card" style="margin-bottom: 0; padding: 1.5rem; position: relative; overflow: hidden;">
            <div style="font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Program Progress</div>
            <div style="font-size: 2rem; font-weight: 800; margin-top: 0.5rem; color: var(--success);">78%</div>
            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">Target: 90% by EOFY</div>
            <div style="height: 4px; background: #eee; border-radius: 2px; margin-top: 0.75rem;">
                <div style="height: 100%; width: 78%; background: var(--success); border-radius: 2px;"></div>
            </div>
        </div>

    </div>

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        <!-- Core Rehab Chart -->
        <div class="section-card">
            <div class="section-header" style="margin-bottom: 1.5rem;">
                <h3 class="section-title">Nutritional Health Trends</h3>
                <div style="color: var(--text-muted); font-size: 0.8rem;">Historical BMI distribution across campus</div>
            </div>
            <div style="height: 300px;">
                <canvas id="mainDashboardChart"></canvas>
            </div>
        </div>

        <!-- Recent Activity Sidebar -->
        <div class="section-card">
            <h3 class="section-title" style="margin-bottom: 1.5rem;">Recent Activity</h3>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php while($act = $activity_res->fetch_assoc()): ?>
                <div style="display:flex; gap: 0.75rem; align-items: flex-start; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem;">
                    <div style="background: var(--secondary); padding: 0.4rem; border-radius: 6px; color: var(--primary);">
                        <span class="material-icons" style="font-size: 18px;">assignment_ind</span>
                    </div>
                    <div>
                        <div style="font-size: 0.85rem; font-weight: 700;"><?php echo $act['first_name'] . ' ' . $act['last_name']; ?></div>
                        <div style="font-size: 0.75rem; color: var(--text-muted);"><?php echo date('M d, Y', strtotime($act['assessment_date'])); ?></div>
                        <div style="font-size: 0.75rem; margin-top: 0.2rem;">Updated Weight: <span style="font-weight:700;"><?php echo $act['weight']; ?> kg</span></div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </div>
    </div>
</div>

<script>
const ctx = document.getElementById('mainDashboardChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
            label: 'Normal BMI %',
            data: [65, 68, 70, 72, 75, 78],
            borderColor: '#0061ff',
            backgroundColor: 'rgba(0, 97, 255, 0.1)',
            fill: true,
            tension: 0.4,
            borderWidth: 3,
            pointRadius: 0,
            pointHoverRadius: 5
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { grid: { display: false } },
            y: { grid: { borderDash: [5, 5] }, suggestedMin: 50, suggestedMax: 100 }
        }
    }
});
</script>

<?php require_once '../../includes/footer.php'; ?>

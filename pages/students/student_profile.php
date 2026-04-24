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
$history_res = $conn->query("SELECT * FROM nutritional_record WHERE student_id = '$student_id' ORDER BY assessment_date DESC");

// Fetch Diet Restrictions
$res_stmt = $conn->prepare("SELECT dr.restriction_name FROM student_allergy_map sam JOIN dietary_restrictions dr ON sam.restriction_id = dr.restriction_id WHERE sam.student_id = ?");
$res_stmt->bind_param("s", $student_id);
$res_stmt->execute();
$restrictions_res = $res_stmt->get_result();
$allergies = [];
while($r = $restrictions_res->fetch_assoc()) $allergies[] = $r['restriction_name'];

$page_title = 'Student Profile';
require_once '../../includes/topbar.php';

// Prepare Chart Data
$chart_labels = [];
$chart_bmis = [];
$chart_raw_data = [];

// Create a copy of the history for the chart
$chart_res = $conn->query("SELECT assessment_date, weight, height FROM nutritional_record WHERE student_id = '$student_id' ORDER BY assessment_date ASC");
while($c = $chart_res->fetch_assoc()) {
    $bmi = ($c['height'] > 0) ? round($c['weight'] / pow($c['height']/100, 2), 1) : 0;
    $chart_labels[] = date('M d, Y', strtotime($c['assessment_date']));
    $chart_bmis[] = $bmi;
    $chart_raw_data[] = ['x' => strtotime($c['assessment_date']), 'y' => $bmi];
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
            <button class="btn-m3 btn-m3-outline" onclick="window.history.back()">
                <span class="material-icons" style="font-size: 18px;">arrow_back</span>
                <span>Back to Roster</span>
            </button>
            <button class="btn-m3 btn-m3-primary" onclick="window.print()">
                <span class="material-icons" style="font-size: 18px;">print</span>
                <span>Print Profile</span>
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

            <!-- Growth Chart -->
            <div class="dashboard-card" style="padding: 1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                    <h3 style="margin: 0 0 1.5rem 0; font-size: 1rem; border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; flex:1;">Growth Analysis Line Graph</h3>
                    <div id="predictionInsight" style="display:none; text-align:right;">
                        <span class="badge" style="background:#ecfdf5; color:#059669; font-weight:800; padding:6px 12px; border-radius:8px;">
                            <span class="material-icons" style="font-size:14px; vertical-align:middle;">speed</span>
                            <span id="recoveryTimeDisplay">Calculating recovery...</span>
                        </span>
                    </div>
                </div>
                <div style="height: 300px;">
                    <canvas id="growthChart"></canvas>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($h = $history_res->fetch_assoc()): ?>
                        <tr style="border-bottom: 1px solid var(--border);">
                            <td style="padding: 0.75rem 0.5rem; font-weight: 800;"><?= date('M d, Y', strtotime($h['assessment_date'])) ?></td>
                            <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);"><?= $h['age_years'] ?>Y, <?= $h['age_months'] ?>M</td>
                            <td style="padding: 0.75rem 0.5rem; font-weight: 700;"><?= $h['weight'] ?> kg</td>
                            <td style="padding: 0.75rem 0.5rem; font-weight: 700;"><?= $h['height'] ?> cm</td>
                            <td style="padding: 0.75rem 0.5rem;">
                                <span style="font-weight: 800; color: <?= $h['nutritional_status'] === 'Normal' ? '#059669' : '#dc2626' ?>;"><?= $h['nutritional_status'] ?></span>
                            </td>
                            <td style="padding: 0.75rem 0.5rem; color: var(--text-muted);"><?= $h['hfa_status'] ?: '---' ?></td>
                        </tr>
                        <?php endwhile; if($history_res->num_rows === 0): ?>
                            <tr><td colspan="6" style="padding: 3rem; text-align:center; color:var(--text-muted);">No assessments recorded yet.</td></tr>
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
        const rawData = <?= json_encode($chart_raw_data) ?>;
        const labels = <?= json_encode($chart_labels) ?>;
        
        if (labels.length === 0) {
            ctx.font = "14px Inter";
            ctx.fillStyle = "#94a3b8";
            ctx.textAlign = "center";
            ctx.fillText("No historical biometric data available for analysis.", ctx.canvas.width/2, ctx.canvas.height/2);
            return;
        }

        // --- PREDICTIVE ENGINE ---
        function predictNextPoints(data, daysOut = [30, 60, 90]) {
            if (data.length < 2) return [];
            const n = data.length;
            let sumX = 0, sumY = 0, sumXY = 0, sumXX = 0;
            const firstX = data[0].x;
            for (let i = 0; i < n; i++) {
                const x = (data[i].x - firstX) / (24 * 60 * 60); // Days since start
                sumX += x; sumY += data[i].y;
                sumXY += x * data[i].y; sumXX += x * x;
            }
            const m = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);
            const b = (sumY - m * sumX) / n;
            
            const lastX = (data[data.length-1].x - firstX) / (24 * 60 * 60);
            return daysOut.map(d => {
                const targetDay = lastX + d;
                const date = new Date(data[data.length-1].x * 1000);
                date.setDate(date.getDate() + d);
                return {
                    label: "Predicted: " + date.toLocaleDateString('en-US', {month:'short', day:'numeric'}),
                    val: parseFloat((m * targetDay + b).toFixed(1))
                };
            });
        }

        const predictions = predictNextPoints(rawData);
        const predictionLabels = predictions.map(p => p.label);
        const predictionValues = [<?= end($chart_bmis) ?: 0 ?>, ...predictions.map(p => p.val)];
        
        // --- RECOVERY ESTIMATION ---
        (function calculateRecovery() {
            if (rawData.length < 2) return;
            
            const n = rawData.length;
            let sumX = 0, sumY = 0, sumXY = 0, sumXX = 0;
            const firstX = rawData[0].x;
            for (let i = 0; i < n; i++) {
                const x = (rawData[i].x - firstX) / (24 * 60 * 60);
                sumX += x; sumY += rawData[i].y;
                sumXY += x * rawData[i].y; sumXX += x * x;
            }
            const m = (n * sumXY - sumX * sumY) / (n * sumXX - sumX * sumX);
            const b = (sumY - m * sumX) / n;
            
            const currentBMI = rawData[rawData.length-1].y;
            let targetBMI = 0;
            let verb = "";
            
            if (currentBMI < 18.5 && m > 0) {
                targetBMI = 18.5;
                verb = "Normal Coverage";
            } else if (currentBMI > 24.9 && m < 0) {
                targetBMI = 24.9;
                verb = "Target Stability";
            }

            if (targetBMI > 0) {
                const lastX = (rawData[rawData.length-1].x - firstX) / (24 * 60 * 60);
                const targetX = (targetBMI - b) / m;
                const daysLimit = Math.ceil(targetX - lastX);
                
                if (daysLimit > 0 && daysLimit < 730) { // Limit to 2 years projection
                    const targetDate = new Date();
                    targetDate.setDate(targetDate.getDate() + daysLimit);
                    document.getElementById('predictionInsight').style.display = 'block';
                    document.getElementById('recoveryTimeDisplay').innerText = `Est. ${daysLimit} days to reach ${verb} (${targetDate.toLocaleDateString()})`;
                }
            }
        })();

        // Extend labels for prediction
        const fullLabels = [...labels, ...predictionLabels];
        const actualData = [...<?= json_encode($chart_bmis) ?>];
        const predictedDataset = Array(actualData.length - 1).fill(null).concat(predictionValues);
        const minBMILine = fullLabels.map(() => 18.5);
        const maxBMILine = fullLabels.map(() => 24.9);

        new Chart(ctx, {
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
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', labels: { usePointStyle: true, font: { weight: 'bold' } } },
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
    });
</script>

<?php require_once '../../includes/footer.php'; ?>

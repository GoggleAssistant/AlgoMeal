<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<?php
$page_title = 'Nutritional Records & Monitoring';
require_once '../../includes/topbar.php';
?>

        <div class="content">
            <div class="section-card">
                <div class="section-header">
                    <h3 class="section-title">Longitudinal Progress Monitoring</h3>
                    <button class="btn"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">add</span> New Assessment</button>
                </div>
                
                <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
                    Record biometric data from digital scales to generate updated BMI classifications and evaluate student rehabilitation progress.
                </p>

                <div style="margin-bottom: 1rem; display: flex; gap: 1rem;">
                    <input type="text" placeholder="Search Student ID..." style="padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; width: 300px;">
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Student</th>
                            <th>Height (cm)</th>
                            <th>Weight (kg)</th>
                            <th>Computed BMI</th>
                            <th>Classification Status</th>
                            <th>Delta</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Sept 01, 2026</td>
                            <td>LRN-100234 (Dela Cruz, J)</td>
                            <td>120 cm</td>
                            <td>18 kg</td>
                            <td>12.5</td>
                            <td><span class="badge warning">Severely Wasted</span></td>
                            <td><span style="color: var(--text-muted);">Baseline</span></td>
                        </tr>
                        <tr>
                            <td>Oct 01, 2026</td>
                            <td>LRN-100234 (Dela Cruz, J)</td>
                            <td>120.5 cm</td>
                            <td>19.2 kg</td>
                            <td>13.2</td>
                            <td><span class="badge" style="background-color: #fef7e0; color: #f29900;">Wasted</span></td>
                            <td><span style="color: var(--success); font-weight: 500;">+1.2 kg</span></td>
                        </tr>
                        <tr>
                            <td>Nov 01, 2026</td>
                            <td>LRN-100567 (Santos, M)</td>
                            <td>115 cm</td>
                            <td>20 kg</td>
                            <td>15.1</td>
                            <td><span class="badge success">Normal</span></td>
                            <td><span style="color: var(--success); font-weight: 500;">Target Reached</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

<?php require_once '../../includes/footer.php'; ?>

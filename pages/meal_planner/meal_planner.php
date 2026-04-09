<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<?php
$page_title = 'CSP Meal Planner (120-Day Cycle)';
require_once '../../includes/topbar.php';
?>

        <div class="content">
            <div class="overview-grid">
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Daily Budget Cap/Student</h3>
                        <p>₱ 20.00</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Min Energy Constraint</h3>
                        <p>300 kcal</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-info">
                        <h3>Min Protein Constraint</h3>
                        <p>12 g</p>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <h3 class="section-title">AI Optimization Engine</h3>
                    <button class="btn" style="background-color: var(--success);"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">auto_awesome</span> Generate 120-Day Plan</button>
                </div>
                <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 1.5rem;">
                    The Heuristic-Enhanced CSP solver will construct the optimal meal cycle avoiding defined allergens while satisfying nutritional and budget constraints.
                </p>

                <table>
                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Date Scheduled</th>
                            <th>Recipe Allocated</th>
                            <th>Est. Cost</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Day 1</td>
                            <td>Sept 01, 2026</td>
                            <td>REC001 - Chicken Arroz Caldo</td>
                            <td>₱ 18.50</td>
                            <td><span class="badge success">Served</span></td>
                        </tr>
                        <tr>
                            <td>Day 2</td>
                            <td>Sept 02, 2026</td>
                            <td>REC003 - Pork Picadillo</td>
                            <td>₱ 22.00</td>
                            <td><span class="badge warning">Over Budget Warning</span></td>
                        </tr>
                        <tr>
                            <td>Day 3</td>
                            <td>Sept 03, 2026</td>
                            <td>REC002 - Ginataang Munggo</td>
                            <td>₱ 14.00</td>
                            <td><span class="badge">Scheduled</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

<?php require_once '../../includes/footer.php'; ?>

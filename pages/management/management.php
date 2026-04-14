<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>
<?php
$page_title = 'System Management';
require_once '../../includes/topbar.php';
require_once '../../db.php';
$isAdmin = ($role === 'Admin');


// Fetch Settings
$res_settings = $conn->query("SELECT * FROM settings");
$settings = [];
while ($row = $res_settings->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$allocated_budget = (float) ($settings['total_allocated_budget'] ?? 500000.00);
$daily_limit = (float) ($settings['total_daily_budget'] ?? 0);

// Financial Sums
$meal_spent = (float) ($conn->query("SELECT SUM(mp.actual_cost) as total FROM meal_plan mp JOIN daily_meal_plans dmp ON mp.scheduled_date = dmp.scheduled_date WHERE dmp.is_served = 1")->fetch_assoc()['total'] ?? 0);
$logs_spent = (float) ($conn->query("SELECT SUM(amount) as total FROM budget_logs")->fetch_assoc()['total'] ?? 0);
$total_spent = $meal_spent + $logs_spent;
$remaining_funds = $allocated_budget - $total_spent;

// Categories for Chart
$categories = ['Food Supplies' => $meal_spent, 'Labor Costs' => 0, 'Logistics' => 0, 'Misc' => 0];
$res_cat = $conn->query("SELECT category, SUM(amount) as total FROM budget_logs GROUP BY category");
while ($row = $res_cat->fetch_assoc()) {
    if (isset($categories[$row['category']]))
        $categories[$row['category']] += (float) $row['total'];
    else
        $categories[$row['category']] = (float) $row['total'];
}

// Kitchen Documentation
$docs = $conn->query("SELECT kd.*, u.faculty_name as uploader FROM kitchen_documentation kd LEFT JOIN users u ON kd.uploaded_by = u.user_id ORDER BY kd.tagged_date DESC, kd.created_at DESC");

?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .mgmt-tabs {
        display: flex;
        gap: 0.75rem;
        margin-bottom: 2rem;
        padding-bottom: 1rem;
        border-bottom: 1px dashed var(--border);
        overflow-x: auto;
    }

    .tab-btn {
        padding: 0.6rem 1.5rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 100px;
        font-weight: 700;
        font-size: 0.85rem;
        color: var(--text-muted);
        cursor: pointer;
        transition: 0.2s;
        white-space: nowrap;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .tab-btn:hover {
        background: #f1f5f9;
        color: var(--text-main);
    }

    .tab-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px -2px rgba(59, 130, 246, 0.3);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
        animation: fadeIn 0.3s ease;
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: translateY(10px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
        color: var(--text-muted);
        font-weight: 700;
        font-size: 0.8rem;
        cursor: pointer;
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
        white-space: nowrap;
    }

    .tab-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        color: var(--text-main);
    }

    .tab-btn.active {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(0, 97, 255, 0.25);
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .doc-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 1.5rem;
    }

    .doc-card {
        background: white;
        border: 1px solid var(--border);
        border-radius: 12px;
        overflow: hidden;
        transition: 0.2s;
    }

    .doc-card:hover {
        transform: translateY(-4px);
        box-shadow: var(--shadow-md);
    }

    .doc-img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        background: #f1f5f9;
        border-bottom: 1px solid var(--border);
    }

    .doc-meta {
        padding: 1rem;
    }

    .doc-date {
        font-size: 0.7rem;
        font-weight: 800;
        color: var(--primary);
        text-transform: uppercase;
        margin-bottom: 0.25rem;
    }

    .doc-desc {
        font-size: 0.85rem;
        color: var(--text-main);
        font-weight: 600;
        line-height: 1.4;
        margin-bottom: 0.5rem;
    }

    .doc-uploader {
        font-size: 0.7rem;
        color: var(--text-muted);
        border-top: 1px solid #f1f5f9;
        padding-top: 0.5rem;
        display: flex;
        align-items: center;
        gap: 4px;
    }

    .attendance-grid {
        background: white;
        overflow-x: auto;
        border: 1px solid var(--border);
        border-radius: 12px;
    }

    .att-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.75rem;
    }

    .att-table th,
    .att-table td {
        border: 1px solid var(--border);
        padding: 0.5rem;
        text-align: center;
    }

    .att-table th {
        background: #f8fafc;
        font-weight: 800;
    }

    .att-table td.name {
        text-align: left;
        font-weight: 700;
        background: #fcfcfc;
        white-space: nowrap;
    }

    .att-status-s {
        color: #10b981;
        font-weight: 900;
    }

    .att-status-a {
        color: #f43f5e;
        font-weight: 900;
    }

    @media print {
        .no-print { display: none !important; }
        .tab-content { display: block !important; }
        
        /* Attendance Roster isolation */
        body.print-roster-only .sidebar,
        body.print-roster-only .topbar,
        body.print-roster-only .mgmt-header,
        body.print-roster-only .mgmt-tabs,
        body.print-roster-only .tab-content:not(#tab-attendance) {
            display: none !important;
        }

        body.print-roster-only .main-content {
            margin: 0 !important;
            padding: 0 !important;
        }

        /* SBFP Form Landscape enforcement */
        body.print-landscape {
            @page { size: landscape; margin: 5mm; }
        }
    }
</style>

<script>
    function printRosterIsolated() {
        document.body.classList.add('print-roster-only');
        window.print();
        setTimeout(() => { document.body.classList.remove('print-roster-only'); }, 500);
    }

    async function saveBudgetSettings() {
        const alloc = document.getElementById('setAllocBudget').value;
        const daily = document.getElementById('setDailyBudget').value;
        if (!alloc || !daily) return alert('Please fill in both budget fields.');
        try {
            const res = await fetch('api_save_settings.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ total_allocated_budget: alloc, total_daily_budget: daily })
            });
            const d = await res.json();
            if (d.success) {
                // Flash the button green briefly
                const btn = document.querySelector('[onclick="saveBudgetSettings()"]');
                btn.style.background = '#10b981';
                btn.textContent = '✓ Saved!';
                setTimeout(() => { btn.style.background = ''; btn.innerHTML = '<span class="material-icons" style="font-size:16px; vertical-align:middle;">save</span> Save Parameters'; location.reload(); }, 1200);
            } else { alert('Error: ' + d.message); }
        } catch (e) { alert('Network error saving settings.'); }
    }
</script>

<div class="content">
    <div class="mgmt-header no-print">
        <div class="mgmt-title">
            <h2>Administrative Control Hub</h2>
            <p>Strategic management of fiscal, operational, and documentation artifacts.</p>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <button class="btn-m3 btn-m3-outline" onclick="window.print()"><span class="material-icons">print</span>
                Print Page</button>
        </div>
    </div>

    <div class="mgmt-tabs no-print">
        <button
            class="tab-btn <?= (!isset($_GET['tab']) || $_GET['tab'] == 'fiscal') && !isset($_GET['page_offset']) ? 'active' : '' ?>"
            data-tab="fiscal" onclick="switchTab('fiscal')">FISCAL CONTROL</button>
        <button class="tab-btn <?= (isset($_GET['tab']) && $_GET['tab'] == 'docs') ? 'active' : '' ?>" data-tab="docs"
            onclick="switchTab('docs')">OPERATIONAL DOCS</button>
        <button class="tab-btn <?= (isset($_GET['tab']) && $_GET['tab'] == 'sbfp1') ? 'active' : '' ?>" data-tab="sbfp1"
            onclick="switchTab('sbfp1')">SBFP FORM 1</button>
        <button
            class="tab-btn <?= (isset($_GET['page_offset']) || (isset($_GET['tab']) && $_GET['tab'] == 'attendance')) ? 'active' : '' ?>"
            data-tab="attendance" data-manual="true" onclick="switchTab('attendance')">ATTENDANCE GRID</button>
    </div> <!-- TAB: FISCAL -->
    <div id="tab-fiscal"
        class="tab-content <?= (!isset($_GET['tab']) || $_GET['tab'] == 'fiscal') && !isset($_GET['page_offset']) ? 'active' : '' ?>">
        <div class="kpi-row no-print">
            <div class="kpi-card">
                <div class="kpi-label">Strategic Allocation</div>
                <div class="kpi-value">&#8369;<?= number_format($allocated_budget, 2) ?></div>
            </div>
            <div class="kpi-card">
                <div class="kpi-label">Cumulative Spend</div>
                <div class="kpi-value" style="color:var(--primary);">&#8369;<?= number_format($total_spent, 2) ?></div>
            </div>
            <div class="kpi-card <?= $remaining_funds < 50000 ? 'warning' : '' ?>">
                <div class="kpi-label">Liquidity Pool</div>
                <div class="kpi-value">&#8369;<?= number_format($remaining_funds, 2) ?></div>
            </div>
        </div>

        <div class="main-grid">
            <div style="display:flex; flex-direction:column; gap:1.5rem;">
                <div class="dashboard-card no-print">
                    <div class="card-header">
                        <span class="material-icons">pie_chart</span>
                        <h3>Budget Distribution</h3>
                    </div>
                    <div class="chart-container">
                        <canvas id="allocationChart"></canvas>
                    </div>
                </div>
                <div class="dashboard-card no-print">
                    <div class="card-header"><span class="material-icons">receipt_long</span>
                        <h3>Manual Log</h3>
                    </div>
                    <?php if ($isAdmin): ?>
                        <div class="logging-form">
                            <div class="form-grid">
                                <div class="form-field"><label>Amount</label><input type="number" id="logAmount"
                                        placeholder="0.00"></div>
                                <div class="form-field"><label>Category</label><select id="logCategory">
                                        <option>Labor Costs</option>
                                        <option>Logistics</option>
                                        <option>Misc</option>
                                    </select></div>
                            </div>
                            <div class="form-field" style="margin-bottom:1rem;"><label>Description</label><input type="text"
                                    id="logDesc"></div>
                            <button class="btn-m3 btn-m3-primary" style="width:100%; border-radius: 8px;"
                                onclick="submitLog()">Record Log</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="dashboard-card">
                <div class="card-header"><span class="material-icons">history</span>
                    <h3>Expediture Ledger</h3>
                </div>
                <table class="reports-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Category</th>
                            <th>Description</th>
                            <th style="text-align:right;">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $l = $conn->query("SELECT * FROM budget_logs ORDER BY created_at DESC LIMIT 8");
                        while ($r = $l->fetch_assoc()): ?>
                            <tr>
                                <td><?= date('M d', strtotime($r['created_at'])) ?></td>
                                <td><span
                                        class="budget-tag tag-<?= strtolower(explode(' ', $r['category'])[0]) ?>"><?= $r['category'] ?></span>
                                </td>
                                <td style="color:var(--text-muted);"><?= $r['description'] ?></td>
                                <td style="text-align:right; font-weight:700;">&#8369;<?= number_format($r['amount'], 2) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div> <!-- END TAB: FISCAL -->

    <!-- TAB: DOCS -->
    <div id="tab-docs" class="tab-content <?= (isset($_GET['tab']) && $_GET['tab'] == 'docs') ? 'active' : '' ?>">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;"
            class="no-print">
            <h3 style="margin:0;">Kitchen Photo Evidence</h3>
            <button class="btn-m3 btn-m3-primary" onclick="openUploadModal()"><span
                    class="material-icons">add_a_photo</span> Add Evidence</button>
        </div>

        <div class="doc-grid">
            <?php while ($d = $docs->fetch_assoc()): ?>
                <div class="doc-card">
                    <img src="../../<?= $d['photo_path'] ?>" class="doc-img">
                    <div class="doc-meta">
                        <div class="doc-date"><?= date('F d, Y', strtotime($d['tagged_date'])) ?></div>
                        <div class="doc-desc"><?= htmlspecialchars($d['caption']) ?></div>
                        <div class="doc-uploader"><span class="material-icons" style="font-size:12px;">person</span>
                            Uploader: <?= $d['uploader'] ?? 'Staff' ?></div>
                    </div>
                </div>
            <?php endwhile;
            if ($docs->num_rows === 0): ?>
                <div style="grid-column: 1/-1; text-align:center; padding:5rem; color:var(--text-muted);">No operational
                    evidence uploaded yet.</div>
            <?php endif; ?>
        </div>
    </div> <!-- END TAB: DOCS -->

    <!-- TAB: SBFP FORM 1 -->
    <div id="tab-sbfp1" class="tab-content <?= (isset($_GET['tab']) && $_GET['tab'] == 'sbfp1') ? 'active' : '' ?>">
        <div class="mgmt-header no-print" style="margin-bottom: 2rem;">
            <div class="mgmt-title">
                <h3
                    style="margin:0; color:var(--primary); font-family: 'Outfit', sans-serif; font-weight: 800; letter-spacing: -0.02em;">
                    SBFP FORM 1: Master List of Beneficiaries</h3>
                <p style="font-size: 0.8rem; font-weight: 600; color: var(--text-muted);">DepEd Standard Format for
                    Beneficiary Documentation.</p>
            </div>
            <div style="display:flex; gap: 0.75rem;">
                <a href="api_export_sbfp1.php" class="btn-m3 btn-m3-tonal">
                    <span class="material-icons">description</span>
                    Export to Excel
                </a>
                <button class="btn-m3 btn-m3-outline" onclick="printFormLandscape()">
                    <span class="material-icons">print</span>
                    Print Full Form (Landscape)
                </button>
            </div>
        </div>

        <div class="dashboard-card" style="padding: 0; overflow-x: auto; border: 2px solid #000; border-radius: 0;">
            <table
                style="width: 100%; border-collapse: collapse; font-size: 11px; font-family: 'Inter', sans-serif; white-space: nowrap; border: 1px solid #000;">
                <thead style="background: #f1f5f9; border-bottom: 2px solid #000;">
                    <tr style="text-align: center; text-transform: uppercase; font-weight: 800;">
                        <th style="padding: 10px; border: 1px solid #000;">Name</th>
                        <th style="padding: 10px; border: 1px solid #000;">Sex</th>
                        <th style="padding: 10px; border: 1px solid #000;">Grade /<br>Section</th>
                        <th style="padding: 10px; border: 1px solid #000;">Date of Birth<br>(MM/DD/YYYY)</th>
                        <th style="padding: 10px; border: 1px solid #000;">Date of Weighing
                            /<br>Measuring<br>(MM/DD/YYYY)</th>
                        <th style="padding: 10px; border: 1px solid #000;">Age in<br>Years / Months</th>
                        <th style="padding: 10px; border: 1px solid #000;">Weight (kg)</th>
                        <th style="padding: 10px; border: 1px solid #000;">Height (cm)</th>
                        <th style="padding: 10px; border: 1px solid #000; background: #e2e8f0;" colspan="2">Nutritional
                            Status (NS)</th>
                        <th style="padding: 10px; border: 1px solid #000; background: #fff7ed;">BMI for 6 y.o.<br>and
                            above<br>(yes or no)</th>
                        <th style="padding: 10px; border: 1px solid #000;">Parent's<br>consent for milk?</th>
                        <th style="padding: 10px; border: 1px solid #000;">Dewormed?</th>
                        <th style="padding: 10px; border: 1px solid #000;">Consent for<br>Participation</th>
                        <th style="padding: 10px; border: 1px solid #000;">In 4Ps<br>(yes or no)</th>
                    </tr>
                    <tr style="text-align: center; font-size: 9px; text-transform: uppercase;">
                        <th colspan="8" style="background:transparent; border:none;"></th>
                        <th style="padding: 5px; border: 1px solid #000;">BMI-A</th>
                        <th style="padding: 5px; border: 1px solid #000;">HFA</th>
                        <th colspan="5"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sql_f1 = "
                            SELECT s.*, 
                                nr.weight as w, 
                                nr.height as h, 
                                nr.nutritional_status as ns_bmi, 
                                nr.hfa_status as ns_hfa, 
                                nr.age_years as y, 
                                nr.age_months as am, 
                                nr.assessment_date as ad
                            FROM student s
                            LEFT JOIN nutritional_record nr ON nr.record_id = (
                                SELECT MAX(record_id) FROM nutritional_record WHERE student_id = s.student_id
                            )
                            ORDER BY s.last_name, s.first_name
                        ";
                    $res_f1 = $conn->query($sql_f1);

                    if ($res_f1 && $res_f1->num_rows > 0):
                        while ($r = $res_f1->fetch_assoc()):
                            $isSix = ($r['ad'] && $r['y'] >= 6) ? 'Yes' : ($r['ad'] ? 'No' : '--');
                            ?>
                            <tr style="border-bottom: 1px solid #000;">
                                <td style="padding: 8px; border-right: 1px solid #000; font-weight: 700; color: #000;">
                                    <?= htmlspecialchars($r['last_name'] . ', ' . $r['first_name']) ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center;">
                                    <?= $r['sex'] == 'Female' ? 'F' : 'M' ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center;">
                                    <?= $r['grade_level'] ?> / <?= $r['section'] ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center;">
                                    <?= date('m/d/Y', strtotime($r['birth_date'])) ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center;">
                                    <?= $r['ad'] ? date('m/d/Y', strtotime($r['ad'])) : '--' ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center; font-weight: 600;">
                                    <?= $r['ad'] ? ($r['y'] . " Y / " . $r['am'] . " M") : '--' ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center;">
                                    <?= $r['w'] ?: '--' ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center;">
                                    <?= $r['h'] ?: '--' ?></td>
                                <td
                                    style="padding: 8px; border-right: 1px solid #000; text-align: center; font-weight: 700; color: var(--primary);">
                                    <?= $r['ns_bmi'] ?: '--' ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center;">
                                    <?= $r['ns_hfa'] ?: '--' ?></td>
                                <td
                                    style="padding: 8px; border-right: 1px solid #000; text-align: center; font-weight: 800; background: #fff7ed;">
                                    <?= $isSix ?></td>
                                <td
                                    style="padding: 8px; border-right: 1px solid #000; text-align: center; font-weight: 700; color: <?= $r['parent_milk_consent'] ? '#15803d' : '#be123c' ?>;">
                                    <?= $r['parent_milk_consent'] ? 'Yes' : 'No' ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center; font-weight: 700;">
                                    <?= $r['deworming_status'] ? 'Yes' : 'No' ?></td>
                                <td style="padding: 8px; border-right: 1px solid #000; text-align: center; font-weight: 700;">
                                    <?= $r['participation_consent'] ? 'Yes' : 'No' ?></td>
                                <td
                                    style="padding: 8px; text-align: center; font-weight: 700; color: <?= $r['is_4ps_beneficiary'] ? '#15803d' : '#000' ?>;">
                                    <?= $r['is_4ps_beneficiary'] ? 'Yes' : 'No' ?></td>
                            </tr>
                        <?php endwhile; else: ?>
                        <tr>
                            <td colspan="15" style="padding: 4rem; text-align: center; color: #94a3b8; font-weight: 700;">No
                                student records identified. Please register students in the roster.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="print-footer no-screen" style="margin-top: 6rem; display: none;">
            <div style="display: flex; justify-content: space-between; padding: 0 4rem;">
                <div style="text-align: center; width: 300px;">
                    <div style="border-bottom: 2px solid #000; margin-bottom: 10px; padding-top: 60px;"></div>
                    <div style="font-size: 11px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase;">
                        SBFP Coordinator / Teacher</div>
                    <div style="font-size: 10px; color: #64748b;">Date Prepared</div>
                </div>
                <div style="text-align: center; width: 300px;">
                    <div style="border-bottom: 2px solid #000; margin-bottom: 10px; padding-top: 60px;"></div>
                    <div style="font-size: 11px; font-weight: 900; letter-spacing: 0.05em; text-transform: uppercase;">
                        School Head / Principal</div>
                    <div style="font-size: 10px; color: #64748b;">Date Approved</div>
                </div>
            </div>
        </div>

        <style>
            @media print {
                .tab-content { display: none !important; }
                #tab-sbfp1 { 
                    display: block !important; 
                    width: 100% !important; 
                    border: none !important; 
                    transform: scale(0.85); 
                    transform-origin: top left;
                }
                .tab-btn, .kpi-row, .mgmt-tabs, .sidebar, .topbar, .no-print, .mgmt-header { display: none !important; }
                .dashboard-card { border: none !important; box-shadow: none !important; padding: 0 !important; }
                .no-screen { display: block !important; }
                body { background: white !important; font-family: 'Inter', sans-serif !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
                .content { padding: 0 !important; margin: 0 !important; max-width: 100% !important; }
                table { border: 2px solid #000 !important; width: 100% !important; font-size: 9px !important; }
                th, td { border: 1px solid #000 !important; padding: 3px !important; color: #000 !important; }
                thead { background: #f1f5f9 !important; }
                .print-footer { display: block !important; }
            }
        </style>
    </div> <!-- END TAB: SBFP FORM 1 -->

    <!-- TAB: ATTENDANCE -->
    <div id="tab-attendance"
        class="tab-content <?= (isset($_GET['tab']) && $_GET['tab'] == 'attendance') || isset($_GET['page_offset']) ? 'active' : '' ?>">
        <?php
        // Fetch ALL dates that were ACTUALLY VERIFIED AND SERVED chronologically
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
        <div class="no-print" style="margin-bottom:1.5rem;">
            <h3 style="margin:0 0 0.25rem 0;">Attendance Record</h3>
            <p style="margin:0; font-size:0.8rem; color:var(--text-muted);">Comprehensive tracking matrix of all
                verified meals.</p>
        </div>

        <div class="no-print" style="margin-bottom:1.5rem; display:flex; justify-content:flex-end; gap: 0.75rem;">
            <a href="api_export_attendance.php" class="btn-m3 btn-m3-tonal">
                <span class="material-icons">table_view</span>
                Export to Excel
            </a>
            <button class="btn-m3 btn-m3-outline" onclick="printRosterIsolated()"
                style="background:white; color:var(--text-main);">
                <span class="material-icons" style="font-size:18px; color:var(--primary);">print</span> 
                Print Roster
            </button>
        </div>

        <div class="attendance-grid"
            style="overflow-x:auto; margin-bottom:2rem; border-radius:12px; border:1px solid var(--border);">
            <table class="att-table" style="min-width: 100%;">
                <thead>
                    <tr>
                        <th
                            style="min-width:200px; border-left:none; vertical-align:bottom; position:sticky; left:0; background:#f8fafc; z-index:2; border-right:2px solid var(--border);">
                            Student Name</th>
                        <?php foreach ($days as $d): ?>
                            <th style="vertical-align:bottom; min-width:140px; background:#f8fafc;">
                                <div style="font-size:0.7rem; font-weight:800; color:var(--primary); margin-bottom:4px;">
                                    <?= date('D, M d Y', strtotime($d)) ?></div>
                                <div style="font-size:0.8rem; font-weight:700; color:var(--text-main); line-height:1.2;">
                                    <?= htmlspecialchars($dishes[$d]) ?></div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($s = $res_stud_att->fetch_assoc()): ?>
                        <tr>
                            <td class="name"
                                style="border-left:none; position:sticky; left:0; background:#ffffff; z-index:1; border-right:2px solid var(--border);">
                                <?= htmlspecialchars($s['full_name']) ?></td>
                            <?php foreach ($days as $d):
                                $char = '--';
                                $status = $meal_data[$s['student_id']][$d] ?? '';
                                if ($status === 'Served') {
                                    $char = '<div style="display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; background:#ecfdf5; color:#10b981; box-shadow:var(--shadow-sm);"><span class="material-icons" style="font-size:16px; font-weight:bold;">check</span></div>';
                                } elseif ($status === 'Absent') {
                                    $char = '<div style="display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:50%; background:#fef2f2; color:#ef4444; box-shadow:var(--shadow-sm);"><span class="material-icons" style="font-size:16px; font-weight:bold;">close</span></div>';
                                }
                                echo "<td style='background:#ffffff; text-align:center;'>$char</td>";
                            endforeach; ?>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <?php if (empty($days)): ?>
            <div
                style="text-align:center; padding:3rem; border:1px dashed var(--border); border-radius:12px; color:var(--text-muted); font-weight:700;">
                No meals have been verified and served yet.</div>
        <?php endif; ?>
    </div> <!-- END TAB: ATTENDANCE -->
</div> <!-- END .content --></div>



<script>
    // Allocation Chart
    document.addEventListener('DOMContentLoaded', () => {
        try {
            const canvas = document.getElementById('allocationChart');
            if (canvas) {
                const ctxAlloc = canvas.getContext('2d');
                new Chart(ctxAlloc, {
                    type: 'doughnut',
                    data: {
                        labels: ['Food Supplies', 'Labor Costs', 'Logistics', 'Misc'],
                        datasets: [{
                            data: [<?= $categories['Food Supplies'] ?>, <?= $categories['Labor Costs'] ?>, <?= $categories['Logistics'] ?>, <?= $categories['Misc'] ?>],
                            backgroundColor: ['#3b82f6', '#10b981', '#8b5cf6', '#f43f5e'],
                            borderWidth: 0,
                            cutout: '75%'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { legend: { display: false } }
                    }
                });
            }
        } catch (e) { console.error('Chart failed:', e); }
    });

    function switchTab(id) {
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('active');
            if (b.getAttribute('data-tab') === id) b.classList.add('active');
        });
        document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
        const target = document.getElementById('tab-' + id);
        if (target) target.classList.add('active');
    }

    function openUploadModal() {
        const body = `
            <div class="input-group"><label>Evidence Photo</label><input type="file" id="upFile" accept="image/*" style="width:100%;"></div>
            <div class="input-group"><label>Service Date</label><input type="date" id="upDate" value="<?= date('Y-m-d') ?>" style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:6px;"></div>
            <div class="input-group"><label>Context Description</label><input type="text" id="upDesc" placeholder="e.g., Preparing 150 servings of Sopas..." style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:6px;"></div>
        `;
        AlgoModal.show({
            title: 'Add Operational Evidence',
            body: body,
            footer: `<button class="btn-m3 btn-m3-outline" onclick="AlgoModal.close()">Cancel</button><button class="btn-m3 btn-m3-primary" onclick="runUpload()">Upload & Tag</button>`
        });
    }

    async function runUpload() {
        const file = document.getElementById('upFile').files[0];
        const date = document.getElementById('upDate').value;
        const desc = document.getElementById('upDesc').value;
        if (!file) return AlgoModal.alert('Missing Input', 'Please select a photo.');

        AlgoModal.close();
        const fd = new FormData();
        fd.append('photo', file);
        fd.append('tagged_date', date);
        fd.append('description', desc);

        try {
            const res = await fetch('api_upload_kitchen_v2.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) location.reload(); else AlgoModal.alert('Upload Error', data.message);
        } catch (e) { }
    }

    async function submitLog() {
        const amount = document.getElementById('logAmount').value;
        const category = document.getElementById('logCategory').value;
        const description = document.getElementById('logDesc').value;
        if (!amount || amount <= 0) return AlgoModal.alert('Invalid Amount', 'Please enter a valid amount.');
        try {
            const res = await fetch('api_log_budget.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ amount, category, description }) });
            const data = await res.json();
            if (data.success) location.reload(); else AlgoModal.alert('Error', data.message);
        } catch (e) { }
    }

</script>

<?php require_once '../../includes/footer.php'; ?>
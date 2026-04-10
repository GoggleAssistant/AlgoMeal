<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<?php
$page_title = 'Algorithm & Planning';
require_once '../../includes/topbar.php';
require_once '../../db.php';
$isAdmin = ($role === 'Admin');


// Get Recipes
$res_recipes = $conn->query("SELECT r.*, GROUP_CONCAT(DISTINCT dr.restriction_name) as allergens, GROUP_CONCAT(DISTINCT rat.restriction_id) as restriction_ids FROM recipes r LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id LEFT JOIN dietary_restrictions dr ON rat.restriction_id = dr.restriction_id GROUP BY r.recipe_id ORDER BY r.recipe_name");
$recipes = [];
while($row = $res_recipes->fetch_assoc()) {
    $recipes[] = $row;
}
$res_stats = $conn->query("SELECT COUNT(*) as st_count FROM student");
$total_students = $res_stats->fetch_assoc()['st_count'];
$recipes_json = json_encode($recipes);
?>

<style>
    /* CALENDAR SPECIFIC STYLES */
    .calendar-container {
        background: white; border: 1px solid var(--border); border-radius: 12px; padding: 1.5rem; margin-bottom: 2rem; box-shadow: var(--shadow-sm);
    }
    .cal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
    .cal-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.5rem; }
    .cal-day-name { text-align: center; font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; padding: 0.5rem 0; }
    .cal-day {
        min-height: 80px; border: 1px solid var(--border); border-radius: 8px; padding: 0.5rem; cursor: pointer; transition: all 0.2s; position: relative;
    }
    .cal-day:hover { border-color: var(--primary); background: #f8fafc; }
    .cal-day.active { border-color: var(--primary); box-shadow: 0 0 0 2px var(--primary); }
    .cal-day.disabled { background: #f1f5f9; color: #94a3b8; cursor: not-allowed; }
    .cal-date { font-weight: 700; font-size: 0.9rem; }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; position: absolute; bottom: 0.5rem; right: 0.5rem; }
    .status-deployed { background: #10b981; }

    /* PLANNER UI STYLES */
    .budget-badge { border: 1px solid var(--primary); color: var(--primary); padding: 0.5rem 1rem; border-radius: 20px; font-weight: 700; font-size: 0.8rem; display: flex; align-items: center; gap: 0.5rem; }
    .meal-cards-container { display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem; }
    .meal-card { background: white; border-radius: 16px; border: 1px solid var(--border); padding: 2rem; box-shadow: var(--shadow-sm); position: relative; }
    .meal-card.primary { border-top: 4px solid var(--primary); }
    .meal-card.backup { border-top: 4px solid var(--error); }
    .meal-label { font-size: 0.75rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--primary); margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
    .meal-label .meal-color-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; flex-shrink: 0; }
    .meal-card.backup .meal-label { color: var(--error); }
    .warning-tag { background: #fff3cd; color: #856404; font-size: 0.65rem; padding: 0.15rem 0.5rem; border-radius: 4px; font-weight: 700; }
    .macro-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-top: 1.5rem; margin-bottom: 1.5rem; text-align: center; }
    .macro-box { background: var(--bg-color); padding: 1rem 0.5rem; border-radius: 8px; }
    .macro-val { font-weight: 800; font-size: 1.1rem; color: var(--text-main); }
    .macro-lbl { font-size: 0.7rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; margin-bottom: 0.25rem;}
    .tag-list { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.5rem; }
    .tag { font-size: 0.7rem; padding: 0.25rem 0.75rem; background: #f1f5f9; color: #475569; border-radius: 12px; font-weight: 600; }
    .alert-tag { background: #fee2e2; color: #b91c1c; }
    .student-accordion { border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
    .accordion-header { padding: 1rem; background: var(--bg-color); cursor: pointer; display: flex; justify-content: space-between; align-items: center; font-weight: 700; font-size: 0.9rem; }
    .accordion-content { padding: 0; max-height: 0; overflow: hidden; transition: max-height 0.3s ease-out; background: white; }
    .accordion-content.expanded { max-height: 300px; overflow-y: auto; }
    .student-list-item { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); font-size: 0.85rem; display: flex; justify-content: space-between; }
    .stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-top: 1.5rem; }
    .stat-box { text-align: center; }
    .stat-box .val { font-size: 1.5rem; font-weight: 800; color: var(--text-main); }
    .stat-box .lbl { font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; }
    select.recipe-dropdown { width: 100%; padding: 0.75rem; border: 2px solid var(--border); border-radius: 8px; font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.5rem; outline: none; box-shadow: var(--shadow-sm); transition: border-color 0.2s; }

    /* SUMMARY BAR DASHBOARD */
    .summary-bar {
        background: white; border: 1px solid var(--border); border-radius: 20px; padding: 1.5rem 2.5rem; box-shadow: var(--shadow-md);
        display: flex; justify-content: space-between; align-items: center; margin-bottom: 5rem;
        background: linear-gradient(to right, #ffffff, #fcfcfc);
    }
    .summary-metrics { display: flex; gap: 3rem; align-items: center; }
    .sum-box { text-align: left; }
    .sum-box .sum-val { font-size: 1.4rem; font-weight: 900; color: var(--text-main); line-height: 1; margin-bottom: 0.25rem; }
    .sum-box .sum-lbl { font-size: 0.65rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; }
    
    .badge-status {
        padding: 0.5rem 1rem; border-radius: 30px; font-size: 0.75rem; font-weight: 800; display: inline-flex; align-items: center; gap: 0.5rem;
    }
    .badge-success { background: #dcfce7; color: #15803d; }
    .badge-warning { background: #fef9c3; color: #854d0e; }
    .badge-danger { background: #fee2e2; color: #b91c1c; }

    .btn-solid-danger { background: #ef4444; color: white !important; border: none; font-weight: 700; transition: all 0.2s; }
    .btn-solid-danger:hover { background: #dc2626; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3); }

</style>

<div class="content">
    

    <!-- PLANNER HEADER -->
    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 2rem;">
        <div>
            <h2 style="font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin:0 0 0.5rem 0;" id="dateDisplay">Select a Date</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;" id="engineStatusIndicator">CSP Engine Standby.</p>
        </div>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem; background: white; border: 1px solid var(--border); border-radius: 12px; padding: 0.75rem 1.25rem; box-shadow: var(--shadow-sm);">
                <span class="material-icons" style="font-size:18px; color:var(--text-muted);">account_balance_wallet</span>
                <div>
                    <div style="font-size: 0.65rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Total Daily Budget</div>
                    <div style="display: flex; align-items: center; gap: 0.25rem;">
                        <span style="font-weight: 800; color: var(--text-main);">&#8369;</span>
                        <input type="number" id="budgetLimitInput" step="10" min="1" value="500.00" 
                            style="width: 90px; border: none; outline: none; font-weight: 800; font-size: 1rem; color: var(--text-main); background: transparent;"
                            onchange="saveBudgetSetting(this.value)">
                        <span style="font-size: 0.75rem; color: var(--text-muted); font-weight: 600;">total</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MAIN PLANNER CARDS (Hidden until date clicked) -->
    <div id="plannerWorkspace" style="display:none; transition: opacity 0.3s;">
        <div class="meal-cards-container">
            <!-- MEAL A CARD -->
            <div class="meal-card primary" id="cardA">
                <div class="meal-label" id="aLabel">
                    <span class="meal-color-dot" id="aDot" style="background: var(--primary);"></span>
                    Meal A
                </div>
                <select class="recipe-dropdown" id="mealASelect" onchange="runDistribution()">
                    <option value="">Select Meal A...</option>
                    <?php foreach($recipes as $r): ?>
                        <option value="<?= $r['recipe_id'] ?>"><?= $r['recipe_name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">₱ <span id="aCost">0.00</span> <span style="font-size: 0.75rem;">/ student</span></div>

                <div class="macro-grid">
                    <div class="macro-box">
                        <div class="macro-lbl"><span class="material-icons" style="font-size:12px; color:#f97316;">local_fire_department</span> CAL</div>
                        <div class="macro-val" id="aCal">--</div>
                    </div>
                    <div class="macro-box">
                        <div class="macro-lbl"><span class="material-icons" style="font-size:12px; color:#ef4444;">fitness_center</span> PROT</div>
                        <div class="macro-val" id="aProt">--</div>
                    </div>
                </div>

                <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.5px;">Allergens / Ingredients</div>
                <div class="tag-list" id="aTags"><div class="tag">Wait for selection</div></div>

                <div class="student-accordion">
                    <div class="accordion-header" onclick="toggleAccordion('aStudents')">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            Assigned Students (<span id="aCount">0</span>)
                        </div>
                        <span class="material-icons">expand_more</span>
                    </div>
                    <div class="accordion-content" id="aStudents"></div>
                </div>
            </div>

            <!-- MEAL B CARD -->
            <div class="meal-card backup" id="cardB">
                <div class="meal-label" id="bLabel">
                    <span class="meal-color-dot" id="bDot" style="background: var(--error);"></span>
                    Meal B
                </div>
                <select class="recipe-dropdown" id="mealBSelect" onchange="runDistribution()">
                    <option value="">Select Meal B...</option>
                    <?php foreach($recipes as $r): ?>
                        <option value="<?= $r['recipe_id'] ?>"><?= $r['recipe_name'] ?></option>
                    <?php endforeach; ?>
                </select>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 0.5rem;">₱ <span id="bCost">0.00</span> <span style="font-size: 0.75rem;">/ student</span></div>

                <div class="macro-grid">
                    <div class="macro-box">
                        <div class="macro-lbl"><span class="material-icons" style="font-size:12px; color:#f97316;">local_fire_department</span> CAL</div>
                        <div class="macro-val" id="bCal">--</div>
                    </div>
                    <div class="macro-box">
                        <div class="macro-lbl"><span class="material-icons" style="font-size:12px; color:#ef4444;">fitness_center</span> PROT</div>
                        <div class="macro-val" id="bProt">--</div>
                    </div>
                </div>

                <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.5rem; letter-spacing: 0.5px;">Allergens / Ingredients</div>
                <div class="tag-list" id="bTags"><div class="tag">Wait for selection</div></div>

                <div class="student-accordion">
                    <div class="accordion-header" onclick="toggleAccordion('bStudents')">
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            Assigned Students (<span id="bCount">0</span>)
                        </div>
                        <span class="material-icons">expand_more</span>
                    </div>
                    <div class="accordion-content" id="bStudents"></div>
                </div>
            </div>
        </div>

        <div class="summary-bar">
            <div class="summary-metrics">
                <div class="sum-box">
                    <div class="sum-lbl">Metric Status</div>
                    <div id="budgetStatus" style="margin-top:0.25rem;"></div>
                </div>
                <div style="width:1px; height:40px; background:var(--border);"></div>
                <div class="sum-box">
                    <div class="sum-val" id="projCal">0</div>
                    <div class="sum-lbl">Avg Calories</div>
                </div>
                <div class="sum-box">
                    <div class="sum-val" id="projSt">0</div>
                    <div class="sum-lbl">Students Fed</div>
                </div>
                <div class="sum-box">
                    <div class="sum-val" id="projTotalCost" style="color:var(--primary); font-size:1.6rem;">&#8369; 0.00</div>
                    <div class="sum-lbl">Total Daily Spend</div>
                </div>
            </div>

            <div style="display: flex; gap: 1rem; align-items: center;">
                <button class="btn" id="saveBtn" style="padding: 1rem 2rem; font-size: 1rem; background: var(--primary); color: white;" onclick="savePlan()" disabled>Deploy Plan</button>
                <button class="btn btn-solid-danger" id="undeployBtn" style="padding: 1rem 2rem; font-size: 1rem; display:none;" onclick="undeployPlan()">Undeploy Plan</button>
                <button class="btn" id="serveBtn" style="padding: 1rem 2rem; font-size: 1rem; background: #16a34a; color: white; display:none;" onclick="serveDay()">Mark as Served</button>
                <button class="btn" id="unserveBtn" style="padding: 1rem 2rem; font-size: 1rem; display:none; border: 2px solid var(--border); background: white; color: var(--text-main); font-weight: 700;" onclick="unserveDay()">Unlock Plan</button>
            </div>

        </div>
    </div>

    <!-- CALENDAR WIDGET -->
    <div class="calendar-container">
        <div class="cal-header">
            <div>
                <h3 style="margin:0; font-size: 1.25rem; font-weight: 800;" id="calMonthDisplay">April 2026</h3>
                <span style="font-size: 0.8rem; color: var(--text-muted);">Deployment History & Future Planning Matrix</span>
            </div>
            <div style="display: flex; gap: 0.5rem;">
                <button class="btn" style="padding: 0.5rem; border:1px solid var(--border);" onclick="changeMonth(-1)"><span class="material-icons">chevron_left</span></button>
                <button class="btn" style="padding: 0.5rem; border:1px solid var(--border);" onclick="changeMonth(1)"><span class="material-icons">chevron_right</span></button>
            </div>
        </div>
        
        <div class="cal-grid">
            <div class="cal-day-name">Sun</div><div class="cal-day-name">Mon</div><div class="cal-day-name">Tue</div>
            <div class="cal-day-name">Wed</div><div class="cal-day-name">Thu</div><div class="cal-day-name">Fri</div><div class="cal-day-name">Sat</div>
        </div>
        <div class="cal-grid" id="calGridArea">
            <!-- JS Injected -->
        </div>
    </div>
</div>

<script>
    const recipes = <?= $recipes_json ?>;
    const isAdmin = <?php echo json_encode($isAdmin); ?>;
    let distributionData = null;

    let selectedDateStr = null;
    let currentBudgetLimit = 25.00;

    // Pre-build restriction ID map for fast conflict checking
    const recipeRestrictions = {};
    recipes.forEach(r => {
        recipeRestrictions[r.recipe_id] = r.restriction_ids ? r.restriction_ids.toString().split(',') : [];
    });

    async function loadBudgetSetting() {
        try {
            const res = await fetch('api_handle_settings.php?key=total_daily_budget');
            const data = await res.json();
            currentBudgetLimit = parseFloat(data.value) || 500.00;
            document.getElementById('budgetLimitInput').value = currentBudgetLimit.toFixed(2);
        } catch(e) { currentBudgetLimit = 500.00; }
    }

    async function saveBudgetSetting(val) {
        currentBudgetLimit = parseFloat(val) || 500.00;
        await fetch('api_handle_settings.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ key: 'total_daily_budget', value: currentBudgetLimit.toFixed(2) })
        });
        if (distributionData) renderLists();
    }

    function checkStudentConflict(student, recipeId) {
        if (!student.restriction_ids || !student.restriction_ids.length) return false;
        const reqs = Array.isArray(student.restriction_ids) ? student.restriction_ids.map(String) : student.restriction_ids.toString().split(',');
        const recReqs = recipeRestrictions[recipeId] || [];
        return reqs.some(r => recReqs.includes(r));
    }

    // CALENDAR LOGIC
    let currYear = 2026;
    let currMonth = 3; // April

    const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

    async function loadCalendar() {
        document.getElementById('calMonthDisplay').innerText = `${monthNames[currMonth]} ${currYear}`;
        
        let mStr = (currMonth + 1).toString().padStart(2, '0');
        let yMonth = `${currYear}-${mStr}`;
        
        try {
            const res = await fetch(`api_get_month_plan.php?month=${yMonth}`);
            const data = await res.json();
            const deployed = data.success ? data.data : {};
            
            drawGrid(deployed);
        } catch(e) {
            drawGrid({});
        }
    }

    function drawGrid(deployed) {
        const grid = document.getElementById('calGridArea');
        grid.innerHTML = '';
        
        const firstDay = new Date(currYear, currMonth, 1).getDay();
        const daysInMonth = new Date(currYear, currMonth + 1, 0).getDate();
        
        // padding
        for (let i = 0; i < firstDay; i++) {
            grid.innerHTML += `<div class="cal-day disabled"></div>`;
        }

        for (let i = 1; i <= daysInMonth; i++) {
            let dStr = `${currYear}-${(currMonth+1).toString().padStart(2,'0')}-${i.toString().padStart(2,'0')}`;
            let hasPlan = deployed[dStr] ? true : false;
            
            let isToday = (dStr === new Date().toLocaleDateString('en-CA')); // format YYYY-MM-DD local
            let extraClass = isToday ? ' active' : '';

            let html = `<div class="cal-day${extraClass}" id="cal-day-${dStr}" onclick="selectDate('${dStr}', this)">
                            <div class="cal-date">${i}</div>`;
            if (hasPlan && deployed[dStr].assigned_recipes) {
                let assigned = deployed[dStr].assigned_recipes.split(',');
                let rec1 = recipes.find(r => r.recipe_id === assigned[0]);
                let rec2 = assigned.length > 1 ? recipes.find(r => r.recipe_id === assigned[1]) : null;
                
                let color1 = (rec1 && rec1.hex_color) ? rec1.hex_color : '#3b82f6';
                let color2 = (rec2 && rec2.hex_color) ? rec2.hex_color : color1;
                
                let styleStr = `background: linear-gradient(135deg, ${color1}22 50%, ${color2}22 50%); border-left: 4px solid ${color1}; border-right: 4px solid ${color2};`;
                let lockIcon = '';
                
                if (deployed[dStr].status === 'served') {
                    styleStr = `background: linear-gradient(135deg, ${color1}44 50%, ${color2}44 50%); border-left: 4px solid ${color1}; border-right: 4px solid ${color2}; box-shadow: inset 0 0 0 2px rgba(21, 128, 61, 0.3);`;
                    lockIcon = '<span class="material-icons" style="position:absolute; top:0.25rem; right:0.25rem; font-size:14px; color:#15803d;" title="Served & Locked">lock</span>';
                }

                html = `<div class="cal-day${extraClass}" id="cal-day-${dStr}" onclick="selectDate('${dStr}', this)" style="${styleStr}">
                            <div class="cal-date">${i}</div>
                            ${lockIcon}
                            <div style="position:absolute; bottom:0.5rem; right:0.5rem; display:flex; gap:2px;">
                                <div class="status-dot" style="background: ${color1}; position:static;"></div>
                                <div class="status-dot" style="background: ${color2}; position:static;"></div>
                            </div>`;
            }

            html += `</div>`;
            grid.innerHTML += html;
        }
    }

    function changeMonth(dir) {
        currMonth += dir;
        if (currMonth > 11) { currMonth = 0; currYear++; }
        if (currMonth < 0) { currMonth = 11; currYear--; }
        loadCalendar();
    }

    // Reset lock state on every date change
    async function selectDate(dateStr, el) {
        document.querySelectorAll('.cal-day').forEach(d => d.classList.remove('active'));
        if(el) el.classList.add('active');

        selectedDateStr = dateStr;
        
        // Reset UI lock state
        document.getElementById('mealASelect').disabled = false;
        document.getElementById('mealBSelect').disabled = false;
        document.getElementById('saveBtn').style.display = 'flex';
        document.getElementById('saveBtn').disabled = true;
        document.getElementById('saveBtn').style.background = 'var(--primary)';
        document.getElementById('saveBtn').innerText = 'Deploy Plan';
        document.getElementById('serveBtn').style.display = 'none';
        document.getElementById('serveBtn').disabled = false;
        document.getElementById('serveBtn').innerText = 'Mark as Served';
        document.getElementById('unserveBtn').style.display = 'none';

        
        const dFormat = new Date(dateStr).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });

        document.getElementById('dateDisplay').innerText = 'Plan Lunch for ' + dFormat;
        document.getElementById('plannerWorkspace').style.display = 'block';

        // Check if history exists
        document.getElementById('engineStatusIndicator').innerHTML = '<span style="color:var(--primary);">Querying Database...</span>';
        
        try {
            const res = await fetch(`api_get_day_plan.php?date=${dateStr}`);
            const data = await res.json();
            
            if (data.success && data.meal_a) {
                document.getElementById('mealASelect').value = data.meal_a;
                document.getElementById('mealBSelect').value = data.meal_b || '';

                if (data.is_served) {
                    document.getElementById('engineStatusIndicator').innerHTML = '<span style="color:#16a34a; font-weight:800;"><span class="material-icons" style="font-size:14px; vertical-align:middle;">lock</span> Served Day — Plan is Locked</span>';
                    runDistribution().then(() => lockWorkspace());

                } else {
                    document.getElementById('engineStatusIndicator').innerHTML = '<span style="color:#10b981;">Loaded Historical Configuration</span>';
                    document.getElementById('saveBtn').style.display = 'none'; // Replaced by Undeploy
                    document.getElementById('undeployBtn').style.display = 'flex';
                    document.getElementById('serveBtn').style.display = 'flex';
                    runDistribution();
                }
            } else {
                // RUN CSP
                document.getElementById('engineStatusIndicator').innerHTML = '<span style="color:var(--primary); font-weight:700;"><span class="material-icons" style="font-size:14px; vertical-align:middle;">auto_awesome</span> Invoking CSP Engine...</span>';
                
                const cspRes = await fetch(`api_generate_csp.php?date=${dateStr}`);
                const cspData = await cspRes.json();
                
                if (cspData.success) {
                    document.getElementById('mealASelect').value = cspData.meal_a;
                    document.getElementById('mealBSelect').value = cspData.meal_b;
                    document.getElementById('engineStatusIndicator').innerHTML = '<span style="color:#10b981; font-weight:700;"><span class="material-icons" style="font-size:14px; vertical-align:middle;">check_circle</span> CSP Optimized Selection. Ready to Deploy.</span>';
                    runDistribution();
                } else {
                     document.getElementById('engineStatusIndicator').innerHTML = '<span style="color:var(--error);">CSP Failed: Adjust constraints.</span>';
                }
            }
        } catch(e) {
            console.error(e);
        }
    }

    function toggleAccordion(id) {
        document.getElementById(id).classList.toggle('expanded');
    }

    // DYNAMIC CARD UPDATER
    function updateCardVisuals(mealType, recipeId) {
        const prefix = mealType === 'A' ? 'a' : 'b';
        const cardEl = document.getElementById(`card${mealType}`);
        const labelEl = document.getElementById(`${prefix}Label`);
        const dotEl = document.getElementById(`${prefix}Dot`);
        const recipe = recipes.find(r => r.recipe_id === recipeId);

        if (recipe) {
            const color = recipe.hex_color || (mealType === 'A' ? '#3b82f6' : '#ef4444');
            
            // Apply recipe color to card border-top and label
            cardEl.style.borderTopColor = color;
            labelEl.style.color = color;
            dotEl.style.background = color;

            document.getElementById(`${prefix}Cost`).innerText = parseFloat(recipe.base_cost_per_serving).toFixed(2);
            document.getElementById(`${prefix}Cal`).innerText = recipe.energy_kcal;
            document.getElementById(`${prefix}Prot`).innerText = Number(recipe.protein_g).toFixed(1) + 'g';
            
            let tagsHtml = '';
            if (recipe.allergens) {
                recipe.allergens.split(',').forEach(a => {
                    let label = a === 'Halal' ? 'Non-Halal (Pork)' : a;
                    tagsHtml += `<span class="tag alert-tag">Contains ${label}</span>`;
                });
            } else {
                tagsHtml = '<span class="tag">No Major Allergens</span>';
            }
            document.getElementById(`${prefix}Tags`).innerHTML = tagsHtml;
        } else {
            const defaultColor = mealType === 'A' ? 'var(--primary)' : 'var(--error)';
            cardEl.style.borderTopColor = defaultColor;
            labelEl.style.color = defaultColor;
            dotEl.style.background = defaultColor;
            document.getElementById(`${prefix}Cost`).innerText = '0.00';
            document.getElementById(`${prefix}Cal`).innerText = '--';
            document.getElementById(`${prefix}Prot`).innerText = '--';
            document.getElementById(`${prefix}Tags`).innerHTML = '<div class="tag">Wait for selection</div>';
        }
    }

    async function runDistribution() {
        const mealA = document.getElementById('mealASelect').value;
        const mealB = document.getElementById('mealBSelect').value;
        
        updateCardVisuals('A', mealA);
        updateCardVisuals('B', mealB);

        const saveBtn = document.getElementById('saveBtn');
        if (!mealA) { saveBtn.disabled = true; return; }

        try {
            saveBtn.disabled = true;
            saveBtn.innerText = 'Calculating...';

            const response = await fetch(`api_daily_distribution.php?meal_a=${mealA}&meal_b=${mealB}`);
            distributionData = await response.json();

            if (distributionData.success) {
                renderLists();
            }
        } catch (e) { console.error(e); }
    }

    function renderLists() {
        const mealA = document.getElementById('mealASelect').value;
        const mealB = document.getElementById('mealBSelect').value;
        const saveBtn = document.getElementById('saveBtn');
        const recA = recipes.find(r => r.recipe_id === mealA);
        const recB = recipes.find(r => r.recipe_id === mealB);
        const colorA = recA ? (recA.hex_color || '#3b82f6') : '#3b82f6';
        const colorB = recB ? (recB.hex_color || '#ef4444') : '#ef4444';

        function studentRow(s, assignedRecipe, fromList, toList, mealLabel, mealColor) {
            const hasConflict = checkStudentConflict(s, assignedRecipe);
            const conflictWarn = hasConflict ? `<span class="warning-tag" title="${s.restriction_names || ''}">⚠ Allergy Conflict</span>` : '';
            return `
            <div class="student-list-item" style="align-items: center; ${hasConflict ? 'background:#fffbeb;' : ''}">
                <div>
                    <span style="font-weight: 700;">${s.name}</span>
                    <span style="color:var(--text-muted); font-size:0.7rem; margin-left:0.5rem;">${s.section}</span>
                    ${conflictWarn}
                    ${s.forced ? `<span style="font-size:0.65rem; color:${mealColor}; font-weight:700; margin-left:0.4rem;">(Auto-assigned)</span>` : ''}
                </div>
                <button onclick="swapStudent('${s.id}', '${fromList}', '${toList}')" title="Move to ${toList === 'a' ? 'Meal A' : 'Meal B'}" style="background:none; border:none; color:var(--text-muted); cursor:pointer;">
                    <span class="material-icons" style="font-size:16px;">swap_horiz</span>
                </button>
            </div>`;
        }

        document.getElementById('aCount').innerText = distributionData.meal_a_list.length;
        document.getElementById('aStudents').innerHTML = distributionData.meal_a_list.map(s => 
            studentRow(s, mealA, 'a', 'b', 'Meal B', colorA)
        ).join('') || '<div style="padding: 1rem; color: var(--text-muted); font-size: 0.85rem;">No students assigned.</div>';

        document.getElementById('bCount').innerText = distributionData.meal_b_list.length;
        document.getElementById('bStudents').innerHTML = distributionData.meal_b_list.map(s => 
            studentRow(s, mealB, 'b', 'a', 'Meal A', colorB)
        ).join('') || '<div style="padding: 1rem; color: var(--text-muted); font-size: 0.85rem;">No students assigned.</div>';

        // Daily Projection — Total cost vs Total budget
        const aCount = distributionData.meal_a_list.length;
        const bCount = distributionData.meal_b_list.length;
        const totalFed = aCount + bCount;
                let totalCal = 0, totalCost = 0;
        if (recA && aCount > 0) { totalCal += recA.energy_kcal * aCount; totalCost += parseFloat(recA.base_cost_per_serving) * aCount; }
        if (recB && bCount > 0) { totalCal += recB.energy_kcal * bCount; totalCost += parseFloat(recB.base_cost_per_serving) * bCount; }

        const avgKcal = totalFed > 0 ? Math.round(totalCal / totalFed) : 0;
        const isOverBudget = totalCost > currentBudgetLimit;

        document.getElementById('projSt').innerText = totalFed;
        document.getElementById('projCal').innerText = avgKcal;
        document.getElementById('projTotalCost').innerHTML = '&#8369; ' + totalCost.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2});
        
        const statusEl = document.getElementById('budgetStatus');
        if (isOverBudget) {
            statusEl.innerHTML = `<span class="badge-status badge-danger"><span class="material-icons" style="font-size:1rem;">warning</span> Over by &#8369;${(totalCost - currentBudgetLimit).toFixed(2)}</span>`;
            document.getElementById('projTotalCost').style.color = '#dc2626';
        } else {
            statusEl.innerHTML = `<span class="badge-status badge-success"><span class="material-icons" style="font-size:1rem;">check_circle</span> Within Budget</span>`;
            document.getElementById('projTotalCost').style.color = 'var(--primary)';
        }

        saveBtn.disabled = false;
        saveBtn.innerText = 'Deploy Plan';
    }

    function swapStudent(id, from, to) {
        let student = null;
        if (from === 'a') {
            const idx = distributionData.meal_a_list.findIndex(s => s.id === id);
            if (idx > -1) student = distributionData.meal_a_list.splice(idx, 1)[0];
        } else if (from === 'b') {
            const idx = distributionData.meal_b_list.findIndex(s => s.id === id);
            if (idx > -1) student = distributionData.meal_b_list.splice(idx, 1)[0];
        } else if (from === 'exc') {
            const idx = distributionData.excluded_list.findIndex(s => s.id === id);
            if (idx > -1) student = distributionData.excluded_list.splice(idx, 1)[0];
        }
        if (!student) return;
        student.reason = 'Manually Assigned';

        if (to === 'a') distributionData.meal_a_list.push(student);
        else if (to === 'b') distributionData.meal_b_list.push(student);
        else distributionData.excluded_list.push(student);

        distributionData.meal_a_list.sort((x, y) => x.name.localeCompare(y.name));
        distributionData.meal_b_list.sort((x, y) => x.name.localeCompare(y.name));
        distributionData.excluded_list.sort((x, y) => x.name.localeCompare(y.name));

        renderLists();
    }

    async function savePlan() {
        if (!distributionData || !selectedDateStr) return;
        const mealA = document.getElementById('mealASelect').value;
        const mealB = document.getElementById('mealBSelect').value;
        const btn = document.getElementById('saveBtn');
        const serveBtn = document.getElementById('serveBtn');

        btn.disabled = true;
        btn.innerText = 'Deploying...';
        serveBtn.style.display = 'none';

        try {
            const response = await fetch('api_save_meal_plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'deploy',
                    date: selectedDateStr,
                    meal_a_recipe: mealA,
                    meal_b_recipe: mealB,
                    meal_a_list: distributionData.meal_a_list.map(s => s.id),
                    meal_b_list: distributionData.meal_b_list.map(s => s.id)
                })
            });
            const data = await response.json();
            if (data.success) {
                // Success - hide deploy, show undeploy/serve
                btn.style.display = 'none'; 
                document.getElementById('undeployBtn').style.display = 'flex';
                document.getElementById('serveBtn').style.display = 'flex';
                
                loadCalendar();
                btn.innerText = 'Deploy Plan'; // Reset for next use
                btn.disabled = false;
            } else {
                alert('Failed: ' + data.message);
                btn.disabled = false;
                btn.innerText = 'Deploy Plan';
            }
        } catch (e) {
            console.error(e);
            btn.disabled = false;
            btn.innerText = 'Deploy Plan';
        }
    }

    async function serveDay() {
        if (!selectedDateStr) return;
        if (!confirm('Mark this day as Served? This will LOCK the plan and prevent any further changes.')) return;

        const serveBtn = document.getElementById('serveBtn');
        serveBtn.disabled = true;
        serveBtn.innerText = 'Locking...';

        try {
            const response = await fetch('api_save_meal_plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'mark_served', date: selectedDateStr })
            });
            const data = await response.json();
            if (data.success) {
                loadCalendar();
                lockWorkspace();
            } else {
                alert('Could not lock: ' + data.message);
                serveBtn.disabled = false;
                serveBtn.innerHTML = 'Mark as Served';
            }
        } catch(e) {
            console.error(e);
            serveBtn.disabled = false;
            serveBtn.innerHTML = 'Mark as Served';
        }
    }

    function lockWorkspace() {
        document.getElementById('mealASelect').disabled = true;
        document.getElementById('mealBSelect').disabled = true;
        
        const saveBtn = document.getElementById('saveBtn');
        saveBtn.disabled = true;
        saveBtn.innerText = 'Plan Locked';
        saveBtn.style.background = '#94a3b8';
        saveBtn.style.display = 'flex';
        
        document.getElementById('serveBtn').style.display = 'none';
        document.getElementById('undeployBtn').style.display = 'none';
        // Only admins can unlock a served plan
        if (isAdmin) {
            document.getElementById('unserveBtn').style.display = 'flex';
        }
        document.getElementById('engineStatusIndicator').innerHTML =

            '<span style="color:#16a34a; font-weight:800;"><span class="material-icons" style="font-size:14px; vertical-align:middle;">lock</span> This day has been served and is locked.</span>';
        // Disable all swap buttons

        document.querySelectorAll('.student-list-item button').forEach(b => b.disabled = true);
    }

    async function unserveDay() {
        if (!selectedDateStr) return;
        if (!confirm('Unlock this day? This will allow you to edit and undeploy the plan.')) return;

        const uBtn = document.getElementById('unserveBtn');
        uBtn.disabled = true;
        uBtn.innerText = 'Unlocking...';

        try {
            const response = await fetch('api_save_meal_plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'unserve', date: selectedDateStr })
            });
            const data = await response.json();
            if (data.success) {
                location.reload(); 
            } else {
                alert('Unlock failed: ' + data.message);
                uBtn.disabled = false;
                uBtn.innerHTML = 'Unlock Plan';
            }

        } catch(e) {
            console.error(e);
            uBtn.disabled = false;
            uBtn.innerHTML = 'Unlock Plan';
        }

    }


    async function undeployPlan() {
        if (!selectedDateStr) return;
        if (!confirm('Undeploy this plan? This will wipe the assignments and reset the day.')) return;

        const uBtn = document.getElementById('undeployBtn');
        uBtn.disabled = true;
        uBtn.innerText = 'Resetting...';

        try {
            const response = await fetch('api_save_meal_plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'undeploy', date: selectedDateStr })
            });
            const data = await response.json();
            if (data.success) {
                location.reload(); // Hard refresh to reset the CSP state for the day
            } else {
                alert('Reset failed: ' + data.message);
                uBtn.disabled = false;
                uBtn.innerText = 'Undeploy';
            }
        } catch(e) {
            console.error(e);
            uBtn.disabled = false;
        }
    }

    // Init
    loadBudgetSetting();
    loadCalendar().then(() => {
        let todayStr = new Date().toLocaleDateString('en-CA');
        selectDate(todayStr, document.getElementById('cal-day-' + todayStr));
    });
</script>

<?php require_once '../../includes/footer.php'; ?>

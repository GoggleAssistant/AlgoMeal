<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<?php
$page_title = 'Command Planner';
require_once '../../includes/topbar.php';
require_once '../../db.php';
$isAdmin = ($role === 'Admin' || $role === 'Super Admin');



// Get Recipes
$res_recipes = $conn->query("SELECT r.*, GROUP_CONCAT(DISTINCT dr.restriction_name) as allergens, GROUP_CONCAT(DISTINCT rat.restriction_id) as restriction_ids FROM recipes r LEFT JOIN recipe_allergen_tags rat ON r.recipe_id = rat.recipe_id LEFT JOIN dietary_restrictions dr ON rat.restriction_id = dr.restriction_id GROUP BY r.recipe_id ORDER BY r.recipe_name");
$recipes = [];
while ($row = $res_recipes->fetch_assoc()) {
    $recipes[] = $row;
}
$recipes_json = json_encode($recipes);
?>

<style>
    .planner-grid {
        display: flex;
        flex-direction: column;
        gap: 2rem;
        padding-bottom: 3rem;
    }

    .workspace-panel {
        background: white;
        border: 1px solid var(--border);
        border-radius: 20px;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }

    .workspace-header {
        padding: 1.25rem 2rem;
        border-bottom: 1px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #fdfdfd;
    }

    /* M3 Pill Buttons */
    .btn-m3 {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 100px;
        font-weight: 700;
        font-size: 0.85rem;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        border: none;
        cursor: pointer;
        box-shadow: var(--shadow-sm);
    }

    .btn-m3:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
    }

    .btn-m3:active {
        transform: scale(0.98);
    }

    .btn-m3-primary {
        background: var(--primary);
        color: white;
    }

    .btn-m3-outline {
        background: white;
        color: var(--text-main);
        border: 1px solid var(--border);
        box-shadow: none;
    }

    .btn-m3-outline:hover {
        background: #f8fafc;
        border-color: var(--primary);
        color: var(--primary);
    }

    .btn-m3-tonal {
        background: #e0e7ff;
        color: #3730a3;
        box-shadow: none;
    }

    .btn-m3-tonal:hover {
        background: #d1d5db;
    }

    .btn-m3-danger {
        background: #fee2e2;
        color: #b91c1c;
        box-shadow: none;
    }

    .btn-m3-danger:hover {
        background: #fecaca;
    }

    .workspace-body {
        padding: 2.5rem;
    }


    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem 1rem;
        text-align: center;
        background: #fafafa;
        border: 2px dashed var(--border);
        border-radius: 12px;
    }

    .meal-cards-container {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 1.25rem;
    }

    .meal-card-x {
        background: white;
        border: 1px solid var(--border);
        border-top: 4px solid var(--primary);
        border-radius: 12px;
        padding: 1.25rem;
        display: flex;
        flex-direction: column;
    }

    .macro-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin: 1rem 0;
        background: #f8fafc;
        padding: 0.6rem;
        border-radius: 8px;
    }

    .macro-box {
        text-align: center;
    }

    .macro-lbl {
        font-size: 0.6rem;
        font-weight: 800;
        color: var(--text-muted);
    }

    .macro-val {
        font-size: 1.1rem;
        font-weight: 900;
        color: var(--text-main);
    }

    .tag-list {
        display: flex;
        flex-wrap: wrap;
        gap: 0.3rem;
    }

    .tag {
        font-size: 0.6rem;
        font-weight: 700;
        padding: 0.2rem 0.5rem;
        background: #e2e8f0;
        color: #475569;
        border-radius: 4px;
    }

    .alert-tag {
        background: #fee2e2;
        color: #b91c1c;
    }

    .student-accordion {
        margin-top: 1.25rem;
        border: 1px solid var(--border);
        border-radius: 8px;
    }

    .accordion-header {
        padding: 0.6rem 0.8rem;
        background: #f8fafc;
        border-bottom: 1px solid var(--border);
        font-weight: 700;
        font-size: 0.8rem;
        color: var(--text-main);
        cursor: pointer;
        display: flex;
        justify-content: space-between;
    }

    .accordion-body {
        max-height: 250px;
        overflow-y: auto;
        display: none;
        padding: 0.4rem;
    }

    .student-accordion.expanded .accordion-body {
        display: block;
    }

    .student-list-item {
        display: flex;
        justify-content: space-between;
        padding: 0.4rem;
        border-bottom: 1px solid #f1f5f9;
        font-size: 0.75rem;
    }

    .btn-status-toggle {
        background: #f1f5f9;
        border: 1px solid transparent;
        color: var(--text-muted);
        padding: 3px 5px;
        border-radius: 4px;
        cursor: pointer;
        transition: 0.2s;
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }

    .btn-status-toggle.active {
        background: var(--btn-color);
        color: white;
        border-color: var(--btn-color);
    }

    .btn-milk-toggle {
        background: #f1f5f9;
        color: #94a3b8;
        border: 1px solid var(--border);
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: all 0.2s;
        padding: 0;
    }

    .btn-milk-toggle.active {
        background: #60a5fa;
        color: white;
        border-color: #60a5fa;
        box-shadow: 0 2px 4px rgba(96, 165, 250, 0.3);
    }

    .btn-milk-toggle:disabled {
        opacity: 0.3;
        cursor: not-allowed;
        background: #f1f5f9 !important;
        color: #cbd5e1 !important;
        border-color: var(--border) !important;
        box-shadow: none !important;
    }

    /* Large Cal Grid */
    .big-cal-matrix {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 0.75rem;
        margin-top: 1.5rem;
    }

    .cal-day-name {
        text-align: center;
        font-weight: 900;
        font-size: 0.8rem;
        color: var(--text-muted);
        text-transform: uppercase;
        padding-bottom: 1rem;
    }

    .cal-day {
        aspect-ratio: 1.1;
        border: 1px solid var(--border);
        border-radius: 12px;
        position: relative;
        cursor: pointer;
        padding: 1rem;
        background: white;
        transition: 0.2s;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
    }

    .cal-day:hover {
        border-color: var(--primary);
        transform: translateY(-2px);
        box-shadow: var(--shadow-md);
    }

    .cal-day.active {
        border-color: var(--primary);
        background: #f0f7ff;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
    }

    .cal-day.today {
        border: 2px solid #cbd5e1;
        border-style: dashed;
    }

    .cal-day.disabled {
        background: #f8fafc;
        cursor: default;
        border-style: dashed;
        opacity: 0.5;
    }

    .cal-date {
        font-size: 1.2rem;
        font-weight: 900;
        color: var(--text-main);
    }

    .cal-indicator {
        height: 6px;
        border-radius: 3px;
        width: 100%;
        margin-top: auto;
    }
</style>

<div class="content">
    <div class="planner-grid">




        <!-- MAIN WORKSPACE -->
        <div class="workspace-panel" id="mainWorkspace">
            <div class="workspace-header">
                <div style="display:flex; align-items:center; gap: 1.5rem;">
                    <div>
                        <h2 style="font-size: 1.6rem; font-weight: 900; margin:0; color: var(--text-main);"
                            id="wsDateTitle">--</h2>
                        <div id="wsStatus"
                            style="font-size:0.8rem; color:var(--text-muted); font-weight:700; display:flex; align-items:center; gap:6px; margin-top:4px;">
                        </div>
                    </div>
                    <button class="btn-m3 btn-m3-tonal" onclick="openCalendarModal()">
                        <span class="material-icons" style="font-size:18px;">event</span> Switch Mission Date
                    </button>
                </div>
                <div style="display:flex; gap:0.75rem;" id="wsActions"></div>
            </div>

            <!-- Attendance Legend -->
            <div style="border-bottom: 1px dashed var(--border); padding-bottom: 1.25rem; margin-bottom: 1.5rem; margin-top: 0.5rem; display: flex; gap: 2.5rem; flex-wrap: wrap; justify-content: center;">
                <div style="display:flex; align-items:center; gap: 6px; font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">
                    <span class="material-icons" style="font-size:16px; color:#10b981;">done</span> Served
                </div>
                <div style="display:flex; align-items:center; gap: 6px; font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">
                    <span class="material-icons" style="font-size:16px; color:#ef4444;">close</span> Absent
                </div>
                <div style="display:flex; align-items:center; gap: 6px; font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">
                    <span class="material-icons" style="font-size:16px; color:#60a5fa;">water_drop</span> Milk Served
                </div>
                <div style="display:flex; align-items:center; gap: 6px; font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">
                    <span class="material-icons" style="font-size:16px; color:#94a3b8;">local_drink</span> No Milk
                </div>
                <div style="display:flex; align-items:center; gap: 6px; font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">
                    <span class="material-icons" style="font-size:16px; color:#f59e0b;">bakery_dining</span> Snack Served
                </div>
                <div style="display:flex; align-items:center; gap: 6px; font-size: 0.75rem; font-weight: 700; color: var(--text-muted);">
                    <span class="material-icons" style="font-size:16px; color:#94a3b8;">cookie</span> No Snack
                </div>
            </div>

            <div class="workspace-body" id="wsBody">
                <!-- Initial content will be injected by selectDate -->
            </div>
        </div>

    </div>
</div>




<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const recipes = <?= $recipes_json ?>;
        const isAdmin = <?php echo json_encode($isAdmin); ?>;

        let currDate = new Date();
        let currentSelDate = null;
        let currentPlan = null;
        let reschedulingMode = false;

        // ==========================================
        // CALENDAR LOGIC (MODAL)
        // ==========================================
        window.openCalendarModal = () => {
            AlgoModal.show({
                title: 'Strategic Mission Matrix',
                maxWidth: '800px',
                body: `
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem;">
                        <div>
                            <h2 id="modalMonthHeader" style="margin:0; font-size: 1.5rem; font-weight: 800; color: var(--text-main);">--</h2>
                            <p style="margin:4px 0 0 0; color:var(--text-muted); font-size:0.85rem;">Select a date to coordinate mission deployment.</p>
                        </div>
                        <div style="display: flex; gap: 0.5rem;">
                            <button class="btn-m3 btn-m3-outline" style="padding: 8px 16px;" onclick="window.changeMonth(-1)"><span class="material-icons">chevron_left</span></button>
                            <button class="btn-m3 btn-m3-outline" style="padding: 8px 16px;" onclick="window.changeMonth(1)"><span class="material-icons">chevron_right</span></button>
                        </div>
                    </div>
                    <div class="big-cal-matrix" style="margin-top:0; grid-template-columns: repeat(7, 1fr); gap: 10px;">
                        <div class="cal-day-name">S</div><div class="cal-day-name">M</div><div class="cal-day-name">T</div>
                        <div class="cal-day-name">W</div><div class="cal-day-name">T</div><div class="cal-day-name">F</div><div class="cal-day-name">S</div>
                    </div>
                    <div class="big-cal-matrix" id="modalCalGrid" style="margin-top:0; grid-template-columns: repeat(7, 1fr); gap: 10px;"></div>
                `,
                footer: `<button class="btn-m3 btn-m3-primary" onclick="openBulkGenerateModal()"><span class="material-icons" style="font-size:16px;">auto_awesome</span> Bulk Generate Portfolio</button>
                         <button class="btn-m3 btn-m3-outline" onclick="AlgoModal.close()">Return to Base</button>`
            });
            renderCalendar();
        };

        window.changeMonth = (offset) => {
            currDate.setMonth(currDate.getMonth() + offset);
            renderCalendar();
        };

        async function renderCalendar() {
            try {
                const y = currDate.getFullYear();
                const m = currDate.getMonth();
                const header = document.getElementById('modalMonthHeader');
                if (header) header.innerText = currDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

                const mStr = `${y}-${(m + 1).toString().padStart(2, '0')}`;
                let monthPlans = {};
                try {
                    const res = await fetch(`api_get_month_plan.php?month=${mStr}`);
                    const data = await res.json();
                    if (data.success) monthPlans = data.data;
                } catch (e) { }

                const grid = document.getElementById('modalCalGrid');
                if (!grid) return;
                grid.innerHTML = '';


                const firstDay = new Date(y, m, 1).getDay();
                const days = new Date(y, m + 1, 0).getDate();

                for (let i = 0; i < firstDay; i++) {
                    grid.innerHTML += `<div class="cal-day disabled"></div>`;
                }

                for (let i = 1; i <= days; i++) {
                    let dStr = `${y}-${(m + 1).toString().padStart(2, '0')}-${i.toString().padStart(2, '0')}`;
                    let isSelected = (dStr === currentSelDate);
                    let isToday = (dStr === new Date().toLocaleDateString('en-CA'));
                    let extra = isSelected ? ' active' : (isToday ? ' today' : '');

                    let html = `<div class="cal-day${extra}" id="cday-${dStr}" onclick="selectDate('${dStr}')">
                                    <div class="cal-date">${i}</div>`;

                    if (monthPlans[dStr]) {
                        const c1 = monthPlans[dStr].a_color || '#3b82f6';
                        const c2 = monthPlans[dStr].b_color ? monthPlans[dStr].b_color : c1;
                        const hasB = monthPlans[dStr].b_color ? true : false;
                        const isServed = monthPlans[dStr].is_served;

                        if (isServed) {
                            html += `<div style="font-size:0.6rem; color:#10b981; font-weight:900; display:flex; align-items:center; gap:2px;"><span class="material-icons" style="font-size:10px;">check_circle</span> SERVED</div>`;
                        } else {
                            html += `<div style="font-size:0.6rem; color:var(--text-muted); font-weight:800;">DEPLOYED</div>`;
                        }

                        html += `<div class="cal-indicator" style="background: ${hasB ? `linear-gradient(90deg, ${c1} 50%, ${c2} 50%)` : c1};"></div>`;
                    }
                    html += `</div>`;
                    grid.innerHTML += html;
                }
            } catch (err) {
                console.error("RenderCalendar Panic:", err);
            }
        }

        // ==========================================
        // WORKSPACE LOGIC
        // ==========================================
        function checkConflict(student, recipeId) {
            if (!student.restriction_ids || student.restriction_ids.length === 0) return null;
            const rec = recipes.find(r => r.recipe_id === recipeId);
            if (!rec) return null;
            const recIds = rec.restriction_ids ? String(rec.restriction_ids).split(',').map(s => s.trim()) : [];
            const stuIds = Array.isArray(student.restriction_ids) ? student.restriction_ids.map(String) : String(student.restriction_ids).split(',').map(s => s.trim());
            const stuNames = student.restriction_names ? student.restriction_names.split(',').map(s => s.trim()) : [];

            const conflictMap = { '9': ['17'], '16': ['17'] };
            for (let i = 0; i < stuIds.length; i++) {
                const stuId = stuIds[i];
                if (!stuId) continue;
                if (recIds.includes(stuId)) return stuNames[i] || 'Restriction';
                if (conflictMap[stuId]) {
                    for (const conflictingId of conflictMap[stuId]) {
                        if (recIds.includes(conflictingId)) return stuNames[i] || 'Restriction';
                    }
                }
            }
            return null;
        }

        window.toggleAcc = (id) => { document.getElementById(id).classList.toggle('expanded'); };

        window.selectDate = async (dStr) => {
            if (reschedulingMode) { runReschedule(dStr); return; }
            AlgoModal.close(); // Close calendar if open

            currentSelDate = dStr;
            const titleEl = document.getElementById('wsDateTitle');
            if (titleEl) titleEl.innerText = new Date(dStr).toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' });

            const body = document.getElementById('wsBody');
            if (body) body.innerHTML = `
                <div style="text-align:center; padding: 6rem 0;">
                    <div class="loader" style="margin-bottom: 1.5rem;"></div>
                    <div style="font-weight:900; color:var(--text-muted); font-size:1.1rem; letter-spacing:1px; text-transform:uppercase;">Decoding Strategy Portfolio...</div>
                </div>
            `;
            const actions = document.getElementById('wsActions');
            if (actions) actions.innerHTML = '';


            try {
                const res = await fetch(`api_get_day_plan.php?date=${dStr}`);
                const data = await res.json();
                if (data.success && data.has_plan) {
                    currentPlan = data;
                    renderPlanUI();
                } else {
                    currentPlan = null;
                    renderEmptyUI();
                }
            } catch (e) {
                if (body) body.innerHTML = `<div style="text-align:center; color:red;">Connection error.</div>`;
            }
        };

        function renderEmptyUI() {
            document.getElementById('wsStatus').innerHTML = '<span class="material-icons" style="font-size:14px; color:var(--error);">error_outline</span> Unplanned Strategy Area';
            document.getElementById('wsActions').innerHTML = `
                <button class="btn-m3 btn-m3-primary" onclick="generateToday()"><span class="material-icons" style="font-size:18px;">auto_fix_high</span> Generate Meal Plan</button>
                <button class="btn-m3 btn-m3-tonal" onclick="openBulkGenerateModal()"><span class="material-icons" style="font-size:18px;">auto_awesome</span> Bulk Generate Meal Plans</button>
            `;
            document.getElementById('wsBody').innerHTML = `
                <div class="empty-state" style="padding: 10rem 0;">
                    <span class="material-icons" style="font-size: 80px; color: var(--border); margin-bottom: 2rem; opacity: 0.4;">event_busy</span>
                    <h3 style="margin: 0; font-size: 1.5rem; color: var(--text-main); font-weight:900;">No Plans For Today</h3>
                    <p style="color: var(--text-muted); font-size: 1.1rem; margin-top:1rem; max-width: 450px;">This date has not been planned yet. Click the buttons above to generate the meal plans.</p>
                </div>
            `;
        }


        function renderPlanUI() {
            const actions = document.getElementById('wsActions');
            if (currentPlan.is_served) {
                document.getElementById('wsStatus').innerHTML = '<span style="color:var(--success);"><span class="material-icons" style="font-size:14px; vertical-align:middle;">verified</span> Mission Secured & Verified</span>';
                actions.innerHTML = `<button class="btn-m3 btn-m3-outline" onclick="unlockPlan()"><span class="material-icons" style="font-size:18px;">lock_open</span> Unlock Strategy</button>`;
            } else {
                document.getElementById('wsStatus').innerHTML = '<span style="color:var(--primary);"><span class="material-icons" style="font-size:14px; vertical-align:middle;">settings</span> Strategy Draft Active</span>';
                actions.innerHTML = `
                    <button class="btn-m3 btn-m3-primary" onclick="lockPlan()"><span class="material-icons" style="font-size:18px;">check_circle</span> Finalize & Serve</button>
                    <button class="btn-m3 btn-m3-outline" onclick="openEditRecipesModal()"><span class="material-icons" style="font-size:18px;">edit</span> Edit Components</button>
                    <button class="btn-m3 btn-m3-outline" onclick="openRescheduleModal()"><span class="material-icons" style="font-size:18px;">event_repeat</span> Reschedule Date</button>
                    <button class="btn-m3 btn-m3-danger" onclick="deletePlan()"><span class="material-icons" style="font-size:18px;">delete</span> Purge</button>
                `;
            }


            let html = `<div class="meal-cards-container">`;
            let swapAtoB = currentPlan.meal_b && !currentPlan.is_served ? { to: 'b' } : null;
            let swapBtoA = !currentPlan.is_served ? { to: 'a' } : null;

            html += buildFullMealCard('A', currentPlan.meal_a, currentPlan.meal_a_list, swapAtoB);
            if (currentPlan.meal_b) {
                html += buildFullMealCard('B', currentPlan.meal_b, currentPlan.meal_b_list, swapBtoA);
            }

            html += `</div>`;

            if (currentPlan.snack) {
                html += buildSnackCard(currentPlan.snack);
            }
            document.getElementById('wsBody').innerHTML = html;
        }

        function buildFullMealCard(type, recipeId, list, targetListParams) {
            if (!recipeId) return '';
            const rec = recipes.find(r => r.recipe_id === recipeId);
            const color = rec ? (rec.hex_color || '#3b82f6') : '#3b82f6';
            const name = rec ? rec.recipe_name : 'Unknown';

            let tagsHtml = '';
            if (rec && rec.allergens) {
                rec.allergens.split(',').forEach(a => { tagsHtml += `<span class="tag alert-tag">${a}</span>`; });
            } else {
                tagsHtml = '<span class="tag">No Major Allergens</span>';
            }

            let studentsHtml = '';
            if (list) {
                list.sort((a, b) => a.name.localeCompare(b.name));
                list.forEach(s => {
                    const conflictName = checkConflict(s, recipeId);
                    const warnHtml = conflictName ? `<span style="color:#b91c1c; font-size:10px; font-weight:800; background:#fee2e2; padding:1px 4px; border-radius:4px; margin-left:4px;">⚠ ${conflictName.toUpperCase()}</span>` : '';
                    const status = s.feeding_status || 'Served';
                    const hasMilk = s.with_milk === 1;
                    const milkAllowed = s.milk_consent === 1 && !s.restriction_ids.map(Number).includes(1);
                    const hasSnack = s.with_snack === 1;
                    const snackAllowed = currentPlan.snack ? !checkConflict(s, currentPlan.snack) : true;

                    const statusHtml = `
                    <div style="display:flex; align-items: center; gap: 6px; margin-right: 12px;">
                        <div style="display:flex; gap: 4px; border-right: 1px solid var(--border); padding-right: 8px;">
                            <button onclick="updateFeeding('${s.id}', 'Served')" class="btn-status-toggle ${status === 'Served' ? 'active' : ''}" title="Served" style="--btn-color: #10b981;"><span class="material-icons" style="font-size: 14px;">done</span></button>
                            <button onclick="updateFeeding('${s.id}', 'Absent')" class="btn-status-toggle ${status === 'Absent' ? 'active' : ''}" title="Absent" style="--btn-color: #ef4444;"><span class="material-icons" style="font-size: 14px;">close</span></button>
                        </div>
                        <div style="display:flex; gap: 4px;">
                            <button onclick="toggleMilk('${s.id}', ${hasMilk ? 0 : 1})" 
                                    class="btn-milk-toggle ${hasMilk ? 'active' : ''}" 
                                    ${!milkAllowed ? 'disabled' : ''} 
                                    title="${!milkAllowed ? 'Milk Restriction Active' : (hasMilk ? 'Milk Served' : 'Add Milk')}">
                                <span class="material-icons" style="font-size: 16px;">${hasMilk ? 'water_drop' : 'local_drink'}</span>
                            </button>
                            <button onclick="toggleSnack('${s.id}', ${hasSnack ? 0 : 1})" 
                                    class="btn-milk-toggle ${hasSnack ? 'active' : ''}" 
                                    ${!snackAllowed ? 'disabled' : ''} 
                                    style="color: ${hasSnack ? '#f59e0b' : (!snackAllowed ? '#cbd5e1' : '#94a3b8')}; border-color: ${hasSnack ? '#f59e0b' : '#e2e8f0'}; background: ${hasSnack ? '#fffbeb' : (!snackAllowed ? '#f1f5f9' : '#fff')};"
                                    title="${!snackAllowed ? 'Snack Restriction Active' : (hasSnack ? 'Snack Served' : 'Add Snack')}">
                                <span class="material-icons" style="font-size: 16px;">${hasSnack ? 'bakery_dining' : 'cookie'}</span>
                            </button>
                        </div>
                    </div>`;

                    let swapHtml = targetListParams ? `<button onclick="swapStud('${s.id}', '${targetListParams.to}')" style="background:none; border:none; color:var(--text-muted); cursor:pointer;"><span class="material-icons" style="font-size:16px;">swap_horiz</span></button>` : '';

                    studentsHtml += `
                    <div class="student-list-item" style="align-items: center; ${conflictName ? 'background:#fffbeb;' : ''}">
                        <div style="flex: 1;">
                            <span style="font-weight: 700;">${s.name}</span> <span style="color:var(--text-muted); font-size:0.7rem;">${s.section}</span>
                            ${warnHtml}
                        </div>
                        ${statusHtml}${swapHtml}
                    </div>`;
                });
            }

            return `
            <div class="meal-card-x" style="border-top-color: ${color};">
                <div style="font-size:0.75rem; font-weight:800; color:${color}; text-transform:uppercase;">Meal ${type}</div>
                <h3 style="margin: 0.25rem 0 1rem 0; color:var(--text-main); font-size:1.1rem;">${name}</h3>
                <div class="macro-grid">
                    <div class="macro-box"><div class="macro-lbl">CALORIES</div><div class="macro-val">${rec ? rec.energy_kcal : '--'}</div></div>
                    <div class="macro-box"><div class="macro-lbl">PROTEIN</div><div class="macro-val">${rec ? Number(rec.protein_g).toFixed(1) + 'g' : '--'}</div></div>
                </div>
                <div class="tag-list" style="margin-bottom:1rem;">${tagsHtml}</div>
                <div class="student-accordion expanded" id="acc${type}">
                    <div class="accordion-header" onclick="toggleAcc('acc${type}')"><span>Students (${list ? list.length : 0})</span><span class="material-icons">expand_more</span></div>
                    <div class="accordion-body">${studentsHtml}</div>
                </div>
            </div>`;
        }

        function buildSnackCard(snackId) {
            const rec = recipes.find(r => r.recipe_id === snackId);
            if (!rec) return '';
            return `
            <div style="margin-top: 1.5rem; background: #fffbeb; border: 2px dashed #fcd34d; padding: 1.25rem; border-radius: 12px; display:flex; align-items:center; gap: 1.5rem;">
                <div style="background:#f59e0b; width: 48px; height: 48px; border-radius: 12px; display:flex; align-items:center; justify-content:center; color:white;">
                    <span class="material-icons" style="font-size: 24px;">bakery_dining</span>
                </div>
                <div>
                    <div style="font-size:0.75rem; font-weight:800; color:#b45309; text-transform:uppercase;">Daily Snack Attachment</div>
                    <h3 style="margin: 0.15rem 0 0 0; color:#78350f; font-size:1.1rem; font-weight: 900;">${rec.recipe_name}</h3>
                </div>
            </div>`;
        }

        window.generateToday = async () => {
            if (!currentSelDate) return;
            try {
                const res = await fetch('api_generate_plan.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ date: currentSelDate }) });
                const data = await res.json();
                if (data.success) {
                    renderCalendar();
                    selectDate(currentSelDate);

                    if (window.debugMode && data.debug_data) {
                        let rows = data.debug_data.map(d => `
                            <tr style="border-bottom: 1px solid var(--border);">
                                <td style="padding: 0.5rem; font-weight:700;">${d.name}</td>
                                <td style="padding: 0.5rem; text-align:right; color:#10b981;">${d.variety > 0 ? '+' + d.variety : 0}</td>
                                <td style="padding: 0.5rem; text-align:right; color:#d93025;">${d.category_fatigue}</td>
                                <td style="padding: 0.5rem; text-align:right; color:#3b82f6;">+${d.nutrition}</td>
                                <td style="padding: 0.5rem; text-align:right; color:#d93025;">${d.restrictions}</td>
                                <td style="padding: 0.5rem; text-align:right; font-weight:900;">${d.total}</td>
                            </tr>
                        `).join('');

                        AlgoModal.show({
                            title: '<span style="color:#b91c1c;"><span class="material-icons" style="vertical-align:middle;">bug_report</span> Sandbox: Heuristic Math</span>',
                            body: `<table style="width:100%; border-collapse:collapse; font-size:0.8rem;">
                                <thead>
                                    <tr style="color:var(--text-muted); font-size:0.65rem; border-bottom:2px solid var(--border);">
                                        <th style="padding:0.5rem; text-align:left;">RECIPE</th>
                                        <th style="padding:0.5rem; text-align:right;">VARIETY</th>
                                        <th style="padding:0.5rem; text-align:right;">FATIGUE</th>
                                        <th style="padding:0.5rem; text-align:right;">MACROS</th>
                                        <th style="padding:0.5rem; text-align:right;">RESTRICTIONS</th>
                                        <th style="padding:0.5rem; text-align:right;">TOTAL</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>`,
                            footer: `<button class="btn-m3 btn-m3-outline" onclick="AlgoModal.close()">Close Logs</button>`
                        });
                    }
                }
            } catch (e) { }
        };

        window.openBulkGenerateModal = () => {
            const body = `
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border); margin-bottom: 1rem;">
                    <p style="margin: 0 0 1rem 0; font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">The Heuristic Engine will automatically analyze restrictions, optimize budget limits, and sequentially deploy meals across the selected timeframe.</p>
                    <div style="display:flex; flex-direction:column; gap: 1rem;">
                        <div><label style="display:block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem; color:var(--text-main);">Start Date Deployment</label><input type="date" id="blkStart" value="${currentSelDate || ''}" style="width:100%; border:1px solid var(--border); padding:0.6rem; border-radius:8px; font-weight:600;"></div>
                        <div><label style="display:block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.25rem; color:var(--text-main);">Consecutive Days</label><input type="number" id="blkDays" value="5" style="width:100%; border:1px solid var(--border); padding:0.6rem; border-radius:8px; font-weight:600;"></div>
                        
                        <div style="margin-top: 0.5rem;">
                            <label style="display:block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.5rem; color:var(--text-main);">Active Mission Days</label>
                            <div style="display:flex; gap: 0.5rem; flex-wrap: wrap;">
                                <label style="font-size:0.75rem; font-weight:600; display:flex; align-items:center; gap:4px;"><input type="checkbox" class="blk-day-cb" value="1" checked style="accent-color:var(--primary);"> Mon</label>
                                <label style="font-size:0.75rem; font-weight:600; display:flex; align-items:center; gap:4px;"><input type="checkbox" class="blk-day-cb" value="2" checked style="accent-color:var(--primary);"> Tue</label>
                                <label style="font-size:0.75rem; font-weight:600; display:flex; align-items:center; gap:4px;"><input type="checkbox" class="blk-day-cb" value="3" checked style="accent-color:var(--primary);"> Wed</label>
                                <label style="font-size:0.75rem; font-weight:600; display:flex; align-items:center; gap:4px;"><input type="checkbox" class="blk-day-cb" value="4" checked style="accent-color:var(--primary);"> Thu</label>
                                <label style="font-size:0.75rem; font-weight:600; display:flex; align-items:center; gap:4px;"><input type="checkbox" class="blk-day-cb" value="5" checked style="accent-color:var(--primary);"> Fri</label>
                            </div>
                        </div>

                        <div style="display:flex; align-items:center; gap: 0.5rem; border-top: 1px dashed var(--border); padding-top: 1rem; margin-top: 0.25rem; font-size: 0.85rem; font-weight: 600;"><input type="checkbox" id="blkOver" style="width:16px; height:16px; accent-color:var(--primary);"> Override Conflicting Plans</div>
                    </div>
                </div>
            `;
            AlgoModal.show({
                title: '<span style="display:flex; align-items:center; gap:8px;"><span class="material-icons" style="color:var(--primary);">auto_awesome</span> Bulk Generation Protocol</span>',
                body: body,
                footer: `<button class="btn-m3 btn-m3-outline" onclick="AlgoModal.close()">Cancel</button><button class="btn-m3 btn-m3-primary" onclick="runBulkGenerate()"><span class="material-icons" style="font-size:16px;">bolt</span> Initialize Engine</button>`
            });
        };

        window.runBulkGenerate = async () => {
            const start = document.getElementById('blkStart').value;
            const days = document.getElementById('blkDays').value;
            const overw = document.getElementById('blkOver').checked;
            const weekdays = Array.from(document.querySelectorAll('.blk-day-cb:checked')).map(cb => parseInt(cb.value));

            if (weekdays.length === 0) return AlgoModal.alert('Strategy Warning', 'Please select at least one active mission day.');

            AlgoModal.close();
            try {
                const res = await fetch('api_bulk_generate.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ start_date: start, days_count: days, weekdays: weekdays, overwrite: overw }) });
                const data = await res.json();
                if (data.success) {
                    renderCalendar();
                    if (currentSelDate) selectDate(currentSelDate);
                    refreshBudgetBar();

                    // Show Summary Modal
                    let detailsHtml = '<div style="max-height: 400px; overflow-y: auto; background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid var(--border);">';
                    if (data.details && data.details.length > 0) {
                        data.details.forEach(d => {
                            const recA = recipes.find(r => r.recipe_id === d.meal_a);
                            const recB = d.meal_b ? recipes.find(r => r.recipe_id === d.meal_b) : null;
                            detailsHtml += `
                                <div style="display:flex; justify-content:space-between; align-items:center; padding: 0.75rem 0; border-bottom: 1px dashed var(--border);">
                                    <div>
                                        <div style="font-weight:900; color:var(--text-main); font-size:0.85rem;">${new Date(d.date).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}</div>
                                        <div style="font-size:0.75rem; color:var(--text-muted); font-weight:600;">Managed deployment confirmed</div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:0.7rem; font-weight:800; color:var(--primary);">${recA ? recA.recipe_name : 'Unknown'}</div>
                                        ${recB ? `<div style="font-size:0.7rem; font-weight:800; color:var(--text-muted);">${recB.recipe_name}</div>` : ''}
                                    </div>
                                </div>
                            `;
                        });
                    } else {
                        detailsHtml += '<div style="text-align:center; padding:2rem; color:var(--text-muted);">No new plans were created (Items may have been skipped due to existing records).</div>';
                    }
                    detailsHtml += '</div>';

                    AlgoModal.show({
                        title: '<span style="display:flex; align-items:center; gap:8px;"><span class="material-icons" style="color:var(--success);">verified</span> Deployment Summary</span>',
                        body: `
                            <div style="margin-bottom:1rem; font-weight:700; color:var(--text-main); font-size:1rem;">${data.message}</div>
                            ${detailsHtml}
                        `,
                        footer: '<button class="btn-m3 btn-m3-primary" onclick="AlgoModal.close()">Mission Acknowledged</button>'
                    });
                }
            } catch (e) { }
        };

        async function refreshBudgetBar() {
            try {
                const res = await fetch('../../api_budget_status.php');
                const d = await res.json();
                if (!d) return;
                const pct = d.pct;
                const color = pct >= 90 ? '#ef4444' : (pct >= 70 ? '#f59e0b' : '#10b981');
                const bgBadge = pct >= 90 ? '#fee2e2' : (pct >= 70 ? '#fef3c7' : '#d1fae5');
                const bar = document.getElementById('budgetBar');
                if (!bar) return;
                bar.querySelector('.budget-pct-badge').style.background = bgBadge;
                bar.querySelector('.budget-pct-badge').style.color = color;
                bar.querySelector('.budget-pct-badge').textContent = pct + '% Used';
                bar.querySelector('.budget-spent-val').textContent = '₱' + Number(d.spent).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                bar.querySelector('.budget-remaining-val').style.color = color;
                bar.querySelector('.budget-remaining-val').textContent = '₱' + Number(d.remaining).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                const fill = document.getElementById('budgetBarFill');
                if (fill) { fill.style.width = Math.min(pct, 100) + '%'; fill.style.background = color; }
            } catch (e) { }
        }

        window.openRescheduleModal = () => {
            let tempDate = new Date(currentSelDate);
            const renderResCal = (y, m) => {
                const first = new Date(y, m, 1).getDay();
                const last = new Date(y, m + 1, 0).getDate();
                const mName = new Date(y, m).toLocaleString('en-US', { month: 'long', year: 'numeric' });
                let html = `
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;"><button class="btn-m3 btn-m3-outline" style="padding:4px 8px;" onclick="AlgoModal.prevMonth()"><span class="material-icons">chevron_left</span></button><div style="font-weight:900; color:var(--text-main); font-size:1.1rem;">${mName}</div><button class="btn-m3 btn-m3-outline" style="padding:4px 8px;" onclick="AlgoModal.nextMonth()"><span class="material-icons">chevron_right</span></button></div>
                    <div class="big-cal-matrix" style="grid-template-columns: repeat(7, 1fr); gap: 6px; margin-top:0;">`;
                for (let i = 0; i < first; i++) html += `<div class="cal-day disabled"></div>`;
                for (let i = 1; i <= last; i++) {
                    const d = `${y}-${(m + 1).toString().padStart(2, '0')}-${i.toString().padStart(2, '0')}`;
                    html += `<div class="cal-day" style="padding:8px; display:flex; align-items:center; justify-content:center; font-size:1rem; font-weight:800;" onclick="runReschedule('${d}')">${i}</div>`;
                }
                html += `</div>`;
                document.getElementById('resCalArea').innerHTML = html;
            };
            AlgoModal.show({ title: 'Reschedule Plan Date', body: `<div id="resCalArea" style="min-height:300px; padding-top:0.5rem;"></div>`, footer: `<button class="btn-m3 btn-m3-outline" onclick="AlgoModal.close()">Cancel Migration</button>` });
            AlgoModal.resDate = tempDate;
            AlgoModal.prevMonth = () => { AlgoModal.resDate.setMonth(AlgoModal.resDate.getMonth() - 1); renderResCal(AlgoModal.resDate.getFullYear(), AlgoModal.resDate.getMonth()); };
            AlgoModal.nextMonth = () => { AlgoModal.resDate.setMonth(AlgoModal.resDate.getMonth() + 1); renderResCal(AlgoModal.resDate.getFullYear(), AlgoModal.resDate.getMonth()); };
            renderResCal(tempDate.getFullYear(), tempDate.getMonth());
        };

        window.runReschedule = async (newD) => {
            if (!newD || newD === currentSelDate) { AlgoModal.close(); return; }
            if (!await AlgoModal.confirm('Move Plan', `Are you sure you want to move this plan to ${newD}?`)) return;
            AlgoModal.close();
            try { fetch('api_reschedule_plan.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ old_date: currentSelDate, new_date: newD }) }).then(() => { renderCalendar(); selectDate(newD); }); } catch (e) { }
        };

        window.deletePlan = async () => {
            if (!await AlgoModal.confirm('Clear Plan', 'Delete the meal plan for this date?', 'danger')) return;
            try { fetch('api_delete_plan.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ date: currentSelDate }) }).then(() => { renderCalendar(); selectDate(currentSelDate); }); } catch (e) { }
        };

        window.updateFeeding = async (studId, status) => {
            if (currentPlan.is_served) return;
            const updateInList = (list) => { let s = list.find(x => x.id === studId); if (s) s.feeding_status = status; };
            if (currentPlan.meal_a_list) updateInList(currentPlan.meal_a_list);
            if (currentPlan.meal_b_list) updateInList(currentPlan.meal_b_list);
            renderPlanUI();
            try { fetch('api_update_status.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ student_id: studId, date: currentSelDate, status: status }) }); } catch (e) { }
        };

        window.toggleMilk = async (studId, state) => {
            if (currentPlan.is_served) return;
            const updateInList = (list) => { let s = list.find(x => x.id === studId); if (s) s.with_milk = state; };
            if (currentPlan.meal_a_list) updateInList(currentPlan.meal_a_list);
            if (currentPlan.meal_b_list) updateInList(currentPlan.meal_b_list);
            renderPlanUI();
            try { fetch('api_update_milk.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ student_id: studId, date: currentSelDate, with_milk: state }) }); } catch (e) { }
        };

        window.toggleSnack = async (studId, state) => {
            if (currentPlan.is_served) return;
            const updateInList = (list) => { let s = list.find(x => x.id === studId); if (s) s.with_snack = state; };
            if (currentPlan.meal_a_list) updateInList(currentPlan.meal_a_list);
            if (currentPlan.meal_b_list) updateInList(currentPlan.meal_b_list);
            renderPlanUI();
            try { fetch('api_update_snack.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ student_id: studId, date: currentSelDate, with_snack: state }) }); } catch (e) { }
        };

        window.swapStud = async (studId, toList) => {
            let s = null; let fromIdx = -1;
            if (currentPlan.meal_a_list && (fromIdx = currentPlan.meal_a_list.findIndex(x => x.id === studId)) > -1) s = currentPlan.meal_a_list.splice(fromIdx, 1)[0];
            else if (currentPlan.meal_b_list && (fromIdx = currentPlan.meal_b_list.findIndex(x => x.id === studId)) > -1) s = currentPlan.meal_b_list.splice(fromIdx, 1)[0];
            if (!s) return;
            let targetRecipe = (toList === 'a') ? currentPlan.meal_a : currentPlan.meal_b;
            if (toList === 'a') currentPlan.meal_a_list.push(s); else currentPlan.meal_b_list.push(s);
            renderPlanUI();
            try { fetch('api_update_assignment.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ student_id: studId, date: currentSelDate, recipe_id: targetRecipe }) }); } catch (e) { }
        };

        window.lockPlan = async () => {
            const servedCount = (currentPlan.meal_a_list ? currentPlan.meal_a_list.filter(s => (s.feeding_status || 'Served') === 'Served').length : 0) +
                (currentPlan.meal_b_list ? currentPlan.meal_b_list.filter(s => (s.feeding_status || 'Served') === 'Served').length : 0);

            if (servedCount === 0) return AlgoModal.alert('Strategy Warning', 'Cannot finalize a plan with zero served students.', 'danger');

            const body = `
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border);">
                    <p style="margin: 0 0 1rem 0; font-size: 0.85rem; color: var(--text-muted); font-weight: 600;">To ensure accurate fiscal reporting in the Management Hub, please enter the total actual expenditure for today's feeding session.</p>
                    <div style="display:flex; flex-direction:column; gap: 0.5rem;">
                        <label style="display:block; font-size: 0.75rem; font-weight: 700; color:var(--text-main);">ACTUAL TOTAL COST (₱)</label>
                        <div style="position:relative;">
                            <span style="position:absolute; left: 12px; top: 50%; transform: translateY(-50%); font-weight:700; color:var(--text-muted);">₱</span>
                            <input type="number" id="actualTotalCost" step="0.01" value="${(servedCount * 27).toFixed(2)}" style="width:100%; border:1px solid var(--border); padding:0.6rem; padding-left: 1.8rem; border-radius:8px; font-weight:900; font-size:1.1rem; color:var(--primary);">
                        </div>
                        <p style="margin-top:0.5rem; font-size:0.75rem; font-weight:600; color:var(--text-muted);">This amount will be distributed among ${servedCount} served beneficiaries.</p>
                    </div>
                </div>
            `;

            AlgoModal.show({
                title: '<span style="display:flex; align-items:center; gap:8px;"><span class="material-icons" style="color:var(--success);">verified</span> Verify & Secure Mission</span>',
                body: body,
                footer: `<button class="btn-m3 btn-m3-outline" onclick="AlgoModal.close()">Return to Draft</button><button class="btn-m3 btn-m3-primary" onclick="runLockPlan()"><span class="material-icons" style="font-size:16px;">lock</span> Finalize Consumption</button>`
            });
        };

        window.runLockPlan = async () => {
            const cost = document.getElementById('actualTotalCost').value;
            if (!cost || cost <= 0) return AlgoModal.alert('Data Error', 'Please enter a valid total cost.');

            AlgoModal.close();
            try {
                fetch('api_lock_plan.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ date: currentSelDate, total_cost: parseFloat(cost) })
                }).then(() => { renderCalendar(); selectDate(currentSelDate); refreshBudgetBar(); });
            } catch (e) { }
        };

        window.unlockPlan = async () => {
            if (!await AlgoModal.confirm('Unlock Plan', 'Revert this plan to draft?')) return;
            try { fetch('api_unlock_plan.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ date: currentSelDate }) }).then(() => { renderCalendar(); selectDate(currentSelDate); }); } catch (e) { }
        };

        window.openEditRecipesModal = () => {
            let options = recipes.filter(r => r.category !== 'Snack').map(r => `<option value="${r.recipe_id}">${r.recipe_name}</option>`).join('');
            let snackOptions = recipes.filter(r => r.category === 'Snack').map(r => `<option value="${r.recipe_id}">${r.recipe_name}</option>`).join('');

            const body = `
                <div style="background: #f8fafc; padding: 1.5rem; border-radius: 8px; border: 1px solid var(--border); display:flex; flex-direction:column; gap:1.25rem;">
                    <div><label style="display:block; font-size: 0.75rem; font-weight: 700; color:var(--primary); margin-bottom: 0.4rem; text-transform:uppercase;">Primary Profile (Meal A)</label><select id="erMealA" style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:8px; font-weight:600; cursor:pointer;">${options}</select></div>
                    <div><label style="display:block; font-size: 0.75rem; font-weight: 700; color:var(--text-muted); margin-bottom: 0.4rem; text-transform:uppercase;">Secondary Mission (Meal B)</label><select id="erMealB" style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:8px; font-weight:600; cursor:pointer;"><option value="">-- No Secondary Option --</option>${options}</select></div>
                    <div style="border-top:1px dashed var(--border); padding-top:1rem;"><label style="display:block; font-size: 0.75rem; font-weight: 700; color:#d97706; margin-bottom: 0.4rem; text-transform:uppercase;">Appended Snack Attachment</label><select id="erSnack" style="width:100%; padding:0.6rem; border:1px solid #fcd34d; border-radius:8px; font-weight:600; cursor:pointer;"><option value="">-- Random Snack Generated --</option>${snackOptions}</select></div>
                </div>
            `;
            AlgoModal.show({ title: 'Edit Mission Components', body: body, footer: `<button class="btn-m3 btn-m3-outline" onclick="AlgoModal.close()">Cancel</button><button class="btn-m3 btn-m3-primary" onclick="runUpdateRecipes()">Override Profiles</button>` });
            document.getElementById('erMealA').value = currentPlan.meal_a;
            if (currentPlan.meal_b) document.getElementById('erMealB').value = currentPlan.meal_b;
            if (currentPlan.snack) document.getElementById('erSnack').value = currentPlan.snack;
        };

        window.runUpdateRecipes = async () => {
            const mA = document.getElementById('erMealA').value;
            const mB = document.getElementById('erMealB').value;
            const mS = document.getElementById('erSnack').value;
            AlgoModal.close();
            try { fetch('api_update_day_recipes.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ date: currentSelDate, meal_a: mA, meal_b: mB, snack: mS }) }).then(() => { renderCalendar(); selectDate(currentSelDate); }); } catch (e) { }
        };

        // Initialize
        await renderCalendar();
        let todayStr = new Date().toLocaleDateString('en-CA');
        selectDate(todayStr);
    });
</script>

<?php require_once '../../includes/footer.php'; ?>
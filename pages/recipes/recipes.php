<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>
<?php
$page_title = 'Recipe Database';
require_once '../../includes/topbar.php';
$isAdmin = ($role === 'Admin');
?>

<style>
    .filter-pill {
        padding: 0.5rem 1.25rem; background: var(--surface); border: 1px solid var(--border);
        border-radius: 99px; font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all 0.2s; color: var(--text-muted);
    }
    .filter-pill.active { background: var(--primary); border-color: var(--primary); color: white; }
    
    .recipe-card {
        background: var(--surface); border-radius: 12px; overflow: hidden; border: 1px solid var(--border);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); cursor: pointer; display: flex; flex-direction: column;
    }
    .recipe-card:hover { transform: translateY(-8px); box-shadow: 0 12px 24px rgba(0,0,0,0.1); border-color: var(--primary); }
    .recipe-card .card-img { height: 12px; position: relative; }
    .recipe-card .card-content { padding: 1.25rem; flex: 1; display: flex; flex-direction: column; }
    
    .badge-allergen {
        background: #fee2e2; color: #b91c1c; font-size: 0.65rem; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 700; text-transform: uppercase;
    }

    /* MODAL POLISH */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 1000; }
    .modal-overlay.active { display: flex; }
    .modal { background: white; border-radius: 16px; box-shadow: var(--shadow-lg); position: relative; overflow: hidden; }
    .modal-title { font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 1.5rem; }
    
    .btn-modal-close {
        position: absolute; top: 12px; right: 12px; width: 40px; height: 40px; 
        background: rgba(0,0,0,0.03); border: none; color: white; border-radius: 10px;
        display: flex; align-items: center; justify-content: center; cursor: pointer; 
        transition: all 0.2s; z-index: 100;
    }
    .btn-modal-close:hover { background: rgba(0,0,0,0.08); transform: translateY(-1px); }
    .btn-modal-close .material-icons { font-size: 20px; color: var(--text-muted); opacity: 0.6; }
    .btn-modal-close:hover .material-icons { opacity: 1; }

    /* Swatch System Updated */
    .swatch-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 0.6rem; margin-top: 0.75rem; }
    .swatch { 
        width: 100%; aspect-ratio: 1; border-radius: 8px; cursor: pointer; border: 3px solid white; 
        transition: all 0.2s; box-shadow: var(--shadow-sm); 
    }
    .swatch:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
    .swatch.active { border-color: var(--primary); transform: scale(1.05); }

    .modal-detail-header { 
        position: relative; background: white; 
        box-shadow: 0 4px 12px rgba(0,0,0,0.03);
        z-index: 100; flex-shrink: 0;
    }
    #modalHero {
        height: 8px; width: 100%; position: relative; 
    }
    .modal-body-header { padding: 1.5rem 2.5rem; }



</style>

<div class="content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
        <div>
            <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">Recipe Database</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Browse and manage nutritional-compliant meal recipes.</p>

        </div>
        <div style="display: flex; gap: 0.75rem;">
            <div style="position: relative; width: 300px;">
                <span class="material-icons" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 1.25rem; color: var(--text-muted);">search</span>
                <input type="text" id="recipeSearch" placeholder="Search recipes..." style="width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; outline: none; transition: all 0.2s;">
            </div>
            <?php if ($isAdmin): ?>
            <button class="btn" style="background: var(--primary); color: white; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; font-weight: 600;" onclick="openAddRecipeModal()">
                <span class="material-icons">add</span> New Recipe
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recipe Grid -->
    <div id="recipeGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
        <!-- Cards will be injected here via JS -->
    </div>
</div>

<!-- Add/Edit Modal -->
<div class="modal-overlay" id="addRecipeModal">
    <div class="modal" style="max-width: 650px; width: 95%; max-height: 90vh; overflow-y: auto; padding: 2rem;">
        <h2 class="modal-title">Add New Recipe</h2>
        <form id="recipeForm">
            <input type="hidden" name="action" id="recipeAction" value="add">
            <input type="hidden" name="recipe_id" id="form_recipe_id">
            
            <div style="display: grid; grid-template-columns: 1fr 120px; gap: 1.5rem; margin-bottom: 1.5rem;">
                <div>
                    <label style="display:block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Recipe Name</label>
                    <input type="text" name="recipe_name" id="form_name" required style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px; font-weight: 700;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Custom Color</label>
                    <input type="color" name="hex_color" id="form_color" value="#3b82f6" style="width: 100%; height: 48px; padding: 4px; border: 1px solid var(--border); border-radius: 8px; cursor: pointer;">
                </div>
            </div>

            <div style="margin-bottom: 2rem;">
                <label style="display:block; font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Quick Preset Palette</label>
                <div class="swatch-grid">
                    <?php 
                    $presets = [
                        '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#ec4899', '#64748b',
                        '#0ea5e9', '#059669', '#d97706', '#dc2626', '#7c3aed', '#db2777', '#475569'
                    ];
                    foreach($presets as $p): ?>
                        <div class="swatch" style="background: <?= $p ?>" onclick="selectSwatch('<?= $p ?>', this)"></div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Brief Description</label>
                <textarea name="description" id="form_desc" rows="2" style="width: 100%; padding: 0.75rem; border: 1px solid var(--border); border-radius: 8px;"></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1.2fr; gap: 1rem; margin-bottom: 1.5rem;">
                <div class="form-field">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.4rem;">Energy (kcal)</label>
                    <input type="number" name="energy_kcal" id="form_kcal" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
                </div>
                <div class="form-field">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.4rem;">Protein (g)</label>
                    <input type="number" step="0.1" name="protein_g" id="form_protein" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
                </div>
                <div class="form-field">
                    <label style="display:block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.4rem;">Base Cost/Student (PHP)</label>
                    <input type="number" step="0.01" name="cost" id="form_cost" required style="width:100%; padding:0.6rem; border:1px solid var(--border); border-radius:6px;">
                </div>
            </div>

            <!-- Ingredients & Instructions simplified for space here -->
            <div style="margin-bottom: 1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                    <label style="font-size: 0.875rem; font-weight: 600;">Ingredients</label>
                    <button type="button" onclick="addIngredientRow()" style="background:none; border:none; color:var(--primary); cursor:pointer; font-size: 0.75rem; font-weight:700;">+ Add</button>
                </div>
                <div id="formIngredientsList" style="display:flex; flex-direction:column; gap:0.5rem;"></div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                    <label style="font-size: 0.875rem; font-weight: 600;">Preparation Steps</label>
                    <button type="button" onclick="addInstructionRow()" style="background:none; border:none; color:var(--primary); cursor:pointer; font-size: 0.75rem; font-weight:700;">+ Add</button>
                </div>
                <div id="formInstructionsList" style="display:flex; flex-direction:column; gap:0.5rem;"></div>
            </div>

            <div style="margin-bottom: 2rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Allergen Exclusions</label>
                <div id="restrictionCheckboxes" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; background: var(--bg-color); padding: 1rem; border-radius: 12px;"></div>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 1rem; padding-top: 1.5rem; border-top: 1px solid var(--border);">
                <button type="button" class="btn btn-outline" onclick="closeModal('addRecipeModal')">Cancel</button>
                <button type="submit" class="btn" style="background: var(--primary); color: white; padding: 0.75rem 2rem;">Save Recipe</button>
            </div>
        </form>
    </div>
</div>

<!-- Recipe Detail Modal -->
<div class="modal-overlay" id="recipeDetailModal">
    <div class="modal" style="max-width: 800px; width: 95%; height: 85vh; display: flex; flex-direction: column;">
        <div class="modal-detail-header">
            <button class="btn-modal-close" onclick="closeModal('recipeDetailModal')">
                <span class="material-icons">close</span>
            </button>
            <div id="modalHero"></div>
            <div class="modal-body-header">
                <h2 id="modalTitle" style="color: var(--text-main); font-size: 2.25rem; font-weight: 900; margin: 0 0 0.75rem 0; line-height: 1.1;"></h2>
                <div id="modalBadges" style="display:flex; flex-wrap:wrap; gap:0.5rem;"></div>
            </div>
        </div>

        <div style="flex: 1; overflow-y: auto; padding: 2.5rem; display: grid; grid-template-columns: 280px 1fr; gap: 3rem;">
            <!-- Left: Stats -->
            <div style="border-right: 1px solid var(--border); padding-right: 2rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2.5rem;">
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; text-align: center;">
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-bottom: 0.25rem;">Energy</div>
                        <div id="modalKcal" style="font-size: 1.1rem; font-weight: 900; color: var(--primary);"></div>
                    </div>
                    <div style="background: #f8fafc; padding: 1rem; border-radius: 12px; text-align: center;">
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; margin-bottom: 0.25rem;">Protein</div>
                        <div id="modalProtein" style="font-size: 1.1rem; font-weight: 900; color: var(--primary);"></div>
                    </div>
                </div>

                <h4 style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 1.25rem;">Ingredients Required</h4>
                <ul id="modalIngredients" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.6rem;"></ul>
            </div>

            <!-- Right -->
            <div>
                <p id="modalDesc" style="color: var(--text-muted); font-size: 1.05rem; line-height: 1.6; margin-bottom: 2.5rem;"></p>
                <h4 style="font-size: 0.8rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); margin-bottom: 1.5rem;">Preparation Logic</h4>
                <div id="modalInstructions" style="display: flex; flex-direction: column; gap: 1.5rem;"></div>
            </div>
        </div>

        <!-- Footer -->
        <div style="padding: 1.5rem 2.5rem; background: #f8fafc; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div>
                <div style="font-size: 0.7rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.25rem;">Target Cost per Serving</div>
                <div id="modalCost" style="font-size: 1.25rem; font-weight: 900; color: var(--text-main);"></div>
            </div>
            <div style="display: flex; gap: 0.75rem;">
                <?php if ($isAdmin): ?>
                <button id="btnModifyRecipe" class="btn" style="background: white; border: 2px solid var(--border); color: var(--text-main); font-weight: 700;" onclick="openEditRecipe()">Modify Details</button>
                <button id="btnDeleteRecipe" class="btn" style="background: #fee2e2; border: 2px solid #fecaca; color: #b91c1c; font-weight: 700;" onclick="deleteCurrentRecipe()">Delete Recipe</button>
                <?php endif; ?>
                <button class="btn" style="background: var(--text-main); color: white; padding: 0.75rem 2rem;" onclick="closeModal('recipeDetailModal')">Close Details</button>
            </div>
        </div>
    </div>
</div>

<script>
    let allRecipes = [];
    let currentViewingRecipe = null;
    let restrictionList = [];
    const isAdmin = <?php echo json_encode($isAdmin); ?>;


    function selectSwatch(color, el) {
        document.getElementById('form_color').value = color;
        document.querySelectorAll('.swatch').forEach(s => s.classList.remove('active'));
        el.classList.add('active');
    }

    async function loadRecipes() {
        const grid = document.getElementById('recipeGrid');
        grid.innerHTML = '<div style="grid-column: 1/-1; padding: 4rem; text-align: center; color: var(--text-muted); font-weight: 700;">Querying Repository...</div>';
        
        try {
            const response = await fetch('api_get_recipes.php');
            allRecipes = await response.json();
            renderRecipes(allRecipes);
        } catch (error) { console.error(error); }
    }

    async function loadRestrictions() {
        try {
            const response = await fetch('api_get_restrictions.php');
            const data = await response.json();
            restrictionList = data;
            renderRestrictionCheckboxes();
        } catch (e) {
            console.error('Failed to load restrictions:', e);
            restrictionList = [];
            renderRestrictionCheckboxes();
        }
    }

    function renderRestrictionCheckboxes() {
        const container = document.getElementById('restrictionCheckboxes');
        if (!container) return;
        
        // Use a 3-column grid for better spacing with 16+ items
        container.style.display = 'grid';
        container.style.gridTemplateColumns = 'repeat(3, 1fr)';
        container.style.gap = '0.75rem';

        container.innerHTML = restrictionList.map(r => `
            <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.75rem; cursor:pointer; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="${r.restriction_name}">
                <input type="checkbox" name="restrictions[]" value="${r.restriction_id}"> ${r.restriction_name}
            </label>
        `).join('');
    }

    function addIngredientRow(name = '', amount = '', unit = '') {
        const div = document.createElement('div');
        div.style = "display:grid; grid-template-columns: 2fr 1fr 1fr 32px; gap:0.5rem;";
        div.innerHTML = `
            <input type="text" name="ing_names[]" value="${name}" placeholder="Ingredient" style="padding:0.5rem; border:1px solid var(--border); border-radius:6px; font-size:0.8rem;">
            <input type="text" name="ing_amounts[]" value="${amount}" placeholder="Qty" style="padding:0.5rem; border:1px solid var(--border); border-radius:6px; font-size:0.8rem;">
            <input type="text" name="ing_units[]" value="${unit}" placeholder="Unit" style="padding:0.5rem; border:1px solid var(--border); border-radius:6px; font-size:0.8rem;">
            <button type="button" onclick="this.parentElement.remove()" style="color:#ef4444; background:none; border:none; cursor:pointer;"><span class="material-icons" style="font-size:18px;">delete_outline</span></button>
        `;
        document.getElementById('formIngredientsList').appendChild(div);
    }

    function addInstructionRow(text = '') {
        const div = document.createElement('div');
        div.style = "display:flex; gap:0.5rem;";
        div.innerHTML = `
            <textarea name="instructions[]" placeholder="Describe preparation step..." style="flex:1; padding:0.6rem; border:1px solid var(--border); border-radius:6px; font-size:0.85rem; height:50px; resize:none;">${text}</textarea>
            <button type="button" onclick="this.parentElement.remove()" style="color:#ef4444; background:none; border:none; cursor:pointer;"><span class="material-icons" style="font-size:18px;">delete_outline</span></button>
        `;
        document.getElementById('formInstructionsList').appendChild(div);
    }

    function openAddRecipeModal() {
        document.getElementById('recipeAction').value = 'add';
        document.getElementById('form_recipe_id').value = '';
        document.getElementById('recipeForm').reset();
        document.getElementById('formIngredientsList').innerHTML = '';
        document.getElementById('formInstructionsList').innerHTML = '';
        addIngredientRow();
        addInstructionRow();
        document.querySelector('#addRecipeModal h2').innerText = 'New Recipe';

        document.getElementById('addRecipeModal').classList.add('active');
    }

    function viewRecipe(r) {
        currentViewingRecipe = r;
        const color = r.hex_color || '#3b82f6';
        
        document.getElementById('modalTitle').innerText = r.recipe_name;
        document.getElementById('modalDesc').innerText = r.description || 'No description provided.';
        document.getElementById('modalKcal').innerText = r.energy_kcal + ' kcal';
        document.getElementById('modalProtein').innerText = r.protein_g + 'g';
        document.getElementById('modalCost').innerText = '₱ ' + parseFloat(r.base_cost_per_serving).toFixed(2);
        
        const hero = document.getElementById('modalHero');
        hero.style.backgroundColor = color;
        // Multi-tone effect
        hero.style.backgroundImage = `radial-gradient(circle at 0% 0%, rgba(255,255,255,0.15) 0%, transparent 60%), radial-gradient(circle at 100% 100%, rgba(0,0,0,0.1) 0%, transparent 60%)`;

        // Ingredients
        document.getElementById('modalIngredients').innerHTML = r.ingredients.map(i => `
            <li style="display:flex; justify-content:space-between; padding:0.6rem 0.8rem; background:white; border:1px solid #f1f5f9; border-radius:8px; font-size:0.85rem;">
                <span style="font-weight:700;">${i.name}</span>
                <span style="color:var(--primary); font-weight:800;">${i.amount} ${i.unit}</span>
            </li>
        `).join('');

        // Instructions
        document.getElementById('modalInstructions').innerHTML = r.instructions.map(i => `
            <div style="display:flex; gap:1.25rem;">
                <div style="width:28px; height:28px; background:${color}; color:white; border-radius:8px; display:flex; align-items:center; justify-content:center; font-size:0.8rem; font-weight:900; flex-shrink:0; box-shadow: 0 4px 10px -2px ${color}66;">${i.step_no}</div>
                <div style="font-size:0.95rem; line-height:1.6; color:var(--text-main); font-weight:500;">${i.instruction}</div>
            </div>
        `).join('');

        // Restrictions/Allergens
        const badgesContainer = document.getElementById('modalBadges');
        const allergens = r.allergens ? r.allergens.split(',') : [];
        badgesContainer.innerHTML = allergens.map(a => `
            <span class="badge-allergen" style="background: #fee2e2; color: #b91c1c; font-size: 0.65rem; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: 700; text-transform: uppercase;">${a.trim()}</span>
        `).join('');

        document.getElementById('recipeDetailModal').classList.add('active');
    }


    function openEditRecipe() {
        if (!currentViewingRecipe) return;
        const r = currentViewingRecipe;
        openAddRecipeModal();
        
        document.getElementById('recipeAction').value = 'edit';
        document.getElementById('form_recipe_id').value = r.recipe_id;
        document.getElementById('form_name').value = r.recipe_name;
        document.getElementById('form_desc').value = r.description;
        document.getElementById('form_kcal').value = r.energy_kcal;
        document.getElementById('form_protein').value = r.protein_g;
        document.getElementById('form_cost').value = r.base_cost_per_serving;
        document.getElementById('form_color').value = r.hex_color;

        // Reset ingredients/instructions from view data
        document.getElementById('formIngredientsList').innerHTML = '';
        r.ingredients.forEach(i => addIngredientRow(i.name, i.amount, i.unit));
        document.getElementById('formInstructionsList').innerHTML = '';
        r.instructions.forEach(i => addInstructionRow(i.instruction));

        // Restore restrictions checkboxes
        const ids = r.restriction_ids ? r.restriction_ids.split(',') : [];
        const checkboxes = document.querySelectorAll('#restrictionCheckboxes input[type="checkbox"]');
        checkboxes.forEach(cb => {
            cb.checked = ids.includes(cb.value);
        });

        document.querySelector('#addRecipeModal h2').innerText = 'Modify Recipe Details';
        closeModal('recipeDetailModal');
    }


    function renderRecipes(recipes) {
        const grid = document.getElementById('recipeGrid');
        grid.innerHTML = recipes.map(r => `
            <div class="recipe-card" onclick='viewRecipe(${JSON.stringify(r).replace(/'/g, "&apos;")})'>
                <div style="height: 12px; background: ${r.hex_color || '#3b82f6'}; opacity:0.8;"></div>
                <div class="card-content">
                    <h3 style="font-size:1.1rem; font-weight:900; margin:0 0 0.5rem 0; color:var(--text-main);">${r.recipe_name}</h3>
                    <p style="font-size:0.85rem; color:var(--text-muted); line-height:1.5; margin-bottom:1rem; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden;">${r.description}</p>
                    
                    <div style="display:flex; flex-wrap:wrap; gap:0.4rem; margin-bottom:1.5rem;">
                        ${(r.allergens ? r.allergens.split(',') : []).map(a => `<span class="badge-allergen">${a.trim()}</span>`).slice(0, 3).join('')}
                        ${(r.allergens ? r.allergens.split(',').length : 0) > 3 ? `<span style="font-size:0.65rem; color:var(--text-muted); font-weight:700;">+${r.allergens.split(',').length - 3} more</span>` : ''}
                    </div>

                    <div style="margin-top:auto; display:flex; justify-content:space-between; align-items:flex-end;">

                        <div style="display:flex; gap:0.75rem; font-size:0.75rem; font-weight:800;">
                            <div><span style="display:block; color:var(--text-muted); font-size:0.6rem;">ENERGY</span>${r.energy_kcal}kcal</div>
                            <div><span style="display:block; color:var(--text-muted); font-size:0.6rem;">PROTEIN</span>${r.protein_g}g</div>
                        </div>
                        <div style="text-align:right;">
                            <div style="font-size:0.6rem; font-weight:800; color:var(--text-muted);">FED COST</div>
                            <div style="font-size:1.1rem; font-weight:900; color:${r.hex_color || '#3b82f6'};">&#8369;${parseFloat(r.base_cost_per_serving).toFixed(1)}</div>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
    }

    function closeModal(id) { document.getElementById(id).classList.remove('active'); }

    function deleteCurrentRecipe() {
        if (!currentViewingRecipe) return;
        if (!confirm(`Are you sure you want to permanently delete "${currentViewingRecipe.recipe_name}"? This cannot be undone.`)) return;
        const fd = new FormData();
        fd.append('action', 'delete');
        fd.append('recipe_id', currentViewingRecipe.recipe_id);
        fetch('api_save_recipe.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) { closeModal('recipeDetailModal'); loadRecipes(); }
                else alert('Delete failed: ' + (data.message || 'Unknown error'));
            });
    }

    document.getElementById('recipeForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const res = await fetch('api_save_recipe.php', { method: 'POST', body: new FormData(this) });
        const data = await res.json();
        if (data.success) { closeModal('addRecipeModal'); loadRecipes(); }
    });

    loadRecipes();
    loadRestrictions();
</script>

<?php require_once '../../includes/footer.php'; ?>

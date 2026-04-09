<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<?php
$page_title = 'Recipe Database';
require_once '../../includes/topbar.php';
?>

<div class="content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 2rem;">
        <div>
            <h2 style="font-size: 1.75rem; font-weight: 800; color: var(--text-main); margin-bottom: 0.25rem;">DepEd Approved Recipes</h2>
            <p style="color: var(--text-muted); font-size: 0.875rem;">Explore nutritional-compliant meals for school feeding programs.</p>
        </div>
        <div style="display: flex; gap: 0.75rem;">
            <div style="position: relative; width: 300px;">
                <span class="material-icons" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); font-size: 1.25rem; color: var(--text-muted);">search</span>
                <input type="text" id="recipeSearch" placeholder="Search recipes..." style="width: 100%; padding: 0.6rem 1rem 0.6rem 2.5rem; border: 1px solid var(--border); border-radius: 8px; font-size: 0.875rem; outline: none; transition: all 0.2s;">
            </div>
            <button class="btn" style="background: var(--primary); color: white; display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; font-weight: 600;" onclick="openAddRecipeModal()">
                <span class="material-icons">add</span> New Recipe
            </button>
        </div>
    </div>

    <!-- Recipe Grid -->
    <div id="recipeGrid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1.5rem;">
        <!-- Cards will be injected here via JS -->
    </div>
</div>

<div class="modal-overlay" id="addRecipeModal">
    <div class="modal" style="max-width: 600px; width: 95%; max-height: 90vh; overflow-y: auto;">
        <h2 class="modal-title">Add New Recipe</h2>
        <form id="recipeForm">
            <input type="hidden" name="action" id="recipeAction" value="add">
            <input type="hidden" name="recipe_id" id="form_recipe_id">
            
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Recipe Name</label>
                <input type="text" name="recipe_name" id="form_name" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border); border-radius: 6px;">
            </div>
            
            <div style="margin-bottom: 1rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Description</label>
                <textarea name="description" id="form_desc" rows="3" style="width: 100%; padding: 0.6rem; border: 1px solid var(--border); border-radius: 6px;"></textarea>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 80px; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.4rem;">Energy (kcal)</label>
                    <input type="number" name="energy_kcal" id="form_kcal" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border); border-radius: 6px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.4rem;">Protein (g)</label>
                    <input type="number" step="0.1" name="protein_g" id="form_protein" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border); border-radius: 6px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.4rem;">Cost per serving</label>
                    <input type="number" step="0.1" name="cost" id="form_cost" required style="width: 100%; padding: 0.6rem; border: 1px solid var(--border); border-radius: 6px;">
                </div>
                <div>
                    <label style="display:block; font-size: 0.75rem; font-weight: 600; margin-bottom: 0.4rem;">Tag Color</label>
                    <input type="color" name="hex_color" id="form_color" value="#3b82f6" required style="width: 100%; height: 38px; padding: 0.2rem; border: 1px solid var(--border); border-radius: 6px; cursor: pointer;">
                </div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                    <label style="font-size: 0.875rem; font-weight: 600;">Ingredients</label>
                    <button type="button" onclick="addIngredientRow()" style="background:none; border:none; color:var(--primary); cursor:pointer; font-size: 0.75rem; font-weight:700;">+ Add Ingredient</button>
                </div>
                <div id="formIngredientsList" style="display:flex; flex-direction:column; gap:0.5rem;"></div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 0.5rem;">
                    <label style="font-size: 0.875rem; font-weight: 600;">Preparation Steps</label>
                    <button type="button" onclick="addInstructionRow()" style="background:none; border:none; color:var(--primary); cursor:pointer; font-size: 0.75rem; font-weight:700;">+ Add Step</button>
                </div>
                <div id="formInstructionsList" style="display:flex; flex-direction:column; gap:0.5rem;"></div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display:block; font-size: 0.875rem; font-weight: 600; margin-bottom: 0.5rem;">Restrictions / Allergens</label>
                <div id="restrictionCheckboxes" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; background: var(--bg-color); padding: 1rem; border-radius: 6px;">
                    <!-- Restriction checkboxes injected here -->
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" onclick="closeModal('addRecipeModal')">Cancel</button>
                <button type="submit" class="btn" style="background: var(--primary); color: white;">Save Recipe</button>
            </div>
        </form>
    </div>
</div>

<!-- Recipe Detail Modal -->
<div class="modal-overlay" id="recipeDetailModal">
    <div class="modal" style="max-width: 800px; width: 95%; height: 85vh; padding: 0; overflow: hidden; display: flex; flex-direction: column;">
        <div id="modalHero" style="height: 250px; width: 100%; position: relative; background-size: cover; background-position: center;">
            <div style="position: absolute; inset: 0; background: linear-gradient(0deg, rgba(0,0,0,0.8) 0%, rgba(0,0,0,0) 50%);"></div>
            <button onclick="closeModal('recipeDetailModal')" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.2); border:none; color:white; border-radius: 50%; width: 32px; height:32px; display:flex; align-items:center; justify-content:center; cursor:pointer; backdrop-filter: blur(4px);">
                <span class="material-icons">close</span>
            </button>
            <div style="position: absolute; bottom: 1.5rem; left: 1.5rem; right: 1.5rem;">
                <div id="modalBadges" style="display:flex; gap:0.5rem; margin-bottom: 0.75rem;"></div>
                <h2 id="modalTitle" style="color: white; font-size: 2rem; font-weight: 800; margin: 0;"></h2>
            </div>
        </div>
        <div style="flex: 1; overflow-y: auto; padding: 2rem; display: grid; grid-template-columns: 280px 1fr; gap: 2.5rem;">
            <!-- Left: Stats & Ingredients -->
            <div style="border-right: 1px solid var(--border); padding-right: 2rem;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 2rem;">
                    <div style="background: var(--bg-color); padding: 0.75rem; border-radius: 8px; text-align: center;">
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Energy</div>
                        <div id="modalKcal" style="font-weight: 800; color: var(--primary);"></div>
                    </div>
                    <div style="background: var(--bg-color); padding: 0.75rem; border-radius: 8px; text-align: center;">
                        <div style="font-size: 0.65rem; color: var(--text-muted); font-weight: 700; text-transform: uppercase;">Protein</div>
                        <div id="modalProtein" style="font-weight: 800; color: var(--primary);"></div>
                    </div>
                </div>
                <h4 style="font-size: 0.875rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; color: var(--text-muted);">Ingredients</h4>
                <ul id="modalIngredients" style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.5rem;"></ul>
            </div>
            <!-- Right: Description & Instructions -->
            <div>
                <p id="modalDesc" style="font-style: italic; color: var(--text-muted); margin-bottom: 2rem; font-size: 0.95rem; line-height: 1.5;"></p>
                <h4 style="font-size: 0.875rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 1rem; color: var(--text-muted);">Preparation Steps</h4>
                <div id="modalInstructions" style="display: flex; flex-direction: column; gap: 1.25rem;"></div>
            </div>
        </div>
        <div style="padding: 1rem 2rem; background: var(--bg-color); border-top: 1px solid var(--border);">
            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                <div style="font-size: 0.75rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em;">Restrictions & Warnings</div>
                <div id="modalRestrictionsList" style="display: flex; gap: 0.5rem; flex-wrap: wrap;"></div>
            </div>
        </div>

        <div style="padding: 1.25rem 2rem; border-top: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; background: var(--surface);">
            <div style="font-size: 0.875rem; font-weight: 600;">Cost <strong>per student</strong>: <span id="modalCost" style="color: var(--primary); font-weight: 800;"></span></div>
            <div style="display:flex; gap:0.75rem;">
                <button class="btn btn-outline" onclick="openEditRecipe()" style="border: 1px solid var(--border); color: var(--text-main);">Edit Recipe</button>
                <button class="btn" style="background: var(--primary); color: white;">Pin to Planner</button>
            </div>
        </div>
    </div>
</div>

<style>
    .filter-pill {
        padding: 0.5rem 1.25rem;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: 99px;
        font-size: 0.875rem;
        font-weight: 600;
        white-space: nowrap;
        cursor: pointer;
        transition: all 0.2s;
        color: var(--text-muted);
    }
    .filter-pill.active {
        background: var(--primary);
        border-color: var(--primary);
        color: white;
    }
    .recipe-card {
        background: var(--surface);
        border-radius: 12px;
        overflow: hidden;
        border: 1px solid var(--border);
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
        display: flex;
        flex-direction: column;
    }
    .recipe-card:hover {
        transform: translateY(-8px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.1);
        border-color: var(--primary);
    }
    .recipe-card .card-img {
        height: 180px;
        background-size: cover;
        background-position: center;
        position: relative;
    }
    .recipe-card .card-content {
        padding: 1.25rem;
        flex: 1;
        display: flex;
        flex-direction: column;
    }
    .badge-allergen {
        background: #ffebee;
        color: #c62828;
        font-size: 0.65rem;
        padding: 0.2rem 0.6rem;
        border-radius: 4px;
        font-weight: 700;
        text-transform: uppercase;
    }
    .badge-restriction {
        background: #e3f2fd;
        color: #1565c0;
        font-size: 0.65rem;
        padding: 0.2rem 0.6rem;
        border-radius: 4px;
        font-weight: 700;
        text-transform: uppercase;
    }
    @keyframes pulse {
        0%, 100% { opacity: 0.5; }
        50% { opacity: 1; }
    }
</style>

<script>
    let allRecipes = [];
    let currentViewingRecipe = null;
    let restrictionList = [];

    async function loadRecipes() {
        try {
            const response = await fetch('api_get_recipes.php');
            allRecipes = await response.json();
            renderRecipes(allRecipes);
        } catch (error) {
            console.error('Error loading recipes:', error);
        }
    }

    async function loadRestrictions() {
        try {
            const response = await fetch('../../scratch/check_res.php'); // Re-using existing check or I should create a proper one
            restrictionList = await response.json();
            renderRestrictionCheckboxes();
        } catch (error) {
            // Fallback for demo if scratch is gone
            restrictionList = [
                {restriction_id: 1, restriction_name: 'Lactose'},
                {restriction_id: 2, restriction_name: 'Peanut'},
                {restriction_id: 3, restriction_name: 'Shellfish'},
                {restriction_id: 4, restriction_name: 'Soy'},
                {restriction_id: 5, restriction_name: 'Eggs'},
                {restriction_id: 6, restriction_name: 'Wheat / Gluten'},
                {restriction_id: 7, restriction_name: 'Fish'},
                {restriction_id: 8, restriction_name: 'Tree Nuts'},
                {restriction_id: 9, restriction_name: 'Halal'},
                {restriction_id: 10, restriction_name: 'Vegetarian'},
                {restriction_id: 11, restriction_name: 'Vegan'},
                {restriction_id: 12, restriction_name: 'Sesame'},
                {restriction_id: 13, restriction_name: 'Mustard'},
                {restriction_id: 14, restriction_name: 'Molluscs'},
                {restriction_id: 15, restriction_name: 'Celery'},
                {restriction_id: 16, restriction_name: 'Red Meat-Free'}
            ];
            renderRestrictionCheckboxes();
        }
    }

    function renderRestrictionCheckboxes() {
        const container = document.getElementById('restrictionCheckboxes');
        if (!container) return;
        container.innerHTML = restrictionList.map(r => `
            <label style="display:flex; align-items:center; gap:0.5rem; font-size:0.8rem; cursor:pointer;">
                <input type="checkbox" name="restrictions[]" value="${r.restriction_id}">
                ${r.restriction_name}
            </label>
        `).join('');
    }

    function addIngredientRow(name = '', amount = '', unit = '') {
        const div = document.createElement('div');
        div.style.display = 'grid';
        div.style.gridTemplateColumns = '2fr 1fr 1fr 32px';
        div.style.gap = '0.5rem';
        div.innerHTML = `
            <input type="text" name="ing_names[]" value="${name}" placeholder="Ingredient Name" style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:4px; font-size:0.8rem;">
            <input type="text" name="ing_amounts[]" value="${amount}" placeholder="Qty" style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:4px; font-size:0.8rem;">
            <input type="text" name="ing_units[]" value="${unit}" placeholder="Unit" style="width:100%; padding:0.5rem; border:1px solid var(--border); border-radius:4px; font-size:0.8rem;">
            <button type="button" onclick="this.parentElement.remove()" style="color:#ef4444; background:none; border:none; cursor:pointer;"><span class="material-icons" style="font-size:16px;">delete</span></button>
        `;
        document.getElementById('formIngredientsList').appendChild(div);
    }

    function addInstructionRow(text = '') {
        const div = document.createElement('div');
        div.style.display = 'flex';
        div.style.gap = '0.5rem';
        div.innerHTML = `
            <textarea name="instructions[]" placeholder="Step description" style="flex:1; padding:0.5rem; border:1px solid var(--border); border-radius:4px; font-size:0.8rem; height:45px;">${text}</textarea>
            <button type="button" onclick="this.parentElement.remove()" style="color:#ef4444; background:none; border:none; cursor:pointer;"><span class="material-icons" style="font-size:16px;">delete</span></button>
        `;
        document.getElementById('formInstructionsList').appendChild(div);
    }

    function openAddRecipeModal() {
        document.getElementById('recipeAction').value = 'add';
        document.getElementById('form_recipe_id').value = '';
        document.getElementById('recipeForm').reset();
        document.getElementById('formIngredientsList').innerHTML = '';
        document.getElementById('formInstructionsList').innerHTML = '';
        document.getElementById('form_color').value = '#3b82f6';
        addIngredientRow();
        addInstructionRow();
        document.querySelector('#addRecipeModal h2').innerText = 'Add New Recipe';
        document.getElementById('addRecipeModal').classList.add('active');
    }

    function openEditRecipe() {
        if (!currentViewingRecipe) return;
        const r = currentViewingRecipe;
        
        document.getElementById('recipeAction').value = 'edit';
        document.getElementById('form_recipe_id').value = r.recipe_id;
        document.getElementById('form_name').value = r.recipe_name;
        document.getElementById('form_desc').value = r.description;
        document.getElementById('form_kcal').value = r.energy_kcal;
        document.getElementById('form_protein').value = r.protein_g;
        document.getElementById('form_cost').value = r.base_cost_per_serving;
        document.getElementById('form_color').value = r.hex_color || '#3b82f6';

        // Set Allergens (using IDs for precision)
        const appliedIds = r.restriction_ids ? r.restriction_ids.split(',') : [];
        document.querySelectorAll('#restrictionCheckboxes input').forEach(cb => {
            cb.checked = appliedIds.includes(cb.value);
        });

        // Set Ingredients
        document.getElementById('formIngredientsList').innerHTML = '';
        if (r.ingredients && r.ingredients.length > 0) {
            r.ingredients.forEach(i => addIngredientRow(i.name, i.amount, i.unit));
        } else {
            addIngredientRow();
        }

        // Set Instructions
        document.getElementById('formInstructionsList').innerHTML = '';
        if (r.instructions && r.instructions.length > 0) {
            r.instructions.forEach(i => addInstructionRow(i.instruction));
        } else {
            addInstructionRow();
        }

        document.querySelector('#addRecipeModal h2').innerText = 'Edit Recipe';
        document.getElementById('addRecipeModal').classList.add('active');
        closeModal('recipeDetailModal');
    }

    document.getElementById('recipeForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const formData = new FormData(this);
        
        try {
            const response = await fetch('api_save_recipe.php', {
                method: 'POST',
                body: formData
            });
            const res = await response.json();
            if (res.success) {
                closeModal('addRecipeModal');
                loadRecipes();
                // Show success toast here if available
            } else {
                alert('Error: ' + res.message);
            }
        } catch (error) {
            console.error('Save failed:', error);
        }
    });



    function renderRecipes(recipes) {
        const grid = document.getElementById('recipeGrid');
        grid.innerHTML = recipes.map(r => {
            const color = r.hex_color || '#3b82f6';
            
            return `
                <div class="recipe-card" onclick='viewRecipe(${JSON.stringify(r).replace(/'/g, "&apos;")})'>
                    <div class="card-img" style="height: 12px; background: ${color}; opacity: 0.8;"></div>
                    <div class="card-content">
                        <h3 style="font-size: 1.15rem; font-weight: 800; margin: 0 0 0.5rem 0; color: var(--text-main); line-height: 1.2;">${r.recipe_name}</h3>
                        <p style="font-size: 0.85rem; color: var(--text-muted); line-height: 1.4; margin-bottom: 1.5rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;">${r.description}</p>
                        
                        <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center;">
                            <div style="display: flex; gap: 0.75rem;">
                                <div style="font-size: 0.75rem; font-weight: 700;">
                                    <span style="display:block; color: var(--text-muted); font-size: 0.6rem; text-transform: uppercase;">Energy</span>
                                    ${r.energy_kcal} kcal
                                </div>
                                <div style="font-size: 0.75rem; font-weight: 700;">
                                    <span style="display:block; color: var(--text-muted); font-size: 0.6rem; text-transform: uppercase;">Protein</span>
                                    ${r.protein_g}g
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size: 0.6rem; color: var(--text-muted); text-transform: uppercase; font-weight: 700;">Cost per student</div>
                                <div style="font-size: 1rem; font-weight: 800; color: ${color};">&#8369; ${parseFloat(r.base_cost_per_serving).toFixed(2)}</div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
    }

    function viewRecipe(r) {
        document.getElementById('modalTitle').innerText = r.recipe_name;
        document.getElementById('modalDesc').innerText = r.description;
        document.getElementById('modalKcal').innerText = r.energy_kcal + ' kcal';
        document.getElementById('modalProtein').innerText = r.protein_g + 'g';
        document.getElementById('modalCost').innerText = '₱ ' + r.base_cost_per_serving;
        
        const color = r.hex_color || '#3b82f6';
        document.getElementById('modalHero').style.background = color;
        document.getElementById('modalHero').style.height = '100px'; 
        document.getElementById('modalHero').style.backgroundImage = 'none';

        document.getElementById('modalBadges').innerHTML = ''; // Removed from hero

        // Restrictions Section at Bottom
        const restrictionHtml = [];
        if (r.allergens) {
            r.allergens.split(',').forEach(a => {
                let label = a;
                let style = 'background:#fee2e2; color:#b91c1c;';
                
                // If it's a Halal conflict, rename it for the warning UI
                if (a === 'Halal') {
                    label = 'Non-Halal (Pork)';
                }

                restrictionHtml.push(`<span class="badge-allergen" style="font-size:0.75rem; ${style}">Contains ${label}</span>`);
            });
        }
        document.getElementById('modalRestrictionsList').innerHTML = restrictionHtml.length > 0 ? restrictionHtml.join('') : '<span style="font-size:0.8rem; color:var(--text-muted);">None reported.</span>';
        
        // Save current for editing
        currentViewingRecipe = r;

        // Ingredients
        document.getElementById('modalIngredients').innerHTML = r.ingredients.map(i => `
            <li style="display: flex; justify-content: space-between; padding: 0.6rem 0.75rem; background: var(--bg-color); border-radius: 6px; font-size: 0.875rem;">
                <span style="font-weight: 600; color: var(--text-main);">${i.name}</span>
                <span style="color: var(--primary); font-weight: 700;">${i.amount} ${i.unit}</span>
            </li>
        `).join('');

        // Instructions
        document.getElementById('modalInstructions').innerHTML = r.instructions.map(i => `
            <div style="display: flex; gap: 1rem;">
                <div style="width: 24px; height: 24px; background: var(--primary); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 800; flex-shrink: 0;">${i.step_no}</div>
                <div style="font-size: 0.95rem; line-height: 1.5; color: var(--text-main);">${i.instruction}</div>
            </div>
        `).join('');

        document.getElementById('recipeDetailModal').classList.add('active');
    }

    function closeModal(id) {
        document.getElementById(id).classList.remove('active');
    }

    // Search
    document.getElementById('recipeSearch').addEventListener('input', function(e) {
        const term = e.target.value.toLowerCase();
        const filtered = allRecipes.filter(r => 
            r.recipe_name.toLowerCase().includes(term) || 
            r.description.toLowerCase().includes(term)
        );
        renderRecipes(filtered);
    });

    loadRecipes();
    loadRestrictions();
</script>

<?php require_once '../../includes/footer.php'; ?>

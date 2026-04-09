<?php require_once '../../includes/header.php'; ?>
<?php require_once '../../includes/sidebar.php'; ?>

<?php
$page_title = 'Recipe Database';
require_once '../../includes/topbar.php';
?>

        <div class="content">
            <div class="section-card">
                <div class="section-header">
                    <h3 class="section-title">DepEd Approved Recipes</h3>
                    <button class="btn"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">add</span> Add Recipe</button>
                </div>

                <div style="margin-bottom: 1rem; display: flex; gap: 1rem;">
                    <input type="text" placeholder="Search recipes..." style="padding: 0.5rem; border: 1px solid var(--border); border-radius: 4px; width: 300px;">
                    <button class="btn-text"><span class="material-icons" style="font-size: 16px; vertical-align: middle;">filter_list</span> Filters</button>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>Recipe ID</th>
                            <th>Recipe Name</th>
                            <th>Energy (kcal)</th>
                            <th>Protein (g)</th>
                            <th>Cost/Serving</th>
                            <th>Allergens</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>REC001</td>
                            <td>Chicken Arroz Caldo</td>
                            <td>350 kcal</td>
                            <td>15g</td>
                            <td>₱ 18.50</td>
                            <td>None</td>
                            <td>
                                <button class="btn-text">View</button>
                                <button class="btn-text">Edit</button>
                            </td>
                        </tr>
                        <tr>
                            <td>REC002</td>
                            <td>Ginataang Munggo</td>
                            <td>320 kcal</td>
                            <td>14g</td>
                            <td>₱ 14.00</td>
                            <td><span class="badge warning">Dairy/Coconut</span></td>
                            <td>
                                <button class="btn-text">View</button>
                                <button class="btn-text">Edit</button>
                            </td>
                        </tr>
                        <tr>
                            <td>REC003</td>
                            <td>Pork Picadillo</td>
                            <td>380 kcal</td>
                            <td>18g</td>
                            <td>₱ 22.00</td>
                            <td>None</td>
                            <td>
                                <button class="btn-text">View</button>
                                <button class="btn-text">Edit</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

<?php require_once '../../includes/footer.php'; ?>

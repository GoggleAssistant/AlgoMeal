<?php
require_once '../../db.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? 'add';
$recipe_id = $_POST['recipe_id'] ?? null;
$recipe_name = $_POST['recipe_name'] ?? '';
$description = $_POST['description'] ?? '';
$kcal = (int)($_POST['energy_kcal'] ?? 0);
$protein = (float)($_POST['protein_g'] ?? 0);
$cost = (float)($_POST['cost'] ?? 0);
$restrictions = $_POST['restrictions'] ?? [];
$ing_names = $_POST['ing_names'] ?? [];
$ing_amounts = $_POST['ing_amounts'] ?? [];
$ing_units = $_POST['ing_units'] ?? [];
$instructions = $_POST['instructions'] ?? [];
$hex_color = $_POST['hex_color'] ?? '#3b82f6';

if ($action === 'add') {
    // Generate a new REC ID
    $res = $conn->query("SELECT MAX(CAST(SUBSTRING(recipe_id, 4) AS UNSIGNED)) as max_id FROM recipes");
    $row = $res->fetch_assoc();
    $next_id = 'REC' . str_pad(($row['max_id'] ?? 0) + 1, 3, '0', STR_PAD_LEFT);
    $recipe_id = $next_id;

    $stmt = $conn->prepare("INSERT INTO recipes (recipe_id, recipe_name, description, energy_kcal, protein_g, base_cost_per_serving, hex_color) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssidds", $recipe_id, $recipe_name, $description, $kcal, $protein, $cost, $hex_color);
} else {
    $stmt = $conn->prepare("UPDATE recipes SET recipe_name=?, description=?, energy_kcal=?, protein_g=?, base_cost_per_serving=?, hex_color=? WHERE recipe_id=?");
    $stmt->bind_param("ssiddss", $recipe_name, $description, $kcal, $protein, $cost, $hex_color, $recipe_id);
}

if ($stmt->execute()) {
    // 1. Update Restrictions
    $conn->query("DELETE FROM recipe_allergen_tags WHERE recipe_id = '$recipe_id'");
    foreach ($restrictions as $rid) {
        $stmt_tag = $conn->prepare("INSERT INTO recipe_allergen_tags (recipe_id, restriction_id) VALUES (?, ?)");
        $stmt_tag->bind_param("si", $recipe_id, $rid);
        $stmt_tag->execute();
    }

    // 2. Update Ingredients
    $conn->query("DELETE FROM recipe_ingredients WHERE recipe_id = '$recipe_id'");
    for ($i = 0; $i < count($ing_names); $i++) {
        if (empty($ing_names[$i])) continue;
        $stmt_ing = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, name, amount, unit) VALUES (?, ?, ?, ?)");
        $stmt_ing->bind_param("ssss", $recipe_id, $ing_names[$i], $ing_amounts[$i], $ing_units[$i]);
        $stmt_ing->execute();
    }

    // 3. Update Instructions
    $conn->query("DELETE FROM recipe_instructions WHERE recipe_id = '$recipe_id'");
    for ($i = 0; $i < count($instructions); $i++) {
        if (empty($instructions[$i])) continue;
        $step_no = $i + 1;
        $stmt_inst = $conn->prepare("INSERT INTO recipe_instructions (recipe_id, step_no, instruction) VALUES (?, ?, ?)");
        $stmt_inst->bind_param("sis", $recipe_id, $step_no, $instructions[$i]);
        $stmt_inst->execute();
    }
    
    echo json_encode(['success' => true, 'recipe_id' => $recipe_id]);
} else {
    echo json_encode(['success' => false, 'message' => $conn->error]);
}
?>

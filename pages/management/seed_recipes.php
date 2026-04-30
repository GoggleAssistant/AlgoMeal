<?php
// seed_recipes.php
require_once '../../db.php';

session_start();
$role = $_SESSION['role'] ?? 'Guest';
if ($role !== 'Admin' && $role !== 'Super Admin') {
    die("Unauthorized access. Admin privileges required.");
}

$recipes = [
    [
        'id' => 'R009',
        'name' => 'Vegetable Lentil Soup',
        'desc' => 'Protein-packed lentil soup with carrots and spinach.',
        'energy' => 180,
        'protein' => 12.0,
        'fat' => 4.5,
        'carbs' => 24.0,
        'cost' => 18.50,
        'color' => '#10b981',
        'restrictions' => [15, 12] // Celery, Sesame
    ],
    [
        'id' => 'R010',
        'name' => 'Baked Fish Fillet',
        'desc' => 'White fish seasoned with calamansi and ginger.',
        'energy' => 220,
        'protein' => 26.0,
        'fat' => 8.0,
        'carbs' => 2.0,
        'cost' => 45.00,
        'color' => '#60a5fa',
        'restrictions' => [7, 9] // Fish, Halal (it is halal, so we mark it as Halal)
    ],
    [
        'id' => 'R011',
        'name' => 'Peanut-Free Kare-Kare',
        'desc' => 'Made with cashew butter instead of peanuts.',
        'energy' => 350,
        'protein' => 18.0,
        'fat' => 22.0,
        'carbs' => 14.0,
        'cost' => 55.00,
        'color' => '#f59e0b',
        'restrictions' => [8] // Tree Nuts (contains cashews)
    ],
    [
        'id' => 'R012',
        'name' => 'Steamed Chicken with Broccoli',
        'desc' => 'Lean chicken breast with fresh broccoli. Low fat.',
        'energy' => 190,
        'protein' => 28.0,
        'fat' => 5.0,
        'carbs' => 6.0,
        'cost' => 38.00,
        'color' => '#fbbf24',
        'restrictions' => [9] // Halal
    ],
    [
        'id' => 'R013',
        'name' => 'Tofu and Mushroom Stir-fry',
        'desc' => 'A meat-free protein option with oyster mushrooms.',
        'energy' => 150,
        'protein' => 14.0,
        'fat' => 7.0,
        'carbs' => 10.0,
        'cost' => 22.00,
        'color' => '#8b5cf6',
        'restrictions' => [4, 16] // Soy, Red Meat-Free
    ]
];

foreach ($recipes as $r) {
    // Insert into recipes table
    $stmt = $conn->prepare("INSERT INTO recipes (recipe_id, recipe_name, description, energy_kcal, protein_g, fat_g, carbs_g, base_cost_per_serving, hex_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('sssddddds', $r['id'], $r['name'], $r['desc'], $r['energy'], $r['protein'], $r['fat'], $r['carbs'], $r['cost'], $r['color']);
    $stmt->execute();
    
    // Insert into recipe_allergen_tags
    foreach ($r['restrictions'] as $res_id) {
        $stmt_tag = $conn->prepare("INSERT INTO recipe_allergen_tags (recipe_id, restriction_id) VALUES (?, ?)");
        $stmt_tag->bind_param('si', $r['id'], $res_id);
        $stmt_tag->execute();
    }
}

echo "Seeding complete. Added " . count($recipes) . " new recipes.";
?>

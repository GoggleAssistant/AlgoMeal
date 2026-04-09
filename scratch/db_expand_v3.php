<?php
require_once '../db.php';

echo "<pre>";
echo "Starting Deep Nutritional Seeding (Phase 2)...\n";

// 1. Expand Dietary Restrictions (Adding the missing ones)
$new_restrictions = [
    ['Sesame', 'Allergy'],
    ['Mustard', 'Allergy'],
    ['Molluscs', 'Allergy'],
    ['Celery', 'Allergy'],
    ['Red Meat-Free', 'Religious']
];

foreach ($new_restrictions as $nr) {
    $stmt = $conn->prepare("INSERT INTO dietary_restrictions (restriction_name, type) VALUES (?, ?) ON DUPLICATE KEY UPDATE restriction_name=restriction_name");
    $stmt->bind_param("ss", $nr[0], $nr[1]);
    $stmt->execute();
}

// 2. Fetch Mapping
$res = $conn->query("SELECT * FROM dietary_restrictions");
$map = [];
while($row = $res->fetch_assoc()) $map[$row['restriction_name']] = $row['restriction_id'];

// 3. Define the next batch of recipes
$new_recipes = [
    [
        'id' => 'REC006',
        'name' => 'Creamy Chicken Sopas',
        'desc' => 'A classic Filipino elbow macaroni soup with chicken, vegetables, and evaporated milk for a creamy finish.',
        'energy' => 380,
        'protein' => 14.2,
        'cost' => 20.00,
        'restrictions' => ['Wheat / Gluten', 'Lactose', 'Halal / Pork-Free'],
        'ingredients' => [
            ['Elbow Macaroni', '200', 'g'],
            ['Chicken strips', '150', 'g'],
            ['Evaporated Milk', '1/2', 'cup'],
            ['Carrots (diced)', '1/2', 'cup'],
            ['Cabbage (shredded)', '1', 'cup']
        ],
        'steps' => [
            'Sauté garlic and onion in a large pot.',
            'Add chicken and cook until no longer pink.',
            'Add water and macaroni; simmer until pasta is tender.',
            'Stir in carrots and cabbage.',
            'Slowly pour in evaporated milk while stirring. Season with salt.'
        ]
    ],
    [
        'id' => 'REC007',
        'name' => 'Misua with Patola and Pork',
        'desc' => 'Fine wheat noodles (misua) in a clear broth with sponge gourd (patola) and ground pork.',
        'energy' => 290,
        'protein' => 12.0,
        'cost' => 16.50,
        'restrictions' => ['Wheat / Gluten'], // Contains Pork
        'ingredients' => [
            ['Misua (wheat noodles)', '100', 'g'],
            ['Ground pork', '150', 'g'],
            ['Patola (sponge gourd)', '1', 'pc'],
            ['Garlic', '4', 'cloves'],
            ['Chicken stock', '4', 'cups']
        ],
        'steps' => [
            'Sauté garlic until golden brown.',
            'Add ground pork and sauté until browned.',
            'Add chicken stock and bring to a boil.',
            'Add patola and cook for 2-3 minutes.',
            'Add misua and cook for 1 minute (do not overcook).'
        ]
    ],
    [
        'id' => 'REC008',
        'name' => 'Adobong Sitaw with Tofu',
        'desc' => 'Yardlong beans sautéed in a classic adobo sauce (soy and vinegar) with crispy fried tofu chunks.',
        'energy' => 210,
        'protein' => 11.5,
        'cost' => 12.00,
        'restrictions' => ['Soy', 'Halal / Pork-Free', 'Vegetarian', 'Vegan'],
        'ingredients' => [
            ['Yardlong beans (Sitaw)', '1', 'bundle'],
            ['Firm Tofu (cubed)', '2', 'blocks'],
            ['Soy sauce', '3', 'tbsp'],
            ['Vinegar', '2', 'tbsp'],
            ['Garlic', '5', 'cloves']
        ],
        'steps' => [
            'Fry tofu cubes until golden and set aside.',
            'Sauté garlic until fragrant.',
            'Add sitaw and cook for 2 minutes.',
            'Add soy sauce, vinegar, and water. Simmer until sitaw is tender.',
            'Mix in the fried tofu and serve.'
        ]
    ],
    [
        'id' => 'REC009',
        'name' => 'Sinigang na Bangus',
        'desc' => 'The quintessential Filipino sour soup featuring milkfish (bangus) and hearty local vegetables.',
        'energy' => 240,
        'protein' => 19.5,
        'cost' => 28.00,
        'restrictions' => ['Fish', 'Halal / Pork-Free'],
        'ingredients' => [
            ['Bangus (Milkfish), sliced', '500', 'g'],
            ['Tamarind base soup mix', '1', 'pck'],
            ['Radish (Labahos)', '1', 'pc'],
            ['Eggplant', '1', 'pc'],
            ['Kangkong (River spinach)', '1', 'bundle']
        ],
        'steps' => [
            'Bring water to a boil with tomatoes and onions.',
            'Add radish and okra; simmer for 5 minutes.',
            'Add bangus and tamarind mix.',
            'Stir in eggplant and simmer until fish is cooked.',
            'Add kangkong and turn off heat.'
        ]
    ],
    [
        'id' => 'REC010',
        'name' => 'Steamed Camote Tops with Dip',
        'desc' => 'Light and refreshing blanched sweet potato leaves served with a tangy calamansi-soy dipping sauce.',
        'energy' => 95,
        'protein' => 3.0,
        'cost' => 8.00,
        'restrictions' => ['Soy', 'Halal / Pork-Free', 'Vegetarian', 'Vegan'],
        'ingredients' => [
            ['Camote tops (Talbos)', '2', 'bundles'],
            ['Calamansi', '3', 'pcs'],
            ['Soy sauce', '1', 'tbsp'],
            ['Tomatoes (chopped)', '2', 'pcs']
        ],
        'steps' => [
            'Boil water in a pot.',
            'Blanch camote tops for 1-2 minutes until wilted.',
            'Drain and plunge into cold water to stop cooking.',
            'Arrange on a plate with tomatoes.',
            'Serve with a mix of soy sauce and calamansi juice.'
        ]
    ]
];

// 4. Upsert Recipes & Tags
foreach ($new_recipes as $r) {
    $stmt = $conn->prepare("REPLACE INTO recipes (recipe_id, recipe_name, description, energy_kcal, protein_g, base_cost_per_serving) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssdid", $r['id'], $r['name'], $r['desc'], $r['energy'], $r['protein'], $r['cost']);
    $stmt->execute();
    
    // Clear old tags/ing/inst
    $conn->query("DELETE FROM recipe_ingredients WHERE recipe_id='{$r['id']}'");
    $conn->query("DELETE FROM recipe_instructions WHERE recipe_id='{$r['id']}'");
    $conn->query("DELETE FROM recipe_allergen_tags WHERE recipe_id='{$r['id']}'");
    
    // Tags
    foreach ($r['restrictions'] as $label) {
        if (isset($map[$label])) {
            $rid = $map[$label];
            $conn->query("INSERT INTO recipe_allergen_tags (recipe_id, restriction_id) VALUES ('{$r['id']}', $rid)");
        }
    }
    
    // Ingredients
    foreach ($r['ingredients'] as $ing) {
        $stmt = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, name, amount, unit) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $r['id'], $ing[0], $ing[1], $ing[2]);
        $stmt->execute();
    }
    
    // Instructions
    foreach ($r['steps'] as $idx => $s) {
        $sn = $idx + 1;
        $stmt = $conn->prepare("INSERT INTO recipe_instructions (recipe_id, step_no, instruction) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $r['id'], $sn, $s);
        $stmt->execute();
    }
    echo "Seed: {$r['name']} [COMPLETE]\n";
}

echo "\nPhase 2 Seeding Complete.\n";
echo "</pre>";
?>

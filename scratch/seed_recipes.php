<?php
require_once __DIR__ . '/../db.php';

// 1. Clear Existing Data
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE recipe_allergen_tags");
$conn->query("TRUNCATE TABLE recipe_ingredients");
$conn->query("TRUNCATE TABLE recipe_instructions");
$conn->query("DELETE FROM recipes");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "Database Cleared.\n";

$category_colors = [
    'Rice Meal'  => '#f59e0b',
    'Soup'       => '#0ea5e9',
    'Viand'      => '#ef4444',
    'Pasta'      => '#f97316',
    'Snack'      => '#a855f7',
    'Vegetable'  => '#10b981',
    'General'    => '#64748b',
];

$recipes = [
    [
        'id' => 'REC001',
        'name' => 'Chicken Arroz Caldo',
        'cat' => 'Rice Meal',
        'desc' => 'A comforting Filipino rice porridge with chicken, ginger, and garlic.',
        'kcal' => 350,
        'protein' => 22.5,
        'cost' => 35.00,
        'ings' => [
            ['Chicken Thighs', '200', 'g'],
            ['Glutinous Rice', '1/2', 'cup'],
            ['Ginger', '1', 'thumb'],
            ['Garlic', '3', 'cloves'],
            ['Fish Sauce', '1', 'tsp']
        ],
        'steps' => [
            'Sauté ginger and garlic until fragrant.',
            'Add chicken and cook until slightly browned.',
            'Stir in rice and water, bring to a boil.',
            'Simmer until rice is tender and porridge consistency is reached.'
        ]
    ],
    [
        'id' => 'REC002',
        'name' => 'Chicken Sopas',
        'cat' => 'Soup',
        'desc' => 'Creamy macaroni chicken soup with colorful vegetables.',
        'kcal' => 420,
        'protein' => 18.0,
        'cost' => 40.00,
        'ings' => [
            ['Elbow Macaroni', '100', 'g'],
            ['Shredded Chicken', '150', 'g'],
            ['Evaporated Milk', '1/2', 'cup'],
            ['Carrots', '1/2', 'pc'],
            ['Cabbage', '50', 'g']
        ],
        'steps' => [
            'Boil macaroni until al dente.',
            'In a separate pot, sauté onions and shredded chicken.',
            'Add broth and vegetables, simmer until tender.',
            'Stir in milk and cooked macaroni, season to taste.'
        ]
    ],
    [
        'id' => 'REC003',
        'name' => 'Adobong Sitaw with Pork',
        'cat' => 'Vegetable',
        'desc' => 'String beans cooked in soy sauce, vinegar, and garlic with tender pork bits.',
        'kcal' => 280,
        'protein' => 15.5,
        'cost' => 30.00,
        'ings' => [
            ['String Beans', '200', 'g'],
            ['Pork Belly', '100', 'g'],
            ['Soy Sauce', '2', 'tbsp'],
            ['Vinegar', '1', 'tbsp'],
            ['Garlic', '4', 'cloves']
        ],
        'steps' => [
            'Sauté garlic and pork until fat is rendered.',
            'Add soy sauce and vinegar, do not stir immediately.',
            'Add string beans and water.',
            'Cook until beans are tender yet still slightly crisp.'
        ]
    ],
    [
        'id' => 'REC004',
        'name' => 'Filipino Style Spaghetti',
        'cat' => 'Pasta',
        'desc' => 'Sweet and savory spaghetti with hotdogs and ground meat.',
        'kcal' => 550,
        'protein' => 25.0,
        'cost' => 45.00,
        'ings' => [
            ['Spaghetti Pasta', '150', 'g'],
            ['Ground Beef/Pork', '150', 'g'],
            ['Sweet Tomato Sauce', '200', 'ml'],
            ['Cheese', '30', 'g'],
            ['Hotdogs', '2', 'pcs']
        ],
        'steps' => [
            'Cook spaghetti according to package instructions.',
            'Sauté meat and hotdogs until browned.',
            'Pour in tomato sauce and simmer for 15 minutes.',
            'Toss with pasta and top with grated cheese.'
        ]
    ],
    [
        'id' => 'REC005',
        'name' => 'Egg Drop Soup',
        'cat' => 'Soup',
        'desc' => 'Light and savory cornstarch-thickened soup with wispy beaten eggs.',
        'kcal' => 120,
        'protein' => 8.0,
        'cost' => 15.00,
        'ings' => [
            ['Eggs', '2', 'pcs'],
            ['Chicken Broth', '2', 'cups'],
            ['Cornstarch', '1', 'tbsp'],
            ['Green Onions', '1', 'stalk']
        ],
        'steps' => [
            'Bring chicken broth to a gentle simmer.',
            'Slowly drizzle beaten eggs while stirring in one direction.',
            'Thicken with cornstarch slurry.',
            'Garnish with sliced green onions.'
        ]
    ],
    [
        'id' => 'REC006',
        'name' => 'Banana Cue',
        'cat' => 'Snack',
        'desc' => 'Deep-fried saba bananas coated in caramelized brown sugar.',
        'kcal' => 310,
        'protein' => 2.0,
        'cost' => 12.00,
        'ings' => [
            ['Saba Bananas', '3', 'pcs'],
            ['Brown Sugar', '1/4', 'cup'],
            ['Cooking Oil', '1', 'cup']
        ],
        'steps' => [
            'Heat oil in a pan.',
            'Add brown sugar and wait for it to float/caramelize.',
            'Add bananas and coat them with the melted sugar.',
            'Drain and serve on skewers.'
        ]
    ],
    [
        'id' => 'REC007',
        'name' => 'Ginisang Monggo',
        'cat' => 'Vegetable',
        'desc' => 'Mung bean stew with spinach and pork cracklings.',
        'kcal' => 290,
        'protein' => 19.0,
        'cost' => 25.00,
        'ings' => [
            ['Mung Beans', '1/2', 'cup'],
            ['Spinach', '50', 'g'],
            ['Pork Bits', '50', 'g'],
            ['Tomatoes', '1', 'pc']
        ],
        'steps' => [
            'Boil mung beans until soft and skins burst.',
            'Sauté garlic, onions, and tomatoes with pork.',
            'Pour in cooked mung beans.',
            'Add spinach at the very end and season.'
        ]
    ],
    [
        'id' => 'REC008',
        'name' => 'Beef Pares',
        'cat' => 'Rice Meal',
        'desc' => 'Braised beef in a sweet soy-based sauce served with garlic fried rice.',
        'kcal' => 620,
        'protein' => 35.0,
        'cost' => 65.00,
        'ings' => [
            ['Beef Brisket', '200', 'g'],
            ['Star Anise', '1', 'pc'],
            ['Soy Sauce', '3', 'tbsp'],
            ['Garlic Rice', '1', 'bowl']
        ],
        'steps' => [
            'Boil beef until very tender.',
            'Sauté aromatics and add beef with soy sauce and star anise.',
            'Thicken the sauce slightly.',
            'Serve with hot garlic fried rice and clear soup.'
        ]
    ]
];

foreach ($recipes as $r) {
    $hex = $category_colors[$r['cat']] ?? '#64748b';
    $stmt = $conn->prepare("INSERT INTO recipes (recipe_id, recipe_name, category, description, energy_kcal, protein_g, base_cost_per_serving, hex_color) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssidds", $r['id'], $r['name'], $r['cat'], $r['desc'], $r['kcal'], $r['protein'], $r['cost'], $hex);
    $stmt->execute();
    
    foreach ($r['ings'] as $ing) {
        $si = $conn->prepare("INSERT INTO recipe_ingredients (recipe_id, name, amount, unit) VALUES (?, ?, ?, ?)");
        $si->bind_param("ssss", $r['id'], $ing[0], $ing[1], $ing[2]);
        $si->execute();
    }
    
    foreach ($r['steps'] as $idx => $step) {
        $step_no = $idx + 1;
        $ss = $conn->prepare("INSERT INTO recipe_instructions (recipe_id, step_no, instruction) VALUES (?, ?, ?)");
        $ss->bind_param("sis", $r['id'], $step_no, $step);
        $ss->execute();
    }
    
    echo "Inserted: " . $r['name'] . "\n";
}

echo "\nSeeding Complete!";
?>

-- =====================================================
-- Seed: ENRICHED Filipino School Canteen Recipes
-- =====================================================

-- 1. Clean up existing REC009-REC026 entries to re-insert with full data
DELETE FROM recipe_instructions WHERE recipe_id BETWEEN 'REC009' AND 'REC026';
DELETE FROM recipe_ingredients WHERE recipe_id BETWEEN 'REC009' AND 'REC026';
DELETE FROM recipes WHERE recipe_id BETWEEN 'REC009' AND 'REC026';

-- 2. Insert Enriched Recipes
INSERT INTO recipes (recipe_id, recipe_name, category, description, energy_kcal, protein_g, fat_g, carbs_g, base_cost_per_serving, prep_time, servings, hex_color) VALUES
('REC009','Pork Sinigang','Main Course','Classic Filipino sour soup with pork, tamarind, and garden vegetables. Rich in Vitamin C and protein.',480,22.5,18.0,45.0,28.00,'45 mins',1,'#ef4444'),
('REC010','Chicken Tinola','Main Course','Traditional ginger-based chicken soup with green papaya and malunggay leaves. Excellent for recovery.',420,28.0,14.0,38.0,25.00,'40 mins',1,'#10b981'),
('REC011','Pinakbet','Main Course','A nutritious blend of local vegetables like squash, string beans, and eggplant, sautéed with shrimp paste.',380,14.0,12.0,52.0,18.00,'30 mins',1,'#f59e0b'),
('REC012','Pork Menudo','Main Course','Hearty pork stew with liver, potatoes, and carrots in a rich tomato sauce. High in iron.',510,24.5,20.0,48.0,30.00,'50 mins',1,'#dc2626'),
('REC013','Chopsuey','Main Course','Stir-fried colorful vegetables with chicken strips and quail eggs. High fiber and vitamins.',390,18.5,10.0,50.0,22.00,'25 mins',1,'#22c55e'),
('REC014','Nilagang Baka','Main Course','Simple and comforting beef soup with corn, cabbage, and potatoes. Great source of zinc and protein.',465,30.0,16.0,40.0,35.00,'60 mins',1,'#6366f1'),
('REC015','Sardines with Rice','Main Course','Quick and budget-friendly meal of sardines in tomato sauce with misua and rice.',420,20.0,14.0,55.0,15.00,'15 mins',1,'#f43f5e'),
('REC016','Ginataang Kalabasa','Main Course','Sweet squash and string beans cooked in creamy coconut milk with shrimp.',440,16.0,18.5,52.0,20.00,'35 mins',1,'#fbbf24'),
('REC017','Paksiw na Bangus','Main Course','Milkfish stewed in vinegar, ginger, and eggplant. Traditional preservation method.',390,26.0,14.0,30.0,24.00,'30 mins',1,'#3b82f6'),
('REC018','Tofu Stir Fry','Main Course','Healthy protein alternative with fried tofu cubes and mixed vegetable strips.',350,18.0,12.0,40.0,16.00,'20 mins',1,'#8b5cf6'),
('REC019','Champorado','Snack','Sweet chocolate rice porridge made with glutinous rice and pure cocoa. Best served with milk.',380,8.5,10.0,65.0,12.00,'30 mins',1,'#78350f'),
('REC020','Lugaw','Snack','Savory rice porridge topped with toasted garlic and spring onions. Easy to digest.',290,10.0,6.0,52.0,8.00,'25 mins',1,'#f1f5f9'),
('REC021','Maja Blanca','Snack','Creamy coconut milk pudding with sweet corn kernels. A popular Filipino dessert snack.',310,5.2,12.0,46.0,10.00,'40 mins',1,'#fef3c7'),
('REC022','Puto','Snack','Steamed rice cakes, light and fluffy. Perfect pairing for savory dishes or as a standalone snack.',260,6.5,4.0,50.0,8.00,'20 mins',1,'#ffffff'),
('REC023','Biko','Snack','Sticky rice cake topped with caramelized coconut curd (latik). High energy snack.',340,5.8,10.5,58.0,10.00,'45 mins',1,'#92400e'),
('REC024','Arroz Caldo with Egg','Snack','Ginger-infused chicken rice porridge with a whole hard-boiled egg. Filling and nutritious.',350,14.5,8.0,55.0,14.00,'35 mins',1,'#fde68a'),
('REC025','Camote Cue','Snack','Deep-fried sweet potato skewers coated with caramelized brown sugar.',320,3.5,8.0,60.0,7.00,'20 mins',1,'#ea580c'),
('REC026','Ginataan Bilo-bilo','Snack','Warm coconut milk soup with sticky rice balls, saba banana, and jackfruit.',360,5.5,14.0,54.0,11.00,'40 mins',1,'#ec4899');

-- 3. Insert Instructions
INSERT INTO recipe_instructions (recipe_id, step_no, instruction) VALUES
('REC009',1,'Boil pork in water until tender.'),
('REC009',2,'Add onions and tomatoes, simmer for 5 minutes.'),
('REC009',3,'Add tamarind mix and vegetables (radish, sitaw, eggplant).'),
('REC009',4,'Simmer until vegetables are cooked but still firm.'),
('REC010',1,'Sauté ginger, garlic, and onion.'),
('REC010',2,'Add chicken and cook until slightly browned.'),
('REC010',3,'Pour in water and simmer until chicken is tender.'),
('REC010',4,'Add papaya and malunggay leaves before serving.'),
('REC011',1,'Sauté garlic, onion, and ginger.'),
('REC011',2,'Add pork or shrimp and shrimp paste (bagoong).'),
('REC011',3,'Add squash and water, cook until squash is soft.'),
('REC011',4,'Add string beans, okra, and eggplant; simmer until done.'),
('REC012',1,'Sauté garlic and onion, add pork and liver.'),
('REC012',2,'Add tomato sauce and water, simmer until pork is tender.'),
('REC012',3,'Add potatoes, carrots, and bell peppers.'),
('REC012',4,'Season and cook until vegetables are soft.'),
('REC013',1,'Sauté garlic, onion, and chicken strips.'),
('REC013',2,'Add cauliflower, carrots, and baguio beans.'),
('REC013',3,'Add cabbage and bell peppers, toss in oyster sauce.'),
('REC013',4,'Garnish with boiled quail eggs.'),
('REC014',1,'Boil beef in water with peppercorns until tender.'),
('REC014',2,'Add corn on the cob and potatoes.'),
('REC014',3,'Add cabbage and pechay leaves.'),
('REC014',4,'Simmer for 2 minutes and serve hot.'),
('REC015',1,'Sauté garlic and onion.'),
('REC015',2,'Add canned sardines and misua noodles.'),
('REC015',3,'Add a bit of water and patola if available.'),
('REC015',4,'Serve over warm rice.'),
('REC016',1,'Sauté garlic and onion, add shrimp.'),
('REC016',2,'Add squash and coconut milk.'),
('REC016',3,'Simmer until squash starts to mash slightly.'),
('REC016',4,'Add string beans and season.'),
('REC019',1,'Boil glutinous rice in water until soft and thick.'),
('REC019',2,'Stir in cocoa powder or chocolate tablets.'),
('REC019',3,'Add sugar and stir constantly to prevent burning.'),
('REC019',4,'Serve with evaporated milk on top.'),
('REC024',1,'Sauté ginger and garlic.'),
('REC024',2,'Add chicken and rice, sauté until rice is translucent.'),
('REC024',3,'Add water and simmer until rice becomes porridge.'),
('REC024',4,'Top with a hard-boiled egg and toasted garlic.');

-- 4. Insert Ingredients (Sample for core recipes)
INSERT INTO recipe_ingredients (recipe_id, name, amount, unit) VALUES
('REC009','Pork Belly','150','g'),
('REC009','Tamarind Mix','1','pack'),
('REC009','Water spinach (Kangkong)','1','bunch'),
('REC009','Radish','1','pc'),
('REC010','Chicken','200','g'),
('REC010','Ginger','1','thumb'),
('REC010','Green Papaya','1','cup'),
('REC010','Malunggay Leaves','1','cup'),
('REC019','Glutinous Rice','1/2','cup'),
('REC019','Cocoa Powder','3','tbsp'),
('REC019','Sugar','4','tbsp'),
('REC024','Rice','1/2','cup'),
('REC024','Chicken','100','g'),
('REC024','Egg','1','pc'),
('REC024','Ginger','1','thumb');

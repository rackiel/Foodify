<?php
/**
 * Script to update the Ingredients column in the CSV file with detailed ingredient lists
 * based on Filipino cooking practices and common recipes
 */

function getDetailedIngredients($dishName, $category, $notes) {
    $dishNameLower = strtolower($dishName);
    $notesLower = strtolower($notes ?? '');
    
    // Detailed ingredient mappings based on Filipino cooking practices
    $ingredientMap = [
        // Sinangag (Garlic Fried Rice)
        'sinangag' => 'Garlic, Rice, Cooking oil, Salt, Black pepper, Onion (optional)',
        
        // Silog dishes
        'tapsilog' => 'Beef tapa, Garlic fried rice, Egg, Cooking oil, Salt, Black pepper',
        'tocilog' => 'Pork tocino, Garlic fried rice, Egg, Cooking oil, Salt, Sugar',
        'bangsilog' => 'Bangus (milkfish), Garlic fried rice, Egg, Cooking oil, Salt, Calamansi',
        'hotsilog' => 'Hotdog, Garlic fried rice, Egg, Cooking oil, Salt',
        'spamsilog' => 'Spam, Garlic fried rice, Egg, Cooking oil, Salt',
        'cornsilog' => 'Corned beef, Garlic fried rice, Egg, Cooking oil, Onion, Garlic',
        'baconsilog' => 'Bacon, Garlic fried rice, Egg, Cooking oil, Salt',
        'dangsilog' => 'Dried fish, Garlic fried rice, Egg, Cooking oil, Salt',
        'adobolog' => 'Adobo (chicken or pork), Garlic fried rice, Egg, Cooking oil',
        
        // Rice porridges
        'lugaw' => 'Rice, Water, Salt, Garlic, Ginger, Spring onion, Fried garlic, Chicken stock (optional)',
        'goto' => 'Rice, Beef tripe, Water, Salt, Garlic, Ginger, Onion, Spring onion, Fish sauce, Black pepper',
        'arroz caldo' => 'Rice, Chicken, Water, Ginger, Garlic, Onion, Spring onion, Fish sauce, Saffron or turmeric, Salt, Black pepper',
        'champorado' => 'Rice, Cocoa powder or tablea, Sugar, Water, Evaporated milk (optional)',
        
        // Bread and snacks
        'pandesal' => 'Flour, Yeast, Sugar, Salt, Butter or margarine, Eggs, Milk, Breadcrumbs',
        'taho' => 'Soft tofu, Brown sugar syrup (arnibal), Tapioca pearls (sago), Vanilla extract (optional)',
        
        // Omelettes
        'omelette w/ tomatoes' => 'Eggs, Tomatoes, Onion, Garlic, Salt, Black pepper, Cooking oil',
        'tortang talong' => 'Eggplant, Eggs, Onion, Garlic, Salt, Black pepper, Cooking oil',
        'tortang giniling' => 'Ground pork, Eggs, Onion, Garlic, Salt, Black pepper, Cooking oil, Potatoes (optional)',
        
        // Pancit dishes
        'pancit canton' => 'Egg noodles, Chicken or pork, Shrimp, Cabbage, Carrots, Green beans, Onion, Garlic, Soy sauce, Oyster sauce, Cooking oil, Salt, Black pepper',
        'pancit bihon' => 'Rice noodles, Chicken or pork, Shrimp, Cabbage, Carrots, Green beans, Onion, Garlic, Soy sauce, Fish sauce, Cooking oil, Salt, Black pepper',
        'pancit malabon' => 'Thick rice noodles, Shrimp, Squid, Oysters, Hard-boiled eggs, Chicharon, Onion, Garlic, Annatto powder, Shrimp paste, Cooking oil',
        'pancit palabok' => 'Rice noodles, Shrimp, Shrimp paste, Hard-boiled eggs, Chicharon, Tofu, Onion, Garlic, Annatto powder, Cooking oil, Calamansi',
        'pancit habhab' => 'Rice noodles, Pork, Vegetables, Onion, Garlic, Soy sauce, Vinegar, Banana leaf',
        'sotanghon guisado' => 'Glass noodles, Chicken or pork, Cabbage, Carrots, Green beans, Onion, Garlic, Soy sauce, Cooking oil, Salt, Black pepper',
        
        // Noodle soups
        'lomi' => 'Thick egg noodles, Pork, Chicken liver, Shrimp, Vegetables, Onion, Garlic, Soy sauce, Cornstarch, Cooking oil, Spring onion',
        'batchoy' => 'Egg noodles, Pork, Pork innards, Chicharon, Onion, Garlic, Fish sauce, Black pepper, Spring onion, Calamansi',
        'sopas' => 'Macaroni, Chicken, Milk or cream, Carrots, Cabbage, Onion, Garlic, Butter, Salt, Black pepper',
        
        // Adobo variations
        'adobo (chicken)' => 'Chicken, Soy sauce, Vinegar, Garlic, Bay leaves, Black peppercorns, Onion, Sugar, Cooking oil, Water',
        'adobo (pork)' => 'Pork, Soy sauce, Vinegar, Garlic, Bay leaves, Black peppercorns, Onion, Sugar, Cooking oil, Water',
        'adobo sa gata' => 'Chicken or pork, Soy sauce, Vinegar, Garlic, Bay leaves, Black peppercorns, Onion, Coconut milk, Cooking oil, Water',
        'adobong puti' => 'Chicken or pork, Vinegar, Garlic, Bay leaves, Black peppercorns, Onion, Salt, Cooking oil, Water',
        
        // Paksiw dishes
        'paksiw na lechon' => 'Lechon (roasted pig), Vinegar, Garlic, Bay leaves, Black peppercorns, Sugar, Salt, Water',
        'paksiw na isda' => 'Fish, Vinegar, Ginger, Garlic, Onion, Salt, Black pepper, Water, Green chili (optional)',
        
        // Stews
        'kare-kare' => 'Oxtail or tripe, Peanut butter, Annatto powder, Eggplant, String beans, Banana heart, Bok choy, Onion, Garlic, Shrimp paste (bagoong), Cooking oil, Water',
        'caldereta (beef)' => 'Beef, Tomato sauce, Potatoes, Carrots, Bell peppers, Onion, Garlic, Bay leaves, Liver spread, Cheese, Green olives, Cooking oil, Salt, Black pepper',
        'menudo (pork)' => 'Pork, Tomato sauce, Potatoes, Carrots, Bell peppers, Onion, Garlic, Bay leaves, Raisins, Green peas, Liver, Cooking oil, Salt, Black pepper',
        'afritada (chicken)' => 'Chicken, Tomato sauce, Potatoes, Carrots, Bell peppers, Onion, Garlic, Bay leaves, Cooking oil, Salt, Black pepper',
        'mechado (beef)' => 'Beef, Tomato sauce, Potatoes, Carrots, Onion, Garlic, Bay leaves, Pork fat (lard), Soy sauce, Cooking oil, Salt, Black pepper',
        
        // Bicol dishes
        'bicol express' => 'Pork, Coconut milk, Shrimp paste, Green chili peppers, Onion, Garlic, Ginger, Cooking oil, Salt',
        'laing' => 'Dried taro leaves, Coconut milk, Pork or shrimp, Shrimp paste, Green chili peppers, Onion, Garlic, Ginger, Cooking oil, Salt',
        
        // Vegetable dishes
        'pinakbet (ilocano)' => 'Eggplant, Okra, Bitter melon, String beans, Squash, Tomatoes, Onion, Garlic, Shrimp paste (bagoong), Pork (optional), Cooking oil, Water',
        'dinengdeng' => 'Grilled fish, Eggplant, Okra, String beans, Bitter melon, Tomatoes, Onion, Garlic, Fish sauce, Water',
        'ginataang sitaw at kalabasa' => 'String beans, Squash, Coconut milk, Shrimp or pork, Onion, Garlic, Ginger, Green chili (optional), Cooking oil, Salt',
        'chopsuey' => 'Mixed vegetables (cabbage, carrots, bell peppers, snow peas, mushrooms), Chicken or pork, Onion, Garlic, Soy sauce, Oyster sauce, Cornstarch, Cooking oil, Salt, Black pepper',
        'pakbet tagalog' => 'Eggplant, Okra, String beans, Squash, Tomatoes, Onion, Garlic, Shrimp paste (bagoong), Pork (optional), Cooking oil, Water',
        
        // Grilled dishes
        'inihaw na liempo' => 'Pork belly, Salt, Black pepper, Garlic, Calamansi, Soy sauce, Cooking oil',
        'chicken inasal' => 'Chicken, Lemongrass, Garlic, Ginger, Calamansi, Vinegar, Annatto powder, Salt, Black pepper, Cooking oil',
        'lechon kawali' => 'Pork belly, Salt, Black pepper, Garlic, Bay leaves, Water, Cooking oil',
        'crispy pata' => 'Pork knuckle, Salt, Black pepper, Garlic, Bay leaves, Water, Cooking oil',
        'inihaw na pusit' => 'Squid, Salt, Black pepper, Garlic, Calamansi, Soy sauce, Cooking oil',
        'inihaw na bangus' => 'Bangus (milkfish), Salt, Black pepper, Garlic, Calamansi, Cooking oil',
        'inihaw na tilapia' => 'Tilapia, Salt, Black pepper, Garlic, Calamansi, Cooking oil',
        
        // Other main dishes
        'bistek tagalog' => 'Beef sirloin, Soy sauce, Calamansi juice, Onion, Garlic, Black pepper, Bay leaves, Sugar, Cooking oil, Water',
        'giniling (pork)' => 'Ground pork, Potatoes, Carrots, Green peas, Onion, Garlic, Tomato sauce, Soy sauce, Cooking oil, Salt, Black pepper',
        'tokwa\'t baboy' => 'Tofu, Pork, Vinegar, Soy sauce, Onion, Garlic, Sugar, Salt, Black pepper, Cooking oil',
        'sinigang na baboy' => 'Pork, Tamarind (or sinigang mix), Tomatoes, Onion, Radish, Eggplant, Okra, String beans, Kangkong (water spinach), Fish sauce, Water, Salt',
        'sinigang na baka' => 'Beef, Tamarind (or sinigang mix), Tomatoes, Onion, Radish, Eggplant, Okra, String beans, Kangkong (water spinach), Fish sauce, Water, Salt',
        'sinigang na hipon' => 'Shrimp, Tamarind (or sinigang mix), Tomatoes, Onion, Radish, Eggplant, Okra, String beans, Kangkong (water spinach), Fish sauce, Water, Salt',
        'sinigang sa miso (bangus)' => 'Bangus (milkfish), Miso paste, Tamarind (or sinigang mix), Tomatoes, Onion, Radish, Eggplant, Okra, String beans, Kangkong (water spinach), Fish sauce, Water, Salt',
        'tinola (chicken)' => 'Chicken, Ginger, Onion, Garlic, Green papaya or chayote, Chili leaves or malunggay, Fish sauce, Water, Salt, Black pepper',
        'nilagang baka' => 'Beef, Potatoes, Cabbage, Carrots, Onion, Garlic, Bay leaves, Black peppercorns, Water, Salt',
        'bulalo' => 'Beef shank with bone marrow, Corn, Potatoes, Cabbage, Onion, Garlic, Bay leaves, Black peppercorns, Water, Salt, Fish sauce',
        'sinigang sa bayabas' => 'Pork or fish, Guava, Tomatoes, Onion, Radish, Eggplant, Okra, String beans, Kangkong (water spinach), Fish sauce, Water, Salt',
        'pininyahang manok' => 'Chicken, Pineapple chunks, Carrots, Bell peppers, Onion, Garlic, Tomato sauce, Soy sauce, Cooking oil, Salt, Black pepper',
        'pochero (luzon)' => 'Beef, Saba banana, Potatoes, Cabbage, Tomatoes, Onion, Garlic, Tomato sauce, Bay leaves, Water, Salt, Black pepper',
        'pochero (visayas)' => 'Beef, Saba banana, Chorizo, Potatoes, Cabbage, Tomatoes, Onion, Garlic, Tomato sauce, Bay leaves, Water, Salt, Black pepper',
        'humba (visayas)' => 'Pork, Black beans, Soy sauce, Vinegar, Brown sugar, Garlic, Onion, Bay leaves, Black peppercorns, Water, Salt',
        'igado (ilocos)' => 'Pork, Pork liver, Bell peppers, Carrots, Onion, Garlic, Soy sauce, Vinegar, Bay leaves, Black peppercorns, Cooking oil, Salt, Black pepper',
        'papaitan (ilocos)' => 'Beef innards (tripe, liver, heart), Bile, Ginger, Onion, Garlic, Green chili, Vinegar, Water, Salt, Black pepper',
        'bagnet (ilocos)' => 'Pork belly, Salt, Black pepper, Garlic, Bay leaves, Water, Cooking oil',
        'dinuguan' => 'Pork, Pork blood, Vinegar, Onion, Garlic, Green chili, Bay leaves, Sugar, Salt, Black pepper, Cooking oil',
        'pinangat (bicol)' => 'Taro leaves, Coconut milk, Fish or shrimp, Onion, Garlic, Ginger, Green chili, Shrimp paste, Cooking oil, Salt',
        'relyenong bangus' => 'Bangus (milkfish), Ground pork, Onion, Garlic, Carrots, Raisins, Eggs, Salt, Black pepper, Cooking oil',
        'embutido' => 'Ground pork, Carrots, Raisins, Pickles, Onion, Garlic, Eggs, Breadcrumbs, Salt, Black pepper, Cooking oil',
        
        // Lumpia
        'lumpiang shanghai' => 'Ground pork, Carrots, Onion, Garlic, Spring onion, Salt, Black pepper, Lumpia wrapper, Cooking oil',
        'lumpiang gulay' => 'Cabbage, Carrots, String beans, Green beans, Onion, Garlic, Salt, Black pepper, Lumpia wrapper, Cooking oil',
        'fresh lumpia (lumpiang sariwa)' => 'Cabbage, Carrots, String beans, Jicama, Shrimp, Pork, Onion, Garlic, Lumpia wrapper, Peanut sauce, Lettuce',
        'lumpiang togue' => 'Bean sprouts, Carrots, Onion, Garlic, Salt, Black pepper, Lumpia wrapper, Cooking oil',
        
        // Fritters
        'ukoy (shrimp fritters)' => 'Shrimp, Sweet potato, All-purpose flour, Cornstarch, Onion, Garlic, Salt, Black pepper, Cooking oil',
        'okoy kalabasa' => 'Squash, All-purpose flour, Cornstarch, Onion, Garlic, Salt, Black pepper, Cooking oil',
        
        // Kinilaw
        'kinilaw na tanigue' => 'Tanigue (Spanish mackerel), Vinegar, Onion, Ginger, Chili, Salt, Black pepper, Calamansi',
        'kilawing puso ng saging' => 'Banana heart, Vinegar, Onion, Ginger, Chili, Salt, Black pepper',
        
        // Munggo dishes
        'ginisang munggo' => 'Mung beans, Pork or shrimp, Onion, Garlic, Tomatoes, Spinach or malunggay, Fish sauce, Cooking oil, Water, Salt, Black pepper',
        'munggo with chicharon' => 'Mung beans, Pork, Chicharon, Onion, Garlic, Tomatoes, Spinach or malunggay, Fish sauce, Cooking oil, Water, Salt, Black pepper',
        
        // Other vegetable dishes
        'ginisang ampalaya' => 'Bitter gourd, Eggs, Onion, Garlic, Tomatoes, Fish sauce, Cooking oil, Salt, Black pepper',
        
        // Street food
        'isaw manok' => 'Chicken intestines, Vinegar, Salt, Black pepper, Cooking oil',
        'isaw baboy' => 'Pork intestines, Vinegar, Salt, Black pepper, Cooking oil',
        'betamax (dugo)' => 'Pork blood, Salt, Black pepper, Cooking oil',
        'adidas (chicken feet)' => 'Chicken feet, Vinegar, Salt, Black pepper, Cooking oil',
        'helmet (chicken head)' => 'Chicken head, Vinegar, Salt, Black pepper, Cooking oil',
        'kwek-kwek' => 'Quail eggs, All-purpose flour, Cornstarch, Annatto powder, Salt, Black pepper, Cooking oil',
        'tokneneng' => 'Chicken eggs, All-purpose flour, Cornstarch, Annatto powder, Salt, Black pepper, Cooking oil',
        'fishball' => 'Fish, All-purpose flour, Cornstarch, Salt, Black pepper, Cooking oil, Sweet or spicy sauce',
        'kikiam' => 'Ground pork, Carrots, Onion, Garlic, Salt, Black pepper, Wrapper, Cooking oil',
        'squidball' => 'Squid, All-purpose flour, Cornstarch, Salt, Black pepper, Cooking oil',
        'calamares (fried squid)' => 'Squid, All-purpose flour, Cornstarch, Salt, Black pepper, Cooking oil, Calamansi',
        'balut (fertilized duck egg)' => 'Fertilized duck egg, Salt, Vinegar',
        'penoy (unfertilized duck egg)' => 'Unfertilized duck egg, Salt',
        
        // Desserts
        'ensaymada' => 'Flour, Yeast, Sugar, Butter, Eggs, Milk, Cheese, Salt',
        'spanish bread' => 'Flour, Yeast, Sugar, Butter, Eggs, Milk, Breadcrumbs, Salt',
        'monay' => 'Flour, Yeast, Sugar, Salt, Water, Butter',
        'hopia (mongo)' => 'Flour, Mung beans, Sugar, Lard or shortening, Salt',
        'bibingka' => 'Rice flour, Coconut milk, Sugar, Eggs, Butter, Salt, Baking powder',
        'puto' => 'Rice flour, Sugar, Baking powder, Water, Salt',
        'kutsinta' => 'Rice flour, Brown sugar, Lye water, Water',
        'biko' => 'Glutinous rice, Coconut milk, Brown sugar, Salt',
        'sapin-sapin' => 'Glutinous rice flour, Coconut milk, Sugar, Ube, Langka, Salt',
        'maja blanca' => 'Cornstarch, Coconut milk, Sugar, Sweet corn, Salt',
        'palitaw' => 'Glutinous rice flour, Coconut, Sugar, Sesame seeds, Water',
        'suman malagkit' => 'Glutinous rice, Coconut milk, Salt, Banana leaves',
        'cassava cake' => 'Cassava, Coconut milk, Sugar, Eggs, Butter, Condensed milk, Salt',
        'ube halaya' => 'Purple yam (ube), Coconut milk, Sugar, Butter, Condensed milk, Salt',
        'leche flan' => 'Eggs, Condensed milk, Evaporated milk, Sugar, Vanilla extract, Salt',
        'halo-halo' => 'Shaved ice, Evaporated milk, Sweetened beans, Coconut strips, Jackfruit, Banana, Gelatin, Tapioca pearls, Purple yam (ube), Leche flan, Sugar',
        'mais con yelo' => 'Sweet corn, Shaved ice, Evaporated milk, Sugar',
        'saba con yelo' => 'Saba banana, Shaved ice, Evaporated milk, Sugar, Caramel',
        'turon' => 'Saba banana, Brown sugar, Lumpia wrapper, Cooking oil',
        'banana cue' => 'Saba banana, Brown sugar, Cooking oil',
        'camote cue' => 'Sweet potato, Brown sugar, Cooking oil',
        
        // Regional dishes
        'kansi (bacolod)' => 'Beef shank, Batwan or tamarind, Onion, Garlic, Ginger, Water, Salt, Black pepper',
        'kbl (kadios, baboy, langka)' => 'Pork, Kadios beans, Jackfruit, Onion, Garlic, Ginger, Vinegar, Water, Salt, Black pepper',
        'laswa (iloilo)' => 'Mixed vegetables, Shrimp, Onion, Garlic, Fish sauce, Water, Salt',
        'piyanggang manok (tausug)' => 'Chicken, Burnt coconut, Turmeric, Onion, Garlic, Ginger, Coconut milk, Cooking oil, Salt, Black pepper',
        'tiyula itum (tausug)' => 'Beef or chicken, Burnt coconut, Turmeric, Onion, Garlic, Ginger, Coconut milk, Cooking oil, Water, Salt, Black pepper',
        'piaparan (maranao)' => 'Chicken, Coconut milk, Turmeric, Onion, Garlic, Ginger, Green chili, Cooking oil, Salt, Black pepper',
        'pastil (maguindanao)' => 'Rice, Shredded chicken, Onion, Garlic, Turmeric, Cooking oil, Salt, Black pepper, Banana leaf',
        'tinowa (cebu)' => 'Fish, Onion, Garlic, Ginger, Tomatoes, Green chili, Water, Salt, Black pepper',
        'linarang (cebu)' => 'Fish, Onion, Garlic, Ginger, Tomatoes, Green chili, Vinegar, Water, Salt, Black pepper',
        'kinilaw na lato' => 'Seaweed (lato), Vinegar, Onion, Ginger, Chili, Salt, Black pepper, Calamansi',
        'bringhe (kapampangan paella)' => 'Glutinous rice, Chicken, Shrimp, Coconut milk, Turmeric, Onion, Garlic, Bell peppers, Raisins, Cooking oil, Salt, Black pepper',
        'morcon' => 'Beef, Hard-boiled eggs, Carrots, Pickles, Onion, Garlic, Tomato sauce, Bay leaves, Cooking oil, Salt, Black pepper',
        'callos (filipino)' => 'Beef tripe, Onion, Garlic, Tomatoes, Bell peppers, Green olives, Tomato sauce, Bay leaves, Cooking oil, Salt, Black pepper',
        'tinapa fried rice' => 'Smoked fish (tinapa), Rice, Garlic, Onion, Eggs, Cooking oil, Salt, Black pepper',
        'gising-gising' => 'Green beans, Coconut milk, Pork, Shrimp paste, Onion, Garlic, Green chili, Cooking oil, Salt',
        'pritong tilapia' => 'Tilapia, Salt, Black pepper, Cooking oil, Calamansi',
        'pritong bangus' => 'Bangus (milkfish), Salt, Black pepper, Cooking oil, Calamansi',
        'inasal na baboy' => 'Pork, Lemongrass, Garlic, Ginger, Calamansi, Vinegar, Annatto powder, Salt, Black pepper, Cooking oil',
        'tinapang galunggong' => 'Smoked round scad (galunggong), Salt, Cooking oil, Calamansi',
        'binagoongan (pork)' => 'Pork, Shrimp paste (bagoong), Eggplant, Onion, Garlic, Green chili, Vinegar, Sugar, Cooking oil, Salt',
        'ginataang hipon' => 'Shrimp, Coconut milk, Onion, Garlic, Ginger, Green chili, Shrimp paste, Cooking oil, Salt',
        'ginataang gulay (mixed)' => 'Mixed vegetables, Coconut milk, Shrimp or pork, Onion, Garlic, Ginger, Green chili, Cooking oil, Salt',
        'tinapa at kamatis' => 'Smoked fish (tinapa), Tomatoes, Onion, Salt, Black pepper',
        'arroz valenciana' => 'Glutinous rice, Chicken, Chorizo, Bell peppers, Onion, Garlic, Tomato sauce, Cooking oil, Salt, Black pepper',
        'pritong manok' => 'Chicken, Salt, Black pepper, Garlic, Cooking oil',
        'inihaw na manok' => 'Chicken, Salt, Black pepper, Garlic, Calamansi, Cooking oil',
        'skinless longganisa' => 'Ground pork, Garlic, Vinegar, Sugar, Salt, Black pepper, Sausage casing (optional)',
        'pork tocino' => 'Pork, Sugar, Salt, Black pepper, Garlic, Anise, Pineapple juice',
        'beef tapa' => 'Beef, Soy sauce, Calamansi juice, Garlic, Sugar, Salt, Black pepper',
    ];
    
    // Try exact match first
    foreach ($ingredientMap as $key => $ingredients) {
        if (stripos($dishNameLower, $key) !== false) {
            return $ingredients;
        }
    }
    
    // Pattern-based matching for variations
    if (stripos($dishNameLower, 'sinangag') !== false || stripos($dishNameLower, 'garlic fried rice') !== false) {
        return 'Garlic, Rice, Cooking oil, Salt, Black pepper, Onion (optional)';
    }
    
    if (stripos($dishNameLower, 'silog') !== false) {
        $mainIngredient = '';
        if (stripos($dishNameLower, 'tapa') !== false) $mainIngredient = 'Beef tapa';
        elseif (stripos($dishNameLower, 'tocino') !== false) $mainIngredient = 'Pork tocino';
        elseif (stripos($dishNameLower, 'bangus') !== false) $mainIngredient = 'Bangus (milkfish)';
        elseif (stripos($dishNameLower, 'hotdog') !== false) $mainIngredient = 'Hotdog';
        elseif (stripos($dishNameLower, 'spam') !== false) $mainIngredient = 'Spam';
        elseif (stripos($dishNameLower, 'corned') !== false) $mainIngredient = 'Corned beef';
        elseif (stripos($dishNameLower, 'bacon') !== false) $mainIngredient = 'Bacon';
        elseif (stripos($dishNameLower, 'dried fish') !== false || stripos($dishNameLower, 'danggit') !== false) $mainIngredient = 'Dried fish';
        elseif (stripos($dishNameLower, 'adobo') !== false) $mainIngredient = 'Adobo (chicken or pork)';
        else $mainIngredient = 'Main protein';
        
        return $mainIngredient . ', Garlic fried rice, Egg, Cooking oil, Salt, Black pepper';
    }
    
    if (stripos($dishNameLower, 'adobo') !== false) {
        $protein = 'Chicken or pork';
        if (stripos($dishNameLower, 'chicken') !== false) $protein = 'Chicken';
        elseif (stripos($dishNameLower, 'pork') !== false) $protein = 'Pork';
        
        $base = $protein . ', Soy sauce, Vinegar, Garlic, Bay leaves, Black peppercorns, Onion, Sugar, Cooking oil, Water';
        
        if (stripos($dishNameLower, 'gata') !== false || stripos($dishNameLower, 'coconut') !== false) {
            return str_replace('Soy sauce, ', 'Soy sauce, Coconut milk, ', $base);
        }
        if (stripos($dishNameLower, 'puti') !== false || stripos($dishNameLower, 'white') !== false) {
            return str_replace('Soy sauce, ', '', $base);
        }
        return $base;
    }
    
    if (stripos($dishNameLower, 'sinigang') !== false) {
        $protein = 'Pork';
        if (stripos($dishNameLower, 'baka') !== false || stripos($dishNameLower, 'beef') !== false) $protein = 'Beef';
        elseif (stripos($dishNameLower, 'hipon') !== false || stripos($dishNameLower, 'shrimp') !== false) $protein = 'Shrimp';
        elseif (stripos($dishNameLower, 'bangus') !== false) $protein = 'Bangus (milkfish)';
        elseif (stripos($dishNameLower, 'manok') !== false || stripos($dishNameLower, 'chicken') !== false) $protein = 'Chicken';
        
        $base = $protein . ', Tamarind (or sinigang mix), Tomatoes, Onion, Radish, Eggplant, Okra, String beans, Kangkong (water spinach), Fish sauce, Water, Salt';
        
        if (stripos($dishNameLower, 'miso') !== false) {
            return str_replace('Tamarind (or sinigang mix)', 'Miso paste, Tamarind (or sinigang mix)', $base);
        }
        if (stripos($dishNameLower, 'bayabas') !== false || stripos($dishNameLower, 'guava') !== false) {
            return str_replace('Tamarind (or sinigang mix)', 'Guava', $base);
        }
        return $base;
    }
    
    if (stripos($dishNameLower, 'pancit') !== false) {
        $base = 'Noodles, Chicken or pork, Shrimp, Cabbage, Carrots, Green beans, Onion, Garlic, Soy sauce, Cooking oil, Salt, Black pepper';
        
        if (stripos($dishNameLower, 'canton') !== false) {
            return 'Egg noodles, ' . substr($base, strpos($base, 'Chicken'));
        }
        if (stripos($dishNameLower, 'bihon') !== false) {
            return 'Rice noodles, ' . substr($base, strpos($base, 'Chicken'));
        }
        if (stripos($dishNameLower, 'palabok') !== false) {
            return 'Rice noodles, Shrimp, Shrimp paste, Hard-boiled eggs, Chicharon, Tofu, Onion, Garlic, Annatto powder, Cooking oil, Calamansi';
        }
        if (stripos($dishNameLower, 'malabon') !== false) {
            return 'Thick rice noodles, Shrimp, Squid, Oysters, Hard-boiled eggs, Chicharon, Onion, Garlic, Annatto powder, Shrimp paste, Cooking oil';
        }
        return $base;
    }
    
    if (stripos($dishNameLower, 'lumpia') !== false) {
        if (stripos($dishNameLower, 'shanghai') !== false) {
            return 'Ground pork, Carrots, Onion, Garlic, Spring onion, Salt, Black pepper, Lumpia wrapper, Cooking oil';
        }
        if (stripos($dishNameLower, 'gulay') !== false || stripos($dishNameLower, 'vegetable') !== false) {
            return 'Cabbage, Carrots, String beans, Green beans, Onion, Garlic, Salt, Black pepper, Lumpia wrapper, Cooking oil';
        }
        if (stripos($dishNameLower, 'sariwa') !== false || stripos($dishNameLower, 'fresh') !== false) {
            return 'Cabbage, Carrots, String beans, Jicama, Shrimp, Pork, Onion, Garlic, Lumpia wrapper, Peanut sauce, Lettuce';
        }
        if (stripos($dishNameLower, 'togue') !== false) {
            return 'Bean sprouts, Carrots, Onion, Garlic, Salt, Black pepper, Lumpia wrapper, Cooking oil';
        }
        return 'Lumpia wrapper, Filling (varies), Cooking oil';
    }
    
    if (stripos($dishNameLower, 'inihaw') !== false || stripos($dishNameLower, 'grilled') !== false) {
        $protein = 'Protein';
        if (stripos($dishNameLower, 'liempo') !== false || stripos($dishNameLower, 'pork belly') !== false) $protein = 'Pork belly';
        elseif (stripos($dishNameLower, 'manok') !== false || stripos($dishNameLower, 'chicken') !== false) $protein = 'Chicken';
        elseif (stripos($dishNameLower, 'bangus') !== false) $protein = 'Bangus (milkfish)';
        elseif (stripos($dishNameLower, 'tilapia') !== false) $protein = 'Tilapia';
        elseif (stripos($dishNameLower, 'pusit') !== false || stripos($dishNameLower, 'squid') !== false) $protein = 'Squid';
        
        return $protein . ', Salt, Black pepper, Garlic, Calamansi, Cooking oil';
    }
    
    if (stripos($dishNameLower, 'ginataang') !== false || stripos($dishNameLower, 'coconut') !== false) {
        $main = 'Mixed vegetables';
        if (stripos($dishNameLower, 'sitaw') !== false && stripos($dishNameLower, 'kalabasa') !== false) {
            $main = 'String beans, Squash';
        } elseif (stripos($dishNameLower, 'hipon') !== false || stripos($dishNameLower, 'shrimp') !== false) {
            $main = 'Shrimp';
        } elseif (stripos($dishNameLower, 'gulay') !== false) {
            $main = 'Mixed vegetables';
        }
        
        return $main . ', Coconut milk, Onion, Garlic, Ginger, Green chili (optional), Cooking oil, Salt';
    }
    
    // Default fallback - return existing ingredients if no match found
    return null;
}

// Read the CSV file
$inputFile = 'foodify_filipino_dishes_with_people_size.csv';
$outputFile = 'foodify_filipino_dishes_with_people_size_updated.csv';
$backupFile = 'foodify_filipino_dishes_with_people_size_backup_' . date('Y-m-d_His') . '.csv';

if (!file_exists($inputFile)) {
    die("Error: Input file '$inputFile' not found.\n");
}

// Create backup
copy($inputFile, $backupFile);
echo "Backup created: $backupFile\n";

// Open files
$inputHandle = fopen($inputFile, 'r');
$outputHandle = fopen($outputFile, 'w');

if (!$inputHandle || !$outputHandle) {
    die("Error: Could not open files.\n");
}

// Read header
$header = fgetcsv($inputHandle);
$ingredientsIndex = array_search('Ingredients', $header);

if ($ingredientsIndex === false) {
    die("Error: 'Ingredients' column not found in CSV.\n");
}

// Write header
fputcsv($outputHandle, $header);

$updated = 0;
$total = 0;

// Process each row
while (($row = fgetcsv($inputHandle)) !== FALSE) {
    $total++;
    
    if (count($row) <= $ingredientsIndex) {
        fputcsv($outputHandle, $row);
        continue;
    }
    
    $dishName = $row[0] ?? '';
    $category = $row[1] ?? '';
    $notes = $row[7] ?? '';
    $currentIngredients = $row[$ingredientsIndex] ?? '';
    
    // Get detailed ingredients
    $detailedIngredients = getDetailedIngredients($dishName, $category, $notes);
    
    if ($detailedIngredients !== null) {
        $row[$ingredientsIndex] = $detailedIngredients;
        $updated++;
    }
    
    fputcsv($outputHandle, $row);
}

fclose($inputHandle);
fclose($outputHandle);

// Replace original file
rename($outputFile, $inputFile);

echo "Processing complete!\n";
echo "Total rows processed: $total\n";
echo "Rows updated: $updated\n";
echo "Backup saved as: $backupFile\n";
?>


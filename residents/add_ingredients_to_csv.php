<?php
// Script to add Ingredients column to CSV file
// This will work with foodify_filipino_dishes_with_people_size.csv

$inputFile = __DIR__ . '/foodify_filipino_dishes_with_people_size.csv';
$originalFile = __DIR__ . '/foodify_filipino_dishes.csv';
$outputFile = __DIR__ . '/foodify_filipino_dishes_with_people_size.csv';
$tempFile = __DIR__ . '/foodify_filipino_dishes_with_people_size_temp_' . time() . '.csv';
$backupFile = __DIR__ . '/foodify_filipino_dishes_with_people_size_backup_' . date('YmdHis') . '.csv';

// Function to extract ingredients from dish name and notes
function extractIngredients($dishName, $notes = '') {
    $ingredients = [];
    $dishNameLower = strtolower($dishName);
    $notesLower = strtolower($notes);
    $combined = $dishNameLower . ' ' . $notesLower;
    
    // Common protein ingredients
    $proteins = [
        'chicken', 'pork', 'beef', 'fish', 'bangus', 'tilapia', 'galunggong', 'hipon', 'shrimp',
        'pusit', 'squid', 'tuna', 'sardinas', 'tinapa', 'smoked fish', 'egg', 'itlog', 'longganisa',
        'tapa', 'tocino', 'bacon', 'spam', 'hotdog', 'corned beef', 'dried fish', 'daing',
        'liver', 'tripe', 'innards', 'blood', 'dugong', 'dugo', 'chicharon', 'pork cracklings',
        'duck', 'quail', 'manok', 'baboy', 'baka', 'isda'
    ];
    
    // Common vegetables
    $vegetables = [
        'rice', 'garlic', 'onion', 'tomato', 'kamatis', 'potato', 'eggplant', 'talong',
        'kalabasa', 'squash', 'sitaw', 'string beans', 'ampalaya', 'bitter gourd',
        'kangkong', 'water spinach', 'pechay', 'cabbage', 'repolyo', 'sayote', 'chayote',
        'upo', 'bottle gourd', 'munggo', 'mung bean', 'togue', 'bean sprout',
        'papaya', 'banana', 'saba', 'langka', 'jackfruit', 'coconut', 'gata', 'coconut milk',
        'taro', 'gabi', 'laing', 'taro leaves', 'malunggay', 'moringa', 'okra',
        'carrots', 'peas', 'green beans', 'chicharo', 'gulay', 'vegetables'
    ];
    
    // Check for proteins
    foreach ($proteins as $protein) {
        if (stripos($combined, $protein) !== false) {
            $ingName = ucfirst($protein);
            if ($protein == 'manok') $ingName = 'Chicken';
            elseif ($protein == 'baboy') $ingName = 'Pork';
            elseif ($protein == 'baka') $ingName = 'Beef';
            elseif ($protein == 'isda') $ingName = 'Fish';
            elseif ($protein == 'itlog') $ingName = 'Egg';
            if (!in_array($ingName, $ingredients)) {
                $ingredients[] = $ingName;
            }
        }
    }
    
    // Check for vegetables
    foreach ($vegetables as $veg) {
        if (stripos($combined, $veg) !== false) {
            $ingName = ucfirst($veg);
            if ($veg == 'gata') $ingName = 'Coconut milk';
            elseif ($veg == 'kamatis') $ingName = 'Tomato';
            elseif ($veg == 'talong') $ingName = 'Eggplant';
            elseif ($veg == 'kalabasa') $ingName = 'Squash';
            elseif ($veg == 'sitaw') $ingName = 'String beans';
            elseif ($veg == 'ampalaya') $ingName = 'Bitter gourd';
            elseif ($veg == 'kangkong') $ingName = 'Water spinach';
            elseif ($veg == 'repolyo') $ingName = 'Cabbage';
            elseif ($veg == 'sayote') $ingName = 'Chayote';
            elseif ($veg == 'upo') $ingName = 'Bottle gourd';
            elseif ($veg == 'munggo') $ingName = 'Mung bean';
            elseif ($veg == 'togue') $ingName = 'Bean sprout';
            elseif ($veg == 'langka') $ingName = 'Jackfruit';
            elseif ($veg == 'gabi') $ingName = 'Taro';
            elseif ($veg == 'laing') $ingName = 'Taro leaves';
            elseif ($veg == 'malunggay') $ingName = 'Moringa';
            if (!in_array($ingName, $ingredients)) {
                $ingredients[] = $ingName;
            }
        }
    }
    
    // Special patterns for Filipino dishes
    if (stripos($dishNameLower, 'sinangag') !== false || stripos($dishNameLower, 'fried rice') !== false) {
        if (!in_array('Rice', $ingredients)) $ingredients[] = 'Rice';
        if (!in_array('Garlic', $ingredients)) $ingredients[] = 'Garlic';
    }
    
    if (stripos($dishNameLower, 'adobo') !== false) {
        if (!in_array('Soy sauce', $ingredients)) $ingredients[] = 'Soy sauce';
        if (!in_array('Vinegar', $ingredients)) $ingredients[] = 'Vinegar';
        if (!in_array('Garlic', $ingredients)) $ingredients[] = 'Garlic';
    }
    
    if (stripos($dishNameLower, 'sinigang') !== false) {
        if (!in_array('Tamarind', $ingredients)) $ingredients[] = 'Tamarind';
        if (!in_array('Vegetables', $ingredients)) $ingredients[] = 'Vegetables';
    }
    
    if (stripos($dishNameLower, 'ginataang') !== false || stripos($dishNameLower, 'ginataan') !== false) {
        if (!in_array('Coconut milk', $ingredients)) $ingredients[] = 'Coconut milk';
    }
    
    if (stripos($dishNameLower, 'kare-kare') !== false) {
        if (!in_array('Peanut', $ingredients)) $ingredients[] = 'Peanut';
        if (!in_array('Bagoong', $ingredients)) $ingredients[] = 'Bagoong';
    }
    
    if (stripos($dishNameLower, 'pancit') !== false || stripos($dishNameLower, 'noodles') !== false || stripos($dishNameLower, 'mami') !== false || stripos($dishNameLower, 'batchoy') !== false || stripos($dishNameLower, 'lomi') !== false || stripos($dishNameLower, 'sopas') !== false) {
        if (!in_array('Noodles', $ingredients)) $ingredients[] = 'Noodles';
    }
    
    if (stripos($dishNameLower, 'lugaw') !== false || stripos($dishNameLower, 'porridge') !== false || stripos($dishNameLower, 'arroz caldo') !== false || stripos($dishNameLower, 'goto') !== false) {
        if (!in_array('Rice', $ingredients)) $ingredients[] = 'Rice';
    }
    
    if (stripos($dishNameLower, 'tortang') !== false || stripos($dishNameLower, 'omelette') !== false) {
        if (!in_array('Egg', $ingredients)) $ingredients[] = 'Egg';
    }
    
    if (stripos($dishNameLower, 'lumpia') !== false) {
        if (!in_array('Lumpia wrapper', $ingredients)) $ingredients[] = 'Lumpia wrapper';
    }
    
    if (stripos($dishNameLower, 'silog') !== false) {
        if (!in_array('Rice', $ingredients)) $ingredients[] = 'Rice';
        if (!in_array('Egg', $ingredients)) $ingredients[] = 'Egg';
    }
    
    // Extract from notes - look for common patterns
    if (stripos($notesLower, 'with') !== false) {
        preg_match('/with\s+([^,\.]+)/i', $notesLower, $matches);
        if (isset($matches[1])) {
            $withItems = preg_split('/\s+and\s+|\s*\+\s*/i', $matches[1]);
            foreach ($withItems as $item) {
                $item = trim($item);
                if (strlen($item) > 2) {
                    $itemCap = ucfirst($item);
                    if (!in_array($itemCap, $ingredients)) {
                        $ingredients[] = $itemCap;
                    }
                }
            }
        }
    }
    
    // Remove duplicates and sort
    $ingredients = array_unique($ingredients);
    sort($ingredients);
    
    return !empty($ingredients) ? implode(', ', $ingredients) : 'Rice, Vegetables';
}

// Determine which file to use
$fileToUse = file_exists($inputFile) && filesize($inputFile) > 0 ? $inputFile : $originalFile;

if (!file_exists($fileToUse)) {
    die("Error: Neither $inputFile nor $originalFile found.\n");
}

echo "Using file: $fileToUse\n";
echo "File size: " . filesize($fileToUse) . " bytes\n";

// Create backup if output file exists and has content
if (file_exists($outputFile) && filesize($outputFile) > 0) {
    echo "Creating backup...\n";
    copy($outputFile, $backupFile);
    echo "Backup created: $backupFile\n";
}

// Read and process CSV
$in = fopen($fileToUse, 'r');
$out = fopen($tempFile, 'w');

if (!$in) {
    die('Error opening input file: ' . $fileToUse);
}
if (!$out) {
    die('Error opening output file: ' . $outputFile);
}

// Read header
$header = fgetcsv($in, 0, ',', '"');
if ($header === false || empty($header)) {
    die('Error reading header. File might be empty or corrupted.');
}

echo "Original columns: " . count($header) . "\n";

// Check if Ingredients column already exists
$hasIngredients = in_array('Ingredients', $header);
$hasPeopleSize = in_array('People Size', $header);

// Add Ingredients column if it doesn't exist
if (!$hasIngredients) {
    $header[] = 'Ingredients';
    echo "Added Ingredients column\n";
} else {
    echo "Ingredients column already exists\n";
}

// Write header
fputcsv($out, $header, ',', '"');

$rowCount = 0;
$processed = 0;

// Process each row
while (($row = fgetcsv($in, 0, ',', '"')) !== false) {
    $rowCount++;
    
    // Skip empty rows
    if (empty(array_filter($row))) {
        continue;
    }
    
    // Ensure row has enough columns to match header (minus Ingredients)
    $expectedCols = count($header) - ($hasIngredients ? 0 : 1);
    while (count($row) < $expectedCols) {
        $row[] = '';
    }
    
    // Extract dish name and notes
    $dishName = isset($row[0]) ? trim($row[0], '"') : '';
    $notesIndex = 7; // Notes is typically column 7
    if (count($row) > $notesIndex) {
        $notes = trim($row[$notesIndex], '"');
    } else {
        $notes = '';
    }
    
    // Extract ingredients if column doesn't exist or is empty
    if (!$hasIngredients || (isset($row[count($header) - 1]) && empty(trim($row[count($header) - 1])))) {
        $ingredients = extractIngredients($dishName, $notes);
        if (!$hasIngredients) {
            $row[] = $ingredients;
        } else {
            $row[count($header) - 1] = $ingredients;
        }
    }
    
    // Write row
    fputcsv($out, $row, ',', '"');
    $processed++;
    
    if ($rowCount % 100 == 0) {
        echo "Processed $rowCount rows...\n";
    }
}

fclose($in);
fflush($out);
fclose($out);

// Verify temp file was written
clearstatcache();
$tempSize = filesize($tempFile);

echo "\nDone! Processed $processed rows.\n";
echo "Temp file size: " . $tempSize . " bytes\n";

if ($tempSize == 0) {
    echo "WARNING: Temp file is empty! There may have been an error.\n";
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
} else {
    // Move temp file to output file
    if (file_exists($outputFile)) {
        if (file_exists($backupFile)) {
            unlink($backupFile);
        }
        rename($outputFile, $backupFile);
        echo "Backed up existing file to: $backupFile\n";
    }
    rename($tempFile, $outputFile);
    $finalSize = filesize($outputFile);
    echo "SUCCESS: File created successfully!\n";
    echo "Output file: $outputFile\n";
    echo "Final file size: " . $finalSize . " bytes\n";
}
?>


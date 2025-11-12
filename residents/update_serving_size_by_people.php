<?php
$inputFile = __DIR__ . '/foodify_filipino_dishes_with_people_size.csv';
$outputFile = __DIR__ . '/foodify_filipino_dishes_with_people_size_adjusted.csv';

if (!file_exists($inputFile)) {
    die('CSV file not found.');
}

$in = fopen($inputFile, 'r');
$out = fopen($outputFile, 'w');

$header = fgetcsv($in);
fputcsv($out, $header);

function adjust_serving_size($serving, $peopleSize) {
    $peopleSize = (int)$peopleSize;
    if ($peopleSize > 1) {
        // Try to extract the number and unit
        if (preg_match('/(\d+)\s*([a-zA-Z ]+)/', $serving, $m)) {
            $unit = trim($m[2]);
            $perPerson = '1 ' . $unit . ' per person (' . $peopleSize . ' people)';
            $divided = '1 ' . $unit;
            return [$perPerson, $divided . ' (for each of ' . $peopleSize . ' people)'];
        } else {
            // fallback if not matching
            return [$serving . ' (shared by ' . $peopleSize . ' people)', $serving . ' (shared by ' . $peopleSize . ' people)'];
        }
    } elseif ($peopleSize === 1) {
        // Add 'per person' if not present
        if (stripos($serving, 'per person') === false) {
            return [$serving . ' per person', $serving . ' per person'];
        } else {
            return [$serving, $serving];
        }
    } else {
        // Unknown people size, leave as is
        return [$serving, $serving];
    }
}

while (($row = fgetcsv($in)) !== false) {
    $serving = isset($row[2]) ? $row[2] : '';
    $peopleSize = isset($row[8]) ? $row[8] : '';
    list($perPerson, $divided) = adjust_serving_size($serving, $peopleSize);
    // Option 1: per-person format in Serving Size
    $row[2] = $perPerson;
    // Option 2: add a new column for divided serving size
    if (count($row) == 9) {
        $row[] = $divided;
    } else {
        $row[9] = $divided;
    }
    fputcsv($out, $row);
}

fclose($in);
fclose($out);
echo "Done! Output: $outputFile\n";

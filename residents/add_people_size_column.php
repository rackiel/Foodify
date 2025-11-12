<?php
// Path to the CSV file
$inputFile = __DIR__ . '/foodify_filipino_dishes.csv';
$outputFile = __DIR__ . '/foodify_filipino_dishes_with_people_size.csv';

if (!file_exists($inputFile)) {
    die('CSV file not found.');
}

$in = fopen($inputFile, 'r');
$out = fopen($outputFile, 'w');

// Read and update header
$header = fgetcsv($in);
$header[] = 'People Size';
fputcsv($out, $header);

function estimate_people_size($serving, $dish) {
    // Try to extract a number from the serving size
    if (preg_match('/(\\d+)\\s*(pcs?|pieces?|sticks?|rolls?|eggs?|links?)/i', $serving, $m)) {
        return $m[1];
    }
    if (preg_match('/(\\d+)\\s*set/i', $serving, $m)) {
        return $m[1];
    }
    if (preg_match('/(\\d+)\\s*cup/i', $serving, $m)) {
        return $m[1];
    }
    if (preg_match('/(\\d+)\\s*bowl/i', $serving, $m)) {
        return $m[1];
    }
    if (preg_match('/(\\d+)\\s*plate/i', $serving, $m)) {
        return $m[1];
    }
    if (preg_match('/(\\d+)\\s*slice/i', $serving, $m)) {
        return $m[1];
    }
    if (preg_match('/(\\d+)\\s*pack/i', $serving, $m)) {
        return $m[1];
    }
    if (preg_match('/(\\d+)\\s*stick/i', $serving, $m)) {
        return $m[1];
    }
    if (preg_match('/(\\d+)\\s*set/i', $serving, $m)) {
        return $m[1];
    }
    // If it says 'per person', '1 set', '1 bowl', '1 plate', '1 piece', '1 egg', etc.
    if (preg_match('/^(1|One)\\s*(set|bowl|plate|piece|egg|cup|serving|roll|link)/i', trim($serving))) {
        return 1;
    }
    // If dish name contains 'for 2', 'for 3', etc.
    if (preg_match('/for (\\d+)/i', $dish, $m)) {
        return $m[1];
    }
    // If not clear, leave blank
    return '';
}

while (($row = fgetcsv($in)) !== false) {
    $serving = isset($row[2]) ? $row[2] : '';
    $dish = isset($row[0]) ? $row[0] : '';
    $peopleSize = estimate_people_size($serving, $dish);
    $row[] = $peopleSize;
    fputcsv($out, $row);
}

fclose($in);
fclose($out);
echo "Done! Output: $outputFile\n";

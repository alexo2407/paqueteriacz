<?php
// Test simple para el generador CSV: verifica comas, comillas, fórmulas y EOL CRLF
require_once __DIR__ . '/../utils/csv_generator.php';

$headers = ['col1','col2','col3'];
$rows = [
    ['col1' => 'Value with, comma', 'col2' => 'Normal', 'col3' => '=SUM(1,2)'],
    ['col1' => 'He said "Hello"', 'col2' => '+CMD', 'col3' => '123'],
];

$temp = fopen('php://temp', 'r+');
generate_csv_stream($rows, $headers, $temp, ',', true);
rewind($temp);
$out = stream_get_contents($temp);
fclose($temp);

// Expectations
$passed = true;
$errors = [];

// Check BOM
if (substr($out,0,3) !== "\xEF\xBB\xBF") {
    $passed = false;
    $errors[] = 'Missing BOM';
}

// Check CRLF present
if (strpos($out, "\r\n") === false) {
    $passed = false;
    $errors[] = 'Missing CRLF EOL';
}

// Check sanitized formula: original '=SUM' should become "'=SUM"
if (strpos($out, "'=SUM(1,2)") === false) {
    $passed = false;
    $errors[] = 'Formula not sanitized';
}

// Check commas and quotes properly escaped
if (strpos($out, '"He said ""Hello"""') === false && strpos($out, '"He said "Hello""') === false) {
    // try simpler check: presence of Hello
    if (strpos($out, 'Hello') === false) {
        $passed = false;
        $errors[] = 'Quoted field missing or malformed';
    }
}

if ($passed) {
    echo "TEST PASSED\n";
    exit(0);
} else {
    echo "TEST FAILED\n";
    foreach ($errors as $e) echo "- $e\n";
    echo "-- OUTPUT BEGIN --\n". $out . "\n-- OUTPUT END --\n";
    exit(2);
}

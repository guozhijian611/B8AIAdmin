<?php

require_once __DIR__ . '/../utils/DataScope.php';

use plugin\saiadmin\utils\DataScope;

$tests = [
    ['input' => [5, 1], 'expected' => 1],
    ['input' => [5, 2, 3], 'expected' => 3],
    ['input' => [2, 4], 'expected' => 4],
    ['input' => [2, 2], 'expected' => 2],
    ['input' => [5], 'expected' => 5],
    ['input' => [], 'expected' => 5],
];

$passed = 0;
foreach ($tests as $i => $test) {
    $result = DataScope::resolveWidest($test['input']);
    if ($result === $test['expected']) {
        echo "Test " . ($i + 1) . " PASSED: input=" . json_encode($test['input']) . ", expected=" . $test['expected'] . ", got=" . $result . PHP_EOL;
        $passed++;
    } else {
        echo "Test " . ($i + 1) . " FAILED: input=" . json_encode($test['input']) . ", expected=" . $test['expected'] . ", got=" . $result . PHP_EOL;
    }
}

echo "Passed $passed/" . count($tests) . " tests." . PHP_EOL;

if ($passed !== count($tests)) {
    exit(1);
}

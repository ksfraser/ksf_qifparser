<?php
require_once __DIR__ . '/vendor/autoload.php';
$cov = include __DIR__ . '/coverage.php';
$data = $cov->getData();
foreach ($data->lineCoverage() as $file => $lines) {
    if (strpos($file, 'DateParser') !== false) {
        echo $file . PHP_EOL;
        foreach ($lines as $line => $count) {
            $status = is_array($count) && count($count) > 0 ? 'COVERED' : 'UNCOVERED';
            echo "  Line $line: $status (" . json_encode($count) . ")" . PHP_EOL;
        }
    }
}

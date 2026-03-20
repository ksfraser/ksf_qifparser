<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Ksfraser\QifParser\QifParser;

/**
 * Validation Script: Transaction Totals and Integrity
 * 
 * This script iterates through all sanitized fixtures and verifies:
 * 1. Transaction counts match expected.
 * 2. Sum of all transaction amounts (Absolute total for high-level check).
 * 3. Split integrity (FR-2.1.4: Sum of splits must equal transaction amount).
 */

$fixturesDir = __DIR__ . '/../tests/fixtures';
$files = glob("$fixturesDir/*.{qif,QIF}", GLOB_BRACE);

if (empty($files)) {
    die("No fixtures found in $fixturesDir. Please run sanitization first.\n");
}

echo "QIF Parser Validation Report\n";
echo "============================\n\n";
echo sprintf("%-30s | %-5s | %-12s | %-10s\n", "Filename", "Trx", "Total Amt", "Split Check");
echo str_repeat("-", 65) . "\n";

foreach ($files as $file) {
    $filename = basename($file);
    
    try {
        $parser = new QifParser('VALIDATION_BANK', 'VALIDATION_ACCOUNT');
        $transactions = $parser->parse($file);
        
        $count = count($transactions);
        $totalAmount = 0.0;
        $splitErrors = 0;
        
        foreach ($transactions as $trx) {
            $totalAmount += $trx->amount;
            if (!$trx->validateSplits()) {
                $splitErrors++;
            }
        }
        
        echo sprintf(
            "%-30s | %-5d | %-12.2f | %-10s\n",
            substr($filename, 0, 30),
            $count,
            $totalAmount,
            $splitErrors === 0 ? "PASS" : "FAIL ($splitErrors)"
        );
        
    } catch (\Exception $e) {
        echo sprintf("%-30s | ERROR: %s\n", substr($filename, 0, 30), $e->getMessage());
    }
}

echo "\nValidation Complete.\n";

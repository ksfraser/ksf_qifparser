<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Ksfraser\QifParser\QifParser;

$fixturesDir = __DIR__ . '/../tests/fixtures';
if (!is_dir($fixturesDir)) {
    die("Fixtures directory not found: $fixturesDir\n");
}

$files = glob("$fixturesDir/*.{qif,QIF}", GLOB_BRACE);
if (empty($files)) {
    die("No QIF files found in $fixturesDir\n");
}

echo "QIF Parser Diagnostic Summary\n";
echo "============================\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    echo "File: $filename\n";
    
    try {
        // Updated to include required constructor arguments
        $parser = new QifParser('DEFAULT_BANK', 'DEFAULT_ACCOUNT');
        $transactions = $parser->parse($file);
        
        $count = count($transactions);
        echo "  Total Transactions: $count\n";
        
        if ($count > 0) {
            // Summary of first and last transaction
            $first = $transactions[0];
            echo "  Trx 1 Summary:\n";
            echo "    Date:   " . ($first->date ?? 'N/A') . "\n";
            echo "    Amount: " . $first->amount . "\n";
            echo "    Payee:  " . ($first->payee ?? 'N/A') . "\n";
            echo "    FITID:  " . ($first->fitid ?? 'N/A') . "\n";
            
            if ($count > 1) {
                $last = $transactions[$count - 1];
                echo "  Trx $count Summary:\n";
                echo "    Date:   " . ($last->date ?? 'N/A') . "\n";
                echo "    Amount: " . $last->amount . "\n";
                echo "    Payee:  " . ($last->payee ?? 'N/A') . "\n";
            }
        }
    } catch (\Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "Summary complete.\n";

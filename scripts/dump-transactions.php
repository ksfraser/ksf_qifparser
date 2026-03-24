<?php

/**
 * QIF Transaction Dump Utility
 *
 * Iterates every QIF file in a directory and prints full statement and
 * transaction detail. Intended as a developer inspection tool for new
 * or unknown QIF files — particularly the raw (unsanitized) samples in
 * the QIFs/ directory.
 *
 * Usage:
 *   php scripts/dump-transactions.php [directory]
 *
 * If [directory] is omitted the script defaults to QIFs/ in the repo root.
 *
 * @requirement FR-1.1, FR-2.1.1, FR-2.1.3, FR-2.1.4
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Ksfraser\QifParser\QifParser;

// ---------------------------------------------------------------------------
// Directory resolution
// ---------------------------------------------------------------------------
$targetDir = isset($argv[1])
    ? rtrim($argv[1], DIRECTORY_SEPARATOR)
    : __DIR__ . '/../QIFs';

if (!is_dir($targetDir)) {
    fwrite(STDERR, "Error: directory not found: $targetDir\n");
    exit(1);
}

$files = glob("$targetDir/*.{qif,QIF}", GLOB_BRACE);
sort($files);

if (empty($files)) {
    fwrite(STDERR, "No QIF files found in $targetDir\n");
    exit(0);
}

// ---------------------------------------------------------------------------
// Formatting helpers
// ---------------------------------------------------------------------------

function hr(string $char = '-', int $width = 72): string
{
    return str_repeat($char, $width) . "\n";
}

function fmtAmount(float $amount): string
{
    return sprintf('%+10.2f', $amount);
}

// ---------------------------------------------------------------------------
// Main loop
// ---------------------------------------------------------------------------

echo "\n";
echo "QIF Transaction Dump\n";
echo "Directory: " . realpath($targetDir) . "\n";
echo "Files found: " . count($files) . "\n";
echo hr('=');

$grandTotal   = 0.0;
$grandCount   = 0;
$fileErrors   = 0;

foreach ($files as $filePath) {
    $filename = basename($filePath);

    echo "\n";
    echo hr();
    echo "FILE: $filename\n";
    echo hr();

    try {
        $content    = file_get_contents($filePath);
        $parser     = new QifParser('DUMP', 'DUMP', 'CAD', 'MDY');
        $statement  = $parser->parseContent($content);
        $txns       = $statement->transactions;
        $count      = count($txns);

        // ---- Statement summary -------------------------------------------
        echo "  Type        : " . ($statement->type      ?: '(not set)') . "\n";
        echo "  Currency    : " . ($statement->currency  ?: '(not set)') . "\n";
        echo "  Transactions: $count\n";

        if ($count === 0) {
            echo "  (no transactions)\n";
            continue;
        }

        // ---- Transaction table -------------------------------------------
        echo "\n";
        echo sprintf(
            "  %-4s  %-12s  %10s  %-28s  %-22s  %-18s  %-8s\n",
            '#', 'Date', 'Amount', 'Payee', 'Memo', 'Category', 'Splits'
        );
        echo '  ' . str_repeat('-', 100) . "\n";

        $fileTotal = 0.0;

        foreach ($txns as $i => $trx) {
            $fileTotal += $trx->amount;
            $splitInfo  = count($trx->splits) > 0
                ? count($trx->splits) . ' split(s)'
                : '';

            echo sprintf(
                "  %-4d  %-12s  %10s  %-28s  %-22s  %-18s  %s\n",
                $i + 1,
                $trx->date   ?? '?',
                fmtAmount($trx->amount),
                substr($trx->payee    ?? '', 0, 28),
                substr($trx->memo     ?? '', 0, 22),
                substr($trx->category ?? '', 0, 18),
                $splitInfo
            );

            // ---- Split detail --------------------------------------------
            foreach ($trx->splits as $s) {
                $splitValid = abs($trx->amount) > 0.0001
                    ? ($trx->validateSplits() ? 'OK' : 'MISMATCH')
                    : '';
                echo sprintf(
                    "       %s  %-11s  %10s  %-30s  %-20s\n",
                    '↳',
                    '',
                    fmtAmount($s->amount),
                    substr($s->category ?? '', 0, 30),
                    substr($s->memo     ?? '', 0, 20)
                );
            }

            // ---- Split integrity check (only once per transaction) --------
            if (count($trx->splits) > 0 && !$trx->validateSplits()) {
                $splitSum = array_sum(array_map(fn($s) => $s->amount, $trx->splits));
                echo sprintf(
                    "       *** SPLIT MISMATCH: trx=%s  splits=%s  diff=%s ***\n",
                    fmtAmount($trx->amount),
                    fmtAmount($splitSum),
                    fmtAmount($trx->amount - $splitSum)
                );
            }

            // ---- Payee address (if captured) -----------------------------
            if ($trx->payeeDetails && !empty($trx->payeeDetails->address)) {
                $addr = implode(', ', array_filter($trx->payeeDetails->address));
                if ($addr !== '') {
                    echo "       Addr: $addr\n";
                }
            }
        }

        // ---- File totals -------------------------------------------------
        echo '  ' . str_repeat('-', 100) . "\n";
        echo sprintf("  %-4s  %-12s  %10s\n", '', 'FILE TOTAL', fmtAmount($fileTotal));

        $grandTotal += $fileTotal;
        $grandCount += $count;

    } catch (\Exception $e) {
        echo "  ERROR: " . $e->getMessage() . "\n";
        $fileErrors++;
    }
}

// ---------------------------------------------------------------------------
// Grand summary
// ---------------------------------------------------------------------------
echo "\n";
echo hr('=');
echo sprintf("Grand total  : %s\n", fmtAmount($grandTotal));
echo sprintf("Transactions : %d\n", $grandCount);
echo sprintf("Files        : %d processed, %d error(s)\n", count($files) - $fileErrors, $fileErrors);
echo "\n";

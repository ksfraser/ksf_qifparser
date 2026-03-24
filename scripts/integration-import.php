<?php

/**
 * Integration Simulation: bank_import import_statements → qif_parser → validate()
 *
 * Reproduces the EXACT call flow that bank_import/import_statements.php uses
 * when a QIF file is uploaded.  Run this to see what the production <pre> block
 * would print and, crucially, WHY "no transaction details" appears.
 *
 * Two operating modes:
 *
 *   LOCAL (default)
 *     Uses the REAL banking.php from ksf_bank_import (so real validate() logic)
 *     and the CURRENT qif_parser.php from THIS repo.
 *     Use this to test that the version you're about to deploy works correctly.
 *
 *   VENDOR (--vendor flag)
 *     Uses banking.php AND the qif_parser.php that bank_import actually loaded in
 *     production (vendor/ksfraser/qifparser/qif_parser.php).
 *     Use this to reproduce the blank-screen issue.
 *
 * Usage:
 *   php scripts/integration-import.php [qif_file] [--vendor] [--all] [--debug]
 *
 *   qif_file   Path to a single QIF file.
 *              Defaults to the first .qif found in QIFs/.
 *   --all      Process every file in QIFs/ (overrides qif_file).
 *   --vendor   Use bank_import's deployed vendor copy of qif_parser.
 *   --debug    Pass debug=true to parse(), which dumps every transaction object.
 *   --dump     Show all transaction fields at the end (like statement->dump()).
 *
 * Simulated bank_account record (mirrors a typical FA bank_accounts row):
 *   account_code         = 1060    (CIBC PCMC Visa GL code)
 *   bank_account_number  = PCMC-VISA-001
 *   bank_curr_code       = CAD
 *   bank_account_name    = CIBC PCMC Visa
 *   bank_charge_act      = 5400
 *   account_type         = CCard
 *
 * NOTE: bank_import does NOT pass 'bank_id' or 'date_format' to the parser.
 *       qif_parser falls back to:
 *           bank_id     → account_code (so '1060')
 *           date_format → 'MDY'
 *       The statement-ID will therefore be YYYY-MM-DD-1060-1060.
 *
 * @see ksf_bank_import/import_statements.php  lines 528-546 (static_data build)
 * @see ksf_bank_import/import_statements.php  lines 718, 740-760 (parse + validate loop)
 * @see ksf_bank_import/includes/banking.php   statement::validate(), transaction::validate()
 *
 * @requirement FR-1.1, FR-2.1.1, FR-2.1.3, FR-2.1.4
 */

// ---------------------------------------------------------------------------
// CLI argument parsing
// ---------------------------------------------------------------------------
$useVendor   = in_array('--vendor', $argv, true);
$debugParse  = in_array('--debug',  $argv, true);
$dumpFields  = in_array('--dump',   $argv, true);
$processAll  = in_array('--all',    $argv, true);

$repoRoot     = dirname(__DIR__);
$bankImport   = dirname($repoRoot) . '/ksf_bank_import';
$qifsDir      = $repoRoot . '/QIFs';

// Determine which QIF file(s) to process
if ($processAll) {
    $files = glob("$qifsDir/*.{qif,QIF}", GLOB_BRACE);
    sort($files);
} else {
    // Look for a non-flag argument as explicit path
    $explicitFile = null;
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg[0] !== '-') {
            $explicitFile = $arg;
            break;
        }
    }
    if ($explicitFile) {
        $files = [$explicitFile];
    } else {
        $candidates = glob("$qifsDir/*.{qif,QIF}", GLOB_BRACE);
        sort($candidates);
        $files = $candidates ? [$candidates[0]] : [];
    }
}

if (empty($files)) {
    fwrite(STDERR, "No QIF files found.  Provide a path or put files in QIFs/.\n");
    exit(1);
}

// ---------------------------------------------------------------------------
// 1. Load banking.php (real statement / transaction classes from bank_import)
// ---------------------------------------------------------------------------
$bankingPhp = $bankImport . '/includes/banking.php';
if (!file_exists($bankingPhp)) {
    fwrite(STDERR, "Cannot find bank_import/includes/banking.php at:\n  $bankingPhp\n");
    exit(1);
}

// Guard: don't double-define if the test stubs are already loaded
if (!class_exists('banking_base', false)) {
    require_once $bankingPhp;
}

// ---------------------------------------------------------------------------
// 2. Load the qif_parser
// ---------------------------------------------------------------------------
if ($useVendor) {
    $qifParserPath  = $bankImport . '/vendor/ksfraser/qifparser/qif_parser.php';
    $parserLabel    = 'VENDOR (bank_import/vendor/ksfraser/qifparser/qif_parser.php)';
} else {
    $qifParserPath  = $repoRoot . '/qif_parser.php';
    $parserLabel    = 'LOCAL  (ksf_qifparser/qif_parser.php)';
}

if (!file_exists($qifParserPath)) {
    fwrite(STDERR, "Cannot find qif_parser.php at:\n  $qifParserPath\n");
    exit(1);
}

// The qif_parser requires the autoloder; ensure it's loaded before we include it.
if (!class_exists('Ksfraser\\QifParser\\QifParser', false)) {
    $autoload = $repoRoot . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        fwrite(STDERR, "Composer autoloader not found at $autoload\nRun: composer install\n");
        exit(1);
    }
    require_once $autoload;
}

if (!class_exists('qif_parser', false) && !class_exists('QIF_parser', false)) {
    require_once $qifParserPath;
}

// ---------------------------------------------------------------------------
// 3. Build static_data — mirrors import_statements.php lines 528-546 EXACTLY
// ---------------------------------------------------------------------------
// These values simulate a typical CIBC PCMC Visa bank account record.
// Adjust to match your actual FA bank account setup for a more realistic test.
$bank_account = [
    'bank_account_number' => 'PCMC-VISA-001',
    'bank_curr_code'      => 'CAD',
    'account_code'        => '1060',
    'account_type'        => 'CCard',
    'bank_account_name'   => 'CIBC PCMC Visa',
    'bank_charge_act'     => '5400',
];

// NOTE: import_statements.php does NOT pass 'bank_id' or 'date_format'.
$static_data = [
    'account'         => $bank_account['bank_account_number'],
    'account_number'  => $bank_account['bank_account_number'],
    'currency'        => $bank_account['bank_curr_code'],
    'account_code'    => $bank_account['account_code'],
    'account_type'    => $bank_account['account_type'],
    'account_name'    => $bank_account['bank_account_name'],
    'bank_charge_act' => $bank_account['bank_charge_act'],
    // 'bank_id'     => *** NOT PASSED — qif_parser falls back to account_code ***
    // 'date_format' => *** NOT PASSED — qif_parser defaults to 'MDY' ***
];

// ---------------------------------------------------------------------------
// Header
// ---------------------------------------------------------------------------
function hr2(string $c = '-', int $w = 72): void { echo str_repeat($c, $w) . "\n"; }

echo "\n";
hr2('=');
echo "INTEGRATION SIMULATION: import_statements → qif_parser\n";
hr2('=');
echo "Parser : $parserLabel\n";
echo "Mode   : " . ($debugParse ? 'debug=true' : 'debug=false') . "\n";
echo "\n";
echo "Simulated static_data (exact keys import_statements.php passes):\n";
foreach ($static_data as $k => $v) {
    echo sprintf("  %-18s => %s\n", $k, $v);
}
echo "  *** NOTE: 'bank_id' is NOT in static_data — falls back to account_code\n";
echo "  *** NOTE: 'date_format' is NOT in static_data — defaults to 'MDY'\n";

// ---------------------------------------------------------------------------
// Process each file
// ---------------------------------------------------------------------------
$grandSmt_ok  = 0;
$grandSmt_err = 0;
$grandTrz_ok  = 0;
$grandFiles   = 0;

foreach ($files as $filePath) {
    $fname    = basename($filePath);
    $content  = file_get_contents($filePath);

    echo "\n";
    hr2('=');
    echo "FILE: $fname\n";
    hr2('=');

    $smt_ok  = 0;
    $smt_err = 0;
    $trz_ok  = 0;

    // -----------------------------------------------------------------------
    // 4. Call parse() — mirrors import_statements.php line 718
    // -----------------------------------------------------------------------
    $parser = new qif_parser();

    if ($debugParse) {
        ob_start();
    }

    try {
        $statements = $parser->parse($content, $static_data, $debugParse);
    } catch (\Throwable $e) {
        echo "  *** EXCEPTION during parse(): " . $e->getMessage() . "\n";
        echo "  File   : " . $e->getFile() . " line " . $e->getLine() . "\n";
        continue;
    }

    if ($debugParse) {
        $debugOut = ob_get_clean();
        if ($debugOut !== '') {
            echo "\n-- parse() debug output --\n";
            echo $debugOut;
            echo "-- end debug output --\n\n";
        }
    }

    echo "parse() returned " . count($statements) . " statement(s).\n";

    if (empty($statements)) {
        echo "  *** EMPTY RESULT: parse() returned no statements.\n";
        echo "  Possible causes:\n";
        echo "    - QIF file has no transactions\n";
        echo "    - Date parse failure (all dates null → grouped as today but\n";
        echo "      check if 'date_format' should be 'DMY' instead of 'MDY')\n";
        echo "    - Autoloader failed to load Ksfraser\\QifParser\\QifParser\n";
        continue;
    }

    // -----------------------------------------------------------------------
    // 5. Validate loop — mirrors import_statements.php lines 740-760
    // -----------------------------------------------------------------------
    echo "\n";
    echo "-- Replicating import_statements.php validate loop --\n";
    echo "<pre>\n";       // This is what the browser shows inside the <pre> tag

    foreach ($statements as $smt) {
        echo "statement: {$smt->statementId}:";
        if ($smt->validate(false)) {
            $trz_cnt = count($smt->transactions);
            $trz_ok += $trz_cnt;
            $smt_ok++;
            echo " is valid, $trz_cnt transactions\n";
        } else {
            echo " is invalid!!!!!!!!!\n";
            // Replicate the debug re-run that import_statements does
            $smt->validate(true);
            $smt_err++;
        }
    }

    echo "======================================\n";
    echo "Valid statements   : $smt_ok\n";
    echo "Invalid statements : $smt_err\n";
    echo "Total transactions : $trz_ok\n";
    echo "</pre>\n";

    // -----------------------------------------------------------------------
    // 6. Detailed statement + transaction + contact dump
    // -----------------------------------------------------------------------
    echo "\n";
    hr2('-');
    echo "STATEMENT DETAILS\n";
    hr2('-');

    foreach ($statements as $smtId => $smt) {
        echo "\n";
        echo "  Statement ID  : {$smt->statementId}\n";
        echo "  Bank          : {$smt->bank}\n";
        echo "  Account       : {$smt->account}\n";
        echo "  Currency      : {$smt->currency}\n";
        echo "  Timestamp     : {$smt->timestamp}\n";
        echo "  BankId        : {$smt->bankid}\n";
        echo "  AcctId        : {$smt->acctid}\n";
        echo "  StartBalance  : {$smt->startBalance}\n";
        echo "  EndBalance    : {$smt->endBalance}\n";
        echo "  Number        : {$smt->number}\n";
        echo "  Sequence      : {$smt->sequence}\n";
        echo "  Transactions  : " . count($smt->transactions) . "\n";
        echo "\n";

        foreach ($smt->transactions as $i => $trz) {
            $tValid = $trz->validate(false) ? 'VALID' : 'INVALID';
            echo sprintf(
                "    [%3d] %-8s  %s  %s%9.2f  %-30s\n",
                $i + 1,
                $tValid,
                $trz->valueTimestamp,
                $trz->transactionDC === 'D' ? '-' : '+',
                (float) $trz->transactionAmount,
                substr((string) $trz->transactionTitle1, 0, 30)
            );

            // If invalid, show why
            if ($trz->validate(false) === false) {
                $debugFields = ['transactionAmount', 'transactionType', 'transactionCode', 'transactionDC'];
                foreach ($debugFields as $f) {
                    $val = $trz->$f;
                    $empty = ($val == "");
                    echo sprintf("       %-22s = %-20s %s\n", $f, var_export($val, true), $empty ? '<-- EMPTY/ZERO (fails validate)' : '');
                }
            }

            // Contact object
            $contact = $trz->contact ?? null;
            if ($contact !== null && is_object($contact)) {
                echo sprintf("           contact.name   = %s\n", $contact->name  ?? '(null)');
                echo sprintf("           contact.raw    = %s\n", $contact->raw   ?? '(null)');
                echo sprintf("           contact.email  = %s\n", $contact->email ?? '(null)');
                echo sprintf("           contact.phone  = %s\n", $contact->phone ?? '(null)');
            } elseif (isset($trz->contact_id) && $trz->contact_id) {
                echo sprintf("           contact_id     = %d  (set by ContactService)\n", (int) $trz->contact_id);
            } else {
                echo "           contact        = (not set)\n";
            }

            // Optional: full field dump
            if ($dumpFields) {
                $trz->dump();
            }
        }
    }

    $grandSmt_ok  += $smt_ok;
    $grandSmt_err += $smt_err;
    $grandTrz_ok  += $trz_ok;
    $grandFiles++;
}

// ---------------------------------------------------------------------------
// Grand summary
// ---------------------------------------------------------------------------
echo "\n";
hr2('=');
echo sprintf("FILES PROCESSED    : %d\n", $grandFiles);
echo sprintf("Valid statements   : %d\n", $grandSmt_ok);
echo sprintf("Invalid statements : %d\n", $grandSmt_err);
echo sprintf("Total transactions : %d\n", $grandTrz_ok);
echo "\n";

// -----------------------------------------------------------------------
// Diagnostic: show vendor vs local qif_parser.php difference
// -----------------------------------------------------------------------
$vendorQifParser = $bankImport . '/vendor/ksfraser/qifparser/qif_parser.php';
$localQifParser  = $repoRoot   . '/qif_parser.php';

if (file_exists($vendorQifParser) && file_exists($localQifParser)) {
    $vendorHash = md5_file($vendorQifParser);
    $localHash  = md5_file($localQifParser);
    hr2('-');
    echo "VENDOR vs LOCAL qif_parser.php comparison\n";
    hr2('-');
    echo "Local  (this repo) : $localHash\n  $localQifParser\n";
    echo "Vendor (bank_import): $vendorHash\n  $vendorQifParser\n";
    if ($vendorHash === $localHash) {
        echo "Status : IDENTICAL - vendor copy is up to date.\n";
    } else {
        echo "Status : *** DIFFERENT *** — bank_import is running an outdated copy!\n";
        echo "  To sync: copy qif_parser.php to the vendor path, or run composer update.\n";
    }
    echo "\n";
}

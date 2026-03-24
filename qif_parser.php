<?php

/**
 * QIF Parser adapter for ksf_bank_import.
 *
 * Wraps Ksfraser\QifParser\QifParser to produce the same
 * statement / transaction object graph that qfx_parser produces,
 * allowing this module to be dropped into bank_import alongside
 * the existing qfx_parser without any changes to the import flow.
 *
 * Usage in bank_import/includes/parsers.inc:
 * @code
 *   @include_once __DIR__ . '/../vendor/ksfraser/qifparser/qif_parser.php';
 * @endcode
 *
 * static_data keys recognised by parse():
 *   - account_name  : Human-readable bank / account name (string)
 *   - account_code  : GL account code used as accountId (string, e.g. '1060')
 *   - bank_id       : Bank routing / institution ID (string)
 *   - currency      : ISO 4217 currency code (string, default 'CAD')
 *   - date_format   : 'MDY' or 'DMY' (string, default 'MDY')
 *
 * @package  Ksfraser\QifParser
 * @author   Kevin Fraser
 * @since    2026-03-19
 * @requirement FR-1.1
 * @requirement FR-2.1.1
 *
 * @uml
 * start
 * :qif_parser extends parser;
 * :parse($content, $static_data, $debug);
 * :QifParser::parseContent($content) -> QifStatement;
 * :foreach QifTransaction => mapTransaction() => transaction;
 * :group transaction into statement keyed by date;
 * :return array<string, statement>;
 * stop
 * @enduml
 */

// ---------------------------------------------------------------------------
// 1. Autoloader
//    Only required when this file is used stand-alone (tests, CLI scripts).
//    In a bank_import context the Composer autoloader is already loaded.
// ---------------------------------------------------------------------------
if (!class_exists('Ksfraser\\QifParser\\QifParser', false)) {
    $autoloadCandidates = [
        __DIR__ . '/vendor/autoload.php',           // stand-alone / dev install
        __DIR__ . '/../../../autoload.php',          // vendor/ksfraser/qifparser/
        __DIR__ . '/../../autoload.php',             // alternate Composer layout
    ];
    foreach ($autoloadCandidates as $_autoload) {
        if (is_file($_autoload)) {
            require_once $_autoload;
            break;
        }
    }
    unset($_autoload, $autoloadCandidates);
}

// ---------------------------------------------------------------------------
// 2. Fallback base class
//    In bank_import the `parser` abstract class is provided by
//    includes/parser.php.  When this file is loaded stand-alone (e.g. unit
//    tests) we define a minimal stub so the class hierarchy resolves.
// ---------------------------------------------------------------------------
if (!class_exists('parser', false)) {
    /**
     * Minimal stub matching bank_import/includes/parser.php.
     *
     * @requirement FR-1.1
     */
    abstract class parser
    {
        /**
         * Parse raw file content into bank_import data objects.
         *
         * @param string $string      Raw file content.
         * @param array  $static_data Optional configuration hints.
         * @param bool   $debug       Enable debug output.
         * @return mixed
         */
        abstract public function parse($string, $static_data = array(), $debug = false);
    }
}

use Ksfraser\QifParser\QifParser as LibQifParser;

/**
 * Adapter that wraps LibQifParser inside the bank_import parser contract.
 *
 * Implements the same interface as qfx_parser so the import flow can treat
 * QIF files identically to QFX / OFX files.
 *
 * @requirement FR-1.1
 * @requirement FR-2.1.1
 *
 * @uml
 * class qif_parser {
 *   +parse($content, $static_data, $debug): array
 *   -mapTransaction(QifTransaction, accountId, bankId): transaction
 * }
 * note right of qif_parser::mapTransaction
 *   Sets trz->contact (object) with name/raw/payee/memo/email/phone/metadata.
 *   bank_import's transaction class must declare public $contact; for this
 *   to persist (banking_base::__set guards undeclared properties).
 * end note
 * qif_parser --|> parser
 * qif_parser ..> LibQifParser : uses
 * qif_parser ..> statement    : creates
 * qif_parser ..> transaction  : creates
 * @enduml
 */
class qif_parser extends parser
{
    /**
     * Parse raw QIF text and return an array of bank_import statement objects.
     *
     * Transactions are grouped into one statement per unique date so that the
     * result shape matches what qfx_parser produces (one statement per bank
     * account / balance date).
     *
     * @param string $content    Raw QIF file content (from file_get_contents).
     * @param array  $static_data {
     *   @type string account_name  Human-readable bank / account name.
     *   @type string account_code  GL account code (e.g. '1060').
     *   @type string bank_id       Bank routing / institution ID.
     *   @type string currency      ISO 4217 code (default 'CAD').
     *   @type string date_format   'MDY' or 'DMY' (default 'MDY').
     * }
     * @param bool   $debug      Write debug traces when true (mirrors qfx_parser behaviour).
     * @return array<string, statement> Statements keyed by statement ID.
     *
     * @requirement FR-2.1.1
     * @requirement FR-2.1.4
     */
    public function parse($content, $static_data = array(), $debug = true)
    {
        if ($debug) {
            var_dump(__FILE__ . '::' . __LINE__);
        }

        // --- Resolve configuration ----------------------------------------
        $bankId     = $static_data['bank_id']      ?? ($static_data['account_code'] ?? 'QIF');
        $accountId  = $static_data['account_code'] ?? $bankId;
        $currency   = $static_data['currency']     ?? 'CAD';
        $dateFormat = $static_data['date_format']  ?? 'MDY';
        $bankName   = $static_data['account_name'] ?? $bankId;

        // --- Delegate actual QIF parsing to the library -------------------
        $qifParser    = new LibQifParser($bankId, $accountId, $currency, $dateFormat);
        $qifStatement = $qifParser->parseContent($content);

        // --- Map QifTransaction → transaction / statement -----------------
        $smts         = array();
        $statementSeq = 1;

        foreach ($qifStatement->transactions as $qifTrx) {
            $date = $qifTrx->date ?? date('Y-m-d');
            $sid  = $date . '-' . $bankId . '-' . $accountId;

            if (empty($smts[$sid])) {
                $smt               = new statement();
                $smt->bank         = $bankName;
                $smt->account      = $accountId;
                $smt->bankid       = $bankId;
                $smt->acctid       = $accountId;
                $smt->intu_bid     = $bankId;
                $smt->currency     = $qifStatement->currency ?? $currency;
                $smt->timestamp    = $date;
                $smt->startBalance = '0';
                $smt->endBalance   = '0';
                $smt->number       = '00000';
                $smt->sequence     = (string) $statementSeq;
                $smt->statementId  = $sid . '-00000-' . $statementSeq;
                $smts[$sid]        = $smt;
                $statementSeq++;
            }

            $trz = $this->mapTransaction($qifTrx, $accountId, $bankId);

            if ($debug) {
                echo __FILE__ . '::' . __LINE__ . ' ' . print_r($trz, true);
            }

            $smts[$sid]->addTransaction($trz);
        }

        return $smts;
    }

    /**
     * Map a single QifTransaction to a bank_import transaction object.
     *
     * Amount sign convention (matches qfx_parser):
     *   positive QIF amount → transactionDC = 'C' (credit / deposit)
     *   negative QIF amount → transactionDC = 'D' (debit / payment)
     *
     * @param \Ksfraser\QifParser\Entities\QifTransaction $qifTrx   Source entity.
     * @param string                                       $accountId FA account code.
     * @param string                                       $bankId    Bank / institution ID.
     * @return transaction Populated bank_import transaction object.
     *
     * @requirement FR-2.1.3
     */
    private function mapTransaction($qifTrx, string $accountId, string $bankId)
    {
        $trz = new transaction();

        // Dates (QIF stores as YYYY-MM-DD after DateParser normalisation)
        $date                  = $qifTrx->date ?? date('Y-m-d');
        $trz->valueTimestamp   = $date;
        $trz->entryTimestamp   = $date;

        // Amount & direction
        $amount                = (float) $qifTrx->amount;
        $trz->transactionAmount = abs($amount);
        $trz->transactionDC    = ($amount >= 0.0) ? 'C' : 'D';
        $trz->transactionType  = 'TRF';

        // Identifiers
        $trz->fitid              = (string) $qifTrx->fitid;
        $trz->transactionCode    = (string) $qifTrx->fitid;
        $trz->reference          = (string) ($qifTrx->number ?? $qifTrx->fitid);
        $trz->checknumber        = (string) ($qifTrx->number ?? '');
        $trz->transactionCodeDesc = '';

        // Payee / merchant
        $payee                   = (string) ($qifTrx->payee ?? '');
        $trz->account            = $payee;
        $trz->accountName        = $payee;
        $trz->accountName1       = $payee;
        $trz->merchant           = $payee;
        $trz->transactionTitle1  = $payee;

        // Category & memo
        $trz->category           = (string) ($qifTrx->category ?? '');
        $trz->transactionTitle2  = (string) ($qifTrx->category ?? '');
        $trz->memo               = (string) ($qifTrx->memo ?? '');
        $trz->transactionTitle4  = (string) ($qifTrx->memo ?? '');

        // Account context
        $trz->acctid             = $accountId;
        $trz->bankid             = $bankId;
        $trz->intu_bid           = $bankId;

        // --- Contact extraction -------------------------------------------
        // Exposes payee + memo as a lightweight contact object so that the
        // host importer's ContactMatchingService can consume it without
        // re-parsing raw fields.
        //
        // NOTE for bank_import integration: the real `transaction` class
        // in banking.php must declare `public $contact;` for this assignment
        // to survive — banking_base::__set silently drops undeclared props.
        $rawName      = trim((string) ($qifTrx->payee ?? ''));
        $rawMemo      = trim((string) ($qifTrx->memo  ?? ''));
        $trz->contact = (object) [
            'name'     => $rawName !== '' ? $rawName : null,
            'raw'      => $rawName . ($rawMemo !== '' ? ' \u2014 ' . $rawMemo : ''),
            'payee'    => $rawName,
            'memo'     => $rawMemo,
            'email'    => null,
            'phone'    => null,
            'metadata' => (object) [],
        ];

        return $trz;
    }
}

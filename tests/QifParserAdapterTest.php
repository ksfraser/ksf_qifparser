<?php

/**
 * Unit tests for qif_parser — the bank_import adapter.
 *
 * Because qif_parser.php depends on bank_import's global `parser`,
 * `statement`, and `transaction` classes these are stubbed inline below
 * so the suite can run stand-alone without a full bank_import installation.
 *
 * @package  Ksfraser\QifParser\Tests
 * @author   Kevin Fraser
 * @since    2026-03-19
 * @requirement FR-1.1
 * @requirement FR-2.1.1
 * @requirement FR-2.1.3
 * @requirement FR-2.1.4
 */

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

// ---------------------------------------------------------------------------
// Stubs for bank_import global classes
// ---------------------------------------------------------------------------

if (!class_exists('banking_base', false)) {
    /**
     * Minimal banking_base stub (mirrors bank_import/includes/banking.php).
     */
    class banking_base
    {
        public function __set(string $name, $value): void
        {
            $this->$name = $value;
        }

        public function __get(string $name)
        {
            return $this->$name ?? null;
        }
    }
}

if (!class_exists('transaction', false)) {
    /**
     * Minimal transaction stub.
     */
    class transaction extends banking_base
    {
        public $valueTimestamp    = '';
        public $entryTimestamp    = '';
        public $account           = '';
        public $accountName       = '';
        public $accountName1      = '';
        public $accountName2      = '';
        public $transactionType   = '';
        public $transactionCode   = '';
        public $transactionCodeDesc = '';
        public $transactionDC     = '';
        public $transactionAmount = 0;
        public $transactionTitle1 = '';
        public $transactionTitle2 = '';
        public $transactionTitle3 = '';
        public $transactionTitle4 = '';
        public $transactionTitle5 = '';
        public $transactionTitle6 = '';
        public $transactionTitle7 = '';
        public $merchant          = '';
        public $category          = '';
        public $reference         = '';
        public $status            = '';
        public $memo;
        public $sic;
        public $address;
        public $checknumber;
        public $acctid;
        public $fitid;
        public $intu_bid;
        public $bankid;
        // Declared so that banking_base::__set does not silently drop it.
        // In real bank_import, bank_import/includes/banking.php must also
        // declare this property for contact extraction to survive.
        public $contact;
    }
}

if (!class_exists('statement', false)) {
    /**
     * Minimal statement stub.
     */
    class statement extends banking_base
    {
        public $bank         = '';
        public $account      = '';
        public $transactions = [];
        public $currency     = '';
        public $startBalance = 0;
        public $endBalance   = 0;
        public $timestamp    = 0;
        public $number       = '';
        public $sequence     = '';
        public $statementId  = '';
        public $acctid;
        public $fitid;
        public $intu_bid;
        public $bankid;

        public function addTransaction($transaction): void
        {
            $this->transactions[] = $transaction;
        }
    }
}

// Load the adapter (stubs above satisfy the `extends parser` dependency)
require_once __DIR__ . '/../qif_parser.php';

// ---------------------------------------------------------------------------
// Test class
// ---------------------------------------------------------------------------

/**
 * @requirement FR-1.1
 * @requirement FR-2.1.1
 * @requirement FR-2.1.3
 * @requirement FR-2.1.4
 */
#[CoversClass(qif_parser::class)]
class QifParserAdapterTest extends TestCase
{
    /** Minimal valid QIF with one debit and one credit. */
    private const QIF_CONTENT = "!Type:Bank\n"
        . "D01/15/2024\n"
        . "T-250.00\n"
        . "PElectric Company\n"
        . "MHydro bill\n"
        . "LUtilities\n"
        . "N1001\n"
        . "^\n"
        . "D01/20/2024\n"
        . "T1500.00\n"
        . "PSalary Inc\n"
        . "MMontly pay\n"
        . "^\n";

    private array $staticData = [
        'account_name' => 'First Bank',
        'account_code' => '1060',
        'bank_id'      => 'BANK001',
        'currency'     => 'CAD',
        'date_format'  => 'MDY',
    ];

    // ------------------------------------------------------------------
    // parse() — structural tests
    // ------------------------------------------------------------------

    /**
     * parse() returns a non-empty array of statement objects.
     *
     * @requirement FR-2.1.1
     */
    public function testParseReturnsArrayOfStatements(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        foreach ($result as $smt) {
            $this->assertInstanceOf(statement::class, $smt);
        }
    }

    /**
     * Each statement is keyed by date-bankId-accountId.
     *
     * @requirement FR-2.1.1
     */
    public function testStatementKeysContainDateAndIds(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);

        foreach (array_keys($result) as $key) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}-BANK001-1060$/', $key);
        }
    }

    /**
     * Transactions with different dates land in different statements.
     *
     * @requirement FR-2.1.1
     */
    public function testTransactionsSplitByDate(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);

        // One statement per unique date (two dates in the fixture)
        $this->assertCount(2, $result);
    }

    // ------------------------------------------------------------------
    // statement field mapping
    // ------------------------------------------------------------------

    /**
     * @requirement FR-2.1.1
     */
    public function testStatementBankName(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);

        foreach ($result as $smt) {
            $this->assertSame('First Bank', $smt->bank);
        }
    }

    /**
     * @requirement FR-2.1.1
     */
    public function testStatementAccountId(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);

        foreach ($result as $smt) {
            $this->assertSame('1060', $smt->account);
            $this->assertSame('1060', $smt->acctid);
        }
    }

    /**
     * @requirement FR-2.1.1
     */
    public function testStatementBankId(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);

        foreach ($result as $smt) {
            $this->assertSame('BANK001', $smt->bankid);
            $this->assertSame('BANK001', $smt->intu_bid);
        }
    }

    /**
     * @requirement FR-2.1.1
     */
    public function testStatementCurrency(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);

        foreach ($result as $smt) {
            $this->assertSame('CAD', $smt->currency);
        }
    }

    /**
     * statementId follows the expected pattern.
     *
     * @requirement FR-2.1.1
     */
    public function testStatementId(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);

        foreach ($result as $smt) {
            $this->assertStringContainsString('BANK001', $smt->statementId);
            $this->assertStringContainsString('1060', $smt->statementId);
        }
    }

    // ------------------------------------------------------------------
    // transaction field mapping — debit
    // ------------------------------------------------------------------

    private function getDebitTransaction(): transaction
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);
        // Debit is on 2024-01-15
        return $result['2024-01-15-BANK001-1060']->transactions[0];
    }

    /**
     * Negative QIF amount maps to transactionDC='D'.
     *
     * @requirement FR-2.1.3
     */
    public function testDebitTransactionDC(): void
    {
        $trz = $this->getDebitTransaction();
        $this->assertSame('D', $trz->transactionDC);
    }

    /**
     * transactionAmount is always positive (absolute value).
     *
     * @requirement FR-2.1.3
     */
    public function testDebitTransactionAmountIsAbsolute(): void
    {
        $trz = $this->getDebitTransaction();
        $this->assertEqualsWithDelta(250.00, $trz->transactionAmount, 0.001);
    }

    /**
     * @requirement FR-2.1.3
     */
    public function testDebitTransactionType(): void
    {
        $trz = $this->getDebitTransaction();
        $this->assertSame('TRF', $trz->transactionType);
    }

    /**
     * @requirement FR-2.1.3
     */
    public function testDebitPayeeFields(): void
    {
        $trz = $this->getDebitTransaction();
        $this->assertSame('Electric Company', $trz->account);
        $this->assertSame('Electric Company', $trz->accountName);
        $this->assertSame('Electric Company', $trz->accountName1);
        $this->assertSame('Electric Company', $trz->merchant);
        $this->assertSame('Electric Company', $trz->transactionTitle1);
    }

    /**
     * @requirement FR-2.1.3
     */
    public function testDebitMemoAndCategory(): void
    {
        $trz = $this->getDebitTransaction();
        $this->assertSame('Hydro bill', $trz->memo);
        $this->assertSame('Hydro bill', $trz->transactionTitle4);
        $this->assertSame('Utilities', $trz->category);
        $this->assertSame('Utilities', $trz->transactionTitle2);
    }

    /**
     * @requirement FR-2.1.3
     */
    public function testDebitCheckNumber(): void
    {
        $trz = $this->getDebitTransaction();
        $this->assertSame('1001', $trz->checknumber);
    }

    /**
     * @requirement FR-2.1.3
     */
    public function testDebitAccountContext(): void
    {
        $trz = $this->getDebitTransaction();
        $this->assertSame('1060',    $trz->acctid);
        $this->assertSame('BANK001', $trz->bankid);
        $this->assertSame('BANK001', $trz->intu_bid);
    }

    /**
     * @requirement FR-2.1.3
     */
    public function testDebitDates(): void
    {
        $trz = $this->getDebitTransaction();
        $this->assertSame('2024-01-15', $trz->valueTimestamp);
        $this->assertSame('2024-01-15', $trz->entryTimestamp);
    }

    // ------------------------------------------------------------------
    // transaction field mapping — credit
    // ------------------------------------------------------------------

    private function getCreditTransaction(): transaction
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);
        // Credit is on 2024-01-20
        return $result['2024-01-20-BANK001-1060']->transactions[0];
    }

    /**
     * Positive QIF amount maps to transactionDC='C'.
     *
     * @requirement FR-2.1.3
     */
    public function testCreditTransactionDC(): void
    {
        $trz = $this->getCreditTransaction();
        $this->assertSame('C', $trz->transactionDC);
    }

    /**
     * @requirement FR-2.1.3
     */
    public function testCreditTransactionAmount(): void
    {
        $trz = $this->getCreditTransaction();
        $this->assertEqualsWithDelta(1500.00, $trz->transactionAmount, 0.001);
    }

    // ------------------------------------------------------------------
    // static_data fallback behaviour
    // ------------------------------------------------------------------

    /**
     * When bank_id is absent, account_code is used as bankId.
     *
     * @requirement FR-2.1.1
     */
    public function testFallbackBankIdFromAccountCode(): void
    {
        $parser = new qif_parser();
        $data   = ['account_code' => '2000', 'currency' => 'USD'];
        $result = $parser->parse(self::QIF_CONTENT, $data, false);

        foreach ($result as $smt) {
            $this->assertSame('2000', $smt->bankid);
        }
    }

    /**
     * When both bank_id and account_code are absent, 'QIF' is used.
     *
     * @requirement FR-2.1.1
     */
    public function testFallbackBankIdDefault(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, [], false);

        foreach ($result as $smt) {
            $this->assertSame('QIF', $smt->bankid);
        }
    }

    /**
     * When account_name is absent, bankId is used as bank name.
     *
     * @requirement FR-2.1.1
     */
    public function testFallbackBankNameFromBankId(): void
    {
        $parser = new qif_parser();
        $data   = ['bank_id' => 'MYBANK'];
        $result = $parser->parse(self::QIF_CONTENT, $data, false);

        foreach ($result as $smt) {
            $this->assertSame('MYBANK', $smt->bank);
        }
    }

    // ------------------------------------------------------------------
    // Empty content
    // ------------------------------------------------------------------

    /**
     * Parsing empty QIF content returns an empty array (no crash).
     *
     * @requirement FR-2.1.1
     */
    public function testEmptyContentReturnsEmptyArray(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse('', $this->staticData, false);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    // ------------------------------------------------------------------
    // Debug output path (coverage for the debug branches)
    // ------------------------------------------------------------------

    /**
     * parse() with debug=true produces output but still returns statements.
     *
     * @requirement FR-2.1.1
     */
    public function testDebugModeProducesOutput(): void
    {
        $parser = new qif_parser();
        ob_start();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, true);
        $output = ob_get_clean();

        $this->assertIsArray($result);
        $this->assertNotEmpty($output);
    }

    // ------------------------------------------------------------------
    // Contact extraction
    // ------------------------------------------------------------------

    /**
     * mapTransaction() attaches a contact object to each transaction.
     *
     * @requirement FR-2.1.3
     */
    public function testTransactionHasContactObject(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);
        $trz    = array_values($result)[0]->transactions[0];

        $this->assertIsObject($trz->contact);
    }

    /**
     * contact->name matches the payee.
     *
     * @requirement FR-2.1.3
     */
    public function testContactNameMatchesPayee(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);
        $trz    = $result['2024-01-15-BANK001-1060']->transactions[0];

        $this->assertSame('Electric Company', $trz->contact->name);
        $this->assertSame('Electric Company', $trz->contact->payee);
    }

    /**
     * contact->memo matches the memo field.
     *
     * @requirement FR-2.1.3
     */
    public function testContactMemoMatchesMemo(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);
        $trz    = $result['2024-01-15-BANK001-1060']->transactions[0];

        $this->assertSame('Hydro bill', $trz->contact->memo);
    }

    /**
     * contact->raw concatenates payee and memo.
     *
     * @requirement FR-2.1.3
     */
    public function testContactRawConcatenatesPayeeAndMemo(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);
        $trz    = $result['2024-01-15-BANK001-1060']->transactions[0];

        $this->assertStringContainsString('Electric Company', $trz->contact->raw);
        $this->assertStringContainsString('Hydro bill', $trz->contact->raw);
    }

    /**
     * contact->email and contact->phone are null by default (QIF has no contact fields).
     *
     * @requirement FR-2.1.3
     */
    public function testContactEmailAndPhoneAreNull(): void
    {
        $parser = new qif_parser();
        $result = $parser->parse(self::QIF_CONTENT, $this->staticData, false);
        $trz    = $result['2024-01-15-BANK001-1060']->transactions[0];

        $this->assertNull($trz->contact->email);
        $this->assertNull($trz->contact->phone);
    }

    /**
     * When payee is empty, contact->name is null.
     *
     * @requirement FR-2.1.3
     */
    public function testContactNameIsNullWhenPayeeEmpty(): void
    {
        $parser = new qif_parser();
        $qif    = "!Type:Bank\nD01/15/2024\nT-10.00\n^\n";
        $result = $parser->parse($qif, $this->staticData, false);
        $trz    = array_values($result)[0]->transactions[0];

        $this->assertNull($trz->contact->name);
    }

    /**
     * contact->raw contains only payee when memo is absent.
     *
     * @requirement FR-2.1.3
     */
    public function testContactRawWithNoMemo(): void
    {
        $parser = new qif_parser();
        $qif    = "!Type:Bank\nD01/15/2024\nT-10.00\nPOnly Payee\n^\n";
        $result = $parser->parse($qif, $this->staticData, false);
        $trz    = array_values($result)[0]->transactions[0];

        $this->assertSame('Only Payee', $trz->contact->raw);
    }
}

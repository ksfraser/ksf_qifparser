<?php

namespace Ksfraser\QifParser\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\QifParser\Entities\QifTransaction;
use Ksfraser\QifParser\Parsers\AddressParser;
use Ksfraser\QifParser\Parsers\CategoryParser;
use Ksfraser\QifParser\Parsers\MemoParser;
use Ksfraser\QifParser\Parsers\NullParser;
use Ksfraser\QifParser\Parsers\NumberParser;
use Ksfraser\QifParser\QifParser;

/**
 * Unit tests for the simple SRP parsers and the NullParser (Null Object Pattern).
 *
 * Covers MemoParser, NumberParser, CategoryParser, and NullParser directly,
 * as well as verifying that unknown QIF tags are silently discarded through the
 * full-stack integration path.
 *
 * @requirement FR-1.1
 * @requirement FR-2.1.1
 */
class SimpleParserTest extends TestCase
{
    // -----------------------------------------------------------------------
    // AddressParser
    // -----------------------------------------------------------------------

    /**
     * @requirement FR-2.1.1
     * Tests that AddressParser initialises payeeDetails when not already set
     * and sets the first address line (covers the null-init guard branch).
     */
    public function testAddressParserInitialisesPayeeDetailsAndAppendsLine(): void
    {
        $parser = new AddressParser();
        $transaction = new QifTransaction();

        // payeeDetails is null — AddressParser must initialise it
        $this->assertNull($transaction->payeeDetails);
        $parser->parse('123 Fake St', $transaction);

        $this->assertNotNull($transaction->payeeDetails);
        $this->assertInstanceOf(\Ksfraser\Contact\DTO\ContactData::class, $transaction->payeeDetails);
        $this->assertEquals('123 Fake St', $transaction->payeeDetails->address_line_1);
    }

    /**
     * @requirement FR-2.1.1
     * Tests that multiple AddressParser calls fill address_line_1 then address_line_2.
     */
    public function testAddressParserAppendsMultipleLines(): void
    {
        $parser = new AddressParser();
        $transaction = new QifTransaction();

        $parser->parse('123 Fake St', $transaction);
        $parser->parse('Anytown ON', $transaction);
        $parser->parse('K1A 0B1', $transaction);

        $this->assertEquals('123 Fake St', $transaction->payeeDetails->address_line_1);
        $this->assertEquals('Anytown ON, K1A 0B1', $transaction->payeeDetails->address_line_2);
    }

    // -----------------------------------------------------------------------
    // MemoParser
    // -----------------------------------------------------------------------

    /**
     * @requirement FR-2.1.1
     */
    public function testMemoParserSetsMemo(): void
    {
        $parser = new MemoParser();
        $transaction = new QifTransaction();

        $parser->parse('Weekly groceries', $transaction);

        $this->assertEquals('Weekly groceries', $transaction->memo);
    }

    // -----------------------------------------------------------------------
    // NumberParser
    // -----------------------------------------------------------------------

    /**
     * @requirement FR-2.1.1
     */
    public function testNumberParserSetsNumber(): void
    {
        $parser = new NumberParser();
        $transaction = new QifTransaction();

        $parser->parse('12345', $transaction);

        $this->assertEquals('12345', $transaction->number);
    }

    // -----------------------------------------------------------------------
    // CategoryParser
    // -----------------------------------------------------------------------

    /**
     * @requirement FR-2.1.1
     */
    public function testCategoryParserSetsCategory(): void
    {
        $parser = new CategoryParser();
        $transaction = new QifTransaction();

        $parser->parse('Groceries', $transaction);

        $this->assertEquals('Groceries', $transaction->category);
    }

    // -----------------------------------------------------------------------
    // NullParser — Null Object Pattern
    // -----------------------------------------------------------------------

    /**
     * @requirement FR-1.1
     * Verifies the NullParser is a safe no-op: it must not throw, and it must
     * leave the transaction completely unmodified.
     */
    public function testNullParserDoesNothing(): void
    {
        $parser = new NullParser();
        $transaction = new QifTransaction();
        $transaction->amount = 99.99;

        $parser->parse('some unknown content', $transaction);

        // Transaction is completely unchanged
        $this->assertEquals(99.99, $transaction->amount);
        $this->assertNull($transaction->memo);
        $this->assertNull($transaction->payee);
    }

    /**
     * @requirement FR-1.1
     * Verifies that an unrecognised QIF tag (e.g. 'C' for cleared status)
     * is silently discarded through the full parser stack without error,
     * and does not corrupt any other field on the transaction.
     */
    public function testUnknownTagIsDiscardedByQifParser(): void
    {
        // 'C' (cleared/reconciled flag) is a valid QIF tag we haven't modelled
        $qifContent = "!Type:Bank\n" .
            "D03/19'26\n" .
            "T-50.00\n" .
            "PWalmart\n" .
            "C*\n" .   // cleared status — unknown to our parser
            "^\n";

        $parser = new QifParser('B', 'A');
        $statement = $parser->parseContent($qifContent);

        $this->assertCount(1, $statement->transactions);
        $t = $statement->transactions[0];
        $this->assertEquals('Walmart', $t->payee);
        $this->assertEquals(-50.00, $t->amount);
        // 'C' tag should have been silently discarded — no exception thrown
    }
}

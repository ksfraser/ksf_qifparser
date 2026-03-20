<?php

namespace Ksfraser\QifParser\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\QifParser\QifParser;

class QifParserTest extends TestCase
{
    /**
     * @requirement FR-1.1
     * @requirement FR-2.1.1
     */
    public function testParseStandardBankFile()
    {
        $qifContent = "!Type:Bank\n" .
            "D03/19'26\n" .
            "T-50.00\n" .
            "PWalmart\n" .
            "MWeekly groceries\n" .
            "^\n" .
            "D03/20'26\n" .
            "T2000.00\n" .
            "PSalary\n" .
            "^\n";

        $parser = new QifParser('BANK123', 'ACC456', 'USD', 'MDY');
        $statement = $parser->parseContent($qifContent);

        $this->assertEquals('BANK123', $statement->bankId);
        $this->assertEquals('ACC456', $statement->accountId);
        $this->assertEquals('USD', $statement->currency);
        
        $this->assertCount(2, $statement->transactions);
        
        $t1 = $statement->transactions[0];
        $this->assertEquals('2026-03-19', $t1->date);
        $this->assertEquals(-50.00, $t1->amount);
        $this->assertEquals('Walmart', $t1->payee);
        $this->assertEquals('Weekly groceries', $t1->memo);
        $this->assertNotNull($t1->fitid, "Parser should automatically generate FITID.");

        $t2 = $statement->transactions[1];
        $this->assertEquals('2026-03-20', $t2->date);
        $this->assertEquals(2000.00, $t2->amount);
    }

    /**
     * @requirement FR-2.1.3
     * Tests that amounts with a leading dollar sign (e.g. T-$49.25) are parsed correctly.
     * This is the format used in Canadian bank QIF exports.
     */
    public function testParseAmountWithDollarSignPrefix()
    {
        $qifContent = "!Type:CCard\n" .
            "D01/23/2017\n" .
            "T-\$49.25\n" .
            "PSome Payee\n" .
            "^\n";

        $parser = new QifParser('B', 'A', 'CAD', 'MDY');
        $statement = $parser->parseContent($qifContent);

        $this->assertCount(1, $statement->transactions);
        $this->assertEquals(-49.25, $statement->transactions[0]->amount);
    }

    /**
     * @requirement FR-2.1.4
     */
    public function testParseSplitTransaction()
    {
        $qifContent = "!Type:Bank\n" .
            "D03/19'26\n" .
            "T-100.00\n" .
            "PWalmart\n" .
            "SGroceries\n" .
            "EFood\n" .
            "$-60.00\n" .
            "SHousehold\n" .
            "ESoap\n" .
            "$-40.00\n" .
            "^\n";

        $parser = new QifParser('B1', 'A1', 'USD', 'MDY');
        $statement = $parser->parseContent($qifContent);

        $this->assertCount(1, $statement->transactions);
        $t = $statement->transactions[0];
        
        $this->assertCount(2, $t->splits);
        $this->assertEquals(-60.00, $t->splits[0]->amount);
        $this->assertEquals('Groceries', $t->splits[0]->category);
        $this->assertEquals('Household', $t->splits[1]->category);
        
        $this->assertTrue($t->validateSplits());
    }
}

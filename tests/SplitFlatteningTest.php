<?php

namespace Ksfraser\QifParser\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\QifParser\Entities\QifTransaction;
use Ksfraser\QifParser\Entities\QifStatement;
use Ksfraser\QifParser\Services\StatementFlattener;

class SplitFlatteningTest extends TestCase
{
    /**
     * @requirement FR-2.1.4
     */
    public function testFlattenSplitTransaction()
    {
        $flattener = new StatementFlattener();
        $statement = new QifStatement();
        $statement->bankId = 'BANK1';
        $statement->accountId = 'ACC1';

        $transaction = new QifTransaction();
        $transaction->date = '2026-03-19';
        $transaction->payee = 'Walmart';
        $transaction->amount = -100.00;
        $transaction->fitid = 'ABC1';

        // Add 2 splits
        $transaction->addSplit(-60.00, 'Groceries', 'Food items');
        $transaction->addSplit(-40.00, 'Household', 'Soap');

        $statement->transactions[] = $transaction;

        $rows = $flattener->flatten($statement);

        // Should have 3 rows: 1 Summary + 2 Splits
        $this->assertCount(3, $rows);

        // Row 0 is summary
        $this->assertEquals('ABC1', $rows[0]['fitid']);
        $this->assertEquals('SPLIT_SUMMARY', $rows[0]['type']);
        $this->assertEquals(-100.00, $rows[0]['amount']);

        // Row 1 is first split
        $this->assertEquals('ABC1-SPLIT-0', $rows[1]['fitid']);
        $this->assertEquals('SPLIT-DEBIT', $rows[1]['type']);
        $this->assertEquals(-60.00, $rows[1]['amount']);
        $this->assertEquals('Split: Groceries', $rows[1]['name']);
        
        // Row 2 is second split
        $this->assertEquals('ABC1-SPLIT-1', $rows[2]['fitid']);
        $this->assertEquals(-40.00, $rows[2]['amount']);
        $this->assertEquals('Split: Household', $rows[2]['name']);
    }
}

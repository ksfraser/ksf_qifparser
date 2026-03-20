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

    /**
     * @requirement FR-2.1.4
     * Tests that a transaction with no splits produces a single row of type TRANSACTION.
     */
    public function testFlattenNonSplitTransaction(): void
    {
        $flattener = new StatementFlattener();
        $statement = new QifStatement();
        $statement->bankId = 'BNK';
        $statement->accountId = 'ACC';

        $transaction = new QifTransaction();
        $transaction->date = '2026-03-20';
        $transaction->payee = 'Salary';
        $transaction->amount = 2000.00;
        $transaction->fitid = 'SAL1';

        $statement->transactions[] = $transaction;

        $rows = $flattener->flatten($statement);

        $this->assertCount(1, $rows);
        $this->assertEquals('TRANSACTION', $rows[0]['type']);
        $this->assertEquals(2000.00, $rows[0]['amount']);
        $this->assertEquals('SAL1', $rows[0]['fitid']);
    }

    /**
     * @requirement FR-2.1.4
     * Tests that a split with a positive amount is assigned type SPLIT-CREDIT.
     */
    public function testFlattenSplitCreditType(): void
    {
        $flattener = new StatementFlattener();
        $statement = new QifStatement();
        $statement->bankId = 'BNK';
        $statement->accountId = 'ACC';

        $transaction = new QifTransaction();
        $transaction->date = '2026-03-21';
        $transaction->payee = 'Refund';
        $transaction->amount = 50.00;
        $transaction->fitid = 'REF1';

        $transaction->addSplit(50.00, 'Refunds', 'Store refund');

        $statement->transactions[] = $transaction;

        $rows = $flattener->flatten($statement);

        // Row 0 = SPLIT_SUMMARY, Row 1 = SPLIT-CREDIT (positive amount)
        $this->assertCount(2, $rows);
        $this->assertEquals('SPLIT_SUMMARY', $rows[0]['type']);
        $this->assertEquals('SPLIT-CREDIT', $rows[1]['type']);
        $this->assertEquals(50.00, $rows[1]['amount']);
    }
}

<?php

namespace Ksfraser\QifParser\Tests;

use PHPUnit\Framework\Attributes\CoversMethod;
use PHPUnit\Framework\TestCase;
use Ksfraser\QifParser\Entities\QifTransaction;
use Ksfraser\QifParser\Entities\QifStatement;

/**
 * @requirement FR-2.1.2
 * @requirement FR-2.1.3
 * @requirement FR-2.1.4
 */
#[CoversMethod(\Ksfraser\QifParser\Entities\QifTransaction::class, 'validateSplits')]
#[CoversMethod(\Ksfraser\QifParser\Entities\QifTransaction::class, 'addSplit')]
#[CoversMethod(\Ksfraser\QifParser\Entities\QifStatement::class, 'addTransaction')]
class EntityTest extends TestCase
{
    /**
     * @requirement FR-2.1.4
     */
    public function testSplitValidationPasses()
    {
        $transaction = new QifTransaction();
        $transaction->amount = 100.00;
        $transaction->addSplit(60.00, 'Office', 'Supplies');
        $transaction->addSplit(40.00, 'Rent', 'Workspace');

        $this->assertTrue($transaction->validateSplits(), "Split sum should equal total transaction amount.");
    }

    /**
     * @requirement FR-2.1.4
     */
    public function testSplitValidationFails()
    {
        $transaction = new QifTransaction();
        $transaction->amount = 100.00;
        $transaction->addSplit(60.00, 'Office', 'Supplies');
        $transaction->addSplit(30.00, 'Rent', 'Error Case');

        $this->assertFalse($transaction->validateSplits(), "Split sum mismatch should fail validation.");
    }

    /**
     * @requirement FR-2.1.4
     * Tests that validateSplits() returns true when there are no splits (no splits = no mismatch).
     */
    public function testValidateSplitsWithEmptySplits(): void
    {
        $transaction = new QifTransaction();
        $transaction->amount = 50.00;

        $this->assertTrue($transaction->validateSplits(), "A transaction with no splits should pass validation.");
    }

    /**
     * @requirement FR-2.1.2
     */
    public function testStatementEntityCollection()
    {
        $statement = new QifStatement();
        $statement->accountNumber = '123456';
        
        $t1 = new QifTransaction();
        $t1->amount = 10.0;
        
        $t2 = new QifTransaction();
        $t2->amount = 20.0;

        $statement->addTransaction($t1);
        $statement->addTransaction($t2);

        $this->assertCount(2, $statement->transactions);
        $this->assertEquals(10.0, $statement->transactions[0]->amount);
    }
}

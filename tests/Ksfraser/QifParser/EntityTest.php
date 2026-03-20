<?php

namespace Ksfraser\QifParser\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\QifParser\Entities\QifTransaction;
use Ksfraser\QifParser\Entities\QifStatement;

/**
 * @requirement FR-2.1.2
 * @requirement FR-2.1.3
 * @requirement FR-2.1.4
 */
class EntityTest extends TestCase
{
    /**
     * @test
     * @covers \Ksfraser\QifParser\Entities\QifTransaction::validateSplits
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
     * @test
     * @covers \Ksfraser\QifParser\Entities\QifTransaction::validateSplits
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
     * @test
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

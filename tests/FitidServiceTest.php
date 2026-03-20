<?php

namespace Ksfraser\QifParser\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\QifParser\Services\FitidService;
use Ksfraser\QifParser\Entities\QifTransaction;

class FitidServiceTest extends TestCase
{
    /**
     * @requirement FR-2.2.0
     */
    public function testDeterministicFitidGeneration()
    {
        $service = new FitidService();
        $transaction = new QifTransaction();
        $transaction->date = '2026-03-19';
        $transaction->payee = 'Walmart';
        $transaction->amount = -50.25;
        $transaction->fileSequence = 1;

        $bankId = 'BANK123';
        $accountId = 'ACC456';

        $fitid1 = $service->generate($transaction, $accountId, $bankId);
        $fitid2 = $service->generate($transaction, $accountId, $bankId);

        $this->assertEquals($fitid1, $fitid2, "FITID should be deterministic for identical inputs.");
        $this->assertEquals(16, strlen($fitid1), "FITID should be 16 characters matching the ksf standard.");
    }

    /**
     * @requirement FR-2.1.3
     */
    public function testCollisionPreventionWithSequence()
    {
        $service = new FitidService();
        $bankId = 'BANK1';
        $accountId = 'ACC1';

        // Identical same-day transitions
        $transaction1 = new QifTransaction();
        $transaction1->date = '2026-03-19';
        $transaction1->payee = 'Walmart';
        $transaction1->amount = -10.00;
        $transaction1->fileSequence = 1;

        $transaction2 = new QifTransaction();
        $transaction2->date = '2026-03-19';
        $transaction2->payee = 'Walmart';
        $transaction2->amount = -10.00;
        $transaction2->fileSequence = 2;

        $fitid1 = $service->generate($transaction1, $accountId, $bankId);
        $fitid2 = $service->generate($transaction2, $accountId, $bankId);

        $this->assertNotEquals($fitid1, $fitid2, "Same day identical transactions must have unique FITIDs.");
    }
}

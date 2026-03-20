<?php

namespace Ksfraser\QifParser\Entities;

/**
 * @requirement FR-2.1.2
 */
class QifStatement
{
    /** @var string Account Name */
    public $accountName;

    /** @var string Account Number (External Injection) */
    public $accountNumber;

    /** @var string Routing Number (External Injection) */
    public $routingNumber;

    /** @var string Currency Code (External Injection) */
    public $currency;

    /** @var float */
    public $startBalance = 0.0;

    /** @var float */
    public $endBalance = 0.0;

    /** @var QifTransaction[] Array of parsed transactions */
    public $transactions = [];

    /**
     * @param QifTransaction $transaction
     */
    public function addTransaction(QifTransaction $transaction): void
    {
        $this->transactions[] = $transaction;
    }
}

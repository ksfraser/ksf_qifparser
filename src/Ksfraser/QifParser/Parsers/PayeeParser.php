<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\QifTransaction;
use Ksfraser\QifParser\Entities\Payee;

/**
 * SRP Parser for QIF Payee ('P') and Address ('A') Tags
 * 
 * Purpose: Captures multiline address and payer data for 
 * parity with ksf_ofxparser's Payee entity.
 * 
 * @requirement FR-2.1.1 (Payee & Address Support)
 */
class PayeeParser implements ParserInterface
{
    /**
     * @param string $content
     * @param QifTransaction $transaction
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        if (!$transaction->payeeDetails) {
            $transaction->payeeDetails = new Payee();
        }

        // 'P' is Payee name; 'A' (handled in same way) is address line
        if ($transaction->payee === null) {
            $transaction->payee = $content;
        } else {
            $transaction->payeeDetails->addAddressLine($content);
        }
    }
}

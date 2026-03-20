<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\Payee;
use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * SRP Parser for QIF Address ('A') Tags.
 *
 * Purpose: Appends each address line to the transaction's payeeDetails address
 * array. Multiple consecutive 'A' tags accumulate as separate array entries,
 * preserving the full multi-line address from the QIF stream.
 *
 * Initialises payeeDetails if not already present so that A tags before P are
 * handled safely.
 *
 * @requirement FR-2.1.1 (Payee & Address Support)
 */
class AddressParser implements ParserInterface
{
    /**
     * @param string $content The address line text (tag character already stripped)
     * @param QifTransaction $transaction
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        if (!$transaction->payeeDetails) {
            $transaction->payeeDetails = new Payee();
        }

        $transaction->payeeDetails->addAddressLine($content);
    }
}

<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\Contact\DTO\ContactData;
use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * SRP Parser for QIF Payee ('P') Tags.
 *
 * Purpose: Sets the payee name on the transaction and initialises the
 * payeeDetails ContactData DTO if not already present.
 * Address lines ('A') are handled by AddressParser.
 *
 * @requirement FR-2.1.1 (Payee & Address Support)
 */
class PayeeParser implements ParserInterface
{
    /**
     * @param string $content The payee name (tag character already stripped)
     * @param QifTransaction $transaction
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        if (!$transaction->payeeDetails) {
            $transaction->payeeDetails = new ContactData();
        }

        $transaction->payee = $content;
        $transaction->payeeDetails->name = $content;
    }
}

<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\Contact\DTO\ContactData;
use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * SRP Parser for QIF Address ('A') Tags.
 *
 * Purpose: Fills the normalised address fields on the transaction's
 * payeeDetails ContactData DTO. The first 'A' line goes to address_line_1,
 * the second to address_line_2; any further lines are appended to
 * address_line_2 separated by a comma-space.
 *
 * Initialises payeeDetails if not already present so that A tags before P
 * are handled safely.
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
            $transaction->payeeDetails = new ContactData();
        }

        $details = $transaction->payeeDetails;

        if ($details->address_line_1 === '') {
            $details->address_line_1 = $content;
            return;
        }

        if ($details->address_line_2 === '') {
            $details->address_line_2 = $content;
            return;
        }

        $details->address_line_2 .= ', ' . $content;
    }
}

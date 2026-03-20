<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * SRP Parser for QIF Amount ('T') Tags
 * 
 * Purpose: Parses transaction totals from the ASCII/ANSI input.
 * 
 * @requirement FR-2.1.3 (Amount Precision)
 */
class AmountParser implements ParserInterface
{
    /**
     * @param string $content
     * @param QifTransaction $transaction
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        $content = str_replace([',', '$'], '', $content); // Strip thousands separator and currency symbol
        $transaction->amount = (float)$content;
    }
}

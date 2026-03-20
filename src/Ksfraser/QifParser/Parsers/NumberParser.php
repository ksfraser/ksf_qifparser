<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * SRP Parser for QIF Check Number / Action ('N') Tags.
 *
 * @requirement FR-2.1.1
 */
class NumberParser implements ParserInterface
{
    /**
     * @param string $content The check number or action text (tag character already stripped)
     * @param QifTransaction $transaction
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        $transaction->number = $content;
    }
}

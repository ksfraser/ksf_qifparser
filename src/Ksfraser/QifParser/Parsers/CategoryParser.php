<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * SRP Parser for QIF Category / Class ('L') Tags.
 *
 * @requirement FR-2.1.1
 */
class CategoryParser implements ParserInterface
{
    /**
     * @param string $content The category or class text (tag character already stripped)
     * @param QifTransaction $transaction
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        $transaction->category = $content;
    }
}

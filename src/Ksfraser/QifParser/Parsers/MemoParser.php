<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * SRP Parser for QIF Memo ('M') Tags.
 *
 * @requirement FR-2.1.1
 */
class MemoParser implements ParserInterface
{
    /**
     * @param string $content The memo text (tag character already stripped)
     * @param QifTransaction $transaction
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        $transaction->memo = $content;
    }
}

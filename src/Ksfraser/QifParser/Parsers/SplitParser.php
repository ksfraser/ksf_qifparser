<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\QifTransaction;
use Ksfraser\QifParser\Entities\QifSplit;

/**
 * SRP Parser for QIF Split ('S', 'E', '$') Tags
 * 
 * Purpose: Parses the split transaction data (amount, category, description).
 * 
 * @requirement FR-2.1.4 (Split Flattening Support)
 */
class SplitParser implements ParserInterface
{
    /**
     * @param string $content
     * @param QifTransaction $transaction
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        $tag = substr($content, 0, 1);
        $value = trim(substr($content, 1));

        // 'S' always starts a new split entry
        if ($tag === 'S') {
            $this->createNewSplit($transaction);
        } elseif (empty($transaction->splits)) {
            // If E or $ arrive before any S, create a split to attach to
            $this->createNewSplit($transaction);
        }

        $currentSplit = end($transaction->splits);

        switch ($tag) {
            case 'S': // Split Category
                $currentSplit->category = $value;
                break;
            case 'E': // Split Memo/Description
                $currentSplit->memo = $value;
                break;
            case '$': // Split Amount
                $currentSplit->amount = (float)str_replace([',', '$'], '', $value);
                break;
        }
    }

    /**
     * @param QifTransaction $transaction
     * @return void
     */
    private function createNewSplit(QifTransaction $transaction): void
    {
        $transaction->splits[] = new QifSplit();
    }
}

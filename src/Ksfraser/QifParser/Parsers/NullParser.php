<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * Null Object implementation of ParserInterface.
 *
 * Purpose: Provides a safe, explicit no-op handler for any QIF tag that has
 * no registered parser (e.g. C=cleared status, B=budget, Q=quantity, X=tax,
 * numeric memo tags, or any future/unknown QIF extension tags).
 *
 * Using this pattern rather than conditional fallback eliminates the
 * if/else dispatch branch and makes the intent explicit: unrecognised tags
 * are intentionally ignored rather than silently falling through a switch.
 *
 * @requirement FR-1.1 (Architecture & Extensibility – Null Object Pattern)
 */
class NullParser implements ParserInterface
{
    /**
     * Intentional no-op. Unrecognised QIF tags are silently discarded.
     *
     * @param string $content The line content excluding the tag character
     * @param QifTransaction $transaction The in-progress transaction entity
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        // Null Object — deliberately does nothing.
    }
}

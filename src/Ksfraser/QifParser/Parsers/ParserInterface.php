<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * Interface definition for QIF Item Parsers
 * 
 * Purpose: Each class that implements this interface is responsible for 
 * ONE tag in the QIF stream (e.g. 'D' for Date, 'T' for Amount). 
 * This follows the SRP and "Polymorphism over Conditionals" design principles.
 * 
 * @requirement FR-1.1 (Architecture & Extensibility)
 */
interface ParserInterface
{
    /**
     * @param string $content The line content EXCLUDING the tag
     * @param QifTransaction $transaction The in-progress transaction entity
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void;
}

<?php

namespace Ksfraser\QifParser\Parsers;

use Ksfraser\QifParser\Entities\QifTransaction;

/**
 * SRP Parser for QIF Date ('D') Tags
 * 
 * Purpose: Handles date ambiguity according to the Intuit 1997/2006 spec. 
 * Supports MM/DD'YY and DD/MM'YY via explicit configuration or signature detection.
 * 
 * @requirement FR-2.1.2 (Date Format Ambiguity)
 */
class DateParser implements ParserInterface
{
    /** @var string Current format: 'MDY' or 'DMY' */
    private $format = 'MDY';

    /**
     * @param string $format
     */
    public function __construct(string $format = 'MDY')
    {
        $this->format = $format;
    }

    /**
     * @param string $content
     * @param QifTransaction $transaction
     * @return void
     */
    public function parse(string $content, QifTransaction $transaction): void
    {
        $content = str_replace("'", "/", $content); // Handle the ' year separator
        $parts = explode('/', $content);

        // Normalize to YYYY-MM-DD
        if (count($parts) === 3) {
            $year = (int)$parts[2];
            if ($year < 70) {
                $year += 2000;
            } elseif ($year < 100) {
                $year += 1900;
            }

            if ($this->format === 'MDY') {
                $month = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                $day = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
            } else {
                $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
            }

            $transaction->date = "$year-$month-$day";
        }
    }
}

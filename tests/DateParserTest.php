<?php

namespace Ksfraser\QifParser\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\QifParser\Parsers\DateParser;
use Ksfraser\QifParser\Entities\QifTransaction;

class DateParserTest extends TestCase
{
    /**
     * @requirement FR-2.1.2
     */
    public function testParseMdyFormat()
    {
        $parser = new DateParser('MDY');
        $transaction = new QifTransaction();
        
        // Intuit spec uses ' as separator for the year
        $parser->parse("03/19'26", $transaction);
        $this->assertEquals('2026-03-19', $transaction->date);
    }

    /**
     * @requirement FR-2.1.2
     */
    public function testParseDmyFormat()
    {
        $parser = new DateParser('DMY');
        $transaction = new QifTransaction();
        
        $parser->parse("19/03'26", $transaction);
        $this->assertEquals('2026-03-19', $transaction->date);
    }

    public function testFullYearParsing()
    {
        $parser = new DateParser('MDY');
        $transaction = new QifTransaction();
        
        $parser->parse("03/19/2026", $transaction);
        $this->assertEquals('2026-03-19', $transaction->date);
    }
}
